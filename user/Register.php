<?php
session_start();
require_once '../Connection/database.php';

$errors = [];
$active_request = false; // Default to no active request
$form_data = [
    'lrn' => '',
    'firstname' => '',
    'middlename' => '', // Added middle name
    'lastname' => '',
    'extensionname' => '', // Added extension name
    'contact' => '',
    'dob' => '',
    'gender' => '',
    'address' => '',
    'grade_level' => '',
    'guardian_name' => ''
];

// Helper function to generate a unique ULI
function generateUniqueULI($conn, $firstname, $middlename, $lastname, $dob, $grade_level) {
    // Build initials: first letter of first name, middle name only if not "n/a", first letter of last name
    $initials = strtoupper(substr($firstname, 0, 1));
    if (!empty($middlename) && strtolower($middlename) !== 'n/a') {
        $initials .= strtoupper(substr($middlename, 0, 1));
    }
    $initials .= strtoupper(substr($lastname, 0, 1));
    
    // Format the birthday to YYYYMMDD (remove dashes)
    $dob_formatted = str_replace('-', '', $dob);
    
    // Extract the numeric part from the grade level (e.g., "Grade 7" becomes "7")
    preg_match('/\d+/', $grade_level, $matches);
    $gradeNumber = $matches[0] ?? '';
    
    do {
        // Append a random 4-digit suffix for extra uniqueness
        $randomSuffix = rand(1000, 9999);
        $candidateULI = $initials . '-' . $dob_formatted . '-' . $gradeNumber . '-' . $randomSuffix;
        
        // Check if the candidate exists already in the users table
        $stmt = $conn->prepare("SELECT id FROM users WHERE uli = ?");
        $stmt->bind_param('s', $candidateULI);
        $stmt->execute();
        $result = $stmt->get_result();
        $exists = $result->num_rows > 0;
        $stmt->close();
    } while ($exists);
    
    return $candidateULI;
}

