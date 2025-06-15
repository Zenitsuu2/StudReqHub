<?php
header('Content-Type: application/json');
// Retrieve online statuses based on your server logic (e.g., last_activity timestamps)
$statusMap = [
    1 => 'offline', // student id 1 is offline
    2 => 'active',  // student id 2 is active
    // … etc.
];
echo json_encode($statusMap);
?>