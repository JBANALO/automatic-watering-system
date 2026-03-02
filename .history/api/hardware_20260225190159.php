<?php
/**
 * Hardware Data Ingestion API
 * For ESP32 devices to submit sensor readings
 * 
 * POST /api/hardware.php?action=submit
 * Headers: X-API-Key: your_device_api_key
 * Body: {
 *   "moisture": 45,
 *   "temperature": 28.5,
 *   "humidity": 65,
 *   "rainfall": 0,
 *   "tank_level": 87
 * }
 */

require_once '../db_config.php';

// Allow cross-origin requests from ESP32
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: X-API-Key, Content-Type');
header('Content-Type: application/json');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Authenticate device via API key
function authenticateDevice($conn) {
    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
    
    if (empty($api_key)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'API key required']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT id, zone_id, user_id, device_name FROM devices WHERE api_key = ? AND status = 'active'");
    $stmt->bind_param("s", $api_key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or inactive API key']);
        exit;
    }
    
    $device = $result->fetch_assoc();
    
    // Update last_seen timestamp
    $update = $conn->prepare("UPDATE devices SET last_seen = NOW() WHERE id = ?");
    $update->bind_param("i", $device['id']);
    $update->execute();
    
    return $device;
}

// Submit sensor data
if ($method === 'POST' && $action === 'submit') {
    $device = authenticateDevice($conn);
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON data']);
        exit;
    }
    
    $zone_id = $device['zone_id'];
    
    if (empty($zone_id)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Device not assigned to a zone']);
        exit;
    }
    
    // Extract sensor values with defaults
    $moisture = isset($input['moisture']) ? intval($input['moisture']) : 0;
    $temperature = isset($input['temperature']) ? floatval($input['temperature']) : 0;
    $humidity = isset($input['humidity']) ? intval($input['humidity']) : 0;
    $rainfall = isset($input['rainfall']) ? intval($input['rainfall']) : 0;
    $tank_level = isset($input['tank_level']) ? intval($input['tank_level']) : 100;
    
    // Validate ranges
    $moisture = min(100, max(0, $moisture));
    $humidity = min(100, max(0, $humidity));
    $tank_level = min(100, max(0, $tank_level));
    $rainfall = min(100, max(0, $rainfall));
    
    // Insert sensor data
    $stmt = $conn->prepare("INSERT INTO sensor_data (zone_id, moisture_level, temperature, humidity, rainfall, tank_level) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iidiii", $zone_id, $moisture, $temperature, $humidity, $rainfall, $tank_level);
    
    if ($stmt->execute()) {
        // Update zone moisture level
        $update = $conn->prepare("UPDATE zones SET moisture_level = ? WHERE id = ?");
        $update->bind_param("ii", $moisture, $zone_id);
        $update->execute();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Sensor data recorded',
            'zone_id' => $zone_id,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to record data']);
    }
}

// Get device info and settings
elseif ($method === 'GET' && $action === 'info') {
    $device = authenticateDevice($conn);
    
    // Get system settings for this user
    $settings_result = $conn->query("SELECT * FROM system_settings WHERE user_id = " . $device['user_id']);
    $settings = $settings_result->fetch_assoc();
    
    // Get zone info
    $zone_result = $conn->query("SELECT * FROM zones WHERE id = " . $device['zone_id']);
    $zone = $zone_result->fetch_assoc();
    
    echo json_encode([
        'status' => 'success',
        'device' => [
            'name' => $device['device_name'],
            'zone_id' => $device['zone_id'],
            'zone_name' => $zone['zone_name'] ?? 'Unassigned'
        ],
        'settings' => [
            'auto_mode' => (bool)($settings['auto_mode'] ?? 1),
            'moisture_threshold' => intval($settings['moisture_threshold'] ?? 50),
            'skip_rain' => (bool)($settings['skip_rain'] ?? 1)
        ]
    ]);
}

// Health check / ping
elseif ($method === 'GET' && $action === 'ping') {
    $device = authenticateDevice($conn);
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Device connected',
        'device_id' => $device['id'],
        'server_time' => date('Y-m-d H:i:s')
    ]);
}

else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
?>
