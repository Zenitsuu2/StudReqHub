<?php
// admin/fetch_requests.php
include '../Connection/database.php';

// Fetch latest requests
$query = "SELECT r.*, u.firstname, u.lastname, u.contact, u.lrn 
          FROM requests r 
          JOIN users u ON r.user_id = u.id 
          WHERE r.status != 'Received'
          ORDER BY r.created_at DESC";
$result = $conn->query($query);
$requests = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($requests);