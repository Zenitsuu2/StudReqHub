<?php
session_start();
include '../../Connection/database.php';

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../user/Loginpage.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$error_message = '';
$success_message = '';

// Fetch current user data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    die("Database error: " . $conn->error);
}

$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    die("User not found or multiple users with the same ID.");
}

$user_data = $result->fetch_assoc();

// Initialize variables to prevent undefined array key warnings
$firstname = isset($user_data['firstname']) ? $user_data['firstname'] : '';
$lastname = isset($user_data['lastname']) ? $user_data['lastname'] : '';
$middlename = isset($user_data['middlename']) ? $user_data['middlename'] : '';
$email = isset($user_data['email']) ? $user_data['email'] : '';
$contact = isset($user_data['contact']) ? $user_data['contact'] : '';
$guardian_name = isset($user_data['guardian_name']) ? $user_data['guardian_name'] : '';
$address = isset($user_data['address']) ? $user_data['address'] : '';
$dob = isset($user_data['dob']) ? $user_data['dob'] : '';
$grade_level = isset($user_data['grade_level']) ? $user_data['grade_level'] : '';
$lrn = isset($user_data['lrn']) ? $user_data['lrn'] : '';
$picture = isset($user_data['picture']) ? $user_data['picture'] : '';

// Check for success message from session
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    // Clear the session message after use
    unset($_SESSION['success_message']);
}

