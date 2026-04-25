# HC-SR04 Ultrasonic Sensor Setup Guide for ESP32

## Overview
The HC-SR04 is an ultrasonic distance sensor commonly used for water level detection in automatic watering systems. This guide provides step-by-step instructions for wiring and configuring it with an ESP32.

## ⚠️ Important: Voltage Divider Requirement
The HC-SR04 ECHO pin outputs 5V, but ESP32 GPIO pins are 3.3V tolerant. **A voltage divider is REQUIRED** to safely reduce the voltage to 3.3V.

---

## Parts Needed

| Component | Quantity | Notes |
|-----------|----------|-------|
| HC-SR04 Ultrasonic Sensor | 1 | 4-pin module |
| ESP32 Development Board | 1 | Any ESP32 variant |
| Resistor 1kΩ | 1 | For voltage divider |
| Resistor 2kΩ | 1 | For voltage divider |
| Breadboard | 1 | Optional but recommended |
| Jumper wires | 6+ | Male-to-male or male-to-female |
| 5V Power Source | 1 | From ESP32 or external |

---

## Wiring Diagram

### HC-SR04 Pin Connections

```
HC-SR04 Sensor
┌──────────────┐
│ VCC  GND TRIG ECHO │
└──────────────┘
  │    │    │    │
  │    │    │    └─── ECHO (with voltage divider)
  │    │    └──────── TRIG → GPIO 26
  │    └───────────── GND → ESP32 GND
  └────────────────── VCC → ESP32 5V
```

### Voltage Divider for ECHO Pin

```
ECHO (5V) ─[1kΩ]─── Node ─── GPIO 27 (ESP32)
                      │
                    [2kΩ]
                      │
                     GND

Voltage Divider Formula:
Vout = Vin × R2 / (R1 + R2)
Vout = 5V × 2k / (1k + 2k)
Vout = 5V × 2/3 = 3.33V (safe for ESP32)
```

---

## Step-by-Step Hardware Setup

### Step 1: Prepare the Breadboard Layout
1. Place the HC-SR04 sensor on one side of the breadboard
2. Place the ESP32 on the other side
3. Ensure power rails are clearly marked (+ and -)

### Step 2: Connect Power Supply
1. Connect HC-SR04 **VCC** (red wire) to ESP32 **5V pin** (or 5V rail on breadboard)
2. Connect HC-SR04 **GND** (black wire) to ESP32 **GND pin** (or GND rail on breadboard)
3. **Verify connections before proceeding**

### Step 3: Connect TRIG Pin (GPIO 26)
1. Connect HC-SR04 **TRIG** pin to a jumper wire
2. Trace the wire to ESP32 **GPIO 26**
3. This pin sends the trigger pulse to start measurement

### Step 4: Build the Voltage Divider for ECHO Pin
1. Take the **1kΩ resistor** and connect it to HC-SR04 **ECHO** pin
2. Connect the other end of the 1kΩ resistor to a **node point** on the breadboard
3. From that **same node point**, connect the **2kΩ resistor** down to **GND**
4. From that **same node point**, connect a jumper wire to ESP32 **GPIO 27**

**Visual representation:**
```
HC-SR04 ECHO ──[1kΩ]──┬──── GPIO 27 (ESP32)
                      │
                    [2kΩ]
                      │
                     GND
```

### Step 5: Double-Check All Connections
- [ ] VCC connected to 5V
- [ ] GND connected to GND (2 connections: sensor GND and voltage divider GND)
- [ ] TRIG connected to GPIO 26
- [ ] ECHO connected to GPIO 27 (through voltage divider)
- [ ] Resistor values are correct (1kΩ and 2kΩ)
- [ ] All jumper wires are firmly seated

---

## ESP32 Arduino Code

### Basic Ultrasonic Distance Reading

```cpp
// HC-SR04 Ultrasonic Sensor Configuration
#define TRIG_PIN 26  // GPIO 26 for trigger
#define ECHO_PIN 27  // GPIO 27 for echo (voltage divider)

// Speed of sound in cm/microsecond at 20°C
#define SPEED_OF_SOUND 0.034
#define CM_TO_INCHES 0.393701

void setup() {
  Serial.begin(115200);
  delay(2000);
  
  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);
  
  Serial.println("HC-SR04 Ultrasonic Sensor Initialized");
}

void loop() {
  float distance = measureDistance();
  
  Serial.print("Distance: ");
  Serial.print(distance);
  Serial.print(" cm (");
  Serial.print(distance * CM_TO_INCHES);
  Serial.println(" inches)");
  
  delay(1000);  // Measure once per second
}

// Function to measure distance
float measureDistance() {
  // Clear the trigger pin
  digitalWrite(TRIG_PIN, LOW);
  delayMicroseconds(2);
  
  // Set trigger pin HIGH for 10 microseconds
  digitalWrite(TRIG_PIN, HIGH);
  delayMicroseconds(10);
  digitalWrite(TRIG_PIN, LOW);
  
  // Measure echo pulse duration (timeout after 30ms)
  unsigned long duration = pulseIn(ECHO_PIN, HIGH, 30000);
  
  // Handle timeout
  if (duration == 0) {
    Serial.println("WARNING: Ultrasonic sensor timeout - no echo received");
    return -1;  // Return -1 to indicate error
  }
  
  // Calculate distance (duration is in microseconds)
  float distance = duration * SPEED_OF_SOUND / 2;
  // Divide by 2 because sound travels to object and back
  
  return distance;
}
```

