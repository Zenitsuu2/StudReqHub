<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

if (!isset($_SESSION['admin'])) {
    header('Location: login_admin.php');
    exit();
}

include __DIR__ . '/../Connection/database.php';

// Create events table if it doesn't exist
$create_table_query = "CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,     
    event_type VARCHAR(50),
    has_invitation TINYINT(1) DEFAULT 0,
    invitation_file VARCHAR(255),
    color VARCHAR(7)
)";

if (!$conn->query($create_table_query)) {
    die(json_encode(['error' => 'Failed to create events table: ' . $conn->error]));
}

// Check if this is an AJAX request for events data
if(isset($_GET['fetch_events'])) {
    // Check Database Connection
    if (!$conn) {
        die(json_encode(['error' => 'Database connection failed: ' . mysqli_connect_error()]));
    }

    // Fetch Events
    $query = "SELECT id, title, description, start_date AS start, end_date AS end, event_type, 
              has_invitation, invitation_file, color FROM events ORDER BY start_date DESC";
    $result = $conn->query($query);

    if ($result === false) {
        die(json_encode(['error' => 'Error executing query: ' . $conn->error]));
    }

    $events = [];
    while ($row = $result->fetch_assoc()) {
        // Set event color based on event type if not explicitly set
        $color = $row['color'] ?: getEventColor($row['event_type']);
        
        $events[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'description' => $row['description'] ?: '',
            'start' => $row['start'],
            'end' => $row['end'],
            'backgroundColor' => $color,
            'borderColor' => $color,
            'extendedProps' => [
                'event_type' => $row['event_type'],
                'has_invitation' => $row['has_invitation'],
                'invitation_file' => $row['invitation_file'] ?: null // Ensure null if no file exists
            ]
        ];
    }

    // Output JSON Properly
    header('Content-Type: application/json');
    echo json_encode($events);
    exit();
}

// Handle POST Request to Insert Event
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_event') {
    // Check Database Connection
    if (!$conn) {
        die(json_encode(['error' => 'Database connection failed: ' . mysqli_connect_error()]));
    }

    $title = $_POST['title'];
    $description = $_POST['description'];
    $start_date = date('Y-m-d H:i:s', strtotime($_POST['start_date']));
    $end_date = date('Y-m-d H:i:s', strtotime($_POST['end_date']));
    $event_type = $_POST['event_type'];
    $notification_enabled = isset($_POST['notification_enabled']) ? 1 : 0;
    $has_invitation = isset($_POST['has_invitation']) ? 1 : 0;
    
    // Handle file upload for invitation if enabled
    $invitation_file = null;
    if ($has_invitation && isset($_FILES['invitation_file']) && $_FILES['invitation_file']['error'] == 0) {
        $upload_dir = __DIR__ . '/../uploads/invitations/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_name = time() . '_' . basename($_FILES['invitation_file']['name']);
        $target_file = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['invitation_file']['tmp_name'], $target_file)) {
            $invitation_file = $file_name;
        }
    }
    
    // Set color based on event type
    $color = getEventColor($event_type);

    $query = "INSERT INTO events (title, description, start_date, end_date, event_type, 
              notification_enabled, has_invitation, invitation_file, color) 
              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die(json_encode(['error' => 'Prepare failed: ' . $conn->error]));
    }

    $stmt->bind_param('sssssisis', $title, $description, $start_date, $end_date, $event_type, 
                      $notification_enabled, $has_invitation, $invitation_file, $color);
    
    if (!$stmt->execute()) {
        die(json_encode(['error' => 'Execute failed: ' . $stmt->error]));
    }

    echo json_encode(['success' => true]);
    exit();
}

