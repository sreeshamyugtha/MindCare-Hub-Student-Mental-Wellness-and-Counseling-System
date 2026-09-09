<?php
// includes/generate_pdf.php
ob_start(); // Start output buffering to prevent warning/notice printouts from corrupting headers
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/WellnessReportPDF.php';

// 1. Enforce Authentication (Counselor or Admin only)
require_login();
if (!in_array($_SESSION['role'], ['admin', 'counselor'])) {
    set_flash('danger', 'Unauthorized access to report generation.');
    redirect('../index.php');
}

// 2. Fetch parameters
$student_id = intval($_GET['student_id'] ?? 0);
$start_date = trim($_GET['start_date'] ?? '');
$end_date = trim($_GET['end_date'] ?? '');

if ($student_id <= 0) {
    set_flash('danger', 'Invalid student ID specified.');
    if ($_SESSION['role'] === 'admin') {
        redirect('../admin/dashboard.php');
    } else {
        redirect('../counselor/dashboard.php');
    }
}

// Get counselor ID for access controls
$counselor_id = null;
if ($_SESSION['role'] === 'counselor') {
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
}

// 3. Security Access Control Check
$studentQuery = "
    SELECT s.*, u.email, u.username, c.full_name AS counselor_name 
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN counselors c ON s.counselor_id = c.id
    WHERE s.id = ?
";
$studentStmt = $pdo->prepare($studentQuery);
$studentStmt->execute([$student_id]);
$student = $studentStmt->fetch();

if (!$student) {
    set_flash('danger', 'Student record not found.');
    redirect('../' . $_SESSION['role'] . '/dashboard.php');
}

// Counselor can only view reports for their assigned students
if ($_SESSION['role'] === 'counselor' && $student['counselor_id'] != $counselor_id) {
    set_flash('danger', 'Access denied. You can only generate reports for your assigned students.');
    redirect('../counselor/dashboard.php');
}

// Helper to convert UTF-8 text safely into FPDF ISO-8859-1 encoding
function cleanText($text) {
    $text = $text ?? '';
    // Strip emojis or map to descriptive text
    $emoji_map = [
        '😊' => '(Happy)',
        '😐' => '(Neutral)',
        '😢' => '(Sad)',
        '😫' => '(Stressed)',
        '•' => '-',
        '’' => "'",
        '‘' => "'",
        '“' => '"',
        '”' => '"',
        '—' => '-',
        '–' => '-'
    ];
    $text = str_replace(array_keys($emoji_map), array_values($emoji_map), $text);
    if (function_exists('iconv')) {
        $converted = @iconv('UTF-8', 'windows-1252//TRANSLIT', $text);
        if ($converted !== false) return $converted;
    }
    return utf8_decode($text);
}

// 4. Fetch Wellness Data with Optional Date Range filters
$dateFilterSql = "";
$dateParams = [];

if (!empty($start_date) && !empty($end_date)) {
    $dateFilterSql = " AND %column% BETWEEN ? AND ? ";
    $dateParams = [$start_date, $end_date];
}

// A. Mood Tracking Summary
$moodCountQuery = "SELECT COUNT(*) FROM moods WHERE student_id = ?" . str_replace('%column%', 'mood_date', $dateFilterSql);
$moodCountStmt = $pdo->prepare($moodCountQuery);
$moodCountStmt->execute(array_merge([$student_id], $dateParams));
$total_mood_entries = $moodCountStmt->fetchColumn();

// Mood distribution
$moodDistQuery = "
    SELECT mood_score, COUNT(*) as count 
    FROM moods 
    WHERE student_id = ?" . str_replace('%column%', 'mood_date', $dateFilterSql) . "
    GROUP BY mood_score
";
$moodDistStmt = $pdo->prepare($moodDistQuery);
$moodDistStmt->execute(array_merge([$student_id], $dateParams));
$mood_distribution = ['happy' => 0, 'neutral' => 0, 'sad' => 0, 'stressed' => 0];
while ($row = $moodDistStmt->fetch()) {
    $mood_distribution[$row['mood_score']] = intval($row['count']);
}

