<?php 
session_start();
include '../../Connection/database.php'; // Adjust the path to your database connection file

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../user/Loginpage.php');
    exit();
}

// Retrieve user data from the session
$user_id = $_SESSION['user_id'] ?? null;
$user_data = $_SESSION['user_data'] ?? null;
$firstname = $user_data['firstname'] ?? 'Guest';
$lastname = $user_data['lastname'] ?? '';
$username = $firstname . ' ' . $lastname;

// Fetch user data from the database to ensure we have the latest info
if ($user_id) {
    $query = "SELECT * FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user_data = $result->fetch_assoc();
        $_SESSION['user_data'] = $user_data; // Update the session with latest data
        $firstname = $user_data['firstname'] ?? 'Guest';
        $lastname = $user_data['lastname'] ?? '';
        $username = $firstname . ' ' . $lastname;
    }
}

// Handle receiving a request with ULI verification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_receipt'])) {
    $request_id = $_POST['request_id'];
    $submitted_uli = $_POST['uli_number'];
    
    // First, verify if the ULI matches the user's ULI
    $uli_query = "SELECT uli FROM users WHERE id = ?";
    $stmt = $conn->prepare($uli_query);
    
    if (!$stmt) {
        error_log("Database error verifying ULI: " . $conn->error);
        $update_error = "Error verifying ULI: " . $conn->error;
    } else {
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows === 1) {
            $user_data = $result->fetch_assoc();
            $actual_uli = $user_data['uli'];
            
            // Check if ULI matches
            if ($submitted_uli === $actual_uli) {
                // ULI matches, update the request status to "Received"
                $update_query = "UPDATE requests SET status = 'Received', received_date = NOW(), 
                                  received_by = ? WHERE id = ? AND user_id = ?";
                $stmt = $conn->prepare($update_query);
                
                if (!$stmt) {
                    error_log("Database error updating request: " . $conn->error);
                    $update_error = "Error updating request status: " . $conn->error;
                } else {
                    $stmt->bind_param('sii', $username, $request_id, $user_id);
                    
                    if ($stmt->execute()) {
                        // Set success message for SweetAlert
                        $_SESSION['receipt_success'] = true;
                        
                        // Insert record into request_history table for admin tracking
                        $history_query = "INSERT INTO request_history 
                                        (request_id, status_change, changed_by, changed_on, notes) 
                                        VALUES (?, 'Received', ?, NOW(), 'Document received by student')";
                        $history_stmt = $conn->prepare($history_query);
                        
                        if ($history_stmt) {
                            $history_stmt->bind_param('is', $request_id, $username);
                            $history_stmt->execute();
                        } else {
                            // Log the error but don't show to user since the primary action succeeded
                            error_log("Could not insert into history: " . $conn->error);
                        }
                    } else {
                        $update_error = "Error executing update: " . $stmt->error;
                    }
                }
            } else {
                // ULI doesn't match
                $_SESSION['receipt_error'] = "The ULI you entered is incorrect. Please try again.";
            }
        } else {
            $_SESSION['receipt_error'] = "Could not verify your identity. Please try again.";
        }
        
        // Redirect to refresh the page after processing
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Make sure to add this near the top of your script to display messages after redirect
$receipt_success = $_SESSION['receipt_success'] ?? false;
$receipt_error = $_SESSION['receipt_error'] ?? false;

// Clear the session variables to prevent showing the message again on refresh
if (isset($_SESSION['receipt_success'])) {
    unset($_SESSION['receipt_success']);
}
if (isset($_SESSION['receipt_error'])) {
    unset($_SESSION['receipt_error']);
}

// Get the profile picture path - make sure to use the correct field name
$profile_pic = $user_data['picture'] ?? 'default_profile.jpg';

// Use an absolute path for the profile picture
$profile_pic_path = '../../uploads/' . $profile_pic; // Adjust to the correct path to your uploads directory

// Check if the file exists, otherwise use the default profile picture
if (!file_exists($profile_pic_path)) {
    $profile_pic_path = '../../uploads/default_profile.jpg';
}

// Fetch events from the database
$notifications = [];
$important_dates = [];
$upcoming_events = [];

