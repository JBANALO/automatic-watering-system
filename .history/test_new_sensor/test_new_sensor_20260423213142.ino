// Test new soil sensor A0 analog output
#define SOIL_A0_PIN 34

// Set these from your own measurement:
// - AIR/DRY reading
// - WATER/WET reading
int dryRaw = 3000;
int wetRaw = 1200;

void setup() {
  Serial.begin(115200);
  delay(1000);
  Serial.println("\n=== Testing New Soil Sensor A0 ===");
  Serial.println("Checking analog readings...\n");
  Serial.print("Calibration dryRaw=");
  Serial.print(dryRaw);
  Serial.print(" wetRaw=");
  Serial.println(wetRaw);
}

void loop() {
  // Average multiple samples to reduce noise spikes.
  long sum = 0;
  const int samples = 8;
  for (int i = 0; i < samples; i++) {
    sum += analogRead(SOIL_A0_PIN);
    delay(5);
  }

  int rawValue = sum / samples;
  float voltage = rawValue * (3.3 / 4095.0);

  // Convert raw to moisture percentage based on calibration points.
  // 0% at dryRaw, 100% at wetRaw (works for both ascending/descending sensors).
  int moisturePercent = map(rawValue, dryRaw, wetRaw, 0, 100);
  moisturePercent = constrain(moisturePercent, 0, 100);
  
  Serial.print("Raw: ");
  Serial.print(rawValue);
  Serial.print(" | Voltage: ");
  Serial.print(voltage, 2);
  Serial.print("V | Moisture: ");
  Serial.print(moisturePercent);
  Serial.print("% | Status: ");
  
  if (rawValue < 20) {
    Serial.println("NO SIGNAL / CHECK WIRING");
  } else if (moisturePercent < 35) {
    Serial.println("DRY ☀️");
  } else if (moisturePercent < 70) {
    Serial.println("MOIST 💧");
  } else {
    Serial.println("WET 🌊");
  }
  
  delay(1000);
}
