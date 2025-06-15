<?php
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['admin'])) {
    header('Location: admin_login.php');
    exit();
}

include '../Connection/database.php';

// Update the query to include all status types and add ORDER BY for latest dates first
$query = "SELECT r.id, r.user_id, u.lrn, u.dob, u.uli,
          CONCAT(
            u.firstname, ' ',
            CASE 
              WHEN LOWER(u.middlename) = 'n/a' OR u.middlename = '' THEN '' 
              ELSE CONCAT(u.middlename, ' ') 
            END,
            u.lastname,
            CASE 
              WHEN LOWER(u.extensionname) = 'n/a' OR u.extensionname = '' THEN '' 
              ELSE CONCAT(' ', u.extensionname) 
            END
          ) AS student_name,
          guardian_name AS parent_name,
          r.document_type, r.purpose, r.status,
          r.received_date, r.created_at, r.updated_at, r.priority,
          r.school_year, r.decline_reason
          FROM requests r
          JOIN users u ON r.user_id = u.id
          WHERE r.status IN ('Received', 'Completed', 'History', 'Declined')
          ORDER BY 
            CASE 
              WHEN r.updated_at IS NOT NULL 
                   AND r.updated_at != '0000-00-00 00:00:00' 
                   AND r.updated_at != '1970-01-01 00:00:00' 
              THEN r.updated_at 
              ELSE r.received_date 
            END DESC";

$result_history = $conn->query($query);

if ($result_history === false) {
    die('Error executing query: ' . $conn->error);
}

// Change this line to use a consistent variable name
$requests = $result_history->fetch_all(MYSQLI_ASSOC);

// Add this query to fetch completed requests
$historyQuery = "SELECT r.*, u.firstname, u.lastname, u.middlename, u.extensionname, u.contact, u.lrn, u.email 
                 FROM requests r 
                 JOIN users u ON r.user_id = u.id 
                 WHERE r.status = 'Completed'
                 ORDER BY r.updated_at DESC";

$historyResult = $conn->query($historyQuery);
$completedRequests = $historyResult->fetch_all(MYSQLI_ASSOC);

