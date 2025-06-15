<?php
session_start();
require_once '../Connection/database.php';

if (!isset($_SESSION['admin'])) {
    header('Location: login_admin.php');
    exit();
}
include 'sidebar.php';
// Set default date to today if not specified
$selected_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// Validate date format
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $selected_date)) {
    $selected_date = date('Y-m-d'); // Default to today if invalid format
}

// Get document request counts by type for the selected date
$query = "SELECT document_type, COUNT(*) as count 
          FROM requests 
          WHERE DATE(created_at) = ? 
          GROUP BY document_type";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die('Error preparing statement: ' . $conn->error);
}

if (!$stmt->bind_param('s', $selected_date)) {
    die('Error binding parameters: ' . $stmt->error);
}

if (!$stmt->execute()) {
    die('Error executing statement: ' . $stmt->error);
}

$result = $stmt->get_result();

if ($result === false) {
    die('Error getting result: ' . $stmt->error);
}

// Initialize document types and counts
$document_types = [];
$document_counts = [];
$total_requests = 0;

while ($row = $result->fetch_assoc()) {
    $document_types[] = $row['document_type'];
    $document_counts[] = $row['count'];
    $total_requests += $row['count'];
}

// Get total requests for the day
$today = date('Y-m-d');
$today_query = "SELECT COUNT(*) as count FROM requests WHERE DATE(created_at) = ?";
$today_stmt = $conn->prepare($today_query);
$today_stmt->bind_param('s', $today);
$today_stmt->execute();
$today_result = $today_stmt->get_result();
$today_count = $today_result->fetch_assoc()['count'];

// Get all request data for detailed view
$all_requests_query = "SELECT r.id, r.document_type, r.status, r.created_at, 
                      CONCAT(COALESCE(s.firstname, ''), ' ', COALESCE(s.lastname, '')) as full_name 
                      FROM requests r
                      LEFT JOIN users s ON r.user_id = s.id
                      WHERE DATE(r.created_at) = ? 
                      ORDER BY r.created_at DESC";
$all_requests_stmt = $conn->prepare($all_requests_query);
$all_requests_stmt->bind_param('s', $selected_date);
$all_requests_stmt->execute();
$all_requests_result = $all_requests_stmt->get_result();
$all_requests = [];
while ($row = $all_requests_result->fetch_assoc()) {
    $all_requests[] = $row;
}

// Get requests by status for the selected date
$status_query = "SELECT status, COUNT(*) as count 
                FROM requests 
                WHERE DATE(created_at) = ? 
                GROUP BY status";
$status_stmt = $conn->prepare($status_query);
$status_stmt->bind_param('s', $selected_date);
$status_stmt->execute();
$status_result = $status_stmt->get_result();
$status_labels = [];
$status_counts = [];
while ($row = $status_result->fetch_assoc()) {
    $status_labels[] = $row['status'];
    $status_counts[] = $row['count'];
}

// Get requests by document type for the past 7 days for trend analysis
$trend_query = "SELECT DATE(created_at) as request_date, document_type, COUNT(*) as count 
                FROM requests 
                WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
                GROUP BY DATE(created_at), document_type
                ORDER BY DATE(created_at)";
                
$trend_result = $conn->query($trend_query);
$trend_data = [];

while ($row = $trend_result->fetch_assoc()) {
    $date = $row['request_date'];
    $type = $row['document_type'];
    $count = $row['count'];
    
    if (!isset($trend_data[$date])) {
        $trend_data[$date] = [];
    }
    
    $trend_data[$date][$type] = $count;
}

// Get monthly trends (added)
$monthly_query = "SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as request_count
                  FROM requests
                  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                  GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                  ORDER BY month";
                  
$monthly_result = $conn->query($monthly_query);
$monthly_labels = [];
$monthly_counts = [];

while ($row = $monthly_result->fetch_assoc()) {
    // Format the month for display (e.g., "2023-04" to "Apr 2023")
    $month_timestamp = strtotime($row['month'] . '-01');
    $formatted_month = date('M Y', $month_timestamp);
    
    $monthly_labels[] = $formatted_month;
    $monthly_counts[] = $row['request_count'];
}

