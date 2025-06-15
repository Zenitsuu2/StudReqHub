<?php
require '../Connection/database.php';

header('Content-Type: application/json');

$query = "SELECT DISTINCT uli FROM users WHERE uli IS NOT NULL";
$result = $conn->query($query);

if ($result) {
    $ulis = [];
    while ($row = $result->fetch_assoc()) {
        $ulis[] = $row['uli'];
    }
    echo json_encode(['success' => true, 'ulis' => $ulis]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch ULI list']);
}
?>