<?php
session_start();
require_once '../Connection/database.php';

$errors = [];
$success_message = '';

// Check if LRN is already in session from previous page
if (!isset($_SESSION['lrn']) && isset($_GET['lrn'])) {
    $_SESSION['lrn'] = $_GET['lrn'];
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate reCAPTCHA v3 token via a server-side request
    $recaptcha_response = $_POST['recaptcha_response'] ?? '';
    $secret_key_v3 = '6Lf2XzgrAAAAAPRwXlD1orc0BPXi-IogWw1VW4Gi'; //  v3 secret key
    $verifyResponse = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . $secret_key_v3 . '&response=' . $recaptcha_response);
    $responseData = json_decode($verifyResponse);
    error_log("Reset password reCAPTCHA v3 response: " . print_r($responseData, true));
    
    $fallback_required = false;
    if (!$responseData->success || !isset($responseData->score) || $responseData->score < 0.5) {
        $fallback_required = true;
    }
    
    // If a fallback token from v2 is submitted, verify it
    if (isset($_POST['fallback_recaptcha_response'])) {
        $fallback_response = $_POST['fallback_recaptcha_response'];
        $secret_key_v2 = '6LfBcDgrAAAAACMl5bFRiwUeB1pcVlDvF-ozZMvu'; // reCAPTCHA v2 secret key
        $v2_verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secret_key_v2}&response={$fallback_response}");
        $v2_data = json_decode($v2_verify);
        error_log("Reset password reCAPTCHA v2 response: " . print_r($v2_data, true));
        if (!$v2_data->success) {
            $errors[] = "Fallback reCAPTCHA verification failed. Please try again.";
        } else {
            $fallback_required = false;
        }
    }
    
    // Only continue processing the password reset if no errors and fallback is not required.
    if (empty($errors) && !$fallback_required) {
        // ... (your existing password validation and DB update code)
        if (isset($_POST['new_password']) && isset($_POST['confirm_password'])) {
            $lrn = $_SESSION['lrn'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
        
            // Validate passwords
            if ($new_password !== $confirm_password) {
                $errors[] = "Passwords do not match!";
            }
        
            // Validate password strength
            if (strlen($new_password) < 8 || !preg_match('/[A-Z]/', $new_password) || !preg_match('/[a-z]/', $new_password) || !preg_match('/[0-9]/', $new_password) || !preg_match('/[\W]/', $new_password)) {
                $errors[] = "Password must be at least 8 characters long and include at least one uppercase letter, one lowercase letter, one number, and one special character.";
            }
        
            if (empty($errors)) {
                // Check if LRN exists in database before updating
                $check_stmt = $conn->prepare("SELECT * FROM users WHERE lrn = ?");
                $check_stmt->bind_param("s", $lrn);
                $check_stmt->execute();
                $result = $check_stmt->get_result();
        
                if ($result->num_rows == 1) {
                    $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE lrn = ?");
                    $stmt->bind_param("ss", $hashed_password, $lrn);
        
                    if ($stmt->execute()) {
                        $success_message = "Password reset successfully. You can now <a href='Loginpage.php'>login</a> with your new password.";
                        unset($_SESSION['lrn']); // Clear the session after successful reset
                    } else {
                        $errors[] = "Password reset failed!";
                    }
                } else {
                    $errors[] = "LRN not found in the database.";
                }
            }
        }
    }
}

