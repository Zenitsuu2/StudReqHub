<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session and check authentication
session_start();
if (!isset($_SESSION['admin'])) {
    header('HTTP/1.1 403 Forbidden');
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

// Check CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    header('HTTP/1.1 403 Forbidden');
    die(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
}

// Validate input
if (!isset($_POST['request_id']) || !isset($_POST['honors'])) {
    header('HTTP/1.1 400 Bad Request');
    die(json_encode(['success' => false, 'message' => 'Missing required fields']));
}

// Database connection
require '../Connection/database.php';

try {
    $stmt = $conn->prepare("UPDATE requests SET honors = ? WHERE id = ?");
    $stmt->bind_param('si', $_POST['honors'], $_POST['request_id']);
    
    if ($stmt->execute()) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Honors updated successfully']);
    } else {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['success' => false, 'message' => 'Database update failed']);
    }
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Close connections
$stmt->close();
$conn->close();
exit();
?>