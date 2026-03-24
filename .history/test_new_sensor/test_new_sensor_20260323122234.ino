// Test new soil sensor A0 analog output
#define SOIL_A0_PIN 32

void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println("\n=== Testing New Soil Sensor A0 ===");
  Serial.println("Checking analog readings...\n");
}

void loop() {
  int rawValue = analogRead(SOIL_A0_PIN);
  float voltage = rawValue * (3.3 / 4095.0);
  
  Serial.print("Raw: ");
  Serial.print(rawValue);
  Serial.print(" | Voltage: ");
  Serial.print(voltage, 2);
  Serial.print("V | Status: ");
  
  if (rawValue > 3500) {
    Serial.println("DRY ☀️");
  } else if (rawValue > 2500) {
    Serial.println("MOIST 💧");
  } else {
    Serial.println("WET 🌊");
  }
  
  delay(1000);
}
