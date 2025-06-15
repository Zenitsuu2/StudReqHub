<?php
session_start();
include '../../Connection/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../user/Loginpage.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data
$query = "SELECT firstname, middlename, lastname, extensionname, contact, lrn, uli FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header('Location: ../../user/Loginpage.php');
    exit();
}

$user_data = $result->fetch_assoc();

// Format full name
function formatFullName($firstname, $middlename, $lastname, $extensionname) {
    $name_parts = [$firstname];
    
    if ($middlename && strtolower($middlename) !== 'n/a') {
        $name_parts[] = $middlename;
    }
    
    $name_parts[] = $lastname;
    
    if ($extensionname && strtolower($extensionname) !== 'n/a') {
        $name_parts[] = $extensionname;
    }
    
    return implode(' ', $name_parts);
}

// Check for active requests
$query = "SELECT * FROM requests WHERE user_id = ? AND status != 'Completed'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$active_request = $stmt->get_result()->fetch_assoc();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $document_type = $_POST['document_type'];
    $purpose = $_POST['purpose'];
    $priority = $_POST['priority'];

    // Generate ULI if missing
    if (empty($user_data['uli'])) {
        $user_data['uli'] = 'ULI-' . uniqid();
        $stmt = $conn->prepare("UPDATE users SET uli = ? WHERE id = ?");
        $stmt->bind_param("si", $user_data['uli'], $user_id);
        $stmt->execute();
    }

    // Insert request
    $stmt = $conn->prepare("INSERT INTO requests (user_id, document_type, purpose, priority, status, uli, created_at)
                           VALUES (?, ?, ?, ?, 'Pending', ?, NOW())");
    $stmt->bind_param("issss", $user_id, $document_type, $purpose, $priority, $user_data['uli']);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Request submitted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit request']);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Request Other Document</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <style>
        .form-label { font-weight: 500; }
        .modal-header { background-color: #f8f9fa; }
        .btn-primary { background-color: #003366; border-color: #003366; }
        .btn-primary:hover { background-color: #002347; border-color: #002347; }
    </style>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card shadow-sm">
            <div class="card-header bg-primary text-white">
                <h3 class="card-title mb-0">Request Other Document</h3>
            </div>
            <div class="card-body">
                <?php if ($active_request): ?>
                    <div class="alert alert-warning">
                        You already have an active request. You can submit additional requests.
                    </div>
                <?php endif; ?>
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
                            <option value="FORM 137-E">FORM 137-E</option>
                            <option value="SF10">SF10</option>


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

                    <div class="mb-3">
                        <label for="school_year_start" class="form-label">School Year - Start Year</label>
                        <input type="number" name="school_year_start" id="school_year_start" class="form-control" placeholder="e.g. 2022" min="2000" max="2100" required>
                    </div>
                    <div class="mb-3">
                        <label for="school_year_end" class="form-label">School Year - End Year</label>
                        <input type="number" name="school_year_end" id="school_year_end" class="form-control" placeholder="e.g. 2023" min="2001" max="2101" required>
                    </div>

                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-secondary" onclick="window.location.href='home.php'">Back to Home</button>
                        <button type="button" class="btn btn-primary" id="reviewBtn">Review Request</button>
                        <!-- Hidden actual submit button -->
                        <button type="submit" id="hiddenSubmit" style="display:none;">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.getElementById('otherDocumentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('request_other_document.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Your request has been submitted successfully.',
                        confirmButtonColor: '#003366',
                        allowOutsideClick: false,
                        allowEscapeKey: false
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'Failed to submit request. Please try again.',
                        confirmButtonColor: '#dc3545'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An unexpected error occurred. Please try again.',
                    confirmButtonColor: '#dc3545'
                });
            });
        });

        // Listen for changes in the Document Type dropdown and update the Purpose field accordingly
        document.getElementById('document_type').addEventListener('change', function() {
            const purposeContainer = document.getElementById('purpose-container');
            if (this.value === 'Certificate of Enrollment') {
                purposeContainer.innerHTML = `
                    <label for="purpose" class="form-label">Purpose</label>
                    <select name="purpose" id="purpose" class="form-select" required>
                        <option value="">Select Purpose</option>
                        <option value="Enrollment Purposes">Enrollment Purposes</option>
                        <option value="Educational Assistance Purposes">Educational Assistance Purposes</option>
                        <option value="Pantawid Pamilya Pilipino Program (4Ps) Purposes">Pantawid Pamilya Pilipino Program (4Ps) Purposes</option>
                        <option value="Legal Purposes">Legal Purposes</option>
                        <option value="Bank Account Application">Bank Account Application</option>
                        <option value="Passport Application">Passport Application</option>
                        <option value="Birth certificate Application">Birth certificate Application</option>
                    </select>
                `;
            } else {
                purposeContainer.innerHTML = `
                    <label for="purpose" class="form-label">Purpose</label>
                    <input type="text" name="purpose" id="purpose" class="form-control" required>
                `;
            }
        });

        // Show review modal on Review Request button click
        document.getElementById('reviewBtn').addEventListener('click', function() {
            const documentType = document.getElementById('document_type').value;
            const purpose = document.getElementById('purpose').value;
            const priority = document.getElementById('priority').value;
            const schoolYearStart = document.getElementById('school_year_start').value;
            const schoolYearEnd = document.getElementById('school_year_end').value;
            
            // Set review values – combining the start and end
            document.getElementById('review-document_type').textContent = documentType;
            document.getElementById('review-purpose').textContent = purpose;
            document.getElementById('review-priority').textContent = priority;
            document.getElementById('review-school_year').textContent = schoolYearStart + " - " + schoolYearEnd;
            
            // Show the review modal using Bootstrap 5 Modal API
            const reviewModal = new bootstrap.Modal(document.getElementById('reviewModal'));
            reviewModal.show();
        });

        // Submit form on confirm submit button click
        document.getElementById('confirmSubmit').addEventListener('click', function() {
            document.getElementById('hiddenSubmit').click();
        });
    </script>
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