// Handle POST Request to Update Event
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_event') {
    // Check Database Connection
    if (!$conn) {
        die(json_encode(['error' => 'Database connection failed: ' . mysqli_connect_error()]));
    }

    $id = $_POST['id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $start_date = date('Y-m-d H:i:s', strtotime($_POST['start_date']));
    $end_date = date('Y-m-d H:i:s', strtotime($_POST['end_date']));
    $event_type = $_POST['event_type'];
    $notification_enabled = isset($_POST['notification_enabled']) ? 1 : 0;
    $has_invitation = isset($_POST['has_invitation']) ? 1 : 0;

    // Handle file upload for invitation if enabled
    $invitation_file = null;
    if ($has_invitation && isset($_FILES['invitation_file']) && $_FILES['invitation_file']['error'] == 0) {
        $upload_dir = __DIR__ . '/../uploads/invitations/';

        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_name = time() . '_' . basename($_FILES['invitation_file']['name']);
        $target_file = $upload_dir . $file_name;

        if (move_uploaded_file($_FILES['invitation_file']['tmp_name'], $target_file)) {
            $invitation_file = $file_name;
        }
    }

    // Set color based on event type
    $color = getEventColor($event_type);

    $query = "UPDATE events 
              SET title = ?, description = ?, start_date = ?, end_date = ?, event_type = ?, 
                  notification_enabled = ?, has_invitation = ?, invitation_file = ?, color = ? 
              WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        die(json_encode(['error' => 'Prepare failed: ' . $conn->error]));
    }

    $stmt->bind_param('sssssisssi', $title, $description, $start_date, $end_date, $event_type, 
                      $notification_enabled, $has_invitation, $invitation_file, $color, $id);
    
    if (!$stmt->execute()) {
        die(json_encode(['error' => 'Execute failed: ' . $stmt->error]));
    }

    echo json_encode(['success' => true]);
    exit();
}

// Add this in your PHP section where you handle other actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['action']) && $input['action'] == 'delete_event') {
        if (!isset($input['id'])) {
            die(json_encode(['success' => false, 'error' => 'No event ID provided']));
        }

        $query = "DELETE FROM events WHERE id = ?";
        $stmt = $conn->prepare($query);
        
        if (!$stmt) {
            die(json_encode(['success' => false, 'error' => 'Failed to prepare query']));
        }

        $stmt->bind_param('i', $input['id']);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Failed to delete event']);
        }
        exit();
    }
}

// Helper function to get color based on event type
function getEventColor($event_type) {
    $colors = [
        'Graduation Day' => '#8E44AD', // Purple
        'Enrollment Period' => '#27AE60', // Green
        'Document Request Cut-off' => '#F39C12', // Orange
        'Exam Schedule' => '#C0392B', // Red
        'Sem Break' => '#3498DB', // Blue
        'School Program' => '#E74C3C', // Light Red
        'Announcement' => '#2C3E50'  // Dark Blue
    ];
    
    return isset($colors[$event_type]) ? $colors[$event_type] : '#2C3E50'; // Default dark blue
}

// Check for scheduled SMS notifications - run this via cron job daily
if(isset($_GET['send_notifications'])) {
    // Only allow this to be triggered by cron job or admin
    if(!isset($_SESSION['admin']) && $_SERVER['REMOTE_ADDR'] != '127.0.0.1') {
        die('Unauthorized access');
    }
    
    // Get tomorrow's events that have notifications enabled
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $query = "SELECT e.id, e.title, e.start_date, e.description, e.event_type, 
              s.phone_number, s.full_name 
              FROM events e 
              JOIN students s ON 1=1
              WHERE DATE(e.start_date) = ? 
              AND e.notification_enabled = 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param('s', $tomorrow);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while($row = $result->fetch_assoc()) {
        // Send SMS notification
        $message = "REMINDER: " . $row['title'] . " tomorrow at " . 
                  date('h:i A', strtotime($row['start_date'])) . 
                  ". " . substr($row['description'], 0, 100);
        
        // Integrate with your SMS API here
        sendSMS($row['phone_number'], $message);
        
        // Log the notification
        $log_query = "INSERT INTO notification_logs (event_id, student_id, message, sent_date) 
                     VALUES (?, ?, ?, NOW())";
        // Implementation of logging goes here
    }
    
    echo "Notifications sent successfully";
    exit();
}

// Send SMS function - integrate with your preferred SMS gateway
function sendSMS($phone, $message) {
    // Example using a fictional SMS API - replace with your actual SMS gateway
    // This is just a placeholder - implement your actual SMS sending code here
    $api_key = 'YOUR_SMS_API_KEY';
    $sender_id = 'SCHOOL';
    
    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => "https://your-sms-provider.com/api/send",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => json_encode([
            'api_key' => $api_key,
            'sender_id' => $sender_id,
            'phone' => $phone,
            'message' => $message
        ]),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    ]);
    
    $response = curl_exec($curl);
    curl_close($curl);
    
    return $response;
}

