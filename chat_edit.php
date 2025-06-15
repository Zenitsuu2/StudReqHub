<?php
// Start session for admin authentication
session_start();

// Database connection
$db_config = [
    'host' => 'localhost',
    'username' => 'root',
    'password' => '',
    'database' => 'school_document_system'
];

// Connect to database
function connectDB() {
    global $db_config;
    $conn = new mysqli(
        $db_config['host'],
        $db_config['username'],
        $db_config['password'],
        $db_config['database']
    );
    
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    return $conn;
}

// Check if admin is logged in, redirect if not


$conn = connectDB();

// Process form submissions
$alert_message = '';
$alert_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle FAQ addition/update
    if (isset($_POST['action']) && $_POST['action'] === 'saveFAQ') {
        $id = isset($_POST['faq_id']) ? intval($_POST['faq_id']) : 0;
        $question_en = $conn->real_escape_string($_POST['question_en']);
        $answer_en = $conn->real_escape_string($_POST['answer_en']);
        $question_tl = $conn->real_escape_string($_POST['question_tl']);
        $answer_tl = $conn->real_escape_string($_POST['answer_tl']);
        $category = $conn->real_escape_string($_POST['category']);
        
        if ($id > 0) {
            // Update existing FAQ
            $sql = "UPDATE chatbot_faqs SET 
                    question_en = '$question_en', 
                    answer_en = '$answer_en',
                    question_tl = '$question_tl', 
                    answer_tl = '$answer_tl',
                    category = '$category',
                    updated_at = NOW() 
                    WHERE id = $id";
            
            if ($conn->query($sql)) {
                logActivity("Updated FAQ ID: $id - $question_en");
                $alert_message = "FAQ updated successfully!";
                $alert_type = "success";
            } else {
                $alert_message = "Error updating FAQ: " . $conn->error;
                $alert_type = "error";
            }
        } else {
            // Add new FAQ
            $sql = "INSERT INTO chatbot_faqs (question_en, answer_en, question_tl, answer_tl, category, created_at, updated_at) 
                    VALUES ('$question_en', '$answer_en', '$question_tl', '$answer_tl', '$category', NOW(), NOW())";
            
            if ($conn->query($sql)) {
                $new_id = $conn->insert_id;
                logActivity("Added new FAQ ID: $new_id - $question_en");
                $alert_message = "New FAQ added successfully!";
                $alert_type = "success";
            } else {
                $alert_message = "Error adding FAQ: " . $conn->error;
                $alert_type = "error";
            }
        }
    }
    
    // Handle FAQ deletion
    if (isset($_POST['action']) && $_POST['action'] === 'deleteFAQ') {
        $id = intval($_POST['faq_id']);
        
        $sql = "DELETE FROM chatbot_faqs WHERE id = $id";
        if ($conn->query($sql)) {
            logActivity("Deleted FAQ ID: $id");
            $alert_message = "FAQ deleted successfully!";
            $alert_type = "success";
        } else {
            $alert_message = "Error deleting FAQ: " . $conn->error;
            $alert_type = "error";
        }
    }
    
    // Handle "What's New" announcement updates
    if (isset($_POST['action']) && $_POST['action'] === 'updateAnnouncement') {
        $announcement_en = $conn->real_escape_string($_POST['announcement_en']);
        $announcement_tl = $conn->real_escape_string($_POST['announcement_tl']);
        
        // Check if announcement exists
        $check = $conn->query("SELECT id FROM chatbot_announcements WHERE type='whats_new' LIMIT 1");
        
        if ($check->num_rows > 0) {
            $row = $check->fetch_assoc();
            $id = $row['id'];
            $sql = "UPDATE chatbot_announcements SET 
                    content_en = '$announcement_en', 
                    content_tl = '$announcement_tl',
                    updated_at = NOW() 
                    WHERE id = $id";
        } else {
            $sql = "INSERT INTO chatbot_announcements (type, content_en, content_tl, created_at, updated_at) 
                    VALUES ('whats_new', '$announcement_en', '$announcement_tl', NOW(), NOW())";
        }
        
        if ($conn->query($sql)) {
            logActivity("Updated 'What's New' announcement");
            $alert_message = "Announcement updated successfully!";
            $alert_type = "success";
        } else {
            $alert_message = "Error updating announcement: " . $conn->error;
            $alert_type = "error";
        }
    }
}

