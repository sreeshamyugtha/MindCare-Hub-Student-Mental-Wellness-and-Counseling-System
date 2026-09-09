<?php
// student/journal.php
$page_title = "Gratitude & Reflection Journal";
$active_nav = "journal";
require_once '../includes/auth.php';
require_once '../config/db.php';

require_role('student');

$student_id = $_SESSION['student_id'];

function ensure_journal_columns($pdo) {
    $columns = [];
    $stmt = $pdo->query("SHOW COLUMNS FROM journals");
    foreach ($stmt->fetchAll() as $column) {
        $columns[$column['Field']] = true;
    }

    $adds = [
        'journal_date' => "ALTER TABLE journals ADD COLUMN journal_date DATE NULL AFTER student_id",
        'mood' => "ALTER TABLE journals ADD COLUMN mood VARCHAR(30) NULL AFTER content",
        'gratitude_points' => "ALTER TABLE journals ADD COLUMN gratitude_points TEXT NULL AFTER mood",
        'word_count' => "ALTER TABLE journals ADD COLUMN word_count INT NOT NULL DEFAULT 0 AFTER gratitude_points",
        'positive_word_count' => "ALTER TABLE journals ADD COLUMN positive_word_count INT NOT NULL DEFAULT 0 AFTER word_count",
        'favorite' => "ALTER TABLE journals ADD COLUMN favorite TINYINT(1) NOT NULL DEFAULT 0 AFTER positive_word_count",
        'privacy' => "ALTER TABLE journals ADD COLUMN privacy VARCHAR(40) NOT NULL DEFAULT 'private' AFTER favorite"
    ];

    foreach ($adds as $field => $sql) {
        if (!isset($columns[$field])) {
            $pdo->exec($sql);
        }
    }

    $pdo->exec("UPDATE journals SET journal_date = DATE(created_at) WHERE journal_date IS NULL");
}

function journal_word_count($text) {
    preg_match_all('/[A-Za-z0-9]+(?:[\'-][A-Za-z0-9]+)?/', $text, $matches);
    return count($matches[0]);
}

