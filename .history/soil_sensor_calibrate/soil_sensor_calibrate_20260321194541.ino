// Soil Moisture Sensor Calibration Test
// Pin: GPIO 32 (ADC)

#define SOIL_SENSOR_PIN 34

void setup() {
  Serial.begin(115200);
  pinMode(SOIL_SENSOR_PIN, INPUT);
  Serial.println("\n=== Soil Moisture Calibration ===");
  Serial.println("Instructions:");
  Serial.println("1. Keep sensor in AIR (dry) - note the RAW value");
  Serial.println("2. Put sensor in WATER - note the RAW value");
  Serial.println("3. Put sensor in WET SOIL - note the RAW value");
  Serial.println("4. Put sensor in DRY SOIL - note the RAW value");
  Serial.println("\nReading every 1 second...\n");
}

void loop() {
  int rawValue = analogRead(SOIL_SENSOR_PIN);
  
  // Show raw ADC value (0-4095 for ESP32)
  Serial.print("RAW: ");
  Serial.print(rawValue);
  Serial.print(" | ");
  
  // Simple classification based on common ranges
  if (rawValue < 500) {
    Serial.println("State: DRY AIR");
  } else if (rawValue < 1000) {
    Serial.println("State: DRY SOIL");
  } else if (rawValue < 2000) {
    Serial.println("State: MOIST SOIL");
  } else if (rawValue < 3000) {
    Serial.println("State: WET SOIL");
  } else {
    Serial.println("State: WATER");
  }
  
  delay(1000);
}