// Mood history (latest 10 for table & chart)
$moodHistoryQuery = "
    SELECT mood_date, mood_score, notes 
    FROM moods 
    WHERE student_id = ?" . str_replace('%column%', 'mood_date', $dateFilterSql) . "
    ORDER BY mood_date DESC 
    LIMIT 10
";
$moodHistoryStmt = $pdo->prepare($moodHistoryQuery);
$moodHistoryStmt->execute(array_merge([$student_id], $dateParams));
$mood_history = $moodHistoryStmt->fetchAll();

// Mood trend arrays for drawing vector chart
$mood_chart_labels = [];
$mood_chart_data = [];
$mood_scores_map = ['sad' => 1, 'stressed' => 2, 'neutral' => 3, 'happy' => 4];
foreach (array_reverse($mood_history) as $m) {
    $mood_chart_labels[] = date('M d', strtotime($m['mood_date']));
    $mood_chart_data[] = $mood_scores_map[$m['mood_score']];
}

// B. Stress Assessment Summary
$stressStatsQuery = "
    SELECT COUNT(*) as count, ROUND(AVG(score), 1) as avg_score 
    FROM assessments 
    WHERE student_id = ?" . str_replace('%column%', 'taken_at', $dateFilterSql);
$stressStatsStmt = $pdo->prepare($stressStatsQuery);
$stressStatsStmt->execute(array_merge([$student_id], $dateParams));
$stress_stats = $stressStatsStmt->fetch();
$total_assessments = intval($stress_stats['count'] ?? 0);
$avg_stress_score = floatval($stress_stats['avg_score'] ?? 0);

// Individual Assessments
$assessmentsQuery = "
    SELECT score, stress_level, taken_at 
    FROM assessments 
    WHERE student_id = ?" . str_replace('%column%', 'taken_at', $dateFilterSql) . "
    ORDER BY taken_at ASC
";
$assessmentsStmt = $pdo->prepare($assessmentsQuery);
$assessmentsStmt->execute(array_merge([$student_id], $dateParams));
$assessments = $assessmentsStmt->fetchAll();

$stress_chart_labels = [];
$stress_chart_data = [];
foreach ($assessments as $a) {
    $stress_chart_labels[] = date('M d', strtotime($a['taken_at']));
    $stress_chart_data[] = $a['score'];
}

// C. Journal Summary
$journalCountQuery = "SELECT COUNT(*) FROM journals WHERE student_id = ?" . str_replace('%column%', 'created_at', $dateFilterSql);
$journalCountStmt = $pdo->prepare($journalCountQuery);
$journalCountStmt->execute(array_merge([$student_id], $dateParams));
$total_journals = $journalCountStmt->fetchColumn();

// Latest journal reflections
$journalsQuery = "
    SELECT title, content, created_at 
    FROM journals 
    WHERE student_id = ?" . str_replace('%column%', 'created_at', $dateFilterSql) . "
    ORDER BY created_at DESC 
    LIMIT 5
";
$journalsStmt = $pdo->prepare($journalsQuery);
$journalsStmt->execute(array_merge([$student_id], $dateParams));
$journals = $journalsStmt->fetchAll();

// D. Appointment Summary
$appointmentStatsQuery = "
    SELECT COUNT(*) as total,
           SUM(CASE WHEN appointment_date <= CURDATE() AND status = 'approved' THEN 1 ELSE 0 END) as completed
    FROM appointments 
    WHERE student_id = ?" . str_replace('%column%', 'appointment_date', $dateFilterSql);
$appointmentStatsStmt = $pdo->prepare($appointmentStatsQuery);
$appointmentStatsStmt->execute(array_merge([$student_id], $dateParams));
$app_stats = $appointmentStatsStmt->fetch();
$total_appointments = intval($app_stats['total'] ?? 0);
$completed_appointments = intval($app_stats['completed'] ?? 0);

// Appointment List
$appointmentsQuery = "
    SELECT appointment_date, appointment_time, reason, status 
    FROM appointments 
    WHERE student_id = ?" . str_replace('%column%', 'appointment_date', $dateFilterSql) . "
    ORDER BY appointment_date DESC 
    LIMIT 6
";
$appointmentsStmt = $pdo->prepare($appointmentsQuery);
$appointmentsStmt->execute(array_merge([$student_id], $dateParams));
$appointments_list = $appointmentsStmt->fetchAll();

