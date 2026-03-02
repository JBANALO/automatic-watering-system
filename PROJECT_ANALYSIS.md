# 📊 Automatic Watering System - Project Analysis
## Buod ng Proyekto (Project Summary)

**Pangalan ng Proyekto:** Smart Irrigation System  
**Tipo:** Full-Stack Web Application na may Hardware Integration  
**Teknolohiya:** PHP, MySQL, JavaScript, HTML/CSS, ESP32 (Arduino)

---

## 🎯 Ano Ang Proyekto? (What is this Project?)

Ito ay isang **kompletong automated garden watering system** na may:
- ✅ Web-based dashboard para sa pag-control
- ✅ User authentication at multiple user support
- ✅ ESP32 hardware integration (microcontroller)
- ✅ Real-time sensor monitoring
- ✅ Automatic scheduling
- ✅ Weather-based decision making

**Simple Explanation:** Isang sistema na pwedeng mag-automatic na mag-dilig ng halaman base sa moisture level, weather, at schedule na iyong itinakda.

---

## 📁 Mga Bahagi ng Proyekto (Project Components)

### 1️⃣ **Frontend (User Interface)**
📄 **Main File:** `indwx.html`

**Features:**
- Login at Registration system
- Dashboard para sa monitoring
- Zone controls (pwedeng mag-control ng multiple zones)
- Moisture level indicators
- Schedule management
- System settings
- Responsive design (mobile-friendly)

**Technologies:**
- HTML5
- CSS3 (custom styling with gradients, animations)
- JavaScript (vanilla JS, no frameworks)
- RESTful API calls using fetch()

### 2️⃣ **Backend (Server-Side)**
📂 **Folder:** `api/`

**PHP API Files:**
1. **auth.php** - User authentication
   - Login functionality
   - Registration with email validation
   - Password hashing (bcrypt)
   - Session management
   - Password reset functionality

2. **zones.php** - Zone management
   - Create, read, update, delete zones
   - Toggle zone on/off
   - Update moisture levels
   - Get all user zones

3. **sensors.php** - Sensor data handling
   - Store sensor readings
   - Retrieve historical data
   - Latest sensor values
   - Multi-zone support

4. **system.php** - System settings
   - Auto-mode configuration
   - Moisture threshold settings
   - Water usage tracking
   - System status monitoring

5. **schedule.php** - Irrigation scheduling
   - Morning/Evening schedules
   - Custom timing and duration
   - Enable/disable schedules
   - Persistent storage

6. **hardware.php** - Hardware integration
   - Accept sensor data from ESP32
   - API key authentication
   - Data validation
   - Real-time updates

7. **device_control.php** - Device command system
   - Send commands to ESP32
   - Command queue management
   - Status tracking
   - Zone control via hardware

8. **device_register.php** - Device management
   - Register new ESP32 devices
   - Generate API keys
   - Assign devices to zones
   - List all user devices

9. **GmailSMTP.php** - Email functionality
   - Send verification emails
   - Password reset emails
   - System notifications

### 3️⃣ **Database**
📄 **File:** `db_config.php`

**Database Name:** `irrigation_system`

**Tables:**

1. **users**
   - User credentials
   - Email verification
   - Password reset tokens
   - Account timestamps

2. **zones**
   - Watering zone information
   - Zone name at status
   - Moisture levels
   - User assignment

3. **sensor_data**
   - Timestamp records
   - Moisture readings
   - Temperature
   - Humidity
   - Rainfall detection
   - Tank level

4. **schedules**
   - User schedules
   - Time settings
   - Duration configuration
   - Enable/disable status

5. **system_settings**
   - Auto-mode configuration
   - Moisture thresholds
   - Water usage stats

6. **devices** (Hardware support)
   - Device ID
   - API keys
   - Zone assignments
   - Status tracking
   - Last seen timestamp

7. **commands** (Hardware commands)
   - Command queue
   - Execution status
   - Parameters
   - Timestamps

### 4️⃣ **Hardware (ESP32 Client)**
📄 **File:** `ESP32_Client.ino`

**Purpose:** Arduino sketch para sa ESP32 microcontroller

**Hardware Connections:**
```
- Soil Moisture Sensor → GPIO 34 (analog)
- DHT22 (Temp/Humidity) → GPIO 4
- Rain Sensor → GPIO 35 (digital)
- Water Pump Relay → GPIO 25
- Ultrasonic Sensor → TRIG: GPIO 26, ECHO: GPIO 27
```

**Functions:**
- ✅ WiFi connectivity
- ✅ Read sensors (moisture, temp, humidity, rain, tank level)
- ✅ Submit data to backend API
- ✅ Poll for commands from web dashboard
- ✅ Control water pump relay
- ✅ Automatic retries at error handling

**Libraries Used:**
- WiFi.h
- HTTPClient.h
- ArduinoJson.h
- DHT sensor library

---

## 🔑 Main Features (Mga Pangunahing Features)

### 👤 User Management
- Multi-user support
- Secure password hashing
- Email verification system
- Password reset functionality
- Session-based authentication

