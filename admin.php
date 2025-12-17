<?php
session_start();
require_once 'conn.php';

// Check authentication and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ' . BASE_URL);
    exit;
}

$admin_id = $_SESSION['user_id'];
$section_code = $_SESSION['section_code'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_invitation'])) {
        $expires_at = $_POST['expires_at'] ? date('Y-m-d H:i:s', strtotime($_POST['expires_at'])) : NULL;
        $max_uses = $_POST['max_uses'] ? intval($_POST['max_uses']) : 0;
        $invite_code = generateInviteCode();
        
        $sql = "INSERT INTO invitations (section_code, invite_code, created_by, max_uses, expires_at) 
                VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssiis", $section_code, $invite_code, $admin_id, $max_uses, $expires_at);
        $stmt->execute();
        
        $_SESSION['success'] = "Invitation created successfully!";
    }
    
    if (isset($_POST['revoke_invitation'])) {
        $invite_id = intval($_POST['invite_id']);
        $sql = "UPDATE invitations SET is_active = 0 WHERE id = ? AND created_by = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $invite_id, $admin_id);
        $stmt->execute();
        
        $_SESSION['success'] = "Invitation revoked successfully!";
    }
    
    if (isset($_POST['add_timetable'])) {
        $day = $_POST['day'];
        $period = intval($_POST['period']);
        $subject = $_POST['subject'];
        $teacher = $_POST['teacher'];
        $room = $_POST['room'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];
        
        $sql = "INSERT INTO timetable (section_code, day_of_week, period, subject, teacher, room, start_time, end_time) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssisssss", $section_code, $day, $period, $subject, $teacher, $room, $start_time, $end_time);
        $stmt->execute();
        
        $_SESSION['success'] = "Timetable entry added!";
    }
    
    if (isset($_POST['delete_timetable'])) {
        $entry_id = intval($_POST['entry_id']);
        $sql = "DELETE FROM timetable WHERE id = ? AND section_code = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $entry_id, $section_code);
        $stmt->execute();
        
        $_SESSION['success'] = "Timetable entry deleted!";
    }
    
    header('Location: ' . BASE_URL . '/admin');
    exit;
}

// Get section details
$section_sql = "SELECT * FROM sections WHERE section_code = ?";
$stmt = $conn->prepare($section_sql);
$stmt->bind_param("s", $section_code);
$stmt->execute();
$section = $stmt->get_result()->fetch_assoc();

// Get active invitations
$invitations_sql = "SELECT * FROM invitations WHERE section_code = ? AND is_active = 1 ORDER BY created_at DESC";
$stmt = $conn->prepare($invitations_sql);
$stmt->bind_param("s", $section_code);
$stmt->execute();
$invitations = $stmt->get_result();

// Get students in section
$students_sql = "SELECT * FROM users WHERE section_code = ? AND role = 'student' ORDER BY reg_number";
$stmt = $conn->prepare($students_sql);
$stmt->bind_param("s", $section_code);
$stmt->execute();
$students = $stmt->get_result();

// Get timetable
$timetable_sql = "SELECT * FROM timetable WHERE section_code = ? ORDER BY day_of_week, period";
$stmt = $conn->prepare($timetable_sql);
$stmt->bind_param("s", $section_code);
$stmt->execute();
$timetable = $stmt->get_result();

