<?php
session_start();
include '../../Connection/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../user/Loginpage.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if a file was uploaded
if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
    // Check file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    $file_type = $_FILES['profile_photo']['type'];
    
    if (!in_array($file_type, $allowed_types)) {
        $_SESSION['error_message'] = "Only JPG, PNG and GIF files are allowed.";
        header('Location: profile.php');
        exit();
    }
    
    // Check file size (max 2MB)
    if ($_FILES['profile_photo']['size'] > 2 * 1024 * 1024) {
        $_SESSION['error_message'] = "File size should be less than 2MB.";
        header('Location: profile.php');
        exit();
    }
    
    // Create uploads directory if it doesn't exist
    $upload_dir = '../../uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate a unique filename
    $file_extension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
    $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
    $upload_path = $upload_dir . $new_filename;
    
    // Move the uploaded file
    if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $upload_path)) {
        // Delete old profile picture if exists
        $stmt = $conn->prepare("SELECT picture FROM users WHERE id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if (!empty($user['picture']) && file_exists($upload_dir . $user['picture'])) {
            unlink($upload_dir . $user['picture']);
        }
        
        // Update database with new profile picture
        $stmt = $conn->prepare("UPDATE users SET picture = ? WHERE id = ?");
        $stmt->bind_param('si', $new_filename, $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Profile picture updated successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to update profile picture in database.";
        }
    } else {
        $_SESSION['error_message'] = "Failed to upload the file. Please try again.";
    }
} else {
    $_SESSION['error_message'] = "No file uploaded or an error occurred.";
}

// Redirect back to profile page
header('Location: my_profile.php');
exit();
?>