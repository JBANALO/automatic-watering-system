# Google OAuth Setup Guide

This document explains how to set up Google OAuth authentication for the Automatic Watering System.

## Prerequisites

- Google Cloud Console project
- Administrative access to your project

## Step-by-Step Setup

### 1. Create a Google Cloud Project

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Click on the project dropdown at the top
3. Click "NEW PROJECT"
4. Enter project name: "Automatic Watering System"
5. Click "CREATE"

### 2. Enable Google+ API

1. In the search bar, search for "Google+ API"
2. Click on "Google+ API"
3. Click "ENABLE"

### 3. Create OAuth 2.0 Credentials

1. Go to "Credentials" in the left sidebar
2. Click "CREATE CREDENTIALS"
3. Select "OAuth 2.0 Client IDs"
4. If prompted, configure OAuth consent screen first:
   - Select "External"
   - Fill in the required information
   - Add test users (your email)
   - Save and continue

5. For Application type, select "Web application"
6. Name it "Automatic Watering System Web"
7. Under "Authorized JavaScript origins", add:
   ```
   http://localhost
   http://localhost:80
   http://localhost:8080
   http://localhost:3000
   YOUR_PRODUCTION_DOMAIN
   ```

8. Under "Authorized redirect URIs", add:
   ```
   http://localhost/automatic-watering-system/indwx.html
   YOUR_PRODUCTION_URL/indwx.html
   ```

9. Click "CREATE"
10. Copy your Client ID

### 4. Configure Your Application

1. Open `indwx.html` in your text editor
2. Find the line with `YOUR_GOOGLE_CLIENT_ID` (around line 2192)
3. Replace `YOUR_GOOGLE_CLIENT_ID.apps.googleusercontent.com` with your actual Client ID
4. Save the file

## Example Configuration

Your Google Client ID should look like:
```
123456789-abcdefghijklmnopqrstuvwxyz.apps.googleusercontent.com
```

In the HTML file, update this line:
```javascript
client_id: '123456789-abcdefghijklmnopqrstuvwxyz.apps.googleusercontent.com',
```

## Testing

1. Open your application in a browser
2. Click the "Continue with Google" button
3. You should see the Google sign-in pop-up
4. Sign in with a test user
5. You should be logged in to the application

## Production Deployment

Before deploying to production:

1. Update the authorized origins and redirect URIs with your production domain
2. Set the application status to "In production" in Google Cloud Console
3. Change the OAuth consent screen from "External" to "Public" (if desired)
4. Update the Client ID in `indwx.html` (or move it to environment variables)

## Troubleshooting

### "Invalid Client ID" Error
- Check that the Client ID is correct and matches the one in your code
- Ensure your domain is in the authorized JavaScript origins

### Pop-up Not Appearing
- Check browser console for errors
- Ensure Google Sign-In script is loaded from CDN
- Verify JavaScript is enabled

### CORS Issues
- Your domain must be in the authorized origins list
- Localhost requires proper configuration

## Security Notes

- Never commit actual Client IDs to version control
- Use environment variables for production
- Enable HTTPS for production
- Rotate credentials periodically

## Additional Resources

- [Google Identity Documentation](https://developers.google.com/identity)
- [OAuth 2.0 Configuration](https://developers.google.com/identity/protocols/oauth2)