// E. Counseling Notes Summary
$notesQuery = "
    SELECT cn.notes, cn.created_at, cn.visible_to_student, c.full_name AS counselor_name 
    FROM counseling_notes cn
    JOIN counselors c ON cn.counselor_id = c.id
    WHERE cn.student_id = ?" . str_replace('%column%', 'cn.created_at', $dateFilterSql) . "
    ORDER BY cn.created_at DESC
";
$notesStmt = $pdo->prepare($notesQuery);
$notesStmt->execute(array_merge([$student_id], $dateParams));
$session_notes = $notesStmt->fetchAll();

// 5. Initialize PDF Layout Builder
$pdf = new WellnessReportPDF('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->setReportMeta($student['full_name'], $student['student_id_number'], $student['email'], $student['counselor_name'], date('M d, Y h:i A'));
$pdf->AddPage();

// Report Cover / Title Area
$pdf->SetTextColor(44, 62, 80);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 5, 'STUDENT WELLNESS DOSSIER', 0, 1, 'C');
$pdf->Ln(2);

// Filter notice if applied
if (!empty($start_date) && !empty($end_date)) {
    $pdf->SetFont('Arial', 'I', 8.5);
    $pdf->SetTextColor(110, 120, 130);
    $pdf->Cell(0, 4, 'Report filtered for date range: ' . date('M d, Y', strtotime($start_date)) . ' to ' . date('M d, Y', strtotime($end_date)), 0, 1, 'C');
    $pdf->Ln(2);
}

// Student Info Block
$pdf->renderSectionHeader('Student profile details');
$pdf->renderStudentInfoCard();

// Section 1: Mood Diagnostics
$pdf->renderSectionHeader('Mood & Emotional tracking logs');

$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(45, 5, 'Total Mood Entries Logged:', 0, 0, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(20, 5, $total_mood_entries, 0, 1, 'L');
$pdf->Ln(2);

// Draw Mood Charts side-by-side or stacked
$chartY = $pdf->GetY() + 6;
// Vector Mood Trend Chart (Black line trace)
$pdf->DrawLineChart(15, $chartY, 92, 42, '10-DAY MOOD EMOTIONAL TREND', $mood_chart_labels, $mood_chart_data, 1, 4, [1 => 'Sad', 2 => 'Stressed', 3 => 'Neutral', 4 => 'Happy'], [0, 0, 0]);
// Vector Mood Distribution Bar Chart
$pdf->DrawMoodDistributionBarChart(112, $chartY, 83, 42, $mood_distribution);

$pdf->SetY($chartY + 48);

// Mood History Table - Black & White
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 5, 'Recent Mood Entries Table (Max 5 shown)', 0, 1, 'L');
$pdf->Ln(1);

// Table Header
$pdf->SetFillColor(230, 230, 230);
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(35, 6, 'Date', 1, 0, 'C', true);
$pdf->Cell(30, 6, 'Mood Score', 1, 0, 'C', true);
$pdf->Cell(115, 6, 'Description Notes', 1, 1, 'L', true);

// Table Body
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetDrawColor(0, 0, 0);
$displayed_moods = array_slice($mood_history, 0, 5);

if (count($displayed_moods) > 0) {
    foreach ($displayed_moods as $m) {
        $pdf->ensureSpace(6);
        $pdf->Cell(35, 6, cleanText(date('M d, Y', strtotime($m['mood_date']))), 1, 0, 'C');
        
        $score = $m['mood_score'];
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(30, 6, cleanText(ucfirst($score)), 1, 0, 'C');
        $pdf->SetTextColor(0, 0, 0);
        
        $notes = !empty($m['notes']) ? $m['notes'] : 'No notes written.';
        $pdf->Cell(115, 6, cleanText($notes), 1, 1, 'L');
    }
} else {
    $pdf->Cell(180, 6, 'No mood records logged.', 1, 1, 'C');
}


