<?php
include '../Connection/database.php';

// Define the timeframe (e.g., 7 days)
$timeframe = 7;

// Archive requests not collected within the timeframe
$query = "UPDATE requests 
          SET status = 'Archived', archived_date = NOW() 
          WHERE status = 'Ready for Pickup' 
          AND DATEDIFF(NOW(), eta) > ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    die('Error preparing statement: ' . $conn->error);
}

$stmt->bind_param('i', $timeframe);

if ($stmt->execute()) {
    echo "Uncollected requests have been archived.";
} else {
    die('Error executing query: ' . $stmt->error);
}
?>