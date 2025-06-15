<?php
include '../Connection/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    // Debug: Log received data
    file_put_contents('debug.log', print_r($data, true));

    if (isset($data['request_id'], $data['status'])) {
        $request_id = $data['request_id'];
        $status = $data['status'];
        $reason = $data['reason'] ?? null;

        // Check database connection
        if (!$conn) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed']);
            exit;
        }

        // Update request status
        $query = "UPDATE requests SET status = ?, processed_date = NOW(), rejection_reason = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param('ssi', $status, $reason, $request_id);

        if ($stmt->execute()) {
            // Get user details for SMS
            $query = "SELECT u.firstname, u.contact, r.document_type 
                      FROM requests r 
                      JOIN users u ON r.user_id = u.id 
                      WHERE r.id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $request_id);
            $stmt->execute();
            $user = $stmt->get_result()->fetch_assoc();

            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'User details not found']);
                exit;
            }

            // Send SMS notification
            $message = "Hello {$user['firstname']}, your {$user['document_type']} request has been $status";
            if ($status === 'Disapproved') {
                $message .= ". Reason: $reason";
            }

            include_once '../Connection/send_sms.php';
            sendSMS($user['contact'], $message);

            echo json_encode(['success' => true, 'message' => 'Request status updated successfully.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update request status.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid request data.']);
    }
}
?>
