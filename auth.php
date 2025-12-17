<?php
require_once 'conn.php';

function login($reg_number, $password) {
    global $conn;
    
    // Sanitize input
    $reg_number = $conn->real_escape_string($reg_number);
    
    // Get user from database
    $sql = "SELECT * FROM users WHERE reg_number = '$reg_number'";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['reg_number'] = $user['reg_number'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['section_code'] = $user['section_code'];
            
            // Redirect based on role
            switch ($user['role']) {
                case 'student':
                    header('Location: ' . BASE_URL . '/user');
                    break;
                case 'admin':
                    header('Location: ' . BASE_URL . '/admin');
                    break;
                case 'superadmin':
                    header('Location: ' . BASE_URL . '/superadmin');
                    break;
                default:
                    header('Location: ' . BASE_URL);
            }
            exit;
        }
    }
    
    return false;
}

function registerViaInvite($reg_number, $password, $full_name, $invite_code, $section_code) {
    global $conn;
    
    // Validate invitation
    $invite = validateInvitation($invite_code, $section_code);
    if (!$invite) {
        return ['success' => false, 'message' => 'Invalid or expired invitation'];
    }
    
    // Check if reg number already exists
    $checkSql = "SELECT id FROM users WHERE reg_number = ?";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param("s", $reg_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        return ['success' => false, 'message' => 'Registration number already exists'];
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Insert new user
    $insertSql = "INSERT INTO users (reg_number, password, full_name, role, section_code, joined_via_invite, invited_by) 
                  VALUES (?, ?, ?, 'student', ?, ?, ?)";
    $stmt = $conn->prepare($insertSql);
    $stmt->bind_param("sssssi", $reg_number, $hashedPassword, $full_name, $section_code, $invite_code, $invite['created_by']);
    
    if ($stmt->execute()) {
        // Update invitation usage
        updateInvitationUsage($invite_code);
        
        return ['success' => true, 'message' => 'Registration successful'];
    } else {
        return ['success' => false, 'message' => 'Registration failed'];
    }
}

function validateInvitation($invite_code, $section_code) {
    global $conn;
    
    $sql = "SELECT * FROM invitations 
            WHERE invite_code = ? 
            AND section_code = ?
            AND is_active = 1
            AND (expires_at IS NULL OR expires_at > NOW())
            AND (max_uses = 0 OR used_count < max_uses)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $invite_code, $section_code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

function updateInvitationUsage($invite_code) {
    global $conn;
    
    $sql = "UPDATE invitations SET used_count = used_count + 1 WHERE invite_code = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $invite_code);
    $stmt->execute();
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $reg_number = $_POST['reg_number'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (login($reg_number, $password)) {
        // Success - already redirected
    } else {
        $_SESSION['error'] = 'Invalid credentials';
        header('Location: ' . BASE_URL);
        exit;
    }
}
?>