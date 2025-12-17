<?php
session_start();
require_once 'conn.php';

// Check authentication and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'superadmin') {
    header('Location: ' . BASE_URL);
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['create_section'])) {
        $section_code = $_POST['section_code'];
        $section_name = $_POST['section_name'];
        $admin_reg = $_POST['admin_reg'];
        $admin_name = $_POST['admin_name'];
        $admin_password = $_POST['admin_password'];
        
        // Create admin user
        $hashed_password = password_hash($admin_password, PASSWORD_DEFAULT);
        $sql = "INSERT INTO users (reg_number, password, full_name, role) VALUES (?, ?, ?, 'admin')";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $admin_reg, $hashed_password, $admin_name);
        
        if ($stmt->execute()) {
            $admin_id = $stmt->insert_id;
            
            // Create section
            $sql = "INSERT INTO sections (section_code, section_name, created_by) VALUES (?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ssi", $section_code, $section_name, $admin_id);
            $stmt->execute();
            
            // Update admin's section
            $sql = "UPDATE users SET section_code = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $section_code, $admin_id);
            $stmt->execute();
            
            $_SESSION['success'] = "Section created successfully!";
        }
    }
    
    if (isset($_POST['delete_section'])) {
        $section_id = intval($_POST['section_id']);
        $sql = "DELETE FROM sections WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $section_id);
        $stmt->execute();
        
        $_SESSION['success'] = "Section deleted!";
    }
    
    header('Location: ' . BASE_URL . '/superadmin');
    exit;
}

// Get all sections with admin info
$sections_sql = "SELECT s.*, u.reg_number as admin_reg, u.full_name as admin_name 
                 FROM sections s 
                 JOIN users u ON s.created_by = u.id 
                 ORDER BY s.created_at DESC";
$sections = $conn->query($sections_sql);

// Get all users
$users_sql = "SELECT * FROM users ORDER BY role, created_at DESC";
$users = $conn->query($users_sql);

// Get statistics
$stats_sql = "SELECT 
                (SELECT COUNT(*) FROM users) as total_users,
                (SELECT COUNT(*) FROM users WHERE role = 'admin') as total_admins,
                (SELECT COUNT(*) FROM users WHERE role = 'student') as total_students,
                (SELECT COUNT(*) FROM sections) as total_sections";
