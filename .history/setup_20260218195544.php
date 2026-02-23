<?php
/**
 * Smart Irrigation System - Database Setup
 * Run this file once to initialize the database and tables
 * Access via: http://localhost/automatic-watering-system/setup.php
 */

require_once 'db_config.php';

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Database Setup - Smart Irrigation System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body { 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
            padding: 20px; 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container { 
            max-width: 600px; 
            width: 100%;
            background: white; 
            padding: 30px; 
            border-radius: 8px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
        }
        
        h1 { 
            color: #667eea; 
            margin-bottom: 20px; 
            font-size: 1.8em;
        }
        
        h3 {
            color: #333;
            margin-top: 20px;
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        
        p {
            color: #666;
            line-height: 1.6;
            margin-bottom: 10px;
        }
        
        .success { 
            background: #d4edda; 
            border: 1px solid #c3e6cb; 
            color: #155724; 
            padding: 15px; 
            border-radius: 4px; 
            margin: 10px 0; 
        }
        
        .error { 
            background: #f8d7da; 
            border: 1px solid #f5c6cb; 
            color: #721c24; 
            padding: 15px; 
            border-radius: 4px; 
            margin: 10px 0; 
        }
        
        .info { 
            background: #d1ecf1; 
            border: 1px solid #bee5eb; 
            color: #0c5460; 
            padding: 15px; 
            border-radius: 4px; 
            margin: 10px 0; 
        }
        
        code { 
            background: #f4f4f4; 
            padding: 2px 6px; 
            border-radius: 3px; 
            font-family: 'Courier New', monospace;
        }
        
        .step { 
            margin: 20px 0; 
            padding: 15px; 
            background: #f9f9f9; 
            border-left: 4px solid #667eea; 
            border-radius: 4px;
        }
        
        ul, ol {
            margin-left: 20px;
            margin-bottom: 10px;
        }
        
        li {
            margin-bottom: 8px;
        }
        
        a { 
            color: #667eea; 
            text-decoration: none; 
        }
        
        a:hover { 
            text-decoration: underline; 
        }

        @media (max-width: 600px) {
            body {
                padding: 10px;
            }

            .container {
                padding: 20px;
            }

            h1 {
                font-size: 1.4em;
                margin-bottom: 15px;
            }

            h3 {
                font-size: 1em;
                margin-top: 15px;
            }

            .success,
            .error,
            .info {
                padding: 12px;
                font-size: 0.95em;
            }

            .step {
                padding: 12px;
                margin: 15px 0;
            }

            p {
                font-size: 0.95em;
            }

            code {
                font-size: 0.9em;
            }

            ul, ol {
                margin-left: 15px;
            }

            li {
                font-size: 0.95em;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 8px;
            }

            .container {
                padding: 15px;
            }

            h1 {
                font-size: 1.2em;
            }

            h3 {
                font-size: 0.95em;
            }

            .success,
            .error,
            .info {
                padding: 10px;
                font-size: 0.9em;
            }

            .step {
                padding: 10px;
                margin: 12px 0;
            }

            p {
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>💧 Smart Irrigation System - Setup</h1>";

// Check if database was created successfully
$test_query = $conn->query("SHOW TABLES");
if ($test_query && $test_query->num_rows > 0) {
    echo "<div class='success'><strong>✅ Database Setup Successful!</strong></div>";
    echo "<div class='info'>
        <h3>Next Steps:</h3>
        <ol>
            <li><strong>Access the Application:</strong> <a href='indwx.html'>Click here to open the dashboard</a></li>
            <li><strong>Create an Account:</strong> Click 'Sign Up' and register a new user</li>
            <li><strong>Login:</strong> Use your credentials to login</li>
            <li><strong>Configure Your System:</strong> Set up zones, schedules, and settings</li>
        </ol>
    </div>";
    
    echo "<div class='info'>
        <h3>Default Database Credentials:</h3>
        <code>Host: localhost</code><br>
        <code>Database: irrigation_system</code><br>
        <code>User: root</code><br>
        <code>Password: (empty)</code>
    </div>";
    
    echo "<div class='info'>
        <h3>Database Tables Created:</h3>
        <ul>";
    
    while ($row = $test_query->fetch_array()) {
        echo "<li><code>" . $row[0] . "</code></li>";
    }
    
    echo "</ul></div>";
    
} else {
    echo "<div class='error'><strong>⚠️ Database Setup Incomplete</strong></div>";
    echo "<div class='error'>Some tables may not have been created. Please check your MySQL server is running and try again.</div>";
}

// Database connection test
if ($conn->ping()) {
    echo "<div class='success'><strong>✅ MySQL Connection: OK</strong></div>";
} else {
    echo "<div class='error'><strong>❌ MySQL Connection Failed</strong></div>";
}

echo "
        <div class='step'>
            <h3>📋 Troubleshooting</h3>
            <p><strong>Issue: 'Access Denied' error</strong></p>
            <p>Edit <code>db_config.php</code> and update your MySQL credentials (DB_USER, DB_PASS)</p>
            
            <p><strong>Issue: Can't create database</strong></p>
            <p>Ensure your MySQL user has permissions to create databases. Use the root user if necessary.</p>
            
            <p><strong>Issue: Tables not created</strong></p>
            <p>Check that MySQL is running and reload this page.</p>
        </div>

        <div class='step'>
            <h3>🔐 Security Note</h3>
            <p>This setup file should be deleted or secured after initial setup. Consider:</p>
            <ul>
                <li>Renaming or removing <code>setup.php</code> after completion</li>
                <li>Changing the default root MySQL password</li>
                <li>Setting up proper user accounts with limited privileges</li>
            </ul>
        </div>

        <div class='step'>
            <h3>📚 API Endpoints</h3>
            <p>Your system includes the following API endpoints:</p>
            <ul>
                <li><code>api/auth.php</code> - User authentication (login/register)</li>
                <li><code>api/zones.php</code> - Zone management</li>
                <li><code>api/sensors.php</code> - Sensor data collection</li>
                <li><code>api/system.php</code> - System settings</li>
                <li><code>api/schedule.php</code> - Schedule management</li>
            </ul>
        </div>
    </div>
</body>
</html>";

$conn->close();
?>