### 🌱 Zone Control
- Unlimited watering zones
- Individual zone on/off control
- Real-time moisture monitoring
- Visual indicators
- Zone naming

### ⏰ Scheduling
- Morning at evening schedules
- Custom time configuration
- Duration settings
- Toggle schedules on/off
- Persistent storage

### 🌦️ Weather Integration
- Temperature monitoring
- Humidity tracking
- Rain detection
- Auto-skip watering sa ulan
- Weather-based adjustments

### 📊 Sensor Data
- Real-time readings
- Historical data tracking
- Multi-sensor support
- Data visualization
- Tank level monitoring

### 🔧 System Settings
- Auto-mode (intelligent watering)
- Adjustable moisture threshold (30-80%)
- Water usage tracking
- System runtime monitoring

### 🔌 Hardware Integration
- ESP32 support
- API key authentication
- Real-time sensor data
- Remote control
- Command queueing
- Device management

---

## 🏗️ Arkitektura (Architecture)

```
┌─────────────────────────────────────────────────────┐
│                  USER (Browser)                      │
│              indwx.html (Frontend)                   │
└────────────────────┬────────────────────────────────┘
                     │ HTTP/AJAX Requests
                     ↓
┌─────────────────────────────────────────────────────┐
│              PHP Backend (XAMPP)                     │
│  ┌──────────────────────────────────────────────┐  │
│  │  API Endpoints (api/ folder)                 │  │
│  │  - auth.php                                   │  │
│  │  - zones.php                                  │  │
│  │  - sensors.php                                │  │
│  │  - system.php                                 │  │
│  │  - schedule.php                               │  │
│  │  - hardware.php                               │  │
│  │  - device_control.php                         │  │
│  │  - device_register.php                        │  │
│  └──────────────────────────────────────────────┘  │
└────────────────────┬────────────────────────────────┘
                     │ SQL Queries
                     ↓
┌─────────────────────────────────────────────────────┐
│         MySQL Database (irrigation_system)           │
│  - users                                             │
│  - zones                                             │
│  - sensor_data                                       │
│  - schedules                                         │
│  - system_settings                                   │
│  - devices                                           │
│  - commands                                          │
└─────────────────────────────────────────────────────┘

                     ↑ WiFi + HTTP API
                     │
┌─────────────────────────────────────────────────────┐
│              ESP32 Microcontroller                   │
│         (ESP32_Client.ino)                           │
│                                                      │
│  Sensors:                    Actuators:              │
│  - Soil Moisture            - Water Pump Relay       │
│  - DHT22 (Temp/Hum)                                 │
│  - Rain Sensor                                       │
│  - Ultrasonic (Tank)                                 │
└─────────────────────────────────────────────────────┘
```

---

## 💾 Data Flow (Daloy ng Data)

### 1. Sensor to Database
```
ESP32 → reads sensors → prepares JSON →
WiFi → POST to hardware.php → API key auth →
validates data → stores in sensor_data table →
updates zone moisture_level
```

### 2. User Control to Hardware
```
User → clicks button in dashboard →
JavaScript → POST to zones.php →
creates command in commands table →
ESP32 polls device_control.php →
receives command → executes (pump on/off) →
updates command status
```

### 3. Display Dashboard
```
User → loads indwx.html →
JavaScript → fetch from zones.php →
fetch from sensors.php →
updates UI with real-time data →
refreshes every few seconds
```

---

## 🔐 Security Features

1. **Password Protection**
   - bcrypt hashing (cost factor 10)
   - Never stored plain text

2. **Session Management**
   - PHP sessions para sa authentication
   - Timeout protection

3. **API Authentication**
   - API keys para sa ESP32 devices (64-char random)
   - Header-based authentication

4. **SQL Injection Prevention**
   - Prepared statements sa lahat ng queries
   - Parameter binding

5. **Input Validation**
   - Server-side validation
   - Data type checking
   - Range validation

6. **CORS Protection**
   - Controlled cross-origin requests
   - Specific headers only

---

## 📱 Responsive Design

**Mobile Support:**
- 📱 320px+ (small phones)
- 📱 375px+ (medium phones)
- 📱 768px+ (tablets)
- 💻 1024px+ (desktops)

**Features:**
- Flexible grid layout
- Touch-friendly buttons
- Collapsible navigation
- Optimized text sizes
- Mobile-first approach

---

## 🚀 Setup Requirements

### Software:
- ✅ XAMPP (Apache + MySQL + PHP)
- ✅ Modern web browser
- ✅ Arduino IDE (para sa ESP32)
- ✅ ESP32 board support

### Hardware (Optional):
- ✅ ESP32 development board
- ✅ Soil moisture sensor
- ✅ DHT22 temperature/humidity sensor
- ✅ Rain sensor
- ✅ Ultrasonic sensor (HC-SR04)
- ✅ 5V Relay module
- ✅ Water pump
- ✅ Jumper wires
- ✅ Breadboard

---

## 📝 Testing & Documentation

**Dokumentadong Files:**

