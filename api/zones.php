<?php
require_once '../db_config.php';\nsession_start();

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
    $stmt = $conn->prepare("SELECT * FROM zones WHERE user_id = ? ORDER BY id ASC");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
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
    
    // Check for duplicate zone names for this user
    $check = $conn->prepare("SELECT id FROM zones WHERE user_id = ? AND zone_name = ?");
    $check->bind_param("is", $user_id, $zone_name);
    $check->execute();
    $checkResult = $check->get_result();
    if ($checkResult->num_rows > 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'A zone with this name already exists']);
        return;
    }
    
    // Create the new zone with default settings
    $insert = $conn->prepare("INSERT INTO zones (user_id, zone_name, enabled, moisture_level, created_at) VALUES (?, ?, 1, 50, NOW())");
    $insert->bind_param("is", $user_id, $zone_name);

    if ($insert->execute()) {
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
    $check = $conn->prepare("SELECT id FROM zones WHERE id = ? AND user_id = ?");
    $check->bind_param("ii", $zone_id, $user_id);
    $check->execute();
    $checkResult = $check->get_result();
    if ($checkResult->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Zone not found']);
        return;
    }
    
    $moisture = min(100, max(0, $moisture));
    $update = $conn->prepare("UPDATE zones SET moisture_level = ? WHERE id = ?");
    $update->bind_param("ii", $moisture, $zone_id);

    if ($update->execute()) {
        // Record sensor data
        $insert = $conn->prepare("INSERT INTO sensor_data (zone_id, moisture_level) VALUES (?, ?)");
        $insert->bind_param("ii", $zone_id, $moisture);
        $insert->execute();
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
    $check = $conn->prepare("SELECT id FROM zones WHERE id = ? AND user_id = ?");
    $check->bind_param("ii", $zone_id, $user_id);
    $check->execute();
    $checkResult = $check->get_result();
    if ($checkResult->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Zone not found']);
        return;
    }
    
    $enabled_val = $enabled ? 1 : 0;
    $update = $conn->prepare("UPDATE zones SET enabled = ? WHERE id = ?");
    $update->bind_param("ii", $enabled_val, $zone_id);

    if ($update->execute()) {
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
    http_response_code(410);
    echo json_encode([
        'status' => 'error',
        'message' => 'Manual zone watering has been removed. Auto mode handles watering automatically.'
    ]);
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
    $check = $conn->prepare("SELECT id FROM zones WHERE id = ? AND user_id = ?");
    $check->bind_param("ii", $zone_id, $user_id);
    $check->execute();
    $checkResult = $check->get_result();
    if ($checkResult->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Zone not found']);
        return;
    }
    
    // Delete the zone
    $delete = $conn->prepare("DELETE FROM zones WHERE id = ? AND user_id = ?");
    $delete->bind_param("ii", $zone_id, $user_id);

    if ($delete->execute()) {
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
