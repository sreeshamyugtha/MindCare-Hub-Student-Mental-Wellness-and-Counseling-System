<?php
// counselor/notes.php
$page_title = "Add Counseling Notes";
$active_nav = "notes";
require_once '../includes/auth.php';
require_once '../config/db.php';

// Assert Counselor Permissions
require_role('counselor');

$counselor_id = $_SESSION['counselor_id'];

// Get parameters if redirected from schedule or student logs
$get_student_id = intval($_GET['student_id'] ?? 0);
$get_appointment_id = intval($_GET['appointment_id'] ?? 0);

// Fetch assigned students list for selector dropdown
$studentsStmt = $pdo->prepare("SELECT id, full_name, student_id_number FROM students WHERE counselor_id = ? ORDER BY full_name ASC");
$studentsStmt->execute([$counselor_id]);
$assigned_students = $studentsStmt->fetchAll();

// Fetch appointments list for the selected student to support mapping (if student is selected)
$appointments = [];
$preselected_student_id = 0;

if ($get_student_id > 0) {
    // Check if the student is assigned to this counselor
    $verifyStmt = $pdo->prepare("SELECT id FROM students WHERE id = ? AND counselor_id = ?");
    $verifyStmt->execute([$get_student_id, $counselor_id]);
    if ($verifyStmt->fetch()) {
        $preselected_student_id = $get_student_id;
        
        // Fetch approved or rescheduled appointments for this student
        $appStmt = $pdo->prepare("
            SELECT id, appointment_date, appointment_time, reason 
            FROM appointments 
            WHERE student_id = ? AND counselor_id = ? AND status IN ('approved', 'rescheduled')
            ORDER BY appointment_date DESC
        ");
        $appStmt->execute([$preselected_student_id, $counselor_id]);
        $appointments = $appStmt->fetchAll();
    }
}

// Handle Note Submission Form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = intval($_POST['student_id'] ?? 0);
    $appointment_id = intval($_POST['appointment_id'] ?? 0);
    $notes_content = trim($_POST['notes'] ?? '');
    $visible_to_student = isset($_POST['visible_to_student']) ? 1 : 0;
    
    // Validate student is assigned to this counselor
    $verifyStmt = $pdo->prepare("SELECT id FROM students WHERE id = ? AND counselor_id = ?");
    $verifyStmt->execute([$student_id, $counselor_id]);
    $student_exists = $verifyStmt->fetch();
    
    if ($student_id <= 0 || !$student_exists) {
        set_flash('danger', 'Invalid student selected.');
    } elseif (empty($notes_content)) {
        set_flash('warning', 'Session note content cannot be empty.');
    } else {
        try {
            // Validate appointment if selected
            $db_appointment_id = null;
            if ($appointment_id > 0) {
                $appCheck = $pdo->prepare("SELECT id FROM appointments WHERE id = ? AND student_id = ? AND counselor_id = ?");
                $appCheck->execute([$appointment_id, $student_id, $counselor_id]);
                if ($appCheck->fetch()) {
                    $db_appointment_id = $appointment_id;
                }
            }
            
            $insertStmt = $pdo->prepare("
                INSERT INTO counseling_notes (student_id, counselor_id, appointment_id, notes, visible_to_student) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $insertStmt->execute([$student_id, $counselor_id, $db_appointment_id, $notes_content, $visible_to_student]);
            
            set_flash('success', 'Counseling note recorded successfully.');
            redirect('students.php?student_id=' . $student_id);
        } catch (\Exception $e) {
            set_flash('danger', 'Error saving note: ' . $e->getMessage());
        }
    }
}

require_once '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="fw-bold">Add Counseling Session Note</h1>
        <p class="text-muted">Record therapeutic clinical logs and specify sharing access permissions.</p>
    </div>
</div>

<div class="row">
    <div class="col-lg-7 mx-auto">
        <div class="card card-glass border-0 p-4 shadow-sm">
            <h4 class="fw-bold mb-4"><i class="bi bi-journal-plus text-primary me-2"></i> Session Log Form</h4>
            
            <form action="notes.php" method="POST" id="notesForm">
                <!-- Student Selection -->
                <div class="mb-3">
                    <label for="student_select" class="form-label fw-semibold">Select Student</label>
                    <select name="student_id" id="student_select" class="form-select" onchange="updateStudentAppointments()" required>
                        <option value="">-- Choose a student --</option>
                        <?php foreach ($assigned_students as $s): ?>
                            <option value="<?php echo $s['id']; ?>" <?php echo ($preselected_student_id === $s['id']) ? 'selected' : ''; ?>>
                                <?php echo h($s['full_name']); ?> (<?php echo h($s['student_id_number']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Appointment Mapping Selection -->
                <div class="mb-3" id="appointment_mapping_container">
                    <label for="appointment_select" class="form-label fw-semibold">Link to Appointment (Optional)</label>
                    <select name="appointment_id" id="appointment_select" class="form-select">
                        <option value="">-- General consultation / No specific scheduled session --</option>
                        <?php foreach ($appointments as $app): ?>
                            <option value="<?php echo $app['id']; ?>" <?php echo ($get_appointment_id === $app['id']) ? 'selected' : ''; ?>>
                                <?php echo date('M d, Y', strtotime($app['appointment_date'])); ?> at <?php echo date('h:i A', strtotime($app['appointment_time'])); ?> - "<?php echo h(substr($app['reason'], 0, 40)); ?>..."
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Notes Content -->
                <div class="mb-3">
                    <label for="notes" class="form-label fw-semibold">Therapeutic Notes & Recommendations</label>
                    <textarea name="notes" id="notes" rows="8" class="form-control" placeholder="Write session observations, behavior logs, and wellness recommendations here..." required></textarea>
                </div>
                
                <!-- Visibility Checkbox -->
                <div class="mb-4 form-check form-switch p-3 bg-light rounded border-start border-3 border-info">
                    <input type="checkbox" name="visible_to_student" id="visible_to_student" class="form-check-input ms-0 me-2" value="1">
                    <label for="visible_to_student" class="form-check-label fw-semibold text-dark">Share this note with the student</label>
                    <span class="d-block text-muted small mt-1">If enabled, the student will see this note on their dashboard under "Advisor Feedback". Otherwise, it remains visible only to you.</span>
                </div>
                
                <div class="d-flex justify-content-end gap-2">
                    <a href="students.php<?php echo ($preselected_student_id > 0) ? '?student_id=' . $preselected_student_id : ''; ?>" class="btn btn-outline-secondary" style="border-radius:8px;">Cancel</a>
                    <button type="submit" class="btn btn-primary-custom"><i class="bi bi-check2-circle"></i> Save Session Note</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// JavaScript helper to fetch appointments dynamically if the student is changed
function updateStudentAppointments() {
    const studentId = document.getElementById('student_select').value;
    if (!studentId) {
        document.getElementById('appointment_select').innerHTML = '<option value="">-- Select student first --</option>';
        return;
    }
    
    // Redirect with student_id to refresh the select options cleanly in backend PHP (simple and robust)
    window.location.href = 'notes.php?student_id=' + studentId;
}
</script>

<?php require_once '../includes/footer.php'; ?>
