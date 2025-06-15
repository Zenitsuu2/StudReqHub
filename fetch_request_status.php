<?php
// filepath: c:\xampp\htdocs\CAPSTONE\user\fetch_request_status.php
session_start();
include '../Connection/database.php';

// Get the current user's ID from the session
$user_id = $_SESSION['user_id'];

// Fetch the latest request status for the current user
$query = "SELECT status FROM requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $request = $result->fetch_assoc();
    echo json_encode(['status' => $request['status']]);
} else {
    echo json_encode(['status' => null]);
}
?>