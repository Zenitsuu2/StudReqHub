<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_data'])) {
    header('Location: Loginpage.php');
    exit();
}

include '../Connection/database.php';

$user_id = $_SESSION['user_id'];

// Fetch user data if not already in session
if (!isset($_SESSION['user_data'])) {
    $query = "SELECT firstname, lastname, contact, lrn FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $_SESSION['user_data'] = $result->fetch_assoc() ?: [];
}

// Verify user session is valid
$stmt = $conn->prepare("SELECT id FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    session_unset();
    session_destroy();
    header('Location: Loginpage.php');
    exit();
}

// Fetch user's latest request
$query = "SELECT r.*, u.firstname, u.lastname, u.contact, u.lrn 
          FROM requests r 
          JOIN users u ON r.user_id = u.id 
          WHERE r.user_id = ? 
          ORDER BY r.created_at DESC LIMIT 1";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$request = $result->fetch_assoc() ?: [];

// Check if the status is 'Received' and set a notification flag
$notification = ($request['status'] == 'Received') ? true : false;
?>

<?php include 'header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>User Profile</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <script src="https://kit.fontawesome.com/a076d05399.js" crossorigin="anonymous"></script>
    <style>
        .status-container {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .status-box {
            width: 30%;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            font-weight: bold;
            color: white;
            position: relative;
        }
        .waiting { background-color: #9E9E9E; }
        .ready { background-color: #FF9800; }
        .received { background-color: #4CAF50; }
        .loading-indicator {
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            width: 15px;
            height: 15px;
            animation: spin 1s linear infinite;
            margin: 10px auto;
            display: none;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .green-light {
            width: 15px;
            height: 15px;
            background-color: #00FF00;
            border-radius: 50%;
            margin: 10px auto;
            box-shadow: 0 0 10px #00FF00;
            animation: blink 1.5s infinite;
            display: none;
        }
        @keyframes blink {
            0% { opacity: 0.3; }
            50% { opacity: 1; }
            100% { opacity: 0.3; }
        }
        .check-icon {
            font-size: 20px;
            color: white;
            margin-top: 10px;
            display: none;
        }
    </style>
</head>
<body>
<div class="container mt-4">
    <?php if ($notification): ?>
        <div class="alert alert-success" role="alert">
            Your document has been received. Please check the status below.
        </div>
    <?php endif; ?>
    <div class="card p-4">
        <h4>Your Request Information</h4>
        <?php if (!empty($request)): ?>
            <p><strong>Request ID:</strong> #<?= htmlspecialchars($request['id'] ?? 'N/A') ?></p>
            <p><strong>Student Name:</strong> <?= htmlspecialchars($request['firstname'] ?? 'N/A') . ' ' . htmlspecialchars($request['lastname'] ?? '') ?></p>
            <p><strong>Contact Number:</strong> <?= htmlspecialchars($request['contact'] ?? 'N/A') ?></p>
            <p><strong>Document Type:</strong> <?= htmlspecialchars($request['document_type'] ?? 'N/A') ?></p>
            <p><strong>Status:</strong> <?= htmlspecialchars($request['status'] ?? 'N/A') ?></p>
        <?php else: ?>
            <p class="text-danger">No active requests found.</p>
        <?php endif; ?>
    </div>

    <div class="card p-4 mt-3">
        <h4 class="mb-3">Request Status</h4>
        <div class="status-container">
            <div class="status-box waiting">
                Waiting for Approval<br>
                <small>Your request is under review.</small>
                <div class="loading-indicator" style="<?php echo ($request['status'] == 'Pending') ? 'display: block;' : ''; ?>"></div>
            </div>
            <div class="status-box ready">
                Ready for Pickup<br>
                <small>You can pick up your document.</small>
                <div class="green-light" style="<?php echo ($request['status'] == 'Ready for Pickup') ? 'display: block;' : ''; ?>"></div>
            </div>
            <div class="status-box received">
                Received<br>
                <small>The document has been received.</small>
                <i class="fas fa-check-circle check-icon" style="<?php echo ($request['status'] == 'Received') ? 'display: block;' : ''; ?>"></i>
            </div>
        </div>
    </div>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
