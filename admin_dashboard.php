<?php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// PHPMailer setup
use PHPMailer\PHPMailer\Exception; // Correct placement of the use statement
require '../vendor/autoload.php';
// Secure session initialization
if (session_status() == PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path' => '/',
        'domain' => $_SERVER['HTTP_HOST'],
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);
    session_start();
}

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Add this PHP function at the top of your file
function getGreeting() {
    $hour = date('H'); // Will now use Philippine time
    if ($hour >= 5 && $hour < 12) {
        return 'Good Morning';
    } elseif ($hour >= 12 && $hour < 18) {
        return 'Good Afternoon';
    } else {
        return 'Good Evening';
    }
}

// Add this right after your session check
if (isset($_SESSION['admin']) && !isset($_SESSION['greeting_shown'])) {
    $_SESSION['greeting_shown'] = true;
    $greeting = getGreeting();
    echo "
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: '{$greeting}, Admin Crysler!',
                html: '<div style=\"font-size: 1.1em; margin-top: 10px;\">' +
                      'Welcome to StudentRequestHub Admin Dashboard<br>' +
                      '<span style=\"font-size: 0.9em; color: #666;\">Philippine Time: " . date('h:i A') . "</span>' +
                      '</div>',
                icon: 'success',
                showConfirmButton: true,
                confirmButtonText: 'Let\'s Start',
                confirmButtonColor: '#4361ee',
                timer: 3000,
                timerProgressBar: true
            });
        });
    </script>";
}

// CSRF token generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Generate a secure random token
}

// Authentication check
if (!isset($_SESSION['admin'])) {
    header('Location: login_admin.php');
    exit;
}

// Database connection
require '../Connection/database.php';

// --- Improved send_sms() function ---
// Function to send SMS
function send_sms($recipient, $message) {
    $api_key = '';
    $sender_name = '';
    $url = 'https://semaphore.co/api/v4/messages';

    // Format number if needed
    $recipient = trim($recipient);
    if (strpos($recipient, '+63') !== 0) {
        $recipient = preg_replace('/[^0-9]/', '', $recipient);
        if (substr($recipient, 0, 1) === '0') {
            $recipient = '+63' . substr($recipient, 1);
        }
    }

    $parameters = [
        'apikey' => $api_key,
        'number' => $recipient,
        'message' => $message,
        'sendername' => $sender_name
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($parameters),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        error_log('Semaphore cURL error: ' . curl_error($ch));
    }
    
    curl_close($ch);
    return ($httpCode === 200);
}

// --- sendStatusNotification() function ---
function sendStatusNotification($requestId, $status, $conn) {
    try {
        $query = "SELECT u.firstname, u.lastname, u.contact, u.guardian_name, r.document_type 
                 FROM users u JOIN requests r ON u.id = r.user_id 
                 WHERE r.id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) return false;
        
        $stmt->bind_param('i', $requestId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user || empty($user['contact'])) {
            error_log("No user or contact found for request ID: $requestId");
            return false;
        }

        $parent = !empty($user['guardian_name']) ? $user['guardian_name'] : 'Parent/Guardian';
        $student_name = trim($user['firstname'] . ' ' . $user['lastname']);
        $contact = $user['contact'];
        $document_type = $user['document_type'] ?? 'document';

        $messages = [
            'Pending' => "Dear $parent, we've received your $document_type request for $student_name. We will process it soon.",
            'Processing' => "Dear $parent, your $document_type request for $student_name is being processed.",
            'Ready for Pickup' => "Dear $parent, the $document_type for $student_name is ready for pickup at school.",
            'Completed' => "Dear $parent, the $document_type request for $student_name has been completed. Thank you!",
            'Declined' => "Dear $parent, your $document_type request for $student_name was declined. Please contact the school.",
            'Received' => "Dear $parent, we confirm receipt of $document_type for $student_name. Thank you!"
        ];

        if (!isset($messages[$status])) {
            error_log("No message template for status: $status");
            return false;
        }

        return send_sms($contact, $messages[$status]);
    } catch (Exception $e) {
        error_log("Error sending SMS notification: " . $e->getMessage());
        return false;
    }
}

// --- Unified POST handler for status updates ---
// Replace your duplicate handlers with this single handler:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_id'], $_POST['status'])) {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
    }

    $request_id = filter_var($_POST['request_id'], FILTER_VALIDATE_INT);
    $status = $_POST['status'];
    $eta = isset($_POST['eta']) ? $_POST['eta'] : null;

    if (!$request_id) {
        die(json_encode(['success' => false, 'message' => 'Invalid request ID']));
    }

    // Update DB
    $query = "UPDATE requests SET status = ?, processed_date = NOW()".($eta ? ", eta = ?" : "")." WHERE id = ?";
    $stmt = $conn->prepare($query);
    
    if ($eta) {
        $stmt->bind_param('ssi', $status, $eta, $request_id);
    } else {
        $stmt->bind_param('si', $status, $request_id);
    }

    if ($stmt->execute()) {
        $smsSent = sendStatusNotification($request_id, $status, $conn);
        echo json_encode(['success' => true, 'sms_sent' => $smsSent]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update request']);
    }
    exit;
}

// Handle form submissions for status update


// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        error_log("Invalid CSRF token. Received: " . ($_POST['csrf_token'] ?? 'null') . ", Expected: " . $_SESSION['csrf_token']);
        die(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
    }

    // Update request status and ETA
    if (isset($_POST['request_id']) && isset($_POST['eta']) && isset($_POST['status'])) {
        // Input validation
        $status = in_array($_POST['status'], ['Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Declined']) 
            ? $_POST['status'] 
            : 'Pending';
        
        $eta = DateTime::createFromFormat('Y-m-d', $_POST['eta']) ? $_POST['eta'] : null;
        $request_id = filter_var($_POST['request_id'], FILTER_VALIDATE_INT);

        if (!$request_id) {
            die(json_encode(['success' => false, 'message' => 'Invalid request ID']));
        }

        // Log the update attempt
        error_log("[STATUS UPDATE] Attempting to update request ID: $request_id to status: $status with ETA: $eta");
        
        // Update database
        $query = "UPDATE requests SET status = ?, eta = ?, processed_date = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("[STATUS UPDATE ERROR] Prepare failed: " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'Failed to prepare update query']);
            exit;
        }
        
        $stmt->bind_param('ssi', $status, $eta, $request_id);
        
        if ($stmt->execute()) {
            error_log("[STATUS UPDATE] Database updated successfully for request ID: $request_id");
            
            // Send the SMS notification
            $notificationSent = sendStatusNotification($request_id, $status, $conn);
            if ($notificationSent) {
                error_log("[STATUS UPDATE] SMS notification sent successfully.");
            } else {
                error_log("[STATUS UPDATE] SMS notification failed.");
            }
            
            echo json_encode(['success' => true, 'message' => 'Request updated successfully']);
        } else {
            error_log("[STATUS UPDATE ERROR] Execute failed: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Failed to update request: ' . $stmt->error]);
        }
        exit;
    }

    // Mark request as received
    if (isset($_POST['receive_request_id'])) {
        $request_id = filter_var($_POST['receive_request_id'], FILTER_VALIDATE_INT);

        if (!$request_id) {
            error_log("Invalid request ID: " . $_POST['receive_request_id']);
            echo json_encode(['success' => false, 'message' => 'Invalid request ID.']);
            exit;
        }

        // Update the request status in the database
        $query = "UPDATE requests SET status = 'Received', received_date = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Prepare failed: " . $conn->error);
            echo json_encode(['success' => false, 'message' => 'Failed to prepare the query.']);
            exit;
        }

        $stmt->bind_param('i', $request_id);
        if ($stmt->execute()) {
            // Send notification for received status
            $notificationSent = sendStatusNotification($request_id, 'Received', $conn); 
            
            // Log whether notification was sent
            if ($notificationSent) {
                error_log("Notification sent successfully for received request ID: $request_id");
            } else {
                error_log("Failed to send notification for received request ID: $request_id");
            }
            
            echo json_encode(['success' => true, 'message' => 'Request status updated successfully.']);
        } else {
            error_log("Execute failed: " . $stmt->error);
            echo json_encode(['success' => false, 'message' => 'Failed to execute the query.']);
        }
        exit;
    }

    // Simple status update with validation
    if (isset($_POST['request_id']) && isset($_POST['status']) && !isset($_POST['eta'])) {
        $request_id = filter_var($_POST['request_id'], FILTER_VALIDATE_INT);
        $new_status = $_POST['status'];
    
        // Get current status from database
        $query = "SELECT status, user_id FROM requests WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('i', $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $current_data = $result->fetch_assoc();
        $current_status = $current_data['status'];
        $user_id = $current_data['user_id'];
        
        // Define valid status progression
        $valid_transitions = [
            'Pending' => ['Processing', 'Declined'],
            'Processing' => ['Ready for Pickup', 'Declined'],
            'Ready for Pickup' => ['Completed', 'Declined']
        ];
        
        // Validate the status change
        if (!isset($valid_transitions[$current_status]) || 
            !in_array($new_status, $valid_transitions[$current_status])) {
            // Handle invalid status transition
        }
            die(json_encode([
                'success' => false,
                'message' => 'Invalid status change. Must follow sequence: Pending → Processing → Ready for Pickup → Completed'
            ]));
        }
        
        // If validation passes, update the status
        $query = "UPDATE requests SET status = ?, processed_date = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('si', $new_status, $request_id);
        
        if ($stmt->execute()) {
            // Send notification for the new status
            $notificationSent = sendStatusNotification($request_id, $new_status, $conn);
            
            echo json_encode(['success' => true, 'message' => 'Status updated successfully', 'sms_sent' => $notificationSent]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database update failed']);
        }
        exit;
    }


// Fetch requests data with pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10; // Number of records per page
$offset = ($page - 1) * $limit;

$query = "SELECT r.*, u.firstname, u.lastname, u.contact, u.lrn, u.email, r.priority 
          FROM requests r 
          JOIN users u ON r.user_id = u.id 
          WHERE r.status NOT IN ('Completed', 'Declined', 'Received') 
          ORDER BY r.priority DESC, r.created_at DESC
          LIMIT $limit OFFSET $offset";
$result = $conn->query($query);
$requests = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Get total records for pagination
$totalQuery = "SELECT COUNT(*) as total FROM requests 
               WHERE status NOT IN ('Completed', 'Declined', 'Received')";
$totalResult = $conn->query($totalQuery);
$totalRecords = $totalResult ? $totalResult->fetch_assoc()['total'] : 0;
$totalPages = ceil($totalRecords / $limit);

// Fetch request history
$query_history = "SELECT r.*, u.firstname, u.lastname, u.contact, u.lrn 
                  FROM requests r 
                  JOIN users u ON r.user_id = u.id 
                  WHERE r.status = 'Received' AND r.received_date < NOW() - INTERVAL 24 HOUR
                  ORDER BY r.received_date DESC";
$result_history = $conn->query($query_history);
$request_history = $result_history ? $result_history->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<head>
    <!-- Add this in the head section if not already present -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Bootstrap JS is needed for the modal functionality -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- ...existing head content... -->
</head>



<!-- About Modal -->


