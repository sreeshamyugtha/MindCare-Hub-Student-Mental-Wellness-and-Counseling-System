<?php
// counselor/students.php
$page_title = "Assigned Students";
$active_nav = "students";
require_once '../includes/auth.php';
require_once '../config/db.php';

// Assert Counselor Permissions
require_role('counselor');

$counselor_id = $_SESSION['counselor_id'];
$selected_student_id = intval($_GET['student_id'] ?? 0);
$student_details = null;

try {
    $journalColumns = [];
    $columnStmt = $pdo->query("SHOW COLUMNS FROM journals");
    foreach ($columnStmt->fetchAll() as $column) {
        $journalColumns[$column['Field']] = true;
    }
    if (!isset($journalColumns['privacy'])) {
        $pdo->exec("ALTER TABLE journals ADD COLUMN privacy VARCHAR(40) NOT NULL DEFAULT 'private'");
    }
} catch (\Exception $e) {
    // Student details still load; journal privacy migration can run from the journal page.
}

// Security check: If student selected, verify they are mapped to this counselor
if ($selected_student_id > 0) {
    $stmt = $pdo->prepare("
        SELECT s.*, u.email, u.username 
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.id = ? AND s.counselor_id = ?
    ");
    $stmt->execute([$selected_student_id, $counselor_id]);
    $student_details = $stmt->fetch();
    
    if (!$student_details) {
        set_flash('danger', 'Unauthorized or student record not found.');
        redirect('students.php');
    }
    
    // Fetch Student's Wellness Data
    // A. Moods
    $moodsStmt = $pdo->prepare("SELECT * FROM moods WHERE student_id = ? ORDER BY mood_date DESC");
    $moodsStmt->execute([$selected_student_id]);
    $mood_records = $moodsStmt->fetchAll();
    
    // B. Assessments
    $assessmentsStmt = $pdo->prepare("SELECT * FROM assessments WHERE student_id = ? ORDER BY taken_at DESC");
    $assessmentsStmt->execute([$selected_student_id]);
    $assessment_records = $assessmentsStmt->fetchAll();
    
    // C. Journals
    $journalsStmt = $pdo->prepare("SELECT * FROM journals WHERE student_id = ? AND privacy = 'share_counselor' ORDER BY created_at DESC");
    $journalsStmt->execute([$selected_student_id]);
    $journal_records = $journalsStmt->fetchAll();
    
    // D. Counseling Notes
    $notesStmt = $pdo->prepare("SELECT * FROM counseling_notes WHERE student_id = ? AND counselor_id = ? ORDER BY created_at DESC");
    $notesStmt->execute([$selected_student_id, $counselor_id]);
    $session_notes = $notesStmt->fetchAll();
    
    // Prepare Mood progression data for Chart.js
    $chartStmt = $pdo->prepare("SELECT mood_date, mood_score FROM moods WHERE student_id = ? ORDER BY mood_date DESC LIMIT 10");
    $chartStmt->execute([$selected_student_id]);
    $chart_rows = array_reverse($chartStmt->fetchAll());
    
    $chart_labels = [];
    $chart_values = [];
    $mood_scores_map = ['sad' => 1, 'stressed' => 2, 'neutral' => 3, 'happy' => 4];
    
    foreach ($chart_rows as $row) {
        $chart_labels[] = date('M d', strtotime($row['mood_date']));
        $chart_values[] = $mood_scores_map[$row['mood_score']];
    }
} else {
    // Fetch all assigned students
    $studentsStmt = $pdo->prepare("
        SELECT s.*, u.email, 
               (SELECT mood_score FROM moods WHERE student_id = s.id ORDER BY mood_date DESC LIMIT 1) as latest_mood,
               (SELECT stress_level FROM assessments WHERE student_id = s.id ORDER BY taken_at DESC LIMIT 1) as latest_stress
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.counselor_id = ?
        ORDER BY s.full_name ASC
    ");
    $studentsStmt->execute([$counselor_id]);
    $assigned_students = $studentsStmt->fetchAll();
}

require_once '../includes/header.php';
?>

<?php if ($student_details): ?>
    <!-- STUDENT DETAILS VIEW (Tabbed Panel) -->
    <div class="row mb-4">
        <div class="col-sm-8">
            <h1 class="fw-bold"><?php echo h($student_details['full_name']); ?></h1>
            <p class="text-muted mb-0">Student ID: <b><?php echo h($student_details['student_id_number']); ?></b> | Email: <?php echo h($student_details['email']); ?></p>
        </div>
        <div class="col-sm-4 text-sm-end">
            <a href="students.php" class="btn btn-outline-secondary mt-2" style="border-radius:8px;">
                <i class="bi bi-arrow-left me-1"></i> Back to Student List
            </a>
        </div>
    </div>
    
    <!-- Tab Navigation Headers -->
    <div class="card card-glass border-0 p-3 mb-4 shadow-sm">
        <ul class="nav nav-pills nav-fill" id="studentTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab" aria-controls="overview" aria-selected="true">
                    <i class="bi bi-heart-pulse-fill me-1"></i> Wellness Overview
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="moods-tab" data-bs-toggle="tab" data-bs-target="#moods" type="button" role="tab" aria-controls="moods" aria-selected="false">
                    <i class="bi bi-emoji-smile-fill me-1"></i> Mood History (<?php echo count($mood_records); ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="assessments-tab" data-bs-toggle="tab" data-bs-target="#assessments" type="button" role="tab" aria-controls="assessments" aria-selected="false">
                    <i class="bi bi-clipboard-pulse me-1"></i> Stress Tests (<?php echo count($assessment_records); ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="journals-tab" data-bs-toggle="tab" data-bs-target="#journals" type="button" role="tab" aria-controls="journals" aria-selected="false">
                    <i class="bi bi-journals me-1"></i> Private Journals (<?php echo count($journal_records); ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes" type="button" role="tab" aria-controls="notes" aria-selected="false">
                    <i class="bi bi-journal-text me-1"></i> Session Notes (<?php echo count($session_notes); ?>)
                </button>
            </li>
        </ul>
    </div>
    
    <!-- Tab Panes Content -->
    <div class="tab-content" id="studentTabContent">
        
        <!-- Tab 1: Overview and Charts -->
        <div class="tab-pane fade show active" id="overview" role="tabpanel" aria-labelledby="overview-tab">
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="card card-glass border-0 p-4 shadow-sm" style="min-height: 320px;">
                        <h5 class="fw-bold mb-3"><i class="bi bi-graph-up text-primary me-2"></i> Mood Progression Chart</h5>
                        <?php if (count($chart_labels) > 0): ?>
                            <div style="position: relative; height: 250px;">
                                <canvas id="studentMoodChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                                <p class="my-5">No mood data recorded by this student yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="card card-glass border-0 p-4 shadow-sm h-100">
                        <h5 class="fw-bold mb-3"><i class="bi bi-info-circle text-secondary me-2"></i> Diagnostic Summary</h5>
                        
                        <div class="mb-4">
                            <span class="text-muted small d-block">Latest Logged Mood</span>
                            <?php if (count($mood_records) > 0): ?>
                                <span class="badge badge-<?php echo h($mood_records[0]['mood_score']); ?> fs-6 mt-1 px-3 py-2 rounded-pill text-capitalize">
                                    <?php echo h($mood_records[0]['mood_score']); ?>
                                </span>
                                <span class="text-muted d-block small mt-1">Logged on <?php echo date('M d, Y', strtotime($mood_records[0]['mood_date'])); ?></span>
                            <?php else: ?>
                                <span class="text-muted mt-1 d-block small">No logs recorded</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-4">
                            <span class="text-muted small d-block">Perceived Stress Diagnostics</span>
                            <?php if (count($assessment_records) > 0): ?>
                                <span class="badge fs-6 mt-1 px-3 py-2 rounded-pill <?php echo ($assessment_records[0]['stress_level'] === 'High Stress') ? 'bg-danger' : (($assessment_records[0]['stress_level'] === 'Moderate Stress') ? 'bg-warning text-dark' : 'bg-success'); ?>">
                                    <?php echo h($assessment_records[0]['stress_level']); ?>
                                </span>
                                <span class="text-muted d-block small mt-1">Score: <?php echo h($assessment_records[0]['score']); ?>/40 (Taken: <?php echo date('M d, Y', strtotime($assessment_records[0]['taken_at'])); ?>)</span>
                            <?php else: ?>
                                <span class="text-muted mt-1 d-block small">No stress assessments completed</span>
                            <?php endif; ?>
                        </div>
                        
                        <a href="notes.php?student_id=<?php echo $selected_student_id; ?>" class="btn btn-primary-custom w-100"><i class="bi bi-journal-plus me-1"></i> Add Counseling Note</a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tab 2: Mood Logs History -->
        <div class="tab-pane fade" id="moods" role="tabpanel" aria-labelledby="moods-tab">
            <div class="card card-glass border-0 p-4 shadow-sm">
                <h5 class="fw-bold mb-3"><i class="bi bi-emoji-smile-fill text-secondary me-2"></i> Mood Diary logs</h5>
                <?php if (count($mood_records) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th style="width:25%;">Date</th>
                                    <th style="width:25%;">Mood State</th>
                                    <th style="width:50%;">Notes / Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mood_records as $row): ?>
                                    <tr>
                                        <td class="fw-semibold small"><?php echo date('F d, Y', strtotime($row['mood_date'])); ?></td>
                                        <td>
                                            <span class="badge badge-<?php echo h($row['mood_score']); ?> text-capitalize px-3 py-1.5 rounded-pill">
                                                <?php echo h($row['mood_score']); ?>
                                            </span>
                                        </td>
                                        <td class="text-secondary small"><?php echo !empty($row['notes']) ? h($row['notes']) : '<span class="text-muted" style="font-style:italic;">No descriptions added</span>'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small text-center py-4 mb-0">No mood tracking details recorded.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tab 3: Stress Assessment History -->
        <div class="tab-pane fade" id="assessments" role="tabpanel" aria-labelledby="assessments-tab">
            <div class="card card-glass border-0 p-4 shadow-sm">
                <h5 class="fw-bold mb-3"><i class="bi bi-clipboard2-pulse-fill text-primary me-2"></i> PSS-10 Diagnostic Reports</h5>
                <?php if (count($assessment_records) > 0): ?>
                    <div class="accordion" id="assessmentAccordion">
                        <?php foreach ($assessment_records as $index => $row): ?>
                            <div class="accordion-item border-0 bg-light rounded-3 mb-3 overflow-hidden shadow-sm">
                                <h2 class="accordion-header" id="heading<?php echo $row['id']; ?>">
                                    <button class="accordion-button collapsed bg-white border-bottom" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $row['id']; ?>" aria-expanded="false" aria-controls="collapse<?php echo $row['id']; ?>">
                                        <div class="d-flex justify-content-between align-items-center w-100 pe-3">
                                            <div>
                                                <span class="fw-bold text-dark me-3" style="font-size:0.9rem;">Test Date: <?php echo date('M d, Y h:i A', strtotime($row['taken_at'])); ?></span>
                                                <span class="badge <?php echo ($row['stress_level'] === 'High Stress') ? 'bg-danger' : (($row['stress_level'] === 'Moderate Stress') ? 'bg-warning text-dark' : 'bg-success'); ?> small">
                                                    <?php echo h($row['stress_level']); ?>
                                                </span>
                                            </div>
                                            <span class="fw-semibold text-secondary small">Total Score: <?php echo h($row['score']); ?>/40</span>
                                        </div>
                                    </button>
                                </h2>
                                <div id="collapse<?php echo $row['id']; ?>" class="accordion-collapse collapse" aria-labelledby="heading<?php echo $row['id']; ?>" data-bs-parent="#assessmentAccordion">
                                    <div class="accordion-body bg-white p-4">
                                        <h6 class="fw-bold mb-3">Individual Item Answers (Scale 0-4)</h6>
                                        <div class="row g-3">
                                            <div class="col-sm-6 small">
                                                <ol class="ps-3 mb-0">
                                                    <li class="mb-2">Unexpected upset: <b><?php echo $row['q1']; ?></b></li>
                                                    <li class="mb-2">Unable to control life: <b><?php echo $row['q2']; ?></b></li>
                                                    <li class="mb-2">Felt nervous and stressed: <b><?php echo $row['q3']; ?></b></li>
                                                    <li class="mb-2">Confident handling problems (R): <b><?php echo $row['q4']; ?></b></li>
                                                    <li class="mb-2">Things going your way (R): <b><?php echo $row['q5']; ?></b></li>
                                                </ol>
                                            </div>
                                            <div class="col-sm-6 small">
                                                <ol class="ps-3 mb-0" start="6">
                                                    <li class="mb-2">Could not cope with duties: <b><?php echo $row['q6']; ?></b></li>
                                                    <li class="mb-2">Control irritations (R): <b><?php echo $row['q7']; ?></b></li>
                                                    <li class="mb-2">On top of things (R): <b><?php echo $row['q8']; ?></b></li>
                                                    <li class="mb-2">Angered by external limits: <b><?php echo $row['q9']; ?></b></li>
                                                    <li class="mb-2">Difficulties too high: <b><?php echo $row['q10']; ?></b></li>
                                                </ol>
                                            </div>
                                        </div>
                                        <span class="text-muted d-block mt-3 small" style="font-size:0.75rem;">(R) Denotes reverse-scored items. higher numbers reflect higher perceived stress.</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small text-center py-4 mb-0">No stress assessments taken by this student.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tab 4: Student Journals -->
        <div class="tab-pane fade" id="journals" role="tabpanel" aria-labelledby="journals-tab">
            <div class="card card-glass border-0 p-4 shadow-sm">
                <h5 class="fw-bold mb-3"><i class="bi bi-journal-bookmark-fill text-warning me-2"></i> Student Reflections Diary</h5>
                <?php if (count($journal_records) > 0): ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($journal_records as $row): ?>
                            <div class="p-3 bg-light rounded-3 border-start border-3 border-secondary">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h6 class="fw-bold mb-0 text-dark"><?php echo h($row['title']); ?></h6>
                                    <span class="text-muted small" style="font-size:0.75rem;"><i class="bi bi-clock me-1"></i> <?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?></span>
                                </div>
                                <p class="text-secondary mb-0 small" style="white-space: pre-line; line-height: 1.5;"><?php echo h($row['content']); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small text-center py-4 mb-0">No journal logs submitted by this student.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tab 5: Counseling Notes & Timeline -->
        <div class="tab-pane fade" id="notes" role="tabpanel" aria-labelledby="notes-tab">
            <div class="card card-glass border-0 p-4 shadow-sm">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0"><i class="bi bi-timeline text-success me-2"></i> Session History</h5>
                    <a href="notes.php?student_id=<?php echo $selected_student_id; ?>" class="btn btn-success btn-sm" style="border-radius:6px;">
                        <i class="bi bi-plus-circle me-1"></i> Log Session Note
                    </a>
                </div>
                
                <?php if (count($session_notes) > 0): ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($session_notes as $row): ?>
                            <div class="p-3 bg-light rounded-3 border-start border-3 border-success">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge <?php echo ($row['visible_to_student']) ? 'bg-info text-dark' : 'bg-dark text-white'; ?> small">
                                        <?php echo ($row['visible_to_student']) ? 'Shared with Student' : 'Internal Confidential'; ?>
                                    </span>
                                    <span class="text-muted small" style="font-size:0.75rem;"><i class="bi bi-calendar-event me-1"></i> Recorded: <?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?></span>
                                </div>
                                <p class="text-dark small mb-0" style="white-space: pre-line; line-height: 1.5; font-style:italic;">
                                    "<?php echo h($row['notes']); ?>"
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small text-center py-4 mb-0">No notes logged for this student yet.</p>
                <?php endif; ?>
            </div>
        </div>
        
    </div>

    <!-- Active Chart Configuration Block for selected student details -->
    <?php if (count($chart_labels) > 0): ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const ctx = document.getElementById('studentMoodChart').getContext('2d');
        const labels = <?php echo json_encode($chart_labels); ?>;
        const values = <?php echo json_encode($chart_values); ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Mood Trend',
                    data: values,
                    borderColor: '#5B8C85',
                    backgroundColor: 'rgba(91, 140, 133, 0.15)',
                    borderWidth: 3,
                    tension: 0.3,
                    fill: true,
                    pointBackgroundColor: '#4A6FA5',
                    pointBorderColor: '#FFFFFF',
                    pointRadius: 5
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
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
                        }
                    }
                }
            }
        });
    });
    </script>
    <?php endif; ?>

