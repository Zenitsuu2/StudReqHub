<?php

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Get current user ID from session
$current_user_id = $_SESSION['user_id'];

// Database connection
include '../../../../Connection/database.php';


// Query to get only the current user's requests
$query = "SELECT r.id, r.user_id, u.lrn, u.dob, u.uli,
          CONCAT(u.firstname, ' ', u.lastname) AS student_name,
          guardian_name AS parent_name,
          r.document_type, r.purpose, r.status,
          r.received_date, r.created_at, r.updated_at, r.priority,
          r.school_year, r.decline_reason
          FROM requests r
          JOIN users u ON r.user_id = u.id
          WHERE r.user_id = ? AND r.status IN ('Received', 'Completed', 'History', 'Declined')
          ORDER BY 
            CASE 
                WHEN r.updated_at IS NOT NULL AND r.updated_at != '0000-00-00 00:00:00' AND r.updated_at != '1970-01-01 00:00:00' 
                THEN r.updated_at 
                ELSE r.received_date 
            END DESC";

// Prepare and execute the statement with the user_id parameter
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $current_user_id);
$stmt->execute();
$result = $stmt->get_result();
$requests = $result->fetch_all(MYSQLI_ASSOC);

// Function to format date
function formatDate($date) {
    if (empty($date) || $date == '0000-00-00 00:00:00' || $date == '1970-01-01 00:00:00') {
        return 'N/A';
    }
    return date('M d, Y h:i A', strtotime($date));
}

