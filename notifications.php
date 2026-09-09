<?php
// student/notifications.php
$page_title = "Notifications & Reminders";
$active_nav = "events";

require_once '../includes/auth.php';
require_once '../config/db.php';
require_once '../includes/events_db.php';
require_once '../includes/notifications_helper.php';

require_role('student');
$user_id = $_SESSION['user_id'];

// Handle Mark All Read
if (isset($_POST['mark_all_read'])) {
    mark_notifications_read($pdo, $user_id);
    set_flash('success', 'All notifications marked as read.');
    redirect('notifications.php');
}

// Handle Single Read
if (isset($_GET['read_id'])) {
    mark_notifications_read($pdo, $user_id, intval($_GET['read_id']));
    redirect('notifications.php');
}

$notifications_list = get_user_notifications($pdo, $user_id, 50);

require_once '../includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-2 fade-in-up">
    <div>
        <h1 class="fw-bold text-dark mb-1"><i class="bi bi-bell-fill text-primary me-2"></i> Event Reminders & Notifications</h1>
        <p class="text-muted mb-0">Stay updated on new wellness events, schedule changes, and event reminders.</p>
    </div>
    <div class="d-flex gap-2">
        <a href="events.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i> Back to Events</a>
        <?php if (!empty($notifications_list)): ?>
            <form action="notifications.php" method="POST">
                <button type="submit" name="mark_all_read" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-check-all me-1"></i> Mark All as Read
                </button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card card-glass border-0 shadow-sm p-4 fade-in-up">
    <?php if (empty($notifications_list)): ?>
        <div class="text-center py-5">
            <i class="bi bi-bell-slash display-3 text-muted mb-3 d-block"></i>
            <h5 class="fw-bold text-dark">No Notifications</h5>
            <p class="text-muted">You have no event notifications or reminders at this time.</p>
        </div>
    <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($notifications_list as $notif): ?>
                <?php
                $bg_class = $notif['is_read'] ? 'bg-white' : 'bg-light border-start border-4 border-primary';
                $badge_type = 'bg-info';
                if ($notif['type'] === 'success') $badge_type = 'bg-success';
                if ($notif['type'] === 'warning') $badge_type = 'bg-warning text-dark';
                if ($notif['type'] === 'reminder') $badge_type = 'bg-purple';
                if ($notif['type'] === 'event') $badge_type = 'bg-primary';
                ?>
                <div class="list-group-item p-3 mb-2 rounded border shadow-sm <?php echo $bg_class; ?>">
                    <div class="d-flex align-items-center justify-content-between mb-1">
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge <?php echo $badge_type; ?> px-2 py-1 text-capitalize"><?php echo h($notif['type']); ?></span>
                            <h6 class="fw-bold text-dark mb-0"><?php echo h($notif['title']); ?></h6>
                        </div>
                        <span class="text-muted small"><?php echo date('M d, Y h:i A', strtotime($notif['created_at'])); ?></span>
                    </div>
                    <p class="text-muted mb-2 small" style="font-size: 0.9rem;"><?php echo h($notif['message']); ?></p>
                    <div class="d-flex justify-content-end gap-2 align-items-center">
                        <?php if ($notif['link_url']): ?>
                            <a href="../<?php echo h($notif['link_url']); ?>" class="btn btn-sm btn-link text-primary p-0 text-decoration-none small">
                                View Event Details <i class="bi bi-arrow-right"></i>
                            </a>
                        <?php endif; ?>
                        <?php if (!$notif['is_read']): ?>
                            <a href="notifications.php?read_id=<?php echo $notif['id']; ?>" class="btn btn-sm btn-outline-secondary py-0 px-2" style="font-size: 0.75rem;">Mark Read</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once '../includes/footer.php'; ?>
