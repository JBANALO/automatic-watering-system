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

if ($method === 'GET' && $action === 'get') {
    getSystemSettings($user_id, $conn);
} elseif ($method === 'POST' && $action === 'update') {
    updateSystemSettings($user_id, $conn);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

function getSystemSettings($user_id, $conn) {
    $result = $conn->query("SELECT * FROM system_settings WHERE user_id=$user_id");
    
    if ($result->num_rows === 0) {
        // Create default settings if not exists
        $conn->query("INSERT INTO system_settings (user_id) VALUES ($user_id)");
        $result = $conn->query("SELECT * FROM system_settings WHERE user_id=$user_id");
    }
    
    $settings = $result->fetch_assoc();
    echo json_encode(['status' => 'success', 'settings' => $settings]);
}

function updateSystemSettings($user_id, $conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $auto_mode = isset($input['auto_mode']) ? (int)$input['auto_mode'] : null;
    $moisture_threshold = isset($input['moisture_threshold']) ? intval($input['moisture_threshold']) : null;
    $skip_rain = isset($input['skip_rain']) ? (int)$input['skip_rain'] : null;
    $auto_adjust = isset($input['auto_adjust']) ? (int)$input['auto_adjust'] : null;
    $daily_usage = isset($input['daily_usage']) ? intval($input['daily_usage']) : null;
    $runtime = isset($input['runtime']) ? intval($input['runtime']) : null;
    
    $updates = [];
    if ($auto_mode !== null) $updates[] = "auto_mode=$auto_mode";
    if ($moisture_threshold !== null) $updates[] = "moisture_threshold=$moisture_threshold";
    if ($skip_rain !== null) $updates[] = "skip_rain=$skip_rain";
    if ($auto_adjust !== null) $updates[] = "auto_adjust=$auto_adjust";
    if ($daily_usage !== null) $updates[] = "daily_usage=$daily_usage";
    if ($runtime !== null) $updates[] = "runtime=$runtime";
    
    if (empty($updates)) {
        echo json_encode(['status' => 'error', 'message' => 'No updates provided']);
        return;
    }
    
    $sql = "UPDATE system_settings SET " . implode(', ', $updates) . " WHERE user_id=$user_id";
    
    if ($conn->query($sql)) {
        echo json_encode(['status' => 'success', 'message' => 'Settings updated']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Update failed']);
    }
}
?>
