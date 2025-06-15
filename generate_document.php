<?php
require_once __DIR__ . '/../vendor/autoload.php'; // Ensure you have installed PHPWord via Composer

use PhpOffice\PhpWord\TemplateProcessor;

if (isset($_GET['request_id'])) {
    include '../Connection/database.php';

    $request_id = $_GET['request_id'];

    // Fetch request details
    $query = "SELECT r.*, u.firstname, u.lastname, u.contact, u.lrn, r.document_type
              FROM requests r 
              JOIN users u ON r.user_id = u.id 
              WHERE r.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $request_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $request = $result->fetch_assoc();

        // Determine the template based on the document type
        $templatePath = '';
        switch ($request['document_type']) {
            case 'Transcript of Records':
                $templatePath = 'C:/xampp/htdocs/CAPSTONE/Template not pdf.docx';
                break;
            case 'Certificate of Graduation':
                $templatePath = 'C:/xampp/htdocs/CAPSTONE/CERTIFICATE-OF-ENROLLMENT-LATEST.docx';
                break;
            case 'Diploma':
                $templatePath = 'C:\xampp\htdocs\CAPSTONE\Template not pdf.docx';
                break;
            default:
                echo "Invalid document type.";
                exit();
        }

        // Load the template
        $templateProcessor = new TemplateProcessor($templatePath);

        // Replace placeholders with actual data
        $templateProcessor->setValue('STUDENT_NAME', $request['firstname'] . ' ' . $request['lastname']);
        $templateProcessor->setValue('LRN', $request['lrn']);
        $templateProcessor->setValue('DOCUMENT_TYPE', $request['document_type']);
        $templateProcessor->setValue('ADMIN_NAME', 'Admin Name'); // Replace with actual admin name if needed

        // Save the document
        $fileName = $request['firstname'] . ' ' . $request['lastname'] . '.docx';
        $templateProcessor->saveAs($fileName);  

        // Download the document
        header('Content-Description: File Transfer');
        header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
        header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($fileName));
        readfile($fileName);

        // Delete the file after download
        unlink($fileName);
        exit();
    } else {
        echo "Request not found.";
    }
} else {
    echo "Invalid request.";
}
?>