<?php
// student/assessment.php
$page_title = "Stress Assessment";
$active_nav = "assessment";
require_once '../includes/auth.php';
require_once '../config/db.php';

// Assert Student Permissions
require_role('student');

$student_id = $_SESSION['student_id'];
$assessment_result = null;

// Define the 10 PSS Question Categories with 3 variations each
$question_bank = [
    1 => [
        ["text" => "How often have you been upset because of something that happened unexpectedly?", "reverse" => false],
        ["text" => "How often have you felt troubled or irritated by sudden, unexpected occurrences?", "reverse" => false],
        ["text" => "How often did an unforeseen event make you feel flustered or upset?", "reverse" => false],
    ],
    2 => [
        ["text" => "How often have you felt that you were unable to control the important things in your life?", "reverse" => false],
        ["text" => "How often have you felt powerless over the key events and directions in your life?", "reverse" => false],
        ["text" => "How often did you feel you lacked control over significant aspects of your day-to-day life?", "reverse" => false],
    ],
    3 => [
        ["text" => "How often have you felt nervous and 'stressed'?", "reverse" => false],
        ["text" => "How often have you found yourself feeling anxious, tense, or under pressure?", "reverse" => false],
        ["text" => "How often have you felt overwhelmed by nervousness or mental stress?", "reverse" => false],
    ],
    4 => [
        ["text" => "How often have you felt confident about your ability to handle your personal problems?", "reverse" => true],
        ["text" => "How often have you felt sure of your capability to deal with personal difficulties?", "reverse" => true],
        ["text" => "How often have you felt strong and self-assured in resolving your personal issues?", "reverse" => true],
    ],
    5 => [
        ["text" => "How often have you felt that things were going your way?", "reverse" => true],
        ["text" => "How often have you felt that your life was moving in the direction you wanted?", "reverse" => true],
        ["text" => "How often have you felt that events were turning out well for you?", "reverse" => true],
    ],
    6 => [
        ["text" => "How often have you found that you could not cope with all the things that you had to do?", "reverse" => false],
        ["text" => "How often have you felt unable to manage or keep up with your daily responsibilities?", "reverse" => false],
        ["text" => "How often did you feel overwhelmed by the sheer volume of tasks you had to complete?", "reverse" => false],
    ],
    7 => [
        ["text" => "How often have you been able to control irritations in your life?", "reverse" => true],
        ["text" => "How often have you successfully managed or minimized small frustrations in your daily routine?", "reverse" => true],
        ["text" => "How often have you kept your cool when facing annoying situations?", "reverse" => true],
    ],
    8 => [
        ["text" => "How often have you felt that you were on top of things?", "reverse" => true],
        ["text" => "How often have you felt that you were in complete command of your tasks and life?", "reverse" => true],
        ["text" => "How often have you felt highly organized and capable of handling everything?", "reverse" => true],
    ],
    9 => [
        ["text" => "How often have you been angered because of things that were outside of your control?", "reverse" => false],
        ["text" => "How often have you felt angry or frustrated by circumstances you could not influence?", "reverse" => false],
        ["text" => "How often did you lose your temper over external situations beyond your control?", "reverse" => false],
    ],
    10 => [
        ["text" => "How often have you felt difficulties were piling up so high that you could not overcome them?", "reverse" => false],
        ["text" => "How often have you felt like your problems were compounding to a point where you could not solve them?", "reverse" => false],
        ["text" => "How often did you feel so buried under issues that there was no way to get past them?", "reverse" => false],
    ]
];

// Initialize or load the randomized questions for the current attempt
if (!isset($_SESSION['selected_assessment_questions']) || isset($_GET['new_attempt'])) {
    $selected_questions = [];
    foreach ($question_bank as $slot => $options) {
        $rand_key = array_rand($options);
        $selected_questions[$slot] = $options[$rand_key];
    }
    $_SESSION['selected_assessment_questions'] = $selected_questions;
}
$questions = $_SESSION['selected_assessment_questions'];

