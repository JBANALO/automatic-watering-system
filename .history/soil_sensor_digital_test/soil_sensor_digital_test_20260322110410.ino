// Soil Moisture Sensor - Digital Output (D0) Test
// Pin: GPIO32 (Digital input)

#define SOIL_DIGITAL_PIN 32

void setup() {
  Serial.begin(115200);
  pinMode(SOIL_DIGITAL_PIN, INPUT);
  
  Serial.println("\n\n=== Soil Moisture Digital Test ===");
  Serial.println("D0 Output Test - Adjust potentiometer to set threshold");
  Serial.println("Reading every 1 second...\n");
}

void loop() {
  int digitalValue = digitalRead(SOIL_DIGITAL_PIN);
  
  Serial.print("D0 Signal: ");
  Serial.print(digitalValue);
  Serial.print(" | Status: ");
  
  if (digitalValue == HIGH) {
    Serial.println("DRY (or below threshold) ⚠️");
  } else {
    Serial.println("WET (or above threshold) ✅");
  }
  
  delay(1000);
}
