# Academic Timetable System with URL Sharing & Invitations

A complete web-based timetable management system with public sharing and invitation features.

## 🚀 Features

- **Public Timetable Sharing**: Share specific day's timetable via URL
- **Invitation System**: Students can self-register via invitation links
- **Role-based Access**: Student, Admin, Super Admin roles
- **Mobile Responsive**: Works on all devices
- **QR Code Support**: Generate QR codes for quick access
- **Clean URLs**: User-friendly web addresses

## 📋 Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache recommended)
- Modern web browser

## 🛠️ Installation

### 1. Clone/Download Files
Download all files to your web server directory.

### 2. Database Setup
```sql
-- Create database
CREATE DATABASE timetable_system;
USE timetable_system;

-- The system will auto-create tables on first access
-- Default superadmin: reg_number = 'superadmin', password = 'admin123'