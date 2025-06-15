<?php
// Adjust the path to your TCPDF library
if (!file_exists('../tcpdf/tcpdf.php')) {
    die('TCPDF library not found at the specified path.');
}
require_once('../tcpdf/tcpdf.php');
include '../Connection/database.php';

// Check if request_id is provided
if (!isset($_GET['request_id'])) {
    die('Request ID is required.');
}

$request_id = intval($_GET['request_id']);

// Fetch the request details from the database
$query = "SELECT r.*, u.firstname, u.lastname, u.contact, u.lrn, u.uli, u.grade_level, u.address
          FROM requests r
          JOIN users u ON r.user_id = u.id
          WHERE r.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $request_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die('Request not found.');
}

$request = $result->fetch_assoc();

// Create a new PDF document
$pdf = new TCPDF();
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Your System Name');
$pdf->SetTitle('Document Request');
$pdf->SetSubject('Document Request Details');
$pdf->SetKeywords('PDF, document, request');

// Set default header and footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Add a page
$pdf->AddPage();

// Set font
$pdf->SetFont('helvetica', '', 12);

// Add content to the PDF
$html = '
    <h1>Document Request Details</h1>
    <p><strong>Unique Learner ID (ULI):</strong> ' . htmlspecialchars($request['uli']) . '</p>
    <p><strong>Student Name:</strong> ' . htmlspecialchars($request['firstname'] . ' ' . $request['lastname']) . '</p>
    <p><strong>Contact:</strong> ' . htmlspecialchars($request['contact']) . '</p>
    <p><strong>Grade Level:</strong> ' . htmlspecialchars($request['grade_level']) . '</p>
    <p><strong>Address:</strong> ' . htmlspecialchars($request['address']) . '</p>
    <p><strong>Document Type:</strong> ' . htmlspecialchars($request['document_type']) . '</p>
    <p><strong>Status:</strong> ' . htmlspecialchars($request['status']) . '</p>
    <p><strong>Priority:</strong> ' . htmlspecialchars($request['priority']) . '</p>
    <p><strong>ETA:</strong> ' . ($request['eta'] ? htmlspecialchars(date('Y-m-d', strtotime($request['eta']))) : 'Not set') . '</p>
';

// Write the HTML content to the PDF
$pdf->writeHTML($html, true, false, true, false, '');

// Output the PDF as a download
$pdf->Output('Document_Request_' . $request_id . '.pdf', 'D');