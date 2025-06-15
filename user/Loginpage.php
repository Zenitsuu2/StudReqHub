<?php
session_start();
require_once '../Connection/database.php';

// Initialize variables
$errors = [];
$max_attempts = 5; // Maximum login attempts
$lockout_time = 15 * 60; // 15 minutes lockout in seconds

// Function to log login attempts
function logLoginAttempt($lrn, $success) {
    global $conn;
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // Check if table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'login_attempts'");
    if ($check_table->num_rows == 0) {
        // Create table if it doesn't exist
        $create_table = "CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            lrn VARCHAR(12) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            success TINYINT(1) NOT NULL,
            attempt_time DATETIME NOT NULL,
            INDEX (lrn),
            INDEX (ip_address),
            INDEX (attempt_time)
        ) ENGINE=InnoDB";
        $conn->query($create_table);
    }
    
    $stmt = $conn->prepare("INSERT INTO login_attempts (lrn, ip_address, success, attempt_time) VALUES (?, ?, ?, NOW())");
    if ($stmt === false) {
        error_log("Database prepare error: " . $conn->error);
        return false;
    }
    
    if (!$stmt->bind_param("ssi", $lrn, $ip, $success)) {
        error_log("Database bind error: " . $stmt->error);
        return false;
    }
    
    if (!$stmt->execute()) {
        error_log("Database execute error: " . $stmt->error);
        return false;
    }
    
    return true;
}

// Function to check if user is locked out
function isLockedOut($lrn) {
    global $conn, $max_attempts, $lockout_time;
    $ip = $_SERVER['REMOTE_ADDR'];
    
    // Check if table exists
    $check_table = $conn->query("SHOW TABLES LIKE 'login_attempts'");
    if ($check_table->num_rows == 0) {
        return false; // Table doesn't exist, so user is not locked out
    }
    
    $stmt = $conn->prepare("SELECT COUNT(*) as attempts FROM login_attempts 
                           WHERE (lrn = ? OR ip_address = ?) 
                           AND success = 0 
                           AND attempt_time > NOW() - INTERVAL ? SECOND");
    
    if ($stmt === false) {
        error_log("Database prepare error: " . $conn->error);
        return false;
    }
    
    if (!$stmt->bind_param("ssi", $lrn, $ip, $lockout_time)) {
        error_log("Database bind error: " . $stmt->error);
        return false;
    }
    
    if (!$stmt->execute()) {
        error_log("Database execute error: " . $stmt->error);
        return false;
    }
    
    $result = $stmt->get_result();
    if ($result === false) {
        error_log("Database result error: " . $stmt->error);
        return false;
    }
    
    $row = $result->fetch_assoc();
    return $row['attempts'] >= $max_attempts;
}

// Handle login
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['lrn']) && isset($_POST['password'])) {
    $lrn = filter_var($_POST['lrn'], FILTER_SANITIZE_STRING);
    $password = $_POST['password'];
    
    // Check if user is locked out
    if (isLockedOut($lrn)) {
        $errors[] = "Account is temporarily locked due to too many failed attempts. Please try again in 15 minutes.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE lrn = ?");
        if ($stmt === false) {
            die('Prepare failed: ' . htmlspecialchars($conn->error));
        }
        
        $stmt->bind_param("s", $lrn);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                // Successful login
                session_regenerate_id(true); // Prevent session fixation
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_data'] = [
                    'id' => $user['id'],
                    'lrn' => $user['lrn'],
                    'firstname' => $user['firstname'],
                    'lastname' => $user['lastname'],
                    'contact' => $user['contact']
                ];
                $_SESSION['last_activity'] = time();
                
                // Log successful attempt
                logLoginAttempt($lrn, 1);
                
                // Clear previous failed attempts
                $stmt = $conn->prepare("DELETE FROM login_attempts WHERE lrn = ? AND success = 0");
                $stmt->bind_param("s", $lrn);
                $stmt->execute();

                // Update last login time
                $stmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();
                
                if ('login') {
                    $_SESSION['just_logged_in'] = true;
                    header('Location: ../Manaloto/test/home.php');
                    exit();
                }
            } else {
                // Failed login - wrong password
                logLoginAttempt($lrn, 0);
                $errors[] = "Invalid password";
            }
        } else {
            // Failed login - LRN not found
            logLoginAttempt($lrn, 0);
            $errors[] = "LRN not found";
        }
    }
}