// Function to log admin activity
function logActivity($action) {
    global $conn;
    $admin_id = $_SESSION['admin_id'];
    $action = $conn->real_escape_string($action);
    
    $sql = "INSERT INTO admin_activity_logs (admin_id, action, created_at) 
            VALUES ($admin_id, '$action', NOW())";
    $conn->query($sql);
}

// Get all FAQs
$faqs = [];
$result = $conn->query("SELECT * FROM chatbot_faqs ORDER BY category, id DESC");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $faqs[] = $row;
    }
}

// Get current "What's New" announcement
$announcement = [
    'content_en' => '',
    'content_tl' => ''
];
$result = $conn->query("SELECT content_en, content_tl FROM chatbot_announcements WHERE type='whats_new' LIMIT 1");
if ($result->num_rows > 0) {
    $announcement = $result->fetch_assoc();
}

// Get recent activity logs
$activity_logs = [];
$result = $conn->query("SELECT al.*, a.username 
                       FROM admin_activity_logs al
                       JOIN admins a ON al.admin_id = a.id
                       ORDER BY al.created_at DESC
                       LIMIT 20");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $activity_logs[] = $row;
    }
}

// Get chatbot usage stats
$total_conversations = 0;
$result = $conn->query("SELECT COUNT(*) as total FROM chatbot_conversations");
if ($result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $total_conversations = $row['total'];
}

