<?php
include '../Connection/database.php';

$query = "SELECT * FROM events WHERE start_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)";
$result = $conn->query($query);

if ($result === false) {
    die('Error executing query: ' . $conn->error);
}

$events = $result->fetch_all(MYSQLI_ASSOC);

foreach ($events as $event) {
    $query = "SELECT contact FROM users";
    $result = $conn->query($query);

    if ($result === false) {
        die('Error executing query: ' . $conn->error);
    }

    while ($user = $result->fetch_assoc()) {
        $contact = $user['contact'];
        $message = "Reminder: Event '{$event['title']}' is happening tomorrow. {$event['description']}";
        sendSMS($contact, $message);
    }
}