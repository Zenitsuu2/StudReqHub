<?php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}


// Example session role can be 'teacher', 'parent', or 'student'.
// For testing, you can manually set: $_SESSION['role'] = 'teacher';
$role = $_SESSION['role'] ?? 'student';

$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>School Portal System</title>
    <!-- Bootstrap 5 CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <!-- Font Awesome (optional) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #20c997, #6f42c1);
            min-height: 100vh;
            margin: 0;
            display: flex;
            flex-direction: column;
        }
        .navbar {
            background: #273c75;
            padding: 15px 30px;
        }
        .navbar-brand {
            color: #fff;
            font-weight: 700;
            font-size: 1.25rem;
        }
        .nav-link {
            color: #f5f6fa !important;
            margin-right: 15px;
            transition: color 0.3s, border-bottom 0.3s;
        }
        .nav-link:hover {
            color: #c8d6e5 !important;
        }
        .nav-link.active {
            border-bottom: 3px solid #fff;
            padding-bottom: 3px;
            color: #ffffff !important;
        }
        .dashboard-container {
            flex: 1;
            padding: 30px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
        }
        .content-card {
            background: #fff;
            border-radius: 15px;
            padding: 20px;
            max-width: 900px;
            width: 100%;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-top: 20px;
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <!-- System name or logo -->
        <a class="navbar-brand" href="dashboard_user.php">
    <img src="../image/logo.jpg" alt="Logo" height="40" class="me-2">
    <i class="fa fa-school me-2"></i>School Portal
</a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" 
                data-bs-target="#navbarNav" aria-controls="navbarNav" 
                aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto">
                <!-- Common Links for All Users -->
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage === 'dashboard.php') ? 'active' : ''; ?>" 
                       href="home.php">HOME</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo ($currentPage === 'dashboard.php') ? 'active' : ''; ?>" 
                       href="home.php">EVENTS</a>
                </li>
                
                
               
                
                <!-- Teacher-Specific Links -->
                <?php if ($role === 'teacher'): ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage === 'teacher_panel.php') ? 'active' : ''; ?>" 
                           href="teacher_panel.php">Teacher Panel</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage === 'manage_students.php') ? 'active' : ''; ?>" 
                           href="manage_students.php">Manage Students</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($currentPage === 'attendance.php') ? 'active' : ''; ?>" 
                           href="attendance.php">Attendance</a>
                    </li>
                <?php endif; ?>
                
                <!-- Profile and Logout -->
                
                <li class="nav-item">
                    <a class="nav-link" href="Loginpage.php">Logout</a>
                    
                </li>
            </ul>
        </div>
    </div>
</nav>
