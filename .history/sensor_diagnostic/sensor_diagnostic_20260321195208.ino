// ESP32 Soil Sensor Diagnostic Test
// Tests multiple pins and shows voltage readings

void setup() {
  Serial.begin(115200);
  delay(2000);
  Serial.println("\n\n=== ESP32 Sensor Diagnostic ===");
  Serial.println("Testing analog pins for sensor detection...\n");
}

void loop() {
  Serial.println("--- Reading All Analog Pins ---");
  
  // Test common analog pins
  int pin32 = analogRead(32);
  int pin33 = analogRead(33);
  int pin34 = analogRead(34);
  int pin35 = analogRead(35);
  int pin36 = analogRead(36);
  int pin39 = analogRead(39);
  
  Serial.print("GPIO32: ");
  Serial.print(pin32);
  Serial.print(" | ");
  
  Serial.print("GPIO33: ");
  Serial.print(pin33);
  Serial.print(" | ");
  
  Serial.print("GPIO34: ");
  Serial.print(pin34);
  Serial.print(" | ");
  
  Serial.print("GPIO35: ");
  Serial.print(pin35);
  Serial.println();
  
  Serial.print("GPIO36: ");
  Serial.print(pin36);
  Serial.print(" | ");
  
  Serial.print("GPIO39: ");
  Serial.println(pin39);
  
  Serial.println("\n✅ Values should change when sensor is moved between air/water");
  Serial.println("⚠️  All zeros = sensor not connected or not powered\n");
  
  delay(2000);
}
