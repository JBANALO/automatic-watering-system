/*
 * Automatic Watering System - ESP32 Firmware
 * 
 * Sensors:
 *   - Soil Moisture Sensor (analog)
 *   - DHT11 (temperature + humidity)
 *   - HC-SR04 Ultrasonic Sensor (water tank level)
 *   - Relay Module (pump control)
 * 
 * Server: https://web-production-b741c.up.railway.app
 * 
 * Required Libraries (install via Arduino Library Manager):
 *   - DHT sensor library by Adafruit
 *   - Adafruit Unified Sensor
 *   - ArduinoJson by Benoit Blanchon
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <DHT.h>
#include <ArduinoJson.h>
#include "driver/gpio.h"

// ─── WiFi Credentials ────────────────────────────────────────────────────────
const char* WIFI_SSID = "PLDTHOMEFIBRpjcdV";
const char* WIFI_PASS = "PLDTWIFIKPDMP";

// ─── Server Config ────────────────────────────────────────────────────────────
const char* SERVER_URL = "http://192.168.1.204/automatic-watering-system";
const char* API_KEY    = "3123400a54782ebfd0f72064f72a452a064cd9383499e269dc209c2d415c41b6";

// ─── Pin Definitions ──────────────────────────────────────────────────────────
#define DHT_PIN       4     // DHT11 data pin
#define DHT_TYPE      DHT11
#define MOISTURE_PIN  34    // Analog (unused - AO is broken)
#define MOISTURE_DO_PIN 32  // Digital D0 output from soil sensor
#define TRIG_PIN      26    // Ultrasonic sensor TRIG
#define ECHO_PIN      27    // Ultrasonic sensor ECHO
#define RELAY_PIN     14    // Water pump relay (GPIO 14, no special functions)

// Relay driven via PN2222A NPN transistor as level shifter (active HIGH from firmware POV)
bool RELAY_ACTIVE_LOW = false;

// ─── Tank Calibration (adjust to your tank) ───────────────────────────────────
#define TANK_HEIGHT_CM 100.0  // Height of your water tank in cm
#define TANK_EMPTY_CM   23.0  // Distance (cm) when tank is empty (measured)
#define TANK_FULL_CM     2.0  // Distance (cm) when tank is full (measured)

// ─── Timing (milliseconds) ────────────────────────────────────────────────────
#define SUBMIT_INTERVAL   3000  // Submit sensor data every 3 seconds
#define POLL_INTERVAL     5000  // Poll commands every 5 seconds

// ─────────────────────────────────────────────────────────────────────────────

DHT dht(DHT_PIN, DHT_TYPE);

unsigned long lastSubmit = 0;
unsigned long lastPoll   = 0;
bool pumpState = false;  // Track actual relay state

void setRelay(bool on) {
    // Driving an NPN transistor base via a 1k current-limiting resistor.
    // Clean digital drive in both states is fine.
    int onLevel = RELAY_ACTIVE_LOW ? LOW : HIGH;
    int offLevel = RELAY_ACTIVE_LOW ? HIGH : LOW;
    pinMode(RELAY_PIN, OUTPUT);
    digitalWrite(RELAY_PIN, on ? onLevel : offLevel);
    pumpState = on;
}

// Forward declarations
void acknowledgeCommand(int command_id, const char* status);

// ─── Sensor Readers ───────────────────────────────────────────────────────────

int readMoisture() {
    int d0 = digitalRead(MOISTURE_DO_PIN);
    // D0: LOW = wet (100%), HIGH = dry (0%)
    return (d0 == LOW) ? 100 : 0;
}

int readTankLevel() {
    digitalWrite(TRIG_PIN, LOW);
    delayMicroseconds(2);
    digitalWrite(TRIG_PIN, HIGH);
    delayMicroseconds(10);
    digitalWrite(TRIG_PIN, LOW);

    long duration = pulseIn(ECHO_PIN, HIGH, 30000UL);
    if (duration == 0) {
        Serial.println("[TANK] No echo — sensor error");
        return -1;
    }

    float distanceCm = duration * 0.034f / 2.0f;
    int percent = (int)map((long)distanceCm, (long)TANK_EMPTY_CM, (long)TANK_FULL_CM, 0, 100);
    return constrain(percent, 0, 100);
}

// ─── API Calls ────────────────────────────────────────────────────────────────

void submitSensorData() {
    float temperature = dht.readTemperature();
    float humidity    = dht.readHumidity();
    int   moisture    = readMoisture();
    int   tank_level  = readTankLevel();

    bool dhtOk = !isnan(temperature) && !isnan(humidity);
    if (!dhtOk) {
        Serial.println("[WARN] DHT11 read failed — skipping temp/humidity");
    }

    WiFiClientSecure client;
    client.setInsecure(); // Skip SSL cert verification (Railway uses valid cert, but no CA bundle on ESP32)

    HTTPClient http;
    String url = String(SERVER_URL) + "/api/hardware.php?action=submit";
    http.begin(client, url);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("X-API-Key", API_KEY);
    http.setTimeout(10000);

    // Build JSON payload
    String body = "{";
    body += "\"moisture\":" + String(moisture);
    if (dhtOk) {
        body += ",\"temperature\":" + String(temperature, 1);
        body += ",\"humidity\":" + String((int)humidity);
    }
    if (tank_level > 0) {  // 0 = sensor error/uncalibrated, skip to avoid false low_tank cutoff
        body += ",\"tank_level\":" + String(tank_level);
    }
    body += ",\"pump_state\":" + String(pumpState ? 1 : 0);
    body += "}";

    Serial.println("[SUBMIT] Payload: " + body);

    int code = http.POST(body);
    if (code > 0) {
        Serial.println("[SUBMIT] HTTP " + String(code) + " -> " + http.getString());
    } else {
        Serial.println("[SUBMIT] Failed: " + http.errorToString(code));
    }
    http.end();
}

void pollAndExecuteCommands() {
    WiFiClientSecure client;
    client.setInsecure();

    HTTPClient http;
    String url = String(SERVER_URL) + "/api/device_control.php?action=poll";
    http.begin(client, url);
    http.addHeader("X-API-Key", API_KEY);
    http.setTimeout(10000);

    int code = http.GET();
    if (code != 200) {
        Serial.println("[POLL] HTTP " + String(code) + " error");
        http.end();
        return;
    }

    String response = http.getString();
    http.end();
    Serial.println("[POLL] Response: " + response);

    StaticJsonDocument<1024> doc;
    DeserializationError err = deserializeJson(doc, response);
    if (err) {
        Serial.println("[POLL] JSON parse error: " + String(err.c_str()));
        return;
    }

    JsonArray commands = doc["commands"].as<JsonArray>();
    if (commands.size() == 0) {
        Serial.println("[POLL] No pending commands");
        return;
    }

    for (JsonObject cmd : commands) {
        int    command_id = cmd["command_id"].as<int>();
        String action     = cmd["action"].as<String>();

        Serial.println("[CMD] action=" + action + " id=" + String(command_id));

        if (action == "turn_on") {
            setRelay(true);
            Serial.println("[RELAY] Pump ON");
        } else if (action == "turn_off") {
            setRelay(false);
            Serial.println("[RELAY] Pump OFF");
        } else {
            Serial.println("[CMD] Unknown action: " + action);
        }

        acknowledgeCommand(command_id, "executed");
    }
}

void acknowledgeCommand(int command_id, const char* status) {
    WiFiClientSecure client;
    client.setInsecure();

    HTTPClient http;
    String url = String(SERVER_URL) + "/api/device_control.php?action=acknowledge";
    http.begin(client, url);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("X-API-Key", API_KEY);
    http.setTimeout(10000);

    String body = "{\"command_id\":" + String(command_id) + ",\"status\":\"" + String(status) + "\"}";
    int code = http.POST(body);
    if (code > 0) {
        Serial.println("[ACK] command_id=" + String(command_id) + " -> HTTP " + String(code));
    } else {
        Serial.println("[ACK] Failed: " + http.errorToString(code));
    }
    http.end();
}

// ─── WiFi ─────────────────────────────────────────────────────────────────────

void connectWiFi() {
    Serial.print("[WIFI] Connecting to ");
    Serial.println(WIFI_SSID);
    WiFi.mode(WIFI_STA);
    WiFi.begin(WIFI_SSID, WIFI_PASS);

    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 30) {
        delay(500);
        Serial.print(".");
        attempts++;
    }

    if (WiFi.status() == WL_CONNECTED) {
        Serial.println("\n[WIFI] Connected! IP: " + WiFi.localIP().toString());
    } else {
        Serial.println("\n[WIFI] Failed to connect. Restarting...");
        ESP.restart();
    }
}

// ─── Setup & Loop ─────────────────────────────────────────────────────────────

void setup() {
    Serial.begin(115200);
    delay(500);

    pinMode(MOISTURE_DO_PIN, INPUT);
    pinMode(TRIG_PIN,  OUTPUT);
    pinMode(ECHO_PIN,  INPUT);
    setRelay(false);

    dht.begin();

    connectWiFi();

    // Run immediately on boot
    submitSensorData();
    pollAndExecuteCommands();
    lastSubmit = millis();
    lastPoll   = millis();
}

void loop() {
    if (WiFi.status() != WL_CONNECTED) {
        Serial.println("[WIFI] Disconnected. Reconnecting...");
        connectWiFi();
    }

    unsigned long now = millis();

    if (now - lastSubmit >= SUBMIT_INTERVAL) {
        lastSubmit = now;
        submitSensorData();
    }

    if (now - lastPoll >= POLL_INTERVAL) {
        lastPoll = now;
        pollAndExecuteCommands();
    }

    delay(100);
}
