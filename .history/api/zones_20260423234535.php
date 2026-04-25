<?php
session_start();
require_once '../db_config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'GET' && $action === 'list') {
    getZones($user_id, $conn);
} elseif ($method === 'POST' && $action === 'create') {
    createZone($user_id, $conn);
} elseif ($method === 'POST' && $action === 'update') {
    updateZone($user_id, $conn);
} elseif ($method === 'POST' && $action === 'toggle') {
    toggleZone($user_id, $conn);
} elseif ($method === 'POST' && $action === 'water_now') {
    waterZone($user_id, $conn);
} elseif ($method === 'POST' && $action === 'delete') {
    deleteZone($user_id, $conn);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

function getZones($user_id, $conn) {
    $result = $conn->query("SELECT * FROM zones WHERE user_id=$user_id ORDER BY id ASC");
    $zones = [];
    
    while ($row = $result->fetch_assoc()) {
        $zones[] = $row;
    }
    
    echo json_encode(['status' => 'success', 'zones' => $zones]);
}

function createZone($user_id, $conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    $zone_name = trim($input['zone_name'] ?? '');
    
    if (empty($zone_name)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Zone name is required']);
        return;
    }
    
    // Escape the zone name for SQL
    $zone_name = $conn->real_escape_string($zone_name);
    
    // Check for duplicate zone names for this user
    $check = $conn->query("SELECT id FROM zones WHERE user_id=$user_id AND zone_name='$zone_name'");
    if ($check->num_rows > 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'A zone with this name already exists']);
        return;
    }
    
    // Create the new zone with default settings
    $sql = "INSERT INTO zones (user_id, zone_name, enabled, moisture_level, created_at) 
            VALUES ($user_id, '$zone_name', 1, 50, NOW())";
    
    if ($conn->query($sql)) {
        $zone_id = $conn->insert_id;
        echo json_encode([
            'status' => 'success', 
            'message' => 'Zone created successfully',
            'zone_id' => $zone_id,
            'zone_name' => $zone_name
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to create zone: ' . $conn->error]);
    }
}


function updateZone($user_id, $conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    $zone_id = intval($input['zone_id'] ?? 0);
    $moisture = intval($input['moisture_level'] ?? 0);
    
    if ($zone_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid zone ID']);
        return;
    }
    
    // Verify zone belongs to user
    $check = $conn->query("SELECT id FROM zones WHERE id=$zone_id AND user_id=$user_id");
    if ($check->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Zone not found']);
        return;
    }
    
    $moisture = min(100, max(0, $moisture));
    $sql = "UPDATE zones SET moisture_level=$moisture WHERE id=$zone_id";
    
    if ($conn->query($sql)) {
        // Record sensor data
        $conn->query("INSERT INTO sensor_data (zone_id, moisture_level) VALUES ($zone_id, $moisture)");
        echo json_encode(['status' => 'success', 'message' => 'Zone updated']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Update failed']);
    }
}

function toggleZone($user_id, $conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    $zone_id = intval($input['zone_id'] ?? 0);
    $enabled = isset($input['enabled']) ? (bool)$input['enabled'] : true;
    
    if ($zone_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid zone ID']);
        return;
    }
    
    // Verify zone belongs to user
    $check = $conn->query("SELECT id FROM zones WHERE id=$zone_id AND user_id=$user_id");
    if ($check->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Zone not found']);
        return;
    }
    
    $enabled_val = $enabled ? 1 : 0;
    $sql = "UPDATE zones SET enabled=$enabled_val WHERE id=$zone_id";
    
    if ($conn->query($sql)) {
        // Zone toggle only enables/disables zone participation.
        // Do not directly control relay from this switch.
        echo json_encode([
            'status' => 'success', 
            'message' => 'Zone toggled', 
            'enabled' => $enabled,
            'command_queued' => false
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Toggle failed']);
    }
}

function waterZone($user_id, $conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    $zone_id = intval($input['zone_id'] ?? 0);
    $duration_minutes = intval($input['duration_minutes'] ?? 0);
    $liters = intval($input['liters'] ?? 0);
    
    if ($zone_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid zone ID']);
        return;
    }
    
    if ($duration_minutes <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid duration']);
        return;
    }
    
    // Verify zone belongs to user
    $check = $conn->query("SELECT id FROM zones WHERE id=$zone_id AND user_id=$user_id");
    if ($check->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Zone not found']);
        return;
    }
    
    // Update last watered timestamp
    $sql = "UPDATE zones SET last_watered = NOW() WHERE id=$zone_id";
    
    if ($conn->query($sql)) {
        // Queue watering command for hardware (relay ON with duration metadata)
        // ESP32 runtime will auto-turn OFF after duration.
        $command_type = 'turn_on';
        $params = json_encode([
            'duration_minutes' => $duration_minutes,
            'liters' => $liters,
            'source' => 'web_interface'
        ]);
        
        $cmd_stmt = $conn->prepare("INSERT INTO commands (zone_id, command_type, params) VALUES (?, ?, ?)");
        if (!$cmd_stmt) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to prepare command: ' . $conn->error]);
            return;
        }

        $cmd_stmt->bind_param("iss", $zone_id, $command_type, $params);
        if (!$cmd_stmt->execute()) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Failed to queue command: ' . $cmd_stmt->error]);
            return;
        }
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Zone watering started',
            'zone_id' => $zone_id,
            'duration_minutes' => $duration_minutes,
            'liters' => $liters
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to water zone: ' . $conn->error]);
    }
}

function deleteZone($user_id, $conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    $zone_id = intval($input['zone_id'] ?? 0);
    
    if ($zone_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid zone ID']);
        return;
    }
    
    // Verify zone belongs to user
    $check = $conn->query("SELECT id FROM zones WHERE id=$zone_id AND user_id=$user_id");
    if ($check->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Zone not found']);
        return;
    }
    
    // Delete the zone
    $sql = "DELETE FROM zones WHERE id=$zone_id AND user_id=$user_id";
    
    if ($conn->query($sql)) {
        echo json_encode([
            'status' => 'success', 
            'message' => 'Zone deleted successfully',
            'zone_id' => $zone_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Delete failed: ' . $conn->error]);
    }
}
?>
