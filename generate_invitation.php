<?php
require __DIR__ . '/../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

session_start();
include __DIR__ . '/../Connection/database.php';

if (!isset($_GET['event_id']) || empty($_GET['event_id'])) {
    die('Invalid event ID.');
}

$event_id = intval($_GET['event_id']);

$query = "SELECT title, description, start_date, end_date, event_type, invitation_file FROM events WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $event_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Event not found.');
}

$event = $result->fetch_assoc();

$title = $event['title'];
$description = $event['description'];
$start_date = date('F j, Y, g:i A', strtotime($event['start_date']));
$end_date = date('F j, Y, g:i A', strtotime($event['end_date']));
$event_type = $event['event_type'];
$logo_path = 'http://localhost/CAPSTONE/image/logo.jpg'; // Absolute URL for the logo
$invitation_file_path = $event['invitation_file'] ? 'http://localhost/CAPSTONE/uploads/invitations/' . $event['invitation_file'] : null;

$html = "
<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            padding: 0;
        }
        .header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
        }
        .header img {
            width: 80px;
            height: 80px;
            margin-right: 20px;
        }
        .header h1 {
            font-size: 24px;
            margin: 0;
        }
        .header p {
            font-size: 16px;
            margin: 0;
        }
        .content {
            margin-top: 20px;
        }
        .content h2 {
            font-size: 20px;
            margin-bottom: 10px;
        }
        .content p {
            font-size: 14px;
            line-height: 1.6;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            font-size: 12px;
            color: #555;
        }
        .invitation-image {
            text-align: center;
            margin-top: 20px;
        }
        .invitation-image img {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
    <div class='header'>
        <img src='$logo_path' alt='School Logo'>
        <div>
            <h1>VILLA TEODORA ELEMENTARY SCHOOL</h1>
            <p>You're Invited to Our Event!</p>
        </div>
    </div>
    <div class='content'>
        <h2>$title</h2>
        <p><strong>Event Type:</strong> $event_type</p>
        <p><strong>Start Date:</strong> $start_date</p>
        <p><strong>End Date:</strong> $end_date</p>
        <p><strong>Description:</strong> $description</p>
    </div>";

if ($invitation_file_path) {
    $html .= "
    <div class='invitation-image'>
        <img src='$invitation_file_path' alt='Invitation Image'>
    </div>";
}

$html .= "
    <div class='footer'>
        <p>Thank you for being part of our community!</p>
    </div>
</body>
</html>
";

$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true); // Allow loading external resources
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Output the PDF directly in the browser
header('Content-Type: application/pdf');
echo $dompdf->output();