$query = "SELECT title, description, start_date, end_date, event_type 
          FROM events 
          WHERE end_date >= CURDATE() 
          ORDER BY start_date ASC";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    $processed_dates = []; // Array to track processed dates
    
    while ($event = $result->fetch_assoc()) {   
        $start_date = new DateTime($event['start_date']);
        $end_date = new DateTime($event['end_date']);
        $current_date = new DateTime();
        
        $event_title = $event['title'];
        $formatted_start = $start_date->format('F d, Y');
        $date_key = $start_date->format('Y-m-d'); // Create a unique key for each date
        
        // Only process if we haven't seen this date before
        if (!isset($processed_dates[$date_key])) {
            // Only add to notifications if the event is within the next 7 days
            $days_until = $current_date->diff($start_date)->days;
            if ($days_until <= 7 && $start_date >= $current_date) {
                $notifications[] = [
                    'message' => "{$event_title} is happening on {$formatted_start}.",
                    'status' => '',
                    'request_id' => 0,
                    'date' => $start_date
                ];
            }

            // Only add to important dates if the event is current or future
            if ($end_date >= $current_date && 
                in_array($event['event_type'], ['Graduation Day', 'Exam Schedule', 'Enrollment Period'])) {
                $important_dates[] = [
                    'date' => $start_date,
                    'text' => "{$formatted_start} - {$event_title}"
                ];
            }

            // Only add to upcoming events if the event is in the future
            if ($start_date >= $current_date) {
                $upcoming_events[] = [
                    'date' => $start_date,
                    'text' => "{$formatted_start} - {$event_title}"
                ];
            }

            // Mark this date as processed
            $processed_dates[$date_key] = true;
        }
    }
    
    // Sort arrays by date
    usort($notifications, function($a, $b) {
        return $a['date'] <=> $b['date'];
    });
    
    // Remove duplicates from important dates based on date and text
    $important_dates = array_values(array_unique(array_map(function($date) {
        return json_encode(['date' => $date['date']->format('Y-m-d'), 'text' => $date['text']]);
    }, $important_dates)));
    $important_dates = array_map(function($date) {
        $decoded = json_decode($date, true);
        return ['date' => new DateTime($decoded['date']), 'text' => $decoded['text']];
    }, $important_dates);
    
    // Sort important dates
    usort($important_dates, function($a, $b) {
        return $a['date'] <=> $b['date'];
    });
    
    // Remove duplicates from upcoming events based on date and text
    $upcoming_events = array_values(array_unique(array_map(function($event) {
        return json_encode(['date' => $event['date']->format('Y-m-d'), 'text' => $event['text']]);
    }, $upcoming_events)));
    $upcoming_events = array_map(function($event) {
        $decoded = json_decode($event, true);
        return ['date' => new DateTime($decoded['date']), 'text' => $decoded['text']];
    }, $upcoming_events);
    
    // Sort upcoming events
    usort($upcoming_events, function($a, $b) {
        return $a['date'] <=> $b['date'];
    });
}

// Fetch document request statuses for the logged-in user
$query = "SELECT r.id AS request_id, r.document_type, r.status, u.firstname, u.lastname 
          FROM requests r 
          JOIN users u ON r.user_id = u.id 
          WHERE r.user_id = ? 
          ORDER BY r.eta DESC";

$stmt = $conn->prepare($query);

if (!$stmt) {
    // Log the error for debugging
    error_log("Database error in home.php: " . $conn->error);
    // Display a friendly message instead of dying
    $db_error = "We're experiencing some technical difficulties. Please try again later.";
} else {
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $document_type = $row['document_type'] ?? 'Document';
            $status = ucfirst($row['status']); // Capitalize the first letter of the status
            $notifications[] = [
                'message' => "Your request for <strong>{$document_type}</strong> is <strong>{$status}</strong>.",
                'status' => $status,
                'request_id' => $row['request_id']
            ];
        }
    }
}

// Handle login POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];

    // Query to check user credentials
    $query = "SELECT id, firstname, lastname, picture FROM users WHERE username = ? AND password = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('ss', $username, $password);
    $stmt->execute();
    $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();

        // Store user data in session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_data'] = $user;

        // Redirect to the dashboard
        header('Location: ../test/home.php');
        exit();
    } else {
        echo "Invalid username or password.";
    }
}

// At the top, after starting session, set (or check for) a flag from login.
// For example, in your login processing, set $_SESSION['just_logged_in'] = true;
// Then here:
$just_logged_in = $_SESSION['just_logged_in'] ?? false;
if($just_logged_in){
    unset($_SESSION['just_logged_in']); // clear the flag after use
}

// Check if this is an AJAX POST submission for a Certificate of Enrollment request.
// (You can adjust the condition—here we check if the POST contains 'document_type' equal to 'Certificate of Enrollment'.)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['document_type']) && $_POST['document_type'] === 'Certificate of Enrollment') {
    header('Content-Type: application/json');
    $user_id = $_SESSION['user_id'] ?? 0;
    $purpose = $_POST['purpose'] ?? '';
    $school_year_start = $_POST['school_year_start'] ?? '';
    $school_year_end = $_POST['school_year_end'] ?? '';
    $priority = $_POST['priority'] ?? '';

    // Perform your INSERT query
    $query = "INSERT INTO requests (user_id, document_type, purpose, school_year_start, school_year_end, priority, status, created_at)
              VALUES (?, 'Certificate of Enrollment', ?, ?, ?, ?, 'Pending', NOW())";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit();
    }
    // Adjust bind_param types if needed (here 'i' for user_id and 's' for the rest).
    $stmt->bind_param('issss', $user_id, $purpose, $school_year_start, $school_year_end, $priority);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Your Certificate of Enrollment request has been submitted successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit request: ' . $stmt->error]);
    }
    exit();
}

