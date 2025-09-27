<?php

// dashboard.php
// Este archivo actúa como punto de entrada y redirige al usuario al dashboard correspondiente

// Start session
session_start();

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get user role
$userRole = $_SESSION['user_role'];

// Redirect based on role
if ($userRole === 'estudiante') {
    header("Location: dashboard_estudiante.php");
} elseif ($userRole === 'admin' || $userRole === 'profesor') {
    header("Location: dashboard_admin_profesor.php");
} else {
    // Default or error case
    header("Location: dashboard_estudiante.php");
}
exit;
