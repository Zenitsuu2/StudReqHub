<?php
header('Content-Type: application/json'); // Ensure the response is JSON
session_start();

require '../Connection/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF protection
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

    // Check if the action is bulk delete
    if (isset($_POST['action']) && $_POST['action'] === 'bulk_delete') {
        $request_ids = json_decode($_POST['request_ids'], true);

        if (!is_array($request_ids) || empty($request_ids)) {
            echo json_encode(['success' => false, 'message' => 'No requests selected for deletion']);
            exit;
        }

        // Prepare the query to delete multiple requests
        $placeholders = implode(',', array_fill(0, count($request_ids), '?'));
        $query = "DELETE FROM requests WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($query);

        if (!$stmt) {
            echo json_encode(['success' => false, 'message' => 'Failed to prepare database query']);
            exit;
        }

        // Bind the parameters dynamically
        $stmt->bind_param(str_repeat('i', count($request_ids)), ...$request_ids);

        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Requests deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete requests']);
        }

        $stmt->close();
        exit;
    }
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>