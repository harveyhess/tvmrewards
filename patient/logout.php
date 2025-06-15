<?php
require_once __DIR__ . '/../config/config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
session_start();
}

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: login.php');
exit; 