// Section 2: Stress Assessments (PSS-10)
$pdf->ensureSpace(70);
$pdf->renderSectionHeader('Stress assessment diagnostics (PSS-10)');

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(52, 5, 'Total Assessments Completed:', 0, 0, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(20, 5, $total_assessments, 0, 0, 'L');

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(40, 5, 'Average Stress Score:', 0, 0, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(20, 5, $avg_stress_score . ' / 40', 0, 1, 'L');
$pdf->Ln(2);

$assessY = $pdf->GetY() + 6;
// Vector Stress Scores Chart (Black line trace)
$pdf->DrawLineChart(15, $assessY, 180, 40, 'PSS-10 SCORE TRACKER OVER TIME', $stress_chart_labels, $stress_chart_data, 0, 40, [0 => '0', 10 => '10 (Low)', 20 => '20 (Mod)', 30 => '30 (High)', 40 => '40'], [0, 0, 0]);

$pdf->SetY($assessY + 46);

// Assessments Table - Black & White
$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(0, 5, 'Stress Tests Results Table', 0, 1, 'L');
$pdf->Ln(1);

$pdf->SetFillColor(230, 230, 230);
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(45, 6, 'Date Taken', 1, 0, 'C', true);
$pdf->Cell(35, 6, 'Stress Score', 1, 0, 'C', true);
$pdf->Cell(50, 6, 'Diagnostic Level', 1, 0, 'C', true);
$pdf->Cell(50, 6, 'Status', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetDrawColor(0, 0, 0);
if (count($assessments) > 0) {
    foreach (array_reverse($assessments) as $a) {
        $pdf->ensureSpace(6);
        $pdf->Cell(45, 6, cleanText(date('M d, Y h:i A', strtotime($a['taken_at']))), 1, 0, 'C');
        $pdf->Cell(35, 6, $a['score'] . ' / 40', 1, 0, 'C');
        
        $level = $a['stress_level'];
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(50, 6, cleanText($level), 1, 0, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(50, 6, 'Official Logged', 1, 1, 'C');
    }
} else {
    $pdf->Cell(180, 6, 'No stress assessments completed.', 1, 1, 'C');
}

// Section 3: Journals
$pdf->ensureSpace(50);
$pdf->renderSectionHeader('Student journal reflections logs');

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(45, 5, 'Total Journal Reflections:', 0, 0, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(20, 5, $total_journals, 0, 1, 'L');
$pdf->Ln(2);

if (count($journals) > 0) {
    foreach ($journals as $j) {
        $pdf->ensureSpace(20);
        
        // Border left representation using black bar
        $currentY = $pdf->GetY();
        $pdf->SetFillColor(245, 245, 245);
        $pdf->Rect(15, $currentY, 180, 15, 'F');
        $pdf->SetFillColor(0, 0, 0); // black bar accent
        $pdf->Rect(15, $currentY, 1.5, 15, 'F');
        
        $pdf->SetXY(18, $currentY + 1.5);
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(100, 4, cleanText($j['title']), 0, 0, 'L');
        
        $pdf->SetFont('Arial', 'I', 7.5);
        $pdf->SetTextColor(80, 80, 80);
        $pdf->Cell(72, 4, 'Written: ' . date('M d, Y h:i A', strtotime($j['created_at'])), 0, 1, 'R');
        
        $pdf->SetXY(18, $currentY + 6);
        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        // Preview content
        $preview = strlen($j['content']) > 140 ? substr($j['content'], 0, 137) . '...' : $j['content'];
        $pdf->MultiCell(174, 4, cleanText($preview), 0, 'L');
        
        $pdf->SetY($currentY + 17);
    }
} else {
    $pdf->SetFont('Arial', 'I', 8.5);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 6, 'No journal logs written by this student in the filtered range.', 0, 1, 'L');
}

// Section 4: Appointments
$pdf->ensureSpace(50);
$pdf->renderSectionHeader('Counseling appointments timeline');

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(0, 0, 0);
$pdf->Cell(45, 5, 'Total Scheduled Bookings:', 0, 0, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(20, 5, $total_appointments, 0, 0, 'L');

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(45, 5, 'Completed Sessions Count:', 0, 0, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(20, 5, $completed_appointments, 0, 1, 'L');
$pdf->Ln(2);

// Appointment Table - Black & White
$pdf->SetFillColor(230, 230, 230);
$pdf->SetDrawColor(0, 0, 0);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(45, 6, 'Session Date & Time', 1, 0, 'C', true);
$pdf->Cell(30, 6, 'Booking Status', 1, 0, 'C', true);
$pdf->Cell(105, 6, 'Reason / Consultation Remarks', 1, 1, 'L', true);

$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0, 0, 0);
$pdf->SetDrawColor(0, 0, 0);
if (count($appointments_list) > 0) {
    foreach ($appointments_list as $app) {
        $pdf->ensureSpace(6);
        $dateTimeStr = date('M d, Y', strtotime($app['appointment_date'])) . ' ' . date('h:i A', strtotime($app['appointment_time']));
        $pdf->Cell(45, 6, cleanText($dateTimeStr), 1, 0, 'C');
        
        $status = $app['status'];
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(30, 6, cleanText(ucfirst($status)), 1, 0, 'C');
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(105, 6, cleanText($app['reason']), 1, 1, 'L');
    }
} else {
    $pdf->Cell(180, 6, 'No appointments scheduled.', 1, 1, 'C');
}

// Section 5: Counseling Notes
$pdf->ensureSpace(50);
$pdf->renderSectionHeader('Advising & Counseling notes logs');

if (count($session_notes) > 0) {
    foreach ($session_notes as $note) {
        $pdf->ensureSpace(24);
        
        $currY = $pdf->GetY();
        $pdf->SetFillColor(245, 248, 245);
        $pdf->Rect(15, $currY, 180, 18, 'F');
        $pdf->SetFillColor(56, 161, 105); // green bar for official note
        $pdf->Rect(15, $currY, 1.5, 18, 'F');
        
        $pdf->SetXY(18, $currY + 1.5);
        $pdf->SetFont('Arial', 'B', 8.5);
        $pdf->SetTextColor(44, 62, 80);
        $pdf->Cell(100, 4, 'Counselor: ' . cleanText($note['counselor_name']), 0, 0, 'L');
        
        $pdf->SetFont('Arial', 'I', 7.5);
        $pdf->SetTextColor(110, 120, 130);
        $pdf->Cell(72, 4, 'Date: ' . date('M d, Y h:i A', strtotime($note['created_at'])) . ' | ' . ($note['visible_to_student'] ? 'Shared' : 'Internal Confidential'), 0, 1, 'R');
        
        $pdf->SetXY(18, $currY + 6);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(60, 70, 80);
        $preview = strlen($note['notes']) > 175 ? substr($note['notes'], 0, 172) . '...' : $note['notes'];
        $pdf->MultiCell(174, 4, '"' . cleanText($preview) . '"', 0, 'L');
        
        $pdf->SetY($currY + 20);
    }
} else {
    $pdf->SetFont('Arial', 'I', 8.5);
    $pdf->Cell(0, 6, 'No session progress notes logged in the system.', 0, 1, 'L');
}

// Section 6: Counselor Signature Area
$pdf->ensureSpace(35);
$pdf->Ln(5);
$sigY = $pdf->GetY();

$pdf->SetDrawColor(180, 190, 200);
$pdf->Line(15, $sigY + 15, 85, $sigY + 15);
$pdf->Line(125, $sigY + 15, 195, $sigY + 15);

$pdf->SetFont('Arial', 'B', 8.5);
$pdf->SetTextColor(44, 62, 80);
$pdf->SetXY(15, $sigY + 17);
$pdf->Cell(70, 4, 'Counselor Representative Signature', 0, 0, 'C');
$pdf->Cell(40, 4, '', 0, 0, 'C');
$pdf->Cell(70, 4, 'Date of Review', 0, 1, 'C');

$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(100, 110, 120);
$pdf->SetXY(15, $sigY + 21);
$pdf->Cell(70, 4, cleanText($student['counselor_name']), 0, 0, 'C');
$pdf->Cell(40, 4, '', 0, 0, 'C');
$pdf->Cell(70, 4, date('F d, Y'), 0, 1, 'C');

// Output PDF to Browser for direct download
$filename = 'MindCare_Wellness_Report_' . $student['student_id_number'] . '_' . date('Ymd') . '.pdf';
if (ob_get_length()) {
    ob_end_clean(); // Discard any warning/whitespace output from buffer before sending PDF headers
}
$pdf->Output('D', $filename);
exit;
?>