// Check if the user has an active request
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $query = "SELECT * FROM requests WHERE user_id = ? AND status = 'Pending' LIMIT 1";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        die('Error preparing SELECT statement: ' . $conn->error);
    }

    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $active_request = true; // Set to true if an active request exists
    }
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Populate form data for repopulation on error
    $form_data = [
        'lrn' => $_POST['lrn'] ?? '',
        'firstname' => $_POST['firstname'] ?? '',
        'middlename' => $_POST['middlename'] ?? '', // Added middle name
        'lastname' => $_POST['lastname'] ?? '',
        'extensionname' => $_POST['extensionname'] ?? '', // Added extension name
        'contact' => $_POST['contact'] ?? '',
        'dob' => $_POST['dob'] ?? '',
        'gender' => $_POST['gender'] ?? '',
        'address' => $_POST['address'] ?? '',
        'grade_level' => $_POST['grade_level'] ?? '',
        'guardian_name' => $_POST['guardian_name'] ?? ''
    ];

    $lrn = $_POST['lrn'];
    $firstname = $_POST['firstname'];
    $middlename = !empty($_POST['middlename']) ? $_POST['middlename'] : null; // Set to NULL if empty
    $lastname = $_POST['lastname'];
    $extensionname = !empty($_POST['extensionname']) ? $_POST['extensionname'] : null; // Set to NULL if empty

    // Retrieve the raw contact from POST
    $raw_contact = $_POST['contact'];

    // Validate that the raw contact is exactly 11 digits and starts with "09"
    if (strlen($raw_contact) !== 11 || !ctype_digit($raw_contact) || substr($raw_contact, 0, 2) !== '09') {
        $errors[] = "Contact number must be 11 digits starting with 09.";
    } else {
        // Now convert the raw contact to international format (e.g., 09123456789 -> 63123456789)
        $contact = '63' . substr($raw_contact, 1);
    }

    $dob = $_POST['dob'];
    $gender = $_POST['gender'];
    $address = $_POST['address'];
    $grade_level = $_POST['grade_level'];
    $guardian_name = $_POST['guardian_name'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate inputs
    if (strlen($lrn) !== 12 || !ctype_digit($lrn)) {
        $errors[] = "LRN must be 12 digits.";
    }

    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match!";
    }

    // Check if LRN already exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE lrn = ?");
    if (!$stmt) {
        die('Error preparing statement: ' . $conn->error);
    }
    $stmt->bind_param("s", $lrn);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $errors[] = "LRN is already registered.";
    }
    $stmt->close();

    // Handle file upload
    $picture_path = null;
    if (isset($_FILES['picture']) && $_FILES['picture']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['picture']['name'];
        $filetype = pathinfo($filename, PATHINFO_EXTENSION);
        
        if (!in_array(strtolower($filetype), $allowed)) {
            $errors[] = "Only JPG, JPEG, PNG, and GIF files are allowed.";
        } else {
            $upload_dir = '../uploads/';
            // Create directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $new_filename = uniqid() . '.' . $filetype;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['picture']['tmp_name'], $upload_path)) {
                $picture_path = $new_filename;
            } else {
                $errors[] = "Failed to upload image.";
            }
        }
    } else {
        $errors[] = "Please upload a picture.";
    }

    // Validate reCAPTCHA v3 token via a server-side request
    $recaptcha_response = $_POST['recaptcha_response'] ?? '';
    $secret_key_v3 = '6Lf2XzgrAAAAAPRwXlD1orc0BPXi-IogWw1VW4Gi';

    $verifyResponse = file_get_contents('https://www.google.com/recaptcha/api/siteverify?secret=' . $secret_key_v3 . '&response=' . $recaptcha_response);
    $responseData = json_decode($verifyResponse);
    error_log("reCAPTCHA v3 response: " . print_r($responseData, true));

    $fallback_required = false;
    if (!$responseData->success || !isset($responseData->score) || $responseData->score < 0.5) {
        $fallback_required = true;
    }

    // If a fallback token from v2 is submitted, verify it
    if (isset($_POST['fallback_recaptcha_response'])) {
        $fallback_response = $_POST['fallback_recaptcha_response'];
        $secret_key_v2 = '6LfBcDgrAAAAACMl5bFRiwUeB1pcVlDvF-ozZMvu'; // Replace with your v2 secret key
        $v2_verify = file_get_contents("https://www.google.com/recaptcha/api/siteverify?secret={$secret_key_v2}&response={$fallback_response}");
        $v2_data = json_decode($v2_verify);
        error_log("reCAPTCHA v2 response: " . print_r($v2_data, true));
        if (!$v2_data->success) {
            $errors[] = "Fallback CAPTCHA verification failed. Please try again.";
        } else {
            // If v2 succeeds, remove the fallback flag.
            $fallback_required = false;
        }
    }

    // Only proceed with registration if there are no other errors and no fallback is required
    if (empty($errors) && !$fallback_required) {
        $hashed_password = password_hash($password, PASSWORD_BCRYPT);

        // Generate a Unique Learner ID (ULI)
        $uli = generateUniqueULI($conn, $firstname, $middlename, $lastname, $dob, $grade_level);

        // Insert the user into the database - Modified query to include middlename and extension
        $stmt = $conn->prepare("INSERT INTO users (lrn, firstname, middlename, lastname, extensionname, contact, dob, gender, address, grade_level, guardian_name, password, uli, picture) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        if (!$stmt) {
            die('Error preparing statement: ' . $conn->error);
        }

        $stmt->bind_param("ssssssssssssss", $lrn, $firstname, $middlename, $lastname, $extensionname, $contact, $dob, $gender, $address, $grade_level, $guardian_name, $hashed_password, $uli, $picture_path);

        // After successful execution of the INSERT query for user registration
        if ($stmt->execute()) {
            // Generate a unique ULI based on user data
            $uli = generateUniqueULI($conn, $firstname, $middlename, $lastname, $dob, $grade_level);
            
            // Optionally update the user's record with the new ULI if needed:
            // $update = $conn->prepare("UPDATE users SET uli=? WHERE lrn=?");
            // $update->bind_param("ss", $uli, $lrn);
            // $update->execute();
            
            // Set the success message that includes the ULI
           $success_message = "<br>Your ULI is: <span style='color: #00d26a; font-weight: bold;'>$uli</span>";
            
            // Clear your form data if necessary...
        } else {
            $errors[] = 'Error executing query: ' . $stmt->error;
        }
    }
}

