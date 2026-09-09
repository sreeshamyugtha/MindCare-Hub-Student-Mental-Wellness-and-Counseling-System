<?php
// includes/header.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/events_db.php';
require_once __DIR__ . '/notifications_helper.php';

// Dynamically compute the base URL for portable routing
$project_dir = str_replace('\\', '/', dirname(__DIR__));
$document_root = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']);
$base_url = str_replace($document_root, '', $project_dir);
$base_url = '/' . trim($base_url, '/') . '/';
if ($base_url === '//') {
    $base_url = '/';
}
// URL-encode spaces in base URL to resolve path issues in script/stylesheet loads
$base_url = str_replace(' ', '%20', $base_url);

$current_script = $_SERVER['SCRIPT_NAME'];
$is_public = (!strpos($current_script, '/student/') && !strpos($current_script, '/counselor/') && !strpos($current_script, '/admin/'));

// Check if user is logged in for sidebar rendering
$logged_in = isset($_SESSION['user_id']);
$role = $logged_in ? $_SESSION['role'] : null;
$username = $logged_in ? $_SESSION['username'] : '';
$user_display_name = $logged_in ? ($_SESSION['full_name'] ?? $username) : '';

// Fetch counts for badges
$student_appointment_count = 0;
$counselor_photo = 'default.png';
$unread_notifications_count = 0;
$user_notifications = [];

