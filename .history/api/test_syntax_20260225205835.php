<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "PHP is working!<br>";
echo "Testing hardware.php include...<br>";

require_once '../db_config.php';
require_once 'hardware.php';

echo "No syntax errors!";
?>
