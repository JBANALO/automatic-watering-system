/*
 * Relay + Pump Hardware Test (no server needed)
 *
 * Purpose:
 * - Verify relay wiring and pump power path independently from web/app logic.
 * - Use Serial commands:
 *   1 = pump ON
 *   0 = pump OFF
 *   t = toggle
 */

#define RELAY_PIN 25
#define RELAY_ACTIVE_LOW 1

bool pumpOn = false;

void setRelay(bool on) {
  int onLevel = RELAY_ACTIVE_LOW ? LOW : HIGH;
  int offLevel = RELAY_ACTIVE_LOW ? HIGH : LOW;
  digitalWrite(RELAY_PIN, on ? onLevel : offLevel);
  pumpOn = on;

  Serial.print("Pump state: ");
  Serial.println(pumpOn ? "ON" : "OFF");
}

void setup() {
  Serial.begin(115200);
  delay(500);

  pinMode(RELAY_PIN, OUTPUT);
  setRelay(false);

  Serial.println("=== Relay Pump Test Ready ===");
  Serial.println("Send: 1 (ON), 0 (OFF), t (toggle)");
}

void loop() {
  if (Serial.available() > 0) {
    char c = (char)Serial.read();

    if (c == '1') {
      setRelay(true);
    } else if (c == '0') {
      setRelay(false);
    } else if (c == 't' || c == 'T') {
      setRelay(!pumpOn);
    }
  }
}
