<?php
session_start();
include '../../Connection/database.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}

$user_id = $_SESSION['user_id'];

$purpose = trim($_POST['purpose'] ?? '');
$school_year_start = trim($_POST['school_year_start'] ?? '');
$school_year_end = trim($_POST['school_year_end'] ?? '');
$priority = trim($_POST['priority'] ?? '');

if (empty($purpose) || empty($school_year_start) || empty($school_year_end) || empty($priority)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit();
}

$query = "INSERT INTO requests (user_id, document_type, purpose, school_year_start, school_year_end, priority, status, created_at)
          VALUES (?, 'Certificate of Enrollment', ?, ?, ?, ?, 'Pending', NOW())";
$stmt = $conn->prepare($query);

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit();
}

$stmt->bind_param('issss', $user_id, $purpose, $school_year_start, $school_year_end, $priority);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Your certificate of enrollment request has been submitted successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit request: ' . $stmt->error]);
}

exit();
?>