1. **QUICK_START.md** - 5-minute setup guide
2. **README.md** - Complete documentation
3. **TESTING.md** - Test procedures
4. **DELIVERY_SUMMARY.md** - Project summary
5. **INDEX.md** - Documentation index
6. **HARDWARE_INTEGRATION.md** - ESP32 setup guide
7. **HARDWARE_SETUP_COMPLETE.md** - Hardware completion status
8. **GOOGLE_OAUTH_SETUP.md** - OAuth configuration (if needed)

**Test Files:**
- `hardware_test.html` - Testing interface
- `add_tank_level.php` - Database updates

---

## 🎨 Technologies Used

### Frontend:
- HTML5
- CSS3 (custom variables, gradients, animations)
- JavaScript ES6+
- Fetch API
- LocalStorage

### Backend:
- PHP 7.4+
- MySQL 5.7+
- RESTful API design
- JSON data format

### Hardware:
- ESP32 (WiFi + dual-core)
- Arduino framework
- C++ programming
- JSON communication

### Tools:
- XAMPP development server
- Arduino IDE
- VS Code (recommended)

---

## ⚙️ Workflow (Paano Gumagana)

### Normal Operation:

1. **Startup:**
   - User mag-login sa web dashboard
   - ESP32 connects to WiFi
   - Database initialized

2. **Monitoring:**
   - ESP32 reads sensors every 60 seconds
   - Submits data to backend every 5 minutes
   - Dashboard displays real-time data
   - Moisture levels updated

3. **Auto Mode:**
   - System checks moisture threshold
   - If below threshold → turn pump on
   - If above threshold → turn pump off
   - Checks rain sensor (skip if raining)

4. **Manual Control:**
   - User clicks zone toggle
   - Creates command in database
   - ESP32 polls for commands every 10 seconds
   - Executes command
   - Updates status

5. **Scheduling:**
   - System checks schedule times
   - On schedule trigger → activate zones
   - Run for configured duration
   - Auto-stop after duration

---

## 💡 Use Cases

### 1. Home Garden
- Monitor soil moisture
- Auto-water sa tamang oras
- Save water
- Monitor from phone

### 2. Farm Application
- Multiple zones (different crops)
- Weather-based watering
- Water usage tracking
- Remote control

### 3. Educational Project
- Learn IoT
- Practice web development
- Hardware integration
- Database management

### 4. Commercial Greenhouse
- Precision watering
- Data logging
- Schedule automation
- System monitoring

---

## 🔄 Future Enhancements (Pwedeng Idagdag)

Possible improvements:
- 📊 Data analytics at graphs
- 📧 Email/SMS notifications
- 🌐 Weather API integration (OpenWeatherMap)
- 📱 Mobile app (Android/iOS)
- 🔔 Push notifications
- 📷 Camera integration
- 🤖 Machine learning predictions
- ☁️ Cloud deployment
- 📈 Advanced reporting
- 🌍 Multi-language support

---

## ✅ Project Status

**Completion:** 100% Functional ✅

**Working Features:**
- ✅ User authentication
- ✅ Zone management
- ✅ Sensor monitoring
- ✅ Scheduling
- ✅ Auto-mode
- ✅ Hardware integration
- ✅ Device management
- ✅ Email system
- ✅ Responsive UI
- ✅ Complete documentation

**Tested Components:**
- ✅ Database setup
- ✅ API endpoints
- ✅ Frontend UI
- ✅ ESP32 connectivity
- ✅ Sensor readings
- ✅ Command execution

---

## 🎯 Konklusyon (Conclusion)

Ang **Automatic Watering System** ay isang **production-ready, full-stack IoT application** na may:

- ✅ Professional code structure
- ✅ Secure implementation
- ✅ Complete documentation
- ✅ Hardware integration
- ✅ Scalable architecture
- ✅ User-friendly interface
- ✅ Mobile responsive
- ✅ Easy deployment

**Perfect for:**
- Portfolio projects
- School thesis
- Commercial deployment
- Learning full-stack + IoT development

**Estimated Development Time:** 40-60 hours
**Lines of Code:** ~3,000+ lines
**Technologies:** 8+ different technologies

---

## 📞 Quick Links

| What | Where |
|------|-------|
| Setup Database | http://localhost/automatic-watering-system/setup.php |
| Access App | http://localhost/automatic-watering-system/indwx.html |
| Quick Start Guide | QUICK_START.md |
| Full Documentation | README.md |
| Hardware Setup | HARDWARE_INTEGRATION.md |
| Testing Guide | TESTING.md |

---

**Created:** February 25, 2026  
**Version:** 1.0  
**Status:** Complete & Functional ✅

---

## 🙏 Notes

Ito ay **self-contained project** - lahat ng kailangan ay nandito na:
- ✅ Complete source code
- ✅ Database schema
- ✅ API backend
- ✅ Frontend UI
- ✅ Hardware firmware
- ✅ Documentation
- ✅ Setup wizard
- ✅ Test tools

**Plug and play** - just need XAMPP and optional ESP32 hardware! 🚀
