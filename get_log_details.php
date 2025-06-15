<?php
require '../Connection/database.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'Log ID is required.']);
    exit;
}

$logId = intval($_GET['id']);

// Fetch log details
$query = "SELECT al.id AS log_id, al.action, al.timestamp, r.document_type, r.status AS document_status,
                 CONCAT(u.firstname, ' ', u.lastname) AS fullname
          FROM activity_logs al
          LEFT JOIN users u ON al.user_id = u.id
          LEFT JOIN requests r ON al.request_id = r.id
          WHERE al.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $logId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $log = $result->fetch_assoc();
    echo json_encode(['success' => true, 'data' => $log]);
} else {
    echo json_encode(['success' => false, 'message' => 'Log not found.']);
}
?>