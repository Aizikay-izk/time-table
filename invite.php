<?php
session_start();
require_once 'conn.php';

// Get parameters
$section_code = $_GET['section'] ?? '';
$invite_code = $_GET['code'] ?? '';

// Validate invitation
function validateInvitation($section_code, $invite_code) {
    global $conn;
    
    $sql = "SELECT i.*, s.section_name, u.full_name as admin_name 
            FROM invitations i
            JOIN sections s ON i.section_code = s.section_code
            JOIN users u ON i.created_by = u.id
            WHERE i.invite_code = ? 
            AND i.section_code = ?
            AND i.is_active = 1
            AND (i.expires_at IS NULL OR i.expires_at > NOW())
            AND (i.max_uses = 0 OR i.used_count < i.max_uses)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $invite_code, $section_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

$invitation = validateInvitation($section_code, $invite_code);

if (!$invitation) {
    die("Invalid or expired invitation link. Please contact your administrator.");
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $reg_number = trim($_POST['reg_number']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    
    // Validate input
    $errors = [];
    
    if (empty($reg_number)) {
        $errors[] = "Registration number is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Check if reg number already exists
    $check_sql = "SELECT id FROM users WHERE reg_number = ?";
    $stmt = $conn->prepare($check_sql);
    $stmt->bind_param("s", $reg_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $errors[] = "This registration number is already taken";
    }
    
    // If no errors, create account
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $insert_sql = "INSERT INTO users (reg_number, password, full_name, role, section_code, invited_by, joined_via_invite) 
                      VALUES (?, ?, ?, 'student', ?, ?, ?)";
        $stmt = $conn->prepare($insert_sql);
        $stmt->bind_param("ssssis", $reg_number, $hashed_password, $full_name, $section_code, $invitation['created_by'], $invite_code);
        
        if ($stmt->execute()) {
            // Update invitation usage
            $update_sql = "UPDATE invitations SET used_count = used_count + 1 WHERE id = ?";
            $stmt = $conn->prepare($update_sql);
            $stmt->bind_param("i", $invitation['id']);
            $stmt->execute();
            
            $_SESSION['success'] = "Registration successful! You can now login with your credentials.";
            header('Location: ' . BASE_URL);
            exit;
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Join <?= htmlspecialchars($invitation['section_name']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .invitation-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            width: 100%;
            max-width: 480px;
        }
        
        .invitation-header {
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        
        .invitation-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }
        
        .invitation-header p {
            opacity: 0.9;
            font-size: 16px;
            margin-bottom: 5px;
        }
        
        .invitation-body {
            padding: 40px 30px;
        }
        
        .invitation-info {
            background: #f8fafc;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            border-left: 4px solid #4f46e5;
        }
        
        .info-item {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #4a5568;
        }
        
        .info-item i {
            color: #4f46e5;
            width: 20px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #4f46e5;
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }
        
        .btn-join {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #4f46e5, #7c3aed);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .btn-join:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(79, 70, 229, 0.3);
        }
        
        .error-message {
            background: #fed7d7;
            color: #c53030;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            list-style: none;
        }
        
        .error-message li {
            margin: 5px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .password-strength {
            margin-top: 5px;
            font-size: 12px;
            color: #718096;
        }
        
        .invitation-footer {
            text-align: center;
            padding: 20px;
            border-top: 1px solid #e2e8f0;
            color: #718096;
            font-size: 14px;
        }
        
        .invitation-footer a {
            color: #4f46e5;
            text-decoration: none;
            font-weight: 600;
        }
        
        .invitation-footer a:hover {
            text-decoration: underline;
        }
        
        @media (max-width: 480px) {
            .invitation-container {
                border-radius: 15px;
            }
            
            .invitation-header {
                padding: 30px 20px;
            }
            
            .invitation-body {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="invitation-container">
        <div class="invitation-header">
            <h1><i class="fas fa-user-plus"></i> Join Class</h1>
            <p>You've been invited to join</p>
            <p style="font-size: 20px; font-weight: 600;"><?= htmlspecialchars($invitation['section_name']) ?></p>
        </div>
        
        <div class="invitation-body">
            <div class="invitation-info">
                <div class="info-item">
                    <i class="fas fa-user-tie"></i>
                    <span>Invited by: <?= htmlspecialchars($invitation['admin_name']) ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Invitation expires: <?= date('M d, Y', strtotime($invitation['expires_at'])) ?></span>
                </div>
                <div class="info-item">
                    <i class="fas fa-users"></i>
                    <span>Spots left: <?= $invitation['max_uses'] == 0 ? 'Unlimited' : ($invitation['max_uses'] - $invitation['used_count']) ?></span>
                </div>
            </div>
            
            <?php if (!empty($errors)): ?>
                <ul class="error-message">
                    <?php foreach ($errors as $error): ?>
                        <li><i class="fas fa-exclamation-circle"></i> <?= $error ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Choose Your Registration Number *</label>
                    <input type="text" name="reg_number" 
                           placeholder="e.g., ENG2024001" 
                           pattern="[A-Za-z0-9]{6,20}"
                           title="6-20 alphanumeric characters"
                           required>
                </div>
                
                <div class="form-group">
                    <label>Your Full Name (Optional)</label>
                    <input type="text" name="full_name" 
                           placeholder="Enter your full name">
                </div>
                
                <div class="form-group">
                    <label>Set Your Password *</label>
                    <input type="password" name="password" 
                           placeholder="At least 6 characters"
                           minlength="6"
                           required
                           oninput="checkPasswordStrength(this.value)">
                    <div class="password-strength" id="passwordStrength"></div>
                </div>
                
                <div class="form-group">
                    <label>Confirm Password *</label>
                    <input type="password" name="confirm_password" 
                           placeholder="Re-enter your password"
                           minlength="6"
                           required>
                </div>
                
                <button type="submit" name="register" class="btn-join">
                    <i class="fas fa-user-check"></i> Join Section
                </button>
            </form>
        </div>
        
        <div class="invitation-footer">
            <p>Already have an account? <a href="<?= BASE_URL ?>">Login here</a></p>
            <p><small>By joining, you agree to follow college rules and regulations</small></p>
        </div>
    </div>
    
    <script>
        function checkPasswordStrength(password) {
            const strengthDiv = document.getElementById('passwordStrength');
            let strength = 0;
            let message = '';
            
            if (password.length >= 8) strength++;
            if (/[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                    message = 'Weak';
                    strengthDiv.style.color = '#e53e3e';
                    break;
                case 2:
                    message = 'Fair';
                    strengthDiv.style.color = '#d69e2e';
                    break;
                case 3:
                    message = 'Good';
                    strengthDiv.style.color = '#38a169';
                    break;
                case 4:
                    message = 'Strong';
                    strengthDiv.style.color = '#276749';
                    break;
            }
            
            strengthDiv.textContent = message ? `Password strength: ${message}` : '';
        }
    </script>
</body>
</html>