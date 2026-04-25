/**
 * ESP32 Smart Irrigation System - Hardware Client
 * 
 * This sketch connects your ESP32 to the irrigation backend system.
 * 
 * HARDWARE CONNECTIONS:
 * - Soil Moisture Sensor (Analog) -> GPIO 34
 * - DHT22 Temperature/Humidity -> GPIO 4
 * - Rain Sensor (Digital) -> GPIO 35
 * - Water Pump Relay -> GPIO 25
 * - Ultrasonic Sensor (Tank Level) -> TRIG: GPIO 26, ECHO: GPIO 27
 * 
 * REQUIRED LIBRARIES (Install via Arduino Library Manager):
 * - WiFi (built-in)
 * - HTTPClient (built-in)
 * - ArduinoJson (by Benoit Blanchon)
 * - DHT sensor library (by Adafruit)
 * 
 * SETUP INSTRUCTIONS:
 * 1. Register device in web dashboard (get API_KEY)
 * 2. Update WiFi credentials below
 * 3. Update SERVER_URL to your backend URL
 * 4. Update API_KEY with your device key
 * 5. Upload to ESP32
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <DHT.h>

// ==================== CONFIGURATION ====================
// WiFi Credentials
const char* WIFI_SSID = "PLDTHOMEFIBRpjcdV";
const char* WIFI_PASSWORD = "PLDTWIFIKPDMP";

// Server Configuration
const char* SERVER_URL = "http://192.168.1.204/automatic-watering-system/api";  // Change to your server IP
const char* API_KEY = "3123400a54782ebfd0f72064f72a452a064cd9383499e269dc209c2d415c41b6";  // Get this from device registration

// Sensor Pins
#define MOISTURE_PIN 34      // Analog soil moisture sensor (unused when USE_MOISTURE_DO is enabled)
#define MOISTURE_DO_PIN 32   // Digital D0 output from soil sensor module
#define DHT_PIN 4            // DHT22 temperature/humidity
#define RAIN_PIN 35          // Digital rain sensor
#define PUMP_RELAY_PIN 25    // Water pump control
#define TRIG_PIN 26          // Ultrasonic trigger (tank level)
#define ECHO_PIN 27          // Ultrasonic echo (tank level)

// Most relay modules used with ESP32 are active-low (IN=LOW turns relay ON).
#define RELAY_ACTIVE_LOW 1

// Set to 1 to use D0 digital moisture (recommended when AO is unstable).
#define USE_MOISTURE_DO 1

// Timing Configuration (milliseconds)
#define SENSOR_READ_INTERVAL 5000     // Read sensors every 5 seconds (test mode)
#define DATA_SUBMIT_INTERVAL 15000    // Submit data every 15 seconds (test mode)
#define COMMAND_POLL_INTERVAL 5000    // Poll for commands every 5 seconds (test mode)
#define WIFI_RETRY_INTERVAL 30000     // Retry WiFi every 30 seconds

// Tank Configuration (cm)
#define TANK_HEIGHT 100  // Height of your water tank in cm
// ======================================================

// DHT Sensor Setup (optional - comment out if not using)
#define DHT_TYPE DHT11
DHT dht(DHT_PIN, DHT_TYPE);

// Global Variables
unsigned long lastSensorRead = 0;
unsigned long lastDataSubmit = 0;
unsigned long lastCommandPoll = 0;
unsigned long lastWifiRetry = 0;

bool pumpState = false;
bool manualWateringActive = false;
unsigned long manualWateringEndMs = 0;
int moistureLevel = 0;
float temperature = 0;
int humidity = 0;
int rainfall = 0;
int tankLevel = 100;

void setPumpRelay(bool on) {
  int onLevel = RELAY_ACTIVE_LOW ? LOW : HIGH;
  int offLevel = RELAY_ACTIVE_LOW ? HIGH : LOW;
  digitalWrite(PUMP_RELAY_PIN, on ? onLevel : offLevel);
}

// ==================== SETUP ====================
void setup() {
  Serial.begin(115200);
  Serial.println("\n\n=== ESP32 Irrigation System Starting ===");
  
  // Initialize pins
  pinMode(MOISTURE_PIN, INPUT);
  pinMode(MOISTURE_DO_PIN, INPUT);
  pinMode(RAIN_PIN, INPUT);
  pinMode(PUMP_RELAY_PIN, OUTPUT);
  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);
  
  setPumpRelay(false);  // Pump off initially
  
  // Initialize DHT sensor
  dht.begin();
  
  // Connect to WiFi
  connectWiFi();
  
  Serial.println("=== System Ready ===\n");
}

// ==================== MAIN LOOP ====================
void loop() {
  unsigned long currentTime = millis();

  // Auto-stop relay when manual watering duration expires.
  if (manualWateringActive && (long)(currentTime - manualWateringEndMs) >= 0) {
    manualWateringActive = false;
    pumpState = false;
    setPumpRelay(false);
    Serial.println("✓ Manual watering duration complete - Pump turned OFF");
  }
  
  // Check WiFi connection
  if (WiFi.status() != WL_CONNECTED) {
    if (currentTime - lastWifiRetry >= WIFI_RETRY_INTERVAL) {
      Serial.println("WiFi disconnected. Reconnecting...");
      connectWiFi();
      lastWifiRetry = currentTime;
    }
    delay(1000);
    return;
  }
  
  // Read sensors periodically
  if (currentTime - lastSensorRead >= SENSOR_READ_INTERVAL) {
    readSensors();
    lastSensorRead = currentTime;
  }
  
  // Submit data to server
  if (currentTime - lastDataSubmit >= DATA_SUBMIT_INTERVAL) {
    submitSensorData();
    lastDataSubmit = currentTime;
  }
  
  // Poll for commands
  if (currentTime - lastCommandPoll >= COMMAND_POLL_INTERVAL) {
    pollCommands();
    lastCommandPoll = currentTime;
  }
  
  delay(100);  // Small delay to prevent watchdog issues
}

// ==================== WiFi CONNECTION ====================
void connectWiFi() {
  Serial.print("Connecting to WiFi: ");
  Serial.println(WIFI_SSID);
  
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 20) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\nWiFi Connected!");
    Serial.print("IP Address: ");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("\nWiFi Connection Failed!");
  }
}

// ==================== READ SENSORS ====================
void readSensors() {
  Serial.println("\n--- Reading Sensors ---");
  
  #if USE_MOISTURE_DO
  int d0Value = digitalRead(MOISTURE_DO_PIN);
  moistureLevel = (d0Value == LOW) ? 100 : 0;
  Serial.print("Moisture (D0): ");
  Serial.print(moistureLevel);
  Serial.print("% | D0=");
  Serial.println(d0Value);
  #else
  // Read soil moisture (0-4095 for ESP32, convert to 0-100%)
  int rawMoisture = analogRead(MOISTURE_PIN);
  moistureLevel = map(rawMoisture, 4095, 0, 0, 100);  // Invert: dry=0, wet=100
  moistureLevel = constrain(moistureLevel, 0, 100);
  Serial.print("Moisture: ");
  Serial.print(moistureLevel);
  Serial.println("%");
  #endif
  
  // Read DHT11 temperature and humidity
  temperature = dht.readTemperature();
  humidity = (int)dht.readHumidity();
  
  if (isnan(temperature) || isnan(humidity)) {
    Serial.println("DHT11 read failed!");
    temperature = 0;
    humidity = 0;
  }
  
  Serial.print("Temperature: ");
  Serial.print(temperature);
  Serial.println("°C");
  Serial.print("Humidity: ");
  Serial.print(humidity);
  Serial.println("%");
  
  // Read rain sensor (digital)
  rainfall = digitalRead(RAIN_PIN) == LOW ? 100 : 0;  // LOW = raining
  Serial.print("Rainfall: ");
  Serial.println(rainfall > 0 ? "Yes" : "No");
  
  // Read tank level using ultrasonic sensor
  tankLevel = readTankLevel();
  Serial.print("Tank Level: ");
  Serial.print(tankLevel);
  Serial.println("%");
  
  Serial.println("--- Sensors Read Complete ---");
}

// ==================== TANK LEVEL (ULTRASONIC) ====================
int readTankLevel() {
  // Send ultrasonic pulse
  digitalWrite(TRIG_PIN, LOW);
  delayMicroseconds(2);
  digitalWrite(TRIG_PIN, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);
  
  // Read echo
  long duration = pulseIn(ECHO_PIN, HIGH, 30000);  // Timeout 30ms
  
  if (duration == 0) {
    return 100;  // Default to full if read fails
  }
  
  // Calculate distance in cm
  float distance = duration * 0.034 / 2;
  
  // Convert to percentage (full tank = 0cm distance, empty = TANK_HEIGHT cm)
  int level = map((int)distance, 0, TANK_HEIGHT, 100, 0);
  level = constrain(level, 0, 100);
  
  return level;
}

// ==================== SUBMIT SENSOR DATA ====================
void submitSensorData() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("WiFi not connected. Skipping data submit.");
    return;
  }
  
  Serial.println("\n>>> Submitting sensor data to server...");
  
  HTTPClient http;
  String url = String(SERVER_URL) + "/hardware.php?action=submit";
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-API-Key", API_KEY);
  
  // Create JSON payload
  StaticJsonDocument<256> doc;
  doc["moisture"] = moistureLevel;
  doc["temperature"] = temperature;
  doc["humidity"] = humidity;
  doc["rainfall"] = rainfall;
  doc["tank_level"] = tankLevel;
  
  String jsonPayload;
  serializeJson(doc, jsonPayload);
  
  Serial.print("Payload: ");
  Serial.println(jsonPayload);
  
  // Send POST request
  int httpCode = http.POST(jsonPayload);
  
  if (httpCode > 0) {
    String response = http.getString();
    Serial.print("Response Code: ");
    Serial.println(httpCode);
    Serial.print("Response: ");
    Serial.println(response);
  } else {
    Serial.print("HTTP POST failed: ");
    Serial.println(http.errorToString(httpCode));
  }
  
  http.end();
  Serial.println(">>> Data submit complete\n");
}

// ==================== POLL FOR COMMANDS ====================
void pollCommands() {
  if (WiFi.status() != WL_CONNECTED) {
    return;
  }
  
  HTTPClient http;
  String url = String(SERVER_URL) + "/device_control.php?action=poll";
  http.begin(url);
  http.addHeader("X-API-Key", API_KEY);
  
  int httpCode = http.GET();
  
  if (httpCode == 200) {
    String response = http.getString();
    
    StaticJsonDocument<1024> doc;
    DeserializationError error = deserializeJson(doc, response);
    
    if (!error) {
      JsonArray commands = doc["commands"];
      int count = doc["count"];
      
      if (count > 0) {
        Serial.print("\n<<< Received ");
        Serial.print(count);
        Serial.println(" command(s)");
        
        for (JsonObject cmd : commands) {
          int cmdId = cmd["command_id"];
          const char* action = cmd["action"];
          
          Serial.print("Executing: ");
          Serial.println(action);
          
          executeCommand(action, cmd["params"]);
          acknowledgeCommand(cmdId, "executed");
        }
      }
    }
  }
  
  http.end();
}

// ==================== EXECUTE COMMAND ====================
void executeCommand(const char* action, JsonObject params) {
  if (strcmp(action, "turn_on") == 0) {
    pumpState = true;
    setPumpRelay(true);
    Serial.println("✓ Pump turned ON");

    int durationMinutes = 0;
    if (!params.isNull() && params.containsKey("duration_minutes")) {
      durationMinutes = (int)params["duration_minutes"];
    }

    if (durationMinutes > 0) {
      manualWateringActive = true;
      manualWateringEndMs = millis() + ((unsigned long)durationMinutes * 60000UL);
      Serial.print("✓ Manual watering timer set: ");
      Serial.print(durationMinutes);
      Serial.println(" minute(s)");
    } else {
      manualWateringActive = false;
    }
    
  } else if (strcmp(action, "turn_off") == 0) {
    manualWateringActive = false;
    pumpState = false;
    setPumpRelay(false);
    Serial.println("✓ Pump turned OFF");
    
  } else if (strcmp(action, "auto_mode") == 0) {
    Serial.println("✓ Auto mode activated");
    // Implement auto-watering logic based on moisture threshold
    
  } else {
    Serial.print("? Unknown command: ");
    Serial.println(action);
  }
}

// ==================== ACKNOWLEDGE COMMAND ====================
void acknowledgeCommand(int commandId, const char* status) {
  HTTPClient http;
  String url = String(SERVER_URL) + "/device_control.php?action=acknowledge";
  http.begin(url);
  http.addHeader("Content-Type", "application/json");
  http.addHeader("X-API-Key", API_KEY);
  
  StaticJsonDocument<128> doc;
  doc["command_id"] = commandId;
  doc["status"] = status;
  
  String payload;
  serializeJson(doc, payload);
  
  int httpCode = http.POST(payload);
  
  if (httpCode == 200) {
    Serial.print("✓ Command ");
    Serial.print(commandId);
    Serial.println(" acknowledged");
  }
  
  http.end();
}
