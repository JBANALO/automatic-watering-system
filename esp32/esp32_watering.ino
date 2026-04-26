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

// ─── WiFi Credentials ────────────────────────────────────────────────────────
const char* WIFI_SSID = "YOUR_WIFI_SSID";
const char* WIFI_PASS = "YOUR_WIFI_PASSWORD";

// ─── Server Config ────────────────────────────────────────────────────────────
const char* SERVER_URL = "https://web-production-b741c.up.railway.app";
const char* API_KEY    = "YOUR_API_KEY_HERE";  // From device registration in dashboard

// ─── Pin Definitions ──────────────────────────────────────────────────────────
#define DHT_PIN       4     // DHT11 data pin
#define DHT_TYPE      DHT11
#define MOISTURE_PIN  34    // Analog soil moisture sensor (ADC pin)
#define TRIG_PIN      5     // Ultrasonic sensor TRIG
#define ECHO_PIN      18    // Ultrasonic sensor ECHO
#define RELAY_PIN     26    // Relay module IN pin (active LOW)

// ─── Tank Calibration (adjust to your tank) ───────────────────────────────────
#define TANK_EMPTY_CM  20.0  // Distance (cm) when tank is empty
#define TANK_FULL_CM    2.0  // Distance (cm) when tank is full

// ─── Timing (milliseconds) ────────────────────────────────────────────────────
#define SUBMIT_INTERVAL  10000  // Submit sensor data every 10 seconds
#define POLL_INTERVAL     5000  // Poll commands every 5 seconds

// ─────────────────────────────────────────────────────────────────────────────

DHT dht(DHT_PIN, DHT_TYPE);

unsigned long lastSubmit = 0;
unsigned long lastPoll   = 0;

// Forward declarations
void acknowledgeCommand(int command_id, const char* status);

// ─── Sensor Readers ───────────────────────────────────────────────────────────

int readMoisture() {
    int raw = analogRead(MOISTURE_PIN);
    // ESP32 ADC: 0–4095; dry soil = high reading, wet soil = low reading
    int percent = map(raw, 4095, 0, 0, 100);
    return constrain(percent, 0, 100);
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
    if (tank_level >= 0) {
        body += ",\"tank_level\":" + String(tank_level);
    }
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
            digitalWrite(RELAY_PIN, LOW);   // Active LOW relay: LOW = pump ON
            Serial.println("[RELAY] Pump ON");
        } else if (action == "turn_off") {
            digitalWrite(RELAY_PIN, HIGH);  // HIGH = pump OFF
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

    pinMode(TRIG_PIN,  OUTPUT);
    pinMode(ECHO_PIN,  INPUT);
    pinMode(RELAY_PIN, OUTPUT);
    digitalWrite(RELAY_PIN, HIGH); // Pump OFF on boot

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
