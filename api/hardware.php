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

function getAutoDecision($moisture, $tank_level, $threshold) {
    $lowerThreshold = min(100, max(0, intval($threshold)));
    $upperThreshold = 70;
    $criticalDryThreshold = max(0, $lowerThreshold - 5);
    if ($lowerThreshold >= $upperThreshold) {
        $upperThreshold = min(100, $lowerThreshold + 5);
    }

    if ($tank_level !== null && $tank_level <= 15) {
        return ['action' => 'turn_off', 'lower' => $lowerThreshold, 'upper' => $upperThreshold, 'reason' => 'low_tank'];
    }

    // Primary behavior: moisture controls pump state.
    if ($moisture < $lowerThreshold) {
        if ($moisture < $criticalDryThreshold) {
            return ['action' => 'turn_on', 'lower' => $lowerThreshold, 'upper' => $upperThreshold, 'reason' => 'soil_critical_dry'];
        }
        return ['action' => 'turn_on', 'lower' => $lowerThreshold, 'upper' => $upperThreshold, 'reason' => 'soil_dry'];
    }

    if ($moisture >= $upperThreshold) {
        return ['action' => 'turn_off', 'lower' => $lowerThreshold, 'upper' => $upperThreshold, 'reason' => 'soil_wet'];
    }

    return ['action' => null, 'lower' => $lowerThreshold, 'upper' => $upperThreshold, 'reason' => 'hysteresis_hold'];
}

