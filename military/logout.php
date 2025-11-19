
<?php
// logout.php
require_once 'config.php';

// Clear all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

// Redirect to login page
header('Location: ' . BASE_URL . '/login.php');
exit;
?>