// Process AJAX request for Certificate of Enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['document_type']) && $_POST['document_type'] === 'Certificate of Enrollment') {
    header('Content-Type: application/json');
    $user_id = $_SESSION['user_id'] ?? 0;
    $purpose = $_POST['purpose'] ?? '';
    $school_year_start = $_POST['school_year_start'] ?? '';
    $school_year_end = $_POST['school_year_end'] ?? '';
    $priority = $_POST['priority'] ?? '';

    $query = "INSERT INTO requests (user_id, document_type, purpose, school_year_start, school_year_end, priority, status, created_at)
              VALUES (?, 'Certificate of Enrollment', ?, ?, ?, ?, 'Pending', NOW())";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit();
    }
    $stmt->bind_param('issss', $user_id, $purpose, $school_year_start, $school_year_end, $priority);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Your Certificate of Enrollment request has been submitted successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to submit request: ' . $stmt->error]);
    }
    exit();
}

// Process AJAX request for Diploma
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['document_type']) && $_POST['document_type'] === 'Diploma') {
    header('Content-Type: application/json');
    $user_id = $_SESSION['user_id'] ?? 0;
    $purpose = $_POST['purpose'] ?? '';
    // Use the same field names as in your form for start & end year:
    $school_year_start = $_POST['school_year_start'] ?? '';
    $school_year_end = $_POST['school_year_end'] ?? '';
    $priority = $_POST['priority'] ?? '';

    $query = "INSERT INTO requests (user_id, document_type, purpose, school_year_start, school_year_end, priority, status, created_at)
              VALUES (?, 'Diploma', ?, ?, ?, ?, 'Pending', NOW())";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        exit();
    }
    $stmt->bind_param('issss', $user_id, $purpose, $school_year_start, $school_year_end, $priority);
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
    <title>User Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Add SweetAlert CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #f4f6f9;
            transition: all 0.3s ease;
        }

        .dark-mode {
            background-color: #121212;
            color: white;
        }

        .dark-mode .action-card,
        .dark-mode .info-card {
            background-color: #1e1e1e;
            color: #e0e0e0;
        }

        /* Refined Sidebar Styles */
        .sidebar {
            width: 280px;
            background-color: #003366;
            color: white;
            height: 100vh;
            position: fixed;
            padding: 20px 0;
            display: flex;
            flex-direction: column;
            box-shadow: 4px 0px 12px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            z-index: 999;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 0 25px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 15px;
        }

        .sidebar .logo {
            width: 120px;
            display: block;
            margin: 0 auto 10px;
        }

        .sidebar .school-name {
            font-size: 14px;
            text-align: center;
            margin-bottom: 15px;
            font-weight: 600;
            opacity: 0.8;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        .user-profile {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 15px 25px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }

        .sidebar .profile-pic {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid rgba(255,255,255,0.2);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .sidebar .user-name {
            font-size: 18px;
            text-align: center;
            margin: 10px 0 0;
            font-weight: 600;
            color: #ffffff;
            letter-spacing: 0.5px;
        }

        .sidebar-menu {
            flex: 1;
            padding: 0 15px;
        }

        .sidebar a {
            color: rgba(255,255,255,0.85);
            text-decoration: none;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            border-radius: 8px;
            font-size: 15px;
            margin-bottom: 8px;
            transition: all 0.2s ease;
        }

        .sidebar a i {
            margin-right: 12px;
            font-size: 18px;
            min-width: 25px;
            text-align: center;
        }

        .sidebar a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            transform: translateX(5px);
        }

        .sidebar a.active {
            background: rgba(255,255,255,0.15);
            color: white;
            font-weight: 500;
        }

        .sidebar-footer {
            padding: 15px 25px;
            border-top: 1px solid rgba(255,255,255,0.1);
        }

        .mode-toggle {
            display: flex;
            align-items: center;
            cursor: pointer;
            opacity: 0.85;
            transition: opacity 0.3s;
            font-size: 14px;
            font-weight: 500;
            padding: 10px 0;
        }

        .mode-toggle:hover {
            opacity: 1;
        }

        .mode-toggle i {
            margin-right: 8px;
            font-size: 16px;
        }

        .sidebar .close-btn {
            display: none;
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 24px;
            color: white;
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.3s;
        }

        .sidebar .close-btn:hover {
            opacity: 1;
        }

        .header {
            background: linear-gradient(to right, #003366, #00509e);
            color: white;
            padding: 20px 40px;
            margin-left: 280px;
            font-size: 28px;
            font-weight: 600;
            text-align: center;
            transition: margin-left 0.3s ease;
        }

        .main-content {
            margin-left: 280px;
            padding: 40px;
            transition: margin-left 0.3s ease;
        }

        .action-cards {
            display: flex;
            flex-wrap: wrap;
            gap: 30px;
            margin-bottom: 40px;
        }

        .action-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            flex: 1 1 250px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.1);
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.15);
        }

        .action-card.active {
            background-color: #00509e;
            color: white;
        }

        .action-card.active i {
            color: white;
        }

        .action-card i {
            font-size: 40px;
            margin-bottom: 15px;
            color: #00509e;
        }

        .info-card {
            background: #f9f9f9;
            border-left: 5px solid #00509e;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            padding: 25px;
            border-radius: 10px;
            transition: background 0.3s;
            margin-bottom: 30px;
        }

        .info-card:hover {
            background: #e9f5ff;
        }

        .info-card h6 {
            color: #00509e;
            font-weight: 600;
            margin-bottom: 15px;
        }

        .info-card i {
            font-size: 32px;
            color: #00509e;
            margin-bottom: 12px;
        }
        
        /* Profile information styles */
        .profile-info {
            display: none;
        }
        
        .profile-info.active {
            display: block;
        }
        
        .profile-info h5 {
            color: #003366;
            margin-bottom: 20px;
        }
        
        .profile-details {
            background: #fff;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .profile-details table {
            width: 100%;
        }
        
        .profile-details table td {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .profile-details table td:first-child {
            font-weight: 600;
            width: 40%;
            color: #00509e;
        }
        
        .edit-btn {
            background: #00509e;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .edit-btn:hover {
            background: #003366;
        }

        /* Notification item */
        .notification-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(0,0,0,0.05);
        }

        .notification-content {
            flex: 1;
        }

        .confirm-pickup {
            white-space: nowrap;
            margin-left: 10px;
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 250px;
                padding: 20px 0;
            }

            .header {
                margin-left: 0;
                font-size: 24px;
                padding: 20px;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .action-cards {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 20px;
            }

            .action-card {
                flex: 1 1 100%;
                margin-bottom: 20px;
            }

            .sidebar a {
                font-size: 14px;
            }

            .sidebar .close-btn {
                display: block;
            }
        }

        @media (max-width: 576px) {
            .sidebar {
                width: 80%;
                max-width: 300px;
                left: -100%;
                top: 0;
                position: fixed;
                transition: left 0.3s ease;
            }

            .header {
                font-size: 18px;
                padding: 10px 15px;
                margin-left: 0;
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .main-content {
                padding: 15px;
                margin-left: 0;
            }

            .action-cards {
                display: grid;
                grid-template-columns: 1fr;
                gap: 15px;
            }

            .menu-btn {
                display: block;
                font-size: 24px;
                background: none;
                border: none;
                color: white;
                cursor: pointer;
            }

            body.sidebar-open {
                overflow: hidden;
            }

            .main-content::before {
                content: "";
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.4);
                z-index: 998;
                display: none;
            }

            body.sidebar-open .main-content::before {
                display: block;
            }
        }

        // Add this to your existing style section
        .badge {
            padding: 5px 10px;
            font-size: 0.75rem;
            font-weight: 500;
            color: white;
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .badge:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.15);
        }

        /* Dark mode support */
        .dark-mode .badge {
            opacity: 0.9;
        }
    </style>
