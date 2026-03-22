const int WATER_SENSOR_PIN = 34; // ESP32 ADC input pin

// Replace these after your first dry/wet measurement pass.
int dryValue = 3300;
int wetValue = 1200;

void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println("Water sensor test starting...");
  Serial.println("Place sensor dry, then dip sensing area into water.");
}

void loop() {
  int raw = analogRead(WATER_SENSOR_PIN);

  int percent = map(raw, dryValue, wetValue, 0, 100);
  percent = constrain(percent, 0, 100);

  Serial.print("Raw: ");
  Serial.print(raw);
  Serial.print(" | Wetness: ");
  Serial.print(percent);
  Serial.println("%");

  delay(500);
}
