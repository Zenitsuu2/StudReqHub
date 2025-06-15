<?php
// update_event.php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Ensure this is only accessible to admins
if (!isset($_SESSION['admin'])) {
    die(json_encode(['error' => 'Unauthorized access']));
}

include __DIR__ . '/../Connection/database.php';

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['error' => 'Invalid request method']));
}

// Get JSON data from the request
$data = json_decode(file_get_contents('php://input'), true);

// Validate required fields
if (!isset($data['id']) || !isset($data['start_date']) || !isset($data['end_date'])) {
    die(json_encode(['error' => 'Missing required fields']));
}

// Clean and validate the data
$id = intval($data['id']);
$start_date = date('Y-m-d H:i:s', strtotime($data['start_date']));
$end_date = date('Y-m-d H:i:s', strtotime($data['end_date']));

// Update the event in the database
$query = "UPDATE events SET start_date = ?, end_date = ? WHERE id = ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    die(json_encode(['error' => 'Prepare failed: ' . $conn->error]));
}

$stmt->bind_param('ssi', $start_date, $end_date, $id);

if (!$stmt->execute()) {
    die(json_encode(['error' => 'Execute failed: ' . $stmt->error]));
}

// Return success response
echo json_encode(['success' => true]);
?>