<?php else: ?>
    <!-- LIST ASSIGNED STUDENTS VIEW -->
    <div class="row mb-4">
        <div class="col-12">
            <h1 class="fw-bold">My Assigned Students</h1>
            <p class="text-muted">Explore the profiles, daily moods, journal entries, and stress scores of your assigned students.</p>
        </div>
    </div>
    
    <div class="row">
        <div class="col-12">
            <div class="card card-glass border-0 p-4 shadow-sm">
                <h4 class="fw-bold mb-3"><i class="bi bi-people-fill text-primary me-2"></i> Student Directory</h4>
                
                <?php if (count($assigned_students) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Student Name</th>
                                    <th>Student ID</th>
                                    <th>Phone</th>
                                    <th>Latest Mood</th>
                                    <th>Stress Diagnosis</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assigned_students as $row): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-dark d-block"><?php echo h($row['full_name']); ?></span>
                                            <span class="text-muted small" style="font-size:0.75rem;"><?php echo h($row['email']); ?></span>
                                        </td>
                                        <td class="fw-semibold small text-muted"><?php echo h($row['student_id_number']); ?></td>
                                        <td class="small text-muted"><?php echo !empty($row['phone']) ? h($row['phone']) : '-'; ?></td>
                                        <td>
                                            <?php if ($row['latest_mood']): ?>
                                                <span class="badge badge-<?php echo h($row['latest_mood']); ?> text-capitalize px-2.5 py-1 rounded-pill">
                                                    <?php echo h($row['latest_mood']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small" style="font-style:italic;">Not logged</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($row['latest_stress']): ?>
                                                <span class="badge rounded-pill <?php echo ($row['latest_stress'] === 'High Stress') ? 'bg-danger' : (($row['latest_stress'] === 'Moderate Stress') ? 'bg-warning text-dark' : 'bg-success'); ?>">
                                                    <?php echo h($row['latest_stress']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted small" style="font-style:italic;">No tests taken</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="students.php?student_id=<?php echo $row['id']; ?>" class="btn btn-secondary-custom btn-sm">
                                                <i class="bi bi-eye-fill me-1"></i> View Records
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-5 text-center bg-light rounded-4">
                        <span class="display-3 text-muted"><i class="bi bi-people"></i></span>
                        <p class="text-muted mt-3 mb-0">No students currently assigned to your account by the administrator.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