</head>
<body>

<!-- Cleaned and Professional Sidebar -->
<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="school logo/logo.png" class="logo" alt="School Logo">
        <p class="school-name">Villa Teodora Elementary School</p>
    </div>
    
    <div class="user-profile">
        <img src="<?= htmlspecialchars($profile_pic_path) ?>" alt="Profile Picture" class="profile-pic">
        <p class="user-name"><?= htmlspecialchars($username) ?></p>
    </div>
    
    <div class="sidebar-menu">
        <a href="javascript:void(0)" onclick="showDashboard()" class="active"><i class="bi bi-house-door"></i>Dashboard</a>
        <a href="my_profile.php"><i class="bi bi-person"></i>My Profile</a>
        <a href="history_request.php"><i class="bi bi-clock-history"></i>History</a>
        <a href="notification.php"><i class="bi bi-bell"></i>Notifications</a>
        <a href="../../user/Loginpage.php"><i class="bi bi-box-arrow-right"></i>Logout</a>
    </div>
    
    <div class="sidebar-footer">
        <div class="mode-toggle" onclick="toggleMode()">
            <i class="bi bi-moon"></i> Toggle Dark Mode
        </div>
    </div>
    
    <span class="close-btn" onclick="toggleSidebar()">×</span>
</div>

<!-- Header -->
<div class="header">
    <button class="menu-btn d-lg-none" onclick="toggleSidebar()">☰</button>
    <span>Welcome to Your Dashboard</span>
</div>