if ($logged_in) {
    if (!isset($pdo)) {
        require_once __DIR__ . '/../config/db.php';
    }
    try {
        // Run automated reminder check periodically
        check_and_trigger_event_reminders($pdo);

        $unread_notifications_count = get_unread_notifications_count($pdo, $_SESSION['user_id']);
        $user_notifications = get_user_notifications($pdo, $_SESSION['user_id'], 5);

        if ($role === 'student' && isset($_SESSION['student_id'])) {
            $badgeStmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM appointments 
                WHERE student_id = ? AND appointment_date >= CURDATE() AND status IN ('approved', 'rescheduled')
            ");
            $badgeStmt->execute([$_SESSION['student_id']]);
            $student_appointment_count = $badgeStmt->fetchColumn();
        } elseif ($role === 'counselor') {
            $cPhotoStmt = $pdo->prepare("SELECT photo FROM counselors WHERE user_id = ?");
            $cPhotoStmt->execute([$_SESSION['user_id']]);
            $counselor_photo = $cPhotoStmt->fetchColumn() ?: 'default.png';
        }
    } catch (\Exception $e) {
        // Fallback silently if database is not initialized yet
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? h($page_title) . " | MindCare Hub" : "MindCare Hub - Student Mental Wellness & Counseling System"; ?></title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Chart.js (for analytics reports) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="<?php echo $base_url; ?>css/style.css?v=2.0">
    <!-- Theme Detection & Initialization Script to prevent layout shift/flash -->
    <script>
        (function() {
            try {
                const savedTheme = localStorage.getItem('theme') || 'light';
                document.documentElement.setAttribute('data-bs-theme', savedTheme);
            } catch (e) {
                document.documentElement.setAttribute('data-bs-theme', 'light');
            }
        })();
    </script>
</head>
<body>

<?php if ($is_public): ?>
    <!-- Public Navbar -->
    <nav class="navbar navbar-expand-lg navbar-custom py-3">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center gap-2 fw-bold" style="color: var(--primary);" href="<?php echo $base_url; ?>index.php">
                <i class="bi bi-heart-pulse-fill"></i> MindCare Hub
            </a>
            <button class="navbar-expand navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#publicNavbar" aria-controls="publicNavbar" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="publicNavbar">
                <ul class="navbar-nav public-nav-actions ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link nav-button" href="<?php echo $base_url; ?>index.php">Home</a>
                    </li>
                    <?php if ($logged_in): ?>
                        <li class="nav-item">
                            <a class="btn btn-primary-custom" href="<?php echo $base_url . $role; ?>/dashboard.php">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link nav-button" href="<?php echo $base_url; ?>login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="btn btn-secondary-custom" href="<?php echo $base_url; ?>register.php">Student Sign Up</a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item ms-lg-2">
                        <button id="themeToggle" class="btn btn-link text-secondary nav-link p-2" aria-label="Toggle Theme" style="text-decoration: none; box-shadow: none;">
                            <i class="bi bi-moon-fill" id="themeToggleIcon"></i>
                        </button>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
    <div class="container my-4">
        <?php display_flash(); ?>
    </div>

<?php else: ?>
    <!-- Logged In Dashboard Layout with Sidebar -->
    <div class="dashboard-wrapper">
        
        <!-- Toggle Button for Mobile Devices -->
        <button class="sidebar-toggle" aria-label="Toggle Navigation">
            <i class="bi bi-list"></i>
        </button>

        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="brand">
                <i class="bi bi-heart-pulse-fill text-secondary"></i>
                <span>MindCare Hub</span>
            </div>
            
            <ul class="nav-links">
                <?php if ($role === 'student'): ?>
                    <li class="<?php echo ($active_nav === 'dashboard') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>student/dashboard.php">
                            <i class="bi bi-grid-1x2-fill"></i> Dashboard
                        </a>
                    </li>
                    <li class="<?php echo ($active_nav === 'events') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>student/events.php">
                            <i class="bi bi-calendar2-heart-fill"></i> Campus Wellness Events
                        </a>
                    </li>
                    <li class="<?php echo ($active_nav === 'mood') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>student/mood.php">
                            <i class="bi bi-emoji-smile-fill"></i> Mood Tracker
                        </a>
                    </li>
                    <li class="<?php echo ($active_nav === 'assessment') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>student/assessment.php">
                            <i class="bi bi-clipboard2-pulse-fill"></i> Stress Test
                        </a>
                    </li>
                    <li class="<?php echo ($active_nav === 'journal') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>student/journal.php">
                            <i class="bi bi-journal-bookmark-fill"></i> My Journal
                        </a>
                    </li>
                    <li class="<?php echo ($active_nav === 'appointments') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>student/appointments.php" class="d-flex align-items-center justify-content-between w-100">
                            <span><i class="bi bi-calendar-check-fill"></i> Appointments</span>
                            <?php if ($student_appointment_count > 0): ?>
                                <span class="badge bg-danger rounded-pill px-2 py-0.5" style="font-size: 0.7rem; margin-right: 10px;"><?php echo $student_appointment_count; ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="<?php echo ($active_nav === 'articles') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>student/articles.php">
                            <i class="bi bi-book-half"></i> Articles
                        </a>
                    </li>
                    <li class="<?php echo ($active_nav === 'profile') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>student/profile.php">
                            <i class="bi bi-person-fill-gear"></i> My Profile
                        </a>
                    </li>
                
                <?php elseif ($role === 'counselor'): ?>
                    <li class="<?php echo ($active_nav === 'dashboard') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>counselor/dashboard.php">
                            <i class="bi bi-grid-1x2-fill"></i> Dashboard
                        </a>
                    </li>
                    <li class="<?php echo ($active_nav === 'students') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>counselor/students.php">
                            <i class="bi bi-people-fill"></i> My Students
                        </a>
                    </li>
                    <li class="<?php echo ($active_nav === 'reports') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>counselor/reports.php">
                            <i class="bi bi-file-earmark-bar-graph-fill"></i> Wellness Reports
                        </a>
                    </li>
                    <li class="<?php echo ($active_nav === 'appointments') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>counselor/appointments.php">
                            <i class="bi bi-calendar-check-fill"></i> Appointments
                        </a>
                    </li>
                    <li class="<?php echo ($active_nav === 'notes') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>counselor/notes.php">
                            <i class="bi bi-journal-text"></i> Session Notes
                        </a>
                    </li>
                    <li class="<?php echo ($active_nav === 'articles') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>counselor/articles.php">
                            <i class="bi bi-book-half"></i> Articles
                        </a>
                    </li>
                    <li class="<?php echo ($active_nav === 'profile') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>counselor/profile.php">
                            <i class="bi bi-person-fill-gear"></i> My Profile
                        </a>
                    </li>

                <?php elseif ($role === 'admin'): ?>
                    <li class="<?php echo ($active_nav === 'dashboard') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>admin/dashboard.php">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="<?php echo ($active_nav === 'events') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>admin/events.php">
                            <i class="bi bi-calendar-event-fill"></i> Event Management
                        </a>
                    </li>
                    <li class="<?php echo ($active_nav === 'students') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>admin/students.php">
                            <i class="bi bi-people-fill"></i> Manage Students
                        </a>
                    </li>
                    <li class="<?php echo ($active_nav === 'counselors') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>admin/counselors.php">
                            <i class="bi bi-person-badge-fill"></i> Manage Counselors
                        </a>
                    </li>
                    <li class="<?php echo ($active_nav === 'appointments') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>admin/appointments.php">
                            <i class="bi bi-calendar-week-fill"></i> Manage Appointments
                        </a>
                    </li>
                    <li class="<?php echo ($active_nav === 'reports') ? 'active' : ''; ?>">
                        <a href="<?php echo $base_url; ?>admin/reports.php">
                            <i class="bi bi-file-earmark-bar-graph-fill"></i> System Reports
                        </a>
                    </li>
                <?php endif; ?>
            </ul>

            <div class="user-profile-badge d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2 overflow-hidden">
                    <?php if ($role === 'counselor'): ?>
                        <img src="<?php echo $base_url; ?>uploads/counselors/<?php echo h($counselor_photo); ?>" 
                             alt="Dr. <?php echo h($user_display_name); ?>" 
                             class="avatar-photo avatar-photo-xs shadow-sm">
                    <?php else: ?>
                        <div class="avatar-small d-flex align-items-center justify-content-center bg-secondary text-white rounded-circle fw-bold" style="width:32px; height:32px; font-size: 0.9rem; flex-shrink: 0;">
                            <?php echo strtoupper(substr($username, 0, 1)); ?>
                        </div>
                    <?php endif; ?>
                    <div class="text-truncate text-light" style="font-size:0.85rem;">
                        <span class="d-block fw-semibold text-truncate"><?php echo h($user_display_name); ?></span>
                        <span class="d-block text-muted text-capitalize" style="font-size:0.75rem;"><?php echo h($role); ?></span>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <!-- Notification Bell Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-link p-0 border-0 text-secondary fs-5 position-relative" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                            <i class="bi bi-bell-fill"></i>
                            <?php if ($unread_notifications_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.65rem; padding: 2px 5px;">
                                    <?php echo $unread_notifications_count; ?>
                                </span>
                            <?php endif; ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow-lg p-0 border-0 overflow-hidden" style="width: 320px; max-height: 400px; font-size: 0.85rem;">
                            <li class="p-2 bg-light border-bottom d-flex align-items-center justify-content-between">
                                <span class="fw-bold text-dark"><i class="bi bi-bell me-1"></i> Notifications</span>
                                <?php if ($role === 'student'): ?>
                                    <a href="<?php echo $base_url; ?>student/notifications.php" class="text-primary small text-decoration-none">View All</a>
                                <?php endif; ?>
                            </li>
                            <div class="overflow-auto" style="max-height: 300px;">
                                <?php if (empty($user_notifications)): ?>
                                    <li class="p-3 text-center text-muted">No notifications yet.</li>
                                <?php else: ?>
                                    <?php foreach ($user_notifications as $notif): ?>
                                        <li class="p-2 border-bottom <?php echo $notif['is_read'] ? 'bg-white' : 'bg-light'; ?>">
                                            <a href="<?php echo $notif['link_url'] ? $base_url . $notif['link_url'] : '#'; ?>" class="text-decoration-none text-dark d-block">
                                                <div class="fw-semibold text-truncate"><?php echo h($notif['title']); ?></div>
                                                <div class="text-muted text-wrap small mt-1" style="font-size: 0.8rem;"><?php echo h($notif['message']); ?></div>
                                                <div class="text-muted text-end mt-1" style="font-size: 0.7rem;"><?php echo date('M d, g:i a', strtotime($notif['created_at'])); ?></div>
                                            </a>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </ul>
                    </div>

                    <button id="sidebarThemeToggle" class="btn btn-link p-0 border-0 text-secondary fs-5" aria-label="Toggle Theme" style="text-decoration: none; box-shadow: none;">
                        <i class="bi bi-moon-fill" id="sidebarThemeToggleIcon"></i>
                    </button>
                    <a href="<?php echo $base_url; ?>logout.php" class="text-danger fs-5 ms-1" title="Log Out">
                        <i class="bi bi-box-arrow-right"></i>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Workspace Area -->
        <main class="main-content">
            <!-- Alert/Flash Message Center -->
            <div class="mb-4">
                <?php display_flash(); ?>
            </div>
<?php endif; ?>
