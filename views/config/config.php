<?php
// config/config.php

// Error reporting configuration
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Application constants
define('APP_NAME', 'Sistema de Exámenes');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'http://localhost/plataforma-rumbo-al-saber');

// Path constants
define('ROOT_PATH', dirname(__DIR__));
define('ASSETS_PATH', ROOT_PATH . '/assets');
define('UPLOADS_PATH', ASSETS_PATH . '/imgs/uploads');
define('LOGS_PATH', ROOT_PATH . '/logs');

// URL constants
define('BASE_URL', APP_URL);
define('ASSETS_URL', BASE_URL . '/assets');
define('UPLOADS_URL', ASSETS_URL . '/imgs/uploads');

// Database configuration (load from database.php)
require_once __DIR__ . '/database.php';

// User roles
define('ROLE_ADMIN', 'admin');
define('ROLE_PROFESOR', 'profesor');
define('ROLE_ESTUDIANTE', 'estudiante');

// Exam settings
define('MIN_EXAM_DURATION', 5); // minutes
define('MAX_EXAM_DURATION', 180); // minutes
define('MAX_QUESTIONS_PER_EXAM', 50);
define('MIN_QUESTIONS_PER_EXAM', 1);

// File upload settings
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// Pagination
define('RECORDS_PER_PAGE', 10);

// Security settings
define('SESSION_TIMEOUT', 3600); // 1 hour in seconds
define('PASSWORD_MIN_LENGTH', 6);

// Timezone
date_default_timezone_set('America/Mexico_City');

// Helper functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

function hasRole($role) {
    return getUserRole() === $role;
}

function isAdmin() {
    return hasRole(ROLE_ADMIN);
}

function isProfesor() {
    return hasRole(ROLE_PROFESOR);
}

function isEstudiante() {
    return hasRole(ROLE_ESTUDIANTE);
}

function redirectTo($url) {
    header("Location: " . $url);
    exit;
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirectTo(BASE_URL . '/login.php');
    }
}

function requireRole($role) {
    requireLogin();
    if (!hasRole($role)) {
        redirectTo(BASE_URL . '/views/dashboard.php');
    }
}

function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function formatDate($date, $format = 'Y-m-d H:i:s') {
    return date($format, strtotime($date));
}

function createAlert($type, $message) {
    return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">' 
           . $message . 
           '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

// Check session timeout
function checkSessionTimeout() {
    if (isLoggedIn()) {
        $lastActivity = $_SESSION['last_activity'] ?? time();
        if ((time() - $lastActivity) > SESSION_TIMEOUT) {
            session_destroy();
            redirectTo(BASE_URL . '/login.php?timeout=1');
        }
        $_SESSION['last_activity'] = time();
    }
}

// Auto-check session timeout
checkSessionTimeout();

// Create necessary directories
$directories = [LOGS_PATH, UPLOADS_PATH];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
}
?>