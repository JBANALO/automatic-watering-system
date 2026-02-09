<?php
/**
 * Add tank_level column to existing database
 * Run once: http://localhost/automatic-watering-system/add_tank_level.php
 */

require_once 'db_config.php';

echo "<!DOCTYPE html>
<html>
<head><title>Add Tank Level Column</title>
<style>body{font-family:Arial;padding:20px;background:#f5f5f5;}
.success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:15px;border-radius:4px;margin:10px 0;}
.error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:4px;margin:10px 0;}
</style></head><body>";

// Add tank_level column to sensor_data table
$sql = "ALTER TABLE sensor_data ADD COLUMN IF NOT EXISTS tank_level INT DEFAULT 100 AFTER rainfall";

if ($conn->query($sql)) {
    echo "<div class='success'><strong>✅ Success!</strong><br>The tank_level column has been added to sensor_data table.</div>";
    echo "<div class='success'>You can now close this page and refresh your dashboard.</div>";
    echo "<p><a href='indwx.html'>Go to Dashboard</a></p>";
} else {
    // Try alternative syntax for older MySQL versions
    $checkSql = "SHOW COLUMNS FROM sensor_data LIKE 'tank_level'";
    $result = $conn->query($checkSql);
    
    if ($result && $result->num_rows > 0) {
        echo "<div class='success'><strong>✅ Column Already Exists!</strong><br>The tank_level column is already in the database.</div>";
        echo "<p><a href='indwx.html'>Go to Dashboard</a></p>";
    } else {
        // Try without IF NOT EXISTS for older MySQL
        $sql2 = "ALTER TABLE sensor_data ADD COLUMN tank_level INT DEFAULT 100 AFTER rainfall";
        if ($conn->query($sql2)) {
            echo "<div class='success'><strong>✅ Success!</strong><br>The tank_level column has been added.</div>";
            echo "<p><a href='indwx.html'>Go to Dashboard</a></p>";
        } else {
            echo "<div class='error'><strong>❌ Error:</strong> " . $conn->error . "</div>";
            echo "<div class='error'>Try running setup.php to recreate all tables.</div>";
        }
    }
}

$conn->close();
echo "</body></html>";
?>
