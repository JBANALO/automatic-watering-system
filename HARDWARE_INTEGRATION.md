# 🔌 ESP32 Hardware Integration Guide

Complete guide for connecting ESP32 devices to your Smart Irrigation System.

---

## 📋 Table of Contents
1. [Backend Infrastructure](#backend-infrastructure)
2. [Hardware Requirements](#hardware-requirements)
3. [Quick Start](#quick-start)
4. [API Endpoints](#api-endpoints)
5. [ESP32 Setup](#esp32-setup)
6. [Wiring Diagram](#wiring-diagram)
7. [Testing & Troubleshooting](#testing--troubleshooting)

---

## 🔧 Backend Infrastructure

### New Database Tables

Two new tables were automatically created for hardware support:

#### **`devices` Table**
Stores registered ESP32 devices
- `device_id` - Unique device identifier (ESP32_XXXXXX)
- `api_key` - 64-char authentication token
- `zone_id` - Assigned watering zone
- `status` - active/inactive/error
- `last_seen` - Last heartbeat timestamp

#### **`commands` Table**
Queue system for web → ESP32 control
- `command_type` - turn_on/turn_off/auto_mode
- `status` - pending/sent/executed/failed
- `params` - JSON command parameters

### New API Endpoints

| Endpoint | Purpose | Authentication |
|----------|---------|----------------|
| `api/hardware.php` | Submit sensor data | API Key |
| `api/device_control.php` | Poll commands | API Key |
| `api/device_register.php` | Manage devices | Session (User) |

---

## 🛠️ Hardware Requirements

### ESP32 Board
- **Recommended**: ESP32 DevKit V1 or ESP32-WROOM-32
- **WiFi**: 2.4GHz required
- **Power**: 5V via USB or external supply

### Sensors (Optional - use what you have)

| Sensor | Purpose | Type | Pin |
|--------|---------|------|-----|
| Soil Moisture | Measure soil wetness | Analog | GPIO 34 |
| DHT22 | Temperature & Humidity | Digital | GPIO 4 |
| Rain Sensor | Detect rainfall | Digital | GPIO 35 |
| Ultrasonic (HC-SR04) | Tank level | Digital | GPIO 26/27 |

### Actuators

| Component | Purpose | Pin |
|-----------|---------|-----|
| Relay Module | Control water pump | GPIO 25 |
| Water Pump | 5V/12V submersible | Via Relay |

---

## ⚡ Quick Start

### Step 1: Register Device in Web Dashboard

1. **Login to web interface**: `http://localhost/automatic-watering-system/indwx.html`

2. **Open browser console** (F12) and run:
```javascript
fetch('/automatic-watering-system/api/device_register.php?action=register', {
  method: 'POST',
  headers: {'Content-Type': 'application/json'},
  body: JSON.stringify({
    device_name: 'Garden ESP32',
    zone_id: 1,  // Your zone ID
    device_type: 'ESP32'
  })
}).then(r => r.json()).then(data => console.log(data));
```

3. **Copy the API Key** from the response:
```json
{
  "status": "success",
  "device": {
    "api_key": "a1b2c3d4e5f6...64chars",
    "device_id": "ESP32_A1B2C3D4E5F6"
  }
}
```

### Step 2: Configure ESP32 Code

1. **Open** `ESP32_Client.ino` in Arduino IDE

2. **Update WiFi credentials**:
```cpp
const char* WIFI_SSID = "YourWiFiName";
const char* WIFI_PASSWORD = "YourPassword";
```

3. **Update server URL** (use your computer's IP):
```cpp
const char* SERVER_URL = "http://192.168.1.100/automatic-watering-system/api";
```

Find your IP:
- **Windows**: `ipconfig` → Look for "IPv4 Address"
- **Mac/Linux**: `ifconfig` → Look for "inet"

4. **Paste your API Key**:
```cpp
const char* API_KEY = "a1b2c3d4e5f6...paste_your_key_here";
```

### Step 3: Upload to ESP32

1. **Install Arduino IDE** (if not installed)
2. **Add ESP32 board support**:
   - File → Preferences
   - Additional Board URLs: `https://dl.espressif.com/dl/package_esp32_index.json`
   - Tools → Board Manager → Search "ESP32" → Install

3. **Install required libraries**:
   - Sketch → Include Library → Manage Libraries
   - Install: **ArduinoJson** (by Benoit Blanchon)
   - Install: **DHT sensor library** (by Adafruit) - optional

4. **Select board**: Tools → Board → ESP32 Dev Module

5. **Select COM port**: Tools → Port → (your ESP32 port)

6. **Upload**: Click Upload button (→)

### Step 4: Monitor Serial Output

1. Open Serial Monitor: Tools → Serial Monitor
2. Set baud rate to: **115200**
3. You should see:
```
=== ESP32 Irrigation System Starting ===
Connecting to WiFi: YourWiFiName
WiFi Connected!
IP Address: 192.168.1.150
=== System Ready ===

--- Reading Sensors ---
Moisture: 45%
Temperature: 25.5°C
Humidity: 60%
Tank Level: 87%
>>> Submitting sensor data to server...
Response Code: 200
```

---

## 📡 API Endpoints Reference

### 1. Submit Sensor Data (ESP32 → Server)

**Endpoint**: `POST /api/hardware.php?action=submit`

**Headers**:
```
X-API-Key: your_device_api_key
Content-Type: application/json
```

**Request Body**:
```json
{
  "moisture": 45,
  "temperature": 28.5,
  "humidity": 65,
  "rainfall": 0,
  "tank_level": 87
}
```

**Response**:
```json
{
  "status": "success",
  "message": "Sensor data recorded",
  "zone_id": 1,
  "timestamp": "2026-02-25 14:30:00"
}
```

### 2. Poll for Commands (ESP32 polls server)

**Endpoint**: `GET /api/device_control.php?action=poll`

**Headers**:
```
X-API-Key: your_device_api_key
```

**Response** (if commands pending):
```json
{
  "status": "success",
  "commands": [
    {
      "command_id": 123,
      "action": "turn_on",
      "params": {"duration": 300},
      "timestamp": "2026-02-25 14:29:55"
    }
  ],
  "count": 1
}
```

### 3. Acknowledge Command Execution

**Endpoint**: `POST /api/device_control.php?action=acknowledge`

**Headers**:
```
X-API-Key: your_device_api_key
Content-Type: application/json
```

**Request Body**:
```json
{
  "command_id": 123,
  "status": "executed"
}
```

### 4. Device Health Check

**Endpoint**: `GET /api/hardware.php?action=ping`

**Headers**:
```
X-API-Key: your_device_api_key
```

**Response**:
```json
{
  "status": "success",
  "message": "Device connected",
  "server_time": "2026-02-25 14:30:00"
}
```

---

## 🔌 Wiring Diagram

### Basic Setup (Moisture Sensor + Pump)

```
ESP32                    Components
=================================
GPIO 34 ────────────── Moisture Sensor (Analog Out)
                       └─ VCC → 3.3V
                       └─ GND → GND

GPIO 25 ────────────── Relay Module (IN)
                       └─ VCC → 5V
                       └─ GND → GND
                       └─ COM → Pump (+)
                       └─ NO  → Power Supply (+)

Power Supply ────────── Pump (-)
GND ─────────────────── Power Supply (-)
```

### Full Setup (All Sensors)

```
ESP32 Pin    Component           Connection
=========    =========           ==========
GPIO 34      Moisture Sensor     Analog Out
GPIO 4       DHT22               Data Pin
GPIO 35      Rain Sensor         Digital Out
GPIO 26      Ultrasonic          Trigger
GPIO 27      Ultrasonic          Echo
GPIO 25      Relay Module        IN

3.3V         All Sensors         VCC
5V           Relay               VCC
GND          All Components      GND
```

---

## 🧪 Testing & Troubleshooting

### Test 1: Device Registration

**Via Postman/Browser Console**:
```bash
POST http://localhost/automatic-watering-system/api/device_register.php?action=register

Body:
{
  "device_name": "Test ESP32",
  "zone_id": 1
}

Expected: API key returned
```

### Test 2: Sensor Data Submission

**Using curl (replace API_KEY)**:
```bash
curl -X POST \
  http://localhost/automatic-watering-system/api/hardware.php?action=submit \
  -H "X-API-Key: your_api_key_here" \
  -H "Content-Type: application/json" \
  -d '{"moisture":50,"temperature":25,"humidity":60,"rainfall":0,"tank_level":90}'
```

**Expected Response**:
```json
{"status":"success","message":"Sensor data recorded"}
```

### Test 3: Command Polling

```bash
curl -X GET \
  http://localhost/automatic-watering-system/api/device_control.php?action=poll \
  -H "X-API-Key: your_api_key_here"
```

### Test 4: Send Command from Web

**In browser console (logged in)**:
```javascript
// Manually insert a test command
fetch('/automatic-watering-system/api/device_control.php?action=send', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-API-Key': 'your_api_key_here'
  },
  body: JSON.stringify({
    command: 'turn_on',
    params: {duration: 60}
  })
}).then(r => r.json()).then(console.log);
```

---

## 🐛 Common Issues

### Issue: "API key required" error
**Solution**: Make sure you're sending the `X-API-Key` header with every request.

### Issue: "Device not assigned to a zone"
**Solution**: Update device with a zone_id via web interface or API.

### Issue: ESP32 can't connect to WiFi
**Solution**: 
- Check SSID and password
- Ensure 2.4GHz WiFi (ESP32 doesn't support 5GHz)
- Check signal strength

### Issue: "Invalid or inactive API key"
**Solution**: 
- Verify API key is correct (no extra spaces)
- Check device status is 'active' in database

### Issue: Commands not executing
**Solution**:
- Check ESP32 is polling (every 10 seconds)
- Verify pump relay wiring
- Check command status in database

---

## 📊 Database Queries for Debugging

### View all devices:
```sql
SELECT * FROM devices;
```

### View pending commands:
```sql
SELECT * FROM commands WHERE status = 'pending';
```

### Check latest sensor data:
```sql
SELECT * FROM sensor_data ORDER BY recorded_at DESC LIMIT 10;
```

### View device activity:
```sql
SELECT device_name, status, last_seen 
FROM devices 
ORDER BY last_seen DESC;
```

---

## 🚀 Next Steps

1. **Test with simulator first** (use Serial Monitor output)
2. **Connect real sensors** one at a time
3. **Calibrate moisture sensor** (dry vs wet readings)
4. **Test pump control** manually before automation
5. **Enable auto-watering** in web dashboard
6. **Monitor for 24 hours** before deploying permanently

---

## 📞 Support

- Check [TESTING.md](TESTING.md) for web interface tests
- Review [README.md](README.md) for project overview
- Monitor ESP32 Serial output for debugging
- Check browser console for API errors

---

**Created**: February 2026  
**Version**: 1.0  
**Compatible with**: ESP32, ESP8266 (with code modifications)
