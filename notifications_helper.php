<?php
// includes/notifications_helper.php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/events_db.php';

/**
 * Gets unread notifications count for a user.
 */
function get_unread_notifications_count($pdo, $user_id) {
    if (!$user_id) return 0;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    } catch (\Exception $e) {
        return 0;
    }
}

/**
 * Gets user notifications list.
 */
function get_user_notifications($pdo, $user_id, $limit = 10) {
    if (!$user_id) return [];
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT " . intval($limit)
        );
        $stmt->execute([$user_id]);
        return $stmt->fetchAll();
    } catch (\Exception $e) {
        return [];
    }
}

/**
 * Marks notifications as read for a user.
 */
function mark_notifications_read($pdo, $user_id, $notification_id = null) {
    if (!$user_id) return;
    try {
        if ($notification_id) {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
            $stmt->execute([$notification_id, $user_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
            $stmt->execute([$user_id]);
        }
    } catch (\Exception $e) {
        // silent fallback
    }
}
?>
