# 💧 Smart Irrigation System - Full Stack Application

A complete web-based smart garden watering management system with PHP backend, MySQL database, and responsive frontend.

## 📋 Project Structure

```
automatic-watering-system/
├── indwx.html              # Main frontend (login, dashboard, UI)
├── db_config.php           # Database configuration & initialization
├── setup.php               # Database setup wizard
├── README.md               # This file
└── api/
    ├── auth.php            # Authentication (login/register)
    ├── zones.php           # Zone management
    ├── sensors.php         # Sensor data collection
    ├── system.php          # System settings
    └── schedule.php        # Schedule management
```

## 🚀 Quick Start

### 1. Prerequisites
- XAMPP (Apache + MySQL + PHP) installed and running
- Modern web browser (Chrome, Firefox, Edge, Safari)
- Windows/Mac/Linux

### 2. Installation

1. **Copy Files to Web Root**
   - Copy the entire `automatic-watering-system` folder to `C:\xampp\htdocs\` (Windows)
   - Or `/Applications/XAMPP/xamppfiles/htdocs/` (Mac)

2. **Start XAMPP Services**
   - Start Apache module
   - Start MySQL module

3. **Initialize Database**
   - Open browser: `http://localhost/automatic-watering-system/setup.php`
   - Follow the setup wizard
   - Database will be created automatically

4. **Access Application**
   - Open: `http://localhost/automatic-watering-system/indwx.html`
   - Click "Sign Up" to create an account
   - Login with your credentials

### 3. Configuration

Edit `db_config.php` to change database credentials:

```php
define('DB_HOST', 'localhost');   // MySQL host
define('DB_USER', 'root');         // MySQL user
define('DB_PASS', '');             // MySQL password
define('DB_NAME', 'irrigation_system'); // Database name
```

## 🎯 Features

### User Management
- ✅ User registration with email
- ✅ Secure password hashing (bcrypt)
- ✅ Session-based authentication
- ✅ Login/Logout functionality

### Zone Control
- ✅ Multiple watering zones support (unlimited)
- ✅ Real-time moisture level monitoring
- ✅ Toggle zones on/off
- ✅ Visual moisture indicators

### Scheduling
- ✅ Morning/Evening irrigation schedules
- ✅ Custom timing and duration
- ✅ Persistent schedule storage

### System Management
- ✅ Auto-mode with intelligent watering
- ✅ Adjustable moisture threshold (30-80%)
- ✅ Daily water usage tracking
- ✅ Runtime monitoring

### Weather Integration
- ✅ Temperature monitoring
- ✅ Humidity levels
- ✅ Rainfall detection
- ✅ Auto-skip watering on rain
- ✅ Weather-based adjustments

### Sensor Data
- ✅ Real-time sensor readings
- ✅ Historical data tracking
- ✅ Data persistence in database
- ✅ Multi-zone sensor support

## 📱 Responsive Design

The application is fully responsive and works on:
- 📱 Mobile phones (320px and up)
- 📱 Tablets (768px and up)
- 💻 Desktop devices (1200px and up)

CSS Breakpoints:
- `max-width: 1200px` - Large devices
- `max-width: 768px` - Tablets
- `max-width: 480px` - Mobile phones

## 🔌 API Endpoints

### Authentication (`api/auth.php`)
```
POST   /api/auth.php?action=register  - Register new user
POST   /api/auth.php?action=login     - Login user
GET    /api/auth.php?action=logout    - Logout user
GET    /api/auth.php?action=user      - Get current user info
```

### Zones (`api/zones.php`)
```
GET    /api/zones.php?action=list     - Get all zones
POST   /api/zones.php?action=toggle   - Toggle zone on/off
POST   /api/zones.php?action=update   - Update zone moisture
```

### Sensors (`api/sensors.php`)
```
GET    /api/sensors.php?action=latest   - Get latest sensor data
GET    /api/sensors.php?action=history  - Get sensor history
POST   /api/sensors.php?action=update   - Record sensor data
```

### System (`api/system.php`)
```
GET    /api/system.php?action=get      - Get system settings
POST   /api/system.php?action=update   - Update system settings
```

### Schedules (`api/schedule.php`)
```
GET    /api/schedule.php?action=list     - Get all schedules
POST   /api/schedule.php?action=create   - Create new schedule
POST   /api/schedule.php?action=update   - Update schedule
POST   /api/schedule.php?action=delete   - Delete schedule
```

## 📊 Database Schema

### users
- `id` - User ID (Primary Key)
- `username` - Unique username
- `email` - Unique email address
- `password` - Hashed password (bcrypt)
- `created_at` - Registration timestamp

### zones
- `id` - Zone ID (Primary Key)
- `user_id` - Foreign Key (users)
- `zone_name` - Zone name (e.g., "Front Garden")
- `enabled` - Zone enabled status
- `moisture_level` - Current moisture level (0-100)
- `created_at` - Zone creation timestamp