// Handle password reset request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['forgot_lrn'])) {
    $forgot_lrn = filter_var($_POST['forgot_lrn'], FILTER_SANITIZE_STRING);
    
    // Example server-side verification for the forgot password process
    $recaptcha_response = $_POST['recaptcha_response'] ?? '';
    $secret_key_v3 = '6Lf2XzgrAAAAAPRwXlD1orc0BPXi-IogWw1VW4Gi';
    $verifyResponse = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . $secret_key_v3 . '&response=' . $recaptcha_response);
    $responseData = json_decode($verifyResponse);
    error_log("Forgot password reCAPTCHA v3 response: " . print_r($responseData, true));

    $forgot_fallback_required = false;
    if (!$responseData->success || !isset($responseData->score) || $responseData->score < 0.5) {
        $forgot_fallback_required = true;
    }

    // If a v2 fallback token is submitted, verify that too:
    if (isset($_POST['fallback_recaptcha_response'])) {
        $fallback_response = $_POST['fallback_recaptcha_response'];
        $secret_key_v2 = '6LfBcDgrAAAAACMl5bFRiwUeB1pcVlDvF-ozZMvu'; // Replace with your v2 secret key
        $v2_verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secret_key_v2}&response={$fallback_response}");
        $v2_data = json_decode($v2_verify);
        error_log("Forgot password reCAPTCHA v2 response: " . print_r($v2_data, true));
        if (!$v2_data->success) {
            $errors[] = "Fallback CAPTCHA verification failed. Please try again.";
        } else {
            $forgot_fallback_required = false;
        }
    }

    // Proceed with processing the forgot password reset only if no errors exist.
    if (empty($errors)) {
        $sql = "SELECT * FROM users WHERE lrn = ?";
        $stmt = $conn->prepare($sql);
        if ($stmt === false) {
            die('Prepare failed: ' . htmlspecialchars($conn->error) . ' SQL: ' . $sql);
        }
        $stmt->bind_param("s", $forgot_lrn);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            header("Location: reset_password.php?lrn=$forgot_lrn");
            exit();
        } else {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'This LRN number is not registered in our system.',
                        icon: 'error',
                        confirmButtonText: 'Try Again',
                        confirmButtonColor: '#4361ee',
                        background: 'rgba(33, 37, 41, 0.95)',
                        color: '#fff',
                        showClass: {
                            popup: 'animate__animated animate__fadeInDown'
                        },
                        hideClass: {
                            popup: 'animate__animated animate__fadeOutUp'
                        }
                    });
                });
            </script>";
        }
    }
}