// Function to get appropriate badge class based on status
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Received':
            return 'badge-primary';
        case 'Completed':
            return 'badge-success';
        case 'History':
            return 'badge-info';
        case 'Declined':
            return 'badge-danger';
        default:
            return 'badge-secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Document Request History</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #3498db;
            --secondary-color: #2c3e50;
            --success-color: #2ecc71;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --info-color: #3498db;
        }
        
        body {
            background-color: #f8f9fa;
            color: #333;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s, box-shadow 0.3s;
            margin-bottom: 1.5rem;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            font-weight: 600;
        }
        
        .badge {
            font-size: 0.8rem;
            padding: 0.5em 0.7em;
            border-radius: 30px;
        }
        
        .badge-primary {
            background-color: var(--primary-color);
            color: white;
        }
        
        .badge-success {
            background-color: var(--success-color);
            color: white;
        }
        
        .badge-danger {
            background-color: var(--danger-color);
            color: white;
        }
        
        .badge-info {
            background-color: var(--info-color);
            color: white;
        }
        
        .badge-secondary {
            background-color: var(--secondary-color);
            color: white;
        }
        
        .request-detail {
            margin-bottom: 0.5rem;
        }
        
        .request-label {
            font-weight: 600;
            color: #555;
        }
        
        .no-requests {
            text-align: center;
            padding: 3rem;
            background-color: #fff;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .back-btn {
            margin-bottom: 1.5rem;
        }
        
        /* Filter controls */
        .filter-controls {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        /* Timeline style for the cards */
        .timeline-item {
            position: relative;
            padding-left: 2rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 2px;
            background-color: #e9ecef;
        }
        
        .timeline-item::after {
            content: '';
            position: absolute;
            left: -8px;
            top: 1.5rem;
            height: 18px;
            width: 18px;
            border-radius: 50%;
            border: 2px solid var(--primary-color);
            background-color: white;
        }
        
        .timeline-date {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 0.5rem;
        }
        
        /* Status colors */
        .status-received .timeline-item::after {
            border-color: var(--primary-color);
        }
        
        .status-completed .timeline-item::after {
            border-color: var(--success-color);
        }
        
        .status-declined .timeline-item::after {
            border-color: var(--danger-color);
        }
        
        .status-history .timeline-item::after {
            border-color: var(--info-color);
        }
    </style>
</head>
<body>
    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1 class="display-5"><i class="fas fa-history me-3"></i>My Document Request History</h1>
            <p class="lead">Track the status of all your document requests</p>
        </div>
    </div>

    <div class="container mb-5">
        <!-- Back button -->
        <div class="back-btn">
            <a href="dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
        
        <!-- Filter Controls -->
        <div class="filter-controls">
            <div class="row">
                <div class="col-md-8">
                    <div class="input-group">
                        <input type="text" id="searchInput" class="form-control" placeholder="Search by document type, purpose...">
                        <button class="btn btn-outline-secondary" type="button" id="searchButton">
                            <i class="fas fa-search"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-4">
                    <select id="statusFilter" class="form-select">
                        <option value="all">All Statuses</option>
                        <option value="Received">Received</option>
                        <option value="Completed">Completed</option>
                        <option value="Declined">Declined</option>
                        <option value="History">History</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Requests List -->
        <div id="requestsContainer">
            <?php if (empty($requests)): ?>
                <div class="no-requests">
                    <i class="fas fa-folder-open fa-4x text-muted mb-3"></i>
                    <h3>No Requests Found</h3>
                    <p class="text-muted">You haven't submitted any document requests yet.</p>
                    <a href="request_document.php" class="btn btn-primary mt-3">
                        <i class="fas fa-plus me-2"></i>Request a Document
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($requests as $request): ?>
                        <div class="col-md-6 request-item" 
                             data-status="<?= htmlspecialchars($request['status']) ?>"
                             data-document="<?= htmlspecialchars($request['document_type']) ?>"
                             data-purpose="<?= htmlspecialchars($request['purpose']) ?>">
                            <div class="card status-<?= strtolower($request['status']) ?>">
                                <div class="card-body">
                                    <div class="timeline-item">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <h5 class="card-title mb-0">
                                                <?= htmlspecialchars($request['document_type']) ?>
                                            </h5>
                                            <span class="badge <?= getStatusBadgeClass($request['status']) ?>">
                                                <?= htmlspecialchars($request['status']) ?>
                                            </span>
                                        </div>
                                        
                                        <div class="timeline-date">
                                            <i class="far fa-calendar-alt me-1"></i>
                                            <?= !empty($request['updated_at']) && $request['updated_at'] != '0000-00-00 00:00:00' && $request['updated_at'] != '1970-01-01 00:00:00' 
                                                ? 'Updated: ' . formatDate($request['updated_at'])
                                                : 'Received: ' . formatDate($request['received_date']) ?>
                                        </div>
                                        
                                        <div class="request-detail">
                                            <span class="request-label">Request ID:</span>
                                            <span><?= htmlspecialchars($request['id']) ?></span>
                                        </div>
                                        
                                        <div class="request-detail">
                                            <span class="request-label">Purpose:</span>
                                            <span><?= htmlspecialchars($request['purpose']) ?></span>
                                        </div>
                                        
                                        <div class="request-detail">
                                            <span class="request-label">School Year:</span>
                                            <span><?= htmlspecialchars($request['school_year']) ?></span>
                                        </div>
                                        
                                        <div class="request-detail">
                                            <span class="request-label">Date Submitted:</span>
                                            <span><?= formatDate($request['created_at']) ?></span>
                                        </div>
                                        
                                        <?php if ($request['status'] == 'Declined' && !empty($request['decline_reason'])): ?>
                                            <div class="alert alert-danger mt-3">
                                                <strong>Reason for Decline:</strong> <?= htmlspecialchars($request['decline_reason']) ?>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="mt-3">
                                            <button class="btn btn-sm btn-outline-primary view-details" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#requestModal" 
                                                    data-request-id="<?= $request['id'] ?>">
                                                <i class="fas fa-eye me-1"></i>View Details
                                            </button>
                                            
                                            <?php if ($request['status'] == 'Completed'): ?>
                                                <a href="download_document.php?id=<?= $request['id'] ?>" 
                                                   class="btn btn-sm btn-success ms-2">
                                                    <i class="fas fa-download me-1"></i>Download
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Request Details Modal -->
    <div class="modal fade" id="requestModal" tabindex="-1" aria-labelledby="requestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="requestModalLabel">Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="requestModalBody">
                    <!-- Content will be loaded dynamically -->
                    <div class="text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Search functionality
            const searchInput = document.getElementById('searchInput');
            const searchButton = document.getElementById('searchButton');
            const statusFilter = document.getElementById('statusFilter');
            const requestItems = document.querySelectorAll('.request-item');
            
            function filterRequests() {
                const searchTerm = searchInput.value.toLowerCase();
                const statusValue = statusFilter.value;
                
                requestItems.forEach(item => {
                    const status = item.dataset.status;
                    const documentType = item.dataset.document.toLowerCase();
                    const purpose = item.dataset.purpose.toLowerCase();
                    
                    const matchesSearch = documentType.includes(searchTerm) || 
                                          purpose.includes(searchTerm);
                    const matchesStatus = statusValue === 'all' || status === statusValue;
                    
                    if (matchesSearch && matchesStatus) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            }
            
            searchButton.addEventListener('click', filterRequests);
            searchInput.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') {
                    filterRequests();
                }
            });
            statusFilter.addEventListener('change', filterRequests);
            
            // Request details modal
            const viewDetailsButtons = document.querySelectorAll('.view-details');
            const requestModalBody = document.getElementById('requestModalBody');
            
            viewDetailsButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const requestId = this.getAttribute('data-request-id');
                    
                    // In a real application, you would fetch the details via AJAX
                    // For now, we'll simulate loading the data from our PHP array
                    requestModalBody.innerHTML = `
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-3">Loading request details for ID: ${requestId}...</p>
                        </div>
                    `;
                    
                    // Simulate AJAX delay
                    setTimeout(() => {
                        // In a real app, this would come from your AJAX response
                        <?php
                        echo "const requestsData = " . json_encode($requests) . ";\n";
                        ?>
                        
                        const requestData = requestsData.find(r => r.id == requestId);
                        
                        if (requestData) {
                            let statusClass = '';
                            switch(requestData.status) {
                                case 'Received': statusClass = 'primary'; break;
                                case 'Completed': statusClass = 'success'; break;
                                case 'Declined': statusClass = 'danger'; break;
                                default: statusClass = 'secondary';
                            }
                            
                            requestModalBody.innerHTML = `
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-bold">Request Information</h6>
                                        <table class="table table-bordered">
                                            <tr>
                                                <th>Request ID</th>
                                                <td>${requestData.id}</td>
                                            </tr>
                                            <tr>
                                                <th>Document Type</th>
                                                <td>${requestData.document_type}</td>
                                            </tr>
                                            <tr>
                                                <th>Purpose</th>
                                                <td>${requestData.purpose}</td>
                                            </tr>
                                            <tr>
                                                <th>School Year</th>
                                                <td>${requestData.school_year}</td>
                                            </tr>
                                            <tr>
                                                <th>Status</th>
                                                <td><span class="badge bg-${statusClass}">${requestData.status}</span></td>
                                            </tr>
                                            ${requestData.decline_reason ? 
                                                `<tr>
                                                    <th>Decline Reason</th>
                                                    <td class="text-danger">${requestData.decline_reason}</td>
                                                </tr>` : ''}
                                            <tr>
                                                <th>Priority</th>
                                                <td>${requestData.priority || 'Normal'}</td>
                                            </tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-bold">Student Information</h6>
                                        <table class="table table-bordered">
                                            <tr>
                                                <th>Name</th>
                                                <td>${requestData.student_name}</td>
                                            </tr>
                                            <tr>
                                                <th>LRN</th>
                                                <td>${requestData.lrn || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <th>ULI</th>
                                                <td>${requestData.uli || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <th>Date of Birth</th>
                                                <td>${requestData.dob || 'N/A'}</td>
                                            </tr>
                                            <tr>
                                                <th>Parent/Guardian</th>
                                                <td>${requestData.parent_name || 'N/A'}</td>
                                            </tr>
                                        </table>
                                    </div>
                                </div>
                                
                                <h6 class="fw-bold mt-3">Timeline</h6>
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-file-alt text-primary me-2"></i>
                                            Request Created
                                        </div>
                                        <span class="badge bg-light text-dark">${formatDate(requestData.created_at)}</span>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="fas fa-check-circle text-primary me-2"></i>
                                            Request Received
                                        </div>
                                        <span class="badge bg-light text-dark">${formatDate(requestData.received_date)}</span>
                                    </li>
                                    ${requestData.status === 'Completed' || requestData.status === 'Declined' ?
                                        `<li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas fa-${requestData.status === 'Completed' ? 'check-double text-success' : 'times-circle text-danger'} me-2"></i>
                                                Request ${requestData.status}
                                            </div>
                                            <span class="badge bg-light text-dark">${formatDate(requestData.updated_at)}</span>
                                        </li>` : ''}
                                </ul>
                            `;
                        } else {
                            requestModalBody.innerHTML = `
                                <div class="alert alert-danger">
                                    Request details not found for ID: ${requestId}
                                </div>
                            `;
                        }
                    }, 500);
                });
            });
            
            // Date formatting function for JavaScript
            function formatDate(dateString) {
                if (!dateString || dateString === '0000-00-00 00:00:00' || dateString === '1970-01-01 00:00:00') {
                    return 'N/A';
                }
                
                const date = new Date(dateString);
                const options = { 
                    year: 'numeric', 
                    month: 'short', 
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                };
                return date.toLocaleDateString('en-US', options);
            }
        });
    </script>
</body>
</html>