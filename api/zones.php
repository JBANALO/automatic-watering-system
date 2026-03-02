<?php
session_start();
require_once '../db_config.php';

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
} elseif ($method === 'POST' && $action === 'update') {
    updateZone($user_id, $conn);
} elseif ($method === 'POST' && $action === 'toggle') {
    toggleZone($user_id, $conn);
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
        // Queue command for hardware devices
        $command_type = $enabled ? 'turn_on' : 'turn_off';
        $params = json_encode(['source' => 'web_interface']);
        
        $cmd_stmt = $conn->prepare("INSERT INTO commands (zone_id, command_type, params) VALUES (?, ?, ?)");
        $cmd_stmt->bind_param("iss", $zone_id, $command_type, $params);
        $cmd_stmt->execute();
        
        echo json_encode([
            'status' => 'success', 
            'message' => 'Zone toggled', 
            'enabled' => $enabled,
            'command_queued' => true
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Toggle failed']);
    }
}
?>
