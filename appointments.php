<?php
// student/appointments.php
$page_title = "Book Appointment";
$active_nav = "appointments";
require_once '../includes/auth.php';
require_once '../config/db.php';

// Assert Student Permissions
require_role('student');

$student_id = $_SESSION['student_id'];

// 1. Fetch student information to check assigned counselor and email
$profileStmt = $pdo->prepare("
    SELECT s.*, u.email, 
           c.full_name AS counselor_name, 
           c.specialization AS counselor_spec,
           c.photo AS counselor_photo,
           c.id AS counselor_real_id
    FROM students s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN counselors c ON s.counselor_id = c.id
    WHERE s.id = ?
");
$profileStmt->execute([$student_id]);
$student = $profileStmt->fetch();

// 2. Fetch list of all counselors for unassigned students
$counselorsStmt = $pdo->query("SELECT id, full_name, specialization, photo FROM counselors ORDER BY full_name ASC");
$counselors = $counselorsStmt->fetchAll();

// 3. Process Booking Form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_session'])) {
    $counselor_id = intval($_POST['counselor_id'] ?? 0);
    $date = $_POST['appointment_date'] ?? '';
    $time = $_POST['appointment_time'] ?? '';
    $reason = trim($_POST['reason'] ?? '');
    
    // Server-side validation
    $today_date = date('Y-m-d');
    
    if ($counselor_id <= 0 || empty($date) || empty($time) || empty($reason)) {
        set_flash('danger', 'All booking fields are required.');
    } elseif ($date < $today_date) {
        set_flash('warning', 'Appointment date cannot be in the past.');
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO appointments (student_id, counselor_id, appointment_date, appointment_time, reason, status) 
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->execute([$student_id, $counselor_id, $date, $time, $reason]);
            
            // Send booking request notification email to student
            try {
                require_once '../includes/mail.php';
                
                // Get counselor name for email
                $cname = 'Assigned Counselor';
                if ($student['counselor_id'] && $student['counselor_id'] == $counselor_id) {
                    $cname = $student['counselor_name'];
                } else {
                    $cstmt = $pdo->prepare("SELECT full_name FROM counselors WHERE id = ?");
                    $cstmt->execute([$counselor_id]);
                    $cname = $cstmt->fetchColumn() ?: 'Assigned Counselor';
                }
                
                $subject = "Appointment Request Submitted - MindCare Hub";
                $body = "<h2>Hello " . h($student['full_name']) . ",</h2>" .
                        "<p>Your consultation booking request has been submitted successfully and is awaiting counselor review.</p>" .
                        "<p><strong>Session Details:</strong></p>" .
                        "<ul>" .
                        "<li><strong>Counselor:</strong> Dr. " . h($cname) . "</li>" .
                        "<li><strong>Date:</strong> " . date('F d, Y', strtotime($date)) . "</li>" .
                        "<li><strong>Time:</strong> " . date('h:i A', strtotime($time)) . "</li>" .
                        "<li><strong>Objective / Concern:</strong> " . h($reason) . "</li>" .
                        "</ul>" .
                        "<p>You will receive another email update once your counselor approves or reschedules this session.</p>" .
                        "<br><p>Best regards,<br>MindCare Hub Team</p>";
                send_mail($student['email'], $subject, $body);
            } catch (\Exception $mail_ex) {
                // Suppress email exceptions
            }
            
            set_flash('success', 'Appointment request submitted. Awaiting counselor approval.');
            redirect('appointments.php');
        } catch (\Exception $e) {
            set_flash('danger', 'System error booking appointment: ' . $e->getMessage());
        }
    }
}

