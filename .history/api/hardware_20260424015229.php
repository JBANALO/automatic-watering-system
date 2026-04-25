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

function getAutoDecision($moisture, $rainfall, $tank_level, $threshold, $skip_rain) {
    $lowerThreshold = min(100, max(0, intval($threshold)));
    $upperThreshold = 70;
    $criticalDryThreshold = max(0, $lowerThreshold - 5);
    if ($lowerThreshold >= $upperThreshold) {
        $upperThreshold = min(100, $lowerThreshold + 5);
    }

    if ($tank_level <= 15) {
        return ['action' => 'turn_off', 'lower' => $lowerThreshold, 'upper' => $upperThreshold, 'reason' => 'low_tank'];
    }

    // If soil is critically dry, prioritize watering even when rain sensor is noisy/stuck high.
    if ($moisture < $criticalDryThreshold) {
        return ['action' => 'turn_on', 'lower' => $lowerThreshold, 'upper' => $upperThreshold, 'reason' => 'soil_critical_dry'];
    }

    if ($skip_rain && $rainfall > 0) {
        return ['action' => 'turn_off', 'lower' => $lowerThreshold, 'upper' => $upperThreshold, 'reason' => 'rain_detected'];
    }

    if ($moisture < $lowerThreshold) {
        return ['action' => 'turn_on', 'lower' => $lowerThreshold, 'upper' => $upperThreshold, 'reason' => 'soil_dry'];
    }

    if ($moisture >= $upperThreshold) {
        return ['action' => 'turn_off', 'lower' => $lowerThreshold, 'upper' => $upperThreshold, 'reason' => 'soil_wet'];
    }

    return ['action' => null, 'lower' => $lowerThreshold, 'upper' => $upperThreshold, 'reason' => 'hysteresis_hold'];
}

