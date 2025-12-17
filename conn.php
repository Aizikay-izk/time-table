<?php
// Heroku Database Configuration
function getDatabaseConfig() {
    // Check for Heroku ClearDB
    if (getenv('CLEARDB_DATABASE_URL')) {
        $url = parse_url(getenv('CLEARDB_DATABASE_URL'));
        return [
            'host' => $url['host'],
            'user' => $url['user'],
            'password' => $url['pass'],
            'database' => substr($url['path'], 1)
        ];
    }
    
    // Check for JawsDB MySQL
    if (getenv('JAWSDB_URL')) {
        $url = parse_url(getenv('JAWSDB_URL'));
        return [
            'host' => $url['host'],
            'user' => $url['user'],
            'password' => $url['pass'],
            'database' => substr($url['path'], 1)
        ];
    }
    
    // Fallback to environment variables
    return [
        'host' => getenv('DB_HOST') ?: 'localhost',
        'user' => getenv('DB_USER') ?: 'root',
        'password' => getenv('DB_PASSWORD') ?: '',
        'database' => getenv('DB_NAME') ?: 'timetable_system'
    ];
}

// Get configuration
$config = getDatabaseConfig();

// Database connection
$conn = new mysqli(
    $config['host'],
    $config['user'],
    $config['password'],
    $config['database']
);

// Check connection
if ($conn->connect_error) {
    // Try to create database if it doesn't exist
    if ($conn->connect_errno == 1049) {
        $temp_conn = new mysqli($config['host'], $config['user'], $config['password']);
        if (!$temp_conn->connect_error) {
            $temp_conn->query("CREATE DATABASE IF NOT EXISTS " . $config['database']);
            $temp_conn->select_db($config['database']);
            $conn = $temp_conn;
        }
    } else {
        die("Connection failed: " . $conn->connect_error);
    }
}

// Set charset
$conn->set_charset("utf8mb4");

// Set BASE_URL for the application
if (!defined('BASE_URL')) {
    if (getenv('BASE_URL')) {
        define('BASE_URL', getenv('BASE_URL'));
    } elseif (isset($_SERVER['HTTP_HOST'])) {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        define('BASE_URL', $protocol . '://' . $_SERVER['HTTP_HOST']);
    } else {
        define('BASE_URL', 'https://your-app-name.herokuapp.com');
    }
}

// Database setup function
function setupDatabase($conn) {
    // Create tables if they don't exist
    $sql = array();
    
    // Users table
    $sql[] = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reg_number VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100),
        role ENUM('student', 'admin', 'superadmin') DEFAULT 'student',
        section_code VARCHAR(20),
        invited_by INT NULL,
        joined_via_invite VARCHAR(20) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_section (section_code),
        INDEX idx_invited_by (invited_by)
    )";
    
    // Sections table
    $sql[] = "CREATE TABLE IF NOT EXISTS sections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section_code VARCHAR(20) UNIQUE NOT NULL,
        section_name VARCHAR(100) NOT NULL,
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    // Timetable table
    $sql[] = "CREATE TABLE IF NOT EXISTS timetable (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section_code VARCHAR(20) NOT NULL,
        day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') NOT NULL,
        period INT NOT NULL,
        subject VARCHAR(100) NOT NULL,
        teacher VARCHAR(100),
        room VARCHAR(50),
        start_time TIME,
        end_time TIME,
        INDEX idx_section_day (section_code, day_of_week)
    )";
    
    // Invitations table
    $sql[] = "CREATE TABLE IF NOT EXISTS invitations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section_code VARCHAR(20) NOT NULL,
        invite_code VARCHAR(20) UNIQUE NOT NULL,
        created_by INT NOT NULL,
        max_uses INT DEFAULT 1,
        used_count INT DEFAULT 0,
        expires_at DATETIME,
        is_active BOOLEAN DEFAULT TRUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_section_code (section_code),
        INDEX idx_created_by (created_by)
    )";
    
    // Shared views tracking
    $sql[] = "CREATE TABLE IF NOT EXISTS shared_views (
        id INT AUTO_INCREMENT PRIMARY KEY,
        section_code VARCHAR(20) NOT NULL,
        day VARCHAR(20) NOT NULL,
        view_count INT DEFAULT 0,
        last_viewed TIMESTAMP NULL,
        INDEX idx_section_day (section_code, day)
    )";
    
    // Execute all queries
    foreach ($sql as $query) {
        if (!$conn->query($query)) {
            error_log("Database setup error: " . $conn->error);
        }
    }
    
    // Create default superadmin if not exists
    $checkSuperadmin = "SELECT id FROM users WHERE role = 'superadmin' LIMIT 1";
    $result = $conn->query($checkSuperadmin);
    
    if ($result->num_rows == 0) {
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $insertSuperadmin = "INSERT INTO users (reg_number, password, full_name, role) 
                           VALUES ('superadmin', '$hashedPassword', 'System Administrator', 'superadmin')";
        if (!$conn->query($insertSuperadmin)) {
            error_log("Failed to create superadmin: " . $conn->error);
        }
    }
}

// Run setup
setupDatabase($conn);
?>
