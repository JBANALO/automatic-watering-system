# Presentation Guide — Smart Irrigation System

## If Using School WiFi (Different Network)

### Option A: Mobile Hotspot (Recommended)
Set your phone's hotspot to match home WiFi credentials:
- **SSID:** `PLDTHOMEFIBRpjcdV`
- **Password:** `PLDTWIFIKPDMP`

ESP32 will auto-connect — no firmware changes needed.

---

### Option B: Update WiFi Credentials in Firmware
If Option A is not possible, update the ESP32 firmware before leaving home:

1. Open `esp32/esp32_watering.ino`
2. Change lines 25–26:
   ```cpp
   #define WIFI_SSID   "YOUR_SCHOOL_OR_HOTSPOT_SSID"
   #define WIFI_PASS   "YOUR_SCHOOL_OR_HOTSPOT_PASSWORD"
   ```
3. Run in terminal (make sure USB cord is plugged in):
   ```
   Copy-Item "esp32\esp32_watering.ino" "esp32_watering\esp32_watering.ino" -Force
   arduino-cli compile --fqbn esp32:esp32:esp32 esp32_watering
   arduino-cli upload --fqbn esp32:esp32:esp32 --port COM3 esp32_watering
   ```
4. After upload, unplug USB — ESP32 is now standalone.

---

## Day-of Presentation Checklist

- [ ] PSU (12V) plugged in and ON
- [ ] ESP32 USB powered (laptop or USB adapter)
- [ ] WiFi connected (check serial monitor or dashboard shows sensor data)
- [ ] Dashboard accessible at: `https://web-production-b741c.up.railway.app`
- [ ] Soil sensor reads correctly (0% dry, 100% wet)
- [ ] Tank level reads correctly
- [ ] Auto-watering works: dry soil → pump ON, wet soil → pump OFF

## Demo Flow

1. Show dashboard (Zone Management page)
2. Leave soil sensor dry → pump auto-turns ON (green LED on relay)
3. Dip soil sensor in water → pump auto-turns OFF after a few seconds
4. Point out real-time updates (3-second interval)

---

## Important Notes
- **Do not switch relay on/off repeatedly** — avoid unnecessary wear
- Dashboard URL: `https://web-production-b741c.up.railway.app`
- API Key: `9252eaf9cb94b83ee73b5e33ece3fc7db61e7d33985a0fb144732670acb1f322`
- Railway is always online — no need to run local server
