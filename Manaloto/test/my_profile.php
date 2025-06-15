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
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows !== 1) {
    header('Location: ../../user/Loginpage.php');
    exit();
}

$user_data = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Villa Teodora Elementary School</title>
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
        
        .profile-section {
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .profile-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #003366;
            margin-bottom: 15px;
            padding-left: 10px;
            border-left: 4px solid #003366;
        }
        
        .profile-info {
            margin-bottom: 20px; /* Add spacing between fields */
        }
        
        .profile-label {
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
        }
        
        .profile-label i {
            margin-right: 8px;
            color: #003366;
        }
        
        .profile-value {
            color: #333;
            padding-left: 28px; /* Align with the label icon */
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
        
        .status-tag {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            background-color: #4caf50;
            color: white;
            margin-top: 5px;
        }
        
        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .qr-code-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 10px;
            width: fit-content;
            margin: 0 auto 20px auto;
        }
        
        .qr-code-container p {
            margin-top: 10px;
            font-size: 0.8rem;
            color: #666;
        }
        
        /* Modal for photo upload */
        #photoUploadModal .modal-content {
            border-radius: 15px;
        }
        
        .photo-upload-area {
            border: 2px dashed #ccc;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            margin-bottom: 15px;
            cursor: pointer;
        }
        
        .photo-upload-area:hover {
            border-color: #003366;
        }
    </style>
