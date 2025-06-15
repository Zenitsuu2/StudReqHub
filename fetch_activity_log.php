<?php
session_start();
include __DIR__ . '/../Connection/database.php';
// Get filter parameters
$filterFirstName = $_GET['firstname'] ?? '';
$filterLastName = $_GET['lastname'] ?? '';
$filterDocType = $_GET['docType'] ?? '';
$filterDateFrom = $_GET['dateFrom'] ?? '';
$filterDateTo = $_GET['dateTo'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterPurpose = $_GET['purpose'] ?? '';
$filterSchoolYear = $_GET['schoolYear'] ?? '';
$filterPriority = $_GET['priority'] ?? '';

// Build the query with filters
$query = "SELECT r.id, r.user_id, u.lrn, CONCAT(u.firstname, ' ', u.lastname) AS student_name, 
          ud.guardian_name, ud.grade_level, r.document_type, r.purpose, r.school_year, 
          r.status, r.eta, r.received_date, r.created_at, r.priority 
          FROM requests r
          JOIN users u ON r.user_id = u.id
          LEFT JOIN user_details ud ON u.id = ud.user_id
          WHERE 1=1";

// Add filters to the query if they are provided
if (!empty($filterFirstName)) {
    $query .= " AND u.firstname LIKE :firstname";
}

if (!empty($filterLastName)) {
    $query .= " AND u.lastname LIKE :lastname";
}

if (!empty($filterDocType)) {
    $query .= " AND r.document_type = :docType";
}

if (!empty($filterDateFrom)) {
    $query .= " AND r.created_at >= :dateFrom";
}

if (!empty($filterDateTo)) {
    $query .= " AND r.created_at <= :dateTo";
}

if (!empty($filterStatus)) {
    $query .= " AND r.status = :status";
}

if (!empty($filterPurpose)) {
    $query .= " AND r.purpose = :purpose";
}

if (!empty($filterSchoolYear)) {
    $query .= " AND r.school_year = :schoolYear";
}

if (!empty($filterPriority)) {
    $query .= " AND r.priority = :priority";
}

// Order by creation date, newest first
$query .= " ORDER BY r.created_at DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);

// Bind parameters if they are provided
if (!empty($filterFirstName)) {
    $stmt->bindValue(':firstname', "%$filterFirstName%", PDO::PARAM_STR);
}

if (!empty($filterLastName)) {
    $stmt->bindValue(':lastname', "%$filterLastName%", PDO::PARAM_STR);
}

if (!empty($filterDocType)) {
    $stmt->bindValue(':docType', $filterDocType, PDO::PARAM_STR);
}

if (!empty($filterDateFrom)) {
    $stmt->bindValue(':dateFrom', $filterDateFrom . ' 00:00:00', PDO::PARAM_STR);
}

if (!empty($filterDateTo)) {
    $stmt->bindValue(':dateTo', $filterDateTo . ' 23:59:59', PDO::PARAM_STR);
}

if (!empty($filterStatus)) {
    $stmt->bindValue(':status', $filterStatus, PDO::PARAM_STR);
}

if (!empty($filterPurpose)) {
    $stmt->bindValue(':purpose', $filterPurpose, PDO::PARAM_STR);
}

if (!empty($filterSchoolYear)) {
    $stmt->bindValue(':schoolYear', $filterSchoolYear, PDO::PARAM_STR);
}

if (!empty($filterPriority)) {
    $stmt->bindValue(':priority', $filterPriority, PDO::PARAM_STR);
}

$stmt->execute();
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return results as JSON
header('Content-Type: application/json');
echo json_encode($results);
?>