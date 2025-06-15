<?php
session_start();
include '../Connection/database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: Loginpage.php');
    exit();
}

try {
    $user_id = $_SESSION['user_id'];
    $query = "SELECT firstname, lastname, contact, lrn FROM users WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    // Bind parameter
    $stmt->bind_param("i", $user_id); // "i" stands for integer

    // Execute statement
    $stmt->execute();

    // Get result
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();

    if (!$user_data) {
        throw new Exception("User data not found");
    }

    $_SESSION['user_data'] = $user_data;
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Select Document</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .select-container {
            max-width: 600px;
            margin: 50px auto;
            padding: 30px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            animation: slideIn 0.5s ease-out;
        }
        @keyframes slideIn {
            from {
                transform: translateY(-30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        .select-title {
            color: #2c3e50;
            margin-bottom: 30px;
            text-align: center;
            font-size: 2em;
            font-weight: 600;
        }
        .form-select {
            padding: 15px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s ease;
        }
        .form-select:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        .btn-proceed {
            background: #3498db;
            color: white;
            padding: 12px 30px;
            border-radius: 10px;
            border: none;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 20px;
        }
        .btn-proceed:hover {
            background: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
    </style>
</head>
<body>
    <div class="select-container">
        <h2 class="select-title">Select Document to Request</h2>
        <form action="information_form.php" method="POST">
            <select name="document_type" class="form-select mb-4" required>
                <option value="">Choose Document Type</option>
                <option value="Enrollment Form">Enrollment Form</option>
                <option value="TOR">Transcript of Records (TOR)</option>
                <option value="Form 137">Form 137</option>
                <option value="Copy of Grades">Copy of Grades</option>
                <option value="Certificate of Enrollment">Certificate of Enrollment</option>
            </select>
            <button type="submit" class="btn btn-proceed">Proceed to Information Form</button>
        </form>
    </div>
</body>
</html>
