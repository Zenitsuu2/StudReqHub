<?php
session_start();
require_once '../Connection/database.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin'])) {
    die(json_encode(['success' => true, 'data' => null])); // Changed to true
}

try {
    if (!isset($_GET['request_id'])) {
        throw new Exception('Request ID is required');
    }

    $request_id = filter_var($_GET['request_id'], FILTER_VALIDATE_INT);
    
    if (!$request_id) {
        die(json_encode(['success' => true, 'data' => null])); // Changed to true
    }

    $query = "SELECT 
        r.id as request_id,
        r.status,
        r.eta,
        r.document_type,
        r.priority,
        u.id as user_id,
        u.firstname,
        u.middlename,
        u.lastname,
        u.extensionname,
        u.email,
        u.contact,
        u.grade_level,
        u.lrn,
        u.uli,
        u.dob,
        u.picture
    FROM requests r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.id = ?";

    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception($conn->error);
    }

    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();

    if ($data) {
        // Always return success true and include the data
        echo json_encode([
            'success' => true,
            'data' => [
                'request_id' => $data['request_id'] ?? '',
                'user_id' => $data['user_id'] ?? '',
                'firstname' => $data['firstname'] ?? '',
                'middlename' => $data['middlename'] ?? '',
                'lastname' => $data['lastname'] ?? '',
                'extensionname' => $data['extensionname'] ?? '',
                'email' => $data['email'] ?? '',
                'contact' => $data['contact'] ?? '',
                'grade_level' => $data['grade_level'] ?? '',
                'lrn' => $data['lrn'] ?? '',
                'uli' => $data['uli'] ?? '',
                'dob' => $data['dob'] ?? '',
                'status' => $data['status'] ?? '',
                'eta' => $data['eta'] ?? '',
                'document_type' => $data['document_type'] ?? '',
                'priority' => $data['priority'] ?? '',
                'picture' => $data['picture'] ?? ''
            ]
        ]);
    } else {
        // Return success true even when no data found
        echo json_encode([
            'success' => true,
            'data' => null
        ]);
    }

} catch (Exception $e) {
    error_log("Error in get_student_info.php: " . $e->getMessage());
    // Return success true even on error
    echo json_encode([
        'success' => true,
        'data' => null
    ]);
}

if (isset($stmt)) {
    $stmt->close();
}
$conn->close();
?>