<?php
/**
 * Device Control API
 * For ESP32 to poll for pending commands and acknowledge execution
 * 
 * GET /api/device_control.php?action=poll
 * Headers: X-API-Key: your_device_api_key
 * 
 * POST /api/device_control.php?action=acknowledge
 * Body: { "command_id": 123, "status": "executed" }
 */

require_once '../db_config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: X-API-Key, Content-Type');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// Authenticate device
function authenticateDevice($conn) {
    $api_key = $_SERVER['HTTP_X_API_KEY'] ?? '';
    
    if (empty($api_key)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'API key required']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT id, zone_id, user_id FROM devices WHERE api_key = ? AND status = 'active'");
    $stmt->bind_param("s", $api_key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Invalid or inactive API key']);
        exit;
    }
    
    $device = $result->fetch_assoc();
    
    // Update last_seen
    $update = $conn->prepare("UPDATE devices SET last_seen = NOW() WHERE id = ?");
    $update->bind_param("i", $device['id']);
    $update->execute();
    
    return $device;
}

// Poll for pending commands
if ($method === 'GET' && $action === 'poll') {
    $device = authenticateDevice($conn);
    $zone_id  = $device['zone_id'];
    $user_id  = $device['user_id'];

    // Fetch auto mode settings so ESP32 can act locally without a server roundtrip
    $sres = $conn->query("SELECT auto_mode, moisture_threshold FROM system_settings WHERE user_id=$user_id");
    $settings = ['auto_mode' => 0, 'moisture_threshold' => 50];
    if ($sres && $sres->num_rows > 0) {
        $row = $sres->fetch_assoc();
        $settings = ['auto_mode' => (int)$row['auto_mode'], 'moisture_threshold' => (int)$row['moisture_threshold']];
    }

    if (empty($zone_id)) {
        echo json_encode(['status' => 'success', 'commands' => [], 'settings' => $settings]);
        exit;
    }
    
    // Get only the latest pending command for this zone
    $stmt = $conn->prepare("
        SELECT id, command_type, params, created_at 
        FROM commands 
        WHERE zone_id = ? AND status = 'pending' 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->bind_param("i", $zone_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $commands = [];
    while ($row = $result->fetch_assoc()) {
        $commands[] = [
            'command_id' => $row['id'],
            'action' => $row['command_type'],
            'params' => json_decode($row['params'], true),
            'timestamp' => $row['created_at']
        ];
        
        // Mark as sent
        $update = $conn->prepare("UPDATE commands SET status = 'sent', sent_at = NOW() WHERE id = ?");
        $update->bind_param("i", $row['id']);
        $update->execute();
    }
    
    echo json_encode([
        'status' => 'success',
        'commands' => $commands,
        'count' => count($commands),
        'settings' => $settings
    ]);
}

// Acknowledge command execution
elseif ($method === 'POST' && $action === 'acknowledge') {
    $device = authenticateDevice($conn);
    $input = json_decode(file_get_contents('php://input'), true);
    
    $command_id = intval($input['command_id'] ?? 0);
    $exec_status = $input['status'] ?? 'executed';
    
    if ($command_id <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid command_id']);
        exit;
    }
    
    // Validate status
    $valid_statuses = ['executed', 'failed'];
    if (!in_array($exec_status, $valid_statuses)) {
        $exec_status = 'executed';
    }
    
    // Update command status
    $stmt = $conn->prepare("UPDATE commands SET status = ?, executed_at = NOW() WHERE id = ?");
    $stmt->bind_param("si", $exec_status, $command_id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Command acknowledged',
            'command_id' => $command_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to update command']);
    }
}

// Send immediate command (for testing)
elseif ($method === 'POST' && $action === 'send') {
    $device = authenticateDevice($conn);
    $input = json_decode(file_get_contents('php://input'), true);
    
    $zone_id = $device['zone_id'];
    $command_type = $input['command'] ?? 'turn_on';
    $params = json_encode($input['params'] ?? []);
    
    $stmt = $conn->prepare("INSERT INTO commands (zone_id, device_id, command_type, params) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $zone_id, $device['id'], $command_type, $params);
    
    if ($stmt->execute()) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Command queued',
            'command_id' => $conn->insert_id
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to queue command']);
    }
}

else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
?>