$top_queries = [];
$result = $conn->query("SELECT query, COUNT(*) as count 
                       FROM chatbot_queries 
                       GROUP BY query 
                       ORDER BY count DESC 
                       LIMIT 10");
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $top_queries[] = $row;
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Chatbot Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.4.8/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .navbar-brand {
            font-weight: bold;
        }
        .container-fluid {
            padding: 20px;
        }
        .card {
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,.1);
        }
        .card-header {
            font-weight: bold;
            background-color: #f1f5f9;
        }
        .nav-tabs .nav-link.active {
            font-weight: bold;
        }
        .table thead th {
            background-color: #f1f5f9;
        }
        .btn-action {
            margin-right: 5px;
        }
        .category-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .stats-card {
            text-align: center;
            padding: 15px;
        }
        .stats-number {
            font-size: 2rem;
            font-weight: bold;
            color: #0d6efd;
        }
        .activity-timestamp {
            font-size: 0.8rem;
            color: #6c757d;
        }
        textarea {
            min-height: 120px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">School Document System - Admin</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="admin_chatbot.php">Chatbot Management</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="admin_dashboard.php">Dashboard</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <span class="navbar-text me-3">
                        Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                    </span>
                    <a href="admin_logout.php" class="btn btn-light btn-sm">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <h1 class="mt-4 mb-4">Chatbot Management</h1>
        
        <div class="row">
            <div class="col-md-8">
                <ul class="nav nav-tabs mb-3" id="myTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="faq-tab" data-bs-toggle="tab" data-bs-target="#faq" type="button">
                            Manage FAQs
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="announcements-tab" data-bs-toggle="tab" data-bs-target="#announcements" type="button">
                            What's New
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button">
                            Activity Log
                        </button>
                    </li>
                </ul>
                
                <div class="tab-content" id="myTabContent">
                    <!-- FAQ Management Tab -->
                    <div class="tab-pane fade show active" id="faq" role="tabpanel">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>Frequently Asked Questions</span>
                                <button class="btn btn-primary btn-sm" onclick="openFaqModal()">Add New FAQ</button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th width="5%">#</th>
                                                <th width="25%">Question (English)</th>
                                                <th width="25%">Question (Tagalog)</th>
                                                <th width="15%">Category</th>
                                                <th width="30%">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($faqs)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center">No FAQs found. Add your first FAQ.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($faqs as $faq): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($faq['id']); ?></td>
                                                    <td><?php echo htmlspecialchars($faq['question_en']); ?></td>
                                                    <td><?php echo htmlspecialchars($faq['question_tl']); ?></td>
                                                    <td>
                                                        <?php 
                                                        $category_class = '';
                                                        switch($faq['category']) {
                                                            case 'diploma':
                                                                $category_class = 'bg-primary text-white';
                                                                break;
                                                            case 'good_moral':
                                                                $category_class = 'bg-success text-white';
                                                                break;
                                                            case 'certificate':
                                                                $category_class = 'bg-info text-white';
                                                                break;
                                                            default:
                                                                $category_class = 'bg-secondary text-white';
                                                        }
                                                        ?>
                                                        <span class="category-badge <?php echo $category_class; ?>">
                                                            <?php echo ucfirst(str_replace('_', ' ', htmlspecialchars($faq['category']))); ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <button class="btn btn-sm btn-info btn-action" onclick="viewFaqDetails(<?php echo $faq['id']; ?>)">
                                                            View
                                                        </button>
                                                        <button class="btn btn-sm btn-primary btn-action" onclick="editFaq(<?php echo $faq['id']; ?>)">
                                                            Edit
                                                        </button>
                                                        <button class="btn btn-sm btn-danger btn-action" 
                                                            onclick="confirmDeleteFaq(<?php echo $faq['id']; ?>, '<?php echo addslashes($faq['question_en']); ?>')">
                                                            Delete
                                                        </button>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- What's New Announcements Tab -->
                    <div class="tab-pane fade" id="announcements" role="tabpanel">
                        <div class="card">
                            <div class="card-header">Update "What's New" Announcements</div>
                            <div class="card-body">
                                <form id="announcementForm" method="post">
                                    <input type="hidden" name="action" value="updateAnnouncement">
                                    
                                    <div class="mb-3">
                                        <label for="announcement_en" class="form-label">Announcement (English)</label>
                                        <textarea class="form-control" id="announcement_en" name="announcement_en" rows="4"><?php echo htmlspecialchars($announcement['content_en']); ?></textarea>
                                        <div class="form-text">This will appear in the chatbot's "What's New" section for English users.</div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="announcement_tl" class="form-label">Announcement (Tagalog)</label>
                                        <textarea class="form-control" id="announcement_tl" name="announcement_tl" rows="4"><?php echo htmlspecialchars($announcement['content_tl']); ?></textarea>
                                        <div class="form-text">This will appear in the chatbot's "What's New" section for Tagalog users.</div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Save Announcement</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Activity Log Tab -->
                    <div class="tab-pane fade" id="activity" role="tabpanel">
                        <div class="card">
                            <div class="card-header">Recent Activity</div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Admin</th>
                                                <th>Action</th>
                                                <th>Date & Time</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($activity_logs)): ?>
                                                <tr>
                                                    <td colspan="3" class="text-center">No activity logs found.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php foreach ($activity_logs as $log): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($log['username']); ?></td>
                                                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                                                    <td class="activity-timestamp">
                                                        <?php echo date('M d, Y H:i:s', strtotime($log['created_at'])); ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Chatbot Stats Column -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">Chatbot Statistics</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo number_format($total_conversations); ?></div>
                                    <div>Total Conversations</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stats-card">
                                    <div class="stats-number"><?php echo number_format(count($faqs)); ?></div>
                                    <div>Total FAQs</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">Top User Queries</div>
                    <div class="card-body">
                        <?php if (empty($top_queries)): ?>
                            <p class="text-center">No queries recorded yet.</p>
                        <?php else: ?>
                            <ul class="list-group">
                                <?php foreach ($top_queries as $index => $query): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center">
                                    <?php echo ($index + 1) . '. ' . htmlspecialchars($query['query']); ?>
                                    <span class="badge bg-primary rounded-pill"><?php echo $query['count']; ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="card mt-4">
                    <div class="card-header">Quick Help</div>
                    <div class="card-body">
                        <h5>Tips for effective chatbot management:</h5>
                        <ul>
                            <li>Keep answers concise but informative</li>
                            <li>Use consistent terminology across FAQs</li>
                            <li>Group related FAQs by category</li>
                            <li>Update "What's New" regularly to highlight changes</li>
                            <li>Review top queries to identify missing FAQs</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- FAQ Modal -->
    <div class="modal fade" id="faqModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="faqModalTitle">Add New FAQ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="faqForm" method="post">
                        <input type="hidden" name="action" value="saveFAQ">
                        <input type="hidden" id="faq_id" name="faq_id" value="0">
                        
                        <div class="mb-3">
                            <label for="category" class="form-label">Category</label>
                            <select class="form-select" id="category" name="category" required>
                                <option value="">Select Category</option>
                                <option value="diploma">Diploma</option>
                                <option value="good_moral">Good Moral Certificate</option>
                                <option value="certificate">Certificate of Completion</option>
                                <option value="general">General Information</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="question_en" class="form-label">Question (English)</label>
                            <input type="text" class="form-control" id="question_en" name="question_en" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="answer_en" class="form-label">Answer (English)</label>
                            <textarea class="form-control" id="answer_en" name="answer_en" rows="3" required></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="question_tl" class="form-label">Question (Tagalog)</label>
                            <input type="text" class="form-control" id="question_tl" name="question_tl" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="answer_tl" class="form-label">Answer (Tagalog)</label>
                            <textarea class="form-control" id="answer_tl" name="answer_tl" rows="3" required></textarea>
                        </div>
                        
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Save FAQ</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View FAQ Details Modal -->
    <div class="modal fade" id="viewFaqModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">FAQ Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">English</div>
                                <div class="card-body">
                                    <h5 id="view_question_en"></h5>
                                    <hr>
                                    <p id="view_answer_en"></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card h-100">
                                <div class="card-header">Tagalog</div>
                                <div class="card-body">
                                    <h5 id="view_question_tl"></h5>
                                    <hr>
                                    <p id="view_answer_tl"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Category:</strong> <span id="view_category"></span></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Last Updated:</strong> <span id="view_updated_at"></span></p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="edit_from_view_btn">Edit This FAQ</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Form (Hidden) -->
    <form id="deleteFaqForm" method="post" style="display: none;">
        <input type="hidden" name="action" value="deleteFAQ">
        <input type="hidden" id="delete_faq_id" name="faq_id" value="">
    </form>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.4.8/dist/sweetalert2.all.min.js"></script>
    <script>
        // Show SweetAlert for success/error messages
        <?php if (!empty($alert_message)): ?>
        Swal.fire({
            icon: '<?php echo $alert_type; ?>',
            title: '<?php echo $alert_message; ?>',
            timer: 2000,
            showConfirmButton: false
        });
        <?php endif; ?>
        
        // Open the FAQ modal for adding new FAQ
        function openFaqModal() {
            // Reset form
            document.getElementById('faqForm').reset();
            document.getElementById('faq_id').value = '0';
            document.getElementById('faqModalTitle').textContent = 'Add New FAQ';
            
            // Open modal
            const faqModal = new bootstrap.Modal(document.getElementById('faqModal'));
            faqModal.show();
        }
        
        // Open the FAQ modal for editing existing FAQ
        function editFaq(id) {
            // Fetch FAQ data via AJAX
            fetch('get_faq.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Populate form with FAQ data
                        document.getElementById('faq_id').value = data.faq.id;
                        document.getElementById('category').value = data.faq.category;
                        document.getElementById('question_en').value = data.faq.question_en;
                        document.getElementById('answer_en').value = data.faq.answer_en;
                        document.getElementById('question_tl').value = data.faq.question_tl;
                        document.getElementById('answer_tl').value = data.faq.answer_tl;
                        
                        // Update modal title
                        document.getElementById('faqModalTitle').textContent = 'Edit FAQ';
                        
                        // Open modal
                        const faqModal = new bootstrap.Modal(document.getElementById('faqModal'));
                        faqModal.show();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to load FAQ data'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to load FAQ data'
                    });
                });
        }
        
        // View FAQ details
        function viewFaqDetails(id) {
            // Fetch FAQ data via AJAX
            fetch('get_faq.php?id=' + id)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Populate view modal with FAQ data
                        document.getElementById('view_question_en').textContent = data.faq.question_en;
                        document.getElementById('view_answer_en').textContent = data.faq.answer_en;
                        document.getElementById('view_question_tl').textContent = data.faq.question_tl;
                        document.getElementById('view_answer_tl').textContent = data.faq.answer_tl;
                        document.getElementById('view_category').textContent = data.faq.category.replace('_', ' ');
                        document.getElementById('view_updated_at').textContent = data.faq.updated_at;
                        // Set up edit button in view modal
document.getElementById('edit_from_view_btn').onclick = function() {
    const faqModal = new bootstrap.Modal(document.getElementById('faqModal'));
    const viewModal = bootstrap.Modal.getInstance(document.getElementById('viewFaqModal'));
    
    viewModal.hide();
    
    // Populate edit form with current data
    document.getElementById('faq_id').value = data.faq.id;
    document.getElementById('category').value = data.faq.category;
    document.getElementById('question_en').value = data.faq.question_en;
    document.getElementById('answer_en').value = data.faq.answer_en;
    document.getElementById('question_tl').value = data.faq.question_tl;
    document.getElementById('answer_tl').value = data.faq.answer_tl;
    
    // Update modal title
    document.getElementById('faqModalTitle').textContent = 'Edit FAQ';
    
    faqModal.show();
};

// Open view modal
const viewFaqModal = new bootstrap.Modal(document.getElementById('viewFaqModal'));
viewFaqModal.show();
} else {
Swal.fire({
    icon: 'error',
    title: 'Error',
    text: data.message || 'Failed to load FAQ data'
});
}
})
.catch(error => {
console.error('Error:', error);
Swal.fire({
    icon: 'error',
    title: 'Error',
    text: 'Failed to load FAQ data'
});
});
}

