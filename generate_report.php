<?php
require '../vendor/autoload.php';
use Dompdf\Dompdf;

session_start();
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit();
}

include '../Connection/database.php';

$specific_date = $_GET['specific_date'] ?? '';

$query = "SELECT r.*, u.firstname, u.lastname, u.contact FROM requests r JOIN users u ON r.user_id = u.id WHERE r.status = 'Received'";

if ($specific_date) {
    $query .= " AND DATE(r.received_date) = '$specific_date'";
}

$result = $conn->query($query);

if ($result === false) {
    die('Error executing query: ' . $conn->error);
}

$requests = $result->fetch_all(MYSQLI_ASSOC);

$html = '<h1>Request Report</h1>';
$html .= '<table border="1" cellpadding="10">';
$html .= '<thead><tr><th>Request ID</th><th>Student Name</th><th>Contact</th><th>Document</th><th>Status</th><th>Received Date</th></tr></thead>';
$html .= '<tbody>';
foreach ($requests as $request) {
    $html .= '<tr>';
    $html .= '<td>' . $request['id'] . '</td>';
    $html .= '<td>' . $request['firstname'] . ' ' . $request['lastname'] . '</td>';
    $html .= '<td>' . $request['contact'] . '</td>';
    $html .= '<td>' . $request['document_type'] . '</td>';
    $html .= '<td>' . $request['status'] . '</td>';
    $html .= '<td>' . $request['received_date'] . '</td>';
    $html .= '</tr>';
}
$html .= '</tbody></table>';

$dompdf = new Dompdf();
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("request_report.pdf", ["Attachment" => 0]);
?>
<!DOCTYPE html>
<html>
<head>
    <title>Request History</title>
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Your Custom Styles -->
    <link rel="stylesheet" href="css/styles.css">

    <style>
        /* Example custom table header styling to match your screenshot */
        thead.table-header {
            background-color: #0d47a1; /* Navy-ish background */
            color: #fff;              /* White text */
        }
        /* Badge color overrides (optional) */
        .badge {
            font-size: 0.9rem;
        }
        .badge.bg-primary {
            background-color: #007bff !important;
        }
        .badge.bg-warning {
            background-color: #ffc107 !important;
            color: #212529 !important; /* Dark text for contrast */
        }
        .badge.bg-success {
            background-color: #28a745 !important;
        }
        .badge.bg-secondary {
            background-color: #6c757d !important;
        }
        body {
            background: #f8f9fa;
            min-height: 100vh;
        }
        .dashboard-container {
            padding: 30px;
            margin-left: 250px;
            transition: all 0.3s ease;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .requests-table {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .table th {
            background: #1a2a6c;
            color: white;
            padding: 15px;
        }
        .status-badge {
            padding: 8px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .status-pending {
            background: #ffeeba;
            color: #856404;
        }
        .status-processing {
            background: #b8daff;
            color: #004085;
        }
        .status-ready {
            background: #c3e6cb;
            color: #155724;
        }
        .navbar {
            background: #1a2a6c;
            padding: 15px 30px;
        }
        .navbar-brand {
            color: white;
            font-weight: 600;
        }
        .btn-action {
            padding: 8px 15px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        .btn-action:hover {
            transform: translateY(-2px);
        }
        .request-row {
            transition: all 0.3s ease;
        }

        .request-row.new {
            animation: highlightNew 2s ease-out;
        }

        @keyframes highlightNew {
            0% { background-color: #ffeeba; }
            100% { background-color: transparent; }
        }
    </style>
</head>
<body>
<div class="d-flex">
    <?php include 'sidebar.php'; ?>
    <div class="dashboard-container flex-grow-1 p-4">
        <nav class="navbar">
            <div class="container-fluid">
                <span class="navbar-brand">Request History</span>
                <a href="login_admin.php" class="btn btn-outline-light">Logout</a>
            </div>
        </nav>

        <div class="requests-table">
            <h4 class="mb-4">Request History</h4>

            <!-- Search box -->
            <input type="text" id="searchInputHistory" 
                   class="form-control mb-3" 
                   placeholder="Search by LRN or Name" 
                   onkeyup="searchHistory()">

            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="specificDate" class="form-label">Specific Date</label>
                    <input type="date" id="specificDate" class="form-control" onchange="filterByDate()">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-primary" onclick="downloadReport()">Download Report</button>
                </div>
            </div>

            <!-- Table with a custom header style -->
            <table class="table table-bordered table-striped align-middle">
                <thead class="table-header">
                    <tr>
                        <th>Request No.</th>
                        <th>LRN</th>
                        <th>Student Name</th>
                        <th>Contact</th>
                        <th>Document</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Receive Date</th>
                    </tr>
                </thead>
                <tbody id="historyTableBody">
                    <?php foreach ($request_history as $history): ?>
                        <tr class="history-row">
                            <!-- Request ID -->
                            <td>#<?php echo $history['id']; ?></td>

                            <!-- LRN -->
                            <td><?php echo $history['lrn']; ?></td>

                            <!-- Student Name -->
                            <td><?php echo $history['firstname'] . ' ' . $history['lastname']; ?></td>

                            <!-- Contact -->
                            <td><?php echo $history['contact']; ?></td>

                            <!-- Document -->
                            <td><?php echo $history['document_type']; ?></td>

                            <!-- Priority (placeholder if not in DB) -->
                            <td>
                                <?php 
                                  // If your table doesn't have a priority column, you can hardcode 'Normal' or any logic you prefer:
                                  echo !empty($history['priority']) ? $history['priority'] : 'Normal'; 
                                ?>
                            </td>

                            <!-- Status with colored badges -->
                            <td>
                                <?php
                                    $status = $history['status'];
                                    if ($status == 'Ready for Pickup') {
                                        echo '<span class="badge bg-primary">Ready for Pickup</span>';
                                    } elseif ($status == 'Processing') {
                                        echo '<span class="badge bg-warning text-dark">Processing</span>';
                                    } elseif ($status == 'Received') {
                                        echo '<span class="badge bg-success">Received</span>';
                                    } else {
                                        // Fallback for any other status
                                        echo '<span class="badge bg-secondary">' . $status . '</span>';
                                    }
                                ?>
                            </td>

                            <!-- Receive Date -->
                            <td>
                                <?php
                                    echo !empty($history['received_date']) ? date('M d, Y', strtotime($history['received_date'])) : 'N/A';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<!-- Custom Scripts -->
<script src="js/scripts.js"></script>

<script>
    function searchHistory() {
        const input = document.getElementById('searchInputHistory');
        const filter = input.value.toLowerCase();
        const rows = document.querySelectorAll('.history-row');
        rows.forEach(row => {
            const studentName = row.cells[2].textContent.toLowerCase(); // Student Name cell
            const contact     = row.cells[3].textContent.toLowerCase(); // Contact cell (LRN or phone)
            
            if (studentName.includes(filter) || contact.includes(filter)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function filterByDate() {
        const specificDate = document.getElementById('specificDate').value;
        const rows = document.querySelectorAll('.history-row');
        rows.forEach(row => {
            const receiveDate = row.cells[7].textContent; // Receive Date cell
            if (receiveDate.includes(specificDate)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function downloadReport() {
        const specificDate = document.getElementById('specificDate').value;
        window.location.href = `generate_report.php?specific_date=${specificDate}`;
    }
</script>
</body>
</html>