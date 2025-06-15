<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: Loginpage.php');
    exit();
}

include '../Connection/database.php';

$user_id = $_SESSION['user_id'];

// Fetch user data if not already in session
if (!isset($_SESSION['user_data'])) {
    // First, verify the user exists and get their data
    $query = "SELECT firstname, lastname, contact, lrn, guardian_name, uli FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);

    if (!$stmt) {
        die('Error preparing statement: ' . $conn->error);
    }

    if (!$stmt->bind_param("i", $user_id)) {
        die('Error binding parameters: ' . $stmt->error);
    }

    if (!$stmt->execute()) {
        die('Error executing query: ' . $stmt->error);
    }

    $result = $stmt->get_result();

    if (!$result) {
        die('Error getting result: ' . $stmt->error);
    }

    if ($result->num_rows !== 1) {
        session_unset();
        session_destroy();
        header('Location: Loginpage.php');
        exit();
    }

    $_SESSION['user_data'] = $result->fetch_assoc();
    $stmt->close();
}

// Verify user session is valid
$query = "SELECT id FROM users WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($query);

if (!$stmt) {
    die('Error preparing statement: ' . $conn->error);
}

if (!$stmt->bind_param("i", $user_id)) {
    die('Error binding parameters: ' . $stmt->error);
}

if (!$stmt->execute()) {
    die('Error executing query: ' . $stmt->error);
}

$result = $stmt->get_result();

if (!$result) {
    die('Error getting result: ' . $stmt->error);
}

if ($result->num_rows !== 1) {
    session_unset();
    session_destroy();
    header('Location: Loginpage.php');
    exit();
}

// Fetch the latest request
$query = "SELECT r.*, u.firstname, u.lastname, u.contact, u.lrn, u.uli, u.guardian_name
          FROM requests r
          JOIN users u ON r.user_id = u.id
          WHERE r.user_id = ?
          ORDER BY r.created_at DESC
          LIMIT 1";

$stmt = $conn->prepare($query);

if (!$stmt) {
    die('Error preparing statement: ' . $conn->error);
}

if (!$stmt->bind_param("i", $user_id)) {
    die('Error binding parameters: ' . $stmt->error);
}

if (!$stmt->execute()) {
    die('Error executing query: ' . $stmt->error);
}

$result = $stmt->get_result();

if ($result === false) {
    die('Error getting result: ' . $stmt->error);
}

$request = $result->fetch_assoc();
$stmt->close();

function logUserActivity($user_id, $action) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO user_activity (user_id, action, activity_time) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $user_id, $action);
    $stmt->execute();
}

logUserActivity($user_id, 'dashboard_access');

// Check if the user already has an active request
$query = "SELECT * FROM requests WHERE user_id = ? AND status != 'Completed'";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$active_request = $result->fetch_assoc();

// Handle POST submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$active_request) {
    if (isset($_POST['confirm'])) {
        // Retrieve data from $_POST
        $document_type = $_POST['document_type'];
        $purpose       = $_POST['purpose'];
        $school_year   = $_POST['school_year']; // Get the school year
        $priority      = $_POST['priority'];
        $firstname     = $_POST['firstname'];
        $lastname      = $_POST['lastname'];
        $contact       = $_POST['contact'];
        $lrn           = $_POST['lrn'];

        // Get the user's ULI from the users table
        $stmt = $conn->prepare("SELECT uli FROM users WHERE id = ?");
        if (!$stmt) {
            die('Error preparing statement: ' . $conn->error);
        }
        
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $uli = $user['uli'];

        // If ULI is not found, generate a new one
        if (empty($uli)) {
            $uli = 'ULI-' . uniqid();
            // Update the user's ULI
            $stmt = $conn->prepare("UPDATE users SET uli = ? WHERE id = ?");
            $stmt->bind_param("si", $uli, $user_id);
            $stmt->execute();
        }

        // Update user information
        $query = "UPDATE users SET firstname = ?, lastname = ?, contact = ?, lrn = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            die('Error preparing UPDATE statement: ' . $conn->error);
        }

        $stmt->bind_param('ssssi', $firstname, $lastname, $contact, $lrn, $user_id);
        
        if (!$stmt->execute()) {
            die('Error executing UPDATE query: ' . $stmt->error);
        }

        // Insert the new request with the ULI
        $query = "INSERT INTO requests (user_id, document_type, purpose, school_year, priority, status, uli, created_at)
                  VALUES (?, ?, ?, ?, ?, 'Pending', ?, NOW())";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            die('Error preparing INSERT statement: ' . $conn->error);
        }

        $stmt->bind_param('isssss', $user_id, $document_type, $purpose, $school_year, $priority, $uli);
        
        if (!$stmt->execute()) {
            die('Error executing INSERT query: ' . $stmt->error);
        }

        // Add activity log
        logUserActivity($user_id, 'submitted_request');

        header('Location: dashboard_user.php');
        exit();
    }
    elseif (isset($_POST['document_type'])) {
        $_SESSION['document_type'] = $_POST['document_type'];
        $_SESSION['purpose']       = $_POST['purpose'];
        $_SESSION['school_year']   = $_POST['school_year'];
        $_SESSION['priority']      = $_POST['priority'];
        $_SESSION['firstname']     = $_POST['firstname'];
        $_SESSION['lastname']      = $_POST['lastname'];
        $_SESSION['contact']       = $_POST['contact'];
        $_SESSION['lrn']           = $_POST['lrn'];
        header('Location: dashboard_user.php?step=review');
        exit();
    }
}

