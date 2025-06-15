<?php
// delete_event.php
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
if (!isset($data['id'])) {
    die(json_encode(['error' => 'Missing event ID']));
}

// Clean and validate the data
$id = intval($data['id']);

// First, check if the event has an invitation file that needs to be deleted
$query = "SELECT invitation_file FROM events WHERE id = ? AND has_invitation = 1";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    if (!empty($row['invitation_file'])) {
        $file_path = __DIR__ . '/../uploads/invitations/' . $row['invitation_file'];
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
}

// Delete the event from the database
$query = "DELETE FROM events WHERE id = ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    die(json_encode(['error' => 'Prepare failed: ' . $conn->error]));
}

$stmt->bind_param('i', $id);

if (!$stmt->execute()) {
    die(json_encode(['error' => 'Execute failed: ' . $stmt->error]));
}

//