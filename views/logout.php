<?php
// logout.php
// Start session
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to logout confirmation page
header("Location: logout_success.php");
exit;
?>