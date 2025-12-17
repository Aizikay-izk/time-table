<?php
session_start();
require_once 'conn.php';

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: ' . BASE_URL);
    exit;
}

$user_id = $_SESSION['user_id'];
$section_code = $_SESSION['section_code'];

// Get user details
$user_sql = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($user_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

// Get section details
$section_sql = "SELECT * FROM sections WHERE section_code = ?";
$stmt = $conn->prepare($section_sql);
$stmt->bind_param("s", $section_code);
$stmt->execute();
$section = $stmt->get_result()->fetch_assoc();

// Get timetable for current week
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$current_day = date('l');

// Get all timetable entries for the section
$timetable_sql = "SELECT * FROM timetable WHERE section_code = ? ORDER BY day_of_week, period";
$stmt = $conn->prepare($timetable_sql);
$stmt->bind_param("s", $section_code);
$stmt->execute();
$timetable_result = $stmt->get_result();

// Organize timetable by day
$timetable_by_day = [];
while ($entry = $timetable_result->fetch_assoc()) {
    $day = $entry['day_of_week'];
    if (!isset($timetable_by_day[$day])) {
        $timetable_by_day[$day] = [];
    }
    $timetable_by_day[$day][] = $entry;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?= htmlspecialchars($user['reg_number']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: #f5f7fa;
            color: #333;
        }
        
        .student-header {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .header-left h1 {
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .header-right {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .btn-logout {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-logout:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .dashboard-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .welcome-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .welcome-text h2 {
            color: #4a5568;
            font-size: 24px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .welcome-text p {
            color: #718096;
            font-size: 16px;
        }
        
        .share-options {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .share-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #4a5568;
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .share-btn:hover {
            background: #edf2f7;
            transform: translateY(-2px);
        }
        
        .share-btn.primary {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            border: none;
        }
        
        .share-btn.primary:hover {
            box-shadow: 0 6px 12px rgba(79, 70, 229, 0.2);
        }
        
        .timetable-nav {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            overflow-x: auto;
            padding-bottom: 10px;
        }
        
        .day-tab {
            padding: 15px 25px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-weight: 600;
            color: #4a5568;
            cursor: pointer;
            transition: all 0.3s;
            white-space: nowrap;
        }
        
        .day-tab:hover {
            border-color: #4f46e5;
            color: #4f46e5;
        }
        
        .day-tab.active {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            border-color: #4f46e5;
        }
        
        .timetable-day {
            display: none;
        }
        
        .timetable-day.active {
            display: block;
            animation: fadeIn 0.5s;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .timetable-card {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        
        .timetable-header {
            background: #f8fafc;
            padding: 25px 30px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .timetable-header h2 {
            color: #4a5568;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .timetable-grid {
            padding: 0;
        }
        
        .timetable-row {
            display: grid;
            grid-template-columns: 100px 1fr 1fr 1fr;
            gap: 1px;
            background: #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .timetable-row.header {
            background: #4f46e5;
            color: white;
            font-weight: 600;
        }
        
        .timetable-cell {
            background: white;
            padding: 20px;
            min-height: 80px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        
        .period {
            font-weight: 600;
            color: #4f46e5;
            font-size: 16px;
        }
        
        .subject {
            font-weight: 600;
            font-size: 18px;
            margin-bottom: 5px;
            color: #2d3748;
        }
        
        .teacher, .room, .time {
            color: #718096;
            font-size: 14px;
            margin-top: 4px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .empty-cell {
            color: #a0aec0;
            font-style: italic;
            text-align: center;
            grid-column: span 4;
            padding: 40px 20px;
        }
        
        .info-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-top: 40px;
        }
        
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        
        .info-card h3 {
            color: #4a5568;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-item {
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .info-item i {
            color: #4f46e5;
            width: 20px;
            text-align: center;
        }
        
        .info-label {
            color: #718096;
            font-size: 14px;
            min-width: 120px;
        }
        
        .info-value {
            color: #4a5568;
            font-weight: 600;
        }
        
        .share-qr {
            text-align: center;
            padding: 20px;
            background: #f8fafc;
            border-radius: 10px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .timetable-row {
                grid-template-columns: 80px 1fr;
            }
            
            .timetable-row.header {
                grid-template-columns: 80px 1fr;
            }
            
            .timetable-cell:nth-child(3),
            .timetable-cell:nth-child(4) {
                grid-column: span 1;
            }
            
            .student-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .welcome-card {
                flex-direction: column;
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            .timetable-cell {
                padding: 15px 10px;
            }
            
            .share-options {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="student-header">
        <div class="header-left">
            <h1><i class="fas fa-user-graduate"></i> Student Dashboard</h1>
            <p><?= htmlspecialchars($section['section_name']) ?></p>
        </div>
        <div class="header-right">
            <span>Welcome, <?= htmlspecialchars($user['full_name'] ?: $user['reg_number']) ?></span>
            <a href="logout" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <div class="dashboard-container">
        <div class="welcome-card">
            <div class="welcome-text">
                <h2><i class="fas fa-calendar-check"></i> Your Class Timetable</h2>
                <p>View and share your schedule for the week</p>
            </div>
            <div class="share-options">
                <a href="<?= BASE_URL ?>/share/<?= urlencode($section_code) ?>/<?= urlencode($current_day) ?>" 
                   target="_blank" class="share-btn primary">
                    <i class="fas fa-share"></i> Share Today's Timetable
                </a>
                <a href="<?= BASE_URL ?>/user" class="share-btn">
                    <i class="fas fa-sync-alt"></i> Refresh
                </a>
            </div>
        </div>
        
        <div class="timetable-nav">
            <?php foreach ($days as $index => $day): ?>
            <div class="day-tab <?= $day == $current_day ? 'active' : '' ?>" 
                 data-day="<?= $day ?>">
                <?= $day ?>
                <?php if ($day == $current_day): ?>
                <small style="font-size: 12px; opacity: 0.8;"> (Today)</small>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php foreach ($days as $day): ?>
        <div class="timetable-day <?= $day == $current_day ? 'active' : '' ?>" id="day-<?= $day ?>">
            <div class="timetable-card">
                <div class="timetable-header">
                    <h2><i class="fas fa-calendar-day"></i> <?= $day ?> Schedule</h2>
                </div>
                
                <div class="timetable-grid">
                    <div class="timetable-row header">
                        <div class="timetable-cell">Period</div>
                        <div class="timetable-cell">Subject</div>
                        <div class="timetable-cell">Teacher</div>
                        <div class="timetable-cell">Room & Time</div>
                    </div>
                    
                    <?php if (isset($timetable_by_day[$day])): ?>
                        <?php foreach ($timetable_by_day[$day] as $entry): ?>
                        <div class="timetable-row">
                            <div class="timetable-cell period">
                                Period <?= $entry['period'] ?>
                            </div>
                            <div class="timetable-cell">
                                <div class="subject"><?= htmlspecialchars($entry['subject']) ?></div>
                            </div>
                            <div class="timetable-cell">
                                <div class="teacher">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    <?= htmlspecialchars($entry['teacher'] ?? 'Not assigned') ?>
                                </div>
                            </div>
                            <div class="timetable-cell">
                                <div class="room">
                                    <i class="fas fa-door-open"></i>
                                    <?= htmlspecialchars($entry['room'] ?? 'TBA') ?>
                                </div>
                                <?php if ($entry['start_time']): ?>
                                <div class="time">
                                    <i class="fas fa-clock"></i>
                                    <?= date('h:i A', strtotime($entry['start_time'])) ?> - 
                                    <?= date('h:i A', strtotime($entry['end_time'])) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="timetable-row">
                            <div class="timetable-cell empty-cell">
                                <i class="fas fa-calendar-times"></i>
                                No classes scheduled for <?= $day ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div style="text-align: center; margin-top: 20px;">
                <a href="<?= BASE_URL ?>/share/<?= urlencode($section_code) ?>/<?= urlencode($day) ?>" 
                   target="_blank" class="share-btn" style="display: inline-flex;">
                    <i class="fas fa-external-link-alt"></i> Open Public View
                </a>
            </div>
        </div>
        <?php endforeach; ?>
        
        <div class="info-section">
            <div class="info-card">
                <h3><i class="fas fa-user-circle"></i> Your Information</h3>
                <div class="info-item">
                    <i class="fas fa-id-card"></i>
                    <span class="info-label">Registration Number:</span>
                    <span class="info-value"><?= htmlspecialchars($user['reg_number']) ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-user"></i>
                    <span class="info-label">Full Name:</span>
                    <span class="info-value"><?= htmlspecialchars($user['full_name'] ?: 'Not set') ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-users"></i>
                    <span class="info-label">Section:</span>
                    <span class="info-value"><?= htmlspecialchars($section['section_name']) ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span class="info-label">Joined On:</span>
                    <span class="info-value"><?= date('M d, Y', strtotime($user['created_at'])) ?></span>
                </div>
                <?php if ($user['joined_via_invite']): ?>
                <div class="info-item">
                    <i class="fas fa-link"></i>
                    <span class="info-label">Joined Via:</span>
                    <span class="info-value">Invitation (<?= $user['joined_via_invite'] ?>)</span>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="info-card">
                <h3><i class="fas fa-share-alt"></i> Quick Sharing</h3>
                <p style="color: #718096; margin-bottom: 20px;">Share your timetable with others</p>
                
                <div class="share-qr">
                    <p><strong>Scan to view today's timetable:</strong></p>
                    <div id="qrcode"></div>
                    <p style="margin-top: 10px; font-size: 14px; color: #718096;">
                        Share URL: <?= BASE_URL ?>/share/<?= urlencode($section_code) ?>/<?= urlencode($current_day) ?>
                    </p>
                </div>
                
                <div style="margin-top: 20px;">
                    <p style="color: #718096; font-size: 14px; margin-bottom: 10px;">
                        <i class="fas fa-info-circle"></i> Anyone with the link can view. No login required.
                    </p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.0/build/qrcode.min.js"></script>
    <script>
        // Day tab switching
        document.querySelectorAll('.day-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const day = this.getAttribute('data-day');
                
                // Update active tab
                document.querySelectorAll('.day-tab').forEach(t => {
                    t.classList.remove('active');
                });
                this.classList.add('active');
                
                // Show selected day
                document.querySelectorAll('.timetable-day').forEach(dayDiv => {
                    dayDiv.classList.remove('active');
                });
                document.getElementById('day-' + day).classList.add('active');
            });
        });
        
        // Generate QR Code for today's timetable
        document.addEventListener('DOMContentLoaded', function() {
            const shareUrl = '<?= BASE_URL ?>/share/<?= urlencode($section_code) ?>/<?= urlencode($current_day) ?>';
            const qrcode = new QRCode(document.getElementById("qrcode"), {
                text: shareUrl,
                width: 150,
                height: 150,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
        });
        
        // Auto-switch to current day's tab
        function checkCurrentTime() {
            const now = new Date();
            const currentHour = now.getHours();
            
            // If it's after 6 PM, show tomorrow's schedule
            if (currentHour >= 18) {
                const tomorrowIndex = (<?= array_search($current_day, $days) ?> + 1) % <?= count($days) ?>;
                const tomorrow = <?= json_encode($days) ?>[tomorrowIndex];
                
                document.querySelectorAll('.day-tab').forEach(tab => {
                    tab.classList.remove('active');
                    if (tab.getAttribute('data-day') === tomorrow) {
                        tab.classList.add('active');
                    }
                });
                
                document.querySelectorAll('.timetable-day').forEach(dayDiv => {
                    dayDiv.classList.remove('active');
                });
                document.getElementById('day-' + tomorrow).classList.add('active');
            }
        }
        
        // Check time every hour
        setInterval(checkCurrentTime, 3600000);
        checkCurrentTime();
    </script>
</body>
</html>