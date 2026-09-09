<?php
// includes/auth.php

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Escapes HTML content for secure output.
 */
function h($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirects to a location and halts execution.
 */
function redirect($url) {
    header("Location: " . $url);
    exit;
}

/**
 * Sets a flash message to display on the next page load.
 */
function set_flash($type, $message, $is_html = false) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message,
        'is_html' => $is_html
    ];
}

/**
 * Renders and clears the flash message.
 */
function display_flash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        
        $type = h($flash['type']);
        $is_html = $flash['is_html'] ?? false;
        $msg = $is_html ? $flash['message'] : h($flash['message']);
        
        // Map common bootstrap alert types
        $alertClass = "alert-info";
        if ($type === 'success') $alertClass = "alert-success";
        if ($type === 'danger' || $type === 'error') $alertClass = "alert-danger";
        if ($type === 'warning') $alertClass = "alert-warning";
        
        echo "<div class='alert {$alertClass} alert-custom alert-dismissible fade show' role='alert'>
                {$msg}
                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
              </div>";
    }
}

/**
 * Checks if a user is logged in. Redirects to login page if not.
 */
function require_login() {
    if (!isset($_SESSION['user_id'])) {
        set_flash('danger', 'Please log in to access this page.');
        redirect('../login.php'); // default path adjusting for module directories
    }
}

/**
 * Checks if a user has one of the allowed roles. Redirects to landing page if not.
 */
function require_role($allowed_roles) {
    require_login();
    
    if (is_string($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }
    
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        set_flash('danger', 'Unauthorized access to this section.');
        // Redirect based on actual role
        if ($_SESSION['role'] === 'student') {
            redirect('../student/dashboard.php');
        } elseif ($_SESSION['role'] === 'counselor') {
            redirect('../counselor/dashboard.php');
        } elseif ($_SESSION['role'] === 'admin') {
            redirect('../admin/dashboard.php');
        } else {
            redirect('../index.php');
        }
    }
}

/**
 * Returns user details (students or counselors metadata) depending on role.
 */
function get_role_profile($pdo, $user_id, $role) {
    if ($role === 'student') {
        $stmt = $pdo->prepare("SELECT s.*, u.email, u.username, c.full_name AS counselor_name 
                               FROM students s
                               JOIN users u ON s.user_id = u.id
                               LEFT JOIN counselors c ON s.counselor_id = c.id
                               WHERE s.user_id = ?");
    } elseif ($role === 'counselor') {
        $stmt = $pdo->prepare("SELECT c.*, u.email, u.username 
                               FROM counselors c
                               JOIN users u ON c.user_id = u.id
                               WHERE c.user_id = ?");
    } else {
        // Admin
        $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id = ?");
    }
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}
?>