// 4. Fetch all past and upcoming appointments for logs
$appointmentsStmt = $pdo->prepare("
    SELECT a.*, c.full_name AS counselor_name, c.specialization AS counselor_spec, c.photo AS counselor_photo
    FROM appointments a
    JOIN counselors c ON a.counselor_id = c.id
    WHERE a.student_id = ?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
");
$appointmentsStmt->execute([$student_id]);
$appointments = $appointmentsStmt->fetchAll();

require_once '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="fw-bold">Counseling Appointments</h1>
        <p class="text-muted">Schedule interactive coaching consultations with certified support staff.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Left Column: Booking Form -->
    <div class="col-lg-5">
        <div class="card card-glass border-0 p-4 shadow-sm">
            <h4 class="fw-bold mb-4"><i class="bi bi-calendar-plus text-primary me-2"></i> Book a Session</h4>
            
            <form action="appointments.php" method="POST">
                <input type="hidden" name="book_session" value="1">
                
                <!-- Counselor Selection -->
                <div class="mb-4">
                    <label class="form-label fw-semibold mb-2">Consultant Counselor</label>
                    <?php if ($student['counselor_id']): ?>
                        <input type="hidden" name="counselor_id" value="<?php echo $student['counselor_id']; ?>">
                        
                        <!-- Preselected Assigned Counselor Profile Card -->
                        <div class="card bg-light border-0 p-3 mb-2" style="border-radius:12px;">
                            <div class="d-flex align-items-center gap-3">
                                <img src="../uploads/counselors/<?php echo !empty($student['counselor_photo']) ? h($student['counselor_photo']) : 'default.png'; ?>" 
                                     alt="Dr. <?php echo h($student['counselor_name']); ?>" 
                                     class="avatar-photo avatar-photo-md shadow-sm">
                                <div>
                                    <h6 class="fw-bold mb-1 text-dark">Dr. <?php echo h($student['counselor_name']); ?></h6>
                                    <div class="text-muted small mb-1" style="font-size:0.75rem;">ID: CNS-<?php echo h($student['counselor_real_id']); ?></div>
                                    <span class="badge bg-primary-pastel text-primary mb-1 d-inline-block text-truncate" style="font-size:0.7rem;"><?php echo h($student['counselor_spec']); ?></span>
                                    <div class="text-secondary small" style="font-size:0.7rem;">
                                        <strong>Qual:</strong> <?php echo ($student['counselor_real_id'] == 1) ? 'M.Sc. Clinical Psychology (CBT)' : 'Ph.D. Counseling Psychology'; ?><br>
                                        <strong>Exp:</strong> <?php echo ($student['counselor_real_id'] == 1) ? '8+ Years' : '10+ Years'; ?>
                                    </div>
                                    <div class="mt-1">
                                        <span class="badge bg-success-subtle text-success" style="font-size:0.65rem;"><i class="bi bi-circle-fill me-1" style="font-size:0.45rem;"></i> Available</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <span class="text-muted small" style="font-size:0.75rem;"><i class="bi bi-lock-fill me-1 text-secondary"></i> Preselected assigned advisor</span>
                    <?php else: ?>
                        <!-- Selection Cards for Unassigned Students -->
                        <input type="hidden" name="counselor_id" id="selected_counselor_id" value="" required>
                        <div class="row g-2 mb-2">
                            <?php foreach ($counselors as $c): ?>
                                <?php 
                                $c_id = intval($c['id']);
                                $c_photo = !empty($c['photo']) ? $c['photo'] : 'default.png';
                                $c_qual = ($c_id == 1) ? 'M.Sc. Clinical Psychology (CBT)' : (($c_id == 2) ? 'Ph.D. Counseling Psychology' : 'M.Sc. Counseling Psychology');
                                $c_exp = ($c_id == 1) ? '8+ Years' : (($c_id == 2) ? '10+ Years' : '5+ Years');
                                ?>
                                <div class="col-12">
                                    <div class="card counselor-booking-card border p-3" 
                                         id="counselor_card_<?php echo $c['id']; ?>" 
                                         onclick="selectCounselor(<?php echo $c['id']; ?>)"
                                         style="border-radius: 12px; cursor: pointer; transition: all 0.2s;">
                                        <div class="d-flex align-items-center gap-3">
                                            <img src="../uploads/counselors/<?php echo h($c_photo); ?>" 
                                                 alt="Dr. <?php echo h($c['full_name']); ?>" 
                                                 class="avatar-photo avatar-photo-md shadow-sm">
                                            <div class="flex-grow-1">
                                                <h6 class="fw-bold mb-1 text-dark">Dr. <?php echo h($c['full_name']); ?></h6>
                                                <div class="text-muted small mb-1" style="font-size:0.75rem;">ID: CNS-<?php echo h($c['id']); ?></div>
                                                <span class="badge bg-primary-pastel text-primary mb-1 d-inline-block text-truncate" style="font-size:0.7rem;"><?php echo h($c['specialization']); ?></span>
                                                <div class="text-secondary small" style="font-size:0.7rem;">
                                                    <strong>Qual:</strong> <?php echo h($c_qual); ?><br>
                                                    <strong>Exp:</strong> <?php echo h($c_exp); ?>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mt-1">
                                                    <span class="badge bg-success-subtle text-success" style="font-size:0.65rem;"><i class="bi bi-circle-fill me-1" style="font-size:0.45rem;"></i> Available</span>
                                                    <button type="button" class="btn btn-outline-primary btn-xs select-btn py-0.5 px-2" style="font-size:0.7rem; border-radius:6px;">
                                                        Select
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <span class="text-muted small" style="font-size:0.75rem;">Select any active staff advisor to enable booking.</span>
                    <?php endif; ?>
                </div>
                
                <!-- Date and Time Picker -->
                <div class="row mb-3">
                    <div class="col-sm-6">
                        <label for="appointment_date" class="form-label fw-semibold">Session Date</label>
                        <input type="date" name="appointment_date" id="appointment_date" class="form-control" min="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-sm-6">
                        <label for="appointment_time" class="form-label fw-semibold">Session Time</label>
                        <input type="time" name="appointment_time" id="appointment_time" class="form-control" required>
                    </div>
                </div>
                
                <!-- Booking Reason -->
                <div class="mb-4">
                    <label for="reason" class="form-label fw-semibold">Session Objective / Concern</label>
                    <textarea name="reason" id="reason" rows="4" class="form-control" placeholder="Describe the topics you wish to review during this counseling session..." required></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary-custom w-100 py-2 shadow-sm">
                    <i class="bi bi-journal-check me-1"></i> Send Booking Request
                </button>
            </form>
        </div>
    </div>
    
    <!-- Right Column: Appointment Status Logs -->
    <div class="col-lg-7">
        <div class="card card-glass border-0 p-4 shadow-sm">
            <h4 class="fw-bold mb-4"><i class="bi bi-calendar-range text-secondary me-2"></i> Consultations Log</h4>
            
            <?php if (count($appointments) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Schedule</th>
                                <th>Counselor</th>
                                <th>Reason</th>
                                <th class="text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($appointments as $row): ?>
                                <?php
                                    $statusClass = 'bg-secondary';
                                    if ($row['status'] === 'pending') $statusClass = 'bg-warning text-dark';
                                    if ($row['status'] === 'approved') $statusClass = 'bg-success';
                                    if ($row['status'] === 'rejected') $statusClass = 'bg-danger';
                                    if ($row['status'] === 'rescheduled') $statusClass = 'bg-info text-dark';
                                ?>
                                <tr>
                                    <td>
                                        <span class="fw-semibold d-block text-dark small" style="font-size:0.85rem;">
                                            <?php echo date('M d, Y', strtotime($row['appointment_date'])); ?>
                                        </span>
                                        <span class="text-muted d-block" style="font-size:0.75rem;">
                                            <i class="bi bi-clock me-1"></i><?php echo date('h:i A', strtotime($row['appointment_time'])); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="../uploads/counselors/<?php echo !empty($row['counselor_photo']) ? h($row['counselor_photo']) : 'default.png'; ?>" 
                                                 alt="Dr. <?php echo h($row['counselor_name']); ?>" 
                                                 class="avatar-photo avatar-photo-sm">
                                            <div>
                                                <span class="fw-semibold d-block small" style="font-size:0.85rem;">Dr. <?php echo h($row['counselor_name']); ?></span>
                                                <span class="text-muted d-block text-truncate" style="font-size:0.75rem; max-width:140px;"><?php echo h($row['counselor_spec']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="text-secondary small d-inline-block text-truncate" style="max-width: 180px;" title="<?php echo h($row['reason']); ?>">
                                            <?php echo h($row['reason']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?php echo $statusClass; ?> text-capitalize px-2.5 py-1.5 rounded-pill small">
                                            <?php echo h($row['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="p-5 text-center bg-light rounded-4">
                    <span class="display-3 text-muted"><i class="bi bi-calendar2-x"></i></span>
                    <p class="text-muted mt-3 mb-0">No counseling appointments booked. Use the form on the left to schedule your first session.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function selectCounselor(counselorId) {
    // Clear all highlights
    document.querySelectorAll('.counselor-booking-card').forEach(card => {
        card.classList.remove('border-primary', 'shadow', 'bg-light');
        const btn = card.querySelector('.select-btn');
        if (btn) {
            btn.classList.replace('btn-primary', 'btn-outline-primary');
            btn.innerText = 'Select';
        }
    });
    
    // Select the clicked card
    const card = document.getElementById('counselor_card_' + counselorId);
    if (card) {
        card.classList.add('border-primary', 'shadow', 'bg-light');
        const btn = card.querySelector('.select-btn');
        if (btn) {
            btn.classList.replace('btn-outline-primary', 'btn-primary');
            btn.innerText = 'Selected';
        }
    }
    
    // Update hidden field
    const hiddenInput = document.getElementById('selected_counselor_id');
    if (hiddenInput) {
        hiddenInput.value = counselorId;
    }
}
</script>

<?php require_once '../includes/footer.php'; ?>