$stats = $conn->query($stats_sql)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Panel</title>
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
        
        .superadmin-header {
            background: linear-gradient(135deg, #0f172a, #1e293b);
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
            background: rgba(255,255,255,0.1);
            color: white;
            border: 1px solid rgba(255,255,255,0.2);
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }
        
        .btn-logout:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .superadmin-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-top: 4px solid;
        }
        
        .stat-card.users { border-color: #4f46e5; }
        .stat-card.admins { border-color: #7c3aed; }
        .stat-card.students { border-color: #0ea5e9; }
        .stat-card.sections { border-color: #10b981; }
        
        .stat-number {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .stat-card.users .stat-number { color: #4f46e5; }
        .stat-card.admins .stat-number { color: #7c3aed; }
        .stat-card.students .stat-number { color: #0ea5e9; }
        .stat-card.sections .stat-number { color: #10b981; }
        
        .stat-label {
            color: #64748b;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
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
            color: #1e293b;
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
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #475569;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #4f46e5;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: #f8fafc;
            color: #475569;
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
        
        .role-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .role-superadmin { background: #fee2e2; color: #dc2626; }
        .role-admin { background: #dbeafe; color: #2563eb; }
        .role-student { background: #dcfce7; color: #16a34a; }
        
        .btn-delete {
            background: #fee2e2;
            color: #dc2626;
            border: 1px solid #fecaca;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .btn-delete:hover {
            background: #fecaca;
        }
        
        .success-message {
            background: #dcfce7;
            color: #166534;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-code {
            font-family: monospace;
            background: #f1f5f9;
            padding: 3px 8px;
            border-radius: 4px;
            color: #475569;
        }
        
        @media (max-width: 768px) {
            .superadmin-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .section-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
            
            .btn-primary {
                width: 100%;
            }
        }
    </style>
    <script>
        function confirmDelete() {
            return confirm('Are you sure you want to delete this? This action cannot be undone.');
        }
    </script>
</head>
<body>
    <div class="superadmin-header">
        <div class="header-left">
            <h1><i class="fas fa-crown"></i> Super Admin Panel</h1>
            <p>System Administration & Management</p>
        </div>
        <div class="header-right">
            <span>Welcome, <?= htmlspecialchars($_SESSION['full_name']) ?></span>
            <a href="logout" class="btn-logout">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>
    
    <div class="superadmin-container">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message">
                <i class="fas fa-check-circle"></i>
                <?= $_SESSION['success']; unset($_SESSION['success']); ?>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid">
            <div class="stat-card users">
                <div class="stat-number"><?= $stats['total_users'] ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card admins">
                <div class="stat-number"><?= $stats['total_admins'] ?></div>
                <div class="stat-label">Admins</div>
            </div>
            <div class="stat-card students">
                <div class="stat-number"><?= $stats['total_students'] ?></div>
                <div class="stat-label">Students</div>
            </div>
            <div class="stat-card sections">
                <div class="stat-number"><?= $stats['total_sections'] ?></div>
                <div class="stat-label">Sections</div>
            </div>
        </div>
        
        <!-- Create New Section -->
        <div class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-plus-circle"></i> Create New Section</h2>
            </div>
            
            <form method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Section Code *</label>
                        <input type="text" name="section_code" 
                               placeholder="e.g., ENG2024" required
                               pattern="[A-Za-z0-9-]{3,20}">
                    </div>
                    
                    <div class="form-group">
                        <label>Section Name *</label>
                        <input type="text" name="section_name" 
                               placeholder="e.g., Engineering 2024" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Admin Registration Number *</label>
                        <input type="text" name="admin_reg" 
                               placeholder="e.g., ADMIN001" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Admin Full Name *</label>
                        <input type="text" name="admin_name" 
                               placeholder="Admin's full name" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Admin Password *</label>
                        <input type="password" name="admin_password" 
                               placeholder="At least 6 characters" 
                               minlength="6" required>
                    </div>
                </div>
                
                <button type="submit" name="create_section" class="btn-primary">
                    <i class="fas fa-save"></i> Create Section & Admin
                </button>
            </form>
        </div>
        
        <!-- Sections Management -->
        <div class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-sitemap"></i> All Sections</h2>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Section Code</th>
                            <th>Section Name</th>
                            <th>Admin</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($section = $sections->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <span class="section-code"><?= $section['section_code'] ?></span>
                            </td>
                            <td><?= htmlspecialchars($section['section_name']) ?></td>
                            <td>
                                <?= htmlspecialchars($section['admin_name']) ?><br>
                                <small style="color: #64748b;"><?= $section['admin_reg'] ?></small>
                            </td>
                            <td><?= date('M d, Y', strtotime($section['created_at'])) ?></td>
                            <td>
                                <form method="POST" onsubmit="return confirmDelete()">
                                    <input type="hidden" name="section_id" value="<?= $section['id'] ?>">
                                    <button type="submit" name="delete_section" class="btn-delete">
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
        
        <!-- Users Management -->
        <div class="content-section">
            <div class="section-header">
                <h2><i class="fas fa-users"></i> All Users</h2>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Reg Number</th>
                            <th>Full Name</th>
                            <th>Role</th>
                            <th>Section</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($user = $users->fetch_assoc()): ?>
                        <tr>
                            <td><?= htmlspecialchars($user['reg_number']) ?></td>
                            <td><?= htmlspecialchars($user['full_name']) ?></td>
                            <td>
                                <span class="role-badge role-<?= $user['role'] ?>">
                                    <?= ucfirst($user['role']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($user['section_code']): ?>
                                    <span class="section-code"><?= $user['section_code'] ?></span>
                                <?php else: ?>
                                    <span style="color: #94a3b8;">-</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('M d, Y', strtotime($user['created_at'])) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>