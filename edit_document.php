<?php
session_start();
require_once '../Connection/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['admin'])) {
    die(json_encode(['success' => false, 'message' => 'Unauthorized access']));
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    die(json_encode(['success' => false, 'message' => 'Invalid CSRF token']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = filter_var($_POST['request_id'], FILTER_VALIDATE_INT);
    $document_type = filter_var($_POST['document_type'], FILTER_SANITIZE_STRING);
    $purpose = filter_var($_POST['purpose'], FILTER_SANITIZE_STRING);

    if (!$request_id) {
        die(json_encode(['success' => false, 'message' => 'Invalid request ID']));
    }

    // Update the document request
    $stmt = $conn->prepare("UPDATE requests SET document_type = ?, purpose = ? WHERE id = ?");
    $stmt->bind_param("ssi", $document_type, $purpose, $request_id);

    if ($stmt->execute()) {
        // Log the action
        $admin_id = $_SESSION['admin']['id'];
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (admin_id, action, details) VALUES (?, 'edit_document', ?)");
        $details = "Edited document request #$request_id - Changed to: $document_type";
        $log_stmt->bind_param("is", $admin_id, $details);
        $log_stmt->execute();

        echo json_encode(['success' => true, 'message' => 'Document request updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update document request']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}