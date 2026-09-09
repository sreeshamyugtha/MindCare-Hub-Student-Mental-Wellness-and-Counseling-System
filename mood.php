<?php
// student/mood.php
$page_title = "Mood History";
$active_nav = "mood";
require_once '../includes/auth.php';
require_once '../config/db.php';

// Assert Student Permissions
require_role('student');

$student_id = $_SESSION['student_id'];

// 1. Process delete request
if (isset($_GET['action']) && $_GET['action'] === 'delete') {
    $mood_id = intval($_GET['id'] ?? 0);
    if ($mood_id > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM moods WHERE id = ? AND student_id = ?");
            $stmt->execute([$mood_id, $student_id]);
            set_flash('success', 'Mood log entry deleted successfully.');
        } catch (\Exception $e) {
            set_flash('danger', 'Error deleting mood log: ' . $e->getMessage());
        }
    }
    redirect('mood.php');
}

// 2. Fetch all historical mood logs
$stmt = $pdo->prepare("SELECT * FROM moods WHERE student_id = ? ORDER BY mood_date DESC");
$stmt->execute([$student_id]);
$moods = $stmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h1 class="fw-bold">Mood Tracker History</h1>
        <p class="text-muted">Review your logged emotional states over time.</p>
    </div>
    <div class="col-sm-6 text-sm-end">
        <a href="dashboard.php" class="btn btn-primary-custom">
            <i class="bi bi-plus-circle me-1"></i> Log Today's Mood
        </a>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card card-glass border-0 p-4">
            <h4 class="fw-bold mb-3"><i class="bi bi-calendar-heart text-secondary me-2"></i> Previous Logs</h4>
            
            <?php if (count($moods) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="width: 20%;">Date</th>
                                <th style="width: 20%;">Mood Status</th>
                                <th style="width: 45%;">Notes / Reflection</th>
                                <th style="width: 15%;" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($moods as $row): ?>
                                <?php
                                    $emoji = '😐';
                                    if ($row['mood_score'] === 'happy') $emoji = '😊';
                                    if ($row['mood_score'] === 'sad') $emoji = '😢';
                                    if ($row['mood_score'] === 'stressed') $emoji = '😫';
                                ?>
                                <tr>
                                    <td class="fw-semibold">
                                        <?php echo date('M d, Y', strtotime($row['mood_date'])); ?>
                                        <?php if ($row['mood_date'] === date('Y-m-d')): ?>
                                            <span class="badge bg-success ms-1" style="font-size:0.7rem;">Today</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo h($row['mood_score']); ?> text-capitalize px-3 py-2 rounded-pill">
                                            <span class="me-1"><?php echo $emoji; ?></span><?php echo h($row['mood_score']); ?>
                                        </span>
                                    </td>
                                    <td class="text-secondary small">
                                        <?php echo !empty($row['notes']) ? h($row['notes']) : '<span class="text-muted" style="font-style:italic;">No thoughts written today.</span>'; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="mood.php?action=delete&id=<?php echo $row['id']; ?>" class="text-danger fs-5" onclick="return confirm('Are you sure you want to delete this mood log?');" title="Delete Log">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-5 text-center">
                    <span class="display-3 text-muted"><i class="bi bi-calendar-x"></i></span>
                    <p class="text-muted mt-3">No mood records found. Start logging today's mood to build your wellness history!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
