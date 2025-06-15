<?php
session_start();
include __DIR__ . '/../Connection/database.php';

// Fetch all calendar activities, ensuring no duplicate announcements
$query = "SELECT DISTINCT title, description, start_date, end_date, event_type, color FROM events ORDER BY start_date DESC";
$result = $conn->query($query);

if ($result === false) {
    die('Error executing query: ' . $conn->error);
}

$events = [];
$seen_events = [];
while ($row = $result->fetch_assoc()) {
    $event_key = $row['title'] . '_' . $row['start_date']; // Unique identifier
    if (!isset($seen_events[$event_key])) {
        $seen_events[$event_key] = true;
        $events[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Events</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }
        body {
            background: linear-gradient(to right, #003366, #0099cc);
            color: white;
            text-align: center;
            padding: 20px;
        }
        .container {
            max-width: 1100px;
            margin: auto;
            padding: 20px;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            padding-bottom: 20px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.3);
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .logo img {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: white;
            padding: 5px;
        }
        .title {
            text-align: left;
        }
        .title h1 {
            font-size: 26px;
            font-weight: bold;
        }
        .title p {
            font-size: 14px;
            letter-spacing: 2px;
            color: #f0f0f0;
        }
        .nav {
            display: flex;
            gap: 20px;
        }
        .nav a {
            color: white;
            text-decoration: none;
            font-weight: bold;
            padding: 10px 15px;
            border-radius: 5px;
            transition: 0.3s;
        }
        .nav a:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .buttons {
            display: flex;
            justify-content: center;
            gap: 25px;
            margin-top: 40px;
            flex-wrap: wrap;
        }
        .button {
            background: white;
            color: #003366;
            padding: 20px;
            width: 200px;
            border-radius: 15px;
            text-align: center;
            cursor: pointer;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
            transition: transform 0.3s, box-shadow 0.3s;
            text-decoration: none;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .button:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.3);
        }
        .button img {
            width: 50px;
            height: 50px;
            margin-bottom: 10px;
        }
        .images {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        .image-box {
            background: white;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: black;
            font-weight: bold;
            font-size: 20px;
            padding: 40px;
            height: 200px;
            overflow: hidden;
        }
        .image-box img {
            max-width: 100%;
            max-height: 100%;
            object-fit: cover;
            border-radius: 10px;
        }
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }
            .nav {
                flex-direction: column;
                align-items: center;
                gap: 10px;
                margin-top: 15px;
            }
        }
    </style>
    <div class="container mt-4">
        <h2 class="mb-4">📅 Events and Announcements</h2>
        <?php if (!empty($events)): ?>
            <div class="list-group">
                <?php foreach ($events as $event): ?>
                    <div class="list-group-item list-group-item-action">
                        <h5 class="mb-1" style="color: <?php echo htmlspecialchars($event['color']); ?>;">
                            <?php echo htmlspecialchars($event['title']); ?>
                        </h5>
                        <p class="mb-1"><?php echo htmlspecialchars($event['description']); ?></p>
                        <small>
                            <strong>Start:</strong> <?php echo date('M d, Y h:i A', strtotime($event['start_date'])); ?><br>
                            <strong>End:</strong> <?php echo date('M d, Y h:i A', strtotime($event['end_date'])); ?><br>
                            <strong>Type:</strong> <?php echo htmlspecialchars($event['event_type']); ?>
                        </small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p>No events or announcements available.</p>
        <?php endif; ?>
    </div>
</body>
</html>
