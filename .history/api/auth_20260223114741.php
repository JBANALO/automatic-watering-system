<?php
session_start();
require_once '../db_config.php';
require_once 'GmailSMTP.php';

// Gmail Configuration
define('GMAIL_USER', 'asniasrp@gmail.com');
define('GMAIL_PASSWORD', 'lphl pzqf ijlb ajqq');

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
    } elseif ($action === 'forgot-password') {
        sendPasswordResetEmail($input, $conn);
    } elseif ($action === 'reset-password') {
        resetPassword($input, $conn);
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

function sendEmailViaSMTP($to, $subject, $htmlContent) {
    try {
        $smtp = new GmailSMTP(GMAIL_USER, GMAIL_PASSWORD);
        $result = $smtp->send($to, $subject, $htmlContent);
        
        if ($result) {
            return true;
        } else {
            error_log("Failed to send email to: $to");
            return false;
        }
    } catch (Exception $e) {
        error_log("Email exception: " . $e->getMessage());
        return false;
    }
}

function sendVerificationEmail($email, $firstName, $verificationCode) {
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
    
    return sendEmailViaSMTP($email, $subject, $htmlContent);
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
            // Email failed - for development/testing, show the code
            echo json_encode([
                'status' => 'success', 
                'message' => 'Registration successful! Email service not configured. Use code for testing: ' . $verificationCode,
                'user_id' => $user_id,
                'requires_verification' => true,
                'test_code' => $verificationCode,
                'email_send_note' => 'Email service needs Gmail SMTP configuration'
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
    
    $result = $conn->query("SELECT id, password, email_verified FROM users WHERE username='$username'");
    
    if ($result->num_rows === 0) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
        return;
    }
    
    $user = $result->fetch_assoc();
    
    if (!$user['email_verified']) {
        http_response_code(403);
        echo json_encode([
            'status' => 'error', 
            'message' => 'Please verify your email before logging in',
            'requires_verification' => true,
            'user_id' => $user['id']
        ]);
        return;
    }
    
    if (password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        echo json_encode(['status' => 'success', 'message' => 'Login successful', 'user_id' => $user['id']]);
    } else {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
    }
}

function verifyEmail($input, $conn) {
    $userId = intval($input['user_id'] ?? 0);
    $code = $conn->real_escape_string($input['code'] ?? '');
    
    if (empty($userId) || empty($code)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Missing user ID or code']);
        return;
    }
    
    $result = $conn->query("SELECT verification_code, verification_code_expires, email_verified FROM users WHERE id=$userId");
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        return;
    }
    
    $user = $result->fetch_assoc();
    
    if ($user['email_verified']) {
        echo json_encode(['status' => 'error', 'message' => 'Email already verified']);
        return;
    }
    
    if (strtotime($user['verification_code_expires']) < time()) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Verification code expired']);
        return;
    }
    
    if (password_verify($code, $user['verification_code'])) {
        $conn->query("UPDATE users SET email_verified=1, verification_code=NULL, verification_code_expires=NULL WHERE id=$userId");
        
        // Initialize zones for new user
        $zones = ['Front Garden', 'Backyard Lawn', 'Vegetable Garden', 'Side Pathway'];
        foreach ($zones as $zone_name) {
            $conn->query("INSERT INTO zones (user_id, zone_name, moisture_level) VALUES ($userId, '$zone_name', 0)");
        }
        
        // Initialize system settings
        $conn->query("INSERT INTO system_settings (user_id) VALUES ($userId)");
        
        $_SESSION['user_id'] = $userId;
        echo json_encode([
            'status' => 'success', 
            'message' => 'Email verified successfully! You are now logged in.',
            'user_id' => $userId
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid verification code']);
    }
}

function getUserInfo($conn) {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
        return;
    }
    
    $user_id = $_SESSION['user_id'];
    $result = $conn->query("SELECT id, username, first_name, last_name, email FROM users WHERE id=$user_id");
    $user = $result->fetch_assoc();
    
    echo json_encode(['status' => 'success', 'user' => $user]);
}

function sendPasswordResetEmail($input, $conn) {
    $email = $conn->real_escape_string($input['email'] ?? '');
    
    if (empty($email)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Email is required']);
        return;
    }
    
    // Check if user exists
    $result = $conn->query("SELECT id, first_name FROM users WHERE email='$email'");
    
    if ($result->num_rows === 0) {
        // Don't reveal if email exists (security best practice)
        echo json_encode(['status' => 'success', 'message' => 'If this email exists, a reset link will be sent shortly']);
        return;
    }
    
    $user = $result->fetch_assoc();
    $resetCode = generateVerificationCode();
    $resetCodeHash = password_hash($resetCode, PASSWORD_BCRYPT);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 minutes'));
    
    // Store reset code
    $conn->query("UPDATE users SET password_reset_code='$resetCodeHash', password_reset_expires='$expiresAt' WHERE id={$user['id']}");
    
    $subject = "Smart Irrigation System - Password Reset Code";
    
    $htmlContent = "
    <html>
    <body style='font-family: Arial, sans-serif; background-color: #f5f5f5; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background-color: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
            <h2 style='color: #667eea; margin-bottom: 20px;'>Password Reset Request</h2>
            
            <p style='font-size: 16px; color: #333; margin-bottom: 20px;'>
                Hi {$user['first_name']},
            </p>
            
            <p style='font-size: 16px; color: #333; margin-bottom: 20px;'>
                We received a request to reset your password. Use the code below to reset your account password:
            </p>
            
            <div style='background-color: #f0f0f0; padding: 20px; border-radius: 8px; text-align: center; margin: 30px 0;'>
                <p style='font-size: 12px; color: #999; margin: 0; margin-bottom: 10px;'>RESET CODE</p>
                <h1 style='color: #667eea; font-size: 48px; letter-spacing: 10px; margin: 0; font-family: monospace;'>$resetCode</h1>
            </div>
            
            <p style='font-size: 14px; color: #666; margin-bottom: 10px;'>
                <strong>⏱️ This code will expire in 30 minutes</strong>
            </p>
            
            <p style='font-size: 14px; color: #666; margin-bottom: 20px;'>
                If you didn't request a password reset, please ignore this email. Your account is secure.
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
    
    $emailSent = sendEmailViaSMTP($email, $subject, $htmlContent);
    
    if ($emailSent) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Password reset code sent to your email!'
        ]);
    } else {
        echo json_encode([
            'status' => 'success',
            'message' => 'If this email exists, a reset link will be sent shortly'
        ]);
    }
}

function resetPassword($input, $conn) {
    $email = $conn->real_escape_string($input['email'] ?? '');
    $code = $conn->real_escape_string($input['code'] ?? '');
    $newPassword = $input['new_password'] ?? '';
    
    if (empty($email) || empty($code) || empty($newPassword)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'All fields are required']);
        return;
    }
    
    $result = $conn->query("SELECT id, password_reset_code, password_reset_expires FROM users WHERE email='$email'");
    
    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'User not found']);
        return;
    }
    
    $user = $result->fetch_assoc();
    
    if (strtotime($user['password_reset_expires']) < time()) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Password reset code has expired']);
        return;
    }
    
    if (password_verify($code, $user['password_reset_code'])) {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $conn->query("UPDATE users SET password='$hashedPassword', password_reset_code=NULL, password_reset_expires=NULL WHERE id={$user['id']}");
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Password reset successfully! You can now log in with your new password.'
        ]);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid reset code']);
    }
}
?>
