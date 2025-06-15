<?php
// filepath: c:\xampp\htdocs\CAPSTONE\admin\mark_as_received.php

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session
session_start();

// Include database connection
require '../Connection/database.php';

// Check if the request is valid
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token.']);
        exit;
    }

    // Validate request ID
    $request_id = filter_var($_POST['receive_request_id'], FILTER_VALIDATE_INT);
    if (!$request_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid request ID.']);
        exit;
    }

    // Update the request status in the database
    $query = "UPDATE requests SET status = 'Received', received_date = NOW() WHERE id = ?";
    $stmt = $conn->prepare($query);

    if ($stmt) {
        $stmt->bind_param('i', $request_id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Request marked as received.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update the request.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to prepare the query.']);
    }
    exit;
}

// If the request is not valid, return an error
echo json_encode(['success' => false, 'message' => 'Invalid request.']);
exit;