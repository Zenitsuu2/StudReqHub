<?php
// Start session
session_start();

// Include database connection
require '../Connection/database.php';

// Define activity types with their badge classes
$activityTypes = [
    'Login' => 'bg-green-100 text-green-800',
    'Logout' => 'bg-gray-100 text-gray-800',
    'Document Request' => 'bg-blue-100 text-blue-800',
    'Request Approved' => 'bg-emerald-100 text-emerald-800',
    'Request Rejected' => 'bg-red-100 text-red-800',
    'Registration' => 'bg-purple-100 text-purple-800',
    'Profile Update' => 'bg-yellow-100 text-yellow-800',
    'Password Change' => 'bg-indigo-100 text-indigo-800',
    'Document Downloaded' => 'bg-cyan-100 text-cyan-800',
];

// Default filters
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$actionFilter = isset($_GET['action']) ? $_GET['action'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 15; // Items per page

// Calculate pagination variables
$offset = ($page - 1) * $perPage;
$startLink = max(1, $page - 2);
$endLink = min($startLink + 4, $page + 2);

// Fetch users
$usersQuery = "SELECT u.*, 
               (SELECT COUNT(*) FROM activity_logs WHERE user_id = u.id) AS activity_count,
               (SELECT MAX(timestamp) FROM activity_logs WHERE user_id = u.id) AS last_activity,
               (SELECT CONCAT(r.document_type, ' - ', r.status) 
                FROM activity_logs al
                LEFT JOIN requests r ON al.user_id = r.id
                WHERE al.user_id = u.id 
                ORDER BY al.timestamp DESC LIMIT 1) AS last_document_status,
               (SELECT r.document_type 
                FROM activity_logs al
                LEFT JOIN requests r ON al.user_id = r.id
                WHERE al.user_id = u.id 
                ORDER BY al.timestamp DESC LIMIT 1) AS document_type
               FROM users u";
$usersStmt = $conn->query($usersQuery);
$users = [];
if ($usersStmt) {
    while ($row = $usersStmt->fetch_assoc()) {
        $users[] = $row;
    }
}

// Fetch activity logs with filters - Fixed parameter binding
$logsQuery = "SELECT 
                COALESCE(al.id, u.id) AS log_id, 
                al.action, 
                al.timestamp, 
                u.created_at, 
                u.last_login, 
                r.document_type, 
                u.firstname, 
                u.middlename, 
                u.lastname, 
                u.extensionname, 
                u.lrn, 
                r.uli, 
                u.email
              FROM users u
              LEFT JOIN activity_logs al ON u.id = al.user_id
              LEFT JOIN requests r ON u.id = r.user_id
              WHERE (DATE(al.timestamp) BETWEEN ? AND ? OR al.id IS NULL)";

$paramTypes = "ss";
$paramValues = [$startDate, $endDate];

if (!empty($actionFilter)) {
    $logsQuery .= " AND al.action = ?";
    $paramTypes .= "s";
    $paramValues[] = $actionFilter;
}

if (!empty($searchTerm)) {
    $searchParam = '%' . $searchTerm . '%';
    $logsQuery .= " AND (u.lrn LIKE ? OR u.uli LIKE ? OR CONCAT(u.firstname, ' ', u.lastname) LIKE ?)";
    $paramTypes .= "sss";
    $paramValues[] = $searchParam;
    $paramValues[] = $searchParam;
    $paramValues[] = $searchParam;
}

$logsQuery .= " ORDER BY al.timestamp DESC LIMIT ?, ?";
$paramTypes .= "ii";
$paramValues[] = $offset;
$paramValues[] = $perPage;

$logsStmt = $conn->prepare($logsQuery);

// Dynamically bind parameters
if ($logsStmt) {
    $bindParams = array();
    $bindParams[] = &$paramTypes;

    for ($i = 0; $i < count($paramValues); $i++) {
        $bindParams[] = &$paramValues[$i];
    }

    call_user_func_array(array($logsStmt, 'bind_param'), $bindParams);

    $logsStmt->execute();
    $result = $logsStmt->get_result();
    $logs = [];

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
    }
} else {
    echo "Error preparing statement: " . $conn->error;
}

// Get total records for pagination - Fixed parameter binding
$totalQuery = "SELECT COUNT(*) FROM activity_logs al
               JOIN users u ON al.user_id = u.id
               WHERE DATE(al.timestamp) BETWEEN ? AND ?";

$totalParamTypes = "ss";
$totalParamValues = [$startDate, $endDate];

if (!empty($actionFilter)) {
    $totalQuery .= " AND al.action = ?";
    $totalParamTypes .= "s";
    $totalParamValues[] = $actionFilter;
}

if (!empty($searchTerm)) {
    $searchParam = '%' . $searchTerm . '%';
    $totalQuery .= " AND (u.lrn LIKE ? OR u.uli LIKE ? OR CONCAT(u.firstname, ' ', u.lastname) LIKE ?)";
    $totalParamTypes .= "sss";
    $totalParamValues[] = $searchParam;
    $totalParamValues[] = $searchParam;
    $totalParamValues[] = $searchParam;
}

