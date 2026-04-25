/*
 * Relay Hardware Test (no server needed)
 *
 * Auto-cycles relay ON/OFF every 2 seconds so you can verify:
 * - green relay LED behavior
 * - click sound
 * - wiring and polarity
 */

#define RELAY_PIN 25
#define RELAY_ACTIVE_LOW 0

void setRelay(bool on) {
  int onLevel = RELAY_ACTIVE_LOW ? LOW : HIGH;
  int offLevel = RELAY_ACTIVE_LOW ? HIGH : LOW;
  digitalWrite(RELAY_PIN, on ? onLevel : offLevel);

  Serial.print("Relay state: ");
  Serial.println(on ? "ON" : "OFF");
}

void setup() {
  Serial.begin(115200);
  delay(500);

  pinMode(RELAY_PIN, OUTPUT);
  setRelay(false);

  Serial.println("=== Relay GPIO25 Auto Test Ready ===");
  Serial.println("Cycle: ON 2s -> OFF 2s");
  Serial.println("If behavior is inverted, set RELAY_ACTIVE_LOW to 1.");
}

void loop() {
  setRelay(true);
  delay(2000);

  setRelay(false);
  delay(2000);
}
