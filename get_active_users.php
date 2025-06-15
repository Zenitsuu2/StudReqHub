<?php
require '../Connection/database.php';

header('Content-Type: application/json');

try {
    $query = "SELECT 
                u.id,
                u.firstname,
                u.lastname,
                u.email,
                MAX(al.timestamp) as last_activity,
                CASE 
                    WHEN MAX(al.timestamp) > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 'online'
                    WHEN MAX(al.timestamp) > DATE_SUB(NOW(), INTERVAL 1 HOUR) THEN 'recent'
                    ELSE 'offline'
                END as status
              FROM users u
              LEFT JOIN activity_logs al ON u.id = al.user_id
              GROUP BY u.id";
              
    $result = $conn->query($query);
    
    if ($result) {
        $users = array();
        while ($row = $result->fetch_assoc()) {
            $users[] = array(
                'id' => $row['id'],
                'name' => $row['firstname'] . ' ' . $row['lastname'],
                'email' => $row['email'],
                'last_activity' => $row['last_activity'],
                'status' => $row['status']
            );
        }
        
        echo json_encode([
            'success' => true,
            'data' => $users
        ]);
    } else {
        throw new Exception("Error fetching users");
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>