$totalStmt = $conn->prepare($totalQuery);

if ($totalStmt) {
    // Create reference array for binding
    $totalBindParams = array();
    $totalBindParams[] = &$totalParamTypes;
    
    for ($i = 0; $i < count($totalParamValues); $i++) {
        $totalBindParams[] = &$totalParamValues[$i];
    }
    
    // Call bind_param with dynamic arguments
    call_user_func_array(array($totalStmt, 'bind_param'), $totalBindParams);
    
    $totalStmt->execute();
    $result = $totalStmt->get_result();
    $totalRecords = $result->fetch_row()[0];
    $totalPages = ceil($totalRecords / $perPage);
} else {
    // Handle preparation error
    $totalRecords = 0;
    $totalPages = 1;
    echo "Error preparing total count statement: " . $conn->error;
}

// Get activity statistics
$statsQuery = "SELECT 
    COUNT(*) AS total_logs,
    COUNT(DISTINCT user_id) AS unique_users,
    SUM(CASE WHEN action = 'Login' THEN 1 ELSE 0 END) AS login_count,
    SUM(CASE WHEN action = 'Document Request' THEN 1 ELSE 0 END) AS request_count,
    SUM(CASE WHEN action = 'Registration' THEN 1 ELSE 0 END) AS registration_count
    FROM activity_logs";

$statsStmt = $conn->query($statsQuery);

if ($statsStmt) {
    $stats = $statsStmt->fetch_assoc();
} else {
    $stats = [
        'total_logs' => 0,
        'unique_users' => 0,
        'login_count' => 0,
        'request_count' => 0,
        'registration_count' => 0
    ];
}

// Function to get badge class for action type
function getActionBadgeClass($action, $activityTypes) {
    return isset($activityTypes[$action]) ? $activityTypes[$action] : 'bg-gray-100 text-gray-800';
}