<!-- Main Content -->
<div class="main-content">
    <!-- Display any error messages -->
    <?php if (isset($db_error)): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($db_error) ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($update_error)): ?>
        <div class="alert alert-danger" role="alert">
            <?= htmlspecialchars($update_error) ?>
        </div>
    <?php endif; ?>

    <!-- Dashboard Section -->
    <div id="dashboard-content">
        <div class="action-cards">
            <div class="action-card" data-bs-toggle="modal" data-bs-target="#diplomaRequestModal">
                <i class="bi bi-file-earmark-plus-fill"></i>
                <h6>Request Diploma</h6>
            </div>

            <div class="action-card" data-bs-toggle="modal" data-bs-target="#enrollmentCertRequestModal">
                <i class="bi bi-file-earmark-text-fill"></i>
                <h6>Request Certificate of Enrollment</h6>
            </div>

            <div class="action-card" onclick="window.location.href='request_other_document.php'"><i class="bi bi-file-earmark-arrow-down-fill"></i><h6>Other Document</h6></div>
            <div class="action-card" data-href="track_request.php"><i class="bi bi-search"></i><h6>Track Request</h6></div>
            <div class="action-card" data-href="chat_bot.php"><i style="font-size: 40px; margin-right: 8px;">🤖</i><h6>Need Help?</h6>
</div>

        </div>

        <div class="info-card">
            <i class="bi bi-calendar-check me-2 text-success"></i>
            <h6>Important Dates</h6>
            <ul class="mt-3">
                <?php if (!empty($important_dates)): ?>
                    <?php foreach ($important_dates as $date): ?>
                        <li class="mb-2">
                            <?= htmlspecialchars($date['text']) ?>
                            <?php 
                            $days_until = (new DateTime())->diff($date['date'])->days;
                            if ($days_until === 0): ?>
                                <span class="badge" style="background-color: #003366;">Today!</span>
                            <?php elseif ($days_until <= 3): ?>
                                <span class="badge" style="background-color: #00509e;">In <?= $days_until ?> days</span>
                            <?php elseif ($days_until <= 7): ?>
                                <span class="badge" style="background-color: #0073e6;">In <?= $days_until ?> days</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No upcoming important dates at this time.</li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="info-card">
            <i class="bi bi-calendar-plus me-2 text-info"></i>
            <h6>Upcoming Events</h6>
            <ul class="mt-3">
                <?php if (!empty($upcoming_events)): ?>
                    <?php foreach ($upcoming_events as $event): ?>
                        <li class="mb-2">
                            <?= htmlspecialchars($event['text']) ?>
                            <?php 
                            $days_until = (new DateTime())->diff($event['date'])->days;
                            if ($days_until === 0): ?>
                                <span class="badge" style="background-color: #003366;">Today!</span>
                            <?php elseif ($days_until <= 3): ?>
                                <span class="badge" style="background-color: #00509e;">In <?= $days_until ?> days</span>
                            <?php elseif ($days_until <= 7): ?>
                                <span class="badge" style="background-color: #0073e6;">In <?= $days_until ?> days</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No upcoming events at this time.</li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="info-card">
            <i class="bi bi-bell-fill me-2 text-warning"></i>
            <h6>Recent Notifications</h6>
            <ul class="mt-3">
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $note): ?>
                        <li class="notification-item">
                            <div class="notification-content">
                                <?= $note['message'] ?>
                            </div>
                            <?php if (isset($note['status']) && $note['status'] === 'Ready to Pickup'): ?>
                                <button type="button" class="btn btn-success btn-sm confirm-pickup" 
                                        data-request-id="<?= htmlspecialchars($note['request_id']) ?>">
                                    <i class="bi bi-check-circle"></i> Confirm Receipt
                                </button>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li>No recent notifications.</li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
    
    <!-- Profile section (empty div for now, content will be loaded via AJAX or on another page) -->
    <div id="profile-content" style="display: none;">
        <!-- Profile content will be loaded here or on my_profile.php -->
    </div>
</div>

<!-- Hidden form for ULI verification -->
<form id="receive-form" method="POST" action="<?= $_SERVER['PHP_SELF'] ?>" style="display: none;">
    <input type="hidden" name="request_id" id="confirm-request-id" value="">
    <input type="hidden" name="uli_number" id="confirm-uli" value="">
    <input type="hidden" name="confirm_receipt" value="1">
</form>

