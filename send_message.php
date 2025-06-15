<?php
// Allow JSON requests
header('Content-Type: application/json');

// Get raw POST data
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($input['contact']) || !isset($input['message'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Missing contact or message.'
    ]);
    exit;
}

$contact = $input['contact'];
$message = $input['message'];

// Example: Simulate sending a message (e.g., save to database or send SMS/email)
$isSent = true; // Simulate success

if ($isSent) {
    echo json_encode([
        'success' => true
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Message failed to send.'
    ]);
}
?>
