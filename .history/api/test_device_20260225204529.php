<?php
// Simple diagnostic script
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

require_once '../db_config.php';

// Test 1: Check API key
$api_key = $_SERVER['HTTP_X_API_KEY'] ?? 'not_provided';

// Test 2: Get device info
$stmt = $conn->prepare("SELECT id, device_id, zone_id, user_id, device_name FROM devices WHERE api_key = ?");
$stmt->bind_param("s", $api_key);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $device = $result->fetch_assoc();
    
    echo json_encode([
        'status' => 'success',
        'test' => 'Device found',
        'device' => $device,
        'zone_id' => $device['zone_id'],
        'zone_id_type' => gettype($device['zone_id']),
        'is_empty' => empty($device['zone_id']),
        'is_null' => is_null($device['zone_id'])
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'test' => 'Device not found',
        'api_key_received' => substr($api_key, 0, 10) . '...'
    ]);
}
?>