<!-- Diploma Request Modal -->
<div class="modal fade" id="diplomaRequestModal" tabindex="-1" aria-labelledby="diplomaRequestModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="diplomaRequestForm" method="POST" action="home.php">
        <div class="modal-header">
          <h5 class="modal-title" id="diplomaRequestModalLabel">Request Diploma</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <!-- Full Name (Display Only) -->
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($user_data['firstname'] . ' ' . $user_data['lastname']) ?>" disabled>
          </div>
          <!-- LRN (Display Only) -->
          <div class="mb-3">
            <label class="form-label">LRN</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($user_data['lrn']) ?>" disabled>
          </div>
          <!-- Document Type is fixed here -->
          <input type="hidden" name="document_type" value="Diploma">
          <!-- Purpose (Text input) -->
          <div class="mb-3">
            <label for="diplomaPurpose" class="form-label">Purpose</label>
            <input type="text" name="purpose" id="diplomaPurpose" class="form-control" required>
          </div>
          <!-- School Year Picker -->
          <div class="mb-3">
            <label for="schoolYearStart" class="form-label">School Year - Start Year</label>
            <input type="number" name="school_year_start" id="schoolYearStart" class="form-control" placeholder="e.g. 2022" min="2000" max="2100" required>
          </div>
          <div class="mb-3">
            <label for="schoolYearEnd" class="form-label">School Year - End Year</label>
            <input type="number" name="school_year_end" id="schoolYearEnd" class="form-control" placeholder="e.g. 2023" min="2001" max="2101" required>
          </div>
          <!-- Priority Dropdown -->
          <div class="mb-3">
            <label for="diplomaPriority" class="form-label">Priority</label>
            <select name="priority" id="diplomaPriority" class="form-select" required>
              <option value="">Select Priority</option>
              <option value="Normal">Normal</option>
              <option value="Urgent">Urgent</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Submit Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Enrollment Certificate Request Modal -->
<div class="modal fade" id="enrollmentCertRequestModal" tabindex="-1" aria-labelledby="enrollmentCertRequestModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="enrollmentCertRequestForm" method="POST" action="home.php">
        <div class="modal-header">
          <h5 class="modal-title" id="enrollmentCertRequestModalLabel">Request Certificate of Enrollment</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <!-- Full Name (Display Only) -->
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($user_data['firstname'].' '.$user_data['lastname']) ?>" disabled>
          </div>
          <!-- LRN (Display Only) -->
          <div class="mb-3">
            <label class="form-label">LRN</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($user_data['lrn']) ?>" disabled>
          </div>
          <!-- Document Type is fixed here -->
          <input type="hidden" name="document_type" value="Certificate of Enrollment">
          <!-- Purpose Dropdown -->
          <div class="mb-3">
            <label for="enrollmentPurpose" class="form-label">Purpose</label>
            <select name="purpose" id="enrollmentPurpose" class="form-select" required>
              <option value="">Select Purpose</option>
              <option value="Enrollment Purposes">Enrollment Purposes</option>
              <option value="Educational Assistance Purposes">Educational Assistance Purposes</option>
              <option value="Pantawid Pamilya Pilipino Program (4Ps) Purposes">Pantawid Pamilya Pilipino Program (4Ps) Purposes</option>
              <option value="Legal Purposes">Legal Purposes</option>
              <option value="Bank Account Application">Bank Account Application</option>
              <option value="Passport Application">Passport Application</option>
              <option value="Birth certificate Application">Birth certificate Application</option>
            </select>
          </div>
          <!-- School Year Picker -->
          <div class="mb-3">
            <label for="schoolYearStartEnroll" class="form-label">School Year - Start Year</label>
            <input type="number" name="school_year_start" id="schoolYearStartEnroll" class="form-control" placeholder="e.g. 2022" min="2000" max="2100" required>
          </div>
          <div class="mb-3">
            <label for="schoolYearEndEnroll" class="form-label">School Year - End Year</label>
            <input type="number" name="school_year_end" id="schoolYearEndEnroll" class="form-control" placeholder="e.g. 2023" min="2001" max="2101" required>
          </div>
          <!-- Priority Dropdown -->
          <div class="mb-3">
            <label for="enrollmentPriority" class="form-label">Priority</label>
            <select name="priority" id="enrollmentPriority" class="form-select" required>
              <option value="">Select Priority</option>
              <option value="Normal">Normal</option>
              <option value="Urgent">Urgent</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Submit Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Replace reportCardRequestModal with this -->


<!-- Request Form 137 Modal -->
<div class="modal fade" id="requestForm137Modal" tabindex="-1" aria-labelledby="requestForm137ModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form id="requestForm137Form" method="POST" action="request_form137_process.php">
        <div class="modal-header">
          <h5 class="modal-title" id="requestForm137ModalLabel">Request Document</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <!-- Full Name (Display Only) -->
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" class="form-control" value="<?= $user_data['firstname'] . ' ' . $user_data['lastname'] ?>" disabled>
          </div>
          <!-- LRN (Display Only) -->
          <div class="mb-3">
            <label class="form-label">LRN</label>
            <input type="text" class="form-control" value="<?= $user_data['lrn'] ?>" disabled>
          </div>
          <!-- Document Type Dropdown -->
          <div class="mb-3">
            <label for="docType" class="form-label">Document Type</label>
            <select name="document_type" id="docType" class="form-select" required>
              <option value="">Select Document Type</option>
              <option value="Certificate of Enrollment">Certificate of Enrollment</option>
              <option value="Good Moral Certificate">Good Moral Certificate</option>
              <option value="Diploma">Diploma</option>
              <option value="Certificate of Completion of Kinder">Certificate of Completion of Kinder</option>
            </select>
          </div>
          <!-- Purpose: purposeField will be swapped based on document type -->
          <div class="mb-3" id="purposeDiv">
            <label for="purposeInput" class="form-label">Purpose</label>
            <!-- Default: text input -->
            <input type="text" name="purpose" id="purposeInput" class="form-control" placeholder="Enter Purpose" required>
          </div>
          <!-- School Year -->
        
          <!-- School Year Picker -->
          <div class="mb-3">
            <label for="schoolYearStart" class="form-label">School Year - Start Year</label>
            <input type="number" name="school_year_start" id="schoolYearStart" class="form-control" placeholder="e.g. 2022" min="2000" max="2100" required>
          </div>
          <div class="mb-3">
              <option value="Normal">Normal</option>
              <option value="Urgent">Urgent</option>
            </select>
          </div>
          <!-- Hidden field to help enforce max request count per document type -->
          <input type="hidden" name="user_id" value="<?= $user_id ?>">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="submit" id="submitRequest" class="btn btn-primary">Submit Request</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// When document type changes...