// View Invitation function
function viewInvitation($eventId) {
    $iframe = document.getElementById('invitationIframe');
    $iframe = "generate_invitation.php?event_id=" . $eventId;
    $modal = new bootstrap.Modal(document.getElementById('viewInvitationModal'));
    $modal.show();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php include 'sidebar.php'; ?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    

<style>
   

    .event-list {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }

    .event-list-item {
        padding: 15px;
        border-bottom: 1px solid #eee;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .event-list-item:last-child {
        border-bottom: none;
    }

    .view-toggle {
        position: fixed;
        top: 20px;
        right: 30px;
        z-index: 1000;
    }

    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-250px);
        }
        
        .content-wrapper {
            margin-left: 0;
        }
        
        .sidebar.active {
            transform: translateX(0);
        }
        
        .content-wrapper.active {
            margin-left: 250px;
        }
    }

    .btn-toggle-view {
        background: #4361ee;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 5px;
        cursor: pointer;
        margin-right: 10px;
    }

    .btn-toggle-view:hover {
        background: #3651d1;
    }

.floating-add-btn {
    position: fixed;
    bottom: 30px;
    right: 30px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    background-color: #4361ee;
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    cursor: pointer;
    z-index: 1000;
    border: none;
    transition: transform 0.2s;
}

.floating-add-btn:hover {
    transform: scale(1.1);
    background-color: #3651d1;
}

.modal-event-details {
    padding: 20px;
}

.event-header {
    border-bottom: 1px solid #dee2e6;
    padding-bottom: 15px;
    margin-bottom: 15px;
}

.event-time {
    display: flex;
    align-items: center;
    color: #666;
    margin: 10px 0;
}

.event-time i {
    margin-right: 8px;
}

.event-description {
    margin: 15px 0;
    white-space: pre-wrap;
}

.badge-custom {
    padding: 8px 12px;
    border-radius: 4px;
    font-weight: 500;
    font-size: 0.9em;
}