</head>
<body>
    
    <div class="container">
        <!-- Display success/error messages -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <a href="home.php" class="btn btn-secondary back-btn">
            <i class="bi bi-arrow-left"></i> Back to Dashboard
        </a>
        
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4><i class="bi bi-person-circle me-2"></i>My Profile</h4>
                <a href="edit_profile.php" class="btn btn-light btn-sm">
                    <i class="bi bi-pencil-square me-1"></i>Edit
                </a>
            </div>
            <div class="card-body">
                <div class="profile-image-container">
                    <?php
                    // Determine the profile picture path with proper checks
                    if (!empty($user_data['picture']) && file_exists('../../uploads/' . $user_data['picture'])) {
                        $profile_pic = '../../uploads/' . $user_data['picture'];
                    } else {
                        $profile_pic = '../../assets/img/default-profile.jpg';
                    }
                    
                    // Add a cache-busting parameter to prevent browser caching
                    $profile_pic .= '?v=' . time();
                    ?>
                    <img src="<?= htmlspecialchars($profile_pic) ?>" alt="Profile Picture" class="profile-image" 
                         onerror="this.src='../../assets/img/default-profile.jpg'">
                    <button type="button" class="change-photo-btn" data-bs-toggle="modal" data-bs-target="#photoUploadModal">
                        <i class="bi bi-camera"></i> Change Photo
                    </button>
                </div>
                
                <!-- Personal Information Section -->
                <div class="profile-section">
                    <h5 class="profile-section-title"><i class="bi bi-person"></i> Student Information</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="profile-info">
                                <p class="profile-label"><i class="bi bi-person-fill"></i> First Name</p>
                                <p class="profile-value"><?= htmlspecialchars($user_data['firstname']) ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="profile-info">
                                <p class="profile-label"><i class="bi bi-person-fill"></i> Last Name</p>
                                <p class="profile-value"><?= htmlspecialchars($user_data['lastname']) ?></p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="profile-info">
                                <p class="profile-label"><i class="bi bi-person-fill"></i> Middle Name</p>
                                <p class="profile-value">
                                    <?= !empty($user_data['middlename']) ? htmlspecialchars($user_data['middlename']) : 'Not provided' ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="profile-info">
                                <p class="profile-label"><i class="bi bi-envelope-fill"></i> Email</p>
                                <p class="profile-value">
                                    <?= !empty($user_data['email']) ? htmlspecialchars($user_data['email']) : 'Not provided' ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="profile-info">
                                <p class="profile-label"><i class="bi bi-calendar-event"></i> Date of Birth</p>
                                <p class="profile-value">
                                    <?= !empty($user_data['dob']) ? date('F d, Y', strtotime($user_data['dob'])) : 'Not provided' ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="profile-info">
                                <p class="profile-label"><i class="bi bi-geo-alt-fill"></i> Address</p>
                                <p class="profile-value">
                                    <?= !empty($user_data['address']) ? htmlspecialchars($user_data['address']) : 'Not provided' ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Guardian Information Section -->
                <div class="profile-section">
                    <h5 class="profile-section-title"><i class="bi bi-people"></i> Guardian Information</h5>
                    
                    <div class="profile-info">
                        <p class="profile-label"><i class="bi bi-person-badge"></i> Guardian Name</p>
                        <p class="profile-value">
                            <?= !empty($user_data['guardian_name']) ? htmlspecialchars($user_data['guardian_name']) : 'Not provided' ?>
                        </p>
                    </div>

                    <div class="col-md-6">
                        <div class="profile-info">
                            <p class="profile-label"><i class="bi bi-telephone-fill"></i> Contact Number</p>
                            <p class="profile-value">
                                <?= !empty($user_data['contact']) ? htmlspecialchars($user_data['contact']) : 'Not provided' ?>
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- School Information Section -->
                <div class="profile-section">
                    <h5 class="profile-section-title"><i class="bi bi-book"></i> School Information</h5>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="profile-info">
                                <p class="profile-label"><i class="bi bi-mortarboard-fill"></i> Grade Level</p>
                                <p class="profile-value">
                                    <?= !empty($user_data['grade_level']) ? htmlspecialchars($user_data['grade_level']) : 'Not provided' ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="profile-info">
                                <p class="profile-label"><i class="bi bi-credit-card-2-front"></i> LRN</p>
                                <p class="profile-value">
                                    <?= !empty($user_data['lrn']) ? htmlspecialchars($user_data['lrn']) : 'Not provided' ?>
                                </p>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="profile-info">
                                <p class="profile-label"><i class="bi bi-credit-card-2-front"></i>ULI</p>
                                <p class="profile-value">
                                    <?= !empty($user_data['uli']) ? htmlspecialchars($user_data['uli']) : 'Not provided' ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="profile-info">
                        <p class="profile-label"><i class="bi bi-patch-check-fill"></i> Status</p>
                        <p class="profile-value">
                            <span class="status-tag">Active</span>
                        </p>
                    </div>
                </div>
                
                <!-- QR Code Section (Optional) -->
                <?php
                // Build full student details for the QR code
                $qrData  = "Student Details:%0A";
                $qrData .= "Name: " . ($user_data['firstname'] ?? 'Not provided') . " " . ($user_data['middlename'] ?? 'Not provided') . " " . ($user_data['lastname'] ?? 'Not provided') . " " . ($user_data['extensionname'] ?? 'Not provided') . "%0A";

                if (!empty($user_data['middlename']) && strtolower(trim($user_data['middlename'])) !== 'n/a') {
                }

                if (!empty($user_data['extensionname']) && strtolower(trim($user_data['extensionname'])) !== 'n/a') {
                }

                $qrData .= "LRN: " . ($user_data['lrn'] ?? 'Not provided') . "%0A";
                $qrData .= "ULI: " . ($user_data['uli'] ?? 'Not provided') . "%0A";
                $qrData .= "DOB: " . (!empty($user_data['dob']) ? date('F d, Y', strtotime($user_data['dob'])) : 'Not provided') . "%0A";
                $qrData .= "Grade Level: " . ($user_data['grade_level'] ?? 'Not provided') . "%0A";
                $qrData .= "Guardian: " . ($user_data['guardian_name'] ?? 'Not provided') . "%0A";
                $qrData .= "Address: " . ($user_data['address'] ?? 'Not provided') . "%0A";
                $qrData .= "Contact: " . ($user_data['contact'] ?? 'Not provided');
                ?>
                <div class="qr-code-container">
                    <img src="https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=<?= urlencode($qrData) ?>" alt="Profile QR Code">
                    <p>Scan to see full student details</p>
                </div>
                
                <div class="action-buttons mt-4">
                   
                    <a href="edit_profile.php" class="btn btn-primary">
                        <i class="bi bi-pencil-square me-1"></i> Update Profile
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Photo Upload Modal -->
    <div class="modal fade" id="photoUploadModal" tabindex="-1" aria-labelledby="photoUploadModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="photoUploadModalLabel">Change Profile Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="update_photo.php" method="POST" enctype="multipart/form-data">
                        <div class="photo-upload-area" id="dropZone" onclick="document.getElementById('profilePhotoInput').click();">
                            <i class="bi bi-cloud-arrow-up" style="font-size: 2rem; color: #003366;"></i>
                            <p class="mt-2">Drag & drop your photo here or click to browse</p>
                            <input type="file" id="profilePhotoInput" name="profile_photo" accept="image/*" style="display: none;">
                        </div>
                        <div class="text-center">
                            <img id="photoPreview" style="max-width: 200px; max-height: 200px; border-radius: 50%; display: none; margin: 0 auto 15px auto;">
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">Save New Photo</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS and custom script -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview uploaded photo
        document.getElementById('profilePhotoInput').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('photoPreview');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
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
    </script>
</body>
</html>