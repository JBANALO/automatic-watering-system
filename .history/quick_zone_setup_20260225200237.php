<?php
/**
 * Quick Zone Setup - Creates a test zone and assigns unassigned devices
 */
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['user_id'])) {
    die(json_encode([
        'status' => 'error', 
        'message' => 'Please login first at indwx.html'
    ]));
}

$user_id = $_SESSION['user_id'];

// Check if zone already exists
$zone_check = $conn->query("SELECT id, zone_name FROM zones WHERE user_id=$user_id LIMIT 1");

if ($zone_check->num_rows > 0) {
    $existing_zone = $zone_check->fetch_assoc();
    $zone_id = $existing_zone['id'];
    $zone_name = $existing_zone['zone_name'];
    $created_zone = false;
} else {
    // Create a new zone
    $zone_name = "Test Garden Zone";
    $stmt = $conn->prepare("INSERT INTO zones (user_id, zone_name, enabled, moisture_level) VALUES (?, ?, 0, 50)");
    $stmt->bind_param("is", $user_id, $zone_name);
    
    if (!$stmt->execute()) {
        die(json_encode([
            'status' => 'error',
            'message' => 'Failed to create zone: ' . $conn->error
        ]));
    }
    
    $zone_id = $conn->insert_id;
    $created_zone = true;
}

// Find unassigned devices for this user
$unassigned = $conn->query("
    SELECT id, device_id, device_name 
    FROM devices 
    WHERE user_id=$user_id AND (zone_id IS NULL OR zone_id = 0)
");

$assigned_devices = [];
while ($device = $unassigned->fetch_assoc()) {
    $device_db_id = $device['id'];
    $update = $conn->prepare("UPDATE devices SET zone_id = ? WHERE id = ?");
    $update->bind_param("ii", $zone_id, $device_db_id);
    
    if ($update->execute()) {
        $assigned_devices[] = [
            'device_id' => $device['device_id'],
            'device_name' => $device['device_name']
        ];
    }
}

// Return results
echo json_encode([
    'status' => 'success',
    'message' => 'Setup complete!',
    'zone' => [
        'id' => $zone_id,
        'name' => $zone_name,
        'created_new' => $created_zone
    ],
    'devices_assigned' => count($assigned_devices),
    'devices' => $assigned_devices
]);
?>
