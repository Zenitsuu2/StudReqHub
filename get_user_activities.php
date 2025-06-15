<?php
require_once '../Connection/database.php';

if (!isset($_GET['id'])) {
    die(json_encode(['error' => 'ID is required']));
}

$id = intval($_GET['id']);

// Get user details
$userQuery = "SELECT CONCAT(firstname, ' ', COALESCE(middle_name, ''), ' ', lastname) AS full_name,
              lrn, uli, picture
              FROM users 
              WHERE id = ?";

$stmt = $conn->prepare($userQuery);
$stmt->bind_param('i', $id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get all activities for the user
$activityQuery = "SELECT action, timestamp,
                 CASE 
                    WHEN action = 'login' THEN 'Logged into the system'
                    WHEN action = 'document request' THEN 'Requested a document'
                    WHEN action = 'update profile' THEN 'Updated profile information'
                    WHEN action = 'change password' THEN 'Changed account password'
                    WHEN action = 'view document' THEN 'Viewed a document'
                    WHEN action = 'download document' THEN 'Downloaded a document'
                    ELSE action 
                 END as description
                 FROM activity_logs 
                 WHERE id = ?
                 ORDER BY timestamp DESC";

$stmt = $conn->prepare($activityQuery);
$stmt->bind_param('i', $id);
$stmt->execute();
$result = $stmt->get_result();

$activities = [];
while ($row = $result->fetch_assoc()) {
    $activities[] = $row;
}

// Get statistics
$stats = [
    'total' => count($activities),
    'last_active' => $activities[0]['timestamp'] ?? null,
    'most_common' => getMostCommonAction($activities)
];

echo json_encode([
    'user' => $user,
    'activities' => $activities,
    'stats' => $stats
]);

function getMostCommonAction($activities) {
    $actions = array_column($activities, 'action');
    if (empty($actions)) return null;
    
    $counted = array_count_values($actions);
    arsort($counted);
    return array_key_first($counted);
}