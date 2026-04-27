#include <WiFi.h>
#include <WebServer.h>

// ==================== WIFI CONFIG ====================
const char* WIFI_SSID = "YOUR_WIFI_NAME";
const char* WIFI_PASSWORD = "YOUR_WIFI_PASSWORD";
const char* AP_SSID = "ESP32-Soil-Monitor";
const char* AP_PASSWORD = "12345678";

// ==================== PIN CONFIG ====================
#define SOIL_AO_PIN 32
#define SOIL_DO_PIN 35
#define LED_PIN 26
#define RELAY_PIN 25

// ==================== LOGIC CONFIG ====================
// Calibrated from your observed behavior:
// dry around ~2680-2700, wet can drop to ~300-900.
int RAW_DRY = 2690;
int RAW_WET = 700;

// Hysteresis thresholds:
// ON when moisture <= PUMP_ON_THRESHOLD_PERCENT
// OFF when moisture >= PUMP_OFF_THRESHOLD_PERCENT
int PUMP_ON_THRESHOLD_PERCENT = 30;
int PUMP_OFF_THRESHOLD_PERCENT = 70;

bool USE_LED_INDICATOR = true;
bool USE_RELAY_OUTPUT = true; // Enable relay output for hardware pump control.
bool RELAY_ACTIVE_LOW = true;  // Most relay modules are active LOW.

// Virtual input mode for presentation when sensor hardware is unstable.
// Default is OFF for real-time sensor operation.
bool VIRTUAL_MODE = false;
int VIRTUAL_MOISTURE = 55;

// Real-sensor fallback:
// If analog calibration is invalid (dry ~= wet), use DO pin threshold mode.
// Most LM393 soil modules output DO=0 when soil is wet after trim-pot tuning.
bool DO_WET_IS_LOW = true;
bool usingDoFallback = false;

WebServer server(80);

int lastRaw = 0;
int lastMoisture = 0;
int lastDO = 1;
bool virtualPumpOn = false;
unsigned long lastReadMs = 0;

void handleSerialCalibration() {
  while (Serial.available() > 0) {
    char c = (char)Serial.read();
    if (c == 'd' || c == 'D') {
      RAW_DRY = lastRaw;
      Serial.print("CALIBRATION: RAW_DRY set to ");
      Serial.println(RAW_DRY);
    } else if (c == 'w' || c == 'W') {
      RAW_WET = lastRaw;
      Serial.print("CALIBRATION: RAW_WET set to ");
      Serial.println(RAW_WET);
    } else if (c == 'p' || c == 'P') {
      Serial.print("CALIBRATION: RAW_DRY=");
      Serial.print(RAW_DRY);
      Serial.print(" RAW_WET=");
      Serial.println(RAW_WET);
    }
  }
}

int toPercent(int raw) {
  long mapped = map(raw, RAW_DRY, RAW_WET, 0, 100);
  if (mapped < 0) return 0;
  if (mapped > 100) return 100;
  return (int)mapped;
}

void updateVirtualPumpState() {
  if (!virtualPumpOn && lastMoisture <= PUMP_ON_THRESHOLD_PERCENT) {
    virtualPumpOn = true;
  } else if (virtualPumpOn && lastMoisture >= PUMP_OFF_THRESHOLD_PERCENT) {
    virtualPumpOn = false;
  }
}

void applyOutputs() {
  if (USE_LED_INDICATOR) {
    digitalWrite(LED_PIN, virtualPumpOn ? HIGH : LOW);
  }

  if (USE_RELAY_OUTPUT) {
    int relaySignal;
    if (RELAY_ACTIVE_LOW) {
      relaySignal = virtualPumpOn ? LOW : HIGH;
    } else {
      relaySignal = virtualPumpOn ? HIGH : LOW;
    }
    digitalWrite(RELAY_PIN, relaySignal);
  }
}

