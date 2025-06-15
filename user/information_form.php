<?php
session_start();
if (!isset($_SESSION['user_data']) || !isset($_POST['document_type'])) {
    header('Location: select_document.php');
    exit();
}
$user_data = $_SESSION['user_data'];
$_SESSION['document_type'] = $_POST['document_type'];
?>

<!DOCTYPE html>
<html>
<head>
    <title>Information Form</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #e0eafc 0%, #cfdef3 100%);
            min-height: 100vh;
        }
        .info-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 40px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            animation: fadeIn 0.6s ease-out;
        }
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.98);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        .info-title {
            color: #2c3e50;
            margin-bottom: 30px;
            text-align: center;
            font-size: 2em;
            font-weight: 600;
        }
        .form-group {
            margin-bottom: 25px;
        }
        .form-label {
            color: #34495e;
            font-weight: 500;
            margin-bottom: 8px;
        }
        .form-control {
            padding: 12px;
            border-radius: 10px;
            border: 2px solid #e0e0e0;
            transition: all 0.3s ease;
        }
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        .readonly-field {
            background-color: #f8f9fa;
        }
        .btn-submit {
            background: #2ecc71;
            color: white;
            padding: 12px 30px;
            border-radius: 10px;
            border: none;
            transition: all 0.3s ease;
            width: 100%;
        }
        .btn-submit:hover {
            background: #27ae60;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.3);
        }
    </style>
</head>
<body>
    <div class="info-container">
        <h2 class="info-title">Request Information Form</h2>
        <form action="review.php" method="POST">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">First Name</label>
                        <input type="text" class="form-control " value="<?php echo $user_data['firstname']; ?>" >
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Last Name</label>
                        <input type="text" class="form-control " value="<?php echo $user_data['lastname']; ?>" >
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">Contact Number</label>
                        <input type="text" class="form-control " value="<?php echo $user_data['contact']; ?>" >
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label class="form-label">LRN</label>
                        <input type="text" class="form-control " value="<?php echo $user_data['lrn']; ?>" >
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Purpose of Request</label>
                <textarea name="purpose" class="form-control" rows="4" required></textarea>
            </div>
            <div class="form-group">
                <label class="form-label">School Year</label>
                <input type="text" name="school_year" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-submit">Review Request</button>
        </form>
    </div>
</body>
</html>