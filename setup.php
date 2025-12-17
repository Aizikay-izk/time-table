<?php
/**
 * Heroku Deployment Setup Script
 * Run automatically on deploy
 */
require_once 'conn.php';

function runSetup() {
    global $conn;
    
    echo "<h2>🎯 Academic Timetable System Setup</h2>";
    echo "<p>Checking and configuring database...</p>";
    
    // Check tables
    $tables = ['users', 'sections', 'timetable', 'invitations', 'shared_views'];
    $created = 0;
    
    foreach ($tables as $table) {
        $check = $conn->query("SHOW TABLES LIKE '$table'");
        if ($check->num_rows == 0) {
            echo "<p style='color: #e53e3e;'>❌ Table '$table' not found</p>";
        } else {
            echo "<p style='color: #38a169;'>✅ Table '$table' exists</p>";
            $created++;
        }
    }
    
    // Check superadmin
    $result = $conn->query("SELECT * FROM users WHERE role = 'superadmin'");
    if ($result->num_rows == 0) {
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (reg_number, password, full_name, role) 
                     VALUES ('superadmin', '$hashedPassword', 'System Administrator', 'superadmin')");
        echo "<p style='color: #38a169;'>✅ Superadmin created (superadmin / admin123)</p>";
    } else {
        echo "<p style='color: #38a169;'>✅ Superadmin exists</p>";
    }
    
    // Display database info
    echo "<hr>";
    echo "<h3>Database Information:</h3>";
    echo "<ul>";
    echo "<li><strong>Host:</strong> " . $conn->host_info . "</li>";
    echo "<li><strong>Database:</strong> " . $conn->select_db . "</li>";
    echo "<li><strong>Tables created:</strong> $created/5</li>";
    echo "<li><strong>Base URL:</strong> " . BASE_URL . "</li>";
    echo "</ul>";
    
    echo "<hr>";
    echo "<div style='background: #c6f6d5; padding: 20px; border-radius: 10px; margin: 20px 0;'>";
    echo "<h3>🚀 Setup Complete!</h3>";
    echo "<p>Your Academic Timetable System is ready to use.</p>";
    echo "<p><strong>Login URL:</strong> <a href='" . BASE_URL . "' target='_blank'>" . BASE_URL . "</a></p>";
    echo "<p><strong>Default Superadmin:</strong> superadmin / admin123</p>";
    echo "<p><em>Remember to change the default password!</em></p>";
    echo "</div>";
    
    echo "<hr>";
    echo "<h3>📱 Quick Start:</h3>";
    echo "<ol>";
    echo "<li>Login as superadmin</li>";
    echo "<li>Create your first section</li>";
    echo "<li>Add admin user for the section</li>";
    echo "<li>Add timetable entries</li>";
    echo "<li>Share invitation links with students</li>";
    echo "</ol>";
}

// Run setup if accessed directly
if (php_sapi_name() !== 'cli') {
    echo "<!DOCTYPE html>
    <html>
    <head>
        <title>System Setup</title>
        <style>
            body { font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px; }
            .success { color: #38a169; }
            .error { color: #e53e3e; }
            .info { background: #ebf8ff; padding: 15px; border-radius: 5px; margin: 10px 0; }
        </style>
    </head>
    <body>";
    
    runSetup();
    
    echo "</body></html>";
} else {
    // CLI mode for Heroku
    runSetup();
}
?>