// Rest of the code for handling existing requests remains the same
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$active_request) {
    if (isset($_POST['confirm'])) {
        // Retrieve data from $_POST
        $document_type = $_POST['document_type'];
        $purpose       = $_POST['purpose'];
        $school_year   = $_POST['school_year'];
        $priority      = $_POST['priority'];
        $firstname     = $_POST['firstname'];
        $middlename    = !empty($_POST['middlename']) ? $_POST['middlename'] : null;
        $lastname      = $_POST['lastname'];
        $extensionname = !empty($_POST['extensionname']) ? $_POST['extensionname'] : null;
        $contact       = $_POST['contact'];
        $lrn           = $_POST['lrn'];

        // Update user information - Modified query to include middlename and extension
        $query = "UPDATE users SET firstname = ?, middlename = ?, lastname = ?, extensionname = ?, contact = ?, lrn = ? WHERE id = ?";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            die('Error preparing UPDATE statement: ' . $conn->error);
        }

        $stmt->bind_param('ssssssi', $firstname, $middlename, $lastname, $extensionname, $contact, $lrn, $user_id);

        if (!$stmt->execute()) {
            die('Error executing UPDATE query: ' . $stmt->error);
        }

        // Insert the new request
        $query = "INSERT INTO requests (user_id, document_type, purpose, school_year, priority, status, uli)
                  VALUES (?, ?, ?, ?, ?, 'Pending', ?)";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            die('Error preparing INSERT statement: ' . $conn->error);
        }

        $uli = uniqid(''); // Generate a unique ULI for the request
        $stmt->bind_param('isssss', $user_id, $document_type, $purpose, $school_year, $priority, $uli);

        if (!$stmt->execute()) {
            die('Error executing INSERT query: ' . $stmt->error);
        }

        header('Location: dashboard_user.php');
        exit();
    }
}

