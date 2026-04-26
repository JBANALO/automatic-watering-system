<?php
echo "<h1>Environment Variables Debug</h1>";
echo "<pre>";
echo "MYSQL_URL: " . ($_ENV['MYSQL_URL'] ?? getenv('MYSQL_URL') ?? 'NOT FOUND') . "\n";
echo "MYSQL_DATABASE: " . ($_ENV['MYSQL_DATABASE'] ?? getenv('MYSQL_DATABASE') ?? 'NOT FOUND') . "\n";
echo "MYSQLHOST: " . ($_ENV['MYSQLHOST'] ?? getenv('MYSQLHOST') ?? 'NOT FOUND') . "\n";
echo "\nAll env vars:\n";
foreach ($_ENV as $key => $value) {
    if (strpos($key, 'MYSQL') !== false) {
        echo "$key = " . substr($value, 0, 50) . "\n";
    }
}
echo "\ngetenv() vars:\n";
$vars = getenv();
foreach ($vars as $key => $value) {
    if (strpos($key, 'MYSQL') !== false) {
        echo "$key = " . substr($value, 0, 50) . "\n";
    }
}
echo "</pre>";
?>
