/*
 * HC-SR04 Ultrasonic Sensor Test
 * ESP32 Wiring:
 * - VCC → 5V
 * - GND → GND
 * - TRIG → GPIO 26
 * - ECHO → GPIO 27
 */

#define TRIG_PIN 26
#define ECHO_PIN 27

void setup() {
  Serial.begin(115200);
  delay(1000);
  
  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);
  
  Serial.println("\n\nHC-SR04 Ultrasonic Sensor Test");
  Serial.println("================================");
  Serial.println("TRIG: GPIO 26");
  Serial.println("ECHO: GPIO 27");
  Serial.println("\nMeasuring distance...\n");
}

void loop() {
  // Send 10µs pulse to TRIG pin
  digitalWrite(TRIG_PIN, LOW);
  delayMicroseconds(2);
  digitalWrite(TRIG_PIN, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);
  
  // Measure pulse duration on ECHO pin
  long duration = pulseIn(ECHO_PIN, HIGH, 30000); // 30ms timeout
  
  if (duration == 0) {
    Serial.println("ERROR: No echo received! Check wiring.");
  } else {
    // Calculate distance (speed of sound = 343 m/s at 20°C)
    // distance = (duration * speed of sound) / 2
    float distance_cm = (duration * 0.0343) / 2;
    float distance_inches = distance_cm / 2.54;
    
    Serial.print("Distance: ");
    Serial.print(distance_cm, 2);
    Serial.print(" cm  |  ");
    Serial.print(distance_inches, 2);
    Serial.println(" inches");
  }
  
  delay(500); // Read every 500ms
}