### system_settings
- `id` - Setting ID (Primary Key)
- `user_id` - Foreign Key (users)
- `auto_mode` - Auto-mode enabled (0/1)
- `moisture_threshold` - Threshold for auto-watering
- `skip_rain` - Skip watering if rain detected
- `auto_adjust` - Auto-adjust based on moisture
- `daily_usage` - Daily water usage (liters)
- `runtime` - Total runtime (minutes)

### sensor_data
- `id` - Record ID (Primary Key)
- `zone_id` - Foreign Key (zones)
- `moisture_level` - Moisture percentage
- `temperature` - Temperature reading
- `humidity` - Humidity percentage
- `rainfall` - Rainfall amount (mm)
- `recorded_at` - Timestamp

### schedules
- `id` - Schedule ID (Primary Key)
- `user_id` - Foreign Key (users)
- `zone_id` - Foreign Key (zones)
- `schedule_type` - 'morning', 'evening', or 'custom'
- `start_time` - Schedule start time
- `duration` - Watering duration (minutes)
- `enabled` - Schedule enabled status
- `created_at` - Schedule creation timestamp

## 🛠️ Technology Stack

**Frontend:**
- HTML5
- CSS3 (with responsive media queries)
- Vanilla JavaScript (ES6+)
- Fetch API for backend communication

**Backend:**
- PHP 7.0+ (with MySQLi)
- Session management
- Password hashing (bcrypt)
- JSON API responses

**Database:**
- MySQL 5.7+
- Foreign key relationships
- Indexed queries for performance

## 🔒 Security Features

- ✅ Password hashing with bcrypt
- ✅ Session-based authentication
- ✅ SQL injection prevention (prepared statements via MySQLi)
- ✅ User data isolation (users can only access their data)
- ✅ HTTP-only session cookies
- ✅ Input validation

## ⚠️ Important Notes

1. **After Setup**, delete or rename `setup.php` for production
2. **Change default MySQL password** after setup
3. **Use HTTPS** in production environment
4. **Backup database** regularly
5. **Test API endpoints** before production deployment

## 🐛 Troubleshooting

### "Access Denied" Error
- Check MySQL is running
- Verify credentials in `db_config.php`
- Check MySQL user permissions

### "Table already exists" Warning
- This is normal on second run - tables are checked before creation

### Login not working
- Clear browser cookies and cache
- Check PHP sessions directory is writable
- Verify database connection

### API endpoints return errors
- Check browser console for error messages
- Verify API files exist in `api/` folder
- Check server error logs

## 📞 Support

For issues or questions:
1. Check the Troubleshooting section
2. Review browser console for JavaScript errors
3. Check server error logs in XAMPP
4. Verify all files are in correct locations

## � Hardware Integration (ESP32)

**NEW!** Full ESP32 hardware support is now integrated into the backend!

### Quick Start with ESP32
1. **Register your device**: Use the web interface or API to get an API key
2. **Upload Arduino sketch**: Flash `ESP32_Client.ino` to your ESP32
3. **Configure WiFi & API key**: Update credentials in the sketch
4. **Connect sensors**: Wire up moisture, temperature, and pump relay
5. **Start monitoring**: Real-time sensor data flows to your dashboard

### Documentation
- 📘 [HARDWARE_INTEGRATION.md](HARDWARE_INTEGRATION.md) - Complete ESP32 setup guide
- 🧪 [hardware_test.html](hardware_test.html) - Browser-based API testing tool
- 💻 [ESP32_Client.ino](ESP32_Client.ino) - Arduino sketch for ESP32

### Supported Features
- ✅ Real-time sensor data submission (moisture, temp, humidity, tank level)
- ✅ Remote pump control via web interface
- ✅ Command queue system (web → ESP32)
- ✅ Device health monitoring (last seen status)
- ✅ Multi-device support (multiple zones)
- ✅ Automatic watering based on sensor readings

### API Endpoints
- `POST /api/hardware.php?action=submit` - Submit sensor data
- `GET /api/device_control.php?action=poll` - Poll for commands
- `POST /api/device_register.php?action=register` - Register new device

## 📝 Future Enhancements

Potential improvements for version 2.0:
- ~~Real IoT device integration~~ ✅ **COMPLETED**
- Mobile app (iOS/Android)
- Weather API integration (OpenWeather)
- Advanced analytics and reporting
- Push notifications (email/SMS)
- MQTT support for real-time updates
- Battery monitoring for solar-powered setups
- Two-factor authentication
- Database backup/restore functionality
- Multi-language support

## 📄 License

This project is provided as-is for educational purposes.

## 👨‍💻 Development Notes

### Adding New Features

1. **Create API endpoint** in `api/` folder
2. **Add database tables** in `db_config.php`
3. **Update frontend** to call new API
4. **Add responsive styling** for mobile

### Testing

Test each API endpoint:
```bash
curl -X POST http://localhost/automatic-watering-system/api/auth.php?action=login \
  -H "Content-Type: application/json" \
  -d '{"username":"user","password":"pass"}'
```

---

**Version:** 2.0 - Hardware Integration Edition  
**Last Updated:** February 25, 2026  
**Status:** Fully Functional with ESP32 Hardware Support ✅