// Session timeout check
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: Loginpage.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Portal Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #4cc9f0;
            --light-color: #f8f9fa;
            --dark-color: #212529;
            --success-color: #2ecc71;
            --error-color: #e74c3c;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            transition: all 0.25s ease;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: url('../image/admin_picture.jpg') no-repeat center center fixed;
            background-size: cover;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 0;
        }

        .page-wrapper {
            display: flex;
            width: 100%;
            min-height: 100vh;
            justify-content: center;
            align-items: center;
            z-index: 1;
            position: relative;
            padding: 20px;
        }

        .floating-shapes {
            position: absolute;
            width: 100%;
            height: 100%;
            overflow: hidden;
            top: 0;
            left: 0;
            z-index: -1;
        }

        .shape {
            position: absolute;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            animation: float 15s linear infinite;
            z-index: -1;
        }

        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation-duration: 25s;
        }

        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 60%;
            left: 80%;
            animation-duration: 30s;
            animation-delay: 2s;
        }

        .shape:nth-child(3) {
            width: 50px;
            height: 50px;
            top: 80%;
            left: 30%;
            animation-duration: 20s;
            animation-delay: 4s;
        }

        .shape:nth-child(4) {
            width: 100px;
            height: 100px;
            top: 30%;
            left: 70%;
            animation-duration: 22s;
            animation-delay: 1s;
        }

        @keyframes float {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 0.8;
            }
            50% {
                transform: translateY(-100px) rotate(180deg);
                opacity: 0.4;
            }
            100% {
                transform: translateY(0) rotate(360deg);
                opacity: 0.8;
            }
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 20px;
            padding: 40px 30px;
            box-shadow: 0 25px 45px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            position: relative;
            overflow: hidden;
            z-index: 10;
            transition: transform 0.5s, box-shadow 0.5s;
            animation: fadeInUp 1s;
            margin: 20px;
        }

        .login-container::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(
                circle at center,
                rgba(255, 255, 255, 0.1) 0%,
                rgba(255, 255, 255, 0) 60%
            );
            transform: rotate(30deg);
            pointer-events: none;
        }

        .login-container:hover {
            transform: translateY(-10px);
            box-shadow: 0 35px 60px rgba(0, 0, 0, 0.3);
        }

        .login-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo {
            width: 125px;
            height: 125px;
            object-fit: contain;
        }

        .login-header h2 {
            color: white;
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            text-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            text-align: center;
        }

        .login-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 16px;
            margin-top: 5px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 25px;
            position: relative;
        }

        .form-group label {
            display: block;
            color: white;
            margin-bottom: 8px;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }

        .input-group {
            position: relative;
            overflow: hidden;
            border-radius: 10px;
        }

        .input-group input {
            width: 100%;
            padding: 15px 20px 15px 50px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            color: white;
            font-size: 16px;
            transition: all 0.3s;
        }

        .input-group i.fas.fa-lock,
        .input-group i.fas.fa-user {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            left: 15px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 20px;
            transition: all 0.3s;
        }

        .input-group .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s;
            z-index: 10;
        }

        .input-group .toggle-password:hover {
            color: var(--accent-color);
        }

        .login-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(45deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 10px;
            color: white;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 1px;
            cursor: pointer;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            position: relative;
            overflow: hidden;
            transition: all 0.3s;
        }

        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: all 0.6s;
        }

        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 25px rgba(0, 0, 0, 0.3);
        }

        .login-btn:hover::before {
            left: 100%;
        }

        .login-btn:active {
            transform: translateY(0);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .login-footer {
            margin-top: 30px;
            text-align: center;
            color: rgba(255, 255, 255, 0.7);
        }

        .login-footer a {
            color: var(--accent-color);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
        }

        .login-footer a:hover {
            color: white;
            text-decoration: underline;
        }

        .error-list {
            background: rgba(231, 76, 60, 0.2);
            border-left: 4px solid var(--error-color);
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 25px;
            animation: shakeX 0.5s;
        }

        .error-list ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .error-list li {
            color: #fff;
            margin-bottom: 5px;
            padding-left: 20px;
            position: relative;
            font-size: 14px;
        }

        .error-list li:before {
            content: '•';
            position: absolute;
            left: 0;
            color: var(--error-color);
        }

        .reset-message {
            background: rgba(46, 204, 113, 0.2);
            border-left: 4px solid var(--success-color);
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 25px;
            color: white;
            text-align: center;
            animation: fadeIn 0.5s;
        }

        /* Modal styling */
        .modal-content {
            background: rgba(33, 37, 41, 0.95);
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
        }

        .modal-header {
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 20px;
        }

        .modal-title {
            color: white;
            font-weight: 600;
        }

        .modal-body {
            padding: 25px;
        }

        .modal .form-group {
            margin-bottom: 20px;
        }

        .modal .input-group input {
            background: rgba(255, 255, 255, 0.07);
        }

        .modal .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 12px 25px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .modal .btn-primary:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
        }

        .modal .btn-close {
            color: white;
            opacity: 0.7;
            transition: all 0.3s;
        }

        .modal .btn-close:hover {
            opacity: 1;
        }

        /* Animation keyframes */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes shakeX {
            0%, 100% {
                transform: translateX(0);
            }
            10%, 30%, 50%, 70%, 90% {
                transform: translateX(-5px);
            }
            20%, 40%, 60%, 80% {
                transform: translateX(5px);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }
            100% {
                transform: rotate(360deg);
            }
        }

        /* Ripple effect for buttons */
        .ripple {
            position: relative;
            overflow: hidden;
        }

        .ripple::after {
            content: "";
            display: block;
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            pointer-events: none;
            background-image: radial-gradient(circle, #fff 10%, transparent 10.01%);
            background-repeat: no-repeat;
            background-position: 50%;
            transform: scale(10, 10);
            opacity: 0;
            transition: transform .5s, opacity 1s;
        }

        .ripple:active::after {
            transform: scale(0, 0);
            opacity: .3;
            transition: 0s;
        }

        /* Input focus animation */
        .input-focus-effect {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background-color: var(--accent-color);
            transition: width 0.3s;
        }

        .input-group input:focus ~ .input-focus-effect {
            width: 100%;
        }

        /* Change placeholder color */
        ::placeholder {
            color: rgba(255, 255, 255, 0.7);
            opacity: 1;
        }

        /* For better browser compatibility */
        :-ms-input-placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        ::-ms-input-placeholder {
            color: rgba(255, 255, 255, 0.7);
        }

        /* Optional: Add focus styling for better UX */
        input:focus::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }

        /* Responsive adjustments */
        @media (max-width: 576px) {
            .login-container {
                padding: 30px 20px;
                margin: 15px;
            }

            .login-header {
                flex-direction: column;
            }

            .login-header h2 {
                font-size: 24px;
            }

            .login-header p {
                font-size: 14px;
            }

            .logo {
                width: 70px;
                height: 70px;
            }

            .form-group label {
                font-size: 12px;
            }

            .input-group input {
                padding: 12px 15px 12px 45px;
                font-size: 14px;
            }

            .input-group i.fas.fa-lock,
            .input-group i.fas.fa-user {
                font-size: 16px;
            }

            .login-btn {
                padding: 12px;
                font-size: 14px;
            }

            .login-footer {
                font-size: 12px;
            }
        }

        /* Medium devices (tablets) */
        @media (min-width: 577px) and (max-width: 768px) {
            .login-container {
                max-width: 380px;
            }

            .logo {
                width: 80px;
                height: 80px;
            }
        }

        /* Large devices (desktops) */
        @media (min-width: 769px) and (max-width: 992px) {
            .login-container {
                max-width: 400px;
            }
        }

        /* Extra large devices */
        @media (min-width: 993px) {
            .login-container {
                max-width: 420px;
            }
        }

        /* Portrait orientation for mobile */
        @media (max-height: 600px) and (orientation: landscape) {
            .page-wrapper {
                padding: 10px;
                min-height: 120vh;
            }
            
            .login-container {
                padding: 20px;
                margin: 10px 0;
            }
            
            .login-header {
                margin-bottom: 15px;
            }
            
            .form-group {
                margin-bottom: 15px;
            }
            
            .login-footer {
                margin-top: 15px;
            }
            
            .logo {
                width: 60px;
                height: 60px;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <!-- Floating shapes for animated background -->
        <div class="floating-shapes">
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
            <div class="shape"></div>
        </div>

        <div class="login-container animate__animated animate__fadeIn">
            <div class="login-header">
                <div class="logo-container">
                    <img src="../image/logo_no_bg.png" alt="Logo" class="logo">
                </div>
                <div>
                    <h2 class="animate__animated animate__fadeInDown">StudentRequestHub</h2>
                    <p class="animate__animated animate__fadeIn animate__delay-1s">Sign in to access your account</p>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="error-list animate__animated animate__fadeIn">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <?php if (isset($reset_message)): ?>
                <div class="reset-message animate__animated animate__fadeIn">
                    <?php echo $reset_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($reset_error)): ?>
                <div class="error-list animate__animated animate__fadeIn">
                    <ul>
                        <li><?php echo $reset_error; ?></li>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="" class="animate__animated animate__fadeIn animate__delay-1s">
                <div class="form-group">
                    <label for="lrn">Learning Reference Number</label>
                    <div class="input-group">
                        <input type="text" id="lrn" name="lrn" pattern="[0-9]{12}" title="Please enter 12 digits" required placeholder="Enter your 12-digit LRN">
                        <i class="fas fa-user"></i>
                        <div class="input-focus-effect"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <input type="password" id="password" name="password" required placeholder="Enter your password">
                        <i class="fas fa-lock"></i>
                        <i class="fas fa-eye toggle-password"></i>
                        <div class="input-focus-effect"></div>
                    </div>
                </div>

                <button type="submit" class="login-btn ripple animate__animated animate__fadeIn animate__delay-2s">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
            </form>

            <div class="login-footer animate__animated animate__fadeIn animate__delay-2s">
                <p>Don't have an account? <a href="register.php" class="animate__animated animate__pulse animate__delay-3s animate__infinite">Register here</a></p>
                <p class="mt-2">Forgot your password? <a href="#" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">Reset it here</a></p>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" aria-labelledby="forgotPasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="forgotPasswordModalLabel">Reset Your Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="forgot_lrn">Enter your LRN (12 digits)</label>
                            <div class="input-group">
                                <input type="text" id="forgot_lrn" name="forgot_lrn" pattern="[0-9]{12}" title="Please enter 12 digits" required placeholder="Enter your 12-digit LRN">
                                <i class="fas fa-user"></i>
                                <div class="input-focus-effect"></div>
                            </div>
                        </div>
                        
                        <!-- Hidden field for reCAPTCHA v3 token -->
                        <input type="hidden" id="forgotRecaptchaResponse" name="recaptcha_response">
                        
                        <!-- Fallback container for reCAPTCHA v2 (initially hidden) -->
                        <div id="forgotFallbackRecaptchaContainer" style="display:none; margin-top:20px; border:1px solid #ccc; padding:15px; border-radius:8px;">
                            <p style="margin-bottom:10px;">We detected suspicious activity. Please complete the CAPTCHA challenge:</p>
                            <div class="g-recaptcha" data-sitekey="6LfBcDgrAAAAAFsBo5tjm6-keHR2sTEdFSqI3nMq"></div>
                            <button type="button" id="forgotFallbackVerifyBtn" class="btn btn-primary mt-3">Verify & Reset</button>
                        </div>
                        
                        <button type="submit" class="btn btn-primary mt-3 ripple">Continue to Reset</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Add reCAPTCHA v3 script (replace with your v3 site key) -->
    <script src="https://www.google.com/recaptcha/api.js?render=6Lf2XzgrAAAAAPOcNbKKYuDuOmhRhda2JJ4rR3M_"></script>

    <!-- reCAPTCHA v2 script (used in fallback) -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add visual feedback for login attempts
            document.querySelector('form').addEventListener('submit', function(e) {
                const submitButton = document.querySelector('.login-btn');
                submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
                submitButton.disabled = true;
                
                // Add animation class
                submitButton.classList.add('animate__animated', 'animate__pulse');
            });

            // Password visibility toggle with animation
            const passwordField = document.querySelector('#password');
            const togglePassword = document.querySelector('.toggle-password');

            // Initially hide the toggle button
            togglePassword.style.display = passwordField.value ? 'block' : 'none';

passwordField.addEventListener('input', function() {
    togglePassword.style.display = this.value ? 'block' : 'none';
});

togglePassword.addEventListener('click', function() {
    const type = passwordField.getAttribute('type') === 'password' ? 'text' : 'password';
    passwordField.setAttribute('type', type);
    
    // Toggle eye icon with animation
    this.classList.remove('fa-eye', 'fa-eye-slash');
    this.classList.add('animate__animated', 'animate__flipInX');
    
    if (type === 'password') {
        this.classList.add('fa-eye');
    } else {
        this.classList.add('fa-eye-slash');
    }
    
    // Remove animation class after animation completes
    setTimeout(() => {
        this.classList.remove('animate__animated', 'animate__flipInX');
    }, 500);
});

// Add focus animation to input fields
const inputs = document.querySelectorAll('.input-group input');
inputs.forEach(input => {
    input.addEventListener('focus', function() {
        this.parentElement.querySelector('.input-focus-effect').style.width = '100%';
    });
    
    input.addEventListener('blur', function() {
        this.parentElement.querySelector('.input-focus-effect').style.width = '0';
    });
});

// Show modal with animation when there's a reset error
<?php if (isset($reset_error)): ?>
    const forgotModal = new bootstrap.Modal(document.getElementById('forgotPasswordModal'));
    forgotModal.show();
<?php endif; ?>

// Prevent form submission on Enter key for the forgot password modal
document.getElementById('forgotPasswordModal').addEventListener('keydown', function(e) {
    if (e.key === 'Enter' && e.target.id !== 'forgot_lrn') {
        e.preventDefault();
    }
});

// Add ripple effect to all buttons with ripple class
const buttons = document.querySelectorAll('.ripple');
buttons.forEach(button => {
    button.addEventListener('click', function(e) {
        e.preventDefault();
        const rect = this.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        const ripple = document.createElement('span');
        ripple.className = 'ripple-effect';
        ripple.style.left = `${x}px`;
        ripple.style.top = `${y}px`;
        
        this.appendChild(ripple);
        
        setTimeout(() => {
            ripple.remove();
        }, 1000);
        
        // For form buttons, submit after ripple effect
        if (this.type === 'submit') {
            setTimeout(() => {
                this.form.submit();
            }, 500);
        }
    });
});

// Add shake animation to form when there are errors
<?php if (!empty($errors)): ?>
    const loginForm = document.querySelector('.login-container');
    loginForm.classList.add('animate__animated', 'animate__shakeX');
    
    setTimeout(() => {
        loginForm.classList.remove('animate__animated', 'animate__shakeX');
    }, 1000);
<?php endif; ?>

// Auto-focus on the first input field with error
const firstErrorInput = document.querySelector('input:invalid');
if (firstErrorInput) {
    firstErrorInput.focus();
}

// Handle forgot password form submission
document.querySelector('#forgotPasswordModal form').addEventListener('submit', function(e) {
    e.preventDefault();
    const lrn = document.getElementById('forgot_lrn').value;
    
    // Validate LRN format
    if (!/^\d{12}$/.test(lrn)) {
        Swal.fire({
            title: 'Invalid LRN',
            text: 'Please enter a valid 12-digit LRN number',
            icon: 'warning',
            confirmButtonText: 'OK',
            confirmButtonColor: '#4361ee',
            background: 'rgba(33, 37, 41, 0.95)',
            color: '#fff',
            showClass: {
                popup: 'animate__animated animate__fadeInDown'
            },
            hideClass: {
                popup: 'animate__animated animate__fadeOutUp'
            }
        });
        return;
    }
    
    // If validation passes, submit the form
    this.submit();
});

// Close modal when clicking outside
const forgotPasswordModal = document.getElementById('forgotPasswordModal');
forgotPasswordModal.addEventListener('click', function(e) {
    if (e.target === this) {
        const modal = bootstrap.Modal.getInstance(this);
        modal.hide();
    }
});

});

