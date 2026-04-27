<?php
ini_set('display_errors', '0');
error_reporting(0);

// ─── Session cookie params (must run before session_start in any file) ──────
if (session_status() === PHP_SESSION_NONE) {
    // Detect HTTPS (Railway terminates TLS and forwards via X-Forwarded-Proto)
    $isHttps = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') ||
        (($_SERVER['SERVER_PORT'] ?? '') == 443)
    );

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// Database Configuration
// For local: use hardcoded values
// For Railway: parse MYSQL_URL environment variable

// Parse Railway MySQL URL if available
$mysql_url = $_ENV['MYSQL_URL'] ?? getenv('MYSQL_URL') ?? null;

if ($mysql_url) {
    // Parse mysql://user:password@host:port/database
    $parsed = parse_url($mysql_url);
    $host = $parsed['host'] ?? 'localhost';
    $user = $parsed['user'] ?? 'root';
    $pass = $parsed['pass'] ?? '';
    $db = ltrim($parsed['path'] ?? '/irrigation_system', '/');
    $port = $parsed['port'] ?? 3306;
} else {
    // Fallback to individual env vars or defaults for local development
    $host = $_ENV['MYSQLHOST'] ?? getenv('MYSQLHOST') ?? 'localhost';
    $user = $_ENV['MYSQLUSER'] ?? getenv('MYSQLUSER') ?? 'root';
    $pass = $_ENV['MYSQLPASSWORD'] ?? getenv('MYSQLPASSWORD') ?? '';
    $db = $_ENV['MYSQLDATABASE'] ?? getenv('MYSQLDATABASE') ?? 'irrigation_system';
    $port = $_ENV['MYSQLPORT'] ?? getenv('MYSQLPORT') ?? 3306;
}

define('DB_HOST', $host);
define('DB_USER', $user);
define('DB_PASS', $pass);
define('DB_NAME', $db);
define('DB_PORT', $port);

// Create connection with timeouts to prevent hanging requests
$conn = mysqli_init();
if ($conn === false) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Database init failed']));
}

if (defined('MYSQLI_OPT_CONNECT_TIMEOUT')) {
    mysqli_options($conn, MYSQLI_OPT_CONNECT_TIMEOUT, 5);
}
if (defined('MYSQLI_OPT_READ_TIMEOUT')) {
    mysqli_options($conn, MYSQLI_OPT_READ_TIMEOUT, 5);
}
if (defined('MYSQLI_OPT_WRITE_TIMEOUT')) {
    $optWriteTimeout = constant('MYSQLI_OPT_WRITE_TIMEOUT');
    if (is_int($optWriteTimeout)) {
        mysqli_options($conn, $optWriteTimeout, 5);
    }
}

@mysqli_real_connect($conn, DB_HOST, DB_USER, DB_PASS, '', intval(DB_PORT));

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

// Avoid schema setup on production by default (can be re-enabled via DB_SETUP=1)
$dbSetupEnv = $_ENV['DB_SETUP'] ?? getenv('DB_SETUP');
$enableDbSetup = $dbSetupEnv !== null ? $dbSetupEnv === '1' : ($mysql_url === null);

if ($enableDbSetup) {
    // Create database if it doesn't exist
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
    if (!$conn->query($sql)) {
        die(json_encode(['status' => 'error', 'message' => 'Error creating database: ' . $conn->error]));
    }
}

// Select the database
if (!$conn->select_db(DB_NAME)) {
    http_response_code(500);
    die(json_encode(['status' => 'error', 'message' => 'Database not found: ' . DB_NAME]));
}

if ($enableDbSetup) {
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
    last_watered TIMESTAMP NULL,
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

CREATE TABLE IF NOT EXISTS devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    device_id VARCHAR(100) UNIQUE NOT NULL,
    api_key VARCHAR(255) UNIQUE NOT NULL,
    user_id INT NOT NULL,
    zone_id INT,
    device_name VARCHAR(100),
    device_type VARCHAR(50) DEFAULT 'ESP32',
    status ENUM('active', 'inactive', 'error') DEFAULT 'inactive',
    last_seen TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS commands (
    id INT PRIMARY KEY AUTO_INCREMENT,
    zone_id INT NOT NULL,
    device_id INT,
    command_type ENUM('turn_on', 'turn_off', 'auto_mode', 'set_duration') NOT NULL,
    params JSON,
    status ENUM('pending', 'sent', 'executed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    executed_at TIMESTAMP NULL,
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES devices(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    data TEXT,
    expires DATETIME NOT NULL,
    INDEX idx_expires (expires)
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
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $existingColumns[] = $row['Field'];
        }
    }

    $columnsToAdd = [
        'first_name' => "ALTER TABLE users ADD COLUMN first_name VARCHAR(100)",
        'last_name' => "ALTER TABLE users ADD COLUMN last_name VARCHAR(100)",
        'middle_name' => "ALTER TABLE users ADD COLUMN middle_name VARCHAR(100) NULL",
        'birthdate' => "ALTER TABLE users ADD COLUMN birthdate DATE NULL",
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

    // Add missing columns to zones table if they don't exist
    $zoneDescribe = $conn->query("DESCRIBE zones");
    $zoneColumns = [];
    if ($zoneDescribe) {
        while ($row = $zoneDescribe->fetch_assoc()) {
            $zoneColumns[] = $row['Field'];
        }
    }

    if (!in_array('last_watered', $zoneColumns)) {
        $conn->query("ALTER TABLE zones ADD COLUMN last_watered TIMESTAMP NULL");
    }
}

// Database-backed session handler for Railway (prevents session loss on container restart)
class DbSessionHandler implements SessionHandlerInterface {
    private $conn;
    public function __construct($conn) { $this->conn = $conn; }
    public function open($path, $name) { return true; }
    public function close() { return true; }
    public function read($id) {
        $id = $this->conn->real_escape_string($id);
        $result = $this->conn->query("SELECT data FROM sessions WHERE id='$id' AND expires > NOW()");
        if ($result && $result->num_rows > 0) return $result->fetch_assoc()['data'];
        return '';
    }
    public function write($id, $data) {
        $id   = $this->conn->real_escape_string($id);
        $data = $this->conn->real_escape_string($data);
        $this->conn->query("INSERT INTO sessions (id, data, expires) VALUES ('$id', '$data', DATE_ADD(NOW(), INTERVAL 1 DAY)) ON DUPLICATE KEY UPDATE data='$data', expires=DATE_ADD(NOW(), INTERVAL 1 DAY)");
        return true;
    }
    public function destroy($id) {
        $id = $this->conn->real_escape_string($id);
        $this->conn->query("DELETE FROM sessions WHERE id='$id'");
        return true;
    }
    public function gc($maxlifetime) {
        $this->conn->query("DELETE FROM sessions WHERE expires < NOW()");
        return true;
    }
}
// Ensure sessions table exists (needed even when full schema setup is skipped on Railway)
$conn->query("CREATE TABLE IF NOT EXISTS sessions (
    id VARCHAR(128) PRIMARY KEY,
    data TEXT,
    expires DATETIME NOT NULL,
    INDEX idx_expires (expires)
)");

$_dbSessionHandler = new DbSessionHandler($conn);
session_set_save_handler($_dbSessionHandler, true);

register_shutdown_function(function () use ($conn) {
    if ($conn instanceof mysqli) {
        $conn->close();
    }
});

// Set header for JSON responses
header('Content-Type: application/json');
// No closing PHP tag intentionally - prevents trailing newline that breaks session cookies
