<?php
// Database configuration
define('DB_HOST', 'mysql.railway.internal');
define('DB_USER', 'root');
define('DB_PASS', 'iyHmlSOlnPjrIZBJRyjrqwqSDAzmYUwX');
define('DB_NAME', 'railway');

// Application configuration
define('SITE_NAME', 'Patient Loyalty Rewards System');
define('SITE_URL', 'mysql://root:iyHmlSOlnPjrIZBJRyjrqwqSDAzmYUwX@mysql.railway.internal:3306/railway');
define('UPLOAD_DIR', __DIR__ . '/../uploads');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['csv']);

// Server configuration
define('PORT', 3306); // Development server port

// Session configuration
define('SESSION_NAME', 'patient_loyalty_session');
define('SESSION_LIFETIME', 3600); // 1 hour

// Points configuration
define('DEFAULT_POINTS_RATE', 100); // 1 point per 100 KES

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Create uploads directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
} 