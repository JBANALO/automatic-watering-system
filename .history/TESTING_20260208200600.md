# 🧪 System Testing Guide

Test each feature to ensure everything is working correctly.

## 1. Database Connection Test

**File:** `setup.php`

```
Steps:
1. Visit http://localhost/automatic-watering-system/setup.php
2. Should see "✅ MySQL Connection: OK"
3. Should see "✅ Database Setup Successful!"
4. Should list 6 tables: users, zones, schedules, sensor_data, system_settings, sessions
```

**Expected Result:** ✅ All green checkmarks

---

## 2. Authentication Test

### Register Test
```
1. Open http://localhost/automatic-watering-system/indwx.html
2. Click "Sign Up"
3. Enter:
   - Username: testuser1
   - Email: test@example.com
   - Password: Test1234
4. Click "Create Account"
5. Page should show "Registration successful! Please login."
6. Should automatically redirect to login form
```

**Expected Result:** ✅ User created successfully

### Login Test
```
1. Enter credentials:
   - Username: testuser1
   - Password: Test1234
2. Click "Sign In"
3. Dashboard should load
4. Should see "Welcome, testuser1" in top right
```

**Expected Result:** ✅ Login successful, dashboard visible

---

## 3. Zone Control Test

```
1. On dashboard, go to "Zone Control" card
2. Zones should be listed: Front Garden, Backyard Lawn, Vegetable Garden, Side Pathway
3. Each zone should have:
   - Zone name
   - Moisture bar showing percentage
   - Toggle switch to enable/disable
4. Click toggle on "Front Garden"
   - Should turn green and show "active"
   - Moisture bar should be visible
5. Click again to disable
   - Should turn gray
```

**Expected Result:** ✅ Zones toggle successfully between enabled/disabled

---

## 4. Auto Mode Test

```
1. In "System Status" card, toggle "Auto Mode"
2. When enabled:
   - Toggle should be green
   - Status should show "✅ Auto Mode Active"
   - Background should be light green
3. When disabled:
   - Toggle should be gray
   - Status should show "⏸️ Auto Mode Disabled"
   - Background should be light yellow
4. Check browser console (F12) for any errors
```

**Expected Result:** ✅ Auto mode toggles correctly

---

## 5. Moisture Threshold Test

```
1. In "System Status", find "Moisture Threshold" slider
2. Slide left to 30% - value should update
3. Slide right to 80% - value should update
4. Try setting to 50%
5. Value display should update in real-time
```

**Expected Result:** ✅ Slider works smoothly, values update

---

## 6. System Statistics Test

```
1. Look for "Liters Today" stat in System Status
2. Should show a number (e.g., 245)
3. Look for "Minutes Active" stat
4. Should show a number (e.g., 45)
5. These should persist across page reloads if data is saved
```

**Expected Result:** ✅ Statistics display correctly

---

## 7. Schedule Settings Test

```
1. Go to "Schedule Settings" card
2. Morning Schedule section:
   - Should have time input (default: 06:00)
   - Should have duration input (default: 30 min)
3. Evening Schedule section:
   - Should have time input (default: 18:00)
   - Should have duration input (default: 30 min)
4. Click "Save Schedule"
   - Should show alert: "Schedule saved successfully! ✅"
5. Change values and save again
```

**Expected Result:** ✅ Schedule inputs work and can be saved

---

## 8. Weather & Sensors Test

```
1. Go to "Weather & Sensors" card
2. Should display:
   - Temperature: 28°C
   - Humidity: 65%
   - Rain Today: 0mm
3. Should have checkboxes for:
   - Skip watering if rain detected (checked)
   - Auto-adjust based on soil moisture (checked)
4. Toggle checkboxes on/off
5. Should update visually
```

**Expected Result:** ✅ Weather data displays and controls work

---

## 9. Responsive Design Test

### Desktop (1200px+)
```
1. Open on full-screen browser
2. Cards should be in responsive grid (2-3 columns)
3. All controls should be clearly visible
4. No horizontal scrolling needed
```

### Tablet (768px)
```
1. Resize browser to ~800px width
2. Grid should collapse to single column
3. Font sizes should adjust
4. Layout should remain readable
```

### Mobile (480px)
```
1. Resize browser to ~400px width
2. All elements should stack vertically
3. Toggle switches should be slightly smaller
4. Text should remain readable
5. Buttons should be easy to tap
```

**Expected Result:** ✅ Layout adapts smoothly at all sizes

---

## 10. Browser Console Test

```
1. Press F12 to open Developer Tools
2. Go to Console tab
3. Perform all above tests
4. Console should show NO red error messages
5. Warnings are OK, errors are NOT OK
```

**Expected Result:** ✅ No JavaScript errors

---

## 11. API Endpoint Test (Advanced)

Test using browser console or curl:

### Test Login API
```javascript
// In browser console:
fetch('api/auth.php?action=login', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({username: 'testuser1', password: 'Test1234'})
}).then(r => r.json()).then(data => console.log(data));
```

Expected response:
```json
{"status": "success", "message": "Login successful", "user_id": 1}
```

### Test Get Zones
```javascript
fetch('api/zones.php?action=list')
    .then(r => r.json())
    .then(data => console.log(data));
```

**Expected Result:** ✅ API returns valid JSON responses

---

## 12. Logout Test

```
1. Click "Logout" button in top right
2. Should return to login screen
3. Should clear all session data
4. Try accessing dashboard without logging in
5. Should be redirected to login form
```

**Expected Result:** ✅ Logout works, session cleared

---

## 📋 Test Checklist

- [ ] Database connection working
- [ ] User registration works
- [ ] User login works
- [ ] Zones display correctly
- [ ] Zone toggles work
- [ ] Auto mode toggles
- [ ] Moisture threshold slider works
- [ ] Statistics display
- [ ] Schedules can be saved
- [ ] Weather section displays
- [ ] Desktop layout looks good
- [ ] Tablet layout looks good
- [ ] Mobile layout looks good
- [ ] No JavaScript errors
- [ ] API endpoints respond correctly
- [ ] Logout works properly

---

## 🚀 Performance Issues?

If experiencing slow responses:

1. **Check MySQL Performance**
   - XAMPP Control Logs should show MySQL running
   - Restart MySQL if needed

2. **Check Browser Performance**
   - Press F12 → Performance tab
   - Reload page and check for slow tasks
   - Look for slow API responses

3. **Check Network**
   - Should see API calls in Network tab (F12)
   - Response times should be <500ms typically

4. **Clear Cache**
   - Hard refresh: Ctrl+Shift+R (Windows/Linux) or Cmd+Shift+R (Mac)

---

## 📝 Known Issues & Workarounds

| Issue | Workaround |
|-------|-----------|
| Zones not listed | Refresh page or check browser console |
| "Not authenticated" error | Login again, clear cookies |
| API 405 Method Not Allowed | Ensure endpoint exists in api/ folder |
| CORS errors | Not applicable (local testing) |
| Session timeout | Re-login and increase session timeout |

---

## ✅ Everything Working?

If all tests pass, you're ready for:
- Demonstration to your professor
- Adding real IoT device integration
- Deploying to a web server
- Further customization and enhancements

**Congratulations! Your smart irrigation system is ready! 🎉**

---

**Last Updated:** February 8, 2026  
**Test Date:** [Your Date Here]  
**Tester:** [Your Name Here]  
**Status:** ✅ PASSED / ❌ FAILED
