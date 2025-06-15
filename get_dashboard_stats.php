<?php
// filepath: c:\xampp\htdocs\CAPSTONE\admin\get_dashboard_stats.php
require '../Connection/database.php';

// Example queries – adjust the table names/conditions as needed.
$query = "
    SELECT 
        (SELECT COUNT(*) FROM activity_logs) AS total_activities,
        (SELECT COUNT(*) FROM users WHERE TIMESTAMPDIFF(SECOND, last_activity, NOW()) < 300) AS active_users,
        (SELECT COUNT(*) FROM requests WHERE status <> 'pending') AS document_requests
";
$result = $conn->query($query);

if ($result) {
    $data = $result->fetch_assoc();
    echo json_encode(['success' => true, 'data' => $data]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error fetching dashboard stats.']);
}
?>