// Fetch user's latest request
$query = "SELECT r.*, u.firstname, u.lastname, u.contact, u.lrn, u.uli, u.guardian_name
          FROM requests r
          JOIN users u ON r.user_id = u.id
          WHERE r.user_id = ?
          ORDER BY r.created_at DESC
          LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result === false) {
    die('Error executing query: ' . $conn->error);
}

$request = $result->fetch_assoc();

// Fetch upcoming events
$query = "SELECT * FROM events WHERE date >= CURDATE() ORDER BY date ASC";
$result = $conn->query($query);

if ($result === false) {
    die('Error executing query: ' . $conn->error);
}

$events = $result->fetch_all(MYSQLI_ASSOC);

// Include your common header (with navigation)
include 'header.php';

/**
 * Determine which step we are on based on the request status:
 * Pending           -> Step 1
 * Processing        -> Step 2
 * Ready for Pickup  -> Step 3
 * Completed         -> Step 4
 */
function getCurrentStep($status) {
    switch ($status) {
        case 'Pending':
            return 1;
        case 'Processing':
            return 2;
        case 'Ready for Pickup':
            return 3;
        case 'Completed':
            return 4;
        default:
            return 1; // fallback
    }
}

/**
 * Return 'step-active' or 'step-inactive' based on whether
 * $stepNum is <= the current step
 */
