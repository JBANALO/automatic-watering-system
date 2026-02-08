<?php
session_start();
require_once '../db_config.php';

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if ($action === 'register') {
        registerUser($input, $conn);
    } elseif ($action === 'login') {
        loginUser($input, $conn);
    }
} elseif ($method === 'GET' && $action === 'logout') {
    session_destroy();
    echo json_encode(['status' => 'success', 'message' => 'Logged out successfully']);
} elseif ($method === 'GET' && $action === 'user') {
    getUserInfo($conn);
} else {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}

function registerUser($input, $conn) {
    $username = $conn->real_escape_string($input['username'] ?? '');
    $email = $conn->real_escape_string($input['email'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        return;
    }
    
    // Check if user exists
    $check = $conn->query("SELECT id FROM users WHERE username='$username' OR email='$email'");
    if ($check->num_rows > 0) {
        http_response_code(409);
        echo json_encode(['status' => 'error', 'message' => 'Username or email already exists']);
        return;
    }
    
    $hashed = password_hash($password, PASSWORD_BCRYPT);
    $sql = "INSERT INTO users (username, email, password) VALUES ('$username', '$email', '$hashed')";
    
    if ($conn->query($sql)) {
        $user_id = $conn->insert_id;
        
        // Initialize zones for new user
        $zones = ['Front Garden', 'Backyard Lawn', 'Vegetable Garden', 'Side Pathway'];
        foreach ($zones as $zone_name) {
            $conn->query("INSERT INTO zones (user_id, zone_name, moisture_level) VALUES ($user_id, '$zone_name', 0)");
        }
        
        // Initialize system settings
        $conn->query("INSERT INTO system_settings (user_id) VALUES ($user_id)");
        
        echo json_encode(['status' => 'success', 'message' => 'User registered successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Registration failed']);
    }
}

function loginUser($input, $conn) {
    $username = $conn->real_escape_string($input['username'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing username or password']);
        return;
    }
    
    $result = $conn->query("SELECT id, password FROM users WHERE username='$username'");
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
        return;
    }
    
    $user = $result->fetch_assoc();
    
    if (password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        echo json_encode(['status' => 'success', 'message' => 'Login successful', 'user_id' => $user['id']]);
    } else {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
    }
}

function getUserInfo($conn) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    $result = $conn->query("SELECT id, username, email FROM users WHERE id=$user_id");
    $user = $result->fetch_assoc();
    
    echo json_encode(['status' => 'success', 'user' => $user]);
}
?>
