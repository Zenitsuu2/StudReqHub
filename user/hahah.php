<?php

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to your dashboard</title>
    <link rel="stylesheet" href="styles.css"> <!-- External CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            display: flex;
            flex-direction: column;
        }
        .header {
            background: linear-gradient(to right, #2c3e50, #1abc9c);
            color: white;
            padding: 10px 20px;
            text-align: center;
            font-size: 24px;
            position: relative;
        }
        .content-wrapper {
            display: flex;
            flex-grow: 1;
        }
        .sidebar {
            width: 300px;
            background: #2c3e50;
            color: white;
            height: 100vh;
            padding: 20px;
            box-sizing: border-box;
        }
        .school-logo {
            width: 100%;
            max-width: 125px;
            display: block;
            margin: 0 auto 10px;
        }
        .profile-pic {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            display: block;
            margin: 10px auto;
        }
        .sidebar h2, .sidebar h3 {
            text-align: center;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
        }
        .sidebar ul li {
            margin: 15px 0;
        }
        .sidebar ul li a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 10px;
            background: #34495e;
            border-radius: 5px;
            text-align: center;
        }
        .sidebar ul li a:hover, .sidebar ul li a.active {
            background: #1abc9c;
        }
        .dropdown {
            position: relative;
            display: block;
            text-align: center;
        }
        .dropdown-content {
            display: none;
            position: absolute;
            background: #34495e;
            left: 0;
            width: 100%;
            z-index: 1;
        }
        .dropdown:hover .dropdown-content {
            display: block;
        }
        .dropdown-content a {
            display: block;
            padding: 10px;
            color: white;
            text-decoration: none;
            text-align: center;
        }
        .main-content {
            flex-grow: 1;
            padding: 20px;
            background: #ecf0f1;
        }
        .notification {
            text-align: right;
            position: relative;
        }
        .notification i {
            font-size: 20px;
        }
        .badge {
            background: red;
            color: white;
            padding: 3px 7px;
            border-radius: 50%;
            position: absolute;
            top: -5px;
            right: -10px;
        }
        .task-summary {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 30px;
            margin-top: 20px;
            padding: 20px;
        }
        .task-box {
            background: #34495e;
            color: white;
            padding: 60px;
            text-align: center;
            border-radius: 15px;
            font-size: 24px;
            cursor: pointer;
            transition: transform 0.3s, background 0.3s;
        }
        .task-box:hover {
            background: #1abc9c;
            transform: scale(1.05);
        }
    </style>
</head>
<body>
    <div class="header">Welcome to Villa Teodora Elementary School Dashboard</div>
    <div class="content-wrapper">
        <div class="sidebar">
            <div class="school-info">
                <img src="school logo/school logo.png" alt="School Logo" class="school-logo">
                <h2>Villa Teodora Elementary School</h2>
            </div>
            <div class="profile">
                <img src="profile/sample profile.jpg" alt="Student Profile" class="profile-pic">
                <h3>@Justine Gabriel Manaloto C.</h3>
            </div>
            <nav>
                <ul>
                    <li><a href="#" class="active"><i class="fas fa-tachometer-alt"></i> Dashboard</a></li>
                    <li class="dropdown">
                        <a href="#"><i class="fas fa-tasks"></i> My Task</a>
                        <div class="dropdown-content">
                            <a href="task1.php">Diploma for Grade 6</a>
                            <a href="task2.php">Diploma for Kinder</a>
                            <a href="task3.php">Report Card</a>
                        </div>
                    </li>
                    <li><a href="#"><i class="fas fa-user"></i> Profile</a></li>
                    <li><a href="#"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="#"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                </ul>
            </nav>
        </div>
        <div class="main-content">
            <div class="notification">
                <i class="fas fa-bell"></i>
                <span class="badge">4</span>
            </div>
            <h1>Welcome to User Dashboard</h1>
            <div class="task-summary">
                <div class="task-box" onclick="location.href='dashboard_user.php?step=request_form';">Request Information</div>
                <div class="task-box" onclick="location.href='overdue.php';">Track Request</div>
                <div class="task-box" onclick="location.href='pending.php';">History</div>
                <div class="task-box" onclick="location.href='completed.php';">Request Form</div>
            </div>
        </div>
    </div>
</body>
</html>