// Add styles for ripple effect
const style = document.createElement('style');
style.textContent = `
.ripple-effect {
    position: absolute;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.7);
    transform: scale(0);
    animation: ripple 0.6s linear;
    pointer-events: none;
}

@keyframes ripple {
    to {
        transform: scale(4);
        opacity: 0;
    }
}
`;
document.head.appendChild(style);
</script>
<script>
document.addEventListener("DOMContentLoaded", function(){
  // Generate token for forgot password form using reCAPTCHA v3
  grecaptcha.ready(function() {
    grecaptcha.execute('6Lf2XzgrAAAAAPOcNbKKYuDuOmhRhda2JJ4rR3M_', { action: 'forgot_password' })
    .then(function(token) {
      document.getElementById('forgotRecaptchaResponse').value = token;
    });
  });

  // If the server-side sets a flag (e.g. $forgot_fallback_required), you can output JavaScript to show the fallback.
  <?php if(isset($forgot_fallback_required) && $forgot_fallback_required): ?>
    document.getElementById('forgotFallbackRecaptchaContainer').style.display = 'block';
  <?php endif; ?>

  // Handle fallback verify button for forgot password form
  document.getElementById("forgotFallbackVerifyBtn")?.addEventListener("click", function() {
    // Get the reCAPTCHA v2 token
    const captchaResponse = grecaptcha.getResponse();
    if (!captchaResponse) {
      Swal.fire({
        title: 'Verification Required',
        text: 'Please complete the CAPTCHA challenge.',
        icon: 'warning',
        confirmButtonText: 'OK',
        customClass: { popup: 'swal2-dark' }
      });
      return;
    }
    // Append the fallback token as a hidden field and submit the form.
    let fallbackField = document.createElement('input');
    fallbackField.type = 'hidden';
    fallbackField.name = 'fallback_recaptcha_response';
    fallbackField.value = captchaResponse;
    this.closest('form').appendChild(fallbackField);
    this.closest('form').submit();
  });
});
</script>
</body>
</html>