// Confirm before deleting FAQ
function confirmDeleteFaq(id, question) {
Swal.fire({
title: 'Delete FAQ?',
html: `Are you sure you want to delete:<br><strong>"${question}"</strong>?`,
icon: 'warning',
showCancelButton: true,
confirmButtonColor: '#d33',
cancelButtonColor: '#3085d6',
confirmButtonText: 'Yes, delete it!'
}).then((result) => {
if (result.isConfirmed) {
    document.getElementById('delete_faq_id').value = id;
    document.getElementById('deleteFaqForm').submit();
}
});
}

// Initialize tooltips
const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Tab persistence
document.addEventListener('DOMContentLoaded', function() {
// Show the tab that was last active
const activeTab = localStorage.getItem('activeChatbotTab');
if (activeTab) {
const tab = document.querySelector(`#${activeTab}-tab`);
if (tab) {
    const tabInstance = new bootstrap.Tab(tab);
    tabInstance.show();
}
}

// Store the active tab when changed
const tabEls = document.querySelectorAll('button[data-bs-toggle="tab"]');
tabEls.forEach(tabEl => {
tabEl.addEventListener('shown.bs.tab', function (event) {
    const activeTab = event.target.getAttribute('id').split('-')[0];
    localStorage.setItem('activeChatbotTab', activeTab);
});
});
});

// Form validation
document.getElementById('faqForm').addEventListener('submit', function(e) {
const form = e.target;
const requiredFields = ['category', 'question_en', 'answer_en', 'question_tl', 'answer_tl'];

for (const fieldName of requiredFields) {
const field = form.elements[fieldName];
if (!field.value.trim()) {
    e.preventDefault();
    field.focus();
    Swal.fire({
        icon: 'error',
        title: 'Missing Information',
        text: `Please fill in the ${field.labels[0].textContent} field`
    });
    return;
}
}
});

// Auto-resize textareas
function autoResizeTextarea(textarea) {
textarea.style.height = 'auto';
textarea.style.height = (textarea.scrollHeight) + 'px';
}

document.querySelectorAll('textarea').forEach(textarea => {
// Initial resize
autoResizeTextarea(textarea);

// Resize on input
textarea.addEventListener('input', function() {
    autoResizeTextarea(this);
});
});
</script>
</body>
</html>