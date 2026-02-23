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
    } elseif ($action === 'verify-email') {
        verifyEmail($input, $conn);
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

function generateVerificationCode() {
    return str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}

function sendVerificationEmail($email, $firstName, $verificationCode) {
    // Email configuration
    $to = $email;
    $subject = "Smart Irrigation System - Email Verification Code";
    
    $htmlContent = "
    <html>
    <body style='font-family: Arial, sans-serif; background-color: #f5f5f5; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
            <h2 style='color: #667eea; margin-bottom: 20px;'>Welcome to Smart Irrigation System, $firstName!</h2>
            
            <p style='font-size: 16px; color: #333; margin-bottom: 20px;'>
                Thank you for creating an account. To complete your registration, please verify your email by entering the code below:
            </p>
            
            <div style='background-color: #f0f0f0; padding: 20px; border-radius: 8px; text-align: center; margin: 30px 0;'>
                <p style='font-size: 12px; color: #999; margin: 0; margin-bottom: 10px;'>VERIFICATION CODE</p>
                <h1 style='color: #667eea; font-size: 48px; letter-spacing: 10px; margin: 0; font-family: monospace;'>$verificationCode</h1>
            </div>
            
            <p style='font-size: 14px; color: #666; margin-bottom: 10px;'>
                <strong>⏱️ This code will expire in 30 minutes</strong>
            </p>
            
            <p style='font-size: 14px; color: #666; margin-bottom: 20px;'>
                If you didn't create this account, please ignore this email or contact our support team.
            </p>
            
            <hr style='border: none; border-top: 1px solid #e0e0e0; margin: 30px 0;'>
            
            <p style='font-size: 12px; color: #999; text-align: center;'>
                Smart Irrigation System<br>
                Automated Garden Control
            </p>
        </div>
    </body>
    </html>
    ";
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: noreply@smartirrigation.local\r\n";
    
    return mail($to, $subject, $htmlContent, $headers);
}

function registerUser($input, $conn) {
    $username = $conn->real_escape_string($input['username'] ?? '');
    $firstName = $conn->real_escape_string($input['firstName'] ?? '');
    $lastName = $conn->real_escape_string($input['lastName'] ?? '');
    $email = $conn->real_escape_string($input['email'] ?? '');
    $password = $input['password'] ?? '';
    
    if (empty($username) || empty($firstName) || empty($lastName) || empty($email) || empty($password)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
        return;
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid email format']);
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
    $verificationCode = generateVerificationCode();
    $verificationCodeHash = password_hash($verificationCode, PASSWORD_BCRYPT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    
    $sql = "INSERT INTO users (username, first_name, last_name, email, password, verification_code, verification_code_expires, email_verified) 
            VALUES ('$username', '$firstName', '$lastName', '$email', '$hashed', '$verificationCodeHash', '$expiresAt', 0)";
    
    if ($conn->query($sql)) {
        $user_id = $conn->insert_id;
        
        // Send verification email
        $emailSent = sendVerificationEmail($email, $firstName, $verificationCode);
        
        if ($emailSent) {
            echo json_encode([
                'status' => 'success', 
                'message' => 'Registration successful! Verification code sent to your email.',
                'user_id' => $user_id,
                'requires_verification' => true
            ]);
        } else {
            // Email failed but user created - still ask for verification
            echo json_encode([
                'status' => 'success', 
                'message' => 'Registration successful! If you don\'t see the email, check your spam folder.',
                'user_id' => $user_id,
                'requires_verification' => true,
                'email_send_note' => 'Email service may need configuration'
            ]);
        }
    } else {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Registration failed: ' . $conn->error]);
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