document.getElementById('docType').addEventListener('change', function() {
  const selected = this.value;
  const purposeDiv = document.getElementById('purposeDiv');
  
  if (selected === 'Certificate of Enrollment') {
    // Swap the purpose input to a dropdown with specific options
    purposeDiv.innerHTML = `
      <label for="purposeInput" class="form-label">Purpose</label>
      <select name="purpose" id="purposeInput" class="form-select" required>
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
    // Otherwise, show a text input
    purposeDiv.innerHTML = `
      <label for="purposeInput" class="form-label">Purpose</label>
      <input type="text" name="purpose" id="purposeInput" class="form-control" placeholder="Enter Purpose" required>
    `;
  }
});

// Optional: before submitting the form, you can check (via AJAX or a JS variable)
// if the user already has 3 requests for the selected document type.
// For this example, assume the check is done server‐side and returns an error message if exceeded.

// Handle form submission response with SweetAlert
document.getElementById('requestForm137Form').addEventListener('submit', function(e) {
  e.preventDefault();
  
  // Submit via AJAX (optional) or you can let the form submit normally.
  // In this example, we'll use fetch() for AJAX submission.
  const formData = new FormData(this);
  
  fetch(this.action, {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Use SweetAlert to show success message
      Swal.fire({
        icon: 'success',
        title: 'Request Submitted',
        text: data.message || 'Your request has been successfully submitted!',
        confirmButtonColor: '#003366'
      }).then(() => {
        // Optionally reload page or close modal
        window.location.reload();
      });
    } else {
      // Show error message
      Swal.fire({
        icon: 'error',
        title: 'Request Failed',
        text: data.message || 'There was an error processing your request. Please try again.',
        confirmButtonColor: '#dc3545'
      });
    }
  })
  .catch(error => {
    console.error('Error:', error);
    Swal.fire({
      icon: 'error',
      title: 'Request Failed',
      text: 'There was an error processing your request. Please try again.',
      confirmButtonColor: '#dc3545'
    });
  });
});
</script>

<script>
    // Show welcome message with SweetAlert when page loads
    document.addEventListener('DOMContentLoaded', function() {
        <?php if($just_logged_in): ?>
        Swal.fire({
            icon: 'success',
            title: 'Welcome to your dashboard!',
            text: 'Hello, <?= htmlspecialchars($username) ?>! We\'re glad to see you.',
            confirmButtonColor: '#003366',
            timer: 3000
        });
        <?php endif; ?>
        
        // Set active class on current page
        const currentPage = window.location.pathname;
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            if (link.getAttribute('href') !== '#' && currentPage.includes(link.getAttribute('href'))) {
                link.classList.add('active');
            }
        });
        
        // Check for receipt success message
        <?php if ($receipt_success): ?>
            Swal.fire({
                icon: 'success',
                title: 'Document Received',
                text: 'You have successfully confirmed receipt of your document.',
                confirmButtonColor: '#28a745'
            });
        <?php endif; ?>
        
        // Check for receipt error message
        <?php if ($receipt_error): ?>
            Swal.fire({
                icon: 'error',
                title: 'Verification Failed',
                text: '<?= htmlspecialchars($receipt_error) ?>',
                confirmButtonColor: '#dc3545'
            });
        <?php endif; ?>
        
        // Add event listeners to all confirm pickup buttons
        document.querySelectorAll('.confirm-pickup').forEach(button => {
            button.addEventListener('click', function() {
                const requestId = this.getAttribute('data-request-id');
                
                // Show SweetAlert with ULI input field
                Swal.fire({
                    title: 'Confirm Document Receipt',
                    text: 'Please enter your ULI (Unique Learner Identifier) to confirm receipt:',
                    input: 'text',
                    inputAttributes: {
                        autocapitalize: 'off',
                        required: 'required',
                        placeholder: 'Enter your ULI number'
                    },
                    showCancelButton: true,
                    confirmButtonText: 'Confirm Receipt',
                    confirmButtonColor: '#28a745',
                    cancelButtonText: 'Cancel',
                    showLoaderOnConfirm: true,
                    preConfirm: (uli) => {
                        if (!uli) {
                            Swal.showValidationMessage('ULI is required');
                            return false;
                        }
                        // Set the values in the hidden form
                        document.getElementById('confirm-request-id').value = requestId;
                        document.getElementById('confirm-uli').value = uli;
                        // Submit the form
                        document.getElementById('receive-form').submit();
                    }
                });
            });
        });
    });

    function toggleMode() {
        document.body.classList.toggle('dark-mode');
    }

    function toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        if (sidebar.style.left === '0px') {
            sidebar.style.left = '-100%';
            document.body.classList.remove('sidebar-open');
        } else {
            sidebar.style.left = '0';
            document.body.classList.add('sidebar-open');
        }
    }

    function showDashboard() {
        document.getElementById('dashboard-content').style.display = 'block';
        
        document.getElementById('profile-content').style.display = 'none';
        // Update active class in sidebar
        document.querySelectorAll('.sidebar-menu a').forEach(link => {
            link.classList.remove('active');
        });
        document.querySelector('.sidebar-menu a:first-child').classList.add('active');
    }

    // Make action cards clickable
    document.querySelectorAll('.action-card').forEach(card => {
        card.addEventListener('click', function() {
            const href = this.getAttribute('data-href');
            if (href) {
                window.location.href = href;
            }
        });
    });

    // Responsive sidebar toggle for mobile
    window.addEventListener('resize', function() {
        const sidebar = document.getElementById('sidebar');
        if (window.innerWidth > 992) {
            sidebar.style.left = '0';
        } else {
            sidebar.style.left = '-100%';
            document.body.classList.remove('sidebar-open');
        }
    });
</script>

<script>
// Add SweetAlert logout confirmation
document.addEventListener('DOMContentLoaded', function() {
    const logoutLink = document.querySelector('a[href="../../user/Loginpage.php"]');
    if (logoutLink) {
        logoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: "You will be logged out.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#003366',
                cancelButtonColor: '#dc3545',
                confirmButtonText: 'Yes, logout!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = logoutLink.getAttribute('href');
                }
            });
        });
    }
});
</script>

<script>
document.getElementById('schoolYearStart').addEventListener('change', function() {
    var startYear = parseInt(this.value);
    if (!isNaN(startYear)) {
        document.getElementById('schoolYearEnd').value = startYear + 1;
    }
});

// Update any existing JavaScript that references reportCardRequestModal
document.getElementById('enrollmentCertRequestModal').addEventListener('show.bs.modal', function (event) {
    // Your existing modal show logic
});
</script>

<script>
// Handle Enrollment Certificate Request form submission with AJAX
document.getElementById('enrollmentCertRequestForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const form = this;
  const formData = new FormData(form);
  
  // Optionally disable submit button to avoid multiple submissions
  const submitBtn = form.querySelector('button[type="submit"]');
  submitBtn.disabled = true;
  
  fetch(form.action, {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Request Submitted',
        text: data.message || 'Your request has been successfully submitted!',
        confirmButtonColor: '#003366'
      }).then(() => {
        // Optionally reload the page or simply close the modal
        form.reset();
        bootstrap.Modal.getInstance(document.getElementById('enrollmentCertRequestModal')).hide();
        window.location.reload();
      });
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Request Failed',
        text: data.message || 'There was an error processing your request. Please try again.',
        confirmButtonColor: '#dc3545'
      });
      submitBtn.disabled = false;
    }
  })
  .catch(error => {
    console.error('Error:', error);
    Swal.fire({
      icon: 'error',
      title: 'Request Failed',
      text: 'There was an error processing your request. Please try again.',
      confirmButtonColor: '#dc3545'
    });
    submitBtn.disabled = false;
  });
});
</script>

<script>
// Handle Diploma Request form submission with AJAX
document.getElementById('diplomaRequestForm').addEventListener('submit', function(e) {
  e.preventDefault();
  
  const form = this;
  const formData = new FormData(form);
  
  const submitBtn = form.querySelector('button[type="submit"]');
  submitBtn.disabled = true;
  
  fetch(form.action, {
    method: 'POST',
    body: formData
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Request Submitted',
        text: data.message || 'Your diploma request has been successfully submitted!',
        confirmButtonColor: '#003366'
      }).then(() => {
        form.reset();
        bootstrap.Modal.getInstance(document.getElementById('diplomaRequestModal')).hide();
        window.location.reload();
      });
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Request Failed',
        text: data.message || 'There was an error processing your request. Please try again.',
        confirmButtonColor: '#dc3545'
      });
      submitBtn.disabled = false;
    }
  })
  .catch(error => {
    console.error('Error:', error);
    Swal.fire({
      icon: 'error',
      title: 'Request Failed',
      text: 'There was an error processing your request. Please try again.',
      confirmButtonColor: '#dc3545'
    });
    submitBtn.disabled = false;
  });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

