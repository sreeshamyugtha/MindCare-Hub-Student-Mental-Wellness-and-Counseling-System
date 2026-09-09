<?php
// student/dashboard.php
$page_title = "Student Dashboard";
$active_nav = "dashboard";
require_once '../includes/auth.php';
require_once '../config/db.php';

// Assert Student Permissions
require_role('student');

$student_id = $_SESSION['student_id'];

// Get student and assigned counselor details
$profileStmt = $pdo->prepare("
    SELECT s.*, u.email, c.full_name AS counselor_name, c.specialization AS counselor_spec, c.photo AS counselor_photo
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN counselors c ON s.counselor_id = c.id
    WHERE s.id = ?
");
$profileStmt->execute([$student_id]);
$student = $profileStmt->fetch();

// 1. Process Quick Mood Logging Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_mood'])) {
    $mood_score = $_POST['mood_score'] ?? '';
    $notes = trim($_POST['mood_notes'] ?? '');
    $today = date('Y-m-d');
    
    if (in_array($mood_score, ['happy', 'neutral', 'sad', 'stressed'])) {
        try {
            // Check if mood is logged already for today
            $checkStmt = $pdo->prepare("SELECT id FROM moods WHERE student_id = ? AND mood_date = ?");
            $checkStmt->execute([$student_id, $today]);
            $existing = $checkStmt->fetch();
            
            if ($existing) {
                // Update today's mood
                $updateStmt = $pdo->prepare("UPDATE moods SET mood_score = ?, notes = ? WHERE id = ?");
                $updateStmt->execute([$mood_score, $notes, $existing['id']]);
                set_flash('success', "Today's mood updated to " . ucfirst($mood_score) . "!");
            } else {
                // Insert new mood for today
                $insertStmt = $pdo->prepare("INSERT INTO moods (student_id, mood_date, mood_score, notes) VALUES (?, ?, ?, ?)");
                $insertStmt->execute([$student_id, $today, $mood_score, $notes]);
                set_flash('success', "Logged today's mood as " . ucfirst($mood_score) . ". Good job tracking your wellness!");
            }
            redirect('dashboard.php');
        } catch (\Exception $e) {
            set_flash('danger', 'Error logging mood: ' . $e->getMessage());
        }
    } else {
        set_flash('warning', 'Please select a valid mood.');
    }
}

// 2. Fetch today's logged mood
$todayMoodStmt = $pdo->prepare("SELECT * FROM moods WHERE student_id = ? AND mood_date = ?");
$todayMoodStmt->execute([$student_id, date('Y-m-d')]);
$today_mood = $todayMoodStmt->fetch();

// 3. Fetch latest Stress Assessment
$assessmentStmt = $pdo->prepare("SELECT score, stress_level, taken_at FROM assessments WHERE student_id = ? ORDER BY taken_at DESC LIMIT 1");
$assessmentStmt->execute([$student_id]);
$last_assessment = $assessmentStmt->fetch();

