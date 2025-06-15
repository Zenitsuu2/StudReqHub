<?php
session_start();
if (!isset($_SESSION['user_data']) || !isset($_SESSION['document_type'])) {
    header('Location: select_document.php');
    exit();
}

include '../Connection/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm'])) {
    $query = "INSERT INTO requests (user_id, document_type, purpose, school_year, status) 
              VALUES (?, ?, ?, ?, 'Pending')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('isss', $_SESSION['user_id'], $_SESSION['document_type'], $_POST['purpose'], $_POST['school_year']);
    $stmt->execute();
    
    header('Location: dashboard.php');
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Review Request</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #f6d365 0%, #fda085 100%);
            min-height: 100vh;
        }
        .review-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 40px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            animation: scaleIn 0.5s ease-out;
        }
        @keyframes scaleIn {
            from {
                transform: scale(0.95);
                opacity: 0;
            }
            to {
                transform: scale(1);
                opacity: 1;
            }
        }
        .review-title {
            color: #2c3e50;
            margin-bottom: 30px;
            text-align: center;
            font-size: 2em;
            font-weight: 600;
        }
        .review-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
        }
        .info-label {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        .info-value {
            color: #2c3e50;
            font-size: 1.1em;
            font-weight: 500;
            margin-bottom: 15px;
        }
        .btn-group {
            display: flex;
            gap: 15px;
        }
        .btn-confirm {
            background: #e74c3c;
            color: white;
            padding: 12px 30px;
            border-radius: 10px;
            border: none;
            transition: all 0.3s ease;
            flex: 1;
        }
        .btn-edit {
            background: #95a5a6;
            color: white;
            padding: 12px 30px;
            border-radius: 10px;
            border: none;
            transition: all 0.3s ease;
            flex: 1;
        }
        .btn-confirm:hover {
            background: #c0392b;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }
        .btn-edit:hover {
            background: #7f8c8d;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(149, 165, 166, 0.3);
        }
    </style>
</head>
<body>
    <div class="review-container">
        <h2 class="review-title">Review Your Request</h2>
        <div class="review-card">
            <div class="row">
                <div class="col-md-6">
                    <div class="info-label">Document Type</div>
                    <div class="info-value"><?php echo $_SESSION['document_type']; ?></div>
                    
                    <div class="info-label">Full Name</div>
                    <div class="info-value"><?php echo $_SESSION['user_data']['firstname'] . ' ' . $_SESSION['user_data']['lastname']; ?></div>
                </div>
                <div class="col-md-6">
                    <div class="info-label">Contact Number</div>
                    <div class="info-value"><?php echo $_SESSION['user_data']['contact']; ?></div>
                    
                    <div class="info-label">LRN</div>
                    <div class="info-value"><?php echo $_SESSION['user_data']['lrn']; ?></div>
                </div>
            </div>
            <div class="info-label">Purpose</div>
            <div class="info-value"><?php echo $_POST['purpose']; ?></div>
            
            <div class="info-label">School Year</div>
            <div class="info-value"><?php echo $_POST['school_year']; ?></div>
        </div>
        
        <form method="POST" class="btn-group">
            <input type="hidden" name="purpose" value="<?php echo $_POST['purpose']; ?>">
            <input type="hidden" name="school_year" value="<?php echo $_POST['school_year']; ?>">
            <a href="dashboard_user.php" class="btn btn-edit">Confirm</a>
            <a href="information_form.php" class="btn btn-edit">Edit Information</a>
        </form>
    </div>
</body>
</html>