<style>
    :root {
        --sidebar-bg: #1a1f36;
        --sidebar-hover: #252d47;
        --sidebar-active: #4264e8;
        --sidebar-text: #9197a3;
        --sidebar-text-active: #ffffff;
        --section-label: #5a6178;
        --online-status: #34d399;
        --sidebar-width: 240px;
    }

    .sidebar {
        width: var(--sidebar-width);
        height: 100vh;
        position: fixed;
        top: 0;
        left: 0;
        background-color: var(--sidebar-bg);
        color: var(--sidebar-text);
        display: flex;
        flex-direction: column;
        overflow: hidden;
        transition: all 0.3s ease;
        z-index: 1000;
    }

    /* Header styles */
    .sidebar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }

    .brand {
        display: flex;
        align-items: center;
    }

    .brand i {
        font-size: 24px;
        color: var(--sidebar-active);
        margin-right: 12px;
    }

    .brand-text h3 {
        color: var(--sidebar-text-active);
        margin: 0;
        font-size: 14px;
        font-weight: 600;
    }

    .user-status {
        display: flex;
        flex-direction: column;
        font-size: 12px;
    }

    .username {
        color: var(--sidebar-text);
        font-weight: 500;
    }

    .status {
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .status i {
        font-size: 8px;
        color: var(--online-status);
    }

    .close-btn {
        background: transparent;
        border: none;
        color: var(--sidebar-text);
        cursor: pointer;
        padding: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    /* Content styles */
    .sidebar-content {
        flex-grow: 1;
        overflow-y: auto;
        padding-top: 8px;
    }

    .section-label {
        padding: 16px 16px 8px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.7px;
        text-transform: uppercase;
        color: var(--section-label);
    }

    .nav-menu {
        list-style: none;
        padding: 0;
        margin: 0;
    }

    .nav-item {
        position: relative;
    }

    .nav-item a {
        display: flex;
        align-items: center;
        padding: 12px 16px;
        text-decoration: none;
        color: inherit;
        transition: all 0.2s ease;
    }

    .nav-item a i {
        min-width: 24px;
        font-size: 16px;
        margin-right: 12px;
        text-align: center;
    }

    .nav-item:hover a {
        background-color: var(--sidebar-hover);
        color: var(--sidebar-text-active);
    }

    .nav-item.active {
        background-color: var(--sidebar-hover);
    }

    .nav-item.active a {
        color: var(--sidebar-text-active);
        font-weight: 500;
    }

    .nav-item.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 4px;
        background-color: var(--sidebar-active);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
        }
        
        .sidebar.show {
            transform: translateX(0);
        }
    }

    /* Animations for menu items */
    @keyframes fadeInLeft {
        from {
            opacity: 0;
            transform: translateX(-10px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .nav-item {
        animation: fadeInLeft 0.3s ease forwards;
        animation-delay: calc(var(--item-index) * 0.05s);
        opacity: 0;
    }

    .bottom-section {
        margin-top: auto;
        padding: 16px;
        border-top: 1px solid rgba(255, 255, 255, 0.05);
    }

    .about-btn,
    .logout-btn {
        width: 100%;
        display: flex;
        align-items: center;
        padding: 12px;
        border: none;
        background: transparent;
        color: var(--sidebar-text);
        cursor: pointer;
        transition: all 0.2s ease;
    }

    .about-btn:hover,
    .logout-btn:hover {
        background-color: var(--sidebar-hover);
        color: var(--sidebar-text-active);
        border-radius: 6px;
    }

    .about-btn i,
    .logout-btn i {
        margin-right: 12px;
        font-size: 16px;
    }

    .logout-btn {
        margin-top: 8px;
        color: #ff6b6b;
    }

    .about-system {
        margin-bottom: 8px;
    }

    /* Modal Styles */
    .modal-content {
        border-radius: 12px;
    }

    /* Add these specific styles for the logo centering */
    .modal-body .text-center {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 100%;
    }

    .modal-body .text-center img {
        max-width: 150px;
        height: auto;
        margin: 0 auto;
        display: block;
    }

    /* Override any potential conflicting styles */
    #aboutModal .modal-body img.img-fluid {
        margin-left: auto !important;
        margin-right: auto !important;
        display: block !important;
    }

    .developer-info {
        padding: 15px;
        background: #f8f9fa;
        border-radius: 8px;
        text-align: left; /* Reset text alignment for content */
    }

    .developer-info li {
        margin-bottom: 8px;
        font-size: 15px;
    }
</style>
</script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/11.7.5/sweetalert2.min.css">
    <style>
        /* Your CSS styles here */
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --success-color: #4cc9f0;
            --danger-color: #f72585;
            --warning-color: #f8961e;
            --info-color: #4895ef;
            --light-color: #f8f9fa;
            --dark-color: #212529;
        }
        
        body {
            background-color: #f5f7fb;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }
        
        .dashboard-container {
            padding: 2rem;
            margin-left: 250px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* Navbar styles */
        .navbar {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            padding: 1rem 2rem;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .navbar-brand {
            color: white;
            font-weight: 700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .navbar-brand i {
            font-size: 1.75rem;
        }
        
        /* Stats cards */
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.05);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            border: none;
            height: 100%;
            position: relative;
            overflow: hidden;
            border: none;
        }
        
        .stat-card::before {
            content: '';
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: currentColor;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 20px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card h3 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: inherit;
        }
        
        .stat-card p {
            color: inherit;
            opacity: 0.8;
            margin-bottom: 0;
        }
        
        .stat-card i {
            font-size: 2.5rem;
            opacity: 0.2;
            position: absolute;
            right: 1.5rem;
            top: 1.5rem;
        }
        
        .stat-card.pending {
            color: var (--warning-color);
            background-color: rgba(248, 150, 30, 0.1);
        }
        
        .stat-card.processing {
            color: var (--info-color);
            background-color: rgba(72, 149, 239, 0.1);
        }
        
        .stat-card.ready {
            color: var(--success-color);
            background-color: rgba(76, 201, 240, 0.1);
        }
        
        .stat-card.archived {
            color: var (--secondary-color);
            background-color: rgba(63, 55, 201, 0.1);
        }
        
        /* Requests table */
        .requests-table {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.05);
            margin-top: 2rem;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background:#4264e8; 
            color: white;
            padding: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.8rem;
            letter-spacing: 0.5px;
            border: none;
        }
        
        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-top: 1px solid #f1f3f9;
        }
        
        .table tr:last-child td {
            border-bottom: none;
        }
        
        .table tr:hover td {
            background-color: rgba(67, 97, 238, 0.05);
        }
        
        /* Status badges */
        .status-badge {
            padding: 0.5rem 0.75rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        
        .status-badge i {
            font-size: 0.6rem;
        }
        
        .status-pending {
            background-color: rgba(248, 150, 30, 0.1);
            color: var(--warning-color);
        }
        
        .status-processing {
            background-color: rgba(72, 149, 239, 0.1);
            color: var (--info-color);
        }
        
        .status-ready {
            background-color: rgba(76, 201, 240, 0.1);
            color: var (--success-color);
        }
        
        .status-completed {
            background-color: rgba(67, 97, 238, 0.1);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-weight: 500;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(0, 0, 0, 0.15);
        }
        
        .btn-action:active {
            transform: translateY(0);
        }
        
        .btn-action i {
            font-size: 0.9rem;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .animate-fade-in {
            animation: fadeIn 0.5s ease-out forwards;
        }
        
        .request-row {
            opacity: 0;
            animation: fadeIn 0.5s ease-out forwards;
        }
        
        .request-row:nth-child(1) { animation-delay: 0.1s; }
        .request-row:nth-child(2) { animation-delay: 0.2s; }
        .request-row:nth-child(3) { animation-delay: 0.3s; }
        .request-row:nth-child(4) { animation-delay: 0.4s; }
        .request-row:nth-child(5) { animation-delay: 0.5s; }
        
        /* Highlight new requests */
        .request-row.new-request {
            position: relative;
        }
        
        .request-row.new-request::after {
            content: 'New';
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--danger-color);
            color: white;
            font-size: 0.6rem;
            font-weight: bold;
            padding: 0.25rem 0.5rem;
            border-radius: 10px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        /* Modal styles */
        .modal-content {
            border: none;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border-radius: 12px 12px 0 0;
            padding: 1.25rem;
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            border-top: 1px solid #f1f3f9;
            padding: 1.25rem 1.5rem;
        }
        
        /* Responsive adjustments */
        @media (max-width: 992px) {
            .dashboard-container {
                margin-left: 0;
                padding: 1rem;
            }
            
            .stat-card h3 {
                font-size: 1.5rem;
            }
            
            .stat-card i {
                font-size: 2rem;
            }
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        
        ::-webkit-scrollbar-track {
            background: #f1f3f9;
        }
        
        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 4px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: var(--secondary-color);
        }
        
        /* Loading spinner */
        .spinner-container {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100px;
        }
        
        .spinner {
            width: 3rem;
            height: 3rem;
            border: 0.25rem solid rgba(67, 97, 238, 0.2);
            border-top-color: var(--primary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>

</script>
</head>
<body>
    <div class="d-flex">
        <?php include 'sidebar.php'; ?>
<div id="sidebarModalStatus"></div>

        <div class="dashboard-container flex-grow-1">
            <nav class="navbar navbar-expand-lg mb-4">
                <div class="container-fluid">
                <a href="admin_dashboard.php" class="navbar-brand">
    <i class="fas fa-tachometer-alt"></i>
    Admin Dashboard
</a>

                </div>
            </nav>

            <div class="stats-container row g-4 mb-4">
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card pending h-100 animate__animated animate__fadeInUp" onclick="filterRequests('Pending')">
                        <i class="fas fa-clock"></i>
                        <h3><?php echo count(array_filter($requests, function($r) { return $r['status'] == 'Pending'; })); ?></h3>
                        <p>Pending Requests</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card processing h-100 animate__animated animate__fadeInUp animate__delay-1s" onclick="filterRequests('Processing')">
                        <i class="fas fa-cog"></i>
                        <h3><?php echo count(array_filter($requests, function($r) { return $r['status'] == 'Processing'; })); ?></h3>
                        <p>Processing</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card ready h-100 animate__animated animate__fadeInUp animate__delay-2s" onclick="filterRequests('Ready for Pickup')">
                        <i class="fas fa-check-circle"></i>
                        <h3><?php echo count(array_filter($requests, function($r) { return $r['status'] == 'Ready for Pickup'; })); ?></h3>
                        <p>Ready for Pickup</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="stat-card archived h-100 animate__animated animate__fadeInUp animate__delay-3s" onclick="filterRequests('Archived')">
                        <i class="fas fa-archive"></i>
                        <h3><?php echo count(array_filter($requests, function($r) { return $r['status'] == 'Archived'; })); ?></h3>
                        <p>Archived Requests</p>
                    </div>
                </div>
            </div>

            <div class="requests-table animate__animated animate__fadeIn">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4 class="mb-0">
                        <i class="fas fa-file-alt me-2"></i>
                        Document Requests
                    </h4>
                    <div>
                        <button class="btn btn-danger me-2" id="bulkDeleteBtn">
                            <i class="fas fa-trash-alt me-1"></i> Delete Selected
                        </button>
                        <button class="btn btn-primary" id="refreshBtn">
                            <i class="fas fa-sync-alt me-1"></i> Refresh
                        </button>
                    </div>
                </div>
                
                <div class="row mb-4 g-3">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fas fa-search"></i></span>
                            <input type="text" id="searchInput" class="form-control" placeholder="Search requests...">
                            <button class="btn btn-outline-secondary" type="button" id="clearSearchBtn">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="row g-2">
                            <div class="col-md-4">
                                <select id="documentTypeFilter" class="form-select">
                                    <option value="all">All Documents</option>
                                    <option value="Diploma">DIPLOMA</option>
                                    <option value="Certificate of Completion of Kinder">CERTIFICATE OF COMPLETION OF KINDER</option>
                                    <option value="FORM 137-E">FORM 137-E</option>
                                    <option value="SF10">SF10</option>
                                    <option value="Good Moral">GOOD MORAL</option>
                                    <option value="Certificate Of Enrollment">CERTIFICATE OF ENROLLMENT</option>
                                    <option value="Grade 6 Completion">GRADE 6 COMPLETION</option>
                                    
                                    
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select id="statusFilter" class="form-select">
                                    <option value="all">All Statuses</option>
                                    <option value="Pending">Pending</option>
                                    <option value="Processing">Processing</option>
                                    <option value="Ready for Pickup">Ready</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <select id="priorityFilter" class="form-select">
                                    <option value="all">All Priorities</option>
                                    <option value="Urgent">Urgent</option>
                                    <option value="Normal">Normal</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="table-container">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th width="50"><input type="checkbox" id="selectAll"></th>
                                <th>Request ID</th>
                                <th>Student</th>
                                <th>Contact</th>
                                <th>Document</th>
                                <th>Priority</th>
                                <th>Status</th>
                                <th>ETA</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="requestsTableBody">
                            <?php foreach ($requests as $request): 
                                $isNew = strtotime($request['created_at']) > time() - 3600; // Within last hour
                            ?>
                            <tr class="request-row <?php echo $isNew ? 'new-request' : ''; ?>" 
                                data-id="<?php echo $request['id']; ?>"
                                data-status="<?php echo strtolower($request['status']); ?>" 
                                data-document-type="<?php echo strtolower($request['document_type']); ?>" 
                                data-priority="<?php echo strtolower($request['priority']); ?>">
                                <td><input type="checkbox" class="request-checkbox" value="<?php echo $request['id']; ?>"></td>
                                <td>#<?php echo $request['id']; ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar me-2">
                                            <?php 
                                            $initials = strtoupper(substr($request['firstname'], 0, 1) . substr($request['lastname'], 0, 1));
                                            $colors = ['#4361ee', '#3f37c9', '#4cc9f0', '#4895ef', '#f72585'];
                                            $color = $colors[array_rand($colors)];
                                            ?>
                                            <div class="avatar-initials" style="width:36px;height:36px;background-color:<?php echo $color; ?>;color:white;border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:600;">
                                                <?php echo $initials; ?>
                                            </div>
                                        </div>
                                        <div>
                                            <div class="fw-semibold"><?php echo $request['firstname'] . ' ' . $request['lastname']; ?></div>
                                            <small class="text-muted">LRN: <?php echo $request['lrn']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    
                                    <a href="tel:<?php echo $request['contact']; ?>" class="text-decoration-none">
                                        <i class="fas fa-phone me-1"></i> <?php echo $request['contact']; ?>
                                    </a>
                                </td>
                                <td><?php echo $request['document_type']; ?></td>
                                <td>
                                    <span class="badge bg-<?php echo $request['priority'] == 'Urgent' ? 'danger' : 'primary'; ?>">
                                        <?php echo $request['priority']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $request['status'])); ?>">
                                        <i class="fas fa-circle"></i> <?php echo $request['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($request['eta']): ?>
                                        <span class="d-flex align-items-center">
                                            <i class="far fa-calendar-alt me-1"></i>
                                            <?php echo date('M d, Y', strtotime($request['eta'])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
    <div class="d-flex gap-2">
    <?php if (strtolower($request['status']) === 'pending'): ?>
        <!-- Only display Update Status button if status is Pending -->
        <button class="btn btn-primary btn-action btn-sm update-btn" 
            data-bs-toggle="modal" 
            data-bs-target="#updateModal"
            data-id="<?php echo $request['id']; ?>"
            data-status="<?php echo $request['status']; ?>"
            data-eta="<?php echo $request['eta']; ?>"
            title="Update Status">
            <i class="fas fa-edit"></i> 
        </button>
    <?php else: ?>
        <!-- Update Status is always available -->
        <button class="btn btn-primary btn-action btn-sm update-btn" 
            data-bs-toggle="modal" 
            data-bs-target="#updateModal"
            data-id="<?php echo $request['id']; ?>"
            data-status="<?php echo $request['status']; ?>"
            data-eta="<?php echo $request['eta']; ?>"
            title="Update Status">
            <i class="fas fa-edit"></i>
        </button>
       
        <a href="download_document.php?request_id=<?php echo $request['id']; ?>" 
           class="btn btn-success btn-action btn-sm download-btn"
           title="Download Document">
            <i class="fas fa-download"></i>
        </a>
        <?php if (strtolower($request['document_type']) === 'diploma'): ?>
            <button class="btn btn-info btn-action btn-sm put-honors-btn"
                data-bs-toggle="modal"
                data-bs-target="#putHonorsModal"
                data-id="<?php echo $request['id']; ?>"
                data-honors="<?php echo htmlspecialchars($request['honors'] ?? ''); ?>"
                title="Put Honors">
                <i class="fas fa-award"></i>
            </button>
        <?php endif; ?>
    <?php endif; ?>
    </div>
</td>

                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mt-3">
                    <div class="text-muted">
                        Showing <span id="visibleCount"><?php echo count($requests); ?></span> of <?php echo $totalRecords; ?> requests
                    </div>
                    <nav>
                        <ul class="pagination mb-0">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>">Previous</a>
                            </li>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">Next</a>
                            </li>
                        </ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Request Modal -->
    <div class="modal fade" id="updateModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Student Status</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="updateRequestForm">
                    <div class="modal-body">
                        <input type="hidden" name="request_id" id="modalRequestId">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

                        <!-- Student Information -->
                        <div class="row mb-4" id="studentInfoContainer">
                            <div class="col-md-3 text-center">
                                <img id="studentPhoto" src="../uploads/default_profile.jpg" class="img-thumbnail mb-2" style="width:150px;height:150px;object-fit:cover;">
                            </div>
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label fw-bold">Full Name</label>
                                        <p class="form-control-static" id="studentName">Loading...</p>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label fw-bold">Grade Level</label>
                                        <p class="form-control-static" id="grade_level">Loading...</p>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label fw-bold">Contact Number</label>
                                        <p class="form-control-static" id="contact">Loading...</p>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label fw-bold">ULI Number</label>
                                        <p class="form-control-static" id="uli">Loading...</p>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label fw-bold">LRN</label>
                                        <p class="form-control-static" id="lrn">Loading...</p>
                                    </div>
                                    <div class="col-md-6 mb-2">
                                        <label class="form-label fw-bold">Birthday</label>
                                        <p class="form-control-static" id="dob">Loading...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status and ETA -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select name="status" id="modalStatus" class="form-select" data-current-status="Pending" required>
                                    <option value="Pending">Pending</option>
                                    <option value="Processing">Processing</option>
                                    <option value="Ready for Pickup">Ready for Pickup</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Estimated Completion Date</label>
                                <input type="date" name="eta" id="modalEta" class="form-control">
                            </div>
                        </div>

                        <!-- Decline Reason -->
                        <div class="form-group mb-3" id="declineReasonContainer" style="display:none;">
                            <label class="form-label">Reason for Decline <span class="text-danger">*</span></label>
                            <textarea name="decline_reason" class="form-control" rows="3" placeholder="Please specify the reason for declining this request..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>

                        <!-- Approve/Disapprove buttons -->
                        <div id="pendingActions" style="display:none;">
                            <button type="button" class="btn btn-success me-2" id="approveBtn">
                                <i class="fas fa-check me-1"></i> Approve
                            </button>
                            <button type="button" class="btn btn-danger" id="disapproveBtn">
                                <i class="fas fa-times me-1"></i> Disapprove
                            </button>
                        </div>

                        <!-- Regular update button -->
                        <button type="submit" class="btn btn-primary" id="regularUpdateBtn">
                            <i class="fas fa-save me-1"></i> Update Status
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
      


        // Status change handler - show/hide decline reason
        document.getElementById('modalStatus').addEventListener('change', function() {
            if (this.value === 'Declined') {
                document.getElementById('declineReasonContainer').style.display = 'block';
            } else {
                document.getElementById('declineReasonContainer').style.display = 'none';
            }
            
            // Handle pending status buttons
            if (this.value === 'Pending') {
                document.getElementById('pendingActions').style.display = 'block';
                document.getElementById('regularUpdateBtn').style.display = 'none';
            } else {
                document.getElementById('pendingActions').style.display = 'none';
                document.getElementById('regularUpdateBtn').style.display = 'block';
            }
        });

        // Approve button handler
   // Approve button handler - change status from Pending to Processing
// Approve button handler - change status from Pending to Processing
document.getElementById('approveBtn').addEventListener('click', function() {
    // Disable the button to prevent double execution
    this.disabled = true;

    const requestId = document.getElementById('modalRequestId').value;
    const eta = document.getElementById('modalEta').value;

    // Validate that we have a request ID and ETA
    if (!requestId) {
        Swal.fire('Error', 'Invalid request ID', 'error');
        this.disabled = false;
        return;
    }

    if (!eta) {
        Swal.fire('Validation Error', 'Please set an Estimated Completion Date (ETA) before approving.', 'warning');
        this.disabled = false;
        return;
    }

    // Prepare the form data
    const formData = new FormData();
    formData.append('request_id', requestId);
    formData.append('status', 'Processing'); // Set status to "Processing"
    formData.append('eta', eta);
    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

    // Send the request to update the status
    fetch('update_request.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Approved!',
                text: 'The request has been approved and is now being processed.',
                icon: 'success',
                confirmButtonColor: '#4361ee'
            }).then(() => {
                location.reload(); // Reload the page to reflect changes
            });
        } else {
            Swal.fire({
                title: 'Error!',
                text: data.message || 'Failed to approve the request.',
                icon: 'error',
                confirmButtonColor: '#f72585'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error!',
            text: 'There was a problem connecting to the server.',
            icon: 'error',
            confirmButtonColor: '#f72585'
        });
    })
    .finally(() => {
        this.disabled = false; // Re-enable the button
    });
});

        // Replace the existing disapprove button handler with this:
document.getElementById('disapproveBtn').addEventListener('click', function () {
    Swal.fire({
        title: 'Confirm Disapproval',
        html: `
            <div class="form-group">
                <label class="mb-2">Select reason for disapproval:</label>
                <select id="declineReason" class="form-select mb-3">
                    <option value="">Select a reason...</option>
                    <option value="Incomplete Requirements">Incomplete Requirements</option>
                    <option value="Invalid Document Request">Invalid Document Request</option>
                    <option value="Student Record Not Found">Student Record Not Found</option>
                    <option value="Incorrect Information">Incorrect Information</option>
                    <option value="Already Issued">Already Issued</option>
                    <option value="other">Other (Please specify)</option>
                </select>
                <textarea id="otherReason" class="form-control mt-3" 
                    placeholder="Please specify the reason..." 
                    style="display: none;"></textarea>
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Submit',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#f72585',
        didRender: () => {
            // Add change event listener to dropdown
            document.getElementById('declineReason').addEventListener('change', function() {
                const otherReason = document.getElementById('otherReason');
                otherReason.style.display = this.value === 'other' ? 'block' : 'none';
            });
        },
        preConfirm: () => {
            const selectedReason = document.getElementById('declineReason').value;
            const otherReason = document.getElementById('otherReason').value;
            
            if (!selectedReason) {
                Swal.showValidationMessage('Please select a reason');
                return false;
            }
            
            if (selectedReason === 'other' && !otherReason.trim()) {
                Swal.showValidationMessage('Please specify the reason');
                return false;
            }
            
            return selectedReason === 'other' ? otherReason : selectedReason;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            const formData = new FormData();
            formData.append('request_id', document.getElementById('modalRequestId').value);
            formData.append('status', 'Declined');
            formData.append('decline_reason', result.value);
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

            // Add the current date as processed_date
            const currentDate = new Date().toISOString().split('T')[0];
            formData.append('processed_date', currentDate);

            fetch('update_request.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let message = 'Request has been disapproved.';
                    if (data.sms_sent) {
                        message += ' SMS notification sent successfully.';
                    }
                    Swal.fire({
                        title: 'Disapproved!',
                        text: 'Request has been disapproved and moved to history.',
                        icon: 'success',
                        confirmButtonColor: '#4361ee'
                    }).then(() => {
                        // Redirect to history page
                        window.location.href = 'history.php';
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message || 'Failed to disapprove the request.',
                        icon: 'error',
                        confirmButtonColor: '#f72585'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'There was a problem connecting to the server.',
                    text: 'There was a problem connecting to the server.',
                    icon: 'error',
                    confirmButtonColor: '#f72585'
                });
            });
        }
    });
});

        // Update request form handler with SMS notification
        document.getElementById('updateRequestForm').addEventListener('submit', function (e) {
            const currentStatus = document.getElementById('modalStatus').getAttribute('data-current-status');
            const newStatus = document.getElementById('modalStatus').value;

            const statusOrder = ['Pending', 'Processing', 'Ready for Pickup', 'Completed'];
            const currentStatusIndex = statusOrder.indexOf(currentStatus);
            const newStatusIndex = statusOrder.indexOf(newStatus);

            if (newStatusIndex < currentStatusIndex) {
                e.preventDefault();
                Swal.fire('Validation Error', 'Cannot revert to a previous status.', 'warning');
                return;
            }

            e.preventDefault(); // Prevent default form submission

            const formData = new FormData(document.getElementById('updateRequestForm'));
            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

            fetch('update_request.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log(data); // For debugging: check what is returned
                if (data.success) {
                    let infoMsg = 'Request updated successfully.';
                    if (data.sms_sent) {
                        Swal.fire({
                            title: 'SMS Sent!',
                            text: 'Your SMS notification has been sent successfully.',
                            icon: 'success',
                            confirmButtonColor: '#4361ee'
                        });
                    }
                    Swal.fire({
                        title: 'Success!',
                        text: infoMsg,
                        icon: 'success',
                        confirmButtonColor: '#4361ee'
                    }).then(() => {
                        // Reload page or redirect as needed
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message || 'Failed to update request.',
                        icon: 'error',
                        confirmButtonColor: '#f72585'
                    });
                }
            })
            .catch(error => {
                console.error('Fetch error:', error);
                Swal.fire({
                    title: 'Error!',
                    text: 'There was a problem connecting to the server.',
                    icon: 'error',
                    confirmButtonColor: '#f72585'
                });
            });
        });

        // Tooltips for buttons
        $(function () {
            $('[data-toggle="tooltip"]').tooltip();
            $('.btn-action').tooltip();
            
            // Add tooltips to action buttons
            $('.update-btn').attr('title', 'Update status').tooltip();
            $('.preview-btn').attr('title', 'Preview document').tooltip();
            $('.download-btn').attr('title', 'Download document').tooltip();
           
        });

        // CSRF token generation
        if (!sessionStorage.getItem('csrf_token')) {
            const randomToken = Math.random().toString(36).substring(2, 15) + 
                                Math.random().toString(36).substring(2, 15);
            sessionStorage.setItem('csrf_token', randomToken);
        }

        // Fetch and populate student information in modals
        document.querySelectorAll('.btn-action').forEach(button => {
            button.addEventListener('click', function () {
                const requestId = this.getAttribute('data-id');

                // Fetch student information; note that even if there’s an error, we simply log it
                fetch(`get_student_info.php?request_id=${requestId}`)
                    .then(response => {
                        if (!response.ok) {
                            console.error(`HTTP error: ${response.status}`);
                            // Return a safe response
                            return { success: true, data: {
                                firstname: 'N/A',
                                lastname: '',
                                grade_level: 'N/A',
                                contact: 'N/A',
                                uli: 'N/A',
                                lrn: 'N/A',
                                dob: 'N/A',
                                picture: ''
                            }};
                        }
                        return response.json();
                    })
                    .then(data => {
                        // Use default values if no student data is returned
                        const student = (data && data.data) || {
                            firstname: 'N/A',
                            middlename: '',
                            lastname: '',
                            extensionname: '',
                            grade_level: 'N/A',
                            contact: 'N/A',
                            uli: 'N/A',
                            lrn: 'N/A',
                            dob: 'N/A',
                            picture: ''
                        };
                        
                        // Populate modal fields without showing a popup on error
                        document.getElementById('studentName').textContent = getFullName(student);
                        document.getElementById('grade_level').textContent = student.grade_level || 'N/A';
                        document.getElementById('contact').textContent = student.contact || 'N/A';
                        document.getElementById('uli').textContent = student.uli || 'N/A';
                        document.getElementById('lrn').textContent = student.lrn || 'N/A';
                        document.getElementById('dob').textContent = student.dob ? new Date(student.dob).toLocaleDateString() : 'N/A';
                        
                        const photoElement = document.getElementById('studentPhoto');
                        if (photoElement) {
                            photoElement.src = student.picture ? `../uploads/${student.picture}` : '../uploads/default_profile.jpg';
                        }
                    })
                    .catch(error => {
                        console.error('Error loading student info:', error);
                        // Fallback: assign safe values (without triggering any UI popup)
                        document.getElementById('studentName').textContent = 'N/A';
                        document.getElementById('grade_level').textContent = 'N/A';
                        document.getElementById('contact').textContent = 'N/A';
                        document.getElementById('uli').textContent = 'N/A';
                        document.getElementById('lrn').textContent = 'N/A';
                        document.getElementById('dob').textContent = 'N/A';
                    });
            });
        });

        // Bulk delete functionality
        document.getElementById('bulkDeleteBtn').addEventListener('click', function () {
            const selectedIds = Array.from(document.querySelectorAll('.request-checkbox:checked'))
                .map(checkbox => checkbox.value);

            if (selectedIds.length === 0) {
                Swal.fire({
                    title: 'No Selection',
                    text: 'Please select at least one request to delete',
                    icon: 'warning',
                    confirmButtonColor: '#4361ee'
                });
                return;
            }

            Swal.fire({
                title: 'Confirm Deletion',
                text: `Are you sure you want to delete ${selectedIds.length} selected requests?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#f72585',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete them'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'bulk_delete');
                    formData.append('request_ids', JSON.stringify(selectedIds));
                    formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

                    fetch('request_actions.php', {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    title: 'Deleted!',
                                    text: `${selectedIds.length} requests have been deleted`,
                                    icon: 'success',
                                    confirmButtonColor: '#4361ee'
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    title: 'Error!',
                                    text: data.message || 'Failed to delete requests',
                                    icon: 'error',
                                    confirmButtonColor: '#f72585'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                title: 'Error!',
                                text: 'There was a problem connecting to the server',
                                icon: 'error',
                                confirmButtonColor: '#f72585'
                            });
                        });
                    }
                });
            });

            // Receive request button handler
            const receiveButtons = document.querySelectorAll('.receive-btn');
            receiveButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const requestId = this.getAttribute('data-id');

                    Swal.fire({
                        title: 'Confirm Receipt',
                        text: 'Mark this document as received by the student?',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#4361ee',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Yes, confirm receipt'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            const formData = new FormData();
                            formData.append('receive_request_id', requestId);
                            formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

                            fetch('admin_dashboard.php', {
                                method: 'POST',
                                body: formData
                            })
                            .then(response => response.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire({
                                        title: 'Success!',
                                        text: 'Document marked as received.',
                                        icon: 'success',
                                        confirmButtonColor: '#4361ee'
                                    }).then(() => {
                                        location.reload(); // Reload the page to reflect changes
                                    });
                                } else {
                                    Swal.fire({
                                        title: 'Error!',
                                        text: data.message || 'Failed to update status.',
                                        icon: 'error',
                                        confirmButtonColor: '#f72585'
                                    });
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                Swal.fire({
                                    title: 'Error!',
                                    text: 'There was a problem connecting to the server.',
                                    icon: 'error',
                                    confirmButtonColor: '#f72585'
                                });
                            });
                        }
                    });
                });
            });

