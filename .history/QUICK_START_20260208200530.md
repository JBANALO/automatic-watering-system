# 🚀 QUICK START GUIDE

## ⚡ 5-Minute Setup

### Step 1: Ensure XAMPP is Running
- Open XAMPP Control Panel
- Start **Apache** module
- Start **MySQL** module

### Step 2: Initialize Database
1. Open browser
2. Go to: `http://localhost/automatic-watering-system/setup.php`
3. You should see "✅ Database Setup Successful!"
4. If you see errors, check MySQL is running

### Step 3: Access the Application
1. Go to: `http://localhost/automatic-watering-system/indwx.html`
2. Click **Sign Up**
3. Enter username, email, and password
4. Click **Create Account**
5. Go back and **Sign In**

### Step 4: Test the System
- Enable some zones
- Adjust moisture threshold slider
- Toggle Auto Mode on/off
- View sensor data

## ✅ Checklist

- [ ] XAMPP Apache running
- [ ] XAMPP MySQL running
- [ ] setup.php shows success message
- [ ] Can access indwx.html
- [ ] User registration works
- [ ] Can login successfully
- [ ] Dashboard displays zones
- [ ] Can toggle zones on/off

## 📱 Try on Mobile

The system is fully responsive! Open the app on your phone:
1. Find your computer's IP address (e.g., 192.168.x.x)
2. On mobile browser: `http://192.168.x.x/automatic-watering-system/indwx.html`
3. App should work perfectly on mobile devices

## 🔧 Backend API Structure

All features are backed by PHP APIs:
- **Login/Register** - Secure user authentication
- **Zones** - Manage multiple watering areas
- **Sensors** - Real-time moisture/weather data  
- **Schedules** - Automated watering times
- **System** - Settings and statistics

## 🐛 Troubleshooting

| Problem | Solution |
|---------|----------|
| "Connection failed" | Start MySQL in XAMPP |
| "Access Denied" | Check db_config.php credentials |
| "Not authenticated" | Login first or clear cookies |
| Can't see zones | Refresh page or check browser console |
| API errors | Verify api/ folder exists with all .php files |

## 📚 Learn More

See `README.md` for:
- Full API documentation
- Database schema
- Security features
- Future enhancements

---

**You're all set! Start watering your garden smartly! 🌱💧**