// Redirect to login if no LRN in session
if (!isset($_SESSION['lrn'])) {
    // If there's a success message, don't redirect (let user see the success message)
    if (empty($success_message)) {
        header("Location: Loginpage.php");
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: url('../image/admin_picture.jpg') no-repeat center center fixed;
            
            background-size: cover;
            position: relative;
            overflow: hidden;
        }
        .reset-container {
            background: linear-gradient(145deg, rgba(15, 23, 42, 0.9), rgba(26, 32, 44, 0.9));
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            width: 400px;
            backdrop-filter: blur(10px);
            transform: translateY(0);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .reset-container:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }
        h2 {
            text-align: center;
            color: white;
            margin-bottom: 30px;
            font-size: 28px;
            font-weight: 600;
            position: relative;

        }
        .form-group {
            margin-bottom: 25px;
            position: relative;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: white;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .input-group {
            position: relative;
        }
        .input-group i.fa-lock {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            transition: all 0.3s ease;
            z-index: 1;
        }
        .input-group i.password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
            cursor: pointer;
            z-index: 2;
        }
        input {
            width: 100%;
            padding: 12px 45px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
        }
        input:focus {
            border-color: #23a6d5;
            box-shadow: 0 0 10px rgba(35, 166, 213, 0.1);
        }
        input:focus + i {
            color: #23a6d5;
        }
        .reset-btn {
            background: linear-gradient(to right, #4c1d95, #5b21b6, #6d28d9);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 10px;
            width: 100%;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        .reset-btn:hover {
            background: linear-gradient(to right, #5b21b6, #6d28d9, #7c3aed);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(124, 58, 237, 0.3);
        }
        .reset-btn:active {
            transform: translateY(0);
        }
        .error {
            background: rgba(255, 68, 68, 0.1);
            color: #ff4444;
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 5px;
            border-left: 4px solid #ff4444;
            font-size: 14px;
        }
        .success {
            background: rgba(68, 255, 68, 0.1);
            color: #44ff44;
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 5px;
            border-left: 4px solid #44ff44;
            font-size: 14px;
        }
        .reset-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px; /* Add spacing between the logo and the text */
        }

        .logo-container {
            display: flex;
            align-items: center;
        }

        .logo {
            width: 100px; /* Adjust the size of the logo */
            height: 100px;
            object-fit: contain;
        }

        .reset-header h2 {
            color: white;
            font-size: 28px;
            font-weight: 600;
            margin: 0; /* Remove margin for better alignment */
        }

        .reset-header p {
            color: white;
            font-size: 14px;
            margin-top: 5px; /* Add spacing below the title */
        }
        
        .error-list {
            background: rgba(255, 68, 68, 0.1);
            color: #ff4444;
            margin-bottom: 20px;
            padding: 10px;
            border-radius: 5px;
            border-left: 4px solid #ff4444;
            font-size: 14px;
        }
        
        .error-list ul {
            margin: 0;
            padding-left: 20px;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <a href="Loginpage.php" class="logo-link">
                <div class="logo-container">
                    <img src="../image/logo_no_bg.png" alt="Logo" class="logo">
                </div>
            </a>

            <div>
                <h2>Reset Password</h2>
                <p>Enter your new password below</p>
            </div>
        </div>
        <?php if (!empty($errors)): ?>
            <div class="error-list">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        <?php if (!empty($success_message)): ?>
            <div class="success">
                <?php echo $success_message; ?>
            </div>
        <?php else: ?>
            <form method="POST" action="">
                <!-- Hidden input for reCAPTCHA v3 token -->
                <input type="hidden" id="recaptchaResponse" name="recaptcha_response">
                
                <!-- Password fields and existing markup -->
                <div id="password-form">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <div class="input-group">
                            <input type="password" id="new_password" name="new_password" required>
                            <i class="fas fa-lock"></i>
                            <i class="fas fa-eye password-toggle" id="toggle-new-password"></i>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-group">
                            <input type="password" id="confirm_password" name="confirm_password" required>
                            <i class="fas fa-lock"></i>
                            <i class="fas fa-eye password-toggle" id="toggle-confirm-password"></i>
                        </div>
                    </div>
                    <button type="submit" class="reset-btn">Reset Password</button>
                </div>
                
                <!-- Fallback container for reCAPTCHA v2 -->
                <?php if (isset($fallback_required) && $fallback_required): ?>
                  <div id="fallbackRecaptchaContainer" style="display:block; margin-top:20px; border:1px solid #ccc; padding:15px; border-radius:8px;">
                    <p style="margin-bottom:10px;">We detected suspicious activity. Please complete the CAPTCHA below:</p>
                    <div class="g-recaptcha" data-sitekey="6LfBcDgrAAAAAFsBo5tjm6-keHR2sTEdFSqI3nMq"></div>
                    <button type="button" id="fallbackVerifyBtn" class="reset-btn" style="margin-top:10px;">Verify & Reset</button>
                  </div>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Setup for new password field
        const newPasswordField = document.getElementById('new_password');
        const newPasswordToggle = document.getElementById('toggle-new-password');
        
        if (newPasswordField && newPasswordToggle) {
            // Initially hide the toggle
            newPasswordToggle.style.display = 'none';
            
            // Show/hide toggle based on input
            newPasswordField.addEventListener('input', function() {
                newPasswordToggle.style.display = this.value.length > 0 ? 'block' : 'none';
            });

            // Toggle password visibility
            newPasswordToggle.addEventListener('click', function() {
                const type = newPasswordField.getAttribute('type');
                newPasswordField.setAttribute('type', type === 'password' ? 'text' : 'password');
                this.className = `fas ${type === 'password' ? 'fa-eye-slash' : 'fa-eye'} password-toggle`;
            });
        }
        
        // Setup for confirm password field
        const confirmPasswordField = document.getElementById('confirm_password');
        const confirmPasswordToggle = document.getElementById('toggle-confirm-password');
        
        if (confirmPasswordField && confirmPasswordToggle) {
            // Initially hide the toggle
            confirmPasswordToggle.style.display = 'none';
            
            // Show/hide toggle based on input
            confirmPasswordField.addEventListener('input', function() {
                confirmPasswordToggle.style.display = this.value.length > 0 ? 'block' : 'none';
            });

            // Toggle password visibility
            confirmPasswordToggle.addEventListener('click', function() {
                const type = confirmPasswordField.getAttribute('type');
                confirmPasswordField.setAttribute('type', type === 'password' ? 'text' : 'password');
                this.className = `fas ${type === 'password' ? 'fa-eye-slash' : 'fa-eye'} password-toggle`;
            });
        }
    });
    </script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Generate token for reset password form using reCAPTCHA v3
        grecaptcha.ready(function() {
            grecaptcha.execute('6Lf2XzgrAAAAAPOcNbKKYuDuOmhRhda2JJ4rR3M_', {action: 'reset_password'}).then(function(token) {
                document.getElementById('recaptchaResponse').value = token;
            });
        });

        // Handle fallback verify button (visible if fallback is required)
        document.getElementById("fallbackVerifyBtn")?.addEventListener("click", function() {
            // Get the token from the v2 widget.
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
            // Append the fallback token as a hidden field so it gets sent with the form.
            let fallbackField = document.createElement('input');
            fallbackField.type = 'hidden';
            fallbackField.name = 'fallback_recaptcha_response';
            fallbackField.value = captchaResponse;
            document.querySelector("form").appendChild(fallbackField);
            // Submit the form.
            document.querySelector("form").submit();
        });
    });
    </script>
    <!-- Add reCAPTCHA v3 script (replace YOUR_RECAPTCHA_V3_SITE_KEY accordingly) -->
    <script src="https://www.google.com/recaptcha/api.js?render=6Lf2XzgrAAAAAPOcNbKKYuDuOmhRhda2JJ4rR3M_"></script>
    <!-- Include reCAPTCHA v2 script (for fallback) -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</body>
</html>