// Function to get relative time
function getRelativeTime($timestamp) {
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return "Just now";
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . " minute" . ($minutes > 1 ? "s" : "") . " ago";
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . " hour" . ($hours > 1 ? "s" : "") . " ago";
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . " day" . ($days > 1 ? "s" : "") . " ago";
    } else {
        return date("M j, Y", $time);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Activity Dashboard</title>
    
    <!-- Stylesheets and Scripts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <!-- Flatpickr for date picker -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <!-- DataTables for export functionality -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.1/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.3.2/css/buttons.bootstrap5.min.css">
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.1/js/jquery.dataTables.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/1.13.1/js/dataTables.bootstrap5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.3.2/js/dataTables.buttons.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.3.2/js/buttons.bootstrap5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.3.2/js/buttons.html5.min.js"></script>
    <script type="text/javascript" src="https://cdn.datatables.net/buttons/2.3.2/js/buttons.print.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script type="text/javascript" src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    
    <!-- Chart.js for analytics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        :root {
            --sidebar-width: 250px;
            --sidebar-collapsed-width: 70px;
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: var(--sidebar-collapsed-width);
            }
        }
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        .timeline-item {
            position: relative;
            padding-left: 20px;
            margin-bottom: 1.5rem;
        }
        .timeline-item:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 2px;
            background-color: #e5e7eb;
        }
        .timeline-item:last-child:before {
            height: 50%;
        }
        .timeline-marker {
            position: absolute;
            left: -4px;
            top: 0;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background-color: #3b82f6;
            border: 2px solid #fff;
        }
        .filter-section {
            transition: max-height 0.3s ease-out;
            max-height: 0;
            overflow: hidden;
        }
        .filter-section.show {
            max-height: 500px;
        }
        .pulse {
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
        .badge-pill {
            border-radius: 9999px;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .user-card {
            transition: all 0.3s ease;
        }
        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 5px;
        }
        .status-online {
            background-color: #10b981;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
            animation: pulse 2s infinite;
        }
        .status-offline {
            background-color: #6b7280;
        }
        .status-recent {
            background-color: #f59e0b;
        }
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }
        .tab-button {
            padding: 0.5rem 1rem;
            border-bottom: 2px solid transparent;
            cursor: pointer;
        }
        .tab-button.active {
            border-bottom: 2px solid #3b82f6;
            color: #3b82f6;
            font-weight: 600;
        }
        /* Added styles for modal animations */
        .modal.fade .modal-dialog {
            transition: transform 0.3s ease-out;
            transform: translateY(-50px);
        }
        .modal.show .modal-dialog {
            transform: translateY(0);
        }
        /* Timeline styling improvements */
        .timeline-content {
            padding: 15px;
            background-color: #f9fafb;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-top: 5px;
        }
        .timeline-date {
            font-size: 0.75rem;
            color: #6b7280;
        }
        /* Highlight effect for modal information */
        .info-highlight {
            background-color: rgba(59, 130, 246, 0.1);
            border-left: 3px solid #3b82f6;
            padding: 0.5rem;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body class="bg-gray-100">
<?php include 'sidebar.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    
    <div class="main-content">
        <div class="row">
            <div class="col-md-12">
                <div class="flex justify-between items-center mb-4">
                    <h1 class="dashboard-title text-2xl font-bold">
                        <i class="fas fa-chart-line me-2"></i>USER ACTIVITY DASHBOARD
                    </h1>
                    <div class="flex space-x-2">
                        <button id="toggleFilters" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded-md text-sm flex items-center">
                            <i class="fas fa-filter mr-1"></i> Filters
                        </button>
                        <button id="toggleAnalytics" class="bg-purple-500 hover:bg-purple-600 text-white px-3 py-2 rounded-md text-sm flex items-center">
                            <i class="fas fa-chart-bar mr-1"></i> Analytics
                        </button>
                        <button id="exportOptions" class="bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded-md text-sm flex items-center">
                            <i class="fas fa-download mr-1"></i> Export
                        </button>
                        <div id="autoRefreshStatus" class="flex items-center text-sm text-gray-600 cursor-pointer">
                            <span id="refreshIndicator" class="mr-1 text-green-500"><i class="fas fa-sync-alt"></i></span>
                            <span>Auto-refreshing</span>
                        </div>
                    </div>
                </div>
                
                <!-- Analytics Section (Hidden by default) -->
                <div id="analyticsSection" class="mb-6 bg-white p-4 rounded-lg shadow-sm hidden">
                    <h2 class="text-lg font-semibold mb-4">Activity Analytics</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Activity by Type Chart -->
                        <div class="bg-white p-4 rounded-lg shadow-sm">
                            <h3 class="text-md font-semibold mb-2">Activity by Type</h3>
                            <div class="chart-container">
                                <canvas id="activityTypeChart"></canvas>
                            </div>
                        </div>
                        
                        <!-- Activity Timeline Chart -->
                        <div class="bg-white p-4 rounded-lg shadow-sm">
                            <h3 class="text-md font-semibold mb-2">Activity Over Time</h3>
                            <div class="chart-container">
                                <canvas id="activityTimelineChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Advanced Filters Section -->
                <div id="filterSection" class="filter-section mb-6 bg-white p-4 rounded-lg shadow-sm">
                    <form id="filterForm" method="GET" action="" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Date Range Filter -->
                        <div class="form-group">
                            <label for="start_date" class="text-sm font-medium text-gray-700 block mb-1">Start Date</label>
                            <input type="date" id="start_date" name="start_date" class="flatpickr border border-gray-300 rounded-md p-2 w-full" 
                                   value="<?= htmlspecialchars($startDate) ?>">
                        </div>
                        <div class="form-group">
                            <label for="end_date" class="text-sm font-medium text-gray-700 block mb-1">End Date</label>
                            <input type="date" id="end_date" name="end_date" class="flatpickr border border-gray-300 rounded-md p-2 w-full"
                                   value="<?= htmlspecialchars($endDate) ?>">
                        </div>
                        
                        <!-- Action Type Filter -->
                        <div class="form-group">
                            <label for="action" class="text-sm font-medium text-gray-700 block mb-1">Action Type</label>
                            <select id="action" name="action" class="border border-gray-300 rounded-md p-2 w-full">
                                <option value="">All Actions</option>
                                <?php foreach (array_keys($activityTypes) as $type): ?>
                                    <option value="<?= $type ?>" <?= $actionFilter === $type ? 'selected' : '' ?>>
                                        <?= $type ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Search Filter -->
                        <div class="form-group">
                            <label for="search" class="text-sm font-medium text-gray-700 block mb-1">Search</label>
                            <div class="relative">
                                <input type="text" id="search" name="search" 
                                       class="pl-10 pr-4 py-2 w-full rounded-md border border-gray-300" 
                                       placeholder="LRN, ULI, Name..."
                                       value="<?= htmlspecialchars($searchTerm) ?>">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3">
                                    <i class="fas fa-search text-gray-400"></i>
                                </span>
                            </div>
                        </div>
                        
                        <!-- Filter Buttons -->
                        <div class="form-group md:col-span-4 flex justify-end space-x-2">
                            <a href="activity_logs.php" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">
                                Reset
                            </a>
                            <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md">
                                Apply Filters
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Activity Type Legend -->
                <div class="mb-6">
                    <h2 class="text-lg font-semibold mb-2">Activity Types</h2>
                    <div class="flex flex-wrap gap-2">
                    <?php foreach ($activityTypes as $type => $class): ?>
                        <span class="inline-block px-3 py-1 rounded-full text-xs font-medium <?= $class ?>">
                            <?= $type ?>
                        </span>
                    <?php endforeach; ?>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white p-4 rounded-lg shadow-sm flex items-center">
                        <div class="rounded-full bg-blue-100 p-3 mr-4">
                            <i class="fas fa-chart-line text-blue-500"></i>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Total Activities</div>
                            <div class="text-xl font-semibold"><?= $stats['total_logs'] ?></div>
                        </div>
                    </div>
                    <div class="bg-white p-4 rounded-lg shadow-sm flex items-center">
                        <div class="rounded-full bg-green-100 p-3 mr-4">
                            <i class="fas fa-users text-green-500"></i>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Active Users</div>
                            <div class="text-xl font-semibold"><?= $stats['unique_users'] ?></div>
                        </div>
                    </div>
                    <div class="bg-white p-4 rounded-lg shadow-sm flex items-center">
                        <div class="rounded-full bg-purple-100 p-3 mr-4">
                            <i class="fas fa-sign-in-alt text-purple-500"></i>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Login Sessions</div>
                            <div class="text-xl font-semibold"><?= $stats['login_count'] ?></div>
                        </div>
                    </div>
                    <div class="bg-white p-4 rounded-lg shadow-sm flex items-center">
                        <div class="rounded-full bg-yellow-100 p-3 mr-4">
                            <i class="fas fa-file-alt text-yellow-500"></i>
                        </div>
                        <div>
                            <div class="text-sm text-gray-500">Document Requests</div>
                            <div class="text-xl font-semibold"><?= $stats['request_count'] ?></div>
                        </div>
                    </div>
                </div>

                <!-- Tab Navigation -->
                <div class="mb-4 border-b border-gray-200">
                    <ul class="flex flex-wrap -mb-px">
                        <li class="mr-2">
                            <a class="tab-button active" data-tab="activity-logs">
                                <i class="fas fa-history mr-1"></i> Activity Logs
                            </a>
                        </li>
                        <li class="mr-2">
                            <a class="tab-button" data-tab="active-users">
                                <i class="fas fa-user-check mr-1"></i> Active Users
                            </a>
                        </li>
                        <li class="mr-2">
                            <a class="tab-button" data-tab="activity-timeline">
                                <i class="fas fa-stream mr-1"></i> Activity Timeline
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Tab Content -->
                <div class="tab-content active" id="activity-logs">
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                            <h2 class="text-lg font-semibold">Activity Logs</h2>
                            <div class="text-sm text-gray-500">
                                Showing <?= count($logs) ?> of <?= $totalRecords ?> activities
                            </div>
                        </div>
                        
                        <!-- Logs Table -->
                        <div class="overflow-x-auto">
                            <table id="logsTable" class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Log ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">LRN / ULI / Email</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
    <?php if (count($logs) > 0): ?>
    <?php foreach ($logs as $log): ?>
        <?php
        // Construct the full name dynamically
        $fullName = htmlspecialchars($log['firstname']);
        if (!empty($log['middlename']) && strtolower($log['middlename']) !== 'n/a') {
            $fullName .= ' ' . htmlspecialchars($log['middlename']);
        }
        $fullName .= ' ' . htmlspecialchars($log['lastname']);
        if (!empty($log['extensionname']) && strtolower($log['extensionname']) !== 'n/a') {
            $fullName .= ' ' . htmlspecialchars($log['extensionname']);
        }
        ?>
        <tr class="hover:bg-gray-50">
            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                <?= '#' . htmlspecialchars($log['log_id']) ?>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $fullName ?></td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                <div>LRN: <?= htmlspecialchars($log['lrn']) ?></div>
                <div>ULI: <?= htmlspecialchars($log['uli']) ?></div>
                <div>Email: <?= htmlspecialchars($log['email']) ?></div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap">
                <div class="text-sm text-gray-900 font-medium">
                    <?= $log['action'] ? htmlspecialchars($log['action']) : '' ?>
                </div>
                <div class="text-sm text-gray-500">
                    <?php if (!empty($log['created_at'])): ?>
                        <strong>Account Created:</strong> <?= date('F j, Y, g:i A', strtotime($log['created_at'])) ?>
                    <?php endif; ?>
                </div>
                <div class="text-sm text-gray-500">
                    <?php if (!empty($log['last_login'])): ?>
                        <strong>Last Login:</strong> <?= date('F j, Y, g:i A', strtotime($log['last_login'])) ?>
                    <?php endif; ?>
                </div>
                <div class="text-sm text-gray-500">
                    <?php if (!empty($log['document_type'])): ?>
                        <strong>Document Requested:</strong> <?= htmlspecialchars($log['document_type']) ?>
                    <?php endif; ?>
                </div>
            </td>
            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
    <?php if (!empty($log['timestamp'])): ?>
        <?= date('F j, Y, g:i A', strtotime($log['timestamp'])) ?>
    <?php else: ?>
        
    <?php endif; ?>
</td>

            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
               
            </td>
        </tr>
    <?php endforeach; ?>
<?php else: ?>
    <tr>
        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500">No activity logs found.</td>
    </tr>
<?php endif; ?>
</tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 sm:px-6">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-700">
                                    Showing <span class="font-medium"><?= $offset + 1 ?></span> to 
                                    <span class="font-medium"><?= min($offset + $perPage, $totalRecords) ?></span> of 
                                    <span class="font-medium"><?= $totalRecords ?></span> results
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                        <?php if ($page > 1): ?>
                                        <a href="?page=<?= $page - 1 ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&action=<?= urlencode($actionFilter) ?>&search=<?= urlencode($searchTerm) ?>" 
                                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = $startLink; $i <= $endLink && $i <= $totalPages; $i++): ?>
                                        <a href="?page=<?= $i ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&action=<?= urlencode($actionFilter) ?>&search=<?= urlencode($searchTerm) ?>" 
                                           class="relative inline-flex items-center px-4 py-2 border <?= $i === $page ? 'bg-blue-50 border-blue-500 text-blue-600 z-10' : 'border-gray-300 bg-white text-gray-500 hover:bg-gray-50' ?>">
                                            <?= $i ?>
                                        </a>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $totalPages): ?>
                                        <a href="?page=<?= $page + 1 ?>&start_date=<?= urlencode($startDate) ?>&end_date=<?= urlencode($endDate) ?>&action=<?= urlencode($actionFilter) ?>&search=<?= urlencode($searchTerm) ?>" 
                                           class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                        <?php endif; ?>
                                    </nav>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Active Users Tab -->
                <div class="tab-content" id="active-users">
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="p-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold">Active Users</h2>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 p-4">
                            <?php foreach ($users as $user): 
                                // Calculate online status based on last activity
                                $status = 'offline';
                                $statusClass = 'status-offline';
                                $statusText = 'Offline';
                                
                                if (!empty($user['last_activity'])) {
                                    $lastActivityTime = strtotime($user['last_activity']);
                                    $timeDiff = time() - $lastActivityTime;
                                    
                                    if ($timeDiff < 300) { // Within 5 minutes
                                        $status = 'online';
                                        $statusClass = 'status-online';
                                        $statusText = 'Online';
                                    } elseif ($timeDiff < 3600) { // Within 1 hour
                                        $status = 'recent';
                                        $statusClass = 'status-recent';
                                        $statusText = 'Recently Active';
                                    }
                                }
                                
                                // Format the name
                                $userName = $user['firstname'];
                                if (!empty($user['middlename']) && strtolower($user['middlename']) !== 'n/a') {
                                    $userName .= ' ' . $user['middlename'][0] . '.';
                                }
                                $userName .= ' ' . $user['lastname'];
                                if (!empty($user['extensionname']) && strtolower($user['extensionname']) !== 'n/a') {
                                    $userName .= ' ' . $user['extensionname'];
                                }
                            ?>
                            <div class="user-card bg-white rounded-lg border border-gray-200 shadow-sm p-4">
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 h-10 w-10 bg-gray-200 rounded-full flex items-center justify-center">
                                        <i class="fas fa-user text-gray-500"></i>
                                    </div>
                                    <div class="ml-4">
                                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($userName) ?></div>
                                        <div class="text-sm text-gray-500">
                                            <?= htmlspecialchars($user['email']) ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="flex items-center justify-between">
                                        <div class="text-xs text-gray-500">
                                            <span>LRN: <?= htmlspecialchars($user['lrn']) ?></span>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <span>ULI: <?= htmlspecialchars($user['uli']) ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-4 flex items-center justify-between">
                                    <div class="flex items-center">
                                        <span class="status-indicator <?= $statusClass ?>"></span>
                                        <span class="text-xs"><?= $statusText ?></span>
                                    </div>
                                    <div class="text-xs text-gray-500">
                                        <?= htmlspecialchars($user['activity_count']) ?> activities
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <div class="text-sm text-gray-500">
    <?= !empty($user['last_activity']) ? htmlspecialchars($user['last_activity']) : 'No recent activity' ?>
