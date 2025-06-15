<?php
session_start();
if (!isset($_SESSION['admin'])) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

include '../Connection/database.php';

if (!isset($_GET['request_id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Request ID is required');
}

$request_id = $_GET['request_id'];
$query = "SELECT status, eta FROM requests WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('HTTP/1.1 404 Not Found');
    exit('Request not found');
}

$request = $result->fetch_assoc();
header('Content-Type: application/json');
echo json_encode($request);