void readSoil() {
  long sum = 0;
  for (int i = 0; i < 10; i++) {
    sum += analogRead(SOIL_AO_PIN);
    delay(3);
  }

  lastRaw = (int)(sum / 10);
  lastDO = digitalRead(SOIL_DO_PIN);

  if (VIRTUAL_MODE) {
    usingDoFallback = false;
    lastMoisture = VIRTUAL_MOISTURE;
    updateVirtualPumpState();
    applyOutputs();

    Serial.print("Raw=");
    Serial.print(lastRaw);
    Serial.print(" | Moisture=");
    Serial.print(lastMoisture);
    Serial.print("% | DO=");
    Serial.print(lastDO);
    Serial.print(" | VirtualPump=");
    Serial.print(virtualPumpOn ? "ON" : "OFF");
    Serial.print(" | AutoMode=ON | ON<=");
    Serial.print(PUMP_ON_THRESHOLD_PERCENT);
    Serial.print(" OFF>=");
    Serial.print(PUMP_OFF_THRESHOLD_PERCENT);
    Serial.print(" | RelayMode=");
    Serial.print(USE_RELAY_OUTPUT ? "ENABLED" : "DISABLED");
    Serial.print(" | SensorMode=VIRTUAL");
    Serial.print(" | Cal(D/W)=");
    Serial.print(RAW_DRY);
    Serial.print("/");
    Serial.println(RAW_WET);
    return;
  }

  int calSpan = RAW_DRY - RAW_WET;
  if (calSpan < 0) calSpan = -calSpan;
  bool analogCalibrationValid = calSpan >= 80;

  if (analogCalibrationValid) {
    usingDoFallback = false;
    lastMoisture = toPercent(lastRaw);
  } else {
    usingDoFallback = true;
    bool wet = DO_WET_IS_LOW ? (lastDO == LOW) : (lastDO == HIGH);
    lastMoisture = wet ? 100 : 0;
  }

  updateVirtualPumpState();
  applyOutputs();

  Serial.print("Raw=");
  Serial.print(lastRaw);
  Serial.print(" | Moisture=");
  Serial.print(lastMoisture);
  Serial.print("% | DO=");
  Serial.print(lastDO);
  Serial.print(" | VirtualPump=");
  Serial.print(virtualPumpOn ? "ON" : "OFF");
  Serial.print(" | AutoMode=ON | ON<=");
  Serial.print(PUMP_ON_THRESHOLD_PERCENT);
  Serial.print(" OFF>=");
  Serial.print(PUMP_OFF_THRESHOLD_PERCENT);
  Serial.print(" | RelayMode=");
  Serial.print(USE_RELAY_OUTPUT ? "ENABLED" : "DISABLED");
  Serial.print(" | SensorMode=");
  Serial.print(usingDoFallback ? "DO_FALLBACK" : "AO_CALIBRATED");
  Serial.print(" | Cal(D/W)=");
  Serial.print(RAW_DRY);
  Serial.print("/");
  Serial.println(RAW_WET);
}

String htmlPage() {
  String soilStatus = lastMoisture <= PUMP_ON_THRESHOLD_PERCENT ? "DRY" : "OK";
  String pumpText = virtualPumpOn ? "ON" : "OFF";
  String pumpColor = virtualPumpOn ? "#c0392b" : "#1f7a3f";
  String soilColor = lastMoisture <= PUMP_ON_THRESHOLD_PERCENT ? "#c0392b" : "#1f7a3f";

  String html;
  html += "<!DOCTYPE html><html><head>";
  html += "<meta charset='UTF-8'>";
  html += "<meta name='viewport' content='width=device-width,initial-scale=1'>";
  html += "<meta http-equiv='refresh' content='2'>";
  html += "<title>ESP32 Soil Monitor</title>";
  html += "<style>";
  html += "body{font-family:Segoe UI,Arial,sans-serif;background:#f5f8f3;padding:18px;color:#1d2521;}";
  html += "h1{font-size:22px;margin-bottom:8px;}";
  html += ".grid{display:grid;grid-template-columns:1fr;gap:12px;max-width:420px;}";
  html += ".card{background:#fff;border-radius:12px;padding:14px 16px;box-shadow:0 2px 10px rgba(0,0,0,.08);} ";
  html += ".v{font-size:30px;font-weight:700;}";
  html += ".l{font-size:12px;color:#5c6c63;letter-spacing:.5px;text-transform:uppercase;}";
  html += "</style></head><body>";
  html += "<h1>Soil Monitor (No Pump Mode)</h1>";
  html += "<div class='grid'>";

  html += "<div class='card'><div class='v'>" + String(lastMoisture) + "%</div><div class='l'>Moisture</div></div>";
  html += "<div class='card'><div class='v'>" + String(lastRaw) + "</div><div class='l'>Raw Analog</div></div>";
  html += "<div class='card'><div class='v' style='color:" + soilColor + "'>" + soilStatus + "</div><div class='l'>Soil Status</div></div>";
  html += "<div class='card'><div class='v' style='color:" + pumpColor + "'>" + pumpText + "</div><div class='l'>Virtual Pump Decision</div></div>";
  html += "<div class='card'><div class='v' style='font-size:22px'>ON<=" + String(PUMP_ON_THRESHOLD_PERCENT) + "% / OFF>=" + String(PUMP_OFF_THRESHOLD_PERCENT) + "%</div><div class='l'>Auto Thresholds</div></div>";
  html += "<div class='card'><div class='v' style='font-size:22px'>" + String(USE_RELAY_OUTPUT ? "ENABLED" : "DISABLED") + "</div><div class='l'>Relay Output Mode</div></div>";
  html += "<div class='card'><div class='v' style='font-size:22px'>" + String(VIRTUAL_MODE ? "ON" : "OFF") + "</div><div class='l'>Virtual Input Mode</div></div>";
  html += "<div class='card'><div class='v' style='font-size:22px'>" + String(usingDoFallback ? "DO_FALLBACK" : "AO_CALIBRATED") + "</div><div class='l'>Sensor Mode</div></div>";
  html += "<div class='card'><div class='v' style='font-size:22px'>" + String(RAW_DRY) + " / " + String(RAW_WET) + "</div><div class='l'>Calibration RawDry / RawWet</div></div>";
  html += "<div class='card'><div class='v'>" + String(lastDO) + "</div><div class='l'>DO Pin State</div></div>";

  html += "<div class='card'>";
  html += "<div class='l' style='margin-bottom:10px'>Virtual Controls</div>";
  html += "<a href='/virtual?mode=on&m=20' style='display:inline-block;margin:4px;padding:8px 12px;background:#c0392b;color:#fff;border-radius:8px;text-decoration:none'>Dry 20%</a>";
  html += "<a href='/virtual?mode=on&m=70' style='display:inline-block;margin:4px;padding:8px 12px;background:#1f7a3f;color:#fff;border-radius:8px;text-decoration:none'>Wet 70%</a>";
  html += "<a href='/virtual?mode=off' style='display:inline-block;margin:4px;padding:8px 12px;background:#334155;color:#fff;border-radius:8px;text-decoration:none'>Use Real Sensor</a>";
  html += "</div>";

  html += "<div class='card'><div class='l'>Tip: If Sensor Mode shows DO_FALLBACK, slowly turn the sensor module trim-pot until DO changes between dry and wet.</div></div>";

  html += "</div>";
  html += "<p style='font-size:12px;color:#7a887f;margin-top:10px;'>Auto refresh every 2 seconds</p>";
  html += "</body></html>";
  return html;
}

