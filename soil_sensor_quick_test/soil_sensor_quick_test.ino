#define SOIL_SENSOR_PIN 32

void setup() {
  Serial.begin(115200);
  pinMode(SOIL_SENSOR_PIN, INPUT);
  Serial.println("\n=== Soil Sensor Quick Test ===");
  Serial.println("Pin: GPIO32");
  Serial.println("Reading every 1 second...");
}

void loop() {
  int rawValue = analogRead(SOIL_SENSOR_PIN);
  int percent = map(rawValue, 3300, 1400, 0, 100);
  percent = constrain(percent, 0, 100);

  Serial.print("RAW: ");
  Serial.print(rawValue);
  Serial.print(" | Moisture: ");
  Serial.print(percent);
  Serial.println("%");

  delay(1000);
}