// Generate invite code function
function generateInviteCode() {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    return substr(str_shuffle($chars), 0, 8);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?= htmlspecialchars($section['section_name']) ?></title>
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
        
        .admin-header {
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
        
        .admin-container {
            display: grid;
            grid-template-columns: 280px 1fr;
            min-height: calc(100vh - 80px);
        }
        
        .admin-sidebar {
            background: white;
            border-right: 1px solid #e2e8f0;
            padding: 30px 0;
        }
        
        .sidebar-nav {
            list-style: none;
        }
        
        .sidebar-nav li {
            margin-bottom: 5px;
        }
        
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 25px;
            color: #4a5568;
            text-decoration: none;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: #f7fafc;
            color: #4f46e5;
            border-left-color: #4f46e5;
        }
        
        .sidebar-nav i {
            width: 20px;
            text-align: center;
        }
        
        .admin-content {
            padding: 30px;
            overflow-y: auto;
        }
        
        .content-section {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .section-header h2 {
            color: #4a5568;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(79, 70, 229, 0.2);
        }
        
        .grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }
        
        .invitation-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .invitation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .invitation-code {
            font-family: monospace;
            font-size: 18px;
            font-weight: 600;
            color: #4f46e5;
            background: white;
            padding: 5px 10px;
            border-radius: 5px;
            border: 1px solid #e2e8f0;
        }
        
        .invitation-info {
            color: #718096;
            font-size: 14px;
            margin-bottom: 10px;
            display: flex;
            gap: 15px;
        }
        
        .invitation-link {
            background: white;
            padding: 10px;
            border-radius: 6px;
            border: 1px dashed #cbd5e0;
            margin-top: 10px;
            font-size: 14px;
            word-break: break-all;
        }
        
        .btn-copy {
            background: #edf2f7;
            border: 1px solid #cbd5e0;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            margin-left: 10px;
            transition: all 0.3s;
        }
        
        .btn-copy:hover {
            background: #e2e8f0;
        }
        
        .btn-revoke {
            background: #fed7d7;
            color: #c53030;
            border: 1px solid #fc8181;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .btn-revoke:hover {
            background: #feb2b2;
        }
        
        .share-links {
            display: grid;
            gap: 15px;
        }
        
        .share-link-item {
            background: #f8fafc;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .share-link-item strong {
            color: #4a5568;
        }
        
        .share-link-buttons {
            display: flex;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .form-group input:focus, .form-group select:focus {
            outline: none;
            border-color: #4f46e5;
        }
        
        .table-container {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f7fafc;
            color: #4a5568;
            font-weight: 600;
            text-align: left;
            padding: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        
        tr:hover {
            background: #f8fafc;
        }
        
        .btn-delete {
            background: #fed7d7;
            color: #c53030;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-delete:hover {
            background: #feb2b2;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        }
        
        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: #4f46e5;
            margin-bottom: 10px;
        }
        
        .stat-label {
            color: #718096;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .success-message {
            background: #c6f6d5;
            color: #22543d;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        @media (max-width: 1024px) {
            .admin-container {
                grid-template-columns: 1fr;
            }
            
            .admin-sidebar {
                display: none;
            }
        }
        
        @media (max-width: 768px) {
            .grid-2 {
                grid-template-columns: 1fr;
            }
            
            .admin-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
        }
    </style>
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('Copied to clipboard!');
            });
        }
        
        function confirmDelete() {
            return confirm('Are you sure you want to delete this?');
        }
    </script>