// Update modal handlers with student information
// Replace the existing updateButtons event listener with this improved version
// Update modal handlers with student information
// Update modal handlers with student information
const updateButtons = document.querySelectorAll('.update-btn');
updateButtons.forEach(button => {
    button.addEventListener('click', function() {
        const requestId = this.getAttribute('data-id');
        const status = this.getAttribute('data-status');
        const eta = this.getAttribute('data-eta');

        // Set modal fields
        document.getElementById('modalRequestId').value = requestId;
        document.getElementById('modalStatus').value = status;
        document.getElementById('modalStatus').setAttribute('data-current-status', status);
        document.getElementById('modalEta').value = eta;

        // Show/hide Approve and Disapprove buttons based on current status
        if (status === 'Pending') {
            document.getElementById('pendingActions').style.display = 'block';
            document.getElementById('regularUpdateBtn').style.display = 'none';
        } else {
            document.getElementById('pendingActions').style.display = 'none';
            document.getElementById('regularUpdateBtn').style.display = 'block';
        }

        // Filter the status dropdown options based on current status
        const statusSelect = document.getElementById('modalStatus');
        const currentStatus = status;
        const validNextStatuses = {
            'Pending': ['Processing'],
            'Processing': ['Ready for Pickup'],
            'Ready for Pickup': ['Completed']
        };

        // Enable/disable options based on current status
        Array.from(statusSelect.options).forEach(option => {
            if (option.value === currentStatus || 
                (validNextStatuses[currentStatus] && validNextStatuses[currentStatus].includes(option.value))) {
                option.disabled = false;
            } else {
                option.disabled = true;
            }
        });
    });
});
        </script>

        <!-- Scripts -->
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/limonte-sweetalert2/11.7.5/sweetalert2.all.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // CSRF token generation
                if (!sessionStorage.getItem('csrf_token')) {
                    const randomToken = Math.random().toString(36).substring(2, 15) + 
                                        Math.random().toString(36).substring(2, 15);
                    sessionStorage.setItem('csrf_token', randomToken);
                }
                
              
              
                
                // Search and filter functionality
                const searchInput = document.getElementById('searchInput');
                const clearSearchBtn = document.getElementById('clearSearchBtn');
                const documentTypeFilter = document.getElementById('documentTypeFilter');
                const statusFilter = document.getElementById('statusFilter');
                const priorityFilter = document.getElementById('priorityFilter');
                const visibleCountEl = document.getElementById('visibleCount');
                
                function applyFilters() {
                    const searchTerm = searchInput.value.toLowerCase();
                    const documentType = documentTypeFilter.value.toLowerCase();
                    const status = statusFilter.value.toLowerCase();
                    const priority = priorityFilter.value.toLowerCase();
                    
                    const rows = document.querySelectorAll('.request-row');
                    let visibleCount = 0;
                    
                    rows.forEach(row => {
                        const rowDocType = row.getAttribute('data-document-type').toLowerCase();
                        const rowStatus = row.getAttribute('data-status').toLowerCase();
                        const rowPriority = row.getAttribute('data-priority').toLowerCase();
                        const text = row.textContent.toLowerCase();
                        
                        const matchesSearch = searchTerm === '' || text.includes(searchTerm);
                        const matchesDocType = documentType === 'all' || rowDocType.includes(documentType);
                        const matchesStatus = status === 'all' || rowStatus === status;
                        const matchesPriority = priority === 'all' || rowPriority === priority;
                        
                        if (matchesSearch && matchesDocType && matchesStatus && matchesPriority) {
                            row.style.display = '';
                            visibleCount++;
                        } else {
                            row.style.display = 'none';
                        }
                    });
                    
                    visibleCountEl.textContent = visibleCount;
                }
                
                searchInput.addEventListener('input', applyFilters);
                clearSearchBtn.addEventListener('click', function() {
                    searchInput.value = '';
                    applyFilters();
                });
                documentTypeFilter.addEventListener('change', applyFilters);
                statusFilter.addEventListener('change', applyFilters);
                priorityFilter.addEventListener('change', applyFilters);
                
                // Filter requests by status from stat cards
                window.filterRequests = function(status) {
                    statusFilter.value = status;
                    applyFilters();
                };
                
                // Select all checkboxes
                const selectAllCheckbox = document.getElementById('selectAll');
                const requestCheckboxes = document.querySelectorAll('.request-checkbox');
                
                selectAllCheckbox.addEventListener('change', function() {
                    requestCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
                
                // Bulk delete
                document.getElementById('bulkDeleteBtn').addEventListener('click', function () {
                    const selectedIds = Array.from(document.querySelectorAll('.request-checkbox:checked'))
                        .map(checkbox => checkbox.value);

                    if (selectedIds.length === 0) {
                        Swal.fire({
                            title: 'No Selection',
                            text: 'Please select at least one request to delete',
                            icon: 'warning',
                            confirmButtonColor: '#4361ee'
                        });
                        return;
                    }
                Swal.fire({
                    title: 'Confirm Deletion',
                    text: `Are you sure you want to delete ${selectedIds.length} selected requests?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#f72585',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, delete them'
                }).then((result) => {
                    if (result.isConfirmed) {
                        const formData = new FormData();
                        formData.append('action', 'bulk_delete');
                        formData.append('request_ids', JSON.stringify(selectedIds));
                        formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

                        fetch('request_actions.php', {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error(`HTTP error! status: ${response.status}`);
                                }
                                return response.json();
                            })
                            .then(data => {
                                if (data.success) {
                                    Swal.fire({
                                        title: 'Deleted!',
                                        text: `${selectedIds.length} requests have been deleted`,
                                        icon: 'success',
                                        confirmButtonColor: '#4361ee'
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire({
                                        title: 'Error!',
                                        text: data.message || 'Failed to delete requests',
                                        icon: 'error',
                                        confirmButtonColor: '#f72585'
                                    });
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                Swal.fire({
                                    title: 'Error!',
                                    text: 'There was a problem connecting to the server',
                                    icon: 'error',
                                    confirmButtonColor: '#f72585'
                                });
                            });
                    }
                });
            });
            
            // Refresh button
            document.getElementById('refreshBtn').addEventListener('click', function() {
                location.reload();
            });
            
            // Logout button
            document.getElementById('logoutBtn').addEventListener('click', function() {
                Swal.fire({
                    title: 'Logout',
                    text: 'Are you sure you want to logout?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#4361ee',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, logout'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'login_admin.php';
                    }
                });
            });
        });
    </script>
    <script>
// Add this validation to the status change handler in admin_dashboard.php
// Replace the existing status change handler with this:
// Add this to your update modal handler
document.getElementById('modalStatus').addEventListener('change', function() {
    const currentStatus = this.getAttribute('data-current-status');
    const newStatus = this.value;
    
    const validTransitions = {
        'Pending': ['Processing', 'Declined'],
        'Processing': ['Ready for Pickup'],
        'Ready for Pickup': ['Completed']
    };
    
    if (!validTransitions[currentStatus] || !validTransitions[currentStatus].includes(newStatus)) {
        Swal.fire({
            title: 'Invalid Status Change',
            text: `You can only change from ${currentStatus} to: ${validTransitions[currentStatus].join(', ')}`,
            icon: 'warning'
        });
        this.value = currentStatus;
    }
});

// Update the form submission handler to include status validation

    
    // Define valid status progression
    const validTransitions = {
        'Pending': ['Processing'],
        'Processing': ['Ready for Pickup'],
        'Ready for Pickup': ['Completed']
    };
    
    // Validate the status change
    if (!validTransitions[currentStatus] || !validTransitions[currentStatus].includes(newStatus)) {
        Swal.fire({
            title: 'Invalid Status Change',
            text: 'You must follow the sequence: Pending → Processing → Ready for Pickup → Completed',
            icon: 'warning',
            confirmButtonColor: '#4361ee'
        });
        return false;
    }

    // Continue with form submission if validation passes
    const formData = new FormData(this);
    
    fetch('update_request.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                title: 'Success!',
                text: 'Status updated successfully',
                icon: 'success',
                confirmButtonColor: '#4361ee'
            }).then(() => {
                if (newStatus === 'Completed') {
                    window.location.href = 'history.php';
                } else {
                    location.reload();
                }
            });
        } else {
            Swal.fire({
                title: 'Error!',
                text: data.message || 'Failed to update status',
                icon: 'error',
                confirmButtonColor: '#f72585'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            title: 'Error!',
            text: 'There was a problem connecting to the server',
            icon: 'error',
            confirmButtonColor: '#f72585'
        });
    });
});
</script>
<script>
// Sidebar-specific JavaScript
document.querySelectorAll('.sidebar-action').forEach(button => {
    button.addEventListener('click', function () {
        // Sidebar-specific logic
    });
});

// Sidebar-specific logic
document.querySelectorAll('.sidebar-link').forEach(link => {
    link.addEventListener('click', function () {
        // Sidebar-specific actions
    });
});
</script>

<!-- Edit Document Modal -->


<script>
// Add this JavaScript code in your existing script section

// Edit document button handler

</script>
<script>

</script>
<script>

</script>
  
<!-- Put Honors Modal -->
<div class="modal fade" id="putHonorsModal" tabindex="-1" aria-labelledby="putHonorsModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <!-- Use action="#" to avoid traditional submission -->
      <form id="putHonorsForm" method="POST" action="#">
        <div class="modal-header">
          <h5 class="modal-title" id="putHonorsModalLabel">Put Honors (Diploma Request)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="request_id" id="honorsRequestId">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
          <div class="mb-3">
            <label for="honorsSelect" class="form-label">Select Honors Level</label>
            <select name="honors" id="honorsSelect" class="form-select" required>
              <option value="">Select an honors level</option>
              <option value="With Honors">With Honors</option>
              <option value="With High Honors">With High Honors</option>
              <option value="With Highest Honors">With Highest Honors</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary">Save Honors</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>

</script>

<?php
// Update the status options in the modal and add status validation
function getNextAllowedStatuses($currentStatus) {
    $statusFlow = [
        'Pending' => ['Processing'], // Pending can only move to Processing
        'Processing' => ['Ready for Pickup'], // Processing can only move to Ready for Pickup
        'Ready for Pickup' => ['Completed'], // Ready for Pickup can only move to Completed
        'Completed' => [] // Completed is final state
    ];
    
    return $statusFlow[$currentStatus] ?? [];
}
?>
<script>

</script>
</body>
</html>

<script>
// Replace the existing putHonorsForm event listener with this improved version

// Add event listeners to all honor buttons
document.querySelectorAll('.put-honors-btn').forEach(button => {
    button.addEventListener('click', function() {
        const requestId = this.getAttribute('data-id');
        const honors = this.getAttribute('data-honors') || '';
        
        // Set the form values
        document.getElementById('honorsRequestId').value = requestId;
        document.getElementById('honorsSelect').value = honors;
        
        // Initialize and show the modal
        const modal = new bootstrap.Modal(document.getElementById('putHonorsModal'));
        modal.show();
        
        // Reset the form state when modal is shown
        const form = document.getElementById('putHonorsForm');
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Save Honors';
    });
});

// Function to properly clean up the modal
function cleanupModal() {
    // Remove modal-related classes from body
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    
    // Remove any modal backdrops
    const modalBackdrops = document.getElementsByClassName('modal-backdrop');
    while(modalBackdrops.length > 0){
        modalBackdrops[0].parentNode.removeChild(modalBackdrops[0]);
    }
}

// Add event listener for modal close and dismiss buttons
document.querySelectorAll('#putHonorsModal .btn-close, #putHonorsModal .close, #putHonorsModal [data-bs-dismiss="modal"]').forEach(button => {
    button.addEventListener('click', function() {
        setTimeout(cleanupModal, 300);
    });
});

// Handle ESC key and backdrop clicks
document.getElementById('putHonorsModal').addEventListener('hidden.bs.modal', function() {
    setTimeout(cleanupModal, 300);
});

// Form submission handler
document.getElementById('putHonorsForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Get references to elements we'll need
    const form = this;
    const submitBtn = form.querySelector('button[type="submit"]');
    const modalEl = document.getElementById('putHonorsModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    const requestId = document.getElementById('honorsRequestId').value;
    const updatedHonors = document.getElementById('honorsSelect').value;
    
    // Disable the submit button to prevent double submission
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving...';
    
    // Use standard fetch instead of async/await
    const formData = new FormData(form);
    fetch('update_honors.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        // First, close the modal completely
        if (modal) {
            modal.hide();
            cleanupModal();
        }
        
        // Update UI elements
        const putButton = document.querySelector(`.put-honors-btn[data-id="${requestId}"]`);
        if (putButton) {
            putButton.setAttribute('data-honors', updatedHonors);
        }
        
        // Reset form
        form.reset();
        
        // Check success and show appropriate message
        if (data.success) {
            // After everything is cleaned up, show success message
            setTimeout(() => {
                Swal.fire({
                    title: 'Success!',
                    text: 'Honors updated successfully.',
                    icon: 'success',
                    confirmButtonColor: '#4361ee',
                    willClose: () => {
                        // Ensure clean state after alert closes
                        document.body.style.overflow = '';
                        document.body.style.paddingRight = '';
                    }
                });
            }, 300);
        } else {
            setTimeout(() => {
                Swal.fire({
                    title: 'Error!',
                    text: data.message || 'Failed to update honors.',
                    icon: 'error',
                    confirmButtonColor: '#f72585'
                });
            }, 300);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Close modal if still open
        if (modal) {
            modal.hide();
            cleanupModal();
        }
        
        setTimeout(() => {
            Swal.fire({
                title: 'Error!',
                text: 'There was a problem connecting to the server.',
                icon: 'error',
                confirmButtonColor: '#f72585'
            });
        }, 300);
    })
    .finally(() => {
        // Always re-enable the button
        submitBtn.disabled = false;
        submitBtn.innerHTML = 'Save Honors';
    });
});

// Helper function to build student's full name
function getFullName(student) {
    let fullName = student.firstname;
    if (student.middlename && student.middlename.toLowerCase() !== 'n/a') {
        fullName += ' ' + student.middlename;
    }
    fullName += ' ' + student.lastname;
    if (student.extensionname && student.extensionname.toLowerCase() !== 'n/a') {
        fullName += ' ' + student.extensionname;
    }
    return fullName;
}

// When fetching and setting student info in the update modal:
document.querySelectorAll('.btn-action').forEach(button => {
    button.addEventListener('click', function () {
        const requestId = this.getAttribute('data-id');
        
        fetch(`get_student_info.php?request_id=${requestId}`)
            .then(response => response.json())
            .then(data => {
                // Use default values if no student data is returned
                const student = (data && data.data) || {
                    firstname: 'N/A',
                    middlename: '',
                    lastname: '',
                    extensionname: '',
                    grade_level: 'N/A',
                    contact: 'N/A',
                    uli: 'N/A',
                    lrn: 'N/A',
                    dob: 'N/A',
                    picture: ''
                };

                // Set the full name using the helper getFullName function
                document.getElementById('studentName').textContent = getFullName(student);
                document.getElementById('grade_level').textContent = student.grade_level || 'N/A';
                document.getElementById('contact').textContent = student.contact || 'N/A';
                document.getElementById('uli').textContent = student.uli || 'N/A';
                document.getElementById('lrn').textContent = student.lrn || 'N/A';
                document.getElementById('dob').textContent = student.dob ? new Date(student.dob).toLocaleDateString() : 'N/A';

                const photoElement = document.getElementById('studentPhoto');
                if (photoElement) {
                    photoElement.src = student.picture ? `../uploads/${student.picture}` : '../uploads/default_profile.jpg';
                }
            })
            .catch(error => {
                console.error('Error loading student info:', error);
                // Fallback: assign safe values
                document.getElementById('studentName').textContent = 'N/A';
                document.getElementById('grade_level').textContent = 'N/A';
                document.getElementById('contact').textContent = 'N/A';
                document.getElementById('uli').textContent = 'N/A';
                document.getElementById('lrn').textContent = 'N/A';
                document.getElementById('dob').textContent = 'N/A';
            });
    });
});

// Test SMS function

</script>