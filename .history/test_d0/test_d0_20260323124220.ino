// Test new soil sensor D0 digital output
#define SOIL_D0_PIN 32

void setup() {
  pinMode(SOIL_D0_PIN, INPUT);
  Serial.begin(115200);
  delay(1000);
  Serial.println("\n=== Testing New Soil Sensor D0 ===");
  Serial.println("D0 should be LOW (0) when WET, HIGH (1) when DRY\n");
}

void loop() {
  int d0State = digitalRead(SOIL_D0_PIN);
  
  Serial.print("D0 State: ");
  Serial.print(d0State);
  Serial.print(" | ");
  
  if (d0State == LOW) {
    Serial.println("WET 🌊");
  } else {
    Serial.println("DRY ☀️");
  }
  
  delay(500);
}