function journal_positive_word_count($text) {
    $positive_words = ['happy', 'grateful', 'excited', 'peaceful', 'proud', 'confident', 'hopeful', 'smile', 'calm', 'kind', 'thankful', 'good', 'great', 'joy', 'love', 'strong', 'progress', 'better'];
    $words = preg_split('/[^a-z0-9]+/i', strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
    $count = 0;
    foreach ($words as $word) {
        if (in_array($word, $positive_words, true)) {
            $count++;
        }
    }
    return $count;
}

function journal_positive_things_count($answers) {
    $count = 0;
    foreach (['gratitude', 'achievement', 'positive'] as $key) {
        $value = trim($answers[$key] ?? '');
        if ($value !== '') {
            $parts = preg_split('/[\r\n,;]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
            $count += max(1, count(array_filter(array_map('trim', $parts))));
        }
    }
    return $count;
}

function journal_build_content($answers) {
    $labels = [
        'gratitude' => 'Gratitude',
        'achievement' => 'Achievement',
        'positive' => 'Positive Moment',
        'challenge' => 'Challenge',
        'reflection' => 'Self Reflection',
        'goal' => "Tomorrow's Goal"
    ];
    $sections = [];
    foreach ($labels as $key => $label) {
        $value = trim($answers[$key] ?? '');
        if ($value !== '') {
            $sections[] = $label . ":\n" . $value;
        }
    }
    return implode("\n\n", $sections);
}

function journal_current_streak($dates) {
    $dateSet = array_flip($dates);
    $cursor = new DateTime(date('Y-m-d'));
    if (!isset($dateSet[$cursor->format('Y-m-d')])) {
        $cursor->modify('-1 day');
    }
    $streak = 0;
    while (isset($dateSet[$cursor->format('Y-m-d')])) {
        $streak++;
        $cursor->modify('-1 day');
    }
    return $streak;
}

function journal_longest_streak($dates) {
    sort($dates);
    $longest = 0;
    $current = 0;
    $previous = null;
    foreach ($dates as $date) {
        $day = new DateTime($date);
        if ($previous && $day->diff($previous)->days === 1) {
            $current++;
        } else {
            $current = 1;
        }
        $longest = max($longest, $current);
        $previous = $day;
    }
    return $longest;
}

function journal_encouragement() {
    $messages = [
        'You are stronger than you think.',
        'Small progress is still progress.',
        'Every day is a new beginning.',
        'Your reflection today is a gift to tomorrow.',
        'A few honest words can lighten a heavy day.',
        'You showed up for yourself today.'
    ];
    return $messages[abs(crc32(date('Y-m-d'))) % count($messages)];
}

function journal_mood_meta($mood) {
    $map = [
        'very_happy' => ['&#128512;', 'Very Happy', 'journal-mood-very-happy'],
        'happy' => ['&#128522;', 'Happy', 'journal-mood-happy'],
        'neutral' => ['&#128528;', 'Neutral', 'journal-mood-neutral'],
        'sad' => ['&#128532;', 'Sad', 'journal-mood-sad'],
        'stressed' => ['&#128547;', 'Stressed', 'journal-mood-stressed'],
        'tired' => ['&#128564;', 'Tired', 'journal-mood-tired'],
        'excited' => ['&#128525;', 'Excited', 'journal-mood-excited']
    ];
    return $map[$mood] ?? ['&#128528;', 'Neutral', 'journal-mood-neutral'];
}

ensure_journal_columns($pdo);

if (isset($_GET['export'])) {
    $exportStmt = $pdo->prepare("SELECT title, journal_date, mood, privacy, word_count, positive_word_count, content FROM journals WHERE student_id = ? ORDER BY journal_date DESC, created_at DESC");
    $exportStmt->execute([$student_id]);
    $exportRows = $exportStmt->fetchAll();

    if ($_GET['export'] === 'excel') {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=my-journal-entries.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Title', 'Date', 'Mood', 'Privacy', 'Words', 'Positive Words', 'Content']);
        foreach ($exportRows as $row) {
            fputcsv($out, $row);
        }
        fclose($out);
        exit;
    }

    if ($_GET['export'] === 'pdf') {
        require_once '../includes/fpdf/fpdf.php';
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, 'My Gratitude & Reflection Journal', 0, 1);
        $pdf->SetFont('Arial', '', 10);
        foreach ($exportRows as $row) {
            $pdf->Ln(4);
            $pdf->SetFont('Arial', 'B', 12);
            $pdf->MultiCell(0, 7, $row['title'] . ' - ' . $row['journal_date']);
            $pdf->SetFont('Arial', '', 9);
            $pdf->MultiCell(0, 6, 'Mood: ' . ($row['mood'] ?: 'Not selected') . ' | Privacy: ' . $row['privacy'] . ' | Words: ' . $row['word_count']);
            $pdf->MultiCell(0, 6, $row['content']);
        }
        $pdf->Output('D', 'my-journal-entries.pdf');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    try {
        if ($_POST['ajax_action'] === 'save_entry') {
            $valid_moods = ['very_happy', 'happy', 'neutral', 'sad', 'stressed', 'tired', 'excited'];
            $valid_privacy = ['private', 'share_counselor', 'anonymous_research'];
            $answers = $_POST['prompts'] ?? [];
            $title = trim($_POST['title'] ?? '');
            $mood = trim($_POST['mood'] ?? '');
            $privacy = trim($_POST['privacy'] ?? 'private');
            $today = date('Y-m-d');
            $content = journal_build_content($answers);

            if ($title === '') {
                $title = 'Reflection for ' . date('F j, Y');
            }
            if (!in_array($mood, $valid_moods, true)) {
                throw new Exception('Please select how you are feeling today.');
            }
            if (!in_array($privacy, $valid_privacy, true)) {
                $privacy = 'private';
            }
            if ($content === '') {
                throw new Exception('Please answer at least one reflection prompt.');
            }

            $word_count = journal_word_count($content);
            $positive_word_count = journal_positive_word_count($content);
            $positive_things = journal_positive_things_count($answers);
            $gratitude_points = trim($answers['gratitude'] ?? '');

            $check = $pdo->prepare("SELECT id FROM journals WHERE student_id = ? AND journal_date = ? LIMIT 1");
            $check->execute([$student_id, $today]);
            $existing = $check->fetch();

            if ($existing) {
                $stmt = $pdo->prepare("
                    UPDATE journals
                    SET title = ?, content = ?, mood = ?, gratitude_points = ?, word_count = ?, positive_word_count = ?, privacy = ?, updated_at = NOW()
                    WHERE id = ? AND student_id = ?
                ");
                $stmt->execute([$title, $content, $mood, $gratitude_points, $word_count, $positive_word_count, $privacy, $existing['id'], $student_id]);
                $entry_id = $existing['id'];
                $status = "Today's journal was updated.";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO journals (student_id, journal_date, title, content, mood, gratitude_points, word_count, positive_word_count, privacy, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$student_id, $today, $title, $content, $mood, $gratitude_points, $word_count, $positive_word_count, $privacy]);
                $entry_id = $pdo->lastInsertId();
                $status = "Today's journal was saved.";
            }

            echo json_encode([
                'success' => true,
                'entry_id' => $entry_id,
                'message' => $status,
                'word_count' => $word_count,
                'positive_word_count' => $positive_word_count,
                'positive_things' => $positive_things,
                'encouragement' => journal_encouragement()
            ]);
            exit;
        }

        if ($_POST['ajax_action'] === 'toggle_favorite') {
            $id = intval($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("UPDATE journals SET favorite = IF(favorite = 1, 0, 1) WHERE id = ? AND student_id = ?");
            $stmt->execute([$id, $student_id]);
            $fetch = $pdo->prepare("SELECT favorite FROM journals WHERE id = ? AND student_id = ?");
            $fetch->execute([$id, $student_id]);
            echo json_encode(['success' => true, 'favorite' => intval($fetch->fetchColumn())]);
            exit;
        }
    } catch (Exception $e) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

$stmt = $pdo->prepare("SELECT * FROM journals WHERE student_id = ? ORDER BY journal_date DESC, created_at DESC");
$stmt->execute([$student_id]);
$journals = $stmt->fetchAll();

$today = date('Y-m-d');
$month_start = date('Y-m-01');
$month_end = date('Y-m-t');
$today_entry = null;
$favorite_entries = [];
$entry_dates = [];
$calendar_entries = [];
$mood_counts = [];
$monthly_counts = [];
$total_words = 0;
$longest_entry_words = 0;
$favorite_mood = 'Not enough data';

foreach ($journals as $row) {
    $date = $row['journal_date'] ?: date('Y-m-d', strtotime($row['created_at']));
    if ($date === $today) {
        $today_entry = $row;
    }
    if (intval($row['favorite']) === 1) {
        $favorite_entries[] = $row;
    }
    if (!in_array($date, $entry_dates, true)) {
        $entry_dates[] = $date;
    }
    $calendar_entries[$date] = $row;
    $words = intval($row['word_count']);
    $total_words += $words;
    $longest_entry_words = max($longest_entry_words, $words);
    if (!empty($row['mood'])) {
        $mood_counts[$row['mood']] = ($mood_counts[$row['mood']] ?? 0) + 1;
    }
    $month_key = date('M Y', strtotime($date));
    $monthly_counts[$month_key] = ($monthly_counts[$month_key] ?? 0) + 1;
}

if (!empty($mood_counts)) {
    arsort($mood_counts);
    $favorite_mood_key = array_key_first($mood_counts);
    $favorite_mood_meta = journal_mood_meta($favorite_mood_key);
    $favorite_mood = html_entity_decode($favorite_mood_meta[0], ENT_QUOTES, 'UTF-8') . ' ' . $favorite_mood_meta[1];
}

$total_entries = count($journals);
$avg_words = $total_entries > 0 ? round($total_words / $total_entries) : 0;
$current_streak = journal_current_streak($entry_dates);
$longest_streak = journal_longest_streak($entry_dates);

$gratitudeMonthStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM journals
    WHERE student_id = ? AND journal_date BETWEEN ? AND ? AND gratitude_points IS NOT NULL AND TRIM(gratitude_points) <> ''
");
$gratitudeMonthStmt->execute([$student_id, $month_start, $month_end]);
$gratitude_this_month = intval($gratitudeMonthStmt->fetchColumn());

$prompt_sets = [
    [
        'gratitude' => 'What are three things you are grateful for today?',
        'achievement' => 'What is one thing you accomplished today?',
        'positive' => 'What made you smile today?',
        'challenge' => 'What was the biggest challenge you faced today? How did you handle it?',
        'reflection' => 'What did you learn about yourself today?',
        'goal' => 'What is one thing you want to improve tomorrow?'
    ],
    [
        'gratitude' => 'Who or what helped you feel supported today?',
        'achievement' => 'What small win deserves credit today?',
        'positive' => 'Describe one peaceful or happy moment from today.',
        'challenge' => 'What felt difficult today, and what helped you continue?',
        'reflection' => 'What emotion did you notice most strongly today?',
        'goal' => 'What gentle goal would make tomorrow easier?'
    ],
    [
        'gratitude' => 'Name three things from today that you do not want to take for granted.',
        'achievement' => 'What responsibility did you handle well today?',
        'positive' => 'What conversation, place, or activity lifted your mood?',
        'challenge' => 'What challenged your patience or confidence today?',
        'reflection' => 'What did today teach you about your needs?',
        'goal' => 'What is one kind thing you can do for yourself tomorrow?'
    ]
];
$daily_prompts = $prompt_sets[abs(crc32($today)) % count($prompt_sets)];
$today_answers = [];
if ($today_entry) {
    $section_keys = [
        'Gratitude' => 'gratitude',
        'Achievement' => 'achievement',
        'Positive Moment' => 'positive',
        'Challenge' => 'challenge',
        'Self Reflection' => 'reflection',
        "Tomorrow's Goal" => 'goal'
    ];
    $parts = preg_split('/\n\n+/', $today_entry['content']);
    foreach ($parts as $part) {
        foreach ($section_keys as $label => $key) {
            $prefix = $label . ":\n";
            if (strpos($part, $prefix) === 0) {
                $today_answers[$key] = trim(substr($part, strlen($prefix)));
            }
        }
    }
}

$calendar_month = date('Y-m');
$first_day = new DateTime($calendar_month . '-01');
$days_in_month = intval($first_day->format('t'));
$start_weekday = intval($first_day->format('w'));

$monthly_labels = array_keys($monthly_counts);
$monthly_values = array_values($monthly_counts);
if (empty($monthly_labels)) {
    $monthly_labels = [date('M Y')];
    $monthly_values = [0];
}

require_once '../includes/header.php';
?>

<style>
.journal-hero {
    background: linear-gradient(135deg, rgba(220, 238, 255, 0.95), rgba(234, 246, 255, 0.86));
    border: 1px solid rgba(43, 108, 176, 0.16);
    color: #1A365D;
}
.journal-title-typing {
    display: inline-block;
    overflow: hidden;
    white-space: nowrap;
    animation: journalTyping 1.6s steps(34, end);
}
@keyframes journalTyping {
    from { max-width: 0; }
    to { max-width: 100%; }
}
.journal-stat {
    background: #ffffff;
    border: 1px solid rgba(43, 108, 176, 0.12);
    border-radius: 14px;
    padding: 0.9rem;
    color: #1A365D;
}
.journal-stat .text-muted {
    color: #5f718a !important;
}
.journal-stat .fw-bold,
.journal-stat .text-dark {
    color: #1A365D !important;
}
.journal-mood-button {
    border: 2px solid transparent;
    border-radius: 14px;
    background: #ffffff;
    color: #1A365D;
    padding: 0.75rem 0.55rem;
    width: 100%;
    transition: transform 0.18s ease, border-color 0.18s ease, box-shadow 0.18s ease;
}
.journal-mood-button:hover,
.journal-mood-button.active {
    transform: translateY(-2px);
    border-color: #2B6CB0;
    box-shadow: 0 8px 18px rgba(43, 108, 176, 0.16);
}
.journal-mood-emoji {
    display: block;
    font-size: 1.65rem;
}
.guided-prompt-card {
    background: rgba(255, 255, 255, 0.9);
    border: 1px solid rgba(43, 108, 176, 0.14);
    border-radius: 14px;
    padding: 1rem;
    color: #1A365D;
}
.guided-prompt-card .form-label {
    color: #1A365D;
}
.journal-calendar {
    display: grid;
    grid-template-columns: repeat(7, minmax(0, 1fr));
    gap: 0.45rem;
}
.journal-calendar-day {
    min-height: 58px;
    border-radius: 12px;
    border: 1px solid rgba(43, 108, 176, 0.12);
    background: #edf2f7;
    color: #1A365D;
    padding: 0.45rem;
    text-align: left;
}
.journal-calendar-day.completed { background: #d8f3dc; }
.journal-calendar-day.partial { background: #fff3bf; }
.journal-calendar-day.empty { background: #edf2f7; color: #718096; }
.journal-entry-card {
    background: #ffffff;
    border-radius: 14px;
    border: 1px solid rgba(43, 108, 176, 0.14);
    animation: fadeInUp 0.45s ease both;
    color: #1A365D;
}
.journal-entry-card .text-muted {
    color: #5f718a !important;
}
.journal-entry-card .text-secondary {
    color: #36516f !important;
}
.journal-favorite-btn {
    border: 0;
    background: transparent;
    color: #94a3b8;
    font-size: 1.2rem;
}
.journal-favorite-btn.active { color: #E53E3E; }
.journal-celebration {
    pointer-events: none;
    position: fixed;
    inset: 0;
    z-index: 2000;
    display: none;
    background: radial-gradient(circle, rgba(255, 255, 255, 0.22), transparent 55%);
}
.journal-celebration.show {
    display: block;
    animation: celebrateFade 1.2s ease forwards;
}
.journal-celebration::before {
    content: "*  *  *  *  *";
    position: absolute;
    top: 38%;
    left: 50%;
    transform: translateX(-50%);
    color: #2B6CB0;
    font-size: 3rem;
    letter-spacing: 0.35rem;
}
@keyframes celebrateFade {
    0% { opacity: 0; transform: scale(0.96); }
    25% { opacity: 1; transform: scale(1); }
    100% { opacity: 0; transform: scale(1.04); }
}
.timeline-item {
    border-left: 3px solid #90CDF4;
    padding-left: 1rem;
    margin-left: 0.6rem;
    position: relative;
}
.timeline-item::before {
    content: "";
    position: absolute;
    left: -0.46rem;
    top: 0.25rem;
    width: 0.75rem;
    height: 0.75rem;
    border-radius: 50%;
    background: #2B6CB0;
}
[data-bs-theme="dark"] .journal-hero {
    background: linear-gradient(135deg, #dceeff 0%, #b9ddff 100%);
    border-color: rgba(144, 205, 244, 0.45);
    color: #10213a;
}
[data-bs-theme="dark"] .journal-stat {
    background: var(--bg-light);
    border-color: rgba(144, 205, 244, 0.24);
    color: #e2e8f0;
}
[data-bs-theme="dark"] .journal-stat .text-muted {
    color: #a8b5c7 !important;
}
[data-bs-theme="dark"] .journal-stat .fw-bold,
[data-bs-theme="dark"] .journal-stat .text-dark {
    color: #f8fafc !important;
}
[data-bs-theme="dark"] .journal-mood-button {
    background: var(--bg-light);
    color: #e2e8f0;
    border-color: rgba(144, 205, 244, 0.24);
}
[data-bs-theme="dark"] .journal-mood-button:hover,
[data-bs-theme="dark"] .journal-mood-button.active {
    background: #1e3a5f;
    color: #f8fafc;
    border-color: #60a5fa;
}
[data-bs-theme="dark"] .guided-prompt-card {
    background: var(--bg-light);
    border-color: rgba(144, 205, 244, 0.24);
    color: #e2e8f0;
}
[data-bs-theme="dark"] .guided-prompt-card .form-label {
    color: #f8fafc;
}
[data-bs-theme="dark"] .guided-prompt-card .form-control,
[data-bs-theme="dark"] #guidedJournalForm .form-control,
[data-bs-theme="dark"] #guidedJournalForm .form-select,
[data-bs-theme="dark"] #journalSearch,
[data-bs-theme="dark"] #journalDateFilter,
[data-bs-theme="dark"] #journalMoodFilter,
[data-bs-theme="dark"] #journalMonthFilter,
[data-bs-theme="dark"] #journalYearFilter {
    background-color: #1e293b;
    border-color: rgba(144, 205, 244, 0.35);
    color: #e2e8f0;
}
[data-bs-theme="dark"] .guided-prompt-card .form-control::placeholder,
[data-bs-theme="dark"] #guidedJournalForm .form-control::placeholder,
[data-bs-theme="dark"] #journalSearch::placeholder,
[data-bs-theme="dark"] #journalMonthFilter::placeholder,
[data-bs-theme="dark"] #journalYearFilter::placeholder {
    color: #94a3b8;
}
[data-bs-theme="dark"] .journal-calendar-day {
    background: var(--bg-light);
    border-color: rgba(144, 205, 244, 0.24);
    color: #e2e8f0;
}
[data-bs-theme="dark"] .journal-calendar-day.completed {
    background: rgba(22, 163, 74, 0.22);
    border-color: rgba(34, 197, 94, 0.4);
}
[data-bs-theme="dark"] .journal-calendar-day.partial {
    background: rgba(234, 179, 8, 0.22);
    border-color: rgba(250, 204, 21, 0.45);
}
[data-bs-theme="dark"] .journal-calendar-day.empty {
    background: var(--bg-light);
    color: #a8b5c7;
}
[data-bs-theme="dark"] .journal-entry-card {
    background: #f8fbff;
    border-color: rgba(96, 165, 250, 0.25);
    color: #10213a;
}
[data-bs-theme="dark"] .journal-entry-card h6,
[data-bs-theme="dark"] .journal-entry-card .fw-bold {
    color: #10213a;
}
[data-bs-theme="dark"] .journal-entry-card .text-muted {
    color: #52657f !important;
}
[data-bs-theme="dark"] .journal-entry-card .text-secondary {
    color: #36516f !important;
}
@media print {
    .sidebar, .sidebar-toggle, .journal-no-print, .navbar-custom { display: none !important; }
    .main-content { margin-left: 0 !important; }
}
</style>

<div class="journal-celebration" id="journalCelebration"></div>

<div class="row mb-4 align-items-center fade-in-up">
    <div class="col-lg-8">
        <h1 class="fw-bold journal-title-typing">Gratitude &amp; Reflection Journal</h1>
        <p class="text-muted mb-0">A calm space to notice your emotions, name the good, and reflect with honesty.</p>
    </div>
    <div class="col-lg-4 text-lg-end mt-3 mt-lg-0 journal-no-print">
        <a href="journal.php?export=pdf" class="btn btn-outline-danger me-1"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
        <a href="journal.php?export=excel" class="btn btn-outline-success me-1"><i class="bi bi-file-earmark-spreadsheet"></i> Excel</a>
        <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
    </div>
</div>

<?php if (!$today_entry): ?>
    <div class="alert alert-info alert-custom journal-no-print">
        <i class="bi bi-bell me-2"></i>You haven't reflected today. Take five minutes to write about your day.
    </div>
<?php endif; ?>

<div class="card journal-hero p-4 mb-4 shadow-sm fade-in-up">
    <div class="row g-3">
        <div class="col-md-2 col-6"><div class="journal-stat"><span class="text-muted small">Today's Status</span><div class="fw-bold text-dark"><?php echo $today_entry ? 'Completed' : 'Not Written'; ?></div></div></div>
        <div class="col-md-2 col-6"><div class="journal-stat"><span class="text-muted small">Streak</span><div class="fw-bold text-dark">&#128293; <?php echo h($current_streak); ?> Days</div></div></div>
        <div class="col-md-2 col-6"><div class="journal-stat"><span class="text-muted small">Entries</span><div class="fw-bold text-dark"><?php echo h($total_entries); ?></div></div></div>
        <div class="col-md-3 col-6"><div class="journal-stat"><span class="text-muted small">Gratitude This Month</span><div class="fw-bold text-dark"><?php echo h($gratitude_this_month); ?></div></div></div>
        <div class="col-md-3 d-grid"><a href="#writeToday" class="btn btn-primary-custom align-content-center"><i class="bi bi-pencil-square me-1"></i> Write Today's Journal</a></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="card card-glass border-0 p-4 shadow-sm journal-no-print" id="writeToday">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h4 class="fw-bold mb-1"><i class="bi bi-stars text-warning me-2"></i>Daily Guided Journal</h4>
                    <p class="text-muted small mb-0">Today's prompts are chosen for <?php echo date('F j, Y'); ?>.</p>
                </div>
                <span class="badge bg-secondary-subtle text-secondary rounded-pill px-3 py-2"><?php echo $today_entry ? 'Editing Today' : 'New Entry'; ?></span>
            </div>

            <form id="guidedJournalForm">
                <input type="hidden" name="ajax_action" value="save_entry">
                <input type="hidden" name="mood" id="journalMoodInput" value="<?php echo h($today_entry['mood'] ?? ''); ?>">

                <div class="mb-3">
                    <label class="form-label fw-semibold">Journal Title</label>
                    <input type="text" class="form-control" name="title" maxlength="255" value="<?php echo h($today_entry['title'] ?? ('Reflection for ' . date('F j, Y'))); ?>">
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">How are you feeling today?</label>
                    <div class="row g-2">
                        <?php foreach (['very_happy', 'happy', 'neutral', 'sad', 'stressed', 'tired', 'excited'] as $mood_key): ?>
                            <?php $meta = journal_mood_meta($mood_key); ?>
                            <div class="col-6 col-md-3 col-lg">
                                <button type="button" class="journal-mood-button <?php echo (($today_entry['mood'] ?? '') === $mood_key) ? 'active' : ''; ?>" data-mood="<?php echo h($mood_key); ?>">
                                    <span class="journal-mood-emoji"><?php echo $meta[0]; ?></span>
                                    <span class="small fw-semibold"><?php echo h($meta[1]); ?></span>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="row g-3">
                    <?php foreach ($daily_prompts as $key => $prompt): ?>
                        <div class="col-md-6">
                            <div class="guided-prompt-card h-100">
                                <label class="form-label fw-semibold"><?php echo h($prompt); ?></label>
                                <textarea class="form-control journal-answer" name="prompts[<?php echo h($key); ?>]" rows="4" placeholder="Write a few honest lines..."><?php echo h($today_answers[$key] ?? ''); ?></textarea>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="row g-3 align-items-end mt-2">
                    <div class="col-md-7">
                        <label class="form-label fw-semibold">Journal Privacy</label>
                        <select name="privacy" class="form-select">
                            <option value="private" <?php echo (($today_entry['privacy'] ?? '') === 'private') ? 'selected' : ''; ?>>Private - Visible only to me</option>
                            <option value="share_counselor" <?php echo (($today_entry['privacy'] ?? '') === 'share_counselor') ? 'selected' : ''; ?>>Share with Counselor</option>
                            <option value="anonymous_research" <?php echo (($today_entry['privacy'] ?? '') === 'anonymous_research') ? 'selected' : ''; ?>>Anonymous for Research</option>
                        </select>
                    </div>
                    <div class="col-md-5 text-md-end">
                        <div class="text-muted small mb-2" id="liveWordCounter">0 words, 0 positive words</div>
                        <button type="submit" class="btn btn-primary-custom"><i class="bi bi-check2-circle me-1"></i> Save Reflection</button>
                    </div>
                </div>
            </form>

            <div class="alert alert-success mt-4 d-none" id="journalSaveResult"></div>
            <div class="alert alert-warning mt-3 d-none" id="writingSuggestion"></div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="card card-glass border-0 p-4 shadow-sm mb-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-calendar3 text-primary me-2"></i>Journal Calendar</h5>
            <div class="journal-calendar mb-2 text-center fw-semibold small text-muted">
                <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div><div>Thu</div><div>Fri</div><div>Sat</div>
            </div>
            <div class="journal-calendar">
                <?php for ($i = 0; $i < $start_weekday; $i++): ?><div></div><?php endfor; ?>
                <?php for ($day = 1; $day <= $days_in_month; $day++): ?>
                    <?php
                        $date = $calendar_month . '-' . str_pad((string)$day, 2, '0', STR_PAD_LEFT);
                        $entry = $calendar_entries[$date] ?? null;
                        $class = 'empty';
                        if ($entry) {
                            $class = intval($entry['word_count']) >= 50 ? 'completed' : 'partial';
                        }
                    ?>
                    <button type="button" class="journal-calendar-day <?php echo $class; ?>" <?php if ($entry): ?>data-bs-toggle="modal" data-bs-target="#entryModal<?php echo $entry['id']; ?>"<?php endif; ?>>
                        <span class="fw-bold"><?php echo $day; ?></span>
                        <?php if ($entry): ?><span class="d-block small text-truncate"><?php echo h($entry['title']); ?></span><?php endif; ?>
                    </button>
                <?php endfor; ?>
            </div>
            <div class="d-flex gap-3 small text-muted mt-3">
                <span><i class="bi bi-square-fill text-success"></i> Completed</span>
                <span><i class="bi bi-square-fill text-warning"></i> Partial</span>
                <span><i class="bi bi-square-fill text-secondary"></i> No Entry</span>
            </div>
        </div>

        <div class="card card-glass border-0 p-4 shadow-sm">
            <h5 class="fw-bold mb-3"><i class="bi bi-bar-chart-line text-secondary me-2"></i>Journal Statistics</h5>
            <div class="row g-2 mb-3">
                <div class="col-6"><div class="journal-stat"><span class="small text-muted">Avg Words</span><div class="fw-bold"><?php echo h($avg_words); ?></div></div></div>
                <div class="col-6"><div class="journal-stat"><span class="small text-muted">Longest Entry</span><div class="fw-bold"><?php echo h($longest_entry_words); ?></div></div></div>
                <div class="col-6"><div class="journal-stat"><span class="small text-muted">Longest Streak</span><div class="fw-bold"><?php echo h($longest_streak); ?></div></div></div>
                <div class="col-6"><div class="journal-stat"><span class="small text-muted">Favorite Mood</span><div class="fw-bold"><?php echo h($favorite_mood); ?></div></div></div>
            </div>
            <div style="height: 220px;"><canvas id="journalProgressChart"></canvas></div>
        </div>
    </div>
</div>

<div class="card card-glass border-0 p-4 shadow-sm my-4 journal-no-print">
    <h5 class="fw-bold mb-3"><i class="bi bi-search text-primary me-2"></i>Search Journal</h5>
    <div class="row g-3">
        <div class="col-md-4"><input type="text" id="journalSearch" class="form-control" placeholder="Keyword"></div>
        <div class="col-md-2"><input type="date" id="journalDateFilter" class="form-control"></div>
        <div class="col-md-2">
            <select id="journalMoodFilter" class="form-select">
                <option value="">Mood</option>
                <?php foreach (['very_happy', 'happy', 'neutral', 'sad', 'stressed', 'tired', 'excited'] as $mood_key): ?>
                    <?php $meta = journal_mood_meta($mood_key); ?>
                    <option value="<?php echo h($mood_key); ?>"><?php echo $meta[0] . ' ' . h($meta[1]); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><input type="number" id="journalMonthFilter" class="form-control" min="1" max="12" placeholder="Month"></div>
        <div class="col-md-2"><input type="number" id="journalYearFilter" class="form-control" min="2000" max="2100" placeholder="Year"></div>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card card-glass border-0 p-4 shadow-sm">
            <h5 class="fw-bold mb-3"><i class="bi bi-clock-history text-secondary me-2"></i>Mood Timeline</h5>
            <?php if ($journals): ?>
                <div class="d-flex flex-column gap-3" id="journalEntriesList">
                    <?php foreach ($journals as $row): ?>
                        <?php $meta = journal_mood_meta($row['mood'] ?? 'neutral'); $date = $row['journal_date'] ?: date('Y-m-d', strtotime($row['created_at'])); ?>
                        <div class="journal-entry-card p-3 journal-entry-item"
                            data-title="<?php echo h(strtolower($row['title'])); ?>"
                            data-content="<?php echo h(strtolower($row['content'])); ?>"
                            data-mood="<?php echo h($row['mood']); ?>"
                            data-date="<?php echo h($date); ?>"
                            data-month="<?php echo h(date('n', strtotime($date))); ?>"
                            data-year="<?php echo h(date('Y', strtotime($date))); ?>">
                            <div class="d-flex justify-content-between gap-3">
                                <div class="timeline-item flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <span class="fs-5"><?php echo $meta[0]; ?></span>
                                        <span class="fw-bold"><?php echo date('M d', strtotime($date)); ?></span>
                                        <span class="badge bg-light text-secondary border"><?php echo h($meta[1]); ?></span>
                                    </div>
                                    <h6 class="fw-bold mb-1"><?php echo h($row['title']); ?></h6>
                                    <p class="text-muted small mb-0"><?php echo h(substr(preg_replace('/\s+/', ' ', $row['content']), 0, 150)); ?>...</p>
                                </div>
                                <div class="text-end">
                                    <button class="journal-favorite-btn <?php echo intval($row['favorite']) === 1 ? 'active' : ''; ?>" data-id="<?php echo $row['id']; ?>" title="Favorite memory">
                                        <i class="bi <?php echo intval($row['favorite']) === 1 ? 'bi-heart-fill' : 'bi-heart'; ?>"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary d-block mt-2" data-bs-toggle="modal" data-bs-target="#entryModal<?php echo $row['id']; ?>">Open</button>
                                </div>
                            </div>
                        </div>

                        <div class="modal fade" id="entryModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
                                <div class="modal-content border-0 rounded-4 shadow-lg">
                                    <div class="modal-header border-0 bg-light">
                                        <div>
                                            <h5 class="modal-title fw-bold"><?php echo h($row['title']); ?></h5>
                                            <span class="text-muted small"><?php echo $meta[0] . ' ' . h($meta[1]); ?> | <?php echo date('F d, Y', strtotime($date)); ?> | <?php echo h($row['word_count']); ?> words</span>
                                        </div>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p class="text-secondary" style="white-space: pre-line; line-height: 1.7;"><?php echo h($row['content']); ?></p>
                                    </div>
                                    <div class="modal-footer border-0">
                                        <span class="me-auto badge bg-secondary-subtle text-secondary"><?php echo h(str_replace('_', ' ', $row['privacy'])); ?></span>
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center text-muted py-4 d-none" id="journalNoResults">No entries match your search.</div>
            <?php else: ?>
                <p class="text-muted text-center py-4 mb-0">No journal entries yet. Today's reflection can be the first one.</p>
            <?php endif; ?>
        </div>
    </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const moodButtons = document.querySelectorAll('.journal-mood-button');
    const moodInput = document.getElementById('journalMoodInput');
    const form = document.getElementById('guidedJournalForm');
    const result = document.getElementById('journalSaveResult');
    const celebration = document.getElementById('journalCelebration');
    const suggestionBox = document.getElementById('writingSuggestion');
    const answers = document.querySelectorAll('.journal-answer');
    const liveCounter = document.getElementById('liveWordCounter');
    const positiveWords = ['happy', 'grateful', 'excited', 'peaceful', 'proud', 'confident', 'hopeful', 'smile', 'calm', 'kind', 'thankful', 'good', 'great', 'joy', 'love', 'strong', 'progress', 'better'];
    const suggestions = ['What made today special?', 'What challenged you today?', 'What are you thankful for?', 'What helped you keep going today?'];
    let typingTimer;

    function allAnswerText() {
        return Array.from(answers).map(item => item.value).join(' ');
    }

    function updateLiveCounter() {
        const text = allAnswerText().trim();
        const words = text ? text.match(/[A-Za-z0-9]+(?:['-][A-Za-z0-9]+)?/g) || [] : [];
        const positives = words.filter(word => positiveWords.includes(word.toLowerCase())).length;
        liveCounter.textContent = `${words.length} words, ${positives} positive words`;
    }

    moodButtons.forEach(button => {
        button.addEventListener('click', function() {
            moodButtons.forEach(item => item.classList.remove('active'));
            button.classList.add('active');
            moodInput.value = button.dataset.mood;
        });
    });

    answers.forEach(answer => {
        answer.addEventListener('input', function() {
            updateLiveCounter();
            suggestionBox.classList.add('d-none');
            clearTimeout(typingTimer);
            typingTimer = setTimeout(function() {
                suggestionBox.textContent = suggestions[Math.floor(Math.random() * suggestions.length)];
                suggestionBox.classList.remove('d-none');
            }, 10000);
        });
    });

    if (form) {
        form.addEventListener('submit', function(event) {
            event.preventDefault();
            result.classList.add('d-none');
            fetch('journal.php', {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json().then(data => ({ ok: response.ok, data })))
            .then(({ ok, data }) => {
                if (!ok || !data.success) {
                    throw new Error(data.message || 'Unable to save your journal.');
                }
                result.className = 'alert alert-success mt-4';
                result.innerHTML = `<strong>${data.message}</strong><br>You wrote ${data.word_count} words today. You mentioned ${data.positive_things} positive things. You used ${data.positive_word_count} positive words today.<br><span class="fw-semibold">${data.encouragement}</span>`;
                celebration.classList.add('show');
                setTimeout(() => celebration.classList.remove('show'), 1300);
            })
            .catch(error => {
                result.className = 'alert alert-danger mt-4';
                result.textContent = error.message;
            });
        });
    }

    document.querySelectorAll('.journal-favorite-btn').forEach(button => {
        button.addEventListener('click', function() {
            const data = new FormData();
            data.append('ajax_action', 'toggle_favorite');
            data.append('id', button.dataset.id);
            fetch('journal.php', { method: 'POST', body: data })
                .then(response => response.json())
                .then(payload => {
                    if (payload.success) {
                        button.classList.toggle('active', payload.favorite === 1);
                        button.querySelector('i').className = payload.favorite === 1 ? 'bi bi-heart-fill' : 'bi bi-heart';
                    }
                });
        });
    });

    const filters = ['journalSearch', 'journalDateFilter', 'journalMoodFilter', 'journalMonthFilter', 'journalYearFilter'].map(id => document.getElementById(id));
    const entryItems = document.querySelectorAll('.journal-entry-item');
    const noResults = document.getElementById('journalNoResults');

    function filterEntries() {
        const query = (filters[0].value || '').toLowerCase();
        const date = filters[1].value;
        const mood = filters[2].value;
        const month = filters[3].value;
        const year = filters[4].value;
        let visible = 0;
        entryItems.forEach(item => {
            const matches = (!query || item.dataset.title.includes(query) || item.dataset.content.includes(query))
                && (!date || item.dataset.date === date)
                && (!mood || item.dataset.mood === mood)
                && (!month || item.dataset.month === month)
                && (!year || item.dataset.year === year);
            item.classList.toggle('d-none', !matches);
            if (matches) visible++;
        });
        if (noResults) noResults.classList.toggle('d-none', visible !== 0);
    }
    filters.forEach(input => input && input.addEventListener('input', filterEntries));
    if (filters[2]) filters[2].addEventListener('change', filterEntries);

    const progressCanvas = document.getElementById('journalProgressChart');
    if (progressCanvas) {
        new Chart(progressCanvas, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($monthly_labels); ?>,
                datasets: [{
                    label: 'Entries',
                    data: <?php echo json_encode($monthly_values); ?>,
                    backgroundColor: 'rgba(43, 108, 176, 0.75)',
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
            }
        });
    }

    updateLiveCounter();
});
</script>

<?php require_once '../includes/footer.php'; ?>
