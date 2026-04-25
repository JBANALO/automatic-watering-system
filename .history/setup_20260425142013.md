# Smart Irrigation Wi-Fi Setup (Home/School)

This file is your quick runbook when changing Wi-Fi locations.
Use this with Copilot Chat so setup is repeatable.

## What This Covers
- Switch ESP32 Wi-Fi between home and school
- Keep local API target correct
- Compile and upload firmware
- Verify connection quickly

## Main Firmware File
- `esp32_client_runtime/esp32_client_runtime.ino`

## Before You Start
1. Connect ESP32 to laptop via USB.
2. Make sure XAMPP Apache is running if using local API.
3. Confirm current laptop local IP if needed (example: `192.168.1.204`).

---

## HOME Wi-Fi Mode
Use this when you are at home and ESP32 should connect to home router.

### Copilot Chat Prompt (copy/paste)
```
Open esp32_client_runtime/esp32_client_runtime.ino and set HOME Wi-Fi.
Use these values:
- WIFI_SSID = <HOME_SSID>
- WIFI_PASSWORD = <HOME_PASSWORD>
- SERVER_URL = http://<HOME_LAPTOP_IP>/automatic-watering-system/api
Then compile and upload to ESP32 on detected COM port.
After upload, monitor serial and verify Wi-Fi connected + payload submit success.
```

---

## SCHOOL Wi-Fi Mode
Use this when you are at school and ESP32 should connect to school Wi-Fi.

### Copilot Chat Prompt (copy/paste)
```
Open esp32_client_runtime/esp32_client_runtime.ino and set SCHOOL Wi-Fi.
Use these values:
- WIFI_SSID = <SCHOOL_SSID>
- WIFI_PASSWORD = <SCHOOL_PASSWORD>
- SERVER_URL = http://<SCHOOL_LAPTOP_IP>/automatic-watering-system/api
Then compile and upload to ESP32 on detected COM port.
After upload, monitor serial and verify Wi-Fi connected + payload submit success.
```

---

## Quick Commands (Manual Option)
Run these in terminal from project root:

```powershell
arduino-cli board list
arduino-cli compile --fqbn esp32:esp32:esp32 esp32_client_runtime
arduino-cli upload -p COM3 --fqbn esp32:esp32:esp32 esp32_client_runtime
arduino-cli monitor -p COM3 -c baudrate=115200
```

Note: replace `COM3` with detected port.

---

## Success Checklist
- Serial shows `WiFi Connected!`
- Serial shows `Submitting sensor data to server...`
- Response code is `200`
- Dashboard updates moisture/temperature/humidity

---

## Common Issues
1. `Could not open COM port`
- Replug USB
- Close other serial monitor windows
- Run `arduino-cli board list` again

2. `WiFi Connection Failed`
- Check SSID/password spelling
- Confirm 2.4GHz network (ESP32 usually needs 2.4GHz)

3. `HTTP POST failed`
- Check `SERVER_URL`
- Check laptop IP changed after network switch
- Ensure Apache is running

---

## Fast Reminder
When changing location, usually only these lines need updates:
- `WIFI_SSID`
- `WIFI_PASSWORD`
- `SERVER_URL`
