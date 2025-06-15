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

// Handle form submission for report card request
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !$active_request) {
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

    // Insert new report card request
    $stmt = $conn->prepare("INSERT INTO requests (user_id, document_type, purpose, school_year, priority, status, uli, created_at)
                            VALUES (?, 'Report Card', ?, ?, ?, 'Pending', ?, NOW())");
    $stmt->bind_param("issss", $user_id, $purpose, $school_year, $priority, $user_data['uli']);
    $stmt->execute();

    // Set success message
    $_SESSION['success_message'] = "Report card request has been successfully submitted!";
    header("Location: home.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request Report Card</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
</head>
<body class="bg-light">
<div class="container mt-5">
    <h3>Request Report Card</h3>

    <!-- Display Success or Error Messages -->
    <?php if (isset($success_message)): ?>
        <div class="alert alert-success">
            <?= $success_message ?>
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
        <form id="otherDocumentForm" method="POST" class="needs-validation" novalidate>
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars(formatFullName(
                    $user_data['firstname'],
                    $user_data['middlename'],
                    $user_data['lastname'],
                    $user_data['extensionname']
                )) ?>" disabled>
            </div>
            
            <div class="mb-3">
                <label class="form-label">LRN</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($user_data['lrn']) ?>" disabled>
            </div>

            <div class="mb-3">
                <label for="document_type" class="form-label">Document Type</label>
                <select name="document_type" id="document_type" class="form-select" required>
                    <option value="">Select Document Type</option>
                    <option value="Good Moral Certificate">Certificate of Good Moral Character</option>
                    <option value="Certificate of Enrollment">Certificate of Enrollment</option>
                    <option value="Diploma">Diploma</option>
                    <option value="Certificate of Completion of Kinder">Certificate of Completion of Kinder</option>
                </select>
            </div>

            <div class="mb-3" id="purpose-container">
                <label for="purpose" class="form-label">Purpose</label>
                <input type="text" name="purpose" id="purpose" class="form-control" required>
            </div>

            <div class="mb-3">
                <label for="priority" class="form-label">Priority</label>
                <select name="priority" id="priority" class="form-select" required>
                    <option value="">Select Priority</option>
                    <option value="Normal">Normal</option>
                    <option value="Urgent">Urgent</option>
                </select>
            </div>
            
            <!-- New school year field -->
            <div class="mb-3">
                <label for="school_year" class="form-label">School Year</label>
                <input type="date" name="school_year" id="school_year" class="form-control" placeholder="e.g. 2023" required>
            </div>
            
            <div class="d-flex justify-content-between">
                <button type="button" class="btn btn-secondary" onclick="window.location.href='home.php'">Back to Home</button>
                <button type="button" class="btn btn-primary" id="reviewBtn">Review Request</button>
                <!-- Hidden actual submit button -->
                <button type="submit" id="hiddenSubmit" style="display:none;">Submit Request</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="reviewModalLabel">Review Request Information</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p><strong>Full Name:</strong> <span id="review-fullname"><?= htmlspecialchars(formatFullName(
            $user_data['firstname'],
            $user_data['middlename'],
            $user_data['lastname'],
            $user_data['extensionname']
        )) ?></span></p>
        <p><strong>LRN:</strong> <span id="review-lrn"><?= htmlspecialchars($user_data['lrn']) ?></span></p>
        <p><strong>Document Type:</strong> <span id="review-document_type"></span></p>
        <p><strong>Purpose:</strong> <span id="review-purpose"></span></p>
        <p><strong>Priority:</strong> <span id="review-priority"></span></p>
        <p><strong>School Year:</strong> <span id="review-school_year"></span></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Edit</button>
        <button type="button" class="btn btn-primary" id="confirmSubmit">Submit Request</button>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('reviewBtn').addEventListener('click', function() {
    // Gather form values
    const documentType = document.getElementById('document_type').value;
    const purpose = document.getElementById('purpose').value;
    const priority = document.getElementById('priority').value;
    const schoolYear = document.getElementById('school_year').value; // will be in YYYY-MM-DD format

    // Populate the review modal
    document.getElementById('review-document_type').textContent = documentType;
    document.getElementById('review-purpose').textContent = purpose;
    document.getElementById('review-priority').textContent = priority;
    document.getElementById('review-school_year').textContent = schoolYear;

    // Show the review modal using Bootstrap 5 Modal API
    const reviewModal = new bootstrap.Modal(document.getElementById('reviewModal'));
    reviewModal.show();
});

// When confirmed in the review modal, trigger the actual submission.
document.getElementById('confirmSubmit').addEventListener('click', function() {
    document.getElementById('hiddenSubmit').click();
});
</script>

</body>
</html>