### Advanced: Multiple Readings with Averaging

```cpp
#define TRIG_PIN 26
#define ECHO_PIN 27
#define SPEED_OF_SOUND 0.034
#define NUM_READINGS 5

float getAverageDistance(int numReadings) {
  float sum = 0;
  int validReadings = 0;
  
  for (int i = 0; i < numReadings; i++) {
    float distance = measureDistance();
    
    // Only count valid readings (distance > 0)
    if (distance > 0) {
      sum += distance;
      validReadings++;
    }
    delay(50);  // Small delay between readings
  }
  
  if (validReadings == 0) {
    return -1;  // All readings failed
  }
  
  return sum / validReadings;
}

void setup() {
  Serial.begin(115200);
  pinMode(TRIG_PIN, OUTPUT);
  pinMode(ECHO_PIN, INPUT);
}

void loop() {
  float avgDistance = getAverageDistance(NUM_READINGS);
  
  if (avgDistance > 0) {
    Serial.print("Average Distance: ");
    Serial.print(avgDistance);
    Serial.println(" cm");
  } else {
    Serial.println("ERROR: Unable to get valid readings");
  }
  
  delay(1000);
}
```

---

## Testing & Troubleshooting

### Test 1: Serial Monitor Output
1. Upload the code to your ESP32
2. Open Serial Monitor (Ctrl+Shift+M in VS Code or 115200 baud)
3. You should see distance measurements every second
4. Hold your hand at different distances from the sensor
5. Distance values should change proportionally

**Expected output:**
```
Distance: 15.30 cm (6.02 inches)
Distance: 20.45 cm (8.05 inches)
Distance: 25.12 cm (9.89 inches)
```

### Test 2: Voltage Measurement (Multimeter)
1. Set multimeter to DC voltage mode
2. Probe the GPIO 27 side of the voltage divider (not the ECHO side)
3. Measured voltage should be **~3.3V** (not 5V)
4. If it reads 5V, **DO NOT connect to ESP32 - fix the voltage divider first**

### Test 3: Range Testing
- **Minimum Distance:** 2-3 cm
- **Maximum Distance:** 400-500 cm
- **Blind Zone:** 0-2 cm (no reading)
- Test from 5cm to 100cm for best accuracy

### Common Issues & Solutions

| Issue | Cause | Solution |
|-------|-------|----------|
| No readings (all -1 or 0) | Loose wiring | Check all connections, especially voltage divider |
| Incorrect distances | Voltage divider wrong | Verify 1kΩ and 2kΩ resistor values with multimeter |
| Erratic readings | EMI interference | Use shorter jumper wires, add capacitor across sensor VCC-GND |
| Always 0cm | ECHO pin not receiving | Test GPIO 27 with multimeter, check resistor connections |
| High variance | Environmental factors | Take multiple readings and average them |
| Sensor gets hot | Short circuit | Check power connections immediately |

### Verification Checklist
- [ ] Serial Monitor shows changing distance values
- [ ] Values are in realistic range (2-400cm)
- [ ] Holding hand closer decreases value
- [ ] Holding hand farther increases value
- [ ] Voltage divider output is ~3.3V
- [ ] No error messages in console

---

## Integration with Automatic Watering System

### Water Level Detection Setup
For water tank level monitoring:

```cpp
// Assume empty tank at ~100cm, full at ~10cm from sensor
#define FULL_TANK_DISTANCE 10.0   // cm
#define EMPTY_TANK_DISTANCE 100.0 // cm

bool isTankFull() {
  float distance = getAverageDistance(5);
  return distance > 0 && distance <= FULL_TANK_DISTANCE;
}

bool isTankEmpty() {
  float distance = getAverageDistance(5);
  return distance > 0 && distance >= EMPTY_TANK_DISTANCE;
}
```

### API Integration
Send sensor data to your backend:
```cpp
void reportWaterLevel() {
  float distance = getAverageDistance(5);
  
  if (distance > 0) {
    // Calculate percentage (0-100)
    float percentage = map(distance, EMPTY_TANK_DISTANCE, FULL_TANK_DISTANCE, 0, 100);
    percentage = constrain(percentage, 0, 100);
    
    // Send to your API endpoint
    // POST to /api/sensors.php with water_level data
  }
}
```

---

## Additional Resources

- **HC-SR04 Datasheet:** Check manufacturer specs for accuracy details
- **ESP32 GPIO Reference:** Ensure GPIO 26 and 27 are available on your board
- **Voltage Divider Calculator:** https://www.ohmslawcalculator.com/voltage-divider-calculator

---

## Safety Notes

⚠️ **Important Safety Warnings:**

1. **Never connect ECHO directly to ESP32** without the voltage divider - this will damage the pin
2. **Use correct resistor values** - 1kΩ and 2kΩ (not 10kΩ or other values)
3. **Keep sensor dry** if using in wet environments (consider waterproof case)
4. **Test voltage divider output before connecting to ESP32**
5. **Power down before making wiring changes**

---

## Version History

- **v1.0** (Apr 24, 2026): Initial setup guide with voltage divider configuration
