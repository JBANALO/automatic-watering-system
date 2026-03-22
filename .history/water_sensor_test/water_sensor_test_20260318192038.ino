const int WATER_SENSOR_PIN = 34; // ESP32 ADC input pin

// Replace these after your first dry/wet measurement pass.
int dryValue = 10;
int wetValue = 180;

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
  Serial.print("% | State: ");
  if (percent < 20) {
    Serial.println("DRY");
  } else if (percent < 60) {
    Serial.println("DAMP");
  } else {
    Serial.println("WET");
  }

  delay(500);
}
