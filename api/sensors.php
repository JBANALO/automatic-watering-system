<?php
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

if ($method === 'GET' && $action === 'latest') {
    getLatestSensorData($user_id, $conn);
} elseif ($method === 'GET' && $action === 'history') {
    getSensorHistory($user_id, $conn);
} elseif ($method === 'POST' && $action === 'update') {
    updateSensorData($user_id, $conn);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

function getLatestSensorData($user_id, $conn) {
    $result = $conn->query("
        SELECT 
            z.id,
            z.zone_name,
            s.moisture_level,
            s.temperature,
            s.humidity,
            s.rainfall,
            s.tank_level,
            s.recorded_at
        FROM zones z
        LEFT JOIN (
            SELECT * FROM sensor_data 
            WHERE zone_id IN (SELECT id FROM zones WHERE user_id=$user_id)
            ORDER BY zone_id, recorded_at DESC
        ) s ON z.id = s.zone_id AND s.recorded_at = (
            SELECT recorded_at FROM sensor_data 
            WHERE zone_id = z.id 
            ORDER BY recorded_at DESC LIMIT 1
        )
        WHERE z.user_id = $user_id
        ORDER BY z.id ASC
    ");

    if ($result === false) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to load sensor data']);
        return;
    }
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    echo json_encode(['status' => 'success', 'sensors' => $data]);
}

function getSensorHistory($user_id, $conn) {
    $zone_id = intval($_GET['zone_id'] ?? 0);
    $limit = intval($_GET['limit'] ?? 100);
    
    $result = $conn->query("
        SELECT sd.* FROM sensor_data sd
        JOIN zones z ON sd.zone_id = z.id
        WHERE z.user_id = $user_id AND sd.zone_id = $zone_id
        ORDER BY sd.recorded_at DESC
        LIMIT $limit
    ");

    if ($result === false) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to load sensor history']);
        return;
    }
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    
    echo json_encode(['status' => 'success', 'history' => array_reverse($history)]);
}

function updateSensorData($user_id, $conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    $zone_id = intval($input['zone_id'] ?? 0);

    if (!isset($input['moisture_level'])) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'moisture_level is required']);
        return;
    }

    $moisture = intval($input['moisture_level']);
    $temperature = (isset($input['temperature']) && $input['temperature'] !== '') ? floatval($input['temperature']) : null;
    $humidity = (isset($input['humidity']) && $input['humidity'] !== '') ? intval($input['humidity']) : null;
    $rainfall = (isset($input['rainfall']) && $input['rainfall'] !== '') ? intval($input['rainfall']) : null;
    $tank_level = (isset($input['tank_level']) && $input['tank_level'] !== '') ? intval($input['tank_level']) : null;
    
    // Verify zone belongs to user
    $check = $conn->query("SELECT id FROM zones WHERE id=$zone_id AND user_id=$user_id");
    if ($check->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Zone not found']);
        return;
    }
    
    $moisture = min(100, max(0, $moisture));
    if ($humidity !== null) {
        $humidity = min(100, max(0, $humidity));
    }
    if ($rainfall !== null) {
        $rainfall = min(100, max(0, $rainfall));
    }
    if ($tank_level !== null) {
        $tank_level = min(100, max(0, $tank_level));
    }

    $temperatureSql = $temperature === null ? "NULL" : (string)$temperature;
    $humiditySql = $humidity === null ? "NULL" : (string)$humidity;
    $rainfallSql = $rainfall === null ? "NULL" : (string)$rainfall;
    $tankLevelSql = $tank_level === null ? "NULL" : (string)$tank_level;
    $sql = "INSERT INTO sensor_data (zone_id, moisture_level, temperature, humidity, rainfall, tank_level) 
            VALUES ($zone_id, $moisture, $temperatureSql, $humiditySql, $rainfallSql, $tankLevelSql)";
    
    if ($conn->query($sql)) {
        // Update zone moisture level
        $conn->query("UPDATE zones SET moisture_level=$moisture WHERE id=$zone_id");
        echo json_encode(['status' => 'success', 'message' => 'Sensor data recorded']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Failed to record sensor data']);
    }
}
?>
