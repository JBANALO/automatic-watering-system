<?php
/**
 * Device Registration & Management API
 * For registering new ESP32 devices and managing device settings
 * 
 * Requires user authentication (session-based)
 */

require_once '../db_config.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Generate secure API key
function generateApiKey() {
    return bin2hex(random_bytes(32));
}

// Generate unique device ID
function generateDeviceId() {
    return 'ESP32_' . strtoupper(bin2hex(random_bytes(6)));
}

// Register new device
if ($method === 'POST' && $action === 'register') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $device_name = $input['device_name'] ?? 'ESP32 Device';
    $zone_id = isset($input['zone_id']) ? intval($input['zone_id']) : null;
    $device_type = $input['device_type'] ?? 'ESP32';
    
    // Verify zone belongs to user if specified (0 or null = unassigned is OK)
    if ($zone_id !== null && $zone_id > 0) {
        $check = $conn->query("SELECT id FROM zones WHERE id=$zone_id AND user_id=$user_id");
        if ($check->num_rows === 0) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error', 
                'message' => 'Invalid zone. Zone not found or does not belong to you.',
                'hint' => 'Create a zone first in the dashboard, or set zone_id to 0 for unassigned device'
            ]);
            exit;
        }
    } else {
        // Allow zone_id = 0 or null for unassigned devices
        $zone_id = null;
    }
    
    // Generate device credentials
    $device_id = generateDeviceId();
    $api_key = generateApiKey();
    
    $stmt = $conn->prepare("INSERT INTO devices (device_id, api_key, user_id, zone_id, device_name, device_type, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
    $stmt->bind_param("ssiiss", $device_id, $api_key, $user_id, $zone_id, $device_name, $device_type);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Device registered successfully',
            'device' => [
                'id' => $conn->insert_id,
                'device_id' => $device_id,
                'api_key' => $api_key,
                'device_name' => $device_name,
                'zone_id' => $zone_id
            ]
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to register device']);
    }
}

// List all user's devices
elseif ($method === 'GET' && $action === 'list') {
    $result = $conn->query("
        SELECT d.*, z.zone_name 
        FROM devices d 
        LEFT JOIN zones z ON d.zone_id = z.id 
        WHERE d.user_id = $user_id 
        ORDER BY d.created_at DESC
    ");
    
    $devices = [];
    while ($row = $result->fetch_assoc()) {
        $devices[] = [
            'id' => $row['id'],
            'device_id' => $row['device_id'],
            'device_name' => $row['device_name'],
            'device_type' => $row['device_type'],
            'zone_id' => $row['zone_id'],
            'zone_name' => $row['zone_name'],
            'status' => $row['status'],
            'last_seen' => $row['last_seen'],
            'created_at' => $row['created_at'],
            // Only show API key for admin purposes
            'api_key_preview' => substr($row['api_key'], 0, 8) . '...'
        ];
    }
    
    echo json_encode(['status' => 'success', 'devices' => $devices]);
}

// Get device details (including full API key)
elseif ($method === 'GET' && $action === 'details') {
    $device_id = intval($_GET['device_id'] ?? 0);
    
    $stmt = $conn->prepare("
        SELECT d.*, z.zone_name 
        FROM devices d 
        LEFT JOIN zones z ON d.zone_id = z.id 
        WHERE d.id = ? AND d.user_id = ?
    ");
    $stmt->bind_param("ii", $device_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Device not found']);
        exit;
    }
    
    $device = $result->fetch_assoc();
    echo json_encode(['status' => 'success', 'device' => $device]);
}

// Update device settings
elseif ($method === 'POST' && $action === 'update') {
    $input = json_decode(file_get_contents('php://input'), true);
    $device_id = intval($input['device_id'] ?? 0);
    
    $stmt = $conn->prepare("SELECT id FROM devices WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $device_id, $user_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Device not found']);
        exit;
    }
    
    $updates = [];
    $params = [];
    $types = "";
    
    if (isset($input['device_name'])) {
        $updates[] = "device_name = ?";
        $params[] = $input['device_name'];
        $types .= "s";
    }
    
    if (isset($input['zone_id'])) {
        $zone_id = intval($input['zone_id']);
        if ($zone_id > 0) {
            // Verify zone belongs to user
            $check = $conn->query("SELECT id FROM zones WHERE id=$zone_id AND user_id=$user_id");
            if ($check->num_rows === 0) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Invalid zone']);
                exit;
            }
        }
        $updates[] = "zone_id = ?";
        $params[] = $zone_id;
        $types .= "i";
    }
    
    if (isset($input['status'])) {
        $updates[] = "status = ?";
        $params[] = $input['status'];
        $types .= "s";
    }
    
    if (empty($updates)) {
        echo json_encode(['status' => 'error', 'message' => 'No updates provided']);
        exit;
    }
    
    $sql = "UPDATE devices SET " . implode(', ', $updates) . " WHERE id = ?";
    $params[] = $device_id;
    $types .= "i";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Device updated']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Update failed']);
    }
}

// Delete device
elseif ($method === 'DELETE' || ($method === 'POST' && $action === 'delete')) {
    $input = json_decode(file_get_contents('php://input'), true);
    $device_id = intval($input['device_id'] ?? $_GET['device_id'] ?? 0);
    
    $stmt = $conn->prepare("DELETE FROM devices WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $device_id, $user_id);
    
    if ($stmt->execute() && $stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Device deleted']);
    } else {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Device not found']);
    }
}

// Regenerate API key
elseif ($method === 'POST' && $action === 'regenerate_key') {
    $input = json_decode(file_get_contents('php://input'), true);
    $device_id = intval($input['device_id'] ?? 0);
    
    $stmt = $conn->prepare("SELECT id FROM devices WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $device_id, $user_id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Device not found']);
        exit;
    }
    
    $new_api_key = generateApiKey();
    
    $update = $conn->prepare("UPDATE devices SET api_key = ? WHERE id = ?");
    $update->bind_param("si", $new_api_key, $device_id);
    
    if ($update->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'API key regenerated',
            'api_key' => $new_api_key
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to regenerate key']);
    }
}

else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
