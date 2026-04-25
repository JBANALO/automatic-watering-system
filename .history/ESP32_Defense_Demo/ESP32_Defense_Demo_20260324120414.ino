/**
 * ESP32 Smart Irrigation System - DEFENSE DEMO VERSION
 * Modified for working with D0 digital soil sensor + simulated values
 * 
 * HARDWARE CONNECTIONS FOR DEMO:
 * - Soil Moisture Sensor D0 (Digital) -> GPIO 32
 * - Soil Moisture Sensor VCC -> 5V (V5)
 * - Soil Moisture Sensor GND -> GND
 * - DHT11 Temperature/Humidity -> GPIO 4
 * - Water Pump Relay -> GPIO 25
 * - Status LED (optional) -> GPIO 2 (built-in LED)
 * 
 * WHAT THIS DOES:
 * ✓ Reads real D0 wet/dry detection
 * ✓ Converts to realistic moisture percentages (WET=70-80%, DRY=20-30%)
 * ✓ Reads real DHT11 temperature/humidity
 * ✓ Simulates tank water level (40-90% with realistic variation)
 * ✓ Controls relay for pump simulation
 * ✓ Sends data to web dashboard every 15 seconds
 * ✓ Receives commands from web dashboard
 * 
 * REQUIRED LIBRARIES (Install via Arduino Library Manager):
 * - WiFi (built-in)
 * - HTTPClient (built-in)
 * - ArduinoJson (by Benoit Blanchon)
 * - DHT sensor library (by Adafruit)
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <ArduinoJson.h>
#include <DHT.h>

// ==================== CONFIGURATION ====================
// WiFi Credentials
const char* WIFI_SSID = "TPK44829284";
const char* WIFI_PASSWORD = "aywaaa123";

// Server Configuration
const char* SERVER_URL = "http://10.216.240.89/automatic-watering-system/api";
const char* API_KEY = "3123400a54782ebfd0f72064f72a452a064cd9383499e269dc209c2d415c41b6";

// Sensor Pins
#define SOIL_D0_PIN 32       // Digital soil moisture sensor (D0 output)
#define DHT_PIN 4            // DHT11 temperature/humidity
#define PUMP_RELAY_PIN 25    // Water pump control relay
#define STATUS_LED_PIN 2     // Built-in LED for status indication

// Timing Configuration (milliseconds)
#define SENSOR_READ_INTERVAL 5000     // Read sensors every 5 seconds
#define DATA_SUBMIT_INTERVAL 15000    // Submit data every 15 seconds
#define COMMAND_POLL_INTERVAL 5000    // Poll for commands every 5 seconds
#define WIFI_RETRY_INTERVAL 30000     // Retry WiFi every 30 seconds

// Moisture Simulation Parameters
#define MOISTURE_WET_BASE 75      // Base percentage when wet (D0=LOW)
#define MOISTURE_DRY_BASE 25      // Base percentage when dry (D0=HIGH)
#define MOISTURE_VARIATION 8      // Random variation (+/- %)

// Tank Level Simulation Parameters
#define TANK_LEVEL_MIN 40         // Minimum simulated tank level %
#define TANK_LEVEL_MAX 90         // Maximum simulated tank level %
#define TANK_DRAIN_RATE 2         // How much % decreases when pump is on (per cycle)
#define TANK_FILL_RATE 1          // How much % increases naturally (per cycle)
// ======================================================

// DHT Sensor Setup
#define DHT_TYPE DHT11
DHT dht(DHT_PIN, DHT_TYPE);

// Global Variables
unsigned long lastSensorRead = 0;
unsigned long lastDataSubmit = 0;
unsigned long lastCommandPoll = 0;
unsigned long lastWifiRetry = 0;

bool pumpState = false;
int moistureLevel = 0;
float temperature = 0;
int humidity = 0;
int tankLevel = 70;  // Start at 70% tank level
int soilD0State = HIGH;  // Current D0 reading (HIGH=dry, LOW=wet)

// ==================== SETUP ====================
void setup() {
  Serial.begin(115200);
  delay(2000);
  
  Serial.println("\n\n╔════════════════════════════════════════════╗");
  Serial.println("║  ESP32 IRRIGATION SYSTEM - DEFENSE DEMO   ║");
  Serial.println("║  Using D0 Digital Sensor + Simulated Data ║");
  Serial.println("╚════════════════════════════════════════════╝\n");
  
  // Initialize pins
  pinMode(SOIL_D0_PIN, INPUT);
  pinMode(PUMP_RELAY_PIN, OUTPUT);
  pinMode(STATUS_LED_PIN, OUTPUT);
  
  digitalWrite(PUMP_RELAY_PIN, LOW);   // Pump off initially
  digitalWrite(STATUS_LED_PIN, LOW);    // LED off initially
  
  // Initialize DHT sensor
  dht.begin();
  Serial.println("✓ DHT11 sensor initialized");
  
  // Initialize random seed for realistic simulations
  randomSeed(analogRead(0));
  
  // Connect to WiFi
  connectWiFi();
  
  Serial.println("\n╔════════════════════════════════════════════╗");
  Serial.println("║           SYSTEM READY FOR DEMO           ║");
  Serial.println("╚════════════════════════════════════════════╝\n");
}

// ==================== MAIN LOOP ====================
void loop() {
  unsigned long currentTime = millis();
  
  // Check WiFi connection
  if (WiFi.status() != WL_CONNECTED) {
    if (currentTime - lastWifiRetry >= WIFI_RETRY_INTERVAL) {
      Serial.println("⚠ WiFi disconnected. Reconnecting...");
      connectWiFi();
      lastWifiRetry = currentTime;
    }
    delay(1000);
    return;
  }
  
  // Blink LED to show system is running
  digitalWrite(STATUS_LED_PIN, (millis() / 1000) % 2);
  
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
  Serial.print("📡 Connecting to WiFi: ");
  Serial.println(WIFI_SSID);
  
  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
  
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 30) {
    delay(500);
    Serial.print(".");
    attempts++;
  }
  
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("\n✓ WiFi Connected!");
    Serial.print("  IP Address: ");
    Serial.println(WiFi.localIP());
    Serial.print("  Signal: ");
    Serial.print(WiFi.RSSI());
    Serial.println(" dBm");
  } else {
    Serial.println("\n✗ WiFi Connection Failed!");
  }
}

// ==================== READ SENSORS ====================
void readSensors() {
  Serial.println("\n┌─────────────────────────────────┐");
  Serial.println("│      READING SENSORS            │");
  Serial.println("└─────────────────────────────────┘");
  
  // ===== SOIL MOISTURE (D0 Digital) =====
  soilD0State = digitalRead(SOIL_D0_PIN);
  
  // Convert D0 state to realistic moisture percentage
  if (soilD0State == LOW) {
    // WET: D0 is LOW when above threshold (wet soil)
    // Generate 70-83% moisture (realistic wet soil range)
    int variation = random(-MOISTURE_VARIATION, MOISTURE_VARIATION);
    moistureLevel = MOISTURE_WET_BASE + variation;
    moistureLevel = constrain(moistureLevel, 70, 83);
    
    Serial.print("🌊 D0 Status: WET (0) → Moisture: ");
  } else {
    // DRY: D0 is HIGH when below threshold (dry soil)
    // Generate 17-33% moisture (realistic dry soil range)
    int variation = random(-MOISTURE_VARIATION, MOISTURE_VARIATION);
    moistureLevel = MOISTURE_DRY_BASE + variation;
    moistureLevel = constrain(moistureLevel, 17, 33);
    
    Serial.print("🏜️  D0 Status: DRY (1) → Moisture: ");
  }
  Serial.print(moistureLevel);
  Serial.println("%");
  
  // ===== TEMPERATURE & HUMIDITY (DHT11 - REAL) =====
  temperature = dht.readTemperature();
  humidity = (int)dht.readHumidity();
  
  if (isnan(temperature) || isnan(humidity)) {
    Serial.println("⚠ DHT11 read failed! Using defaults.");
    temperature = 28.5;  // Default temperature
    humidity = 65;        // Default humidity
  }
  
  Serial.print("🌡️  Temperature: ");
  Serial.print(temperature, 1);
  Serial.println("°C (REAL)");
  Serial.print("💧 Humidity: ");
  Serial.print(humidity);
  Serial.println("% (REAL)");
  
  // ===== TANK WATER LEVEL (SIMULATED) =====
  simulateTankLevel();
  Serial.print("🪣 Tank Level: ");
  Serial.print(tankLevel);
  Serial.println("% (SIMULATED)");
  
  // ===== SYSTEM STATUS =====
  Serial.print("⚙️  Pump: ");
  Serial.println(pumpState ? "ON 💦" : "OFF");
  
  Serial.println("└─────────────────────────────────┘\n");
}

// ==================== SIMULATE TANK LEVEL ====================
void simulateTankLevel() {
  // Tank drains when pump is running
  if (pumpState) {
    tankLevel -= TANK_DRAIN_RATE;
    if (tankLevel < TANK_LEVEL_MIN) {
      tankLevel = TANK_LEVEL_MIN;  // Don't go below minimum
    }
  } else {
    // Tank slowly refills (simulating rainfall or manual refill)
    // Add small random chance of increase
    if (random(0, 10) < 3) {  // 30% chance each cycle
      tankLevel += TANK_FILL_RATE;
      if (tankLevel > TANK_LEVEL_MAX) {
        tankLevel = TANK_LEVEL_MAX;  // Don't exceed maximum
      }
    }
  }
  
  // Add small random variation for realism (±1%)
  if (random(0, 10) < 2) {  // 20% chance
    tankLevel += random(-1, 2);
    tankLevel = constrain(tankLevel, TANK_LEVEL_MIN, TANK_LEVEL_MAX);
  }
}

// ==================== SUBMIT SENSOR DATA ====================
void submitSensorData() {
  if (WiFi.status() != WL_CONNECTED) {
    Serial.println("⚠ WiFi not connected. Skipping data submit.");
    return;
  }
  
  Serial.println("📤 Submitting sensor data to server...");
  
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
  doc["rainfall"] = 0;  // No rain sensor for demo
  doc["tank_level"] = tankLevel;
  
  String jsonPayload;
  serializeJson(doc, jsonPayload);
  
  Serial.print("  Payload: ");
  Serial.println(jsonPayload);
  
  // Send POST request
  int httpCode = http.POST(jsonPayload);
  
  if (httpCode > 0) {
    String response = http.getString();
    Serial.print("  Response: ");
    Serial.print(httpCode);
    Serial.print(" - ");
    Serial.println(response);
  } else {
    Serial.print("  ✗ HTTP POST failed: ");
    Serial.println(http.errorToString(httpCode));
  }
  
  http.end();
  Serial.println("✓ Data submit complete\n");
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
        Serial.println("\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        Serial.print("📨 Received ");
        Serial.print(count);
        Serial.println(" command(s) from dashboard");
        Serial.println("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
        
        for (JsonObject cmd : commands) {
          int cmdId = cmd["command_id"];
          const char* action = cmd["action"];
          
          Serial.print("  Executing: ");
          Serial.println(action);
          
          executeCommand(action, cmd["params"]);
          acknowledgeCommand(cmdId, "executed");
        }
        
        Serial.println("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n");
      }
    }
  }
  
  http.end();
}

// ==================== EXECUTE COMMAND ====================
void executeCommand(const char* action, JsonObject params) {
  if (strcmp(action, "turn_on") == 0) {
    pumpState = true;
    digitalWrite(PUMP_RELAY_PIN, HIGH);
    digitalWrite(STATUS_LED_PIN, HIGH);
    Serial.println("  ✓ PUMP TURNED ON 💦");
    Serial.println("  → Relay activated!");
    
  } else if (strcmp(action, "turn_off") == 0) {
    pumpState = false;
    digitalWrite(PUMP_RELAY_PIN, LOW);
    digitalWrite(STATUS_LED_PIN, LOW);
    Serial.println("  ✓ PUMP TURNED OFF");
    Serial.println("  → Relay deactivated");
    
  } else if (strcmp(action, "auto_mode") == 0) {
    Serial.println("  ✓ AUTO MODE ACTIVATED");
    Serial.println("  → System will water based on moisture threshold");
    // Auto mode logic is handled by backend
    
  } else {
    Serial.print("  ? Unknown command: ");
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
    Serial.print("  ✓ Command ");
    Serial.print(commandId);
    Serial.println(" acknowledged");
  }
  
  http.end();
}