</div>

                                    <button class="text-blue-600 hover:text-blue-800 text-sm view-user-profile" data-user-id="<?= $user['id'] ?>">
                                        View Profile <i class="fas fa-eye ml-1"></i>
                                    </button>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Activity Timeline Tab -->
                <div class="tab-content" id="activity-timeline">
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="p-4 border-b border-gray-200">
                            <h2 class="text-lg font-semibold">Activity Timeline</h2>
                        </div>
                        
                        <div class="p-4">
                            <div class="timeline">
                                <?php if (count($logs) > 0): ?>
                                    <?php foreach ($logs as $index => $log): ?>
                                    <div class="timeline-item">
                                        <div class="timeline-marker"></div>
                                        <div>
                                            <div class="font-semibold">
                                                <?= htmlspecialchars($log['action']) ?>
                                                <span class="font-normal text-gray-600"> by </span>
                                                <?= htmlspecialchars($log['firstname'] . ' ' . $log['lastname']) ?>
                                            </div>
                                            <div class="timeline-date">
                                                <?= date('F j, Y, g:i a', strtotime($log['timestamp'])) ?>
                                                (<?= getRelativeTime($log['timestamp']) ?>)
                                            </div>
                                            <div class="timeline-content">
                                                <div class="text-sm">
                                                    <?php if (!empty($log['details'])): ?>
                                                        <?= htmlspecialchars($log['details']) ?>
                                                    <?php else: ?>
                                                        <span class="text-gray-500">No additional details provided.</span>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <?php if (!empty($log['ip_address'])): ?>
                                                <div class="mt-2 text-xs text-gray-500">
                                                    <i class="fas fa-network-wired mr-1"></i> IP: <?= htmlspecialchars($log['ip_address']) ?>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center py-4 text-gray-500">No activity logs found.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Activity Log Details Modal -->
    <div class="modal fade" id="logDetailsModal" tabindex="-1" aria-labelledby="logDetailsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logDetailsModalLabel">Activity Log Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="logDetails">
                        <div class="flex flex-col space-y-3">
                            <div class="info-highlight">
                                <strong>Log ID:</strong> <span id="modal-log-id"></span>
                            </div>
                            <div>
                                <strong>User:</strong> <span id="modal-user"></span>
                            </div>
                            <div>
                                <strong>Action:</strong> <span id="modal-action"></span>
                            </div>
                            <div>
                                <strong>Document Requested:</strong> <span id="modal-document-type"></span>
                            </div>
                            <div>
                                <strong>Timestamp:</strong> <span id="modal-timestamp"></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Options Modal -->
    <div class="modal fade" id="exportModal" tabindex="-1" aria-labelledby="exportModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exportModalLabel">Export Activity Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="flex flex-col space-y-4">
                        <div class="export-option p-3 border rounded hover:bg-gray-50 cursor-pointer flex items-center">
                            <i class="fas fa-file-excel text-green-600 text-2xl mr-3"></i>
                            <div>
                                <h3 class="font-medium">Export to Excel</h3>
                                <p class="text-sm text-gray-500">Download activity logs as Excel spreadsheet</p>
                            </div>
                        </div>
                        <div class="export-option p-3 border rounded hover:bg-gray-50 cursor-pointer flex items-center">
                            <i class="fas fa-file-csv text-blue-600 text-2xl mr-3"></i>
                            <div>
                                <h3 class="font-medium">Export to CSV</h3>
                                <p class="text-sm text-gray-500">Download activity logs as CSV file</p>
                            </div>
                        </div>
                        <div class="export-option p-3 border rounded hover:bg-gray-50 cursor-pointer flex items-center">
                            <i class="fas fa-file-pdf text-red-600 text-2xl mr-3"></i>
                            <div>
                                <h3 class="font-medium">Export to PDF</h3>
                                <p class="text-sm text-gray-500">Download activity logs as PDF document</p>
                            </div>
                        </div>
                        <div class="export-option p-3 border rounded hover:bg-gray-50 cursor-pointer flex items-center">
                            <i class="fas fa-print text-gray-600 text-2xl mr-3"></i>
                            <div>
                                <h3 class="font-medium">Print</h3>
                                <p class="text-sm text-gray-500">Print activity logs directly</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- User Profile Modal -->
    <div class="modal fade" id="userProfileModal" tabindex="-1" aria-labelledby="userProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="userProfileModalLabel">User Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="userProfileDetails">
                        <div class="flex flex-col space-y-3">
                            <div class="info-highlight">
                                <strong>Name:</strong> <span id="profile-name"></span>
                            </div>
                            <div>
                                <strong>Email:</strong> <span id="profile-email"></span>
                            </div>
                            <div>
                                <strong>LRN:</strong> <span id="profile-lrn"></span>
                            </div>
                            <div>
                                <strong>ULI:</strong> <span id="profile-uli"></span>
                            </div>
                            <div>
                                <strong>Last Activity:</strong> <span id="profile-last-activity"></span>
                            </div>
                            <div>
                                <strong>Activity Count:</strong> <span id="profile-activity-count"></span>
                            </div>
                            
                            <!-- Document Requests Section -->
                            <div class="mt-4">
                                <h6 class="font-semibold mb-2">Document Requests</h6>
                                <div id="profile-document-requests" class="space-y-2">
                                    <!-- Document requests will be populated here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

