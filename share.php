<?php
session_start();
require_once 'conn.php';

// Get parameters from URL
$section_code = $_GET['section'] ?? '';
$day = $_GET['day'] ?? '';

// Validate parameters
if (empty($section_code) || empty($day)) {
    die("Invalid URL parameters");
}

// Get section details
$section_sql = "SELECT * FROM sections WHERE section_code = ?";
$stmt = $conn->prepare($section_sql);
$stmt->bind_param("s", $section_code);
$stmt->execute();
$section_result = $stmt->get_result();
$section = $section_result->fetch_assoc();

if (!$section) {
    die("Section not found");
}

// Get timetable for this section and day
$timetable_sql = "SELECT * FROM timetable 
                  WHERE section_code = ? 
                  AND day_of_week = ?
                  ORDER BY period ASC";
$stmt = $conn->prepare($timetable_sql);
$stmt->bind_param("ss", $section_code, $day);
$stmt->execute();
$timetable_result = $stmt->get_result();

// Track view
trackView($section_code, $day);

// Generate share URL
$share_url = BASE_URL . "/share/" . urlencode($section_code) . "/" . urlencode($day);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($section['section_name']) ?> - <?= $day ?> Timetable</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.0/build/qrcode.min.js"></script>
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
            line-height: 1.6;
        }
        
        .share-header {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 30px 20px;
            text-align: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .share-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
        }
        
        .share-header p {
            opacity: 0.9;
            font-size: 16px;
            margin-bottom: 20px;
        }
        
        .share-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        .share-btn {
            padding: 12px 24px;
            border: none;
            border-radius: 50px;
            background: white;
            color: #4f46e5;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .share-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.15);
        }
        
        .share-btn.whatsapp {
            background: #25D366;
            color: white;
        }
        
        .share-btn.print {
            background: #667eea;
            color: white;
        }
        
        .share-btn.copy {
            background: #4f46e5;
            color: white;
        }
        
        .timetable-container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .timetable-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 30px;
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
        }
        
        .cta-section {
            text-align: center;
            padding: 50px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            margin: 30px 0;
        }
        
        .cta-section h3 {
            color: #4a5568;
            margin-bottom: 20px;
            font-size: 24px;
        }
        
        .cta-section p {
            color: #718096;
            margin-bottom: 30px;
            font-size: 16px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .btn-login {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 16px 32px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            text-decoration: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(79, 70, 229, 0.3);
        }
        
        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(79, 70, 229, 0.4);
        }
        
        .qr-section {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 10px;
        }
        
        #qrcode {
            margin: 20px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            display: inline-block;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .url-display {
            background: #f1f5f9;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            word-break: break-all;
            font-family: monospace;
            font-size: 14px;
            color: #4a5568;
            border: 1px solid #e2e8f0;
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
            
            .share-buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .share-btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            .share-header h1 {
                font-size: 22px;
            }
            
            .timetable-cell {
                padding: 15px 10px;
            }
            
            .subject {
                font-size: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="share-header">
        <h1>
            <i class="fas fa-calendar-alt"></i>
            <?= htmlspecialchars($section['section_name']) ?> - <?= $day ?> Timetable
        </h1>
        <p>Shared timetable • No login required • Auto-updates</p>
        
        <div class="share-buttons">
            <button class="share-btn copy" onclick="copyShareLink()">
                <i class="fas fa-copy"></i> Copy Link
            </button>
            <button class="share-btn whatsapp" onclick="shareWhatsApp()">
                <i class="fab fa-whatsapp"></i> WhatsApp
            </button>
            <button class="share-btn print" onclick="window.print()">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
    
    <div class="timetable-container">
        <div class="timetable-card">
            <div class="timetable-header">
                <h2><i class="fas fa-clock"></i> Today's Schedule</h2>
            </div>
            
            <div class="timetable-grid">
                <div class="timetable-row header">
                    <div class="timetable-cell">Period</div>
                    <div class="timetable-cell">Subject</div>
                    <div class="timetable-cell">Teacher</div>
                    <div class="timetable-cell">Room & Time</div>
                </div>
                
                <?php if ($timetable_result->num_rows > 0): ?>
                    <?php while ($row = $timetable_result->fetch_assoc()): ?>
                        <div class="timetable-row">
                            <div class="timetable-cell period">
                                Period <?= $row['period'] ?>
                            </div>
                            <div class="timetable-cell">
                                <div class="subject"><?= htmlspecialchars($row['subject']) ?></div>
                            </div>
                            <div class="timetable-cell">
                                <div class="teacher">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                    <?= htmlspecialchars($row['teacher'] ?? 'Not assigned') ?>
                                </div>
                            </div>
                            <div class="timetable-cell">
                                <div class="room">
                                    <i class="fas fa-door-open"></i>
                                    <?= htmlspecialchars($row['room'] ?? 'TBA') ?>
                                </div>
                                <?php if ($row['start_time']): ?>
                                <div class="time">
                                    <i class="fas fa-clock"></i>
                                    <?= date('h:i A', strtotime($row['start_time'])) ?> - 
                                    <?= date('h:i A', strtotime($row['end_time'])) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="timetable-row">
                        <div class="timetable-cell empty-cell" colspan="4">
                            <i class="fas fa-calendar-times"></i>
                            No classes scheduled for <?= $day ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="qr-section">
            <h3><i class="fas fa-qrcode"></i> Scan QR Code</h3>
            <p>Scan to save this timetable on your phone</p>
            <div id="qrcode"></div>
        </div>
        
        <div class="url-display">
            <strong>Share URL:</strong><br>
            <span id="shareUrl"><?= $share_url ?></span>
        </div>
        
        <div class="cta-section">
            <h3><i class="fas fa-user-plus"></i> Want More Features?</h3>
            <p>Login to view your complete timetable, receive updates, and manage your schedule</p>
            <a href="<?= BASE_URL ?>" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> Login to Full System
            </a>
        </div>
    </div>
    
    <script>
        // Generate QR Code
        document.addEventListener('DOMContentLoaded', function() {
            const qrcode = new QRCode(document.getElementById("qrcode"), {
                text: "<?= $share_url ?>",
                width: 200,
                height: 200,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
        });
        
        // Copy share link
        function copyShareLink() {
            const url = document.getElementById('shareUrl').textContent;
            navigator.clipboard.writeText(url).then(() => {
                alert('Link copied to clipboard!');
            });
        }
        
        // Share via WhatsApp
        function shareWhatsApp() {
            const url = encodeURIComponent("<?= $share_url ?>");
            const text = encodeURIComponent("Check out our class timetable for <?= $day ?>");
            window.open(`https://wa.me/?text=${text}%0A${url}`, '_blank');
        }
        
        // Web Share API
        function shareNative() {
            if (navigator.share) {
                navigator.share({
                    title: '<?= htmlspecialchars($section['section_name']) ?> Timetable',
                    text: 'Check our class schedule for <?= $day ?>',
                    url: '<?= $share_url ?>'
                });
            }
        }
    </script>
</body>
</html>

<?php
function trackView($section_code, $day) {
    global $conn;
    
    // Check if view exists
    $check_sql = "SELECT id FROM shared_views WHERE section_code = ? AND day = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("ss", $section_code, $day);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Update existing view
        $update_sql = "UPDATE shared_views SET view_count = view_count + 1, last_viewed = NOW() 
                       WHERE section_code = ? AND day = ?";
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("ss", $section_code, $day);
        $stmt->execute();
    } else {
        // Insert new view
        $insert_sql = "INSERT INTO shared_views (section_code, day, view_count, last_viewed) 
                       VALUES (?, ?, 1, NOW())";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("ss", $section_code, $day);
        $stmt->execute();
    }
}
?>