// Handle photo upload first
if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === 0) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $max_size = 2 * 1024 * 1024; // 2MB
    
    if (!in_array($_FILES['profile_photo']['type'], $allowed_types)) {
        $error_message = "Only JPEG, PNG, and GIF images are allowed.";
    } elseif ($_FILES['profile_photo']['size'] > $max_size) {
        $error_message = "Image size should not exceed 2MB.";
    } else {
        $upload_dir = '../../uploads/profile_pictures/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate a unique filename
        $filename = $user_id . '_' . time() . '_' . basename($_FILES['profile_photo']['name']);
        $target_file = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $target_file)) {
            // Update the picture variable immediately
            $picture = $target_file;
            
            // Update the database with the new image path
            $update_photo_query = "UPDATE users SET picture = ? WHERE id = ?";
            $update_photo_stmt = $conn->prepare($update_photo_query);
            $update_photo_stmt->bind_param('si', $picture, $user_id);
            
            if ($update_photo_stmt->execute()) {
                $success_message = "Profile picture updated successfully!";
                // Update the user_data array with the new picture path for display
                $user_data['picture'] = $picture;
            } else {
                $error_message = "Error updating profile picture in database.";
            }
        } else {
            $error_message = "Error uploading profile picture.";
        }
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_FILES['profile_photo'])) {
    // Validate and sanitize input data
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $middlename = trim($_POST['middlename']);
    $email = trim($_POST['email']);
    $contact = trim($_POST['contact']);
    $guardian_name = trim($_POST['guardian_name']);
    $address = trim($_POST['address']);
    $dob = trim($_POST['dob']);
    $grade_level = trim($_POST['grade_level']);
    $lrn = trim($_POST['lrn']);
    $picture = $user_data['picture'] ?? ''; // Use existing picture path unless updated
    
    // $picture is already updated if a new photo was uploaded
    
    // Basic validation
    if (empty($firstname) || empty($lastname) || empty($email)) {
        $error_message = "First name, last name, and email are required fields.";
    } else {
        // Update user data in the database
        $update_query = "UPDATE users SET 
                         firstname = ?, 
                         lastname = ?,
                         middlename = ?, 
                         email = ?, 
                         contact = ?, 
                         guardian_name = ?, 
                         address = ?, 
                         dob = ?,
                         grade_level = ?,
                         lrn = ?,
                         picture = ?
                         WHERE id = ?";
        
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param('sssssssssssi', $firstname, $lastname, $middlename, $email, $contact, $guardian_name, $address, $dob, $grade_level, $lrn, $picture, $user_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Profile updated successfully!";
            // Refresh user data
            $query = "SELECT * FROM users WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user_data = $result->fetch_assoc();
            
            // Update the local variables with the new data
            $firstname = isset($user_data['firstname']) ? $user_data['firstname'] : '';
            $lastname = isset($user_data['lastname']) ? $user_data['lastname'] : '';
            $middlename = isset($user_data['middlename']) ? $user_data['middlename'] : '';
            $email = isset($user_data['email']) ? $user_data['email'] : '';
            $contact = isset($user_data['contact']) ? $user_data['contact'] : '';
            $guardian_name = isset($user_data['guardian_name']) ? $user_data['guardian_name'] : '';
            $address = isset($user_data['address']) ? $user_data['address'] : '';
            $dob = isset($user_data['dob']) ? $user_data['dob'] : '';
            $grade_level = isset($user_data['grade_level']) ? $user_data['grade_level'] : '';
            $lrn = isset($user_data['lrn']) ? $user_data['lrn'] : '';
            $picture = isset($user_data['picture']) ? $user_data['picture'] : '';
        } else {
            $error_message = "Error updating profile: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Villa Teodora Elementary School</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f4f6f9;
        }
        
        .container {
            max-width: 800px;
            margin: 40px auto;
        }
        
        .card {
            border-radius: 15px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            border: none;
        }
        
        .card-header {
            background: linear-gradient(to right, #003366, #00509e);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            padding: 15px 20px;
        }
        
        .form-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #003366;
            margin-bottom: 15px;
            padding-left: 10px;
            border-left: 4px solid #003366;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-label {
            font-weight: 600;
            color: #555;
            display: flex;
            align-items: center;
        }
        
        .form-label i {
            margin-right: 8px;
            color: #003366;
        }
        
        .form-control:focus {
            border-color: #003366;
            box-shadow: 0 0 0 0.25rem rgba(0, 51, 102, 0.25);
        }
        
        .profile-image-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: 20px;
            position: relative;
        }
        
        .profile-image {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #f0f0f0;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }
        
        .change-photo-btn {
            position: absolute;
            bottom: 0;
            background: rgba(0, 51, 102, 0.8);
            color: white;
            border: none;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .change-photo-btn:hover {
            background: rgba(0, 51, 102, 1);
        }
        
        .photo-upload-area {
            border: 2px dashed #ccc;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .photo-upload-area:hover {
            border-color: #003366;
        }
        
        .btn-primary {
            background-color: #003366;
            border-color: #003366;
        }
        
        .btn-primary:hover {
            background-color: #00509e;
            border-color: #00509e;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            border-color: #6c757d;
        }
        
        .back-btn {
            margin-bottom: 20px;
        }
        
        .required-field::after {
            content: " *";
            color: #dc3545;
        }
        
        /* Tooltip styling */
        .tooltip-icon {
            cursor: pointer;
            color: #6c757d;
            margin-left: 5px;
        }
        
        /* Form help text */
        .form-text {
            font-size: 0.8rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="my_profile.php" class="btn btn-secondary back-btn">
            <i class="bi bi-arrow-left"></i> Back to Profile
        </a>
        
        <div class="card">
            <div class="card-header">
                <h4><i class="bi bi-pencil-square me-2"></i>Edit Profile</h4>
            </div>
            <div class="card-body">
                <?php if ($error_message): ?>
                <div class="alert alert-danger"><?= $error_message ?></div>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                <div class="alert alert-success"><?= $success_message ?></div>
                <?php endif; ?>
                
                <!-- Profile Picture Section -->

                
                <form method="POST" action="">
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h5 class="section-title"><i class="bi bi-person"></i> Personal Information</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="firstname" class="form-label">
                                        <i class="bi bi-person-fill"></i> First Name
                                    </label>
                                    <input type="text" class="form-control" id="firstname" name="firstname" 
                                           value="<?= htmlspecialchars($firstname) ?>" >
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="lastname" class="form-label ">
                                        <i class="bi bi-person-fill"></i> Last Name
                                    </label>
                                    <input type="text" class="form-control" id="lastname" name="lastname" 
                                           value="<?= htmlspecialchars($lastname) ?>" >
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="middlename" class="form-label ">
                                        <i class="bi bi-person-fill"></i> Middle Name
                                    </label>
                                    <input type="text" class="form-control" id="middlename" name="middlename"
                                           value="<?= htmlspecialchars($middlename) ?>" > 
                                           
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="form-label">
                                        <i class="bi bi-envelope-fill"></i> Email
                                    </label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?= htmlspecialchars($email) ?>" >
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="contact" class="form-label">
                                        <i class="bi bi-telephone-fill"></i> Contact Number
                                    </label>
                                    <input type="text" class="form-control" id="contact" name="contact" 
                                           value="<?= htmlspecialchars($contact) ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="dob" class="form-label">
                                <i class="bi bi-calendar-event"></i> Date of Birth
                            </label>
                            <input type="date" class="form-control" id="dob" name="dob" 
                                   value="<?= htmlspecialchars($dob) ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="address" class="form-label">
                                <i class="bi bi-geo-alt-fill"></i> Address
                            </label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?= htmlspecialchars($address) ?></textarea>
                        </div>
                    </div>
                    
                    <!-- Guardian Information Section -->
                    <div class="form-section">
                        <h5 class="section-title"><i class="bi bi-people"></i> Guardian Information</h5>
                        
                        <div class="form-group">
                            <label for="guardian_name" class="form-label">
                                <i class="bi bi-person-badge"></i> Guardian Name
                            </label>
                            <input type="text" class="form-control" id="guardian_name" name="guardian_name" 
                                   value="<?= htmlspecialchars($guardian_name) ?>">
                        </div>
                    </div>
                    
                    <!-- School Information Section -->
                    <div class="form-section">
                        <h5 class="section-title"><i class="bi bi-book"></i> School Information</h5>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="grade_level" class="form-label">
                                        <i class="bi bi-mortarboard-fill"></i> Grade Level
                                    </label>
                                    <select class="form-select" id="grade_level" name="grade_level">
                                        <option value="">Select Grade Level</option>
                                        <option value="Grade 1" <?= $grade_level == 'Grade 1' ? 'selected' : '' ?>>Grade 1</option>
                                        <option value="Grade 2" <?= $grade_level == 'Grade 2' ? 'selected' : '' ?>>Grade 2</option>
                                        <option value="Grade 3" <?= $grade_level == 'Grade 3' ? 'selected' : '' ?>>Grade 3</option>
                                        <option value="Grade 4" <?= $grade_level == 'Grade 4' ? 'selected' : '' ?>>Grade 4</option>
                                        <option value="Grade 5" <?= $grade_level == 'Grade 5' ? 'selected' : '' ?>>Grade 5</option>
                                        <option value="Grade 6" <?= $grade_level == 'Grade 6' ? 'selected' : '' ?>>Grade 6</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="lrn" class="form-label">
                                        <i class="bi bi-credit-card-2-front"></i> LRN
                                        <i class="bi bi-question-circle tooltip-icon" 
                                           data-bs-toggle="tooltip" 
                                           title="Learner Reference Number (LRN) is a unique 12-digit identification number assigned to each student"></i>
                                    </label>
                                    <input type="text" class="form-control" id="lrn" name="lrn" 
                                           value="<?= htmlspecialchars($lrn) ?>" 
                                           pattern="\d{12}" 
                                           title="LRN should be a 12-digit number">
                                    <div class="form-text">The LRN is a 12-digit number assigned by DepEd.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <a href="my_profile.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
        
        // Preview uploaded photo
        document.getElementById('profilePhotoInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('photoPreview');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    document.getElementById('uploadPhotoBtn').disabled = false;
                }
                reader.readAsDataURL(file);
            }
        });
        
        // Drag and drop functionality
        const dropZone = document.getElementById('dropZone');
        
        dropZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.borderColor = '#003366';
        });
        
        dropZone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            this.style.borderColor = '#ccc';
        });
        
        dropZone.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.borderColor = '#ccc';
            
            const fileInput = document.getElementById('profilePhotoInput');
            fileInput.files = e.dataTransfer.files;
            
            // Trigger change event
            const event = new Event('change', { bubbles: true });
            fileInput.dispatchEvent(event);
        });

        // SweetAlert for successful updates
        <?php if ($success_message): ?>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?= $success_message ?>',
                confirmButtonColor: '#003366',
                timer: 3000
            });
        });
        <?php endif; ?>
    </script>
</body>
</html>