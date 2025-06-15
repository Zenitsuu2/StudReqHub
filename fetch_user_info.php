<?php
include '../Connection/database.php';

if (isset($_GET['request_id'])) {
    $request_id = intval($_GET['request_id']);

    $query = "SELECT u.firstname, u.lastname, u.contact, u.grade_level, u.uli 
              FROM requests r 
              JOIN users u ON r.user_id = u.id 
              WHERE r.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo json_encode(['success' => true, 'firstname' => $user['firstname'], 'lastname' => $user['lastname'], 'contact' => $user['contact'], 'grade_level' => $user['grade_level'], 'uli' => $user['uli']]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>