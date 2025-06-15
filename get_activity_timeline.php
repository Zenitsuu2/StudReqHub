<?php
require '../Connection/database.php';

$query = "SELECT al.action, al.timestamp, r.document_type, r.status AS document_status,
                 CONCAT(u.firstname, ' ', u.lastname) AS fullname
          FROM activity_logs al
          LEFT JOIN users u ON al.user_id = u.id
          LEFT JOIN requests r ON al.request_id = r.id
          ORDER BY al.timestamp DESC LIMIT 10";

$result = $conn->query($query);

if ($result) {
    $activities = [];
    while ($row = $result->fetch_assoc()) {
        $activities[] = $row;
    }
    echo json_encode(['success' => true, 'data' => $activities]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch activity timeline.']);
}
?>