void handleRoot() {
  server.send(200, "text/html", htmlPage());
}

void handleJson() {
  String json = "{";
  json += "\"raw\":" + String(lastRaw) + ",";
  json += "\"moisture\":" + String(lastMoisture) + ",";
  json += "\"do\":" + String(lastDO) + ",";
  json += "\"virtual_pump\":\"" + String(virtualPumpOn ? "ON" : "OFF") + "\",";
  json += "\"relay_mode\":\"" + String(USE_RELAY_OUTPUT ? "ENABLED" : "DISABLED") + "\",";
  json += "\"virtual_mode\":\"" + String(VIRTUAL_MODE ? "ON" : "OFF") + "\",";
  json += "\"sensor_mode\":\"" + String(usingDoFallback ? "DO_FALLBACK" : "AO_CALIBRATED") + "\",";
  json += "\"raw_dry\":" + String(RAW_DRY) + ",";
  json += "\"raw_wet\":" + String(RAW_WET);
  json += "}";
  server.send(200, "application/json", json);
}

void handleVirtualMode() {
  if (server.hasArg("mode")) {
    String mode = server.arg("mode");
    if (mode == "on") {
      VIRTUAL_MODE = true;
      if (server.hasArg("m")) {
        int v = server.arg("m").toInt();
        if (v < 0) v = 0;
        if (v > 100) v = 100;
        VIRTUAL_MOISTURE = v;
      }
    } else if (mode == "off") {
      VIRTUAL_MODE = false;
    }
  }

  server.sendHeader("Location", "/");
  server.send(302, "text/plain", "Redirecting...");
}

void setup() {
  Serial.begin(115200);

  pinMode(SOIL_DO_PIN, INPUT);
  pinMode(LED_PIN, OUTPUT);
  pinMode(RELAY_PIN, OUTPUT);

  digitalWrite(LED_PIN, LOW);

  // Safe relay idle state on boot.
  if (RELAY_ACTIVE_LOW) {
    digitalWrite(RELAY_PIN, HIGH);
  } else {
    digitalWrite(RELAY_PIN, LOW);
  }

  analogSetPinAttenuation(SOIL_AO_PIN, ADC_11db);
  analogReadResolution(12);

  WiFi.mode(WIFI_STA);
  WiFi.begin(WIFI_SSID, WIFI_PASSWORD);

  Serial.print("Connecting to WiFi");
  int attempts = 0;
  while (WiFi.status() != WL_CONNECTED && attempts < 60) {
    delay(500);
    Serial.print(".");
    attempts++;
  }

  Serial.println();
  if (WiFi.status() == WL_CONNECTED) {
    Serial.println("WiFi connected!");
    Serial.print("Open in browser: http://");
    Serial.println(WiFi.localIP());
  } else {
    Serial.println("WiFi not connected. Starting Access Point mode...");
    WiFi.mode(WIFI_AP);
    bool apStarted = WiFi.softAP(AP_SSID, AP_PASSWORD);
    if (apStarted) {
      Serial.print("Connect phone/laptop to WiFi: ");
      Serial.println(AP_SSID);
      Serial.print("AP password: ");
      Serial.println(AP_PASSWORD);
      Serial.print("Open in browser: http://");
      Serial.println(WiFi.softAPIP());
    } else {
      Serial.println("Failed to start Access Point.");
    }
  }

  readSoil();
  lastReadMs = millis();

  server.on("/", handleRoot);
  server.on("/api/status", handleJson);
  server.on("/virtual", handleVirtualMode);
  server.begin();

  Serial.println("Web server started.");
}

void loop() {
  server.handleClient();
  handleSerialCalibration();

  if (millis() - lastReadMs >= 1000) {
    readSoil();
    lastReadMs = millis();
  }
}