// 1. Process questionnaire submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_assessment'])) {
    $answers = [];
    $total_score = 0;
    $missing_answer = false;
    
    for ($i = 1; $i <= 10; $i++) {
        if (!isset($_POST['q' . $i])) {
            $missing_answer = true;
            break;
        }
        
        $val = intval($_POST['q' . $i]);
        $answers[$i] = $val;
        
        // Calculate score based on selected question's configuration
        if ($questions[$i]['reverse']) {
            // Reverse score: 0 -> 4, 1 -> 3, 2 -> 2, 3 -> 1, 4 -> 0
            $score_val = 4 - $val;
        } else {
            $score_val = $val;
        }
        $total_score += $score_val;
    }
    
    if ($missing_answer) {
        set_flash('danger', 'Please answer all 10 questions before submitting.');
    } else {
        // Classify stress level
        $stress_level = 'Low Stress';
        if ($total_score >= 14 && $total_score <= 26) {
            $stress_level = 'Moderate Stress';
        } elseif ($total_score >= 27) {
            $stress_level = 'High Stress';
        }
        
        try {
            $stmt = $pdo->prepare("
                INSERT INTO assessments (student_id, score, stress_level, q1, q2, q3, q4, q5, q6, q7, q8, q9, q10) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $student_id, 
                $total_score, 
                $stress_level, 
                $answers[1], $answers[2], $answers[3], $answers[4], $answers[5],
                $answers[6], $answers[7], $answers[8], $answers[9], $answers[10]
            ]);
            
            // Clear the selected questions so the next test is randomized again
            unset($_SESSION['selected_assessment_questions']);
            
            set_flash('success', 'Stress assessment completed successfully!');
            
            // Set session variable to display result summary instantly
            $_SESSION['recent_assessment'] = [
                'score' => $total_score,
                'level' => $stress_level
            ];
            
            redirect('assessment.php');
            
        } catch (\Exception $e) {
            set_flash('danger', 'Error saving assessment: ' . $e->getMessage());
        }
    }
}

// 2. Fetch assessment history
$historyStmt = $pdo->prepare("SELECT * FROM assessments WHERE student_id = ? ORDER BY taken_at DESC");
$historyStmt->execute([$student_id]);
$assessments = $historyStmt->fetchAll();

// 3. Fetch recent assessment from flash session
if (isset($_SESSION['recent_assessment'])) {
    $assessment_result = $_SESSION['recent_assessment'];
    unset($_SESSION['recent_assessment']);
}

require_once '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="fw-bold">Stress Level Assessment</h1>
        <p class="text-muted">Evaluate your current stress score using the industry standard <b>PSS-10 (Perceived Stress Scale)</b>. A different set of questions is randomly selected for each attempt.</p>
    </div>
</div>

