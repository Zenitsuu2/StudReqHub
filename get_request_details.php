<?php
session_start();
require_once '../Connection/database.php';

// Set proper content type header
header('Content-Type: application/json');

// Check for admin session
if (!isset($_SESSION['admin'])) {
    die(json_encode(['error' => 'Unauthorized access']));
}

// Validate request ID
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die(json_encode(['error' => 'Invalid request ID']));
}

$request_id = intval($_GET['id']);

try {
    // Prepare SQL query with proper joins
    $query = "SELECT r.*, 
              s.firstname, 
              s.lastname,
              s.email,
              s.lrn,
              s.uli,
              s.grade_level,
              s.dob,
                s.contact,
                s.address,
              CONCAT(s.firstname, ' ', s.lastname) as full_name
              FROM requests r
              LEFT JOIN users s ON r.user_id = s.id
              WHERE r.id = ?";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param('i', $request_id);
    if (!$stmt->execute()) {
        throw new Exception($stmt->error);
    }

    $result = $stmt->get_result();
    $request = $result->fetch_assoc();

    if (!$request) {
        die(json_encode(['error' => 'Request not found']));
    }

    // Format the response data
    $response = [
        'id' => $request['id'],
        'lrn' => $request['lrn'] ?? 'N/A',
        'uli' => $request['uli'] ?? 'N/A',
        'full_name' => $request['full_name'] ?? 'N/A',
        'dob' => $request['dob'] ?? 'N/A',
        'contact' => $request['contact'] ?? 'N/A',
        'address' => $request['address'] ?? 'N/A',
        'email' => $request['email'] ?? 'N/A',
        'grade_level' => $request['grade_level'] ?? 'N/A',
        'document_type' => $request['document_type'],
        'status' => $request['status'],
        'created_at' => date('Y-m-d H:i:s', strtotime($request['created_at'])),
        'purpose' => $request['purpose'] ?? 'Not specified',
        'additional_notes' => $request['additional_notes'] ?? 'None'
    ];

    // Return clean JSON response
    echo json_encode($response);

} catch (Exception $e) {
    die(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
}

$stmt->close();
$conn->close();