// Get hourly distribution (added)
$hourly_query = "SELECT 
                   HOUR(created_at) as hour_of_day,
                   COUNT(*) as request_count
                 FROM requests
                 WHERE DATE(created_at) = ?
                 GROUP BY HOUR(created_at)
                 ORDER BY hour_of_day";
                 
$hourly_stmt = $conn->prepare($hourly_query);
$hourly_stmt->bind_param('s', $selected_date);
$hourly_stmt->execute();
$hourly_result = $hourly_stmt->get_result();

$hours = [];
$hourly_counts = [];

// Initialize all hours with zero counts
for ($i = 0; $i < 24; $i++) {
    $hours[] = sprintf("%02d:00", $i);
    $hourly_counts[$i] = 0;
}

while ($row = $hourly_result->fetch_assoc()) {
    $hourly_counts[$row['hour_of_day']] = $row['request_count'];
}

// Function to get request details for modal view
function getRequestDetails($conn, $id) {
    $query = "SELECT r.*, u.full_name, u.email,u
              FROM requests r
              LEFT JOIN users u ON r.user_id = u.id
              WHERE r.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// Convert to JSON for charts
$chart_data = json_encode($document_types);
$chart_counts = json_encode($document_counts);
$trend_chart_data = json_encode($trend_data);
$status_chart_labels = json_encode($status_labels);
$status_chart_counts = json_encode($status_counts);
$monthly_chart_labels = json_encode($monthly_labels);
$monthly_chart_counts = json_encode($monthly_counts);
$hourly_chart_labels = json_encode($hours);
$hourly_chart_counts = json_encode(array_values($hourly_counts));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Request Analytics</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        /* Add these styles at the top of your existing styles */
        body {
            padding: 0;
            margin: 0;
            overflow-x: hidden;
        }

        .main-content {
            margin-left: 250px; /* Width of the sidebar */
            padding: 20px;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .sidebar.active + .main-content {
                margin-left: 250px;
            }
        }

        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
            transition: transform 0.3s ease;
        }
        .card:hover {
            transform: translateY(-5px);
        }
        .card-body {
            padding: 1rem; /* Reduce padding inside cards */
        }
        .stats-card {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            transition: all 0.3s ease;
        }
        .stats-card:hover {
            background-color: #e9ecef;
        }
        .stats-card h3 {
            margin-bottom: 0;
            font-weight: bold;
            color: #343a40;
        }
        .stats-card p {
            color: #6c757d;
            margin-bottom: 0;
        }
        .date-form {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        .dashboard-title {
            color: #343a40;
            border-bottom: 2px solid #6c757d;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .table-container {
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .nav-tabs {
            border-bottom: 2px solid #dee2e6;
            margin-bottom: 20px;
        }
        .nav-tabs .nav-link {
            margin-bottom: -2px;
            border: none;
            color: #6c757d;
        }
        .nav-tabs .nav-link.active {
            color: #343a40;
            border-bottom: 2px solid #007bff;
        }
        .badge-pending {
            background-color: #ffc107;
            color: #212529;
        }
        .badge-completed {
            background-color: #28a745;
            color: #fff;
        }
        .badge-rejected {
            background-color: #dc3545;
            color: #fff;
        }
        
        /* Modal styles */
        .request-detail-modal .modal-header {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
        }
        .request-detail-modal .modal-footer {
            background-color: #f8f9fa;
            border-top: 2px solid #dee2e6;
        }
        .request-detail-row {
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        .request-detail-row:last-child {
            border-bottom: none;
        }
        .request-detail-label {
            font-weight: bold;
            color: #495057;
        }
        .request-status {
            padding: 5px 10px;
            border-radius: 5px;
            display: inline-block;
            font-weight: bold;
        }
        
        /* Add these new rules for chart sizing */
        canvas {
            max-height: 250px; /* Control maximum height of all charts */
        }
        #documentPieChart, #statusPieChart {
            max-height: 200px; /* Make pie charts specifically smaller */
        }
        .chart-container {
            position: relative;
            height: 200px; /* Fixed height for chart containers */
        }
    </style>
</head>
<body>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<div class="main-content"> <!-- Changed from container-fluid to this new wrapper -->
    <div class="row">
        <div class="col-md-12"> <!-- Changed from col-md-11 mx-auto to col-md-12 -->
            <h1 class="dashboard-title mb-4">
                <i class="fas fa-chart-line me-2"></i>Document Request Analytics Dashboard
            </h1>
            
            <!-- Date Selection Form -->
            <div class="date-form">
                <form method="GET" action="" class="d-flex align-items-end">
                    <div class="me-3">
                        <label for="date" class="form-label"><i class="far fa-calendar-alt me-1"></i>Select Date:</label>
                        <input type="date" id="date" name="date" class="form-control" value="<?php echo $selected_date; ?>">
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-1"></i>Apply Filter
                        </button>
                    </div>
                   
                </form>
            </div>
            
            <!-- Stats Summary Row -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <i class="fas fa-file-alt fa-2x mb-2 text-primary"></i>
                        <h3><?php echo $total_requests; ?></h3>
                        <p>Requests on <?php echo date('F j, Y', strtotime($selected_date)); ?></p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <i class="fas fa-calendar-day fa-2x mb-2 text-success"></i>
                        <h3><?php echo $today_count; ?></h3>
                        <p>Requests Today <?php echo date('F j, Y'); ?></p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <i class="fas fa-check-circle fa-2x mb-2 text-info"></i>
                        <h3><?php 
                            $completed_count = 0;
                            foreach ($status_labels as $index => $status) {
                                if ($status == 'Completed') {
                                    $completed_count = $status_counts[$index];
                                    break;
                                }
                            }
                            echo $completed_count;
                        ?></h3>
                        <p>Completed Requests</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <i class="fas fa-hourglass-half fa-2x mb-2 text-warning"></i>
                        <h3><?php 
                            $pending_count = 0;
                            foreach ($status_labels as $index => $status) {
                                if ($status == 'Pending') {
                                    $pending_count = $status_counts[$index];
                                    break;
                                }
                            }
                            echo $pending_count;
                        ?></h3>
                        <p>Pending Requests</p>
                    </div>
                </div>
            </div>
            
            <!-- Chart Navigation Tabs -->
            <ul class="nav nav-tabs" id="chartTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="daily-tab" data-bs-toggle="tab" data-bs-target="#daily" type="button" role="tab" aria-controls="daily" aria-selected="true">
                        <i class="fas fa-chart-pie me-1"></i>Daily Analysis
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="trends-tab" data-bs-toggle="tab" data-bs-target="#trends" type="button" role="tab" aria-controls="trends" aria-selected="false">
                        <i class="fas fa-chart-line me-1"></i>Trends
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="requests-tab" data-bs-toggle="tab" data-bs-target="#requests" type="button" role="tab" aria-controls="requests" aria-selected="false">
                        <i class="fas fa-list me-1"></i>All Requests
                    </button>
                </li>
            </ul>
            
            <!-- Tab Content -->
            <div class="tab-content" id="chartTabsContent">
                <!-- Daily Analysis Tab -->
                <div class="tab-pane fade show active" id="daily" role="tabpanel" aria-labelledby="daily-tab">
                    <div class="row">
                        <!-- Pie Chart -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-chart-pie me-1"></i>Document Requests by Type
                                    </h5>
                                    <h6 class="card-subtitle mb-0 text-muted">
                                        <?php echo date('F j, Y', strtotime($selected_date)); ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="documentPieChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Status Chart -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-tasks me-1"></i>Requests by Status
                                    </h5>
                                    <h6 class="card-subtitle mb-0 text-muted">
                                        <?php echo date('F j, Y', strtotime($selected_date)); ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="statusPieChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <!-- Bar Chart -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-chart-bar me-1"></i>Document Requests Count
                                    </h5>
                                    <h6 class="card-subtitle mb-0 text-muted">
                                        <?php echo date('F j, Y', strtotime($selected_date)); ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="documentBarChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Hourly Distribution -->
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="card-title mb-0">
                                        <i class="far fa-clock me-1"></i>Hourly Request Distribution
                                    </h5>
                                    <h6 class="card-subtitle mb-0 text-muted">
                                        <?php echo date('F j, Y', strtotime($selected_date)); ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container">
                                        <canvas id="hourlyChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Trends Tab -->
                <div class="tab-pane fade" id="trends" role="tabpanel" aria-labelledby="trends-tab">
                    <div class="row">
                        <!-- Weekly Trend Chart -->
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-chart-line me-1"></i>Weekly Document Request Trends
                                    </h5>
                                    <h6 class="card-subtitle mb-0 text-muted">Last 7 days</h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container" style="height: 250px;">
                                        <canvas id="trendLineChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <!-- Monthly Trend Chart -->
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header bg-light">
                                    <h5 class="card-title mb-0">
                                        <i class="fas fa-calendar-alt me-1"></i>Monthly Request Trends
                                    </h5>
                                    <h6 class="card-subtitle mb-0 text-muted">Last 6 months</h6>
                                </div>
                                <div class="card-body">
                                    <div class="chart-container" style="height: 250px;">
                                        <canvas id="monthlyTrendChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- All Requests Tab -->
                <div class="tab-pane fade" id="requests" role="tabpanel" aria-labelledby="requests-tab">
                    <div class="table-container">
                        <h5 class="mb-3"><i class="fas fa-list me-2"></i>All Document Requests for <?php echo date('F j, Y', strtotime($selected_date)); ?></h5>
                        <table id="requestsTable" class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Requester</th>
                                    <th>Document Type</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($all_requests as $request): ?>
                                <tr>
                                    <td><?php echo $request['id']; ?></td>
                                    <td><?php echo $request['full_name'] ?? 'N/A'; ?></td>
                                    <td><?php echo $request['document_type']; ?></td>
                                    <td>
                                        <?php 
                                        $status_class = '';
                                        switch ($request['status']) {
                                            case 'Pending':
                                                $status_class = 'badge-pending';
                                                break;
                                            case 'Completed':
                                                $status_class = 'badge-completed';
                                                break;
                                            case 'Rejected':
                                                $status_class = 'badge-rejected';
                                                break;
                                            default:
                                                $status_class = 'bg-secondary';
                                        }
                                        ?>
                                        <span class="badge <?php echo $status_class; ?>"><?php echo $request['status']; ?></span>
                                    </td>
                                    <td><?php echo date('h:i A', strtotime($request['created_at'])); ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary view-request" data-id="<?php echo $request['id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Request Details Modal -->
<div class="modal fade request-detail-modal" id="requestDetailModal" tabindex="-1" aria-labelledby="requestDetailModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="requestDetailModalLabel">
                    <i class="fas fa-file-alt me-2"></i>Request Details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="requestDetailContent">
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading request details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JavaScript -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

<!-- Chart Initialization -->
<script>
    // Wait for the DOM to be fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize DataTable
        $(document).ready(function() {
            $('#requestsTable').DataTable({
                responsive: true,
                order: [[4, 'desc']], // Sort by time descending by default
                language: {
                    search: "<i class='fas fa-search'></i> Search:",
                    lengthMenu: "Show _MENU_ entries",
                    info: "Showing _START_ to _END_ of _TOTAL_ entries"
                }
            });
            
            // View request button handler
            $('.view-request').on('click', function() {
                const requestId = $(this).data('id');
                
                // Show the modal
                const modal = new bootstrap.Modal(document.getElementById('requestDetailModal'));
                modal.show();
                
                // Set loading state
                $('#requestDetailContent').html(`
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2">Loading request details...</p>
                    </div>
                `);
                
                // Fetch request details
                $.ajax({
                    url: 'get_request_details.php',
                    type: 'GET',
                    data: { id: requestId },
                    dataType: 'json',
                    success: function(data) {
                        if (data.error) {
                            $('#requestDetailContent').html(`
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    ${data.error}
                                </div>
                            `);
                            return;
                        }

                        // Format created_at date
                        const createdDate = new Date(data.created_at);
                        const formattedDate = createdDate.toLocaleString('en-US', {
                            year: 'numeric', 
                            month: 'long', 
                            day: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit'
                        });
                        
                        // Determine status class
                        let statusClass = 'bg-secondary';
                        switch (data.status) {
                            case 'Pending':
                                statusClass = 'badge-pending';
                                break;
                            case 'Completed':
                                statusClass = 'badge-completed';
                                break;
                            case 'Rejected':
                                statusClass = 'badge-rejected';
                                break;
                        }
                        
                        // Build the content
                        const content = `
                            <div class="card mb-3">
                                <div class="card-body">
                                    <div class="row request-detail-row">
                                        <div class="col-md-4 request-detail-label">Request ID:</div>
                                        <div class="col-md-8">#${data.id}</div>
                                    </div>
                                    <div class="row request-detail-row">
                                     <div class="col-md-4 request-detail-label">Student Name:</div>
                                                                            <div class="col-md-8">${data.full_name || 'N/A'}</div>
                                </div>
                                <div class="row request-detail-row">
                                    <div class="col-md-4 request-detail-label">Lrn:</div>
                                    <div class="col-md-8">${data.lrn || 'N/A'}</div>
                                </div>
                                 <div class="row request-detail-row">
                                    <div class="col-md-4 request-detail-label">ULI:</div>
                                    <div class="col-md-8">${data.uli || 'N/A'}</div>
                                </div>
                                 <div class="row request-detail-row">
                                    <div class="col-md-4 request-detail-label">Birthday:</div>
                                    <div class="col-md-8">${data.dob || 'N/A'}</div>
                                </div>
                                 <div class="row request-detail-row">
                                    <div class="col-md-4 request-detail-label">Email:</div>
                                    <div class="col-md-8">${data.email || 'N/A'}</div>
                                </div>
                                <div class="row request-detail-row">
                                    <div class="col-md-4 request-detail-label">Document Type:</div>
                                    <div class="col-md-8">${data.document_type}</div>
                                </div>
                                <div class="row request-detail-row">
                                    <div class="col-md-4 request-detail-label">Status:</div>
                                    <div class="col-md-8">
                                        <span class="badge ${statusClass}">${data.status}</span>
                                    </div>
                                </div>
                                <div class="row request-detail-row">
                                    <div class="col-md-4 request-detail-label">Request Date:</div>
                                    <div class="col-md-8">${formattedDate}</div>
                                </div>
                                <div class="row request-detail-row">
                                    <div class="col-md-4 request-detail-label">Purpose:</div>
                                    <div class="col-md-8">${data.purpose || 'Not specified'}</div>
                                </div>
                                <div class="row request-detail-row">
                                    <div class="col-md-4 request-detail-label">Additional Notes:</div>
                                    <div class="col-md-8">${data.additional_notes || 'None'}</div>
                                </div>
                            </div>
                        </div>
                    `;
                    
                    $('#requestDetailContent').html(content);
                },
                error: function() {
                    $('#requestDetailContent').html(`
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            Error loading request details. Please try again.
                        </div>
                    `);
                }
            });
        });
    });

    // Document Type Pie Chart
    const documentPieCtx = document.getElementById('documentPieChart').getContext('2d');
    const documentPieChart = new Chart(documentPieCtx, {
        type: 'pie',
        data: {
            labels: <?php echo $chart_data; ?>,
            datasets: [{
                data: <?php echo $chart_counts; ?>,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.7)',
                    'rgba(54, 162, 235, 0.7)',
                    'rgba(255, 206, 86, 0.7)',
                    'rgba(75, 192, 192, 0.7)',
                    'rgba(153, 102, 255, 0.7)',
                    'rgba(255, 159, 64, 0.7)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)',
                    'rgba(255, 159, 64, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // Status Pie Chart
    const statusPieCtx = document.getElementById('statusPieChart').getContext('2d');
    const statusPieChart = new Chart(statusPieCtx, {
        type: 'pie',
        data: {
            labels: <?php echo $status_chart_labels; ?>,
            datasets: [{
                data: <?php echo $status_chart_counts; ?>,
                backgroundColor: [
                    'rgba(255, 206, 86, 0.7)',  // Pending - yellow
                    'rgba(40, 167, 69, 0.7)',   // Completed - green
                    'rgba(220, 53, 69, 0.7)'    // Rejected - red
                ],
                borderColor: [
                    'rgba(255, 206, 86, 1)',
                    'rgba(40, 167, 69, 1)',
                    'rgba(220, 53, 69, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'right',
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const label = context.label || '';
                            const value = context.raw || 0;
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = Math.round((value / total) * 100);
                            return `${label}: ${value} (${percentage}%)`;
                        }
                    }
                }
            }
        }
    });

    // Document Type Bar Chart
    const documentBarCtx = document.getElementById('documentBarChart').getContext('2d');
    const documentBarChart = new Chart(documentBarCtx, {
        type: 'bar',
        data: {
            labels: <?php echo $chart_data; ?>,
            datasets: [{
                label: 'Number of Requests',
                data: <?php echo $chart_counts; ?>,
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: ${context.raw}`;
                        }
                    }
                }
            }
        }
    });

    // Hourly Distribution Chart
    const hourlyCtx = document.getElementById('hourlyChart').getContext('2d');
    const hourlyChart = new Chart(hourlyCtx, {
        type: 'line',
        data: {
            labels: <?php echo $hourly_chart_labels; ?>,
            datasets: [{
                label: 'Requests per Hour',
                data: <?php echo $hourly_chart_counts; ?>,
                backgroundColor: 'rgba(153, 102, 255, 0.2)',
                borderColor: 'rgba(153, 102, 255, 1)',
                borderWidth: 2,
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.raw} request${context.raw !== 1 ? 's' : ''} at ${context.label}`;
                        }
                    }
                }
            }
        }
    });

    // Weekly Trend Chart
    const trendLineCtx = document.getElementById('trendLineChart').getContext('2d');
    
    // Prepare trend data for Chart.js
    const trendData = <?php echo $trend_chart_data; ?>;
    const dates = Object.keys(trendData).sort();
    const documentTypes = [...new Set(
        dates.flatMap(date => Object.keys(trendData[date]))
    )];
    
    // Generate colors for each document type
    const backgroundColors = [
        'rgba(255, 99, 132, 0.2)',
        'rgba(54, 162, 235, 0.2)',
        'rgba(255, 206, 86, 0.2)',
        'rgba(75, 192, 192, 0.2)',
        'rgba(153, 102, 255, 0.2)',
        'rgba(255, 159, 64, 0.2)'
    ];
    
    const borderColors = [
        'rgba(255, 99, 132, 1)',
        'rgba(54, 162, 235, 1)',
        'rgba(255, 206, 86, 1)',
        'rgba(75, 192, 192, 1)',
        'rgba(153, 102, 255, 1)',
        'rgba(255, 159, 64, 1)'
    ];
    
    const datasets = documentTypes.map((type, index) => {
        return {
            label: type,
            data: dates.map(date => trendData[date][type] || 0),
            backgroundColor: backgroundColors[index % backgroundColors.length],
            borderColor: borderColors[index % borderColors.length],
            borderWidth: 2,
            tension: 0.4,
            fill: true
        };
    });
    
    const trendLineChart = new Chart(trendLineCtx, {
        type: 'line',
        data: {
            labels: dates.map(date => new Date(date).toLocaleDateString('en-US', { 
                weekday: 'short', 
                month: 'short', 
                day: 'numeric' 
            })),
            datasets: datasets
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: ${context.raw} request${context.raw !== 1 ? 's' : ''}`;
                        }
                    }
                }
            }
        }
    });

    // Monthly Trend Chart
    const monthlyTrendCtx = document.getElementById('monthlyTrendChart').getContext('2d');
    const monthlyTrendChart = new Chart(monthlyTrendCtx, {
        type: 'bar',
        data: {
            labels: <?php echo $monthly_chart_labels; ?>,
            datasets: [{
                label: 'Total Requests',
                data: <?php echo $monthly_chart_counts; ?>,
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            plugins: {
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            return `${context.raw} request${context.raw !== 1 ? 's' : ''} in ${context.label}`;
                        }
                    }
                }
            }
        }
    });

    // Auto-refresh the page every 5 minutes to keep data fresh
    setTimeout(function() {
        window.location.reload();
    }, 300000); // 300000 ms = 5 minutes
});
</script>

</body>
</html>