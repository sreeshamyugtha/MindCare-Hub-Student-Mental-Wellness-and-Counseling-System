<?php
// counselor/reports.php
$page_title = "Caseload Wellness Reports";
$active_nav = "reports"; // Highlight reports item in sidebar
require_once '../includes/auth.php';
require_once '../config/db.php';

// Assert Counselor Permissions
require_role('counselor');

// Safety profile load
if (!isset($_SESSION['counselor_id'])) {
    $stmt = $pdo->prepare("SELECT id FROM counselors WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $counselor = $stmt->fetch();
    if ($counselor) {
        $_SESSION['counselor_id'] = $counselor['id'];
    } else {
        redirect('../logout.php');
    }
}
$counselor_id = $_SESSION['counselor_id'];

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
    // Reports remain available even if schema migration is handled elsewhere.
}

// Fetch all assigned students for the selector dropdown
$studentsStmt = $pdo->prepare("SELECT id, full_name, student_id_number FROM students WHERE counselor_id = ? ORDER BY full_name ASC");
$studentsStmt->execute([$counselor_id]);
$assigned_students = $studentsStmt->fetchAll();

// Get request parameters
$selected_student_id = intval($_GET['student_id'] ?? 0);
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');

$student_details = null;
$error_message = '';

// Check if a student is selected and verify assignment
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
        $error_message = 'Unauthorized or student record not found.';
        $selected_student_id = 0;
    } else {
        // Date filters for queries
        $dateFilterSql = "";
        $dateParams = [];
        
        if (!empty($start_date) && !empty($end_date)) {
            $dateFilterSql = " AND %column% BETWEEN ? AND ? ";
            $dateParams = [$start_date, $end_date];
        }
        
        // 1. Fetch Mood logs and statistics
        // A. Total count
        $moodCountStmt = $pdo->prepare("SELECT COUNT(*) FROM moods WHERE student_id = ?" . str_replace('%column%', 'mood_date', $dateFilterSql));
        $moodCountStmt->execute(array_merge([$selected_student_id], $dateParams));
        $total_moods = $moodCountStmt->fetchColumn();
        
        // B. Mood history
        $moodsStmt = $pdo->prepare("SELECT * FROM moods WHERE student_id = ?" . str_replace('%column%', 'mood_date', $dateFilterSql) . " ORDER BY mood_date DESC");
        $moodsStmt->execute(array_merge([$selected_student_id], $dateParams));
        $mood_records = $moodsStmt->fetchAll();
        
        // C. Distribution for Chart.js
        $moodDistStmt = $pdo->prepare("
            SELECT mood_score, COUNT(*) as count 
            FROM moods 
            WHERE student_id = ?" . str_replace('%column%', 'mood_date', $dateFilterSql) . "
            GROUP BY mood_score
        ");
        $moodDistStmt->execute(array_merge([$selected_student_id], $dateParams));
        $mood_distribution = ['happy' => 0, 'neutral' => 0, 'sad' => 0, 'stressed' => 0];
        while ($row = $moodDistStmt->fetch()) {
            $mood_distribution[$row['mood_score']] = intval($row['count']);
        }
        
        // D. Mood progression for trend chart (last 10)
        $chartStmt = $pdo->prepare("
            SELECT mood_date, mood_score 
            FROM moods 
            WHERE student_id = ?" . str_replace('%column%', 'mood_date', $dateFilterSql) . " 
            ORDER BY mood_date DESC LIMIT 10
        ");
        $chartStmt->execute(array_merge([$selected_student_id], $dateParams));
        $chart_rows = array_reverse($chartStmt->fetchAll());
        
        $mood_chart_labels = [];
        $mood_chart_values = [];
        $mood_scores_map = ['sad' => 1, 'stressed' => 2, 'neutral' => 3, 'happy' => 4];
        foreach ($chart_rows as $row) {
            $mood_chart_labels[] = date('M d', strtotime($row['mood_date']));
            $mood_chart_values[] = $mood_scores_map[$row['mood_score']];
        }
        
        // 2. Fetch Stress Assessments
        $stressStatsStmt = $pdo->prepare("
            SELECT COUNT(*) as count, ROUND(AVG(score), 1) as avg_score 
            FROM assessments 
            WHERE student_id = ?" . str_replace('%column%', 'taken_at', $dateFilterSql)
        );
        $stressStatsStmt->execute(array_merge([$selected_student_id], $dateParams));
        $stress_stats = $stressStatsStmt->fetch();
        $total_assessments = intval($stress_stats['count'] ?? 0);
        $avg_stress_score = floatval($stress_stats['avg_score'] ?? 0);
        
        $assessmentsStmt = $pdo->prepare("SELECT * FROM assessments WHERE student_id = ?" . str_replace('%column%', 'taken_at', $dateFilterSql) . " ORDER BY taken_at DESC");
        $assessmentsStmt->execute(array_merge([$selected_student_id], $dateParams));
        $assessment_records = $assessmentsStmt->fetchAll();
        
        // Stress Trend Data (chronological order)
        $stress_chart_labels = [];
        $stress_chart_values = [];
        foreach (array_reverse($assessment_records) as $a) {
            $stress_chart_labels[] = date('M d', strtotime($a['taken_at']));
            $stress_chart_values[] = intval($a['score']);
        }
        
        // 3. Fetch Journals
        $journalCountStmt = $pdo->prepare("SELECT COUNT(*) FROM journals WHERE student_id = ? AND privacy = 'share_counselor'" . str_replace('%column%', 'created_at', $dateFilterSql));
        $journalCountStmt->execute(array_merge([$selected_student_id], $dateParams));
        $total_journals = $journalCountStmt->fetchColumn();
        
        $journalsStmt = $pdo->prepare("SELECT * FROM journals WHERE student_id = ? AND privacy = 'share_counselor'" . str_replace('%column%', 'created_at', $dateFilterSql) . " ORDER BY created_at DESC");
        $journalsStmt->execute(array_merge([$selected_student_id], $dateParams));
        $journal_records = $journalsStmt->fetchAll();
        
        // 4. Fetch Appointments
        $appStatsStmt = $pdo->prepare("
            SELECT COUNT(*) as total,
                   SUM(CASE WHEN appointment_date <= CURDATE() AND status = 'approved' THEN 1 ELSE 0 END) as completed
            FROM appointments 
            WHERE student_id = ?" . str_replace('%column%', 'appointment_date', $dateFilterSql)
        );
        $appStatsStmt->execute(array_merge([$selected_student_id], $dateParams));
        $app_stats = $appStatsStmt->fetch();
        $total_appointments = intval($app_stats['total'] ?? 0);
        $completed_sessions = intval($app_stats['completed'] ?? 0);
        
        $appStmt = $pdo->prepare("
            SELECT * FROM appointments 
            WHERE student_id = ?" . str_replace('%column%', 'appointment_date', $dateFilterSql) . "
            ORDER BY appointment_date DESC
        ");
        $appStmt->execute(array_merge([$selected_student_id], $dateParams));
        $appointment_records = $appStmt->fetchAll();
        
        // 5. Fetch Session Notes (confidential counseling logs)
        $notesStmt = $pdo->prepare("SELECT * FROM counseling_notes WHERE student_id = ? AND counselor_id = ?" . str_replace('%column%', 'created_at', $dateFilterSql) . " ORDER BY created_at DESC");
        $notesStmt->execute(array_merge([$selected_student_id, $counselor_id], $dateParams));
        $session_notes = $notesStmt->fetchAll();
    }
} else {
    // Fetch summary view of all assigned students
    $studentsStmt = $pdo->prepare("
        SELECT s.*, u.email, 
               (SELECT mood_score FROM moods WHERE student_id = s.id ORDER BY mood_date DESC LIMIT 1) as latest_mood,
               (SELECT stress_level FROM assessments WHERE student_id = s.id ORDER BY taken_at DESC LIMIT 1) as latest_stress,
               (SELECT COUNT(*) FROM moods WHERE student_id = s.id) as mood_count,
               (SELECT COUNT(*) FROM assessments WHERE student_id = s.id) as test_count
        FROM students s
        JOIN users u ON s.user_id = u.id
        WHERE s.counselor_id = ?
        ORDER BY s.full_name ASC
    ");
    $studentsStmt->execute([$counselor_id]);
    $caseload_students = $studentsStmt->fetchAll();
}

require_once '../includes/header.php';
?>

<!-- Print-Friendly CSS Stylesheet override -->
<style>
@media print {
    body {
        background-color: #FFFFFF !important;
        color: #000000 !important;
    }
    .sidebar, .sidebar-toggle, .filter-card, .btn-print-actions, .navbar-custom {
        display: none !important;
    }
    .main-content {
        margin-left: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    .card-glass {
        border: none !important;
        box-shadow: none !important;
        background: transparent !important;
    }
    .tab-content > .tab-pane {
        display: block !important;
        opacity: 1 !important;
        margin-bottom: 2rem !important;
        break-inside: avoid !important;
    }
    .nav-pills, .card-header-glass {
        display: none !important;
    }
}
</style>

<div class="row mb-4 fade-in-up btn-print-actions">
    <div class="col-sm-8">
        <h1 class="fw-bold">Counseling Reports Dashboard</h1>
        <p class="text-muted">Analyze wellness indicators, generate PDF reports, or review student timelines.</p>
    </div>
    <?php if ($student_details): ?>
        <div class="col-sm-4 text-sm-end d-flex align-items-center justify-content-sm-end gap-2 mt-2">
            <a href="reports.php" class="btn btn-outline-secondary" style="border-radius:8px;">
                <i class="bi bi-arrow-left"></i> Caseload Index
            </a>
            <button onclick="window.print()" class="btn btn-secondary-custom">
                <i class="bi bi-printer"></i> Print
            </button>
            <a href="../includes/generate_pdf.php?student_id=<?php echo $selected_student_id; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>" class="btn btn-primary-custom">
                <i class="bi bi-file-earmark-pdf"></i> Export PDF
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($error_message)): ?>
    <div class="alert alert-danger btn-print-actions"><?php echo h($error_message); ?></div>
<?php endif; ?>

<!-- Filters Card (Hides on print) -->
<div class="card card-glass border-0 p-4 mb-4 shadow-sm filter-card btn-print-actions">
    <h5 class="fw-bold mb-3 text-dark"><i class="bi bi-funnel-fill text-primary me-2"></i> Report Filter Configuration</h5>
    <form action="reports.php" method="GET" class="row g-3 align-items-end">
        <!-- Student Caseload Selector -->
        <div class="col-md-4">
            <label for="student_id" class="form-label fw-semibold small">Assigned Student</label>
            <select name="student_id" id="student_id" class="form-select" required>
                <option value="">-- Select a student profile --</option>
                <?php foreach ($assigned_students as $s): ?>
                    <option value="<?php echo $s['id']; ?>" <?php echo ($selected_student_id === $s['id']) ? 'selected' : ''; ?>>
                        <?php echo h($s['full_name']); ?> (<?php echo h($s['student_id_number']); ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <!-- Date Range: Start -->
        <div class="col-md-3">
            <label for="start_date" class="form-label fw-semibold small">Start Date</label>
            <input type="date" name="start_date" id="start_date" class="form-control" value="<?php echo h($start_date); ?>">
        </div>
        
        <!-- Date Range: End -->
        <div class="col-md-3">
            <label for="end_date" class="form-label fw-semibold small">End Date</label>
            <input type="date" name="end_date" id="end_date" class="form-control" value="<?php echo h($end_date); ?>">
        </div>
        
        <!-- Action Buttons -->
        <div class="col-md-2 d-grid gap-2">
            <button type="submit" class="btn btn-primary-custom"><i class="bi bi-search"></i> Apply Filters</button>
        </div>
    </form>
    
    <!-- Quick Presets -->
    <div class="d-flex flex-wrap gap-2 mt-3 pt-3 border-top">
        <span class="text-muted small align-self-center me-2">Quick Date Presets:</span>
        <button type="button" class="btn btn-xs btn-light text-dark border-secondary small py-0.5 px-2" onclick="setPresetDates('month')">This Month</button>
        <button type="button" class="btn btn-xs btn-light text-dark border-secondary small py-0.5 px-2" onclick="setPresetDates('last_month')">Last Month</button>
        <button type="button" class="btn btn-xs btn-light text-dark border-secondary small py-0.5 px-2" onclick="setPresetDates('year')">This Year</button>
        <button type="button" class="btn btn-xs btn-light text-dark border-secondary small py-0.5 px-2" onclick="setPresetDates('all')">All Time</button>
    </div>
</div>

<?php if ($student_details): ?>
    <!-- ---------------------------------------------------- -->
    <!-- DETAILED WELLNESS REPORT VIEW FOR SELECTED STUDENT -->
    <!-- ---------------------------------------------------- -->
    
    <!-- Header Summary Details Card -->
    <div class="card card-glass border-0 p-4 mb-4 shadow-sm">
        <div class="row align-items-center">
            <div class="col-md-7">
                <span class="badge bg-secondary mb-2">Caseload Wellness File</span>
                <h3 class="fw-bold text-dark mb-1"><?php echo h($student_details['full_name']); ?></h3>
                <p class="text-muted mb-0 small">
                    Student ID: <b><?php echo h($student_details['student_id_number']); ?></b> | 
                    Email: <b><?php echo h($student_details['email']); ?></b>
                </p>
            </div>
            <div class="col-md-5 text-md-end mt-3 mt-md-0">
                <div class="d-inline-block text-start p-2 bg-light rounded shadow-sm">
                    <span class="text-muted small d-block" style="font-size:0.75rem;">Timeline Range</span>
                    <span class="fw-semibold text-dark small">
                        <?php 
                        if (!empty($start_date) && !empty($end_date)) {
                            echo date('M d, Y', strtotime($start_date)) . " - " . date('M d, Y', strtotime($end_date));
                        } else {
                            echo "Complete History Logs";
                        }
                        ?>
                    </span>
                </div>
            </div>
        </div>
        
        <!-- Summary Counter Stats Indicators -->
        <div class="row g-3 mt-3 pt-3 border-top">
            <div class="col-6 col-md">
                <div class="p-2.5 bg-light rounded text-center border-start border-3 border-success shadow-sm">
                    <span class="text-muted small d-block" style="font-size:0.7rem;">Mood Logs</span>
                    <h5 class="fw-bold text-success mb-0"><?php echo h($total_moods); ?></h5>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="p-2.5 bg-light rounded text-center border-start border-3 border-danger shadow-sm">
                    <span class="text-muted small d-block" style="font-size:0.7rem;">Avg Stress Score</span>
                    <h5 class="fw-bold text-danger mb-0"><?php echo h($avg_stress_score); ?><span class="small text-muted" style="font-size:0.6rem;">/40</span></h5>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="p-2.5 bg-light rounded text-center border-start border-3 border-warning shadow-sm">
                    <span class="text-muted small d-block" style="font-size:0.7rem;">Reflections Journal</span>
                    <h5 class="fw-bold text-warning mb-0"><?php echo h($total_journals); ?></h5>
                </div>
            </div>
            <div class="col-6 col-md">
                <div class="p-2.5 bg-light rounded text-center border-start border-3 border-primary shadow-sm">
                    <span class="text-muted small d-block" style="font-size:0.7rem;">Completed Sessions</span>
                    <h5 class="fw-bold text-primary mb-0"><?php echo h($completed_sessions); ?></h5>
                </div>
            </div>
            <div class="col-12 col-md">
                <div class="p-2.5 bg-light rounded text-center border-start border-3 border-dark shadow-sm">
                    <span class="text-muted small d-block" style="font-size:0.7rem;">counseling Notes</span>
                    <h5 class="fw-bold text-dark mb-0"><?php echo count($session_notes); ?></h5>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Tabbed Panel Navigation for Web View -->
    <div class="card card-glass border-0 p-3 mb-4 shadow-sm btn-print-actions">
        <ul class="nav nav-pills nav-fill" id="reportTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="charts-tab" data-bs-toggle="tab" data-bs-target="#charts-pane" type="button" role="tab">
                    <i class="bi bi-graph-up-arrow me-1"></i> Analytics Charts
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="mood-tab" data-bs-toggle="tab" data-bs-target="#mood-pane" type="button" role="tab">
                    <i class="bi bi-emoji-smile-fill me-1"></i> Mood History (<?php echo h($total_moods); ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="stress-tab" data-bs-toggle="tab" data-bs-target="#stress-pane" type="button" role="tab">
                    <i class="bi bi-clipboard2-pulse-fill me-1"></i> Stress Tests (<?php echo h($total_assessments); ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="journal-tab" data-bs-toggle="tab" data-bs-target="#journal-pane" type="button" role="tab">
                    <i class="bi bi-journal-bookmark-fill me-1"></i> Journals (<?php echo h($total_journals); ?>)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes-pane" type="button" role="tab">
                    <i class="bi bi-journal-text me-1"></i> counselor Notes (<?php echo count($session_notes); ?>)
                </button>
            </li>
        </ul>
    </div>
    
    <!-- Tab Content Blocks -->
    <div class="tab-content" id="reportTabsContent">
        
        <!-- Tab 1: Charts -->
        <div class="tab-pane fade show active" id="charts-pane" role="tabpanel">
            <div class="row g-4">
                <!-- Mood Trend line chart -->
                <div class="col-lg-6">
                    <div class="card card-glass border-0 p-4 shadow-sm" style="min-height: 340px;">
                        <h5 class="fw-bold mb-3"><i class="bi bi-activity text-primary me-2"></i> Emotional Mood Progression</h5>
                        <?php if (count($mood_chart_labels) > 0): ?>
                            <div style="position: relative; height: 250px;">
                                <canvas id="reportMoodTrendChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center h-100 text-muted"><p class="my-5">No mood entries logged in this timeline range.</p></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Mood distribution doughnut chart -->
                <div class="col-lg-6">
                    <div class="card card-glass border-0 p-4 shadow-sm" style="min-height: 340px;">
                        <h5 class="fw-bold mb-3"><i class="bi bi-pie-chart text-success me-2"></i> Mood Distribution</h5>
                        <?php if ($total_moods > 0): ?>
                            <div style="position: relative; height: 250px;">
                                <canvas id="reportMoodDistChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center h-100 text-muted"><p class="my-5">No mood metrics collected.</p></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Stress level diagnostic line chart -->
                <div class="col-12">
                    <div class="card card-glass border-0 p-4 shadow-sm" style="min-height: 340px;">
                        <h5 class="fw-bold mb-3"><i class="bi bi-speedometer2 text-danger me-2"></i> Perceived Stress Tracker (PSS-10)</h5>
                        <?php if (count($stress_chart_labels) > 0): ?>
                            <div style="position: relative; height: 250px;">
                                <canvas id="reportStressTrendChart"></canvas>
                            </div>
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center h-100 text-muted"><p class="my-5">No stress diagnostic tests completed.</p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Tab 2: Mood Logs History -->
        <div class="tab-pane fade" id="mood-pane" role="tabpanel">
            <div class="card card-glass border-0 p-4 shadow-sm">
                <h5 class="fw-bold mb-3"><i class="bi bi-calendar-event text-secondary me-2"></i> Mood Journal Logs</h5>
                <?php if (count($mood_records) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Mood State</th>
                                    <th>counselor Notes / Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($mood_records as $row): ?>
                                    <tr>
                                        <td class="fw-semibold small"><?php echo date('M d, Y', strtotime($row['mood_date'])); ?></td>
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
                    <p class="text-muted small text-center py-4 mb-0">No mood tracking details found.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tab 3: Stress Test diagnostic -->
        <div class="tab-pane fade" id="stress-pane" role="tabpanel">
            <div class="card card-glass border-0 p-4 shadow-sm">
                <h5 class="fw-bold mb-3"><i class="bi bi-clipboard2-pulse-fill text-primary me-2"></i> Completed PSS-10 Assessments</h5>
                <?php if (count($assessment_records) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Date Completed</th>
                                    <th class="text-center">Total Score</th>
                                    <th class="text-center">Severity Diagnosis</th>
                                    <th>Details</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($assessment_records as $row): ?>
                                    <tr>
                                        <td class="fw-semibold small"><?php echo date('M d, Y h:i A', strtotime($row['taken_at'])); ?></td>
                                        <td class="text-center fw-bold small"><?php echo h($row['score']); ?>/40</td>
                                        <td class="text-center">
                                            <span class="badge rounded-pill <?php echo ($row['stress_level'] === 'High Stress') ? 'bg-danger' : (($row['stress_level'] === 'Moderate Stress') ? 'bg-warning text-dark' : 'bg-success'); ?>">
                                                <?php echo h($row['stress_level']); ?>
                                            </span>
                                        </td>
                                        <td class="small text-muted">
                                            Q1-Q10 Raw values: [<?php echo "{$row['q1']}, {$row['q2']}, {$row['q3']}, {$row['q4']}, {$row['q5']}, {$row['q6']}, {$row['q7']}, {$row['q8']}, {$row['q9']}, {$row['q10']}"; ?>]
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small text-center py-4 mb-0">No stress questionnaires completed.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tab 4: Student Journals -->
        <div class="tab-pane fade" id="journal-pane" role="tabpanel">
            <div class="card card-glass border-0 p-4 shadow-sm">
                <h5 class="fw-bold mb-3"><i class="bi bi-journals text-warning me-2"></i> Private Reflections Diary</h5>
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
                    <p class="text-muted small text-center py-4 mb-0">No journal logs recorded.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Tab 5: Counselor Progress Notes -->
        <div class="tab-pane fade" id="notes-pane" role="tabpanel">
            <div class="card card-glass border-0 p-4 shadow-sm">
                <h5 class="fw-bold mb-3"><i class="bi bi-journal-text text-success me-2"></i> Case Progress Notes</h5>
                <?php if (count($session_notes) > 0): ?>
                    <div class="d-flex flex-column gap-3">
                        <?php foreach ($session_notes as $row): ?>
                            <div class="p-3 bg-light rounded-3 border-start border-3 border-success">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <span class="badge <?php echo ($row['visible_to_student']) ? 'bg-info text-dark' : 'bg-dark text-white'; ?> small">
                                        <?php echo ($row['visible_to_student']) ? 'Shared with Student' : 'Internal Confidential'; ?>
                                    </span>
                                    <span class="text-muted small" style="font-size:0.75rem;"><i class="bi-calendar-event me-1"></i> Recorded: <?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?></span>
                                </div>
                                <p class="text-dark small mb-0" style="white-space: pre-line; line-height: 1.5; font-style:italic;">
                                    "<?php echo h($row['notes']); ?>"
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted small text-center py-4 mb-0">No progress notes written.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Active Chart Configuration Block for Student Web Reports -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // A. Mood Trend Chart
        <?php if (count($mood_chart_labels) > 0): ?>
        const moodTrendCtx = document.getElementById('reportMoodTrendChart').getContext('2d');
        new Chart(moodTrendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($mood_chart_labels); ?>,
                datasets: [{
                    label: 'Emotional Level',
                    data: <?php echo json_encode($mood_chart_values); ?>,
                    borderColor: '#4A6FA5',
                    backgroundColor: 'rgba(74, 111, 165, 0.12)',
                    borderWidth: 3,
                    tension: 0.3,
                    fill: true,
                    pointRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        min: 1,
                        max: 4,
                        ticks: {
                            stepSize: 1,
                            callback: function(value) {
                                const tickMap = {1: 'Sad 😢', 2: 'Stressed 😫', 3: 'Neutral 😐', 4: 'Happy 😊'};
                                return tickMap[value];
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>
        
        // B. Mood Distribution Doughnut
        <?php if ($total_moods > 0): ?>
        const moodDistCtx = document.getElementById('reportMoodDistChart').getContext('2d');
        const moodDist = <?php echo json_encode($mood_distribution); ?>;
        new Chart(moodDistCtx, {
            type: 'doughnut',
            data: {
                labels: ['Happy 😊', 'Neutral 😐', 'Sad 😢', 'Stressed 😫'],
                datasets: [{
                    data: [moodDist.happy, moodDist.neutral, moodDist.sad, moodDist.stressed],
                    backgroundColor: ['#6A994E', '#F4A261', '#4A6FA5', '#E76F51'],
                    borderColor: '#ffffff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' }
                }
            }
        });
        <?php endif; ?>
        
        // C. Stress Level Progression Chart
        <?php if (count($stress_chart_labels) > 0): ?>
        const stressTrendCtx = document.getElementById('reportStressTrendChart').getContext('2d');
        new Chart(stressTrendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($stress_chart_labels); ?>,
                datasets: [{
                    label: 'PSS-10 Stress Score',
                    data: <?php echo json_encode($stress_chart_values); ?>,
                    borderColor: '#E76F51',
                    backgroundColor: 'rgba(231, 111, 81, 0.08)',
                    borderWidth: 3,
                    tension: 0.25,
                    fill: true,
                    pointRadius: 5,
                    pointBackgroundColor: '#E76F51'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        min: 0,
                        max: 40,
                        ticks: { stepSize: 10 },
                        grid: { color: 'rgba(0,0,0,0.05)' }
                    }
                }
            }
        });
        <?php endif; ?>
    });
    </script>

<?php else: ?>
    <!-- ---------------------------------------------------- -->
    <!-- DEFAULT LIST VIEW - DIRECTORY OF ASSIGNED STUDENTS -->
    <!-- ---------------------------------------------------- -->
    <div class="row">
        <div class="col-12">
            <div class="card card-glass border-0 p-4 shadow-sm">
                <h4 class="fw-bold mb-3"><i class="bi bi-directory text-primary me-2"></i> Case Directory</h4>
                <p class="text-muted small">Select any student below to load their clinical wellness report charts, history files, and export controls.</p>
                
                <?php if (count($caseload_students) > 0): ?>
                    <div class="table-responsive mt-3">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Student Profile</th>
                                    <th class="text-center">Mood Tracking Count</th>
                                    <th class="text-center">Stress Tests Count</th>
                                    <th>Latest Mood Log</th>
                                    <th>Stress Status</th>
                                    <th class="text-end">Report Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($caseload_students as $row): ?>
                                    <tr>
                                        <td>
                                            <span class="fw-bold text-dark d-block"><?php echo h($row['full_name']); ?></span>
                                            <span class="text-muted small" style="font-size:0.75rem;"><?php echo h($row['email']); ?></span>
                                        </td>
                                        <td class="text-center fw-semibold small text-muted"><?php echo h($row['mood_count']); ?></td>
                                        <td class="text-center fw-semibold small text-muted"><?php echo h($row['test_count']); ?></td>
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
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-1">
                                                <a href="reports.php?student_id=<?php echo $row['id']; ?>" class="btn btn-secondary-custom btn-sm">
                                                    <i class="bi bi-activity me-1"></i> Interactive Report
                                                </a>
                                                <a href="../includes/generate_pdf.php?student_id=<?php echo $row['id']; ?>" class="btn btn-primary-custom btn-sm" title="Quick PDF Download">
                                                    <i class="bi bi-file-earmark-pdf"></i> PDF
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="p-5 text-center bg-light rounded-4">
                        <span class="display-3 text-muted"><i class="bi bi-people"></i></span>
                        <p class="text-muted mt-3 mb-0">No students are currently mapped to your counselor account.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Javascript Helper to set Date Filters Presets in UI -->
<script>
function setPresetDates(preset) {
    const startInput = document.getElementById('start_date');
    const endInput = document.getElementById('end_date');
    const now = new Date();
    
    if (preset === 'month') {
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
        startInput.value = formatDate(firstDay);
        endInput.value = formatDate(lastDay);
    } else if (preset === 'last_month') {
        const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        const lastDay = new Date(now.getFullYear(), now.getMonth(), 0);
        startInput.value = formatDate(firstDay);
        endInput.value = formatDate(lastDay);
    } else if (preset === 'year') {
        const firstDay = new Date(now.getFullYear(), 0, 1);
        const lastDay = new Date(now.getFullYear(), 11, 31);
        startInput.value = formatDate(firstDay);
        endInput.value = formatDate(lastDay);
    } else {
        startInput.value = '';
        endInput.value = '';
    }
}

function formatDate(date) {
    const d = new Date(date);
    let month = '' + (d.getMonth() + 1);
    let day = '' + d.getDate();
    const year = d.getFullYear();

    if (month.length < 2) month = '0' + month;
    if (day.length < 2) day = '0' + day;

    return [year, month, day].join('-');
}
</script>

<?php require_once '../includes/footer.php'; ?>
