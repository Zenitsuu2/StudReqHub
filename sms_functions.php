<?php
function sendStatusNotification($requestId, $status, $conn) {
    try {
        $query = "SELECT u.firstname, u.lastname, u.contact, u.guardian_name, r.document_type 
                 FROM users u JOIN requests r ON u.id = r.user_id 
                 WHERE r.id = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            error_log("Failed to prepare SMS notification query");
            return false;
        }
        
        $stmt->bind_param('i', $requestId);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!$user || empty($user['contact'])) {
            error_log("No user or contact found for request ID: $requestId");
            return false;
        }

        $parent = !empty($user['guardian_name']) ? $user['guardian_name'] : 'Parent/Guardian';
        $student_name = trim($user['firstname'] . ' ' . $user['lastname']);
        $contact = $user['contact'];
        $document_type = $user['document_type'] ?? 'document';

       $messages = [
    'Pending' => "Good day, $parent. We have received your request for $document_type on behalf of $student_name. It is currently under review. Thank you for your patience. This message is from StudReqHub, Villa Teodora.",

    'Processing' => "Good day, $parent. Your request for $document_type on behalf of $student_name is now being processed. We appreciate your continued patience. This message is from StudReqHub, Villa Teodora.",

    'Ready for Pickup' => "Good day, $parent. The requested $document_type for $student_name is now ready for pickup at the school. Kindly visit during office hours. This message is from StudReqHub, Villa Teodora.",

    'Completed' => "Good day, $parent. The request for $document_type for $student_name has been successfully completed. Thank you for availing our services. This message is from StudReqHub, Villa Teodora.",

    'Declined' => "Good day, $parent. We regret to inform you that the request for $document_type for $student_name has been declined. For further assistance, please contact the school. This message is from StudReqHub, Villa Teodora."
];


        if (!isset($messages[$status])) {
            error_log("No message template for status: $status");
            return false;
        }

        return send_sms($contact, $messages[$status]);
    } catch (Exception $e) {
        error_log("Error sending SMS notification: " . $e->getMessage());
        return false;
    }
}

function send_sms($recipient, $message) {
     $api_key = '57c0f39572700c773370d22195de5d66';
    $sender_name = 'StudReqHub';
    
    // Format phone number
    $recipient = preg_replace('/[^0-9]/', '', $recipient);
    if (strlen($recipient) === 11 && $recipient[0] === '0') {
        $recipient = '+63' . substr($recipient, 1);
    } elseif (strlen($recipient) === 10) {
        $recipient = '+63' . $recipient;
    }

    $parameters = [
        'apikey' => $api_key,
        'number' => $recipient,
        'message' => $message,
        'sendername' => $sender_name
    ];

    $ch = curl_init('https://semaphore.co/api/v4/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($parameters),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        error_log('Semaphore cURL error: ' . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    return ($httpCode === 200);
}
?>