function queueAutoCommandIfNeeded($conn, $device, $zone_id, $moisture, $tank_level, $pump_state = null) {
    $user_id = intval($device['user_id']);
    $minimumOnSeconds = 5;

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

    $settingsStmt = $conn->prepare("SELECT auto_mode, moisture_threshold FROM system_settings WHERE user_id = ? LIMIT 1");
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
    $decision = getAutoDecision($moisture, $tank_level, $threshold);
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
    $lastStmt = $conn->prepare("SELECT command_type, created_at FROM commands WHERE zone_id = ? AND command_type IN ('turn_on', 'turn_off') AND status = 'executed' ORDER BY id DESC LIMIT 1");
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
            // Override dedup if ESP32 reports actual pump state disagrees (e.g. reboot reset relay)
            $pumpIsOn = ($pump_state === 1 || $pump_state === true);
            $desyncDetected = ($desiredAction === 'turn_on' && !$pumpIsOn && $pump_state !== null)
                           || ($desiredAction === 'turn_off' && $pumpIsOn && $pump_state !== null);
            if (!$desyncDetected) {
                return [
                    'queued' => false,
                    'action' => $desiredAction,
                    'reason' => 'already_in_state',
                    'lower_threshold' => $decision['lower'],
                    'upper_threshold' => $decision['upper']
                ];
            }
        }
    }

    // Cancel ALL existing pending commands for this zone to avoid stale command pileup
    $conn->query("UPDATE commands SET status='failed' WHERE zone_id=$zone_id AND status='pending'");

    $params = json_encode([
        'source' => 'auto_mode_sensor',
        'moisture' => intval($moisture),
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

    // Update last watered timestamp when pump is turned on
    if ($desiredAction === 'turn_on') {
        $conn->query("UPDATE zones SET last_watered = NOW() WHERE id = $zone_id");
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

// Check schedules and queue pump commands when inside/outside a scheduled window
function checkAndQueueSchedule($conn, $device, $zone_id) {
    $user_id  = intval($device['user_id']);
    $device_id = intval($device['id']);

    // Current time in Philippines timezone (UTC+8)
    $now        = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $currentTime = $now->format('H:i:s');

    // Get all enabled schedules for this zone
    $stmt = $conn->prepare("SELECT * FROM schedules WHERE zone_id = ? AND user_id = ? AND enabled = 1");
    $stmt->bind_param("ii", $zone_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if (!$result) return;

    $inWindow   = false;
    $activeScheduleId = null;
    while ($row = $result->fetch_assoc()) {
        $startDt = DateTime::createFromFormat('H:i:s', $row['start_time'], new DateTimeZone('Asia/Manila'));
        if (!$startDt) continue;
        $endDt = clone $startDt;
        $endDt->modify('+' . intval($row['duration']) . ' minutes');
        if ($currentTime >= $startDt->format('H:i:s') && $currentTime < $endDt->format('H:i:s')) {
            $inWindow = true;
            $activeScheduleId = $row['id'];
            break;
        }
    }

    // Get the latest executed command to check state
    $lastStmt = $conn->prepare("SELECT command_type, params FROM commands WHERE zone_id = ? AND status = 'executed' ORDER BY id DESC LIMIT 1");
    $lastStmt->bind_param("i", $zone_id);
    $lastStmt->execute();
    $lastCmd = $lastStmt->get_result()->fetch_assoc();

    // Check for any pending/sent command to avoid duplicates
    $pendStmt = $conn->prepare("SELECT command_type FROM commands WHERE zone_id = ? AND status IN ('pending','sent') ORDER BY id DESC LIMIT 1");
    $pendStmt->bind_param("i", $zone_id);
    $pendStmt->execute();
    $pendCmd = $pendStmt->get_result()->fetch_assoc();
    $pendingAction = $pendCmd ? $pendCmd['command_type'] : null;

    if ($inWindow) {
        // Inside window: ensure pump is ON
        $alreadyOn = ($lastCmd && $lastCmd['command_type'] === 'turn_on') || $pendingAction === 'turn_on';
        if (!$alreadyOn) {
            // Cancel any pending turn_off
            $conn->query("UPDATE commands SET status='failed' WHERE zone_id=$zone_id AND command_type='turn_off' AND status='pending'");
            $params = json_encode(['source' => 'schedule', 'schedule_id' => $activeScheduleId]);
            $ins = $conn->prepare("INSERT INTO commands (zone_id, device_id, command_type, params) VALUES (?, ?, 'turn_on', ?)");
            $ins->bind_param("iis", $zone_id, $device_id, $params);
            $ins->execute();
            $conn->query("UPDATE zones SET last_watered = NOW() WHERE id = $zone_id");
        }
    } else {
        // Outside window: if last turn_on was from a schedule, turn off
        if ($lastCmd && $lastCmd['command_type'] === 'turn_on' && $pendingAction !== 'turn_off') {
            $lastParams = json_decode($lastCmd['params'] ?? '{}', true);
            if (($lastParams['source'] ?? '') === 'schedule') {
                $params = json_encode(['source' => 'schedule_end']);
                $ins = $conn->prepare("INSERT INTO commands (zone_id, device_id, command_type, params) VALUES (?, ?, 'turn_off', ?)");
                $ins->bind_param("iis", $zone_id, $device_id, $params);
                $ins->execute();
            }
        }
    }
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
    
    // Extract sensor values from payload only (no fabricated defaults)
    if (!isset($input['moisture'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'moisture is required']);
        exit;
    }

    $moisture = intval($input['moisture']);
    $temperature = (isset($input['temperature']) && $input['temperature'] !== '') ? floatval($input['temperature']) : null;
    $humidity = (isset($input['humidity']) && $input['humidity'] !== '') ? intval($input['humidity']) : null;
    $tank_level = (isset($input['tank_level']) && $input['tank_level'] !== '') ? intval($input['tank_level']) : null;
    $pump_state = isset($input['pump_state']) ? intval($input['pump_state']) : null;
    
    // Validate ranges
    $moisture = min(100, max(0, $moisture));
    if ($humidity !== null) {
        $humidity = min(100, max(0, $humidity));
    }
    if ($tank_level !== null) {
        $tank_level = min(100, max(0, $tank_level));
    }
    
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
        $temperatureSql = $temperature === null ? "NULL" : (string)$temperature;
        $humiditySql = $humidity === null ? "NULL" : (string)$humidity;
        $tankLevelSql = $tank_level === null ? "NULL" : (string)$tank_level;
        $sql = "INSERT INTO sensor_data (zone_id, moisture_level, temperature, humidity, tank_level) VALUES ($zone_id, $moisture, $temperatureSql, $humiditySql, $tankLevelSql)";

        if ($conn->query($sql)) {
            // Update zone moisture level
            $update = $conn->prepare("UPDATE zones SET moisture_level = ? WHERE id = ?");
            $update->bind_param("ii", $moisture, $zone_id);
            $update->execute();

            // Schedule-based control: check if current time is within a scheduled window
            checkAndQueueSchedule($conn, $device, $zone_id);

            // Auto-control: queue relay command based on latest moisture and system settings.
            $autoResult = queueAutoCommandIfNeeded($conn, $device, $zone_id, $moisture, $tank_level);
            
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
            'moisture_threshold' => intval($settings['moisture_threshold'] ?? 50)
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
