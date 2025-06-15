<?php
require_once '../Connection/database.php';

if (!isset($_GET['id'])) {
    die(json_encode(['error' => 'Activity ID is required']));
}

$id = $_GET['id'];
$query = "SELECT a.*, CONCAT(u.firstname, ' ', u.lastname) AS student_name, u.lrn
          FROM activity_logs a
          JOIN users u ON a.user_id = u.id
          WHERE a.id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();
$activity = $result->fetch_assoc();

echo json_encode($activity);