<?php if ($assessment_result): ?>
    <!-- Display test results overlay -->
    <div class="row mb-5">
        <div class="col-12">
            <div class="card card-glass border-0 p-5 text-center shadow-lg" style="background: linear-gradient(135deg, rgba(74, 111, 165, 0.05) 0%, rgba(91, 140, 133, 0.05) 100%), var(--white);">
                <span class="display-1 text-primary mb-3"><i class="bi bi-patch-check-fill"></i></span>
                <h2 class="fw-bold">Your Stress Diagnostics Result</h2>
                <h4 class="mt-3">
                    Stress Category: 
                    <span class="badge px-4 py-2.5 rounded-pill <?php echo ($assessment_result['level'] === 'High Stress') ? 'bg-danger' : (($assessment_result['level'] === 'Moderate Stress') ? 'bg-warning text-dark' : 'bg-success'); ?>">
                        <?php echo h($assessment_result['level']); ?>
                    </span>
                </h4>
                <p class="lead text-muted mt-3">Your cumulative score is <b><?php echo h($assessment_result['score']); ?> / 40</b>.</p>
                
                <div class="row justify-content-center mt-4">
                    <div class="col-md-7">
                        <?php if ($assessment_result['level'] === 'High Stress'): ?>
                            <div class="alert alert-danger alert-custom p-3 mb-4 text-start small">
                                <h6 class="fw-bold"><i class="bi bi-exclamation-octagon-fill me-2"></i> Notice from Counselor:</h6>
                                Your score points to high perceived stress. We strongly recommend scheduling a one-on-one consultation with your college counselor to discuss personalized coping strategies.
                            </div>
                            <a href="appointments.php" class="btn btn-primary-custom btn-lg"><i class="bi bi-calendar-plus me-1"></i> Book Counseling Appointment</a>
                        <?php elseif ($assessment_result['level'] === 'Moderate Stress'): ?>
                            <div class="alert alert-warning alert-custom p-3 mb-4 text-start small text-dark">
                                <h6 class="fw-bold"><i class="bi bi-info-circle-fill me-2"></i> Recommendations:</h6>
                                You are experiencing moderate stress. Try setting up study logs, scheduling sleep timings, and tracking your reflections inside your Private Journal daily.
                            </div>
                            <div class="d-flex justify-content-center gap-2">
                                <a href="journal.php" class="btn btn-secondary-custom"><i class="bi bi-journal-plus me-1"></i> Create Journal Entry</a>
                                <a href="appointments.php" class="btn btn-outline-primary" style="border-radius:8px;">Schedule Session</a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-success alert-custom p-3 mb-4 text-start small">
                                <h6 class="fw-bold"><i class="bi bi-emoji-smile-fill me-2"></i> Great Job!</h6>
                                Your stress levels are low. Continue practicing healthy life habits, sleeping soundly, and logging your moods to maintain balance!
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Left Column: PSS-10 Form -->
    <div class="col-lg-8">
        <div class="card card-glass border-0 p-4 shadow-sm">
            <h4 class="fw-bold mb-3"><i class="bi bi-file-earmark-medical-fill text-primary me-2"></i> PSS-10 Questionnaire</h4>
            <p class="text-muted small">For each statement below, select the option that best describes <b>how often you felt or thought that way during the last month</b>.</p>
            
            <form action="assessment.php" method="POST" class="mt-4">
                <input type="hidden" name="submit_assessment" value="1">
                
                <?php for ($i = 1; $i <= 10; $i++): ?>
                    <div class="question-card mb-4">
                        <h6 class="fw-semibold mb-3">Q<?php echo $i; ?>. <?php echo h($questions[$i]['text']); ?></h6>
                        <div class="row g-2 justify-content-start">
                            <div class="col-12 col-sm-2.4 assessment-choice col">
                                <input type="radio" name="q<?php echo $i; ?>" id="q<?php echo $i; ?>_0" value="0" required>
                                <label for="q<?php echo $i; ?>_0">Never</label>
                            </div>
                            <div class="col-12 col-sm-2.4 assessment-choice col">
                                <input type="radio" name="q<?php echo $i; ?>" id="q<?php echo $i; ?>_1" value="1">
                                <label for="q<?php echo $i; ?>_1">Almost Never</label>
                            </div>
                            <div class="col-12 col-sm-2.4 assessment-choice col">
                                <input type="radio" name="q<?php echo $i; ?>" id="q<?php echo $i; ?>_2" value="2">
                                <label for="q<?php echo $i; ?>_2">Sometimes</label>
                            </div>
                            <div class="col-12 col-sm-2.4 assessment-choice col">
                                <input type="radio" name="q<?php echo $i; ?>" id="q<?php echo $i; ?>_3" value="3">
                                <label for="q<?php echo $i; ?>_3">Fairly Often</label>
                            </div>
                            <div class="col-12 col-sm-2.4 assessment-choice col">
                                <input type="radio" name="q<?php echo $i; ?>" id="q<?php echo $i; ?>_4" value="4">
                                <label for="q<?php echo $i; ?>_4">Very Often</label>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
                
                <div class="text-end pt-3">
                    <button type="submit" class="btn btn-primary-custom btn-lg"><i class="bi bi-file-earmark-check me-1"></i> Submit Stress Assessment</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Right Column: Assessment History -->
    <div class="col-lg-4">
        <div class="card card-glass border-0 p-4 shadow-sm">
            <h5 class="fw-bold mb-3"><i class="bi bi-clock-history text-secondary me-2"></i> Diagnostics History</h5>
            
            <?php if (count($assessments) > 0): ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($assessments as $row): ?>
                        <div class="p-3 bg-light rounded-3 border-start border-3 <?php echo ($row['stress_level'] === 'High Stress') ? 'border-danger' : (($row['stress_level'] === 'Moderate Stress') ? 'border-warning' : 'border-success'); ?>">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold text-dark" style="font-size:0.9rem;">Score: <?php echo h($row['score']); ?>/40</span>
                                <span class="text-muted" style="font-size:0.75rem;"><i class="bi bi-calendar"></i> <?php echo date('M d, Y', strtotime($row['taken_at'])); ?></span>
                            </div>
                            <span class="badge <?php echo ($row['stress_level'] === 'High Stress') ? 'bg-danger' : (($row['stress_level'] === 'Moderate Stress') ? 'bg-warning text-dark' : 'bg-success'); ?> small">
                                <?php echo h($row['stress_level']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted small mb-0 py-3 text-center">No assessments taken yet. Answer the PSS-10 on the left to review your wellness level.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
