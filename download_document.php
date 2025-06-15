<?php
session_start();
require_once '../Connection/database.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login_admin.php');
    exit();
}

if (isset($_GET['request_id'])) {
    $request_id = $_GET['request_id'];
    
    // Fetch request details with corrected column references
    $query = "SELECT r.*, u.firstname, u.middlename, u.lastname, u.lrn, u.grade_level, 
              r.purpose, r.document_type, r.section
              FROM requests r 
              JOIN users u ON r.user_id = u.id 
              WHERE r.id = ?";
    
    // Prepare statement
    $stmt = $conn->prepare($query);
    if ($stmt === false) {
        die('Error preparing statement: ' . $conn->error);
    }
    
    if (!$stmt->bind_param('i', $request_id)) {
        die('Error binding parameters: ' . $stmt->error);
    }
    
    if (!$stmt->execute()) {
        die('Error executing statement: ' . $stmt->error);
    }
    
    $result = $stmt->get_result();
    if ($result === false) {
        die('Error getting result: ' . $stmt->error);
    }
    
    // Fetch the row; if not found, assign safe default values.
    $row = $result->fetch_assoc();
    if (!$row) {
        $row = [
            'firstname'     => 'N/A',
            'middlename'    => '',
            'lastname'      => 'N/A',
            'extensionname' => '',
            'lrn'           => 'N/A',
            'grade_level'   => 'N/A',
            'section'       => 'N/A',
            'purpose'       => 'N/A',
            'document_type' => 'N/A'
        ];
    }
    
    // Build name components safely using null coalescing operator
    $firstName   = strtoupper($row['firstname'] ?? 'N/A');
    $lastName    = strtoupper($row['lastname'] ?? 'N/A');
    $middleName  = (!empty($row['middlename']) && strtoupper($row['middlename']) !== 'N/A')
                    ? strtoupper($row['middlename'])
                    : '';
    $extensionName = (!empty($row['extensionname']) && strtoupper($row['extensionname']) !== 'N/A')
                    ? strtoupper($row['extensionname'])
                    : '';
    
    // Build full name
    if (!empty($middleName) && !empty($extensionName)) {
        $fullName = $firstName . ' ' . $middleName . ' ' . $lastName . ', ' . $extensionName;
    } elseif (!empty($middleName)) {
        $fullName = $firstName . ' ' . $middleName . ' ' . $lastName;
    } elseif (!empty($extensionName)) {
        $fullName = $firstName . ' ' . $lastName . ', ' . $extensionName;
    } else {
        $fullName = $firstName . ' ' . $lastName;
    }
    
    // Date components for the document
    $day    = date('j');
    $month  = date('F');
    $year   = date('Y');
    $suffix = date('S');
    
    // Select document template based on document type
    switch ($row['document_type']) {
        case 'Certificate of Enrollment':
            $template = '../templates/certificate_of_enrollment.html';
            break;
        case 'Good Moral Certificate':
            $template = '../templates/goodmoral_certificate.html';
            break;
        case 'Diploma':
            $template = '../templates/grade6_diploma.html';
            break;
        case 'Certificate of Completion of Kinder':
            $template = '../templates/mark.html';
            break;
        default:
            die('Invalid document type');
    }
    
    if (file_exists($template)) {
        $content = file_get_contents($template);
        
        // Function to convert image file to base64
        function imageToBase64($imagePath) {
            if (file_exists($imagePath)) {
                $imageData = file_get_contents($imagePath);
                $base64 = base64_encode($imageData);
                $mimeType = mime_content_type($imagePath);
                return "data:$mimeType;base64,$base64";
            }
            return false;
        }
        
        // Get template directory and convert image paths in the template to base64
        $templateDir = dirname($template);
        $dom = new DOMDocument();
        @$dom->loadHTML($content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $images = $dom->getElementsByTagName('img');
        
        foreach ($images as $image) {
            $src = $image->getAttribute('src');
            if (strpos($src, 'img/') === 0) {
                $imgPath = $templateDir . '/' . $src;
                $base64Data = imageToBase64($imgPath);
                if ($base64Data) {
                    $image->setAttribute('src', $base64Data);
                }
            }
        }
        
        $content = $dom->saveHTML();
        
        // Replace template placeholders with actual values
        $replacements = [
            '{FULL_NAME}'   => $fullName,
            '{LRN}'         => $row['lrn'] ?? 'N/A',
            '{GRADE_LEVEL}' => $row['grade_level'] ?? 'N/A',
            '{SECTION}'     => $row['section'] ?? 'N/A',
            '{SCHOOL_YEAR}' => '2024-2025',
            '{PURPOSE}'     => strtoupper($row['purpose'] ?? 'N/A'),
            '{DAY}'         => $day,
            '{MONTH}'       => $month,
            '{YEAR}'        => $year,
            '{DAY_SUFFIX}'  => $suffix,
            '{HONORS}'      => !empty($row['honors']) ? $row['honors'] : ''  // This will insert the selected honors level
        ];
        
        $content = str_replace(array_keys($replacements), array_values($replacements), $content);
        
        // Set headers for download as an HTML file
        header('Content-Type: text/html');
        header('Content-Disposition: attachment; filename="' . 
               strtolower(str_replace(' ', '_', $row['document_type'])) . '_' . 
               strtolower(str_replace(' ', '_', $fullName)) . '.html"');
        
        echo $content;
        exit();
    } else {
        die('Template file not found');
    }
} else {
    die('No request ID provided');
}
?>