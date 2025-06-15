<?php
session_start();
require_once '../../Connection/database.php';

// Fetch user's document requests
$queryRequests = "SELECT 
    r.id,
    r.document_type,
    r.status,
    DATE_FORMAT(r.updated_at, '%M %d, %Y %h:%i %p') as updated_at,
    u.firstname,
    u.middlename,
    u.lastname,
    u.extensionname
    FROM requests r
    LEFT JOIN users u ON r.user_id = u.id
    WHERE r.user_id = ?
    ORDER BY r.updated_at DESC";
$stmt = $conn->prepare($queryRequests);
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$resultRequests = $stmt->get_result();

// Fetch upcoming events
$events_query = "SELECT title, description, DATE_FORMAT(start_date, '%M %d, %Y') as formatted_date,
                 DATEDIFF(start_date, CURDATE()) as days_until,
                 event_type
                 FROM events 
                 WHERE end_date >= CURDATE()
                 ORDER BY start_date ASC";
$events_result = $conn->query($events_query);
$events = [];
if ($events_result) {
    while ($event = $events_result->fetch_assoc()) {
        $events[] = $event;
    }
}

// Function to format full name
function formatFullName($firstname, $middlename, $lastname, $extensionname) {
    $name_parts = [$firstname];
    if ($middlename && strtolower($middlename) !== 'n/a') {
        $name_parts[] = $middlename;
    }
    $name_parts[] = $lastname;
    if ($extensionname && strtolower($extensionname) !== 'n/a') {
        $name_parts[] = $extensionname;
    }
    return implode(' ', $name_parts);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Student Request Notifications</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css">
  <!-- Font Awesome Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    :root {
      --primary-color: #1a2a6c;
      --secondary-color: #f4f7fa;
      --accent-color: #ffc107;
      --text-color: #333;
      --light-text: #777;
      --border-radius: 8px;
      --box-shadow: 0 2px 10px rgba(0,0,0,0.1);
      --transition: all 0.3s ease;
    }
    
    body { 
        background-color: var(--secondary-color); 
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        color: var(--text-color);
        line-height: 1.6;
    }
    
    .container { 
        margin: 20px auto; 
        max-width: 900px; 
        background-color: #fff;
        border-radius: var(--border-radius);
        box-shadow: var(--box-shadow);
        padding: 25px;
    }
    
    .header {
        display: flex;
        flex-direction: column;
        gap: 15px;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }
    
    @media (min-width: 768px) {
        .header {
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
        }
    }
    
    .school-name {
        font-weight: 600;
        color: var(--primary-color);
        font-size: 1.2rem;
        text-align: center;
    }
    
    @media (min-width: 768px) {
        .school-name {
            text-align: left;
        }
    }
    
    .back-btn {
        background-color: var(--primary-color);
        color: white;
        padding: 8px 15px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: 500;
        text-align: center;
        display: inline-block;
        transition: var(--transition);
    }
    
    .back-btn:hover {
        background-color: #0f1a4b;
        color: white;
        transform: translateY(-2px);
    }
    
    .section-title { 
        font-weight: 600; 
        color: var(--primary-color); 
        margin-bottom: 20px;
        font-size: 1.3rem;
        position: relative;
        padding-bottom: 8px;
    }
    
    .section-title::after {
        content: '';
        position: absolute;
        bottom: 0;
        left: 0;
        width: 50px;
        height: 3px;
        background-color: var(--primary-color);
    }
    
    .notification-container {
        margin-bottom: 30px;
    }
    
    .notification-item {
        padding: 15px;
        margin-bottom: 15px;
        border-left: 4px solid var(--primary-color);
        border-radius: var(--border-radius);
        background-color: #fff;
        box-shadow: var(--box-shadow);
        transition: var(--transition);
    }
    
    .notification-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 5px 15px rgba(0,0,0,0.15);
    }
    
    .notification-item .title { 
        font-weight: 600; 
        color: var(--text-color); 
        margin-bottom: 5px;
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    @media (min-width: 576px) {
        .notification-item .title {
            flex-direction: row;
            justify-content: space-between;
            align-items: center;
        }
    }
    
    .notification-item .meta { 
        font-size: 0.9rem; 
        color: var(--light-text); 
        margin-bottom: 5px;
    }
    
    .badge {
        font-weight: 500;
        padding: 5px 10px;
        border-radius: 20px;
        font-size: 0.8rem;
        white-space: nowrap;
    }
    
    .empty-notifications { 
        text-align: center; 
        padding: 40px 20px; 
        color: var(--light-text);
        background-color: #f9f9f9;
        border-radius: var(--border-radius);
        border: 1px dashed #ddd;
    }
    
    .empty-notifications i {
        font-size: 2rem;
        margin-bottom: 15px;
        color: #ccc;
    }
    
    .status-pending { background-color: var(--accent-color); color: #000; }
    .status-completed { background-color: #198754; color: #fff; }
    .status-received { background-color: #0dcaf0; color: #000; }
    .status-cancelled { background-color: #dc3545; color: #fff; }
    
    /* Responsive adjustments */
    @media (max-width: 576px) {
        .container {
            margin: 10px;
            padding: 15px;
            border-radius: 0;
        }
        
        .section-title {
            font-size: 1.1rem;
        }
        
        .notification-item {
            padding: 12px;
        }
        
        .badge {
            align-self: flex-start;
            margin-top: 5px;
        }
    }
  </style>
</head>
<body>
<div class="container">
  <div class="header">
    <div class="school-name">VILLA TEODORA ELEMENTARY SCHOOL</div>
    <a href="home.php" class="back-btn">
      <i class="fas fa-arrow-left"></i> Back to Dashboard
    </a>
  </div>

  <div class="notification-container">
    <h3 class="section-title">Your Document Requests</h3>
    <?php if ($resultRequests->num_rows > 0): ?>
      <?php while ($request = $resultRequests->fetch_assoc()): ?>
        <div class="notification-item">
          <div class="title">
            <span><?= htmlspecialchars($request['document_type']) ?></span>
            <span class="badge status-<?= strtolower($request['status']) ?>">
              <?= htmlspecialchars($request['status']) ?>
            </span>
          </div>
          <p class="meta">Requested on: <?= $request['updated_at'] ?></p>
          <?php if ($request['status'] === 'Pending'): ?>
            <p class="text-muted">Your request is being processed. We'll notify you when there's an update.</p>
          <?php elseif ($request['status'] === 'Completed'): ?>
            <p class="text-success fw-bold">Your document is ready for pickup!</p>
          <?php elseif ($request['status'] === 'Received'): ?>
            <p class="text-info">Your request has been received and is awaiting processing.</p>
          <?php endif; ?>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
        <div class="empty-notifications">
          <i class="fas fa-file-alt"></i>
          <p>No document requests found.</p>
          <a href="home.php" class="btn btn-primary mt-3">Make a Request</a>
        </div>
    <?php endif; ?>
  </div>

  <div class="notification-container">
    <h3 class="section-title">Upcoming Events</h3>
    <?php if (!empty($events)): ?>
      <?php foreach ($events as $event): ?>
        <div class="notification-item">
          <div class="title">
            <span><?= htmlspecialchars($event['title']) ?></span>
            <?php if ($event['days_until'] == 0): ?>
              <span class="badge bg-success">Today!</span>
            <?php elseif ($event['days_until'] > 0): ?>
              <span class="badge bg-info">In <?= $event['days_until'] ?> days</span>
            <?php endif; ?>
          </div>
          <p class="meta">
            <i class="far fa-calendar-alt"></i> <?= htmlspecialchars($event['formatted_date']) ?>
            <?php if ($event['event_type']): ?>
              • <i class="fas fa-tag"></i> <?= htmlspecialchars($event['event_type']) ?>
            <?php endif; ?>
          </p>
          <?php if ($event['description']): ?>
            <p class="mt-2"><?= htmlspecialchars($event['description']) ?></p>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="empty-notifications">
        <i class="fas fa-calendar-times"></i>
        <p>No upcoming events at this time.</p>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- Bootstrap JS Bundle -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>