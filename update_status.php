<?php
include '../Connection/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$request_id = $_POST['request_id'] ?? null;
$status = $_POST['status'] ?? null;
$eta = $_POST['eta'] ?? null;

if (!$request_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

// Update the request in the database
$query = "UPDATE requests SET status = ?, eta = ?, updated_at = NOW() WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ssi", $status, $eta, $request_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Request updated successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update request']);
}