// 4. Fetch next upcoming Appointment
$appointmentStmt = $pdo->prepare("
    SELECT a.*, c.full_name AS counselor_name 
    FROM appointments a
    JOIN counselors c ON a.counselor_id = c.id
    WHERE a.student_id = ? AND a.appointment_date >= CURDATE() AND a.status IN ('pending', 'approved', 'rescheduled')
    ORDER BY a.appointment_date ASC, a.appointment_time ASC LIMIT 1
");
$appointmentStmt->execute([$student_id]);
$next_appointment = $appointmentStmt->fetch();

// 5. Fetch latest counseling notes shared by counselor
$noteStmt = $pdo->prepare("
    SELECT cn.*, c.full_name AS counselor_name 
    FROM counseling_notes cn
    JOIN counselors c ON cn.counselor_id = c.id
    WHERE cn.student_id = ? AND cn.visible_to_student = 1 
    ORDER BY cn.created_at DESC LIMIT 1
");
$noteStmt->execute([$student_id]);
$shared_note = $noteStmt->fetch();

// 6. Get counts for Statistics Panel
$moodCount = $pdo->prepare("SELECT COUNT(*) FROM moods WHERE student_id = ?");
$moodCount->execute([$student_id]);
$total_moods = $moodCount->fetchColumn();

$journalCount = $pdo->prepare("SELECT COUNT(*) FROM journals WHERE student_id = ?");
$journalCount->execute([$student_id]);
$total_journals = $journalCount->fetchColumn();

// Ensure the enhanced journal fields exist for the dashboard summary card.
try {
    $journalColumns = [];
    $columnStmt = $pdo->query("SHOW COLUMNS FROM journals");
    foreach ($columnStmt->fetchAll() as $column) {
        $journalColumns[$column['Field']] = true;
    }
    if (!isset($journalColumns['journal_date'])) {
        $pdo->exec("ALTER TABLE journals ADD COLUMN journal_date DATE NULL AFTER student_id");
        $pdo->exec("UPDATE journals SET journal_date = DATE(created_at) WHERE journal_date IS NULL");
    }
    if (!isset($journalColumns['gratitude_points'])) {
        $pdo->exec("ALTER TABLE journals ADD COLUMN gratitude_points TEXT NULL AFTER content");
    }
} catch (\Exception $e) {
    // Keep the dashboard usable even if the database user cannot alter schema.
}

$todayJournalStmt = $pdo->prepare("SELECT id FROM journals WHERE student_id = ? AND journal_date = CURDATE() LIMIT 1");
$todayJournalStmt->execute([$student_id]);
$today_journal = $todayJournalStmt->fetch();

$journalDatesStmt = $pdo->prepare("SELECT DISTINCT journal_date FROM journals WHERE student_id = ? AND journal_date IS NOT NULL ORDER BY journal_date DESC");
$journalDatesStmt->execute([$student_id]);
$journal_dates = array_column($journalDatesStmt->fetchAll(), 'journal_date');

$journal_date_set = array_flip($journal_dates);
$journal_cursor = new DateTime(date('Y-m-d'));
if (!isset($journal_date_set[$journal_cursor->format('Y-m-d')])) {
    $journal_cursor->modify('-1 day');
}
$journal_current_streak = 0;
while (isset($journal_date_set[$journal_cursor->format('Y-m-d')])) {
    $journal_current_streak++;
    $journal_cursor->modify('-1 day');
}

$gratitudeMonthCount = $pdo->prepare("
    SELECT COUNT(*)
    FROM journals
    WHERE student_id = ?
      AND journal_date BETWEEN DATE_FORMAT(CURDATE(), '%Y-%m-01') AND LAST_DAY(CURDATE())
      AND gratitude_points IS NOT NULL
      AND TRIM(gratitude_points) <> ''
");
$gratitudeMonthCount->execute([$student_id]);
$gratitude_entries_this_month = $gratitudeMonthCount->fetchColumn();

// 7. Get Mood History data for Chart (Last 7 entries)
$chartStmt = $pdo->prepare("
    SELECT mood_date, mood_score 
    FROM moods 
    WHERE student_id = ? 
    ORDER BY mood_date DESC LIMIT 7
");
$chartStmt->execute([$student_id]);
$history_rows = array_reverse($chartStmt->fetchAll()); // Order chronologically

$chart_labels = [];
$chart_values = [];
$mood_scores_map = ['sad' => 1, 'stressed' => 2, 'neutral' => 3, 'happy' => 4];

// 8. Fetch Campus Wellness Event Data for Student Dashboard
require_once '../includes/events_db.php';
$student_registered_events = get_student_registered_events($pdo, $student_id);
$registered_eids = array_column($student_registered_events, 'id');

$upcoming_registered_events = array_filter($student_registered_events, function($ev) {
    return (strtotime($ev['event_date']) >= strtotime(date('Y-m-d')) && $ev['status'] === 'Upcoming');
});

$all_upcoming_stmt = $pdo->query("
    SELECT e.*, 
           (SELECT COUNT(*) FROM event_registrations er WHERE er.event_id = e.id AND er.registration_status = 'Registered') as registered_count
    FROM events e
    WHERE e.status = 'Upcoming' AND e.event_date >= CURDATE()
    ORDER BY e.event_date ASC LIMIT 4
");
$all_upcoming_events = $all_upcoming_stmt->fetchAll();
$recommended_events = array_filter($all_upcoming_events, function($ev) use ($registered_eids) {
    return !in_array($ev['id'], $registered_eids);
});

require_once '../includes/header.php';
?>

<!-- Campus Wellness Events Highlights Banner -->
<div class="card card-glass border-0 shadow-sm mb-4 p-4 fade-in-up bg-gradient-primary text-dark">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <span class="badge bg-primary px-3 py-1 mb-2">Campus Mental Health & Wellness</span>
            <h3 class="fw-bold mb-1 text-dark"><i class="bi bi-calendar2-heart-fill text-primary me-2"></i> Join Campus Wellness Events</h3>
            <p class="text-muted mb-0">Participate in group counseling, stress relief workshops, box breathing masterclasses, and yoga sessions.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="events.php?tab=my_events" class="btn btn-outline-primary fw-semibold">
                <i class="bi bi-ticket-perforated-fill me-1"></i> My Passes (<?php echo count($student_registered_events); ?>)
            </a>
            <a href="events.php?tab=browse" class="btn btn-primary-custom fw-semibold">
                <i class="bi bi-compass-fill me-1"></i> Browse All Events
            </a>
        </div>
    </div>
</div>

<?php if (!empty($upcoming_registered_events)): ?>
    <!-- Upcoming Registered Event Reminders Card -->
    <div class="card card-glass border-0 p-4 mb-4 shadow-sm fade-in-up border-start border-4 border-success">
        <h5 class="fw-bold text-dark mb-3"><i class="bi bi-bell-fill text-success me-2"></i> Upcoming Registered Event Reminder</h5>
        <div class="row g-3">
            <?php foreach (array_slice($upcoming_registered_events, 0, 2) as $uev): ?>
                <div class="col-md-6">
                    <div class="p-3 bg-white rounded border shadow-sm h-100 d-flex flex-column justify-content-between">
                        <div>
                            <span class="badge bg-secondary mb-2"><?php echo h($uev['category']); ?></span>
                            <h5 class="fw-bold text-dark mb-1"><?php echo h($uev['title']); ?></h5>
                            <div class="small text-muted mb-1"><i class="bi bi-calendar3 text-primary me-1"></i> <?php echo date('D, M d, Y', strtotime($uev['event_date'])); ?> | <?php echo date('h:i A', strtotime($uev['start_time'])); ?></div>
                            <div class="small text-muted mb-2"><i class="bi bi-geo-alt-fill text-danger me-1"></i> <?php echo h($uev['venue']); ?></div>
                        </div>
                        <div class="pt-2 border-top d-flex justify-content-between align-items-center">
                            <span class="small text-success fw-bold"><i class="bi bi-check-circle-fill"></i> Seat Reserved</span>
                            <a href="events.php?tab=my_events" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-calendar-check me-1"></i> View Event
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>


<?php
// Capture Left column elements (Mood Logger & Chart)
ob_start();
?>
<!-- Gratitude Journal Status Card -->
<div class="card card-glass border-0 p-4 mb-4 shadow-sm">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
        <div>
            <h5 class="fw-bold mb-1 text-dark"><i class="bi bi-journal-heart text-danger me-2"></i>Gratitude Journal</h5>
            <p class="text-muted small mb-0">Reflect today, build your streak, and keep the good moments visible.</p>
        </div>
        <a href="journal.php#writeToday" class="btn btn-primary-custom">
            <i class="bi bi-pencil-square me-1"></i> Write Today's Journal
        </a>
    </div>
    <div class="row g-2 text-center">
        <div class="col-6 col-md-3">
            <div class="bg-light p-3 rounded-3 h-100">
                <span class="text-muted small d-block">Today's Status</span>
                <span class="fw-bold text-dark"><?php echo $today_journal ? 'Completed' : 'Not Written'; ?></span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="bg-light p-3 rounded-3 h-100">
                <span class="text-muted small d-block">Writing Streak</span>
                <span class="fw-bold text-dark">&#128293; <?php echo h($journal_current_streak); ?> Days</span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="bg-light p-3 rounded-3 h-100">
                <span class="text-muted small d-block">Total Entries</span>
                <span class="fw-bold text-dark"><?php echo h($total_journals); ?></span>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="bg-light p-3 rounded-3 h-100">
                <span class="text-muted small d-block">Gratitude This Month</span>
                <span class="fw-bold text-dark"><?php echo h($gratitude_entries_this_month); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Quick Log Mood Panel -->
<div class="card card-glass border-0 p-4 mb-4">
    <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-plus-circle-fill text-secondary me-2"></i> How are you feeling today?</h5>
    <form action="dashboard.php" method="POST">
        <input type="hidden" name="log_mood" value="1">
        <input type="hidden" name="mood_score" id="mood_score_input" value="<?php echo $today_mood ? h($today_mood['mood_score']) : ''; ?>" required>
        
        <div class="row g-2 mb-3">
            <div class="col-6 col-sm-3">
                <div class="mood-option happy <?php echo ($today_mood && $today_mood['mood_score'] === 'happy') ? 'selected' : ''; ?>" data-mood="happy">
                    <span class="mood-emoji">😊</span>
                    <span class="fw-semibold small">Happy</span>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="mood-option neutral <?php echo ($today_mood && $today_mood['mood_score'] === 'neutral') ? 'selected' : ''; ?>" data-mood="neutral">
                    <span class="mood-emoji">😐</span>
                    <span class="fw-semibold small">Neutral</span>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="mood-option sad <?php echo ($today_mood && $today_mood['mood_score'] === 'sad') ? 'selected' : ''; ?>" data-mood="sad">
                    <span class="mood-emoji">😢</span>
                    <span class="fw-semibold small">Sad</span>
                </div>
            </div>
            <div class="col-6 col-sm-3">
                <div class="mood-option stressed <?php echo ($today_mood && $today_mood['mood_score'] === 'stressed') ? 'selected' : ''; ?>" data-mood="stressed">
                    <span class="mood-emoji">😫</span>
                    <span class="fw-semibold small">Stressed</span>
                </div>
            </div>
        </div>
        
        <div class="mb-3">
            <label for="mood_notes" class="form-label small text-muted">Daily Notes (Optional)</label>
            <textarea name="mood_notes" id="mood_notes" rows="2" class="form-control" placeholder="What is causing this feeling today?"><?php echo $today_mood ? h($today_mood['notes']) : ''; ?></textarea>
        </div>
        
        <div class="text-end">
            <button type="submit" class="btn btn-primary-custom">
                <i class="bi bi-check-circle me-1"></i> <?php echo $today_mood ? 'Update Mood' : 'Save Mood'; ?>
            </button>
        </div>
    </form>
</div>

<!-- Mood Progression Chart -->
<div class="card card-glass border-0 p-4">
    <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-graph-up text-primary me-2"></i> Emotional Mood Progression</h5>
    <div style="position: relative; height: 260px;">
        <?php if (count($chart_labels) > 0): ?>
            <canvas id="moodChart"></canvas>
        <?php else: ?>
            <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                <p>No mood logs yet. Use the panel above to log your emotional status!</p>
            </div>
        <?php endif; ?>
    </div>
</div>
<?php
$extra_panels_html = ob_get_clean();

// Capture Right column elements (Counselor, Appointments, Feedback)
ob_start();
?>
<!-- counselor stats summaries row -->
<div class="card card-glass border-0 p-3 mb-4 shadow-sm">
    <span class="text-muted small d-block mb-2"><i class="bi bi-activity"></i> Logged Counts</span>
    <div class="row g-2 text-center">
        <div class="col-6">
            <div class="bg-light p-2 rounded">
                <span class="text-muted small d-block">Mood Logs</span>
                <span class="fw-bold text-dark fs-5"><?php echo h($total_moods); ?></span>
            </div>
        </div>
        <div class="col-6">
            <div class="bg-light p-2 rounded">
                <span class="text-muted small d-block">Journals</span>
                <span class="fw-bold text-dark fs-5"><?php echo h($total_journals); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Counselor Assignment Info -->
<div class="card card-glass border-0 p-4 mb-4 shadow-sm">
    <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-person-badge-fill text-secondary me-2"></i> Assigned Counselor</h5>
    <?php if ($student['counselor_id']): ?>
        <div class="d-flex align-items-center gap-3">
            <img src="../uploads/counselors/<?php echo !empty($student['counselor_photo']) ? h($student['counselor_photo']) : 'default.png'; ?>" 
                 alt="<?php echo h($student['counselor_name']); ?>" 
                 class="avatar-photo avatar-photo-md shadow-sm">
            <div>
                <h6 class="fw-bold mb-0 text-dark"><?php echo h($student['counselor_name']); ?></h6>
                <span class="text-muted small d-block"><?php echo h($student['counselor_spec']); ?></span>
            </div>
        </div>
    <?php else: ?>
        <div class="bg-light rounded p-3 text-center">
            <p class="text-muted small mb-0">You currently do not have an assigned counselor.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Next Scheduled Appointment -->
<div class="card card-glass border-0 p-4 mb-4 shadow-sm">
    <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-calendar-event text-danger me-2"></i> Next Consultation</h5>
    <?php if ($next_appointment): ?>
        <div class="p-3 bg-light rounded-3 mb-3 border-start border-3 border-primary">
            <div class="d-flex justify-content-between align-items-start mb-2">
                <span class="badge bg-primary text-capitalize small"><?php echo h($next_appointment['status']); ?></span>
                <span class="text-muted small" style="font-size:0.8rem;"><i class="bi bi-clock me-1"></i> <?php echo date('h:i A', strtotime($next_appointment['appointment_time'])); ?></span>
            </div>
            <h6 class="fw-bold mb-1 text-dark"><?php echo h($next_appointment['counselor_name']); ?></h6>
            <p class="text-muted small mb-0"><i class="bi bi-calendar-date me-1"></i> <?php echo date('F d, Y', strtotime($next_appointment['appointment_date'])); ?></p>
        </div>
    <?php else: ?>
        <div class="p-3 text-center bg-light rounded-3">
            <p class="text-muted small mb-0">No upcoming appointments booked.</p>
        </div>
    <?php endif; ?>
</div>

<!-- Latest Counseling Note shared -->
<div class="card card-glass border-0 p-4 shadow-sm">
    <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-chat-left-text-fill text-warning me-2"></i> Advisor Feedback</h5>
    <?php if ($shared_note): ?>
        <div class="p-3 bg-white rounded-3 border mb-2" style="font-size: 0.9rem;">
            <p class="text-muted text-truncate mb-2" style="font-size:0.75rem;">Recorded on <?php echo date('M d, Y', strtotime($shared_note['created_at'])); ?></p>
            <p class="mb-0 text-secondary" style="font-style: italic; line-height: 1.4;">"<?php echo nl2br(h($shared_note['notes'])); ?>"</p>
        </div>
    <?php else: ?>
        <p class="text-muted small mb-0 text-center py-2">No counseling feedback shared yet.</p>
    <?php endif; ?>
</div>
<?php
$sidebar_panels_html = ob_get_clean();

// Setup variables for template
$welcome_name = $student['full_name'];
$role = 'student';

// Define Quick Navigation Grid
$quick_nav_cards = [
    [
        'title' => 'Mood Tracker',
        'url' => 'mood.php',
        'icon' => 'bi-emoji-smile-fill',
        'desc' => 'Log and review your emotional states over time.',
        'badge_class' => 'border-primary'
    ],
    [
        'title' => 'Stress Test',
        'url' => 'assessment.php',
        'icon' => 'bi-clipboard2-pulse-fill',
        'desc' => 'Evaluate your stress level with the PSS-10 test.',
        'badge_class' => 'border-info'
    ],
    [
        'title' => 'My Private Journal',
        'url' => 'journal.php',
        'icon' => 'bi-journal-bookmark-fill',
        'desc' => 'Write down your daily reflections and thoughts.',
        'badge_class' => 'border-warning'
    ],
    [
        'title' => 'Appointments',
        'url' => 'appointments.php',
        'icon' => 'bi-calendar-check-fill',
        'desc' => 'Schedule and track counseling appointments.',
        'badge_class' => 'border-danger'
    ]
];

// Include shared layout template
require_once '../includes/common_dashboard.php';
?>

<!-- Chart.js configuration block -->
<?php if (count($chart_labels) > 0): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('moodChart').getContext('2d');
    const labels = <?php echo json_encode($chart_labels); ?>;
    const values = <?php echo json_encode($chart_values); ?>;
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Mood Progression',
                data: values,
                borderColor: '#4A6FA5',
                backgroundColor: 'rgba(74, 111, 165, 0.15)',
                borderWidth: 3,
                tension: 0.35,
                fill: true,
                pointBackgroundColor: '#5B8C85',
                pointBorderColor: '#FFFFFF',
                pointRadius: 6,
                pointHoverRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const val = context.raw;
                            const labelMap = {1: 'Sad 😢', 2: 'Stressed 😫', 3: 'Neutral 😐', 4: 'Happy 😊'};
                            return 'Mood: ' + labelMap[val];
                        }
                    }
                }
            },
            scales: {
                y: {
                    min: 1,
                    max: 4,
                    ticks: {
                        stepSize: 1,
                        callback: function(value) {
                            const tickMap = {1: 'Sad', 2: 'Stressed', 3: 'Neutral', 4: 'Happy'};
                            return tickMap[value];
                        }
                    },
                    grid: {
                        color: 'rgba(0,0,0,0.05)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
});
</script>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
