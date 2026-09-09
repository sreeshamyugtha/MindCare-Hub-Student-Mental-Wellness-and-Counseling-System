<?php
// logout.php
require_once 'includes/auth.php';

// Clear session variables
$_SESSION = [];

// Destroy session cookie if set
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Restart temporary session to issue a flash message
session_start();
set_flash('success', 'You have been successfully logged out.');
redirect('index.php');
?>