// Additional sorting for any records that might need it
// (This is a backup to the SQL ORDER BY in case there are any issues with the database sorting)
usort($requests, function($a, $b) {
    // Use updated_at if valid, otherwise use received_date
    $timeA = (!empty($a['updated_at']) && $a['updated_at'] !== '1970-01-01 00:00:00' && 
              $a['updated_at'] !== '0000-00-00 00:00:00')
             ? strtotime($a['updated_at']) 
             : strtotime($a['received_date']);
    
    $timeB = (!empty($b['updated_at']) && $b['updated_at'] !== '1970-01-01 00:00:00' && 
              $b['updated_at'] !== '0000-00-00 00:00:00')
             ? strtotime($b['updated_at'])
             : strtotime($b['received_date']);
    
    // Handle invalid dates by setting them to 0 (oldest)
    if ($timeA === false) $timeA = 0;
    if ($timeB === false) $timeB = 0;
    
    return $timeB - $timeA; // descending order: latest first
});
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
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-block;
            min-width: 120px;
            text-align: center;
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

        .status-declined {
            background-color: #ffebee;
            color: #c62828;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-weight: 500;
        }

        .reason-text {
            color: #c62828;
            font-style: italic;
            margin-top: 0.5rem;
            font-size: 0.875rem;
        }

        .status-received {
            background-color: #b8daff;
            color: #004085;
        }

        .status-completed {
            background-color: #c3e6cb;
            color: #155724;
        }

        .status-history {
            background-color: #ffeeba;
            color: #856404;
        }

        .status-declined {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* Add a highlight for the newest records */
        tr.newest-record {
            background-color: rgba(184, 218, 255, 0.3) !important;
        }
    </style>
</head>
<body>
<div>
    <?php include 'sidebar.php'; ?>
    <div class="dashboard-container flex-grow-1 p-4">
        <div class="requests-table">
            <h4 class="mb-4">Request History</h4>

            <!-- Search box -->
            <input type="text" 
                   id="searchInputHistory" 
                   class="form-control mb-3" 
                   placeholder="Search across all fields (LRN, Name, Document Type, etc.)" 
                   onkeyup="searchHistory()">

            <!-- Add this after the search input -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="statusFilter" class="form-label">Filter by Status</label>
                    <select class="form-select" id="statusFilter" onchange="filterByStatus()">
                        <option value="">All Status</option>
                        <option value="Received">Received</option>
                        <option value="Completed">Completed</option>
                        <option value="History">History</option>
                        <option value="Declined">Declined</option>
                    </select>
                </div>
                <!-- ... existing date filter and buttons ... -->
            </div>

            <div class="row mb-3">
                <div class="col-md-3">
                    <label for="specificDate" class="form-label">Specific Date</label>
                    <input type="date" id="specificDate" class="form-control" onchange="filterByDate()">
                </div>
               
                <!-- Update the export PDF button -->
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-secondary" id="exportPdf">
                        <i class="fas fa-file-pdf me-2"></i>Export to PDF
                    </button>
                </div>
            </div>
            <!-- Table with a custom header style -->
            <table class="table table-bordered table-striped align-middle">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">LRN</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ULI</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Birthday</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Document Type</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purpose</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Completed Date</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Decline Reason</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (!empty($requests)): ?>
                        <?php 
                        // Get today's date for highlighting newest records
                        $today = date('Y-m-d');
                        
                        foreach ($requests as $index => $request): 
                            // Determine if this is a recent record (for highlighting)
                            $requestDate = '';
                            if (!empty($request['updated_at']) && $request['updated_at'] !== '1970-01-01 00:00:00' && $request['updated_at'] !== '0000-00-00 00:00:00') {
                                $requestDate = date('Y-m-d', strtotime($request['updated_at']));
                            } elseif (!empty($request['received_date']) && $request['received_date'] !== '1970-01-01 00:00:00' && $request['received_date'] !== '0000-00-00 00:00:00') {
                                $requestDate = date('Y-m-d', strtotime($request['received_date']));
                            }
                            
                            $isNewest = ($index < 3); // Consider top 3 records as newest
                        ?>
                            <tr class="history-row <?= $isNewest ? 'newest-record' : '' ?>">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($request['lrn']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($request['uli'] ?? 'N/A') ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($request['student_name']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= !empty($request['dob']) ? date('M d, Y', strtotime($request['dob'])) : 'N/A' ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($request['document_type']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($request['purpose']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php
                                    $statusClass = '';
                                    switch($request['status']) {
                                        case 'Received':
                                            $statusClass = 'status-received';
                                            break;
                                        case 'Completed':
                                            $statusClass = 'status-completed';
                                            break;
                                        case 'History':
                                            $statusClass = 'status-history';
                                            break;
                                        case 'Declined':
                                            $statusClass = 'status-declined';
                                            break;
                                        default:
                                            $statusClass = 'status-received';
                                    }
                                    ?>
                                    <span class="status-badge <?= $statusClass ?>">
                                        <?= htmlspecialchars($request['status']) ?>
                                    </span>
                                    <?php if($request['status'] === 'Declined' && !empty($request['decline_reason'])): ?>
                                        <div class="reason-text mt-1">
                                            <small><i class="fas fa-info-circle"></i> <?= htmlspecialchars($request['decline_reason']) ?></small>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php 
                                        $displayDate = 'N/A';
                                        if ($request['status'] === 'Pending' 
                                            && !empty($request['created_at']) 
                                            && $request['created_at'] !== '1970-01-01 00:00:00'
                                            && $request['created_at'] !== '0000-00-00 00:00:00') {
                                            $displayDate = date('M d, Y h:i A', strtotime($request['created_at']));
                                        } elseif (($request['status'] === 'Completed' || $request['status'] === 'Declined') 
                                            && !empty($request['updated_at']) 
                                            && $request['updated_at'] !== '1970-01-01 00:00:00'
                                            && $request['updated_at'] !== '0000-00-00 00:00:00') {
                                            $displayDate = date('M d, Y h:i A', strtotime($request['updated_at']));
                                        } elseif (!empty($request['received_date']) 
                                            && $request['received_date'] !== '1970-01-01 00:00:00'
                                            && $request['received_date'] !== '0000-00-00 00:00:00') {
                                            $displayDate = date('M d, Y h:i A', strtotime($request['received_date']));
                                        } else {
                                            $displayDate = date('M d, Y h:i A');
                                        }
                                        echo $displayDate;
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php
                                        // New Completed Date column: show updated_at when status is Completed.
                                        if ($request['status'] === 'Completed'
                                            && !empty($request['updated_at']) 
                                            && $request['updated_at'] !== '1970-01-01 00:00:00'
                                            && $request['updated_at'] !== '0000-00-00 00:00:00') {
                                            echo date('M d, Y h:i A', strtotime($request['updated_at']));
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= htmlspecialchars($request['decline_reason'] ?? 'N/A') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-4">No records found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<!-- Bootstrap JS Bundle -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
<!-- Custom Scripts -->
<script src="js/scripts.js"></script>
<!-- jsPDF and jsPDF-AutoTable -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.4.0/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.13/jspdf.plugin.autotable.min.js"></script>
<script>
    // Replace the existing searchHistory function with this enhanced version
    function searchHistory() {
        const input = document.getElementById('searchInputHistory');
        const filter = input.value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            let text = '';
            // Get text from all columns except the last one (decline reason)
            for (let i = 0; i < row.cells.length - 1; i++) {
                text += row.cells[i].textContent + ' ';
            }
            text = text.toLowerCase();
            
            // Show/hide row based on search
            if (text.includes(filter)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        // Update the "No records found" message
        const visibleRows = document.querySelectorAll('tbody tr[style=""]').length;
        const noRecordsRow = document.querySelector('.no-records-row');
        
        if (visibleRows === 0) {
            if (!noRecordsRow) {
                const tbody = document.querySelector('tbody');
                const newRow = document.createElement('tr');
                newRow.className = 'no-records-row';
                newRow.innerHTML = `
                    <td colspan="9" class="text-center py-4">
                        <div class="alert alert-info mb-0">
                            No matching records found
                        </div>
                    </td>
                `;
                tbody.appendChild(newRow);
            }
        } else if (noRecordsRow) {
            noRecordsRow.remove();
        }
    }

    function filterByDate() {
        const specificDate = document.getElementById('specificDate').value; // Format: "YYYY-MM-DD"
        const rows = document.querySelectorAll('tr.history-row');
        
        if (!specificDate) {
            // If date is cleared, show all rows
            rows.forEach(row => {
                row.style.display = '';
            });
            return;
        }
        
        rows.forEach(row => {
            // Assume column index 7 holds the Request Date (formatted as "M d, Y h:i A")
            const dateText = row.cells[7].textContent.trim();
            const parsedDate = new Date(dateText);

            // If the date cannot be parsed, hide the row
            if(isNaN(parsedDate.getTime())) {
                row.style.display = 'none';
                return;
            }
            
            // Format the parsed date to "YYYY-MM-DD"
            const year = parsedDate.getFullYear();
            const month = String(parsedDate.getMonth() + 1).padStart(2, '0');
            const day = String(parsedDate.getDate()).padStart(2, '0');
            const formattedDate = `${year}-${month}-${day}`;
            
            // Show the row if the formatted date matches the input, otherwise hide it
            if (formattedDate === specificDate) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    }

    function filterByStatus() {
        const statusFilter = document.getElementById('statusFilter').value.toLowerCase();
        const rows = document.querySelectorAll('tbody tr');
        
        rows.forEach(row => {
            const statusCell = row.querySelector('td:nth-child(7)'); // Adjust index based on status column
            if (statusCell) {
                const status = statusCell.textContent.trim().toLowerCase();
                if (statusFilter === '' || status.includes(statusFilter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            }
        });
    }

    function exportToPDF() {
        // Initialize jsPDF
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('landscape');
        
        // Get filter values
        const statusFilter = document.getElementById('statusFilter').value;
        const dateFilter = document.getElementById('specificDate').value;
        
        // Set up document title and header
        doc.setFontSize(18);
        doc.setTextColor(41, 128, 185);
        doc.text('StudentRequestHub - Request History Report', 14, 20);
        
        // Add metadata and filter information
        doc.setFontSize(11);
        doc.setTextColor(100);
        let yPos = 30;
        
        // Add date and time
        const now = new Date().toLocaleString('en-PH', { timeZone: 'Asia/Manila' });
        doc.text(`Generated on: ${now}`, 14, yPos);
        yPos += 8;
        
        // Add filter information
        if (statusFilter) {
            doc.text(`Status Filter: ${statusFilter}`, 14, yPos);
            yPos += 8;
        }
        if (dateFilter) {
            doc.text(`Date Filter: ${dateFilter}`, 14, yPos);
            yPos += 8;
        }
        
        // Get visible rows (respecting current filters)
        const visibleRows = Array.from(document.querySelectorAll('tbody tr')).filter(row => 
            row.style.display !== 'none'
        );
        
        // Prepare table data from visible rows
        const tableData = visibleRows.map(row => ({
            lrn: row.cells[0].textContent.trim(),
            uli: row.cells[1].textContent.trim(),
            name: row.cells[2].textContent.trim(),
            birthday: row.cells[3].textContent.trim(),
            document: row.cells[4].textContent.trim(),
            status: row.cells[6].textContent.trim(),
            date: row.cells[7].textContent.trim(),
            reason: row.cells[8].textContent.trim()
        }));
        
        // Sort tableData descending by the "date" field (latest first)
        tableData.sort((a, b) => new Date(b.date) - new Date(a.date));
        
        // Define table columns for jsPDF-AutoTable
        const tableColumns = [
            { header: 'LRN', dataKey: 'lrn' },
            { header: 'ULI', dataKey: 'uli' },
            { header: 'Student Name', dataKey: 'name' },
            { header: 'Birthday', dataKey: 'birthday' },
            { header: 'Document', dataKey: 'document' },
            { header: 'Status', dataKey: 'status' },
            { header: 'Request Date', dataKey: 'date' },
            { header: 'Reason', dataKey: 'reason' }
        ];

        // Add the table to the PDF
        doc.autoTable({
            columns: tableColumns,
            body: tableData,
            startY: yPos + 5,
            styles: {
                fontSize: 8,
                cellPadding: 3
            },
            headStyles: {
                fillColor: [41, 128, 185],
                textColor: [255, 255, 255],
                fontStyle: 'bold'
            },
            alternateRowStyles: {
                fillColor: [245, 245, 245]
            },
            columnStyles: {
                status: {
                    cellCallback: function(cell) {
                        // Color-code the status if needed
                        switch(cell.raw.toLowerCase()) {
                            case 'received':
                                cell.styles.textColor = [0, 64, 133];
                                break;
                            case 'completed':
                                cell.styles.textColor = [21, 87, 36];
                                break;
                            case 'declined':
                                cell.styles.textColor = [114, 28, 36];
                                break;
                        }
                    }
                }
            },
            didDrawPage: function(data) {
                doc.setFontSize(8);
                doc.text(
                    `Page ${data.pageNumber} of ${doc.internal.getNumberOfPages()}`,
                    doc.internal.pageSize.width - 20,
                    doc.internal.pageSize.height - 10
                );
            }
        });

        // Add summary at the end if desired
        doc.setFontSize(10);
        const lastY = doc.lastAutoTable.finalY + 10;
        doc.text(`Total Records: ${tableData.length}`, 14, lastY);

        // Save the PDF file
        const fileName = `Request_History_${statusFilter || 'All'}_${new Date().toISOString().split('T')[0]}.pdf`;
        doc.save(fileName);
    }

    document.getElementById('exportPdf').addEventListener('click', exportToPDF);

    // Add event listeners after page loads
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize filters
        filterByStatus();
        
        // Clear date filter
        document.getElementById('specificDate').value = '';
    });
</script>
</body>
</html>