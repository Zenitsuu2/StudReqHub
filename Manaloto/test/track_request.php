<?php
// Start the session to maintain user login status
session_start();

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
include '../../Connection/database.php'; // Adjust the path to your database connection file

// Get the logged-in user's ID
$user_id = $_SESSION['user_id'];

// Initialize search variables
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$search_term = isset($_GET['search']) ? $_GET['search'] : '';

// Prepare the base SQL query with a JOIN to fetch grade_level from the users table
$sql = "
    SELECT 
        requests.*, 
        users.grade_level 
    FROM 
        requests 
    JOIN 
        users 
    ON 
        requests.user_id = users.id 
    WHERE 
        requests.user_id = ?";

// Add filters if they exist
$params = [$user_id];
$types = "i"; // i for integer (user_id)

if (!empty($status_filter)) {
    $sql .= " AND status = ?";
    $params[] = $status_filter;
    $types .= "s"; // s for string (status)
}

if (!empty($date_filter)) {
    $sql .= " AND DATE(created_at) = ?";
    $params[] = $date_filter;
    $types .= "s"; // s for string (date)
}

if (!empty($search_term)) {
    $sql .= " AND (document_type LIKE ? OR purpose LIKE ? OR uli LIKE ?)";
    $search_param = "%$search_term%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss"; // s for string (three search parameters)
}

// Order by most recent first
$sql .= " ORDER BY created_at DESC";

// Prepare and execute the statement
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    $requests = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    echo "Error preparing statement: " . $conn->error;
    exit();
}

// Get distinct status values for filter dropdown
$status_sql = "SELECT DISTINCT status FROM requests WHERE user_id = ?";
$status_stmt = $conn->prepare($status_sql);
$status_stmt->bind_param("i", $user_id);
$status_stmt->execute();
$status_result = $status_stmt->get_result();
$statuses = [];
while ($row = $status_result->fetch_assoc()) {
    $statuses[] = $row['status'];
}
$status_stmt->close();

// Get distinct dates for filter dropdown
$date_sql = "SELECT DISTINCT DATE(created_at) as request_date FROM requests WHERE user_id = ? ORDER BY created_at DESC";
$date_stmt = $conn->prepare($date_sql);
$date_stmt->bind_param("i", $user_id);
$date_stmt->execute();
$date_result = $date_stmt->get_result();
$dates = [];
while ($row = $date_result->fetch_assoc()) {
    $dates[] = $row['request_date'];
}
$date_stmt->close();

