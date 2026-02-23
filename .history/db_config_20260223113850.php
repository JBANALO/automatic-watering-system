<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'irrigation_system');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

// Create database if it doesn't exist
$sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if (!$conn->query($sql)) {
    die(json_encode(['status' => 'error', 'message' => 'Error creating database: ' . $conn->error]));
}

// Select the database
$conn->select_db(DB_NAME);

// Create tables
$tables = "
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    email_verified BOOLEAN DEFAULT 0,
    verification_code VARCHAR(255),
    verification_code_expires DATETIME,
    password_reset_code VARCHAR(255),
    password_reset_expires DATETIME,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS zones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    zone_name VARCHAR(100) NOT NULL,
    enabled BOOLEAN DEFAULT 0,
    moisture_level INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS schedules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    zone_id INT NOT NULL,
    schedule_type ENUM('morning', 'evening', 'custom') DEFAULT 'custom',
    start_time TIME NOT NULL,
    duration INT NOT NULL,
    enabled BOOLEAN DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS sensor_data (
    id INT PRIMARY KEY AUTO_INCREMENT,
    zone_id INT NOT NULL,
    moisture_level INT NOT NULL,
    temperature FLOAT,
    humidity INT,
    rainfall INT DEFAULT 0,
    tank_level INT DEFAULT 100,
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    auto_mode BOOLEAN DEFAULT 1,
    moisture_threshold INT DEFAULT 50,
    skip_rain BOOLEAN DEFAULT 1,
    auto_adjust BOOLEAN DEFAULT 1,
    daily_usage INT DEFAULT 0,
    runtime INT DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
";

if (!$conn->multi_query($tables)) {
    error_log('Error creating tables: ' . $conn->error);
}

// Consume all results
while ($conn->next_result()) {
    if ($conn->more_results()) {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    }
}

// Add missing columns to users table if they don't exist
// Check which columns exist first
$result = $conn->query("DESCRIBE users");
$existingColumns = [];
while ($row = $result->fetch_assoc()) {
    $existingColumns[] = $row['Field'];
}

$columnsToAdd = [
    'first_name' => "ALTER TABLE users ADD COLUMN first_name VARCHAR(100)",
    'last_name' => "ALTER TABLE users ADD COLUMN last_name VARCHAR(100)",
    'email_verified' => "ALTER TABLE users ADD COLUMN email_verified BOOLEAN DEFAULT 0",
    'verification_code' => "ALTER TABLE users ADD COLUMN verification_code VARCHAR(255)",
    'verification_code_expires' => "ALTER TABLE users ADD COLUMN verification_code_expires DATETIME",
    'password_reset_code' => "ALTER TABLE users ADD COLUMN password_reset_code VARCHAR(255)",
    'password_reset_expires' => "ALTER TABLE users ADD COLUMN password_reset_expires DATETIME"
];

foreach ($columnsToAdd as $columnName => $alterSQL) {
    if (!in_array($columnName, $existingColumns)) {
        $conn->query($alterSQL);
    }
}

// Set header for JSON responses
header('Content-Type: application/json');
?>
