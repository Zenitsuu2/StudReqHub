<?php
session_start();
require_once '../Connection/database.php';

$user_id = $_SESSION['user_id'];

// Fetch the latest request information
$query = "SELECT status, eta FROM requests WHERE user_id = ? ORDER BY created_at DESC LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result === false) {
    echo json_encode(['success' => false, 'message' => 'Error executing query']);
    exit();
}

$request = $result->fetch_assoc();

if ($request) {
    echo json_encode([
        'success' => true,
        'status' => $request['status'],
        'eta' => $request['eta'] ? date('M d, Y', strtotime($request['eta'])) : null
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'No request found']);
}
?>