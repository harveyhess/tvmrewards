<?php
require_once 'config/config.php';

try {
    // Create database if it doesn't exist
    $pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $pdo->exec("CREATE DATABASE IF NOT EXISTS " . DB_NAME);
    $pdo->exec("USE " . DB_NAME);

    // Create tables
    $tables = [
        "CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) UNIQUE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )",

        "CREATE TABLE IF NOT EXISTS tiers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50) NOT NULL,
            min_points INT NOT NULL,
            max_points INT,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",
        
        "CREATE TABLE IF NOT EXISTS patients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            PatientID VARCHAR(50) UNIQUE NOT NULL, /*PatientID CHANGED ,Amount,DATE,ReffNo(transaction id)*/


            name VARCHAR(100) NOT NULL,/*PName*/
            phone_number VARCHAR(20) NOT NULL,
            total_points INT DEFAULT 0,
            tier_id INT,
            qr_token VARCHAR(255) UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (tier_id) REFERENCES tiers(id)
        )",
        
        "CREATE TABLE IF NOT EXISTS transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            PatientID INT NOT NULL,
            amount_paid DECIMAL(10,2) NOT NULL,
            points_earned INT NOT NULL,
            transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (PatientID) REFERENCES patients(id)
        )",
        
        "CREATE TABLE IF NOT EXISTS points_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            points_rate INT NOT NULL DEFAULT 100,
            updated_by INT NOT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (updated_by) REFERENCES admins(id)
        )",

        "CREATE TABLE IF NOT EXISTS login_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            PatientID INT NOT NULL,
            patient_name VARCHAR(100) NOT NULL,
            phone_number VARCHAR(20) NOT NULL,
            login_method ENUM('regular', 'qr_code') NOT NULL DEFAULT 'regular',
            login_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (PatientID) REFERENCES patients(id)
        )",

        "CREATE TABLE IF NOT EXISTS rewards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            description TEXT,
            points_cost INT NOT NULL,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",

        "CREATE TABLE IF NOT EXISTS points_ledger (
            id INT AUTO_INCREMENT PRIMARY KEY,
            PatientID INT NOT NULL,
            points INT NOT NULL,
            type ENUM('earn', 'redeem') NOT NULL,
            reference_id INT,
            reference_type ENUM('transaction', 'redemption') NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (PatientID) REFERENCES patients(id)
        )",

        "CREATE TABLE IF NOT EXISTS redemptions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            PatientID INT NOT NULL,
            reward_id INT NOT NULL,
            points_spent INT NOT NULL,
            status ENUM('pending', 'completed', 'cancelled') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL,
            FOREIGN KEY (PatientID) REFERENCES patients(id),
            FOREIGN KEY (reward_id) REFERENCES rewards(id)
        )",

        "CREATE TABLE IF NOT EXISTS transaction_sync (
            id INT AUTO_INCREMENT PRIMARY KEY,
            file_name VARCHAR(255) NOT NULL,
            last_processed_line INT DEFAULT 0,
            last_sync_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('pending', 'completed', 'failed') DEFAULT 'pending',
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )",

        "CREATE TABLE IF NOT EXISTS tier_downgrade_tracking (
            id INT AUTO_INCREMENT PRIMARY KEY,
            PatientID INT NOT NULL,
            current_tier_id INT NOT NULL,
            downgrade_reason ENUM('inactivity', 'redemption_limit') NOT NULL,
            downgrade_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            is_pending BOOLEAN DEFAULT TRUE,
            FOREIGN KEY (PatientID) REFERENCES patients(id),
            FOREIGN KEY (current_tier_id) REFERENCES tiers(id)
        )",

        "CREATE TABLE IF NOT EXISTS transaction_csv_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            url VARCHAR(255) NOT NULL,
            last_fetched TIMESTAMP NULL,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )"
    ];

    foreach ($tables as $sql) {
        $pdo->exec($sql);
    }

    // Create default admin account
    $defaultAdmin = [
        'username' => 'admin',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'email' => 'admin@hospital.com'
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO admins (username, password, email) VALUES (?, ?, ?)");
    $stmt->execute([$defaultAdmin['username'], $defaultAdmin['password'], $defaultAdmin['email']]);

    // Insert default points settings
    $stmt = $pdo->prepare("INSERT IGNORE INTO points_settings (points_rate, updated_by) VALUES (?, 1)");
    $stmt->execute([DEFAULT_POINTS_RATE]);

    // Insert default tiers
    $defaultTiers = [
        ['Silver', 0, 500, 'Basic tier with standard benefits'],
        ['Gold', 501, 1000, 'Premium tier with enhanced benefits'],
        ['Platinum', 1001, null, 'Elite tier with exclusive benefits']
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO tiers (name, min_points, max_points, description) VALUES (?, ?, ?, ?)");
    foreach ($defaultTiers as $tier) {
        $stmt->execute($tier);
    }

    // Insert default rewards
    $defaultRewards = [
        ['Free Consultation', 'One free consultation with any doctor', 100],
        ['10% Discount', '10% discount on your next visit', 200],
        ['Free Lab Test', 'One free basic lab test', 300],
        ['20% Discount', '20% discount on your next visit', 400],
        ['Free Dental Checkup', 'One free dental checkup', 500],
        ['Free Eye Test', 'One free eye test', 600],
        ['30% Discount', '30% discount on your next visit', 700],
        ['Free Health Package', 'Complete health checkup package', 1000]
    ];

    $stmt = $pdo->prepare("INSERT IGNORE INTO rewards (name, description, points_cost, is_active) VALUES (?, ?, ?, 1)");
    foreach ($defaultRewards as $reward) {
        $stmt->execute($reward);
    }

    echo "Installation completed successfully!\n";
    echo "Default admin credentials:\n";
    echo "Username: admin\n";
    echo "Password: admin123\n";
    echo "Please change these credentials after first login.\n";

} catch (PDOException $e) {
    die("Installation failed: " . $e->getMessage());
} 