</head>
<body>
    <div class="admin-header">
        <div class="header-left">
            <h1><i class="fas fa-user-shield"></i> Admin Panel</h1>
            <p><?= htmlspecialchars($section['section_name']) ?> • <?= $section_code ?></p>
        </div>
        <div class="header-right">
            <span>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></span>
            <a href="logout" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <div class="admin-container">
        <div class="admin-sidebar">
            <ul class="sidebar-nav">
                <li><a href="#dashboard" class="active">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a></li>
                <li><a href="#timetable">
                    <i class="fas fa-calendar-alt"></i> Timetable
                </a></li>
                <li><a href="#invitations">
                    <i class="fas fa-envelope"></i> Invitations
                </a></li>
                <li><a href="#sharing">
                    <i class="fas fa-share-alt"></i> Sharing
                </a></li>
                <li><a href="#students">
                    <i class="fas fa-users"></i> Students
                </a></li>
            </ul>
        </div>
        
        <div class="admin-content">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Dashboard -->
            <div id="dashboard" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-tachometer-alt"></i> Dashboard Overview</h2>
                </div>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?= $students->num_rows ?></div>
                        <div class="stat-label">Total Students</div>
                    </div>
                    <div class="stat-card">
                        <?php 
                        $active_invites = $invitations->num_rows;
                        $invitations->data_seek(0);
                        ?>
                        <div class="stat-number"><?= $active_invites ?></div>
                        <div class="stat-label">Active Invitations</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?= $timetable->num_rows ?></div>
                        <div class="stat-label">Timetable Entries</div>
                    </div>
                    <div class="stat-card">
                        <?php 
                        $shared_views_sql = "SELECT SUM(view_count) as total_views FROM shared_views WHERE section_code = ?";
                        $stmt = $conn->prepare($shared_views_sql);
                        $stmt->bind_param("s", $section_code);
                        $stmt->execute();
                        $views_result = $stmt->get_result()->fetch_assoc();
                        ?>
                        <div class="stat-number"><?= $views_result['total_views'] ?? 0 ?></div>
                        <div class="stat-label">Shared Views</div>
                    </div>
                </div>
            </div>
            
            <!-- Timetable Management -->
            <div id="timetable" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-calendar-alt"></i> Timetable Management</h2>
                    <button type="button" class="btn-primary" onclick="showAddForm()">
                        <i class="fas fa-plus"></i> Add Entry
                    </button>
                </div>
                
                <div id="addForm" style="display: none; margin-bottom: 30px;">
                    <form method="POST">
                        <div class="grid-2">
                            <div class="form-group">
                                <label>Day of Week</label>
                                <select name="day" required>
                                    <option value="">Select Day</option>
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                    <option value="Saturday">Saturday</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Period Number</label>
                                <input type="number" name="period" min="1" max="10" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Subject</label>
                                <input type="text" name="subject" required>
                            </div>
                            
                            <div class="form-group">
                                <label>Teacher</label>
                                <input type="text" name="teacher">
                            </div>
                            
                            <div class="form-group">
                                <label>Room</label>
                                <input type="text" name="room">
                            </div>
                            
                            <div class="form-group">
                                <label>Start Time</label>
                                <input type="time" name="start_time">
                            </div>
                            
                            <div class="form-group">
                                <label>End Time</label>
                                <input type="time" name="end_time">
                            </div>
                        </div>
                        
                        <button type="submit" name="add_timetable" class="btn-primary">
                            <i class="fas fa-save"></i> Save Entry
                        </button>
                    </form>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Period</th>
                                <th>Subject</th>
                                <th>Teacher</th>
                                <th>Room</th>
                                <th>Time</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $timetable->data_seek(0); ?>
                            <?php while ($entry = $timetable->fetch_assoc()): ?>
                            <tr>
                                <td><?= $entry['day_of_week'] ?></td>
                                <td><?= $entry['period'] ?></td>
                                <td><?= htmlspecialchars($entry['subject']) ?></td>
                                <td><?= htmlspecialchars($entry['teacher']) ?></td>
                                <td><?= htmlspecialchars($entry['room']) ?></td>
                                <td>
                                    <?php if ($entry['start_time']): ?>
                                        <?= date('h:i A', strtotime($entry['start_time'])) ?> - 
                                        <?= date('h:i A', strtotime($entry['end_time'])) ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirmDelete()">
                                        <input type="hidden" name="entry_id" value="<?= $entry['id'] ?>">
                                        <button type="submit" name="delete_timetable" class="btn-delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Invitation Management -->
            <div id="invitations" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-envelope"></i> Invitation Management</h2>
                    <button type="button" class="btn-primary" onclick="showInviteForm()">
                        <i class="fas fa-plus"></i> Create Invitation
                    </button>
                </div>
                
                <div id="inviteForm" style="display: none; margin-bottom: 30px;">
                    <form method="POST">
                        <div class="grid-2">
                            <div class="form-group">
                                <label>Max Uses (0 for unlimited)</label>
                                <input type="number" name="max_uses" min="0" value="1">
                            </div>
                            
                            <div class="form-group">
                                <label>Expiry Date (Optional)</label>
                                <input type="datetime-local" name="expires_at">
                            </div>
                        </div>
                        
                        <button type="submit" name="create_invitation" class="btn-primary">
                            <i class="fas fa-plus-circle"></i> Generate Invitation
                        </button>
                    </form>
                </div>
                
                <h3 style="margin-bottom: 20px; color: #4a5568;">Active Invitations</h3>
                <div class="invitations-list">
                    <?php $invitations->data_seek(0); ?>
                    <?php while ($invite = $invitations->fetch_assoc()): ?>
                    <div class="invitation-card">
                        <div class="invitation-header">
                            <span class="invitation-code"><?= $invite['invite_code'] ?></span>
                            <span style="color: <?= $invite['expires_at'] && strtotime($invite['expires_at']) < time() ? '#e53e3e' : '#38a169' ?>">
                                <?= $invite['expires_at'] ? date('M d, Y', strtotime($invite['expires_at'])) : 'No expiry' ?>
                            </span>
                        </div>
                        
                        <div class="invitation-info">
                            <span><i class="fas fa-users"></i> Uses: <?= $invite['used_count'] ?>/<?= $invite['max_uses'] == 0 ? '∞' : $invite['max_uses'] ?></span>
                            <span><i class="fas fa-calendar"></i> Created: <?= date('M d, Y', strtotime($invite['created_at'])) ?></span>
                        </div>
                        
                        <div class="invitation-link">
                            <strong>Invitation Link:</strong><br>
                            <?= BASE_URL ?>/invite/<?= $section_code ?>/<?= $invite['invite_code'] ?>
                            <button class="btn-copy" onclick="copyToClipboard('<?= BASE_URL ?>/invite/<?= $section_code ?>/<?= $invite['invite_code'] ?>')">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                        
                        <form method="POST" onsubmit="return confirm('Revoke this invitation?')">
                            <input type="hidden" name="invite_id" value="<?= $invite['id'] ?>">
                            <button type="submit" name="revoke_invitation" class="btn-revoke">
                                <i class="fas fa-ban"></i> Revoke Invitation
                            </button>
                        </form>
                    </div>
                    <?php endwhile; ?>
                    
                    <?php if ($invitations->num_rows == 0): ?>
                    <p style="color: #718096; text-align: center; padding: 30px;">No active invitations. Create one to invite students.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Sharing Section -->
            <div id="sharing" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-share-alt"></i> Sharing Links</h2>
                </div>
                
                <p style="margin-bottom: 25px; color: #718096;">
                    Share your section's timetable publicly. Anyone with the link can view.
                </p>
                
                <div class="share-links">
                    <?php 
                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    foreach ($days as $day): 
                        $share_url = BASE_URL . "/share/" . urlencode($section_code) . "/" . urlencode($day);
                    ?>
                    <div class="share-link-item">
                        <div>
                            <strong><?= $day ?> Timetable</strong><br>
                            <span style="font-size: 14px; color: #718096;">
                                <?= $share_url ?>
                            </span>
                        </div>
                        <div class="share-link-buttons">
                            <button class="btn-copy" onclick="copyToClipboard('<?= $share_url ?>')">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                            <a href="<?= $share_url ?>" target="_blank" class="btn-copy">
                                <i class="fas fa-external-link-alt"></i> View
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Students Management -->
            <div id="students" class="content-section">
                <div class="section-header">
                    <h2><i class="fas fa-users"></i> Students Management</h2>
                </div>
                
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Reg Number</th>
                                <th>Full Name</th>
                                <th>Joined Via</th>
                                <th>Registration Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $students->data_seek(0); ?>
                            <?php while ($student = $students->fetch_assoc()): ?>
                            <tr>
                                <td><?= htmlspecialchars($student['reg_number']) ?></td>
                                <td><?= htmlspecialchars($student['full_name']) ?></td>
                                <td>
                                    <?php if ($student['joined_via_invite']): ?>
                                        <span style="color: #38a169; font-size: 12px; background: #c6f6d5; padding: 3px 8px; border-radius: 4px;">
                                            <i class="fas fa-link"></i> Invitation
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #718096; font-size: 12px; background: #edf2f7; padding: 3px 8px; border-radius: 4px;">
                                            <i class="fas fa-user-plus"></i> Manual
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('M d, Y', strtotime($student['created_at'])) ?></td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showAddForm() {
            const form = document.getElementById('addForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
        
        function showInviteForm() {
            const form = document.getElementById('inviteForm');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
        
        // Smooth scrolling for sidebar links
        document.querySelectorAll('.sidebar-nav a').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href').substring(1);
                const targetSection = document.getElementById(targetId);
                
                if (targetSection) {
                    // Update active link
                    document.querySelectorAll('.sidebar-nav a').forEach(a => {
                        a.classList.remove('active');
                    });
                    this.classList.add('active');
                    
                    // Scroll to section
                    targetSection.scrollIntoView({ behavior: 'smooth' });
                }
            });
        });
    </script>
</body>
</html>