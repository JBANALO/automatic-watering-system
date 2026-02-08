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
    getSchedules($user_id, $conn);
} elseif ($method === 'POST' && $action === 'create') {
    createSchedule($user_id, $conn);
} elseif ($method === 'POST' && $action === 'update') {
    updateSchedule($user_id, $conn);
} elseif ($method === 'POST' && $action === 'delete') {
    deleteSchedule($user_id, $conn);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

function getSchedules($user_id, $conn) {
    $result = $conn->query("
        SELECT s.*, z.zone_name FROM schedules s
        JOIN zones z ON s.zone_id = z.id
        WHERE s.user_id = $user_id
        ORDER BY s.schedule_type ASC, s.start_time ASC
    ");
    
    $schedules = [];
    while ($row = $result->fetch_assoc()) {
        $schedules[] = $row;
    }
    
    echo json_encode(['status' => 'success', 'schedules' => $schedules]);
}

function createSchedule($user_id, $conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $zone_id = intval($input['zone_id'] ?? 0);
    $start_time = $conn->real_escape_string($input['start_time'] ?? '');
    $duration = intval($input['duration'] ?? 0);
    $schedule_type = $conn->real_escape_string($input['schedule_type'] ?? 'custom');
    
    if ($zone_id <= 0 || empty($start_time) || $duration <= 0) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        return;
    }
    
    // Verify zone belongs to user
    $check = $conn->query("SELECT id FROM zones WHERE id=$zone_id AND user_id=$user_id");
    if ($check->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Zone not found']);
        return;
    }
    
    $sql = "INSERT INTO schedules (user_id, zone_id, start_time, duration, schedule_type) 
            VALUES ($user_id, $zone_id, '$start_time', $duration, '$schedule_type')";
    
    if ($conn->query($sql)) {
        echo json_encode(['status' => 'success', 'message' => 'Schedule created', 'id' => $conn->insert_id]);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Creation failed']);
    }
}

function updateSchedule($user_id, $conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $schedule_id = intval($input['schedule_id'] ?? 0);
    $start_time = isset($input['start_time']) ? $conn->real_escape_string($input['start_time']) : null;
    $duration = isset($input['duration']) ? intval($input['duration']) : null;
    $enabled = isset($input['enabled']) ? intval($input['enabled']) : null;
    
    // Verify schedule belongs to user
    $check = $conn->query("SELECT id FROM schedules WHERE id=$schedule_id AND user_id=$user_id");
    if ($check->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Schedule not found']);
        return;
    }
    
    $updates = [];
    if ($start_time !== null) $updates[] = "start_time='$start_time'";
    if ($duration !== null) $updates[] = "duration=$duration";
    if ($enabled !== null) $updates[] = "enabled=$enabled";
    
    if (empty($updates)) {
        echo json_encode(['status' => 'error', 'message' => 'No updates provided']);
        return;
    }
    
    $sql = "UPDATE schedules SET " . implode(', ', $updates) . " WHERE id=$schedule_id";
    
    if ($conn->query($sql)) {
        echo json_encode(['status' => 'success', 'message' => 'Schedule updated']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Update failed']);
    }
}

function deleteSchedule($user_id, $conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    $schedule_id = intval($input['schedule_id'] ?? 0);
    
    // Verify schedule belongs to user
    $check = $conn->query("SELECT id FROM schedules WHERE id=$schedule_id AND user_id=$user_id");
    if ($check->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Schedule not found']);
        return;
    }
    
    if ($conn->query("DELETE FROM schedules WHERE id=$schedule_id")) {
        echo json_encode(['status' => 'success', 'message' => 'Schedule deleted']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Deletion failed']);
    }
}
?>
