<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Debugging setup
file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] Script started\n", FILE_APPEND);

require '../Connection/database.php';
if (!$conn) {
    $error = "Database connection failed: " . $conn->connect_error;
    file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] $error\n", FILE_APPEND);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

// Include SMS functions
require_once 'sms_functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $debugInfo = [
            'received_token' => $_POST['csrf_token'] ?? null,
            'expected_token' => $_SESSION['csrf_token'] ?? null
        ];
        file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] Invalid CSRF token: " . json_encode($debugInfo) . "\n", FILE_APPEND);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid CSRF token',
            'debug' => $debugInfo
        ]);
        exit;
    }

    // Validate required fields
    $request_id = filter_var($_POST['request_id'], FILTER_VALIDATE_INT);
    $status = $_POST['status'] ?? null;
    $eta = $_POST['eta'] ?? null;
    $decline_reason = $_POST['decline_reason'] ?? null;

    if (!$request_id) {
        file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] Request ID is missing\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Request ID is required']);
        exit;
    }

    if (!$status) {
        file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] Status is missing\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Status is required']);
        exit;
    }

    // Get current request status
    $query = "SELECT status, user_id FROM requests WHERE id = ?";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        $error = "Failed to prepare statement for status check: " . $conn->error;
        file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] $error\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare database query']);
        exit;
    }

    $stmt->bind_param('i', $request_id);
    if (!$stmt->execute()) {
        $error = "Failed to execute status check: " . $stmt->error;
        file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] $error\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Failed to check request status']);
        exit;
    }

    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] Request not found for ID: $request_id\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Request not found']);
        exit;
    }

    $current_request = $result->fetch_assoc();
    $current_status = $current_request['status'];
    $user_id = $current_request['user_id'];

    // Validate status transition
    $validStatuses = ['Pending', 'Processing', 'Ready for Pickup', 'Completed', 'Declined'];
    if (!in_array($status, $validStatuses)) {
        file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] Invalid status value: $status\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Invalid status value']);
        exit;
    }

    // Status transition validation
    $status_order = ['Pending', 'Processing', 'Ready for Pickup', 'Completed'];
    $current_status_index = array_search($current_status, $status_order);
    $new_status_index = array_search($status, $status_order);

    if ($new_status_index !== false && $current_status_index !== false && $new_status_index < $current_status_index) {
        file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] Status reversion attempt: current=$current_status, new=$status\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Cannot revert to a previous status']);
        exit;
    }

    // Additional validation for specific statuses
    if ($status === 'Declined' && empty($decline_reason)) {
        file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] Decline reason is missing\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Reason for Decline is required']);
        exit;
    }

    if ($status !== 'Declined' && empty($eta)) {
        file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] ETA is missing for status: $status\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Estimated Completion Date (ETA) is required']);
        exit;
    }

    // Update the request in the database
    $query = "UPDATE requests SET status = ?, eta = ?, decline_reason = ?, processed_date = NOW() WHERE id = ?";
    file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] Preparing SQL Query: $query\n", FILE_APPEND);

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        $error = "Failed to prepare update statement: " . $conn->error;
        file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] $error\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Failed to prepare database query']);
        exit;
    }

    $stmt->bind_param('sssi', $status, $eta, $decline_reason, $request_id);

    if ($stmt->execute()) {
        file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] Request updated successfully\n", FILE_APPEND);
        
        // Send SMS notification
        $smsSent = sendStatusNotification($request_id, $status, $conn);
        
        // Log SMS result
        file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] SMS notification " . ($smsSent ? "sent successfully" : "failed to send") . "\n", FILE_APPEND);
        
        echo json_encode([
            'success' => true,
            'message' => 'Request updated successfully',
            'sms_sent' => $smsSent
        ]);
    } else {
        $error = "Failed to execute update query: " . $stmt->error;
        file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] $error\n", FILE_APPEND);
        echo json_encode(['success' => false, 'message' => 'Failed to update request']);
    }
    
    $stmt->close();
    exit;
}

file_put_contents('debug.log', "[" . date('Y-m-d H:i:s') . "] Invalid request method: " . $_SERVER['REQUEST_METHOD'] . "\n", FILE_APPEND);
echo json_encode(['success' => false, 'message' => 'Invalid request method']);
?>