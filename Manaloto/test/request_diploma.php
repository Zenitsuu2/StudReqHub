<?php
session_start();
include '../../Connection/database.php';

// Display success/error messages
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../user/Loginpage.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch current user data
$query = "SELECT firstname, lastname, contact, lrn, uli FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header('Location: ../../user/Loginpage.php');
    exit();
}

$user_data = $result->fetch_assoc();

// Prevent duplicate active requests
$query = "SELECT * FROM requests WHERE user_id = ? AND status != 'Completed'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_request = $stmt->get_result()->fetch_assoc();

// Handle form submission for diploma request (removed active request condition for debugging)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $purpose     = $_POST['purpose'];
    $school_year = $_POST['school_year'];
    $priority    = $_POST['priority'];

    // Generate ULI if missing
    if (empty($user_data['uli'])) {
        $user_data['uli'] = 'ULI-' . uniqid();
        $stmt = $conn->prepare("UPDATE users SET uli = ? WHERE id = ?");
        $stmt->bind_param("si", $user_data['uli'], $user_id);
        $stmt->execute();
    }

    // Insert new diploma request
    $stmt = $conn->prepare("INSERT INTO requests (user_id, document_type, purpose, school_year, priority, status, uli, created_at)
                            VALUES (?, 'Diploma', ?, ?, ?, 'Pending', ?, NOW())");
    $stmt->bind_param("issss", $user_id, $purpose, $school_year, $priority, $user_data['uli']);
    
    header('Content-Type: application/json');

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Your diploma request has been successfully submitted!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit request: ' . $stmt->error]);
    }
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request Diploma</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-light">
<div class="container mt-5">
    <h3>Request Diploma</h3>

    <!-- Display Success or Error Messages -->
    <?php if (isset($success_message)): ?>
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?= $success_message ?>'
            }).then(() => {
                // Optionally, you can automatically redirect by uncommenting this line:
                // window.location.href = 'home.php';
            });
        </script>
        <div class="mt-3 text-center">
            <button type="button" class="btn btn-secondary" onclick="window.location.href='home.php'">Back to Home</button>
        </div>
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <?= $error_message ?>
        </div>
    <?php endif; ?>

    <!-- Show active request message if user has one -->
    <?php if ($active_request): ?>
        <div class="alert alert-warning">
            You already have an active request. Please wait until it's completed before submitting a new one.
        </div>
    <?php else: ?>
        <!-- Request Form -->
        <form method="POST" class="bg-white p-4 rounded shadow-sm">
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-control" value="<?= $user_data['firstname'] . ' ' . $user_data['lastname'] ?>" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label">LRN</label>
                <input type="text" class="form-control" value="<?= $user_data['lrn'] ?>" disabled>
            </div>
            <div class="mb-3">
                <label for="purpose" class="form-label">Purpose</label>
                <input type="text" name="purpose" class="form-control" required>
            </div>
            <div class="mb-3">
                <label for="school_year" class="form-label">School Year</label>
                <input type="text" name="school_year" class="form-control" placeholder="e.g. 2022-2023" required>
            </div>
            <div class="mb-3">
                <label for="priority" class="form-label">Priority</label>
                <select name="priority" class="form-select" required>
                    <option value="">Select Priority</option>
                    <option value="Normal">Normal</option>
                    <option value="Urgent">Urgent</option>
                </select>
            </div>
            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" onclick="window.location.href='home.php'">Back to Home</button>
                <button type="submit" class="btn btn-primary">Submit Request</button>
            </div>
        </form>
    <?php endif; ?>
</div>
</body>
</html>