/* Add to your existing style section */
.event-list-item {
    background: white;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.event-list-item:hover {
    transform: translateY(-2px);
}

.event-list-content {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.event-title {
    font-size: 1.2rem;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 5px;
}

.event-type-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 4px;
    color: white;
    font-size: 0.8rem;
    margin-bottom: 8px;
}

.event-actions {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

.event-actions button {
    display: flex;
    align-items: center;
    gap: 5px;
}

.current-month-header {
    border-bottom: 2px solid #4361ee;
    margin-bottom: 2rem;
}

.date-header {
    position: relative;
    color: #6c757d;
}

.date-line {
    height: 1px;
    background: #dee2e6;
}

.events-container {
    position: relative;
}

.events-container::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: #dee2e6;
}
</style>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar of Activities</title>
    
    <!-- jQuery first - FullCalendar requires it -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    
    <!-- FullCalendar 5 bundle has everything needed -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>
    
    <!-- SweetAlert for notifications -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Bootstrap for styling (alternative to Tailwind) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        body {
            padding: 20px;
        }
        #calendar {
            margin: 0 auto;
            max-width: 1200px;
            background-color: white;
            padding: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 5px;
            height: 800px;
        }
        .fc .fc-toolbar-title {
            font-size: 1.5em;
            margin: 0;
        }
        #debug {
            margin-bottom: 20px;
            padding: 15px;
            border-radius: 5px;
        }
        .event-type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            color: white;
            font-size: 12px;
            margin-right: 5px;
        }
        .add-event-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #007bff;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            cursor: pointer;
            z-index: 1000;
        }
        .add-event-btn:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body class="bg-light">
    
    <div class="content-wrapper">
        <div class="view-toggle">
            <button class="btn-toggle-view" onclick="toggleView('calendar')">
                <i class="fas fa-calendar"></i> Calendar View
            </button>
            <button class="btn-toggle-view" onclick="toggleView('list')">
                <i class="fas fa-list"></i> List View
            </button>
        </div>

        <div class="container">
            <h2 class="my-4 text-center">📆 Calendar of Activities</h2>
            
            <!-- List View Container -->
            <div id="eventList" class="event-list" style="display: none;">
                <h3 class="mb-4">Upcoming Events</h3>
                <div id="eventListContainer"></div>
            </div>

            <!-- Existing Calendar Container -->
            <div id="calendarView">
                <div id="debug" class="alert alert-danger" style="display: none;">
                    <p>Debug information will appear here</p>
                </div>
                <div id="calendar"></div>
            </div>
        </div>
    </div>
    
    <!-- Add Event Modal -->
    <div class="modal fade" id="addEventModal" tabindex="-1" aria-labelledby="addEventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addEventModalLabel">Add New Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addEventForm" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_event">
                        
                        <div class="mb-3">
                            <label for="title" class="form-label">Event Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="event_type" class="form-label">Event Type</label>
                            <select class="form-select" id="event_type" name="event_type" required>
                                <option value="">Select Event Type</option>
                                <option value="Graduation Day">🎓 Graduation Day</option>
                                <option value="Enrollment Period">📅 Enrollment Period</option>
                                <option value="Document Request Cut-off">📜 Document Request Cut-off Date</option>
                                <option value="Exam Schedule">📝 Exam Schedule</option>
                                <option value="Sem Break">🏖️ Sem Break (No Classes)</option>
                                <option value="School Program">🎭 School Program</option>
                                <option value="Announcement">📢 Announcement</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="start_date" class="form-label">Start Date & Time</label>
                                    <input type="datetime-local" class="form-control" id="start_date" name="start_date" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="end_date" class="form-label">End Date & Time</label>
                                    <input type="datetime-local" class="form-control" id="end_date" name="end_date" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="4"></textarea>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="notification_enabled" name="notification_enabled" checked>
                            <label class="form-check-label" for="notification_enabled">
                                Send SMS notification to students (1 day before)
                            </label>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="has_invitation" name="has_invitation">
                            <label class="form-check-label" for="has_invitation">
                                Event has invitation file
                            </label>
                        </div>
                        
                        <div class="mb-3" id="invitation_file_section" style="display: none;">
                            <label for="invitation_file" class="form-label">Upload Invitation File (PDF/Image)</label>
                            <input type="file" class="form-control" id="invitation_file" name="invitation_file" accept=".pdf,.jpg,.jpeg,.png">
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveEventBtn">Save Event</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Event Details Modal -->
    <div class="modal fade" id="eventDetailsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventTitle"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body modal-event-details">
                    <div class="event-header">
                        <span id="eventTypeBadge"></span>
                    </div>
                    <div class="event-time">
                        <i class="far fa-clock"></i>
                        <span id="eventTime"></span>
                    </div>
                    <div class="event-description" id="eventDescription"></div>
                    <div id="eventInvitation" class="mt-3" style="display: none;">
                        
                        <a href="#" id="downloadInvitation" class="btn btn-primary btn-sm ms-2">
                            <i class="fas fa-download me-2"></i>Download Invitation
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                   
                   
                </div>
            </div>
        </div>
    </div>

    <!-- View Invitation Modal -->
    <div class="modal fade" id="viewInvitationModal" tabindex="-1" aria-labelledby="viewInvitationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="viewInvitationModalLabel">View Invitation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <iframe id="invitationIframe" src="" style="width: 100%; height: 500px;" frameborder="0"></iframe>
                </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            // Check if FullCalendar is defined
            if (typeof FullCalendar === 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Calendar Error',
                    text: 'Could not initialize calendar. Please refresh the page.'
                });
                return;
            }
            
            var calendarEl = document.getElementById('calendar');
            if (!calendarEl) {
                Swal.fire({
                    icon: 'error',
                    title: 'Calendar Error',
                    text: 'Calendar element not found on page.'
                });
                return;
            }
            
            try {
                var calendar = new FullCalendar.Calendar(calendarEl, {
                    initialView: 'dayGridMonth',
                    headerToolbar: {
                        left: 'prev,next today',
                        center: 'title',
                        right: 'dayGridMonth,timeGridWeek,timeGridDay'
                    },
                    height: 'auto',
                    events: 'calendar_activities.php?fetch_events=1',
                    editable: true,
                    dayMaxEvents: true,
                    eventTimeFormat: {
                        hour: '2-digit',
                        minute: '2-digit',
                        meridiem: 'short'
                    },
                    eventClick: function(info) {
                        showEventDetails(info.event);
                    },
                    eventDrop: function(info) {
                        updateEvent(info.event);
                    },
                    eventResize: function(info) {
                        updateEvent(info.event);
                    },
                    dateClick: function(info) {
                        document.getElementById('start_date').value = info.dateStr + 'T09:00';
                        document.getElementById('end_date').value = info.dateStr + 'T17:00';
                        openAddEventModal();
                    },
                    loading: function(isLoading) {
                        if (isLoading) {
                            // Optional: Show loading indicator
                            // You can add a loading spinner here if needed
                        }
                    },
                    eventDidMount: function(info) {
                        // Silent event mounting - no notification needed
                    }
                });
                
                calendar.render();
                initAddEventForm();
                
            } catch (error) {
                Swal.fire({
                    icon: 'error',
                    title: 'Calendar Error',
                    text: 'Failed to initialize calendar: ' + error.message
                });
            }
            
            function updateEvent(event) {
                var eventData = {
                    id: event.id,
                    start_date: event.start.toISOString(),
                    end_date: event.end ? event.end.toISOString() : event.start.toISOString()
                };
                
                fetch('update_event.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(eventData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Updated!',
                            text: 'Event has been successfully updated.',
                            confirmButtonColor: '#3085d6'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: data.error || 'Failed to update event.',
                            confirmButtonColor: '#d33'
                        });
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: 'Could not update event.',
                        confirmButtonColor: '#d33'
                    });
                });
            }
            
            function showEventDetails(event) {
                // Store current event ID for invitation viewing
                window.currentEventId = event.id;

                // Update modal content
                document.getElementById('eventTitle').textContent = event.title;

                // Format dates
                const startDate = new Date(event.start);
                const endDate = event.end ? new Date(event.end) : null;
                
                let timeStr = `${startDate.toLocaleDateString()} ${startDate.toLocaleTimeString()}`;
                if (endDate) {
                    timeStr += ` - ${endDate.toLocaleDateString()} ${endDate.toLocaleTimeString()}`;
                }
                document.getElementById('eventTime').textContent = timeStr;

                // Event type badge
                const eventType = event.extendedProps.event_type || 'Other';
                const badgeColor = event.backgroundColor || '#2C3E50';
                document.getElementById('eventTypeBadge').innerHTML = `
                    <span class="badge-custom" style="background-color: ${badgeColor}">
                        ${eventType}
                    </span>
                `;

                // Description
                document.getElementById('eventDescription').innerHTML = 
                    event.extendedProps.description || 'No description available';

                // Handle invitation
                const invitationSection = document.getElementById('eventInvitation');
                if (event.extendedProps.has_invitation) {
                    invitationSection.style.display = 'block';
                    document.getElementById('downloadInvitation').href = 
                        `generate_invitation.php?event_id=${event.id}&download=1`;
                } else {
                    invitationSection.style.display = 'none';
                }

                // Show modal
                const modal = new bootstrap.Modal(document.getElementById('eventDetailsModal'));
                modal.show();
            }
            
            function deleteEvent(eventId) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "This will permanently delete this event!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch('delete_event.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: eventId })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire('Deleted!', 'Event has been deleted.', 'success');
                                calendar.refetchEvents();
                                bootstrap.Modal.getInstance(document.getElementById('eventDetailsModal')).hide();
                            } else {
                                Swal.fire('Error!', data.error || 'Failed to delete event.', 'error');
                            }
                        })
                        .catch(error => {
                            Swal.fire('Error!', 'Could not delete event.', 'error');
                        });
                    }
                });
            }
            
            function editEvent(event) {
                // Close the details modal
                bootstrap.Modal.getInstance(document.getElementById('eventDetailsModal')).hide();

                // Populate the Add Event Modal with existing event data
                document.getElementById('title').value = event.title;
                document.getElementById('event_type').value = event.extendedProps.event_type;
                document.getElementById('start_date').value = event.start.toISOString().slice(0, 16); // Format for datetime-local
                document.getElementById('end_date').value = event.end ? event.end.toISOString().slice(0, 16) : '';
                document.getElementById('description').value = event.extendedProps.description || '';
                document.getElementById('notification_enabled').checked = event.extendedProps.notification_enabled || false;
                document.getElementById('has_invitation').checked = event.extendedProps.has_invitation || false;

                // Show the invitation file section if the event has an invitation
                document.getElementById('invitation_file_section').style.display = event.extendedProps.has_invitation ? 'block' : 'none';

                // Change the Save button to Update
                const saveButton = document.getElementById('saveEventBtn');
                saveButton.textContent = 'Update Event';
                saveButton.onclick = function () {
                    updateEvent(event.id);
                };

                // Show the Add Event Modal
                const modal = new bootstrap.Modal(document.getElementById('addEventModal'));
                modal.show();
            }
            loadEventList(); // Initial load
        });
        
        function openAddEventModal() {
            var modal = new bootstrap.Modal(document.getElementById('addEventModal'));
            modal.show();
        }
        
        function initAddEventForm() {
            // Show/hide invitation file upload based on checkbox
            document.getElementById('has_invitation').addEventListener('change', function() {
                document.getElementById('invitation_file_section').style.display = 
                    this.checked ? 'block' : 'none';
            });
            
            // Handle form submission
            document.getElementById('saveEventBtn').addEventListener('click', function() {
                saveEvent();
            });
        }

        function saveEvent() {
            const form = document.getElementById('addEventForm');
            const formData = new FormData(form);
            formData.append('action', 'add_event');

            fetch('calendar_activities.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Event Added!',
                        text: 'Your event has been successfully added to the calendar.',
                        confirmButtonColor: '#3085d6'
                    }).then(() => {
                        document.querySelector('#addEventModal').modal('hide');
                        calendar.refetchEvents();
                        form.reset();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.error || 'Failed to add event.',
                        confirmButtonColor: '#d33'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Could not add event.',
                    confirmButtonColor: '#d33'
                });
            });
        }

        function updateEvent(eventId) {
            const form = document.getElementById('addEventForm');
            const formData = new FormData(form);
            formData.append('action', 'update_event');
            formData.append('id', eventId); // Include the event ID

            fetch('calendar_activities.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Event Updated!',
                        text: 'The event has been successfully updated.',
                        confirmButtonColor: '#3085d6'
                    }).then(() => {
                        document.querySelector('#addEventModal').modal('hide');
                        calendar.refetchEvents(); // Refresh the calendar events
                        form.reset(); // Reset the form
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.error || 'Failed to update the event.',
                        confirmButtonColor: '#d33'
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Could not update the event.',
                    confirmButtonColor: '#d33'
                });
            });
        }

        function viewInvitation(eventId) {
            const iframe = document.getElementById('invitationIframe');
            iframe.src = `generate_invitation.php?event_id=${eventId}`;
            const modal = new bootstrap.Modal(document.getElementById('viewInvitationModal'));
            modal.show();
        }

        // Add this to your existing script section
        function toggleView(view) {
            const calendarView = document.getElementById('calendarView');
            const eventList = document.getElementById('eventList');
            
            if (view === 'calendar') {
                calendarView.style.display = 'block';
                eventList.style.display = 'none';
            } else {
                calendarView.style.display = 'none';
                eventList.style.display = 'block';
                loadEventList();
            }
        }

        // Update the loadEventList function
        function loadEventList() {
            fetch('calendar_activities.php?fetch_events=1')
            .then(response => response.json())
            .then(events => {
                const container = document.getElementById('eventListContainer');
                container.innerHTML = '';
                
                // Get current date and time
                const now = new Date();
                
                // Filter only current and future events
                const currentEvents = events.filter(event => {
                    const eventEndDate = new Date(event.end);
                    return eventEndDate >= now;
                });
                
                // Sort events by start date
                currentEvents.sort((a, b) => new Date(a.start) - new Date(b.start));
                
                // Group events by date
                const eventsByDate = {};
                currentEvents.forEach(event => {
                    const dateStr = new Date(event.start).toLocaleDateString('default', {
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric',
                        weekday: 'long'
                    });
                    if (!eventsByDate[dateStr]) {
                        eventsByDate[dateStr] = [];
                    }
                    eventsByDate[dateStr].push(event);
                });
                
                // Current month header
                const currentMonth = now.toLocaleString('default', { month: 'long', year: 'numeric' });
                const monthHeader = document.createElement('div');
                monthHeader.className = 'current-month-header mb-4';
                monthHeader.innerHTML = `
                    <h2 class="text-2xl font-bold text-primary p-3 bg-light rounded-lg text-center">
                        <i class="far fa-calendar-alt mr-2"></i>${currentMonth}
                    </h2>
                `;
                container.appendChild(monthHeader);
                
                // Create date sections for remaining events
                Object.keys(eventsByDate).forEach(dateStr => {
                    const dateSection = document.createElement('div');
                    dateSection.className = 'date-section mb-4';
                    dateSection.innerHTML = `
                        <div class="date-header d-flex align-items-center mb-3">
                            <div class="date-line flex-grow-1 border-bottom border-2 border-secondary"></div>
                            <h4 class="mx-3 text-secondary font-weight-bold mb-0">${dateStr}</h4>
                            <div class="date-line flex-grow-1 border-bottom border-2 border-secondary"></div>
                        </div>
                        <div class="events-container ps-3"></div>
                    `;
                    
                    const eventsContainer = dateSection.querySelector('.events-container');
                    
                    eventsByDate[dateStr].forEach(event => {
                        // Only add event if it hasn't ended yet
                        const eventEndDate = new Date(event.end);
                        if (eventEndDate >= now) {
                            const startTime = new Date(event.start).toLocaleTimeString('default', {
                                hour: '2-digit',
                                minute: '2-digit'
                            });
                            const endTime = eventEndDate.toLocaleTimeString('default', {
                                hour: '2-digit',
                                minute: '2-digit'
                            });
                            
                            const eventElement = document.createElement('div');
                            eventElement.className = 'event-list-item mb-3 bg-white rounded-lg shadow-sm';
                            eventElement.innerHTML = `
                                <div class="event-list-content p-4">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="event-title mb-2">${event.title}</h5>
                                            <span class="event-type-badge" 
                                                  style="background-color: ${event.backgroundColor}">
                                                ${event.extendedProps.event_type}
                                            </span>
                                            <div class="text-muted mt-2">
                                                <i class="far fa-clock me-2"></i>${startTime} - ${endTime}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="event-actions mt-3 pt-3 border-top">
                                        <button class="btn btn-sm btn-primary me-2" 
                                                onclick='viewEventDetails(${JSON.stringify(event)})'>
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn btn-sm btn-info me-2" 
                                                onclick='editEventFromList(${JSON.stringify(event)})'>
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger" 
                                                onclick='deleteEventFromList(${JSON.stringify({ id: event.id })})'>
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            `;
                            
                            eventsContainer.appendChild(eventElement);
                        }
                    });
                    
                    // Only add date section if it has events
                    if (eventsContainer.children.length > 0) {
                        container.appendChild(dateSection);
                    }
                });
                
                // Show message if no current or future events
                if (Object.keys(eventsByDate).length === 0) {
                    container.innerHTML += `
                        <div class="text-center text-muted py-5">
                            <i class="far fa-calendar-times fa-3x mb-3"></i>
                            <p>No upcoming events scheduled</p>
                        </div>
                    `;
                }
            })
            .catch(error => console.error('Error loading events:', error));
        }

        // Add auto-refresh functionality
        setInterval(loadEventList, 60000); // Refresh every minute

        // Add these new functions for list view actions
        function viewEventDetails(event) {
            const modal = new bootstrap.Modal(document.getElementById('eventDetailsModal'));
            
            // Update modal content
            document.getElementById('eventTitle').textContent = event.title;
            document.getElementById('eventTypeBadge').innerHTML = `
                <span class="badge-custom" style="background-color: ${event.backgroundColor}">
                    ${event.extendedProps.event_type}
                </span>
            `;
            
            const startDate = new Date(event.start);
            const endDate = new Date(event.end);
            document.getElementById('eventTime').innerHTML = `
                <i class="far fa-clock"></i>
                ${startDate.toLocaleDateString()} ${startDate.toLocaleTimeString()} - 
                ${endDate.toLocaleDateString()} ${endDate.toLocaleTimeString()}
            `;
            
            document.getElementById('eventDescription').innerHTML = event.extendedProps.description || 'No description available';
            
            // Handle invitation if exists
            const invitationSection = document.getElementById('eventInvitation');
            if (event.extendedProps.has_invitation) {
                invitationSection.style.display = 'block';
                document.getElementById('downloadInvitation').href = `generate_invitation.php?event_id=${event.id}&download=1`;
            } else {
                invitationSection.style.display = 'none';
            }
            
            modal.show();
        }

        function editEventFromList(event) {
            const modal = new bootstrap.Modal(document.getElementById('addEventModal'));
            
            // Populate form with event data
            document.getElementById('title').value = event.title;
            document.getElementById('event_type').value = event.extendedProps.event_type;
            document.getElementById('description').value = event.extendedProps.description || '';
            document.getElementById('start_date').value = new Date(event.start).toISOString().slice(0, 16);
            document.getElementById('end_date').value = new Date(event.end).toISOString().slice(0, 16);
            
            // Update form for edit mode
            document.getElementById('addEventModalLabel').textContent = 'Edit Event';
            document.getElementById('saveEventBtn').textContent = 'Update Event';
            
            // Add hidden input for event ID
            let hiddenInput = document.getElementById('event_id');
            if (!hiddenInput) {
                hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.id = 'event_id';
                hiddenInput.name = 'id';
                document.getElementById('addEventForm').appendChild(hiddenInput);
            }
            hiddenInput.value = event.id;
            
            modal.show();
        }

        function deleteEventFromList(eventData) {
            Swal.fire({
                title: 'Are you sure?',
                text: "This action cannot be undone!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('calendar_activities.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'delete_event',
                            id: eventData.id
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted!',
                                text: 'Event has been successfully deleted',
                                confirmButtonColor: '#4361ee'
                            }).then(() => {
                                loadEventList(); // Refresh the list view
                                calendar.refetchEvents(); // Refresh the calendar view
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: data.error || 'Failed to delete event',
                                confirmButtonColor: '#d33'
                            });
                        }
                    });
                }
            });
        }

        // Add responsive sidebar toggle
        document.addEventListener('DOMContentLoaded', function() {
            const menuToggle = document.createElement('button');
            menuToggle.className = 'btn btn-primary d-md-none';
            menuToggle.style.position = 'fixed';
            menuToggle.style.left = '10px';
            menuToggle.style.top = '10px';
            menuToggle.style.zIndex = '1001';
            menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            document.body.appendChild(menuToggle);

            menuToggle.addEventListener('click', function() {
                document.querySelector('.sidebar').classList.toggle('active');
                document.querySelector('.content-wrapper').classList.toggle('active');
            });
        });

        // Add these event listeners after your showEventDetails function
        document.addEventListener('DOMContentLoaded', function() {
            // ... existing DOMContentLoaded code ...

            // Edit button handler
            document.getElementById('editEventBtn').addEventListener('click', function() {
                const modal = bootstrap.Modal.getInstance(document.getElementById('eventDetailsModal'));
                modal.hide();

                // Get the current event data
                const event = calendar.getEventById(window.currentEventId);
                if (!event) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Could not find event data',
                        confirmButtonColor: '#d33'
                    });
                    return;
                }

                // Populate form with event data
                document.getElementById('title').value = event.title;
                document.getElementById('event_type').value = event.extendedProps.event_type;
                document.getElementById('description').value = event.extendedProps.description || '';
                document.getElementById('start_date').value = event.start.toISOString().slice(0, 16);
                document.getElementById('end_date').value = event.end ? event.end.toISOString().slice(0, 16) : '';
                
                // Show edit modal
                const addEventModal = new bootstrap.Modal(document.getElementById('addEventModal'));
                addEventModal.show();

                // Update form submission handler
                document.getElementById('saveEventBtn').onclick = function() {
                    const formData = new FormData(document.getElementById('addEventForm'));
                    formData.append('id', window.currentEventId);
                    formData.append('action', 'update_event');

                    fetch('calendar_activities.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Updated!',
                                text: 'Event has been successfully updated',
                                confirmButtonColor: '#4361ee'
                            }).then(() => {
                                addEventModal.hide();
                                calendar.refetchEvents();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: data.error || 'Failed to update event',
                                confirmButtonColor: '#d33'
                            });
                        }
                    });
                };
            });

            // Delete button handler
            document.getElementById('deleteEventBtn').addEventListener('click', function() {
                Swal.fire({
                    title: 'Are you sure?',
                    text: "This action cannot be undone!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        fetch('calendar_activities.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'delete_event',
                                id: window.currentEventId
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted!',
                                    text: 'Event has been successfully deleted',
                                    confirmButtonColor: '#4361ee'
                                }).then(() => {
                                    const modal = bootstrap.Modal.getInstance(document.getElementById('eventDetailsModal'));
                                    modal.hide();
                                    calendar.refetchEvents();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error!',
                                    text: data.error || 'Failed to delete event',
                                    confirmButtonColor: '#d33'
                                });
                            }
                        });
                    }
                });
            });
        });
    </script>
    <button class="floating-add-btn" onclick="openAddEventModal()">
        <i class="fas fa-plus"></i>
    </button>
</body>
</html>