// Fetch user's latest request
if (isset($_SESSION['user_id'])) {
    $query = "SELECT r.*, u.firstname, u.middlename, u.lastname, u.extensionname, u.contact, u.lrn, u.uli, u.guardian_name
            FROM requests r
            JOIN users u ON r.user_id = u.id
            WHERE r.user_id = ?
            ORDER BY r.created_at DESC
            LIMIT 1";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        die('Error preparing SELECT statement: ' . $conn->error);
    }

    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result === false) {
        die('Error executing SELECT query: ' . $conn->error);
    }

    $request = $result->fetch_assoc();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.min.css">
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.0.19/dist/sweetalert2.all.min.js"></script>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        /* Your existing CSS styles remain exactly the same */
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
            font-family: 'Poppins', sans-serif; 
        }
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            background: url('../image/admin_picture.jpg') no-repeat center center fixed;
            background-size: cover;
            position: relative;
            overflow-x: hidden;
            color: #fff;
            padding: 20px 10px; /* Add padding for better spacing on small screens */
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: -1;
        }
        
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        
        .register-container {
            background: rgba(15, 23, 42, 0.75);
            padding: 40px;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 900px;
            backdrop-filter: blur(12px);
            transition: all 0.4s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
            margin: 20px;
        }
        
        .register-container:hover { 
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.6);
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 30px;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .logo-container {
            display: flex;
            align-items: center;
        }

        .logo {
            width: 80px;
            height: 80px;
            object-fit: contain;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.3));
            transition: transform 0.3s ease;
        }
        
        .logo:hover {
            transform: scale(1.05);
        }

        .register-header h2 {
            color: #fff;
            font-size: 28px;
            font-weight: 600;
            margin: 0;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .register-header p {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            margin-top: 5px;
        }
        
        h2 { 
            text-align: center; 
            margin-bottom: 20px; 
            color: #fff;
        }
        
        .form-group { 
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: -5px;
        }
        
        .input-group { 
            position: relative;
            flex: 1 0 calc(50% - 10px);
            min-width: 250px;
            margin-bottom: 20px;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.9);
            font-size: 14px;
        }
        
        .input-group i {
            position: absolute;
            left: 15px;
            top: 43px;
            color: rgba(255, 255, 255, 0.7);
            pointer-events: none;
            transition: all 0.3s ease;
        }
        
        /* Input styling */
        input, select {
            width: 100%;
            padding: 12px 12px 12px 45px; /* Increased left padding for icon */
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            color: #fff;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        
        input:focus, select:focus {
            border-color: rgba(79, 70, 229, 0.7);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.25);
            background: rgba(255, 255, 255, 0.15);
            outline: none;
        }
        
        input:focus + i, select:focus + i {
            color: #4f46e5;
        }
        
        input[type="file"] {
            padding: 10px 12px 10px 45px;
            cursor: pointer;
        }
        
        /* Error states */
        input.error, select.error {
            border-color: #ef4444 !important;
            background-color: rgba(239, 68, 68, 0.1);
        }
        
        .error-field {
            font-size: 12px;
            color: #ef4444;
            margin-top: 5px;
            display: none;
        }
        
        input.error + .error-field, select.error + .error-field {
            display: block;
        }
        
        /* Button styling */
        .register-btn {
            background: linear-gradient(135deg, #4f46e5, #2563eb);
            color: white;
            padding: 14px;
            border: none;
            border-radius: 8px;
            width: 100%;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(79, 70, 229, 0.4);
            background: linear-gradient(135deg, #4338ca, #1d4ed8);
        }
        
        .register-btn:active {
            transform: translateY(1px);
        }
        
        .register-btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }
        
        .register-btn:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }
        
        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(100, 100);
                opacity: 0;
            }
        }
        
        /* Error and success messages */
        .error-list {
            background-color: rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #ef4444;
        }
        
        .error-list ul {
            list-style-type: none;
            padding-left: 5px;
        }
        
        .error-list li {
            margin-bottom: 4px;
            font-size: 14px;
        }
        
        .error-list li:last-child {
            margin-bottom: 0;
        }
        
        .success-message {
            color: #10b981;
            text-align: center;
            margin-bottom: 15px;
            background-color: rgba(16, 185, 129, 0.2);
            padding: 12px;
            border-radius: 8px;
            border-left: 4px solid #10b981;
        }
        
        /* Login link styling */
        .login-link {
            text-align: center;
            margin-top: 25px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
        }
        
        .login-link a {
            color: #4cc9f0;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .login-link a::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -2px;
            left: 0;
            background-color: #4cc9f0;
            transition: width 0.3s ease;
        }
        
        .login-link a:hover {
            color: #6366f1;
        }
        
        .login-link a:hover::after {
            width: 100%;
        }
        
        /* Custom select styling */
        select {
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
        }
        
        .input-group.select::after {
            content: '\f078';
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 15px;
            top: 43px;
            color: rgba(255, 255, 255, 0.7);
            pointer-events: none;
        }
        
        /* File upload styling */
        input[type="file"] {
            padding: 10px 12px 10px 45px;
        }
        
        input[type="file"]::file-selector-button {
            padding: 4px 10px;
            border-radius: 4px;
            background-color: rgba(79, 70, 229, 0.8);
            border: none;
            color: white;
            margin-right: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        input[type="file"]::file-selector-button:hover {
            background-color: rgba(79, 70, 229, 1);
        }
        
        /* SweetAlert customization */
        .swal2-popup {
            font-family: 'Poppins', sans-serif;
            border-radius: 15px;
            background: rgba(30, 41, 59, 0.95);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
        }
        
        .swal2-title {
            color: #fff;
        }
        
        .swal2-html-container {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .swal2-confirm {
            background: linear-gradient(135deg, #4f46e5, #2563eb) !important;
            border: none !important;
            box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3) !important;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .register-container {
                padding: 25px;
                margin: 15px;
            }
            
            .form-group {
                flex-direction: column;
                gap: 0;
            }
            
            .input-group {
                flex: 0 0 100%;
                margin-bottom: 15px;
            }
            
            .register-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .logo {
                width: 70px;
                height: 70px;
            }
            
            .register-header h2 {
                font-size: 24px;
            }
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .register-container {
            animation: fadeIn 0.5s ease-out forwards;
        }

        /* Styling for select elements */
        select {
            width: 100%;
            padding: 12px 12px 12px 45px; /* Adjust padding for better spacing */
            border-radius: 8px;
            color: #fff; /* White text for better contrast */
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            appearance: none; /* Remove default browser styles */
            -webkit-appearance: none; /* For Safari */
            cursor: pointer;
        }

        /* Placeholder text for select elements */
        select option {
            color: #000; /* Black text for options */
        }

        /* Focus state for select elements */
        select:focus {
            border-color: rgba(79, 70, 229, 0.7); /* Highlight border on focus */
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.25); /* Add focus shadow */
            background: rgba(255, 255, 255, 0.3); /* Slightly lighter background on focus */
            outline: none;
        }

        /* Add a dropdown arrow for select elements */
        .input-group.select::after {
            content: '\f078'; /* Font Awesome down arrow */
            font-family: 'Font Awesome 5 Free';
            font-weight: 900;
            position: absolute;
            right: 15px;
            top: 43px;
            color: rgba(255, 255, 255, 0.7); /* White arrow for better visibility */
            pointer-events: none;
        }

        /* ADDED ENHANCED RESPONSIVE STYLES */
        
        /* Mobile first approach */
        @media (max-width: 480px) {
            body {
                padding: 10px 5px;
            }
            
            .register-container {
                padding: 20px 15px;
                margin: 10px 5px;
                width: 95%;
            }
            
            .register-header h2 {
                font-size: 20px;
            }
            
            .register-header p {
                font-size: 12px;
            }
            
            .logo {
                width: 60px;
                height: 60px;
            }
            
            .input-group {
                margin-bottom: 12px;
            }
            
            .input-group label {
                font-size: 13px;
                margin-bottom: 5px;
            }
            
            input, select {
                padding: 10px 10px 10px 40px;
                font-size: 13px;
            }
            
            .input-group i {
                left: 12px;
                top: 38px;
                font-size: 14px;
            }
            
            .register-btn {
                padding: 12px;
                font-size: 15px;
            }
            
            .login-link {
                font-size: 13px;
                margin-top: 20px;
            }
            
            input[type="file"]::file-selector-button {
                padding: 3px 8px;
                font-size: 12px;
            }
        }
        
        /* Small tablets */
        @media (min-width: 481px) and (max-width: 767px) {
            .register-container {
                padding: 25px 20px;
                margin: 15px 10px;
            }
            
            .input-group {
                min-width: 100%;
            }
        }
        
        /* Landscape orientation for phones */
        @media (max-height: 500px) and (orientation: landscape) {
            body {
                align-items: flex-start;
                padding-top: 10px;
                padding-bottom: 10px;
            }
            
            .register-container {
                margin-top: 5px;
                margin-bottom: 5px;
                max-height: 90vh;
                overflow-y: auto;
                padding: 15px;
            }
            
            .register-header {
                margin-bottom: 15px;
            }
            
            .logo {
                width: 50px;
                height: 50px;
            }
            
            .form-group {
                gap: 10px;
            }
            
            .input-group {
                margin-bottom: 10px;
            }
        }
        
        /* Medium devices (tablets) */
        @media (min-width: 768px) and (max-width: 991px) {
            .register-container {
                max-width: 700px;
            }
            
            .input-group {
                flex: 0 0 calc(50% - 10px);
            }
        }
        
        /* Large devices (desktops) */
        @media (min-width: 992px) {
            .register-container {
                max-width: 900px;
                padding: 40px;
            }
            
            .form-group {
                gap: 20px;
            }
        }
        
        /* For high-resolution displays */
        @media (min-width: 1200px) {
            .register-container {
                max-width: 1000px;
            }
            
            .register-header h2 {
                font-size: 32px;
            }
            
            .logo {
                width: 90px;
                height: 90px;
            }
        }
        
        /* Touch-friendly inputs for mobile */
        @media (max-width: 767px) {
            input, select, button {
                font-size: 16px; /* Prevents iOS zoom on focus */
            }
            
            input[type="file"] {
                font-size: 14px;
            }
            
            /* Better tap targets */
            .register-btn {
                padding: 16px;
                margin-top: 15px;
            }
            
            .login-link a {
                padding: 5px 0;
                display: inline-block;
            }
        }
        
        /* Fix for iPhone SE and other very small screens */
        @media (max-width: 375px) {
            .register-container {
                padding: 15px 10px;
            }
            .register-container {
                padding: 15px 10px;
            }
            
            .register-header h2 {
                font-size: 18px;
            }
            
            .logo {
                width: 50px;
                height: 50px;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <div class="logo-container">
                <img src="school logo/school logo.png" alt="School Logo" class="logo">
            </div>
            <div>
                <h2>Student Registration</h2>
                <p>Complete the form below to register</p>
            </div>
        </div>
        
        <?php if(!empty($errors)): ?>
            <div class="error-list">
                <ul>
                    <?php foreach($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if($active_request): ?>
            <div class="error-list">
                <ul>
                    <li>You already have an active request. Please wait for it to be processed before making a new one.</li>
                </ul>
            </div>
        <?php else: ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" enctype="multipart/form-data" id="registrationForm">
                <div class="form-group">
                    <div class="input-group">
                        <label for="lrn">Learner Reference Number (LRN)</label>
                        <input type="text" id="lrn" name="lrn" placeholder="12-digit LRN" value="<?php echo $form_data['lrn']; ?>" required>
                        <i class="fas fa-id-card"></i>
                        <span class="error-field">Please enter a valid 12-digit LRN</span>
                    </div>
                    
                    <div class="input-group">
                        <label for="firstname">Student First Name</label>
                        <input type="text" id="firstname" name="firstname" placeholder="Enter your first name" value="<?php echo $form_data['firstname']; ?>" required>
                        <i class="fas fa-user"></i>
                        <span class="error-field">Please enter your first name</span>
                    </div>
                    
                    <div class="input-group">
                        <label for="middlename">Student Middle Name </label>
                        <input type="text" id="middlename" name="middlename" placeholder="Enter your middle name 'Put N/a if Doesn't Have'" value="<?php echo $form_data['middlename']; ?>"required>
                        <i class="fas fa-user"></i>
                    </div>
                    
                    <div class="input-group">
                        <label for="lastname">Student Last Name</label>
                        <input type="text" id="lastname" name="lastname" placeholder="Enter your last name" value="<?php echo $form_data['lastname']; ?>" required>
                        <i class="fas fa-user"></i>
                        <span class="error-field">Please enter your last name</span>
                    </div>
                    
                    <div class="input-group">
                        <label for="extensionname">Student Extension Name </label>
                        <input type="text" id="extensionname" name="extensionname" placeholder="Jr., Sr., III, etc. 'Put N/a if Doesn't Have'" value="<?php echo $form_data['extensionname']; ?>"required>
                        <i class="fas fa-user-plus"></i>
                    </div>
                    
                    <div class="input-group">
                        <label for="contact">Contact Number</label>
                        <input type="text" id="contact" name="contact" placeholder="09XXXXXXXXX" value="<?php echo $form_data['contact']; ?>" required>
                        <i class="fas fa-phone"></i>
                        <span class="error-field">Please enter a valid contact number starting with 09</span>
                    </div>
                    
                    <div class="input-group">
                        <label for="dob">Date of Birth</label>
                        <input type="date" id="dob" name="dob" value="<?php echo $form_data['dob']; ?>" required>
                        <i class="fas fa-calendar-alt"></i>
                        <span class="error-field">Please select your date of birth</span>
                    </div>
                    
                    <div class="input-group select">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender" required>
                            <option value="" disabled <?php echo empty($form_data['gender']) ? 'selected' : ''; ?>>Select your gender</option>
                            <option value="Male" <?php echo $form_data['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo $form_data['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                        </select>
                        <i class="fas fa-venus-mars"></i>
                        <span class="error-field">Please select your gender</span>
                    </div>
                    
                    <div class="input-group">
                        <label for="address">Complete Address</label>
                        <input type="text" id="address" name="address" placeholder="Enter your complete address" value="<?php echo $form_data['address']; ?>" required>
                        <i class="fas fa-home"></i>
                        <span class="error-field">Please enter your address</span>
                    </div>
                    
                    <div class="input-group select">
                        <label for="grade_level">Grade Level</label>
                        <select id="grade_level" name="grade_level" required>
                            <option value="" disabled <?php echo empty($form_data['grade_level']) ? 'selected' : ''; ?>>Select your grade level</option>
                            <option value="KinderGarten" <?php echo $form_data['grade_level'] === 'KinderGarten' ? 'selected' : ''; ?>>Kindergarten</option>
                            <option value="Grade 1" <?php echo $form_data['grade_level'] === 'Grade 1' ? 'selected' : ''; ?>>Grade 1</option>
                            <option value="Grade 2" <?php echo $form_data['grade_level'] === 'Grade 2' ? 'selected' : ''; ?>>Grade 2</option>
                            <option value="Grade 3" <?php echo $form_data['grade_level'] === 'Grade 3' ? 'selected' : ''; ?>>Grade 3</option>
                            <option value="Grade 4" <?php echo $form_data['grade_level'] === 'Grade 4' ? 'selected' : ''; ?>>Grade 4</option>
                            <option value="Grade 5" <?php echo $form_data['grade_level'] === 'Grade 5' ? 'selected' : ''; ?>>Grade 5</option>
                            <option value="Grade 6" <?php echo $form_data['grade_level'] === 'Grade 6' ? 'selected' : ''; ?>>Grade 6</option>
                        </select>
                        <i class="fas fa-graduation-cap"></i>
                        <span class="error-field">Please select your grade level</span>
                    </div>
                    
                    <div class="input-group">
                        <label for="guardian_name">Guardian's Full Name</label>
                        <input type="text" id="guardian_name" name="guardian_name" placeholder="Enter guardian's full name" value="<?php echo $form_data['guardian_name']; ?>" required>
                        <i class="fas fa-user-shield"></i>
                        <span class="error-field">Please enter your guardian's full name</span>
                    </div>
                    
                    <div class="input-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Create a password" required>
                        <i class="fas fa-lock"></i>
                        <span class="error-field">Please enter a password</span>
                    </div>
                    
                    <div class="input-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required>
                        <i class="fas fa-lock"></i>
                        <span class="error-field">Passwords do not match</span>
                    </div>
                    
                    <div class="input-group">
                        <label for="picture">Upload Your Picture (2x2)</label>
                        <input type="file" id="picture" name="picture" accept="image/*" required>
                        <i class="fas fa-image"></i>
                        <span class="error-field">Please upload your picture (jpg, jpeg, png, gif only)</span>
                    </div>
                </div>
                
                <!-- reCAPTCHA v2 widget -->
                <div id="recaptcha-container" class="g-recaptcha" 
                     data-sitekey="6LfBcDgrAAAAAFsBo5tjm6-keHR2sTEdFSqI3nMq" 
                     data-callback="onRecaptchaSuccess"></div>
                
                <!-- The Register button is disabled until captcha is solved -->
                <button type="submit" class="register-btn" id="register-btn" disabled>Register</button>
                
                <div class="login-link">
                    Already have an account? <a href="Loginpage.php">Login</a>
                </div>
                <?php if (isset($fallback_required) && $fallback_required): ?>
  <div id="fallbackRecaptchaContainer" style="display:block; margin-top:20px; border:1px solid #ccc; padding:15px; border-radius:8px;">
    <p style="margin-bottom:10px;">We detected suspicious activity. Please complete the CAPTCHA below:</p>
    <div class="g-recaptcha" data-sitekey="6LfBcDgrAAAAAFsBo5tjm6-keHR2sTEdFSqI3nMq"></div>
    <button type="button" id="fallbackVerifyBtn" class="register-btn" style="margin-top:10px;">Verify & Register</button>
  </div>
<?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
    
    <script>
        // Client-side form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registrationForm');
            
            if (form) {
                form.addEventListener('submit', function(e) {
                    let isValid = true;
                    
                    // LRN validation (12 digits)
                    const lrn = document.getElementById('lrn');
                    if (!/^\d{12}$/.test(lrn.value)) {
                        lrn.classList.add('error');
                        isValid = false;
                    } else {
                        lrn.classList.remove('error');
                    }
                    
                    // Contact number validation (starts with 09 and has 11 digits)
                    const contact = document.getElementById('contact');
                    if (!/^09\d{9}$/.test(contact.value)) {
                        contact.classList.add('error');
                        isValid = false;
                    } else {
                        contact.classList.remove('error');
                    }
                    
                    // Password match validation
                    const password = document.getElementById('password');
                    const confirmPassword = document.getElementById('confirm_password');
                    if (password.value !== confirmPassword.value) {
                        confirmPassword.classList.add('error');
                        isValid = false;
                    } else {
                        confirmPassword.classList.remove('error');
                    }
                    
                    // File type validation
                    const picture = document.getElementById('picture');
                    if (picture.value) {
                        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                        const fileType = picture.files[0].type;
                        
                        if (!allowedTypes.includes(fileType)) {
                            picture.classList.add('error');
                            isValid = false;
                        } else {
                            picture.classList.remove('error');
                        }
                    }
                    
                    // Text field validations
                    ['firstname', 'lastname', 'address', 'guardian_name'].forEach(function(fieldId) {
                        const field = document.getElementById(fieldId);
                        if (!field.value.trim()) {
                            field.classList.add('error');
                            isValid = false;
                        } else {
                            field.classList.remove('error');
                        }
                    });
                    
                    // Select field validations
                    ['gender', 'grade_level'].forEach(function(fieldId) {
                        const field = document.getElementById(fieldId);
                        if (!field.value) {
                            field.classList.add('error');
                            isValid = false;
                        } else {
                            field.classList.remove('error');
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        
                        // Scroll to the first error
                        const firstError = document.querySelector('.error');
                        if (firstError) {
                            firstError.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                        }
                        
                        // Show error message with SweetAlert
                        Swal.fire({
                            title: 'Validation Error',
                            text: 'Please check the form for errors and try again.',
                            icon: 'error',
                            confirmButtonText: 'OK',
                            customClass: {
                                popup: 'swal2-dark'
                            }
                        });
                    }
                });
                
                // Real-time validation for input fields
                const inputFields = form.querySelectorAll('input, select');
                inputFields.forEach(function(field) {
                    field.addEventListener('blur', function() {
                        // Skip optional fields if they're empty
                        if ((field.id === 'middlename' || field.id === 'extensionname') && !field.value) {
                            return;
                        }
                        
                        let isFieldValid = true;
                        
                        // LRN validation
                        if (field.id === 'lrn' && !/^\d{12}$/.test(field.value)) {
                            isFieldValid = false;
                        }
                        
                        // Contact validation
                        if (field.id === 'contact' && !/^09\d{9}$/.test(field.value)) {
                            isFieldValid = false;
                        }
                        
                        // Password match validation
                        if (field.id === 'confirm_password') {
                            const password = document.getElementById('password');
                            if (field.value !== password.value) {
                                isFieldValid = false;
                            }
                        }
                        
                        // Required field validation
                        if (['firstname', 'lastname', 'address', 'guardian_name', 'password', 'gender', 'grade_level'].includes(field.id)) {
                            if (!field.value.trim()) {
                                isFieldValid = false;
                            }
                        }
                        
                        if (!isFieldValid) {
                            field.classList.add('error');
                        } else {
                            field.classList.remove('error');
                        }
                    });
                });
            }
            
            // Show success message with SweetAlert if present
          
        });
    </script>
    <script>
document.addEventListener("DOMContentLoaded", function() {
    <?php if (isset($success_message)): ?>
    Swal.fire({
        title: 'REGISTRATION SUCCESS!',
        html: <?php echo json_encode("<span style='color:white; font-weight:bold;'>" . $success_message . "</span><br><br><em>PLEASE SCREENSHOT YOUR ULI IMMEDIATELY. IT IS VERY IMPORTANT!</em>"); ?>,
        icon: 'success',
        confirmButtonText: 'Login Now',
        customClass: {
            popup: 'swal2-dark'
        },
        allowOutsideClick: false
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'Loginpage.php';
        }
    });
    <?php endif; ?>
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const form = document.getElementById('registrationForm');
  if (form) {
    form.addEventListener('submit', function(e) {
      e.preventDefault();
      
      let errorMessages = [];
      
      // Validate LRN (must be 12 digits)
      const lrnField = document.getElementById('lrn');
      if (!/^\d{12}$/.test(lrnField.value)) {
        errorMessages.push("LRN must be exactly 12 digits.");
      }
      
      // Validate contact number (starts with "09" and exactly 11 digits)
      const contactField = document.getElementById('contact');
      if (!/^09\d{9}$/.test(contactField.value)) {
        errorMessages.push("Contact number must start with 09 and contain 11 digits.");
      }
      
      // Validate password match
      const passwordField = document.getElementById('password');
      const confirmPasswordField = document.getElementById('confirm_password');
      if (passwordField.value !== confirmPasswordField.value) {
        errorMessages.push("Passwords do not match.");
      }
      
      // Validate required fields for firstname, lastname, address, guardian, gender, grade_level
      const requiredIds = ['firstname', 'lastname', 'address', 'guardian_name', 'gender', 'grade_level'];
      requiredIds.forEach(function(id) {
        const field = document.getElementById(id);
        if (!field.value.trim()) {
          // Capitalize first letter of the field name for the message.
          errorMessages.push(id.charAt(0).toUpperCase() + id.slice(1) + " is required.");
        }
      });
      
      // If any errors were found, display them using SweetAlert and do not submit the form.
      if (errorMessages.length > 0) {
        Swal.fire({
          title: 'Validation Error',
          icon: 'error',
          html: errorMessages.join("<br>"),
          confirmButtonText: 'OK',
          customClass: {
            popup: 'swal2-dark'
          }
        });
        return;
      }
      
      // If validations pass, remove any inline error classes (optional) and submit the form.
      form.submit();
    });
  }
});
</script>
<!-- reCAPTCHA v2 API script is already included in the head -->
<script>
function onRecaptchaSuccess() {
    // Enable the Register button once the captcha is solved
    document.getElementById('register-btn').disabled = false;
    // Optionally hide the captcha widget after success
    document.getElementById('recaptcha-container').style.display = 'none';
}
</script>
</body>
</html>