function queueAutoCommandIfNeeded($conn, $device, $zone_id, $moisture, $rainfall, $tank_level) {
    $user_id = intval($device['user_id']);
    $minimumOnSeconds = 45;

    // Respect zone enable/disable state.
    $zoneStmt = $conn->prepare("SELECT enabled FROM zones WHERE id = ? LIMIT 1");
    if (!$zoneStmt) {
        return ['queued' => false, 'action' => null, 'reason' => 'zone_query_failed'];
    }
    $zoneStmt->bind_param("i", $zone_id);
    $zoneStmt->execute();
    $zoneResult = $zoneStmt->get_result();
    $zoneRow = $zoneResult ? $zoneResult->fetch_assoc() : null;
    if (!$zoneRow || intval($zoneRow['enabled']) !== 1) {
        return ['queued' => false, 'action' => null, 'reason' => 'zone_disabled'];
    }

    $settingsStmt = $conn->prepare("SELECT auto_mode, moisture_threshold, skip_rain FROM system_settings WHERE user_id = ? LIMIT 1");
    if (!$settingsStmt) {
        return ['queued' => false, 'action' => null, 'reason' => 'settings_query_failed'];
    }
    $settingsStmt->bind_param("i", $user_id);
    $settingsStmt->execute();
    $settingsResult = $settingsStmt->get_result();
    $settings = $settingsResult ? $settingsResult->fetch_assoc() : null;

    if (!$settings) {
        return ['queued' => false, 'action' => null, 'reason' => 'settings_missing'];
    }

    $autoMode = intval($settings['auto_mode'] ?? 1) === 1;
    if (!$autoMode) {
        return ['queued' => false, 'action' => null, 'reason' => 'auto_mode_off'];
    }

    $threshold = intval($settings['moisture_threshold'] ?? 50);
    $skipRain = intval($settings['skip_rain'] ?? 1) === 1;
    $decision = getAutoDecision($moisture, $rainfall, $tank_level, $threshold, $skipRain);
    $desiredAction = $decision['action'];

    if ($desiredAction === null) {
        return [
            'queued' => false,
            'action' => null,
            'reason' => $decision['reason'],
            'lower_threshold' => $decision['lower'],
            'upper_threshold' => $decision['upper']
        ];
    }

    // Dedupe: queue only when state transitions.
    $lastStmt = $conn->prepare("SELECT command_type, created_at FROM commands WHERE zone_id = ? AND command_type IN ('turn_on', 'turn_off') ORDER BY id DESC LIMIT 1");
    if ($lastStmt) {
        $lastStmt->bind_param("i", $zone_id);
        $lastStmt->execute();
        $lastResult = $lastStmt->get_result();
        $lastRow = $lastResult ? $lastResult->fetch_assoc() : null;

        // Anti-chatter guard: keep pump ON for a short window before allowing auto turn_off.
        if ($desiredAction === 'turn_off' && $lastRow && ($lastRow['command_type'] ?? '') === 'turn_on' && !empty($lastRow['created_at'])) {
            $lastTs = strtotime($lastRow['created_at']);
            if ($lastTs !== false) {
                $elapsed = time() - $lastTs;
                if ($elapsed >= 0 && $elapsed < $minimumOnSeconds) {
                    return [
                        'queued' => false,
                        'action' => null,
                        'reason' => 'minimum_on_window',
                        'minimum_on_seconds' => $minimumOnSeconds,
                        'elapsed_seconds' => intval($elapsed),
                        'lower_threshold' => $decision['lower'],
                        'upper_threshold' => $decision['upper']
                    ];
                }
            }
        }

        if ($lastRow && ($lastRow['command_type'] ?? '') === $desiredAction) {
            return [
                'queued' => false,
                'action' => $desiredAction,
                'reason' => 'already_in_state',
                'lower_threshold' => $decision['lower'],
                'upper_threshold' => $decision['upper']
            ];
        }
    }

    $params = json_encode([
        'source' => 'auto_mode_sensor',
        'moisture' => intval($moisture),
        'rainfall' => intval($rainfall),
        'tank_level' => intval($tank_level),
        'moisture_threshold' => $decision['lower'],
        'upper_threshold' => $decision['upper'],
        'reason' => $decision['reason']
    ]);

    $insert = $conn->prepare("INSERT INTO commands (zone_id, device_id, command_type, params) VALUES (?, ?, ?, ?)");
    if (!$insert) {
        return ['queued' => false, 'action' => null, 'reason' => 'insert_prepare_failed'];
    }
    $device_id = intval($device['id']);
    $insert->bind_param("iiss", $zone_id, $device_id, $desiredAction, $params);

    if (!$insert->execute()) {
        return ['queued' => false, 'action' => null, 'reason' => 'insert_failed'];
    }

    return [
        'queued' => true,
        'action' => $desiredAction,
        'reason' => $decision['reason'],
        'command_id' => intval($conn->insert_id),
        'lower_threshold' => $decision['lower'],
        'upper_threshold' => $decision['upper']
    ];
}

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
    
    // Allow testing without zone assignment (use 0 as placeholder)
    if (empty($zone_id) || $zone_id == 0 || is_null($zone_id)) {
        // For testing: use 0 as placeholder zone_id (won't update any real zone)
        $zone_id = 0;
        $is_test_mode = true;
    } else {
        $is_test_mode = false;
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
    
    // Insert sensor data (skip database insert if test mode and zone doesn't exist)
    if ($is_test_mode) {
        // Test mode: just acknowledge without database insert
        echo json_encode([
            'status' => 'success',
            'message' => 'Test data received (not saved - device unassigned)',
            'zone_id' => null,
            'test_mode' => true,
            'temperature' => $temperature,
            'moisture' => $moisture,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        // Normal mode: save to database
        $stmt = $conn->prepare("INSERT INTO sensor_data (zone_id, moisture_level, temperature, humidity, rainfall, tank_level) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iidiii", $zone_id, $moisture, $temperature, $humidity, $rainfall, $tank_level);
        
        if ($stmt->execute()) {
            // Update zone moisture level
            $update = $conn->prepare("UPDATE zones SET moisture_level = ? WHERE id = ?");
            $update->bind_param("ii", $moisture, $zone_id);
            $update->execute();

            // Auto-control: queue relay command based on latest moisture and system settings.
            $autoResult = queueAutoCommandIfNeeded($conn, $device, $zone_id, $moisture, $rainfall, $tank_level);
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Sensor data recorded',
                'zone_id' => $zone_id,
                'timestamp' => date('Y-m-d H:i:s'),
                'auto_command' => $autoResult
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to record data']);
        }
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