// Close the database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Document Requests | Tracking System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.min.css">
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #4a6fdc;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e9f2 100%);
            min-height: 100vh;
            padding-top: 2rem;
            padding-bottom: 2rem;
        }

        .container {
            max-width: 1100px;
        }

        .page-header {
            margin-bottom: 2rem;
            color: #333;
        }

        .tracking-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
            padding: 1.5rem;
            margin-bottom: 2rem;
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .tracking-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.15);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eaeaea;
            padding-bottom: 1rem;
            margin-bottom: 1rem;
        }

        .request-id {
            font-size: 1rem;
            color: #666;
        }

        .document-title {
            font-size: 1.4rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
        }

        .request-meta {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .meta-item {
            font-size: 0.9rem;
        }

        .meta-label {
            display: block;
            color: #666;
            margin-bottom: 0.2rem;
            font-weight: 500;
        }

        .meta-value {
            font-weight: 600;
            color: #333;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .status-Pending {
            background-color: #fff4e5;
            color: #ff9800;
        }

        .status-Processing {
            background-color: #e8f4fd;
            color: #2196f3;
        }

        .status-Ready {
            background-color: #e6f9f1;
            color: #4caf50;
        }

        .status-Completed {
            background-color: #e9ecef;
            color: #495057;
        }

        .status-Declined {
            background-color: #feebee;
            color: #f44336;
        }

        .progress-tracker {
            margin: 2rem 0 1rem;
            position: relative;
        }

        .progress-line {
            height: 4px;
            background-color: #e9ecef;
            border-radius: 2px;
            position: relative;
            z-index: 1;
            margin: 0 auto;
        }

        .progress-line-fill {
            height: 100%;
            background: linear-gradient(to right, var(--primary-color), var(--info-color));
            border-radius: 2px;
            width: 0;
            transition: width 1.5s ease;
        }

        .steps {
            display: flex;
            justify-content: space-between;
            margin-top: -12px;
            position: relative;
            z-index: 2;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 3;
        }

        .step-dot {
            width: 24px;
            height: 24px;
            background-color: #e9ecef;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 3px solid white;
            transition: all 0.5s ease;
        }

        .step-dot.active {
            background-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 111, 220, 0.2);
        }

        .step-dot.completed {
            background-color: var(--success-color);
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.2);
        }

        .step-label {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            font-weight: 600;
            color: #666;
            transition: color 0.5s ease;
        }

        .step-dot.active + .step-label,
        .step-dot.completed + .step-label {
            color: #333;
        }

        .step-icon {
            color: white;
            font-size: 0.7rem;
        }

        .timeline {
            margin-top: 2rem;
            border-left: 2px solid #e9ecef;
            padding-left: 1.5rem;
            margin-left: 1rem;
        }

        .timeline-item {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .timeline-item:before {
            content: '';
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: var(--primary-color);
            position: absolute;
            left: -1.65rem;
            top: 6px;
        }

        .timeline-date {
            font-size: 0.8rem;
            color: #666;
            margin-bottom: 0.2rem;
        }

        .timeline-event {
            font-weight: 500;
            color: #333;
        }

        .admin-note {
            background-color: #f8f9fa;
            border-left: 3px solid var(--info-color);
            padding: 1rem;
            margin-top: 1rem;
            border-radius: 4px;
        }

        .filters {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
            margin-bottom: 2rem;
        }

        .no-results {
            text-align: center;
            padding: 3rem 0;
            color: #6c757d;
        }

        .priority-high {
            background-color: #feebee;
            color: #f44336;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .priority-medium {
            background-color: #fff4e5;
            color: #ff9800;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .priority-low {
            background-color: #e9ecef;
            color: #495057;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .search-form {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .search-form .form-group,
        .search-form .btn {
            margin-bottom: 0;
        }

        @media (max-width: 768px) {
            .request-meta {
                grid-template-columns: 1fr;
            }
            
            .card-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .card-header .status-badge {
                margin-top: 1rem;
            }
            
            .search-form {
                flex-direction: column;
            }
            
            .search-form .form-group {
                width: 100%;
                margin-bottom: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Back to Dashboard button -->
        <a href="home.php" class="btn btn-secondary mb-3">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
        
        <!-- Existing page header and content -->
        <div class="page-header">
            <h1>My Document Requests</h1>
            <p class="text-muted">Track the status and progress of your document requests</p>
        </div>

        <!-- Search and Filter Section -->
       
        <?php if (empty($requests)): ?>
            <div class="no-results">
                <i class="fas fa-search fa-3x mb-3 text-muted"></i>
                <h3>No document requests found</h3>
                <p>You haven't made any document requests yet or none match your current filters.</p>
                <a href="new_request.php" class="btn btn-primary mt-3">Create New Request</a>
            </div>
        <?php else: ?>
            <?php foreach ($requests as $request): ?>
                <?php
                    // Determine progress percentage based on status
                    $progressPercentage = 0;
                    switch ($request['status']) {
                        case 'Pending':
                            $progressPercentage = 25;
                            break;
                        case 'Processing':
                            $progressPercentage = 50;
                            break;
                        case 'Ready to Pickup':
                            $progressPercentage = 75;
                            break;
                        case 'Completed':
                            $progressPercentage = 100;
                            break;
                        case 'Declined':
                            $progressPercentage = 100; // Full progress bar but with error styling
                            break;
                    }
                    
                    // Format dates 
                    $created_date = !empty($request['created_at']) ? date('M d, Y h:i A', strtotime($request['created_at'])) : 'N/A';
                    $processed_date = !empty($request['processed_date']) ? date('M d, Y h:i A', strtotime($request['processed_date'])) : 'N/A';
                    $received_date = !empty($request['received_date']) ? date('M d, Y h:i A', strtotime($request['received_date'])) : 'N/A';
                    
                    // Determine status class
                    $statusClass = 'status-Pending';
                    if (strpos($request['status'], 'Processing') !== false) {
                        $statusClass = 'status-Processing';
                    } elseif (strpos($request['status'], 'Ready') !== false) {
                        $statusClass = 'status-Ready';
                    } elseif ($request['status'] === 'Completed') {
                        $statusClass = 'status-Completed';
                    } elseif ($request['status'] === 'Declined') {
                        $statusClass = 'status-Declined';
                    }
                    
                    // Determine priority class
                    $priorityClass = 'priority-low';
                    if ($request['priority'] === 'High') {
                        $priorityClass = 'priority-high';
                    } elseif ($request['priority'] === 'Medium') {
                        $priorityClass = 'priority-medium';
                    }
                ?>
                <div class="tracking-card" data-request-id="<?php echo $request['id']; ?>">
                    <div class="card-header">
                        <div>
                            <div class="request-id">Request #<?php echo $request['id']; ?></div>
                            <h2 class="document-title"><?php echo htmlspecialchars($request['document_type']); ?></h2>
                        </div>
                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo htmlspecialchars($request['status']); ?></span>
                    </div>
                    
                    <div class="request-meta">
                        <div class="meta-item">
                            <span class="meta-label">Purpose</span>
                            <span class="meta-value"><?php echo htmlspecialchars($request['purpose']); ?></span>
                        </div>
                        
                        <div class="meta-item">
    <span class="meta-label">Section</span>
    <span class="meta-value">
        <?php echo !empty($request['grade_level']) ? htmlspecialchars($request['grade_level']) : 'Not specified'; ?>
    </span>
</div>
                        <div class="meta-item">
                            <span class="meta-label">ETA / Coverage</span>
                            <span class="meta-value"><?php echo !empty($request['eta']) ? htmlspecialchars($request['eta']) : 'Not specified'; ?></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">Priority</span>
                            <span class="meta-value"><span class="<?php echo $priorityClass; ?>"><?php echo htmlspecialchars($request['priority']); ?></span></span>
                        </div>
                        <div class="meta-item">
                            <span class="meta-label">ULI</span>
                            <span class="meta-value"><?php echo htmlspecialchars($request['uli']); ?></span>
                        </div>
                    </div>
                    
                    <!-- Progress Tracker -->
                    <div class="progress-tracker">
                        <div class="progress-line">
                            <div class="progress-line-fill" data-progress="<?php echo $progressPercentage; ?>"></div>
                        </div>
                        <div class="steps">
                            <div class="step">
                                <div class="step-dot <?php echo $request['status'] != '' ? 'completed' : ''; ?>">
                                    <i class="fas fa-check step-icon"></i>
                                </div>
                                <div class="step-label">Submitted</div>
                            </div>
                            <div class="step">
                                <div class="step-dot <?php echo in_array($request['status'], ['Processing', 'Ready to Pickup', 'Completed']) ? 'completed' : ($request['status'] === 'Pending' ? 'active' : ''); ?>">
                                    <?php if (in_array($request['status'], ['Processing', 'Ready to Pickup', 'Completed'])): ?>
                                        <i class="fas fa-check step-icon"></i>
                                    <?php elseif ($request['status'] === 'Pending'): ?>
                                        <i class="fas fa-hourglass-half step-icon"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="step-label">Processing</div>
                            </div>
                            <div class="step">
                                <div class="step-dot <?php echo in_array($request['status'], ['Ready to Pickup', 'Completed']) ? 'completed' : ($request['status'] === 'Processing' ? 'active' : ''); ?>">
                                    <?php if (in_array($request['status'], ['Ready to Pickup', 'Completed'])): ?>
                                        <i class="fas fa-check step-icon"></i>
                                    <?php elseif ($request['status'] === 'Processing'): ?>
                                        <i class="fas fa-hourglass-half step-icon"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="step-label">Ready for Pickup</div>
                            </div>
                            <div class="step">
                                <div class="step-dot <?php echo $request['status'] === 'Completed' ? 'completed' : ($request['status'] === 'Ready to Pickup' ? 'active' : ''); ?>">
                                    <?php if ($request['status'] === 'Completed'): ?>
                                        <i class="fas fa-check step-icon"></i>
                                    <?php elseif ($request['status'] === 'Ready to Pickup'): ?>
                                        <i class="fas fa-hourglass-half step-icon"></i>
                                    <?php elseif ($request['status'] === 'Declined'): ?>
                                        <i class="fas fa-times step-icon"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="step-label">Completed</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Timeline -->
                    <div class="timeline">
                        <div class="timeline-item">
                            <div class="timeline-date"><?php echo $created_date; ?></div>
                            <div class="timeline-event">Request submitted</div>
                        </div>
                        
                        <?php if (!empty($request['processed_date']) && $request['status'] !== 'Pending'): ?>
                        <div class="timeline-item">
                            <div class="timeline-date"><?php echo $processed_date; ?></div>
                            <div class="timeline-event">Request processing started</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($request['ready_date']) && in_array($request['status'], ['Ready to Pickup', 'Completed'])): ?>
                        <div class="timeline-item">
                            <div class="timeline-date"><?php echo date('M d, Y h:i A', strtotime($request['ready_date'])); ?></div>
                            <div class="timeline-event">Document ready for pickup</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($request['received_date']) && $request['status'] === 'Completed'): ?>
                        <div class="timeline-item">
                            <div class="timeline-date"><?php echo $received_date; ?></div>
                            <div class="timeline-event">Document received</div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($request['status'] === 'Declined'): ?>
                        <div class="timeline-item">
                            <div class="timeline-date"><?php echo !empty($request['declined_date']) ? date('M d, Y h:i A', strtotime($request['declined_date'])) : $processed_date; ?></div>
                            <div class="timeline-event">Request declined</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if (!empty($request['admin_notes'])): ?>
                    <div class="admin-note">
                        <h5><i class="fas fa-info-circle"></i> Admin Notes</h5>
                        <p><?php echo nl2br(htmlspecialchars($request['admin_notes'])); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- jQuery, Bootstrap & SweetAlert JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.3/dist/sweetalert2.all.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // Animate progress bars on page load
            setTimeout(function() {
                $('.progress-line-fill').each(function() {
                    let progress = $(this).data('progress');
                    $(this).css('width', progress + '%');
                });
            }, 300);

            // Set up Server-Sent Events for real-time updates
            if (!!window.EventSource) {
                setupSSE();
            }

            function setupSSE() {
                var source = new EventSource('request_updates.php');
                
                source.addEventListener('message', function(e) {
                    const data = JSON.parse(e.data);
                    if (data.user_id == <?php echo $user_id; ?>) {
                        updateRequestStatus(data);
                    }
                }, false);

                source.addEventListener('error', function(e) {
                    if (e.readyState == EventSource.CLOSED) {
                        // Connection was closed, try to reconnect after a delay
                        setTimeout(setupSSE, 5000);
                    }
                }, false);
            }

            function updateRequestStatus(data) {
                // Find the request card
                const requestCard = $(`.tracking-card[data-request-id="${data.request_id}"]`);
                
                if (requestCard.length) {
                    // Update status badge
                    let statusClass = 'status-Pending';
                    if (data.status.includes('Processing')) {
                        statusClass = 'status-Processing';
                    } else if (data.status.includes('Ready')) {
                        statusClass = 'status-Ready';
                    } else if (data.status === 'Completed') {
                        statusClass = 'status-Completed';
                    } else if (data.status === 'Declined') {
                        statusClass = 'status-Declined';
                    }
                    
                    requestCard.find('.status-badge')
                        .removeClass('status-Pending status-Processing status-Ready status-Completed status-Declined')
                        .addClass(statusClass)
                        .text(data.status);
                    
                    // Update progress bar
                    let progressPercentage = 0;
                    switch (data.status) {
                        case 'Pending': progressPercentage = 25; break;
                        case 'Processing': progressPercentage = 50; break;
                        case 'Ready to Pickup': progressPercentage = 75; break;
                        case 'Completed': 
                        case 'Declined': progressPercentage = 100; break;
                    }
                    
                    const progressBar = requestCard.find('.progress-line-fill');
                    progressBar.data('progress', progressPercentage);
                    progressBar.css('width', progressPercentage + '%');
                    
                    // Update step indicators
                    const steps = requestCard.find('.step-dot');
                    steps.removeClass('active completed');
                    
                    // First step (Submitted) is always completed
                    $(steps[0]).addClass('completed').html('<i class="fas fa-check step-icon"></i>');
                    
                    // Processing step
                    if (data.status === 'Pending') {
                        $(steps[1]).addClass('active').html('<i class="fas fa-hourglass-half step-icon"></i>');
                    } else if (['Processing', 'Ready to Pickup', 'Completed'].includes(data.status)) {
                        $(steps[1]).addClass('completed').html('<i class="fas fa-check step-icon"></i>');
                    }
                    
                    // Ready for Pickup step
                    if (data.status === 'Processing') {
                        $(steps[2]).addClass('active').html('<i class="fas fa-hourglass-half step-icon"></i>');
                    } else if (['Ready to Pickup', 'Completed'].includes(data.status)) {
                        $(steps[2]).addClass('completed').html('<i class="fas fa-check step-icon"></i>');
                    }
                    
                    // Completed step
                    if (data.status === 'Ready to Pickup') {
                        $(steps[3]).addClass('active').html('<i class="fas fa-hourglass-half step-icon"></i>');
                    } else if (data.status === 'Completed') {
                        $(steps[3]).addClass('completed').html('<i class="fas fa-check step-icon"></i>');
                    } else if (data.status === 'Declined') {
                        $(steps[3]).addClass('active').html('<i class="fas fa-times step-icon"></i>');
                    }
                    
                    // Add new timeline item if there's a state change
                    if (data.event_date && data.event_description) {
                        const formattedDate = new Date(data.event_date).toLocaleString('en-US', {
                            month: 'short',
                            day: 'numeric',
                            year: 'numeric',
                            hour: 'numeric',
                            minute: 'numeric',
                            hour12: true
                        });
                        
                        const newTimelineItem = `
                            <div class="timeline-item">
                                <div class="timeline-date">${formattedDate}</div>
                                <div class="timeline-event">${data.event_description}</div>
                            </div>
                        `;
                        
                        requestCard.find('.timeline').append(newTimelineItem);
                    }
                    
                    // Update admin notes if provided
                    if (data.admin_notes) {
                        if (requestCard.find('.admin-note').length) {
                            requestCard.find('.admin-note p').html(data.admin_notes.replace(/\n/g, '<br>'));
                        } else {
                            const noteHtml = `
                                <div class="admin-note">
                                    <h5><i class="fas fa-info-circle"></i> Admin Notes</h5>
                                    <p>${data.admin_notes.replace(/\n/g, '<br>')}</p>
                                </div>
                            `;
                            requestCard.append(noteHtml);
                        }
                    }
                    
                    // Show notification
                    Swal.fire({
                        title: 'Request Updated',
                        text: `Your request #${data.request_id} has been updated to ${data.status}`,
                        icon: 'info',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 5000,
                        timerProgressBar: true
                    });
                }
            }
        });
    </script>
</body>
</html>