function isActiveStep($stepNum, $currentStep) {
    return ($stepNum <= $currentStep) ? 'step-active' : 'step-inactive';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Dashboard</title>
    <!-- Ensure mobile responsiveness -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">

    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">

    <!-- Custom styles with color-coded steps and simple animations -->
    <style>
        .dashboard-container {
            padding: 15px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
        }
        .select-document-card,
        .review-card,
        .request-info-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        /* Step row styling to mimic your screenshot */
        .step-row {
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-top: 30px;
            margin-bottom: 30px;
            animation: fadeIn 0.5s ease-in-out; /* fade the entire row in */
        }
        @keyframes fadeIn {
            0%   { opacity: 0; }
            100% { opacity: 1; }
        }
        /* Horizontal line behind steps */
        .step-row::before {
            content: "";
            position: absolute;
            top: 35px; /* adjust if icons are bigger */
            left: 0;
            height: 2px;
            background: #ccc;
            z-index: 1;
            width: 0; /* Start from 0 for animation */
            animation: lineGrow 1.5s forwards ease-in-out;
        }
        @keyframes lineGrow {
            0%   { width: 0; }
            100% { width: 100%; }
        }
        /* Each step box */
        .step-col {
            position: relative;
            z-index: 2;
            width: 24%;
            text-align: center;
            opacity: 0;                 /* for staggered fade up */
            animation: fadeUp 1s forwards; 
        }
        /* Delay each step a bit more */
        .step-col:nth-child(1) { animation-delay: 0.2s; }
        .step-col:nth-child(2) { animation-delay: 0.4s; }
        .step-col:nth-child(3) { animation-delay: 0.6s; }
        .step-col:nth-child(4) { animation-delay: 0.8s; }

        @keyframes fadeUp {
            0% {
                opacity: 0;
                transform: translateY(20px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
        /* Circle icon container */
        .step-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 10px auto;
            border-radius: 50%;
            border: 2px solid #ccc; /* default inactive border */
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.3s, border-color 0.3s, background-color 0.3s;
        }
        .step-icon:hover {
            transform: scale(1.1);
        }
        /* SVG inside the circle */
        .step-icon svg {
            width: 24px;
            height: 24px;
            fill: #999; /* default inactive icon color */
            transition: fill 0.3s;
        }

        /* ACTIVE states for each step (color-coded) */
        /* Step 1: Blue */
        .step-blue.step-active {
            border-color:rgb(255, 255, 255);
            background-color:rgb(255, 255, 255);
        }
        .step-blue.step-active svg {
            fill: #fff;
        }
        /* Step 2: Orange */
        .step-orange.step-active {
            border-color:rgb(255, 255, 255);
            background-color:rgb(255, 255, 255);
        }
        .step-orange.step-active svg {
            fill: #fff;
        }
        /* Step 3: Green */
        .step-green.step-active {
            border-color: #28a745;
            background-color: #28a745;
        }
        .step-green.step-active svg {
            fill: #fff;
        }
        /* Step 4: Purple */
        .step-purple.step-active {
            border-color: #6f42c1;
            background-color: #6f42c1;
        }
        .step-purple.step-active svg {
            fill: #fff;
        }

    </style>
</head>
<body style="min-height: 100vh; background: linear-gradient(135deg, #71b7e6, #9b59b6);">

<div class="dashboard-container">
    <?php 
    // Step-based forms (like "request_form", "review") remain the same
    if (isset($_GET['step']) && $_GET['step'] == 'request_form' && !$active_request): 
    ?>
        <!-- Select Document Section -->
        <div class="select-document-card">
            <h4 class="mb-4">Select Document</h4>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label">Document Type</label>
                    <select name="document_type" class="form-select" required>
                        <option value="Diploma"
                            <?php echo (isset($_SESSION['document_type']) && $_SESSION['document_type'] == 'Diploma') ? 'selected' : ''; ?>>
                            Diploma
                        </option>
                        <option value="Good Moral Certificate"
                            <?php echo (isset($_SESSION['document_type']) && $_SESSION['document_type'] == 'Good Moral Certificate') ? 'selected' : ''; ?>>
                            Good Moral Certificate
                        </option>
                        <option value="Certificate of Completion of Kinder"
                            <?php echo (isset($_SESSION['document_type']) && $_SESSION['document_type'] == 'Certificate of Completion of Kinder') ? 'selected' : ''; ?>>
                            Certificate of Completion of Kinder
                        </option>
                        <option value="SF10"
                            <?php echo (isset($_SESSION['document_type']) && $_SESSION['document_type'] == 'SF10') ? 'selected' : ''; ?>>
                            SF10
                        </option>
                        <option value="Certificate of Enrollment"
                            <?php echo (isset($_SESSION['document_type']) && $_SESSION['document_type'] == 'Certificate of Enrollment') ? 'selected' : ''; ?>>
                            Certificate of Enrollment
                        </option>
                    </select>
                </div>
                <?php
                // Generate the current academic year (e.g., 2024-2025)
                $currentYear = date('Y');
                $nextYear = $currentYear + 1;
                $defaultSchoolYear = "$currentYear-$nextYear";
                ?>

                <div class="mb-3">
                    <label class="form-label">School Year</label>
                    <select name="school_year" class="form-select" required>
                        <option value="2023-2024" <?php echo (isset($_SESSION['school_year']) && $_SESSION['school_year'] == '2023-2024') ? 'selected' : ''; ?>>2023-2024</option>
                        <option value="2024-2025" <?php echo (isset($_SESSION['school_year']) && $_SESSION['school_year'] == '2024-2025') ? 'selected' : ''; ?>>2024-2025</option>
                        <option value="2025-2026" <?php echo (isset($_SESSION['school_year']) && $_SESSION['school_year'] == '2025-2026') ? 'selected' : ''; ?>>2025-2026</option>
                        <option value="<?php echo $defaultSchoolYear; ?>" <?php echo (!isset($_SESSION['school_year']) || $_SESSION['school_year'] == $defaultSchoolYear) ? 'selected' : ''; ?>>
                            <?php echo $defaultSchoolYear; ?>
                        </option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Purpose</label>
                    <textarea name="purpose" class="form-control" rows="4" required><?php echo isset($_SESSION['purpose']) ? $_SESSION['purpose'] : ''; ?></textarea>
                </div>
              
                <div class="mb-3">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select" required>
                        <option value="Normal"
                            <?php echo (isset($_SESSION['priority']) && $_SESSION['priority'] == 'Normal') ? 'selected' : ''; ?>>
                            Normal
                        </option>
                        <option value="Urgent"
                            <?php echo (isset($_SESSION['priority']) && $_SESSION['priority'] == 'Urgent') ? 'selected' : ''; ?>>
                            Urgent
                        </option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Student First Name</label>
                    <input type="text" name="firstname" class="form-control"
                           value="<?php echo isset($_SESSION['firstname']) ? $_SESSION['firstname'] : $_SESSION['user_data']['firstname']; ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Student Last Name</label>
                    <input type="text" name="lastname" class="form-control"
                           value="<?php echo isset($_SESSION['lastname']) ? $_SESSION['lastname'] : $_SESSION['user_data']['lastname']; ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact" class="form-control"
                           value="<?php echo isset($_SESSION['contact']) ? $_SESSION['contact'] : $_SESSION['user_data']['contact']; ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">LRN</label>
                    <input type="text" name="lrn" class="form-control"
                           value="<?php echo isset($_SESSION['lrn']) ? $_SESSION['lrn'] : $_SESSION['user_data']['lrn']; ?>" required>
                </div>
                <button type="submit" class="btn btn-primary btn-action">Submit Request</button>
            </form>
        </div>

    <?php elseif (isset($_GET['step']) && $_GET['step'] == 'review' && !$active_request): ?>
        <!-- Review Information Section -->
        <div class="review-card">
            <h4 class="mb-4">Review Your Information</h4>
            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            <form method="POST" action="dashboard_user.php">
                <div class="mb-3">
                    <label class="form-label">First Name</label>
                    <input type="text" name="firstname" class="form-control"
                           value="<?php echo isset($_SESSION['firstname']) ? $_SESSION['firstname'] : $_SESSION['user_data']['firstname']; ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Last Name</label>
                    <input type="text" name="lastname" class="form-control"
                           value="<?php echo isset($_SESSION['lastname']) ? $_SESSION['lastname'] : $_SESSION['user_data']['lastname']; ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Contact Number</label>
                    <input type="text" name="contact" class="form-control"
                           value="<?php echo isset($_SESSION['contact']) ? $_SESSION['contact'] : $_SESSION['user_data']['contact']; ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">LRN</label>
                    <input type="text" name="lrn" class="form-control"
                           value="<?php echo isset($_SESSION['lrn']) ? $_SESSION['lrn'] : $_SESSION['user_data']['lrn']; ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Document Type</label>
                    <select name="document_type" class="form-select" required>
                        <option value="Diploma"
                            <?php echo (isset($_SESSION['document_type']) && $_SESSION['document_type'] == 'Diploma') ? 'selected' : ''; ?>>
                            Diploma
                        </option>
                        <option value="Good Moral Certificate"
                            <?php echo (isset($_SESSION['document_type']) && $_SESSION['document_type'] == 'Good Moral Certificate') ? 'selected' : ''; ?>>
                            Good Moral Certificate
                        </option>
                        <option value="Certificate of Completion of Kinder"
                            <?php echo (isset($_SESSION['document_type']) && $_SESSION['document_type'] == 'Certificate of Completion of Kinder') ? 'selected' : ''; ?>>
                            Certificate of Completion of Kinder
                        </option>
                        <option value="SF10"
                            <?php echo (isset($_SESSION['document_type']) && $_SESSION['document_type'] == 'SF10') ? 'selected' : ''; ?>>
                            SF10
                        </option>
                        <option value="Certificate of Enrollment"
                            <?php echo (isset($_SESSION['document_type']) && $_SESSION['document_type'] == 'Certificate of Enrollment') ? 'selected' : ''; ?>>
                            Certificate of Enrollment
                        </option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Purpose</label>
                    <textarea name="purpose" class="form-control" rows="4" required><?php echo isset($_SESSION['purpose']) ? $_SESSION['purpose'] : ''; ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">School Year</label>
                    <input type="text" name="school_year" class="form-control"
                           value="<?php echo isset($_SESSION['school_year']) ? $_SESSION['school_year'] : ''; ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Priority</label>
                    <select name="priority" class="form-select" required>
                        <option value="Normal"
                            <?php echo (isset($_SESSION['priority']) && $_SESSION['priority'] == 'Normal') ? 'selected' : ''; ?>>
                            Normal
                        </option>
                        <option value="Urgent"
                            <?php echo (isset($_SESSION['priority']) && $_SESSION['priority'] == 'Urgent') ? 'selected' : ''; ?>>
                            Urgent
                        </option>
                    </select>
                </div>
                <div class="d-flex justify-content-between">
                    <a href="?step=request_form" class="btn btn-secondary btn-action">Back to Form</a>
                    <button type="submit" name="confirm" value="1" class="btn btn-success btn-action">Confirm Request</button>
                </div>
            </form>
        </div>

    <?php else: ?>
        <!-- If no step is specified or no active request is found, show a link to create a new request -->
        <?php if (!$active_request && (!isset($_GET['step']) || $_GET['step'] != 'request_form')): ?>
            <div class="review-card">
                <h4>Create a Document Request</h4>
                <p>You currently have no active document requests.</p>
                <a href="?step=request_form" class="btn btn-primary">Request a Document</a>
            </div>
        <?php endif; ?>

        <?php if ($request): ?>
            <?php
            $status = $request['status'];
            // Figure out which step is "current"
            $currentStep = getCurrentStep($status);
            ?>
            <!-- Show the Request Information Card first -->
            <div class="request-info-card">
                <h4>Request for Student record information</h4>
                <p>
                    Published by <strong>Department of Education (DepEd)</strong> on 
                    <strong><?php echo date('M d, Y', strtotime($request['created_at'])); ?></strong>.
                </p>
                <p>
                    Requested from VILLA TEODORA by 
                    <strong><?php echo isset($request['guardian_name']) ? htmlspecialchars($request['guardian_name']) : 'N/A'; ?></strong>
                    at <strong><?php echo date('h:i A', strtotime($request['created_at'])); ?></strong>
                    on <strong><?php echo date('M d, Y', strtotime($request['created_at'])); ?></strong>.
                </p>
                <p><strong>Purpose:</strong> <?php echo htmlspecialchars($request['purpose']); ?></p>
                <p>
                    <strong>Date of Coverage:</strong>
                    <?php
                        $startDate = date('m/d/Y', strtotime($request['created_at']));
                        $endDate   = $request['eta'] ? date('m/d/Y', strtotime($request['eta'])) : 'Not set';
                        echo $startDate . ' - ' . $endDate;
                    ?>
                </p>
                <p><strong>UNIQUE LEARNER ID </strong> <?php echo $request['uli']; ?></p>

                <?php if ($currentStep < 4): ?>
                    <!-- Not completed yet, show actual status in uppercase -->
                    <p><strong>Status:</strong> <?php echo strtoupper($status); ?></p>
                <?php else: ?>
                    <!-- Step 4 means Completed -->
                    <p><strong>Status:</strong> SUCCESSFUL</p>
                    <!-- If "Ready for Pickup" or "Completed" with a QR code -->
                    <?php if (($status === 'Ready for Pickup' || $status === 'Completed') && !empty($request['qr_data'])): ?>
                        <p>Please show this QR code when you pick up your document:</p>
                        <img src="data:image/png;base64,<?php echo base64_encode($request['qr_data']); ?>"
                             alt="QR Code" style="width:200px;height:200px;">
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Now show the 4-step timeline at the bottom (always, or you can hide if step=4) -->
            <?php if ($currentStep < 4): ?>
                <div class="step-row">
                    <!-- Step 1 -->
                    <div class="step-col">
                        <div class="step-icon step-blue <?php echo isActiveStep(1, $currentStep); ?>">
                            <!-- Paper plane icon -->
                            <svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="paper-plane"
                                 class="svg-inline--fa fa-paper-plane" role="img"
                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                                <path d="M476.4 3.7c-6.3-4.6-14.5-5.5-21.4-2.3L21.3 199.4C8.6 204.9.2 217.3 0 231c-.2 13.7 
                                         8.1 26.3 20.6 31.5l135.7 54.2 54.2 135.7c5.2 12.5 17.8 20.8 31.5 20.6
                                         13.7-.2 26.1-8.6 31.6-21.3l197.9-433.7c3.2-6.9 2.3-15.1-2.3-21.4zM160 
                                         304l-107.3-42.9L393.2 83.8 160 304zm57.4 57.4l177.3-175.8-42.9 
                                         107.3-134.4 134.4z"></path>
                            </svg>
                        </div>
                        <h5>REQUEST SUBMITTED</h5>
                        <p>You have submitted an FOI request</p>
                        <small>Date: <?php echo date('M d, Y h:i A', strtotime($request['created_at'])); ?></small>
                    </div>

                    <!-- Step 2 -->
                    <div class="step-col">
                        <div class="step-icon step-orange <?php echo isActiveStep(2, $currentStep); ?>">
                            <!-- Gear icon -->
                            <svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="cog"
                                 class="svg-inline--fa fa-cog" role="img"
                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                                <path d="M487.4 315.7l-42.6-24.6c2.3-14.7 3.5-29.7 3.5-44.9s-1.2-30.2-3.5-44.9l42.6-24.6c15.3-8.8 
                                         21.4-28.4 13.2-43.8l-48-83.1c-8.2-15.2-27.7-21.4-43.8-13.2l-42.6 
                                         24.6c-23-20-49.7-35.5-78.7-46.1V24c0-17-13.8-30.8-30.8-30.8h-96.1c-17 
                                         0-30.8 13.8-30.8 30.8v49.2c-29 10.6-55.7 26.1-78.7 
                                         46.1l-42.6-24.6c-15.1-8.2-35.7-2-43.8 
                                         13.2l-48 83.1c-8.2 15.4-2 35 13.2 
                                         43.8l42.6 24.6C1.2 216.2 0 231.2 0 
                                         246.4s1.2 30.2 3.5 44.9l-42.6 
                                         24.6c-15.2 8.8-21.4 28.4-13.2 
                                         43.8l48 83.1c8.2 15.2 27.7 21.4 
                                         43.8 13.2l42.6-24.6c23 20 49.7 
                                         35.5 78.7 46.1v49.2c0 17 13.8 
                                         30.8 30.8 30.8h96.1c17 0 30.8-13.8 
                                         30.8-30.8v-49.2c29-10.6 55.7-26.1 
                                         78.7-46.1l42.6 24.6c15.1 8.2 
                                         35.7 2 43.8-13.2l48-83.1c8.3-15.5 
                                         2.1-35.1-13.1-43.9zM256 352c-52.9 
                                         0-96-43.1-96-96 0-52.9 43.1-96 
                                         96-96 52.9 0 96 43.1 96 96 0 
                                         52.9-43.1 96-96 96z"></path>
                            </svg>
                        </div>
                        <h5>PROCESSING REQUEST</h5>
                        <p>Your request is already in review</p>
                        <small>Date: ...</small>
                    </div>

                    <!-- Step 3 -->
                    <div class="step-col">
                        <div class="step-icon step-green <?php echo isActiveStep(3, $currentStep); ?>">
                            <!-- Check circle icon -->
                            <svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="check-circle"
                                 class="svg-inline--fa fa-check-circle" role="img"
                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">
                                <path d="M504 256c0 136.967-111.033 248-248 
                                         248S8 392.967 8 256 119.033 8 256 
                                         8s248 111.033 248 248zM227.314 
                                         387.314l184-184c6.248-6.248 
                                         6.248-16.379 0-22.627l-22.627-22.627c-6.248-6.248-16.379-6.248-22.628 
                                         0L216 296.373l-70.059-70.059c-6.248-6.248-16.379-6.248-22.628 
                                         0L100.686 248.94c-6.248 6.248-6.248 
                                         16.379 0 22.627l126.628 126.628c6.249 
                                         6.249 16.379 6.249 22.628.001z"></path>
                            </svg>
                        </div>
                        <h5>REQUEST SUCCESSFUL</h5>
                        <p>Your request was successful</p>
                        <small>Date: ...</small>
                    </div>

                    <!-- Step 4 -->
                    <div class="step-col">
                        <div class="step-icon step-purple <?php echo isActiveStep(4, $currentStep); ?>">
                            <!-- Star icon -->
                            <svg aria-hidden="true" focusable="false" data-prefix="fas" data-icon="star"
                                 class="svg-inline--fa fa-star" role="img"
                                 xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512">
                                <path d="M381.2 150.3L524.9 171c26.2 3.8 36.7 36 
                                         17.7 54.6l-105 102.3 24.8 144.5c4.5 
                                         26.1-23 46-46.4 33.7L288 439.6 184.1 
                                         505c-23.4 12.3-50.9-7.6-46.4-33.7l24.8-144.5-105-102.3c-19-18.6-8.5-50.8 
                                         17.7-54.6l143.7-20.8 64.3-130.3c11.7-23.4 
                                         45.6-23.4 57.3 0l64.3 130.3z"></path>
                            </svg>
                        </div>
                        <h5>RATE YOUR REQUEST</h5>
                        <p>How was your request?</p>
                        <small>Step 4</small>
                    </div>
                </div>
            <?php else: ?>
                <!-- If step 4, the request is completed, so we can show a message or do nothing -->
                <div class="review-card">
                    <h4>Your request is fully completed!</h4>
                    <p>You may rate your request or contact us if needed.</p>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="review-card">
                <p>No request found.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>


<?php
// Fetch the ULI from the database
$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT uli FROM users WHERE id = ?");
if (!$stmt) {
    die('Error preparing statement: ' . $conn->error);
}

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result === false) {
    die('Error executing query: ' . $conn->error);
}

$user = $result->fetch_assoc();
$uli = $user['uli'] ?? 'No ULI'; // Handle cases where ULI is null
?>

<!-- Display the ULI in the user dashboard -->




<!-- Bootstrap 5 Bundle with Popper -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>

<!-- Tawk.to Script (optional) -->
<script type="text/javascript">
    var Tawk_API = Tawk_API || {}, Tawk_LoadStart = new Date();
    (function() {
        var s1 = document.createElement("script"), s0 = document.getElementsByTagName("script")[0];
        s1.async = true;
        // Replace 'your_tawk_to_property_id' with your actual Tawk.to property ID
        s1.src = 'https://embed.tawk.to/your_tawk_to_property_id/default';
        s1.charset = 'UTF-8';
        s1.setAttribute('crossorigin', '*');
        s0.parentNode.insertBefore(s1, s0);
    })();
</script>

<script>
function updateDashboard() {
    fetch('fetch_requests.php')
        .then(response => response.json())
        .then(requests => {
            // Update requests table
            const tbody = document.getElementById('requestsTableBody');
            tbody.innerHTML = requests.map(request => `
                <tr class="request-row" data-status="${request.status.toLowerCase()}" data-document-type="${request.document_type.toLowerCase()}" data-priority="${request.priority.toLowerCase()}">
                    <td>#${request.id}</td>
                    <td>${request.firstname} ${request.lastname}</td>
                    <td>${request.contact}</td>
                    <td>${request.document_type}</td>
                    <td>${request.priority}</td>
                    <td>
                        <span class="status-badge status-${request.status.toLowerCase().replace(' ', '-')}">
                            ${request.status}
                        </span>
                    </td>
                    <td>${request.eta ? new Date(request.eta).toLocaleDateString() : 'Not set'}</td>
                </tr>
            `).join('');
        });
}

// Update every 5 seconds
setInterval(updateDashboard, 5000);

// Initial update
updateDashboard();
</script>

</body>
</html>