<script>
    $(document).ready(function() {
        // Initialize date pickers
        flatpickr('.flatpickr', {
            dateFormat: "Y-m-d",
            allowInput: true
        });
        
        // Toggle filters section
        $('#toggleFilters').click(function() {
            $('#filterSection').toggleClass('show');
        });
        
        // Toggle analytics section
        $('#toggleAnalytics').click(function() {
            $('#analyticsSection').toggleClass('hidden');
            if (!$('#analyticsSection').hasClass('hidden')) {
                initCharts();
            }
        });
        
        // Show export modal
        $('#exportOptions').click(function() {
            $('#exportModal').modal('show');
        });
        
        // Tab switching
        $('.tab-button').click(function() {
            $('.tab-button').removeClass('active');
            $(this).addClass('active');
            
            const tabId = $(this).data('tab');
            $('.tab-content').removeClass('active');
            $('#' + tabId).addClass('active');
        });
        
        // View log details
        $('.view-log-details').click(function() {
            const logId = $(this).data('log-id');
            
            // Here you would typically make an AJAX request to get the log details
            // For now, let's just simulate with the data we have
            $.ajax({
                url: 'get_log_details.php',
                method: 'GET',
                data: { id: logId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#modal-log-id').text(response.data.log_id);
                        $('#modal-user').text(response.data.fullname);
                        $('#modal-action').text(response.data.action);
                        $('#modal-created-at').text(response.data.created_at || 'N/A');
                        $('#modal-last-login').text(response.data.last_login || 'N/A');
                        $('#modal-document-type').text(response.data.document_type || 'N/A');

                        $('#logDetailsModal').modal('show');
                    } else {
                        alert('Error loading log details: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error loading log details. Please try again.');
                }
            });
        });
        
        // Auto refresh toggle
        let autoRefreshInterval;
        $('#autoRefreshStatus').click(function() {
            const refreshIcon = $('#refreshIndicator');
            
            if (refreshIcon.hasClass('text-green-500')) {
                // Turn off auto refresh
                refreshIcon.removeClass('text-green-500').addClass('text-gray-500');
                refreshIcon.html('<i class="fas fa-sync-alt"></i>');
                
                if (autoRefreshInterval) {
                    clearInterval(autoRefreshInterval);
                }
            } else {
                // Turn on auto refresh
                refreshIcon.removeClass('text-gray-500').addClass('text-green-500');
                refreshIcon.html('<i class="fas fa-sync-alt fa-spin"></i>');
                
                // Refresh every 30 seconds
                autoRefreshInterval = setInterval(function() {
                    location.reload();
                }, 30000);
            }
        });
        
        // Initialize charts if analytics section is visible
        if (!$('#analyticsSection').hasClass('hidden')) {
            initCharts();
        }
        
        // Function to initialize charts
        function initCharts() {
            // Sample data for charts - In a real app, you'd get this from the backend
            const activityTypeData = {
                labels: ['Login', 'Logout', 'Document Request', 'Request Approved', 'Request Rejected'],
                datasets: [{
                    label: 'Activity Count',
                    data: [<?= $stats['login_count'] ?>, <?= $stats['login_count'] * 0.8 ?>, <?= $stats['request_count'] ?>, <?= $stats['request_count'] * 0.7 ?>, <?= $stats['request_count'] * 0.3 ?>],
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.7)',
                        'rgba(107, 114, 128, 0.7)',
                        'rgba(16, 185, 129, 0.7)',
                        'rgba(245, 158, 11, 0.7)',
                        'rgba(239, 68, 68, 0.7)'
                    ],
                    borderColor: [
                        'rgba(59, 130, 246, 1)',
                        'rgba(107, 114, 128, 1)',
                        'rgba(16, 185, 129, 1)',
                        'rgba(245, 158, 11, 1)',
                        'rgba(239, 68, 68, 1)'
                    ],
                    borderWidth: 1
                }]
            };
            
            // Create sample dates for the past week
            const timeLabels = [];
            const timeData = [];
            
            for (let i = 6; i >= 0; i--) {
                const date = new Date();
                date.setDate(date.getDate() - i);
                timeLabels.push(date.toLocaleDateString(undefined, { month: 'short', day: 'numeric' }));
                
                // Generate random data for demo
                timeData.push(Math.floor(Math.random() * 20) + 10);
            }
            
            const activityTimelineData = {
                labels: timeLabels,
                datasets: [{
                    label: 'Activity Count',
                    data: timeData,
                    fill: false,
                    borderColor: 'rgba(59, 130, 246, 1)',
                    tension: 0.1,
                    pointBackgroundColor: 'rgba(59, 130, 246, 1)',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 5
                }]
            };
            
            // Create the charts
            const typeCtx = document.getElementById('activityTypeChart').getContext('2d');
            const timelineCtx = document.getElementById('activityTimelineChart').getContext('2d');
            
            new Chart(typeCtx, {
                type: 'doughnut',
                data: activityTypeData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
            
            new Chart(timelineCtx, {
                type: 'line',
                data: activityTimelineData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        }
        
        // Initialize DataTables for export functionality
        $('#logsTable').DataTable({
            dom: 'Bfrtip',
            buttons: [
                {
                    extend: 'excel',
                    text: 'Excel',
                    className: 'hidden',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4]
                    }
                },
                {
                    extend: 'csv',
                    text: 'CSV',
                    className: 'hidden',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4]
                    }
                },
                {
                    extend: 'pdf',
                    text: 'PDF',
                    className: 'hidden',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4]
                    }
                },
                {
                    extend: 'print',
                    text: 'Print',
                    className: 'hidden',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4]
                    }
                }
            ],
            paging: false,
            searching: false,
            info: false
        });
        
        // Connect export modal options to DataTables buttons
        $('.export-option').click(function() {
            const index = $(this).index();
            $('.dt-button').eq(index).click();
            $('#exportModal').modal('hide');
        });
    });

    $(document).ready(function () {
        // Handle "View Profile" button click
        $('.view-user-profile').click(function () {
            const userId = $(this).data('user-id');
            console.log('User ID:', userId); // Debugging: Check if userId is correct

            // Make an AJAX request to fetch user details
            $.ajax({
                url: 'get_student_info.php',
                method: 'GET',
                data: { id: userId },
                dataType: 'json',
                success: function (response) {
                    console.log('Response:', response); // Debugging: Check the response
                    if (response.success) {
                        // Populate modal with user details
                        $('#profile-name').text(response.data.fullname);
                        $('#profile-email').text(response.data.email);
                        $('#profile-lrn').text(response.data.lrn);
                        $('#profile-uli').text(response.data.uli);
                        $('#profile-last-activity').text(response.data.last_activity || 'No recent activity');
                        $('#profile-activity-count').text(response.data.activity_count || '0');

                        // Show the modal
                        $('#userProfileModal').modal('show');
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function (xhr, status, error) {
                    console.error('AJAX Error:', status, error); // Debugging: Check AJAX errors
                    alert('Failed to fetch user profile. Please try again.');
                }
            });
        });
    });

    $(document).ready(function () {
    // Handle "View" button click
    $('.view-log-details').click(function () {
        const logId = $(this).data('log-id');

        // Make an AJAX request to fetch log details
        $.ajax({
            url: 'get_log_details.php', // Create this PHP file to fetch log details
            method: 'GET',
            data: { id: logId },
            dataType: 'json',
            success: function (response) {
                if (response.success) {
                    // Populate modal with log details
                    $('#modal-log-id').text(response.data.log_id);
                    $('#modal-user').text(response.data.fullname);
                    $('#modal-action').text(response.data.action);
                    $('#modal-document-type').text(response.data.document_type || 'N/A');

                    // Show the modal
                    $('#logDetailsModal').modal('show');
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function () {
                alert('Failed to fetch log details. Please try again.');
            }
        });
    });
});

function fetchActivityTimeline() {
    $.ajax({
        url: 'get_activity_timeline.php', // Create this PHP file to fetch timeline data
        method: 'GET',
        dataType: 'json',
        success: function (response) {
            if (response.success) {
                const timelineContainer = $('.timeline');
                timelineContainer.empty(); // Clear existing timeline

                response.data.forEach(activity => {
                    const timelineItem = `
                        <div class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div>
                                <div class="font-semibold">
                                    ${activity.action}
                                    <span class="font-normal text-gray-600"> by </span>
                                    ${activity.fullname}
                                </div>
                                <div class="timeline-date">
                                    ${new Date(activity.timestamp).toLocaleString()}
                                </div>
                                <div class="timeline-content">
                                    <strong>Document:</strong> ${activity.document_type || 'N/A'}<br>
                                    <strong>Status:</strong> ${activity.document_status || 'N/A'}
                                </div>
                            </div>
                        </div>
                    `;
                    timelineContainer.append(timelineItem);
                });
            }
        },
        error: function () {
            console.error('Failed to fetch activity timeline.');
        }
    });
}

// Fetch timeline every 30 seconds
setInterval(fetchActivityTimeline, 30000);
fetchActivityTimeline(); // Initial fetch
</script>
<script>
function fetchActivityTimeline() {
    $.ajax({
        url: 'get_activity_timeline.php',
        method: 'GET',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Sort the activity data ascending by fullname
                response.data.sort(function(a, b) {
                    return a.fullname.localeCompare(b.fullname);
                });
                const timelineContainer = $('.timeline');
                timelineContainer.empty(); // Clear the timeline items

                // Build timeline items
                response.data.forEach(activity => {
                    const timelineItem = `
                        <div class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div>
                                <div class="font-semibold">
                                    ${activity.action}
                                    <span class="font-normal text-gray-600"> by </span>
                                    ${activity.fullname}
                                </div>
                                <div class="timeline-date">
                                    ${new Date(activity.timestamp).toLocaleString()}
                                </div>
                                <div class="timeline-content">
                                    <strong>Document:</strong> ${activity.document_type || 'N/A'}<br>
                                    <strong>Status:</strong> ${activity.document_status || 'N/A'}
                                </div>
                            </div>
                        </div>
                    `;
                    timelineContainer.append(timelineItem);
                });
            }
        },
        error: function () {
            console.error('Failed to fetch activity timeline.');
        }
    });
}

// Poll timeline every 30 seconds to keep it realtime
setInterval(fetchActivityTimeline, 30000);
fetchActivityTimeline(); // Initial fetch
</script>
</body>
</html>
<?php
// Debugging: Check the value of log_id
var_dump($log['log_id']);
?>