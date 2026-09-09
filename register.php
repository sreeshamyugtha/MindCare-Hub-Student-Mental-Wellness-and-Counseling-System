<?php
// register.php
$page_title = "Student Registration";
require_once 'includes/auth.php';
require_once 'config/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'student') redirect('student/dashboard.php');
    if ($_SESSION['role'] === 'counselor') redirect('counselor/dashboard.php');
    if ($_SESSION['role'] === 'admin') redirect('admin/dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $student_id_number = trim($_POST['student_id_number'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Server-side validation
    if (empty($username) || empty($email) || empty($full_name) || empty($student_id_number) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            // Check if username already exists
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $checkStmt->execute([$username]);
            if ($checkStmt->fetch()) {
                $error = 'Username is already taken.';
            }
            
            // Check if email already exists
            if (empty($error)) {
                $checkStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                $checkStmt->execute([$email]);
                if ($checkStmt->fetch()) {
                    $error = 'Email is already registered.';
                }
            }
            
            // Check if Student ID Number already exists
            if (empty($error)) {
                $checkStmt = $pdo->prepare("SELECT id FROM students WHERE student_id_number = ?");
                $checkStmt->execute([$student_id_number]);
                if ($checkStmt->fetch()) {
                    $error = 'Student ID Number is already registered.';
                }
            }
            
            if (empty($error)) {
                // Begin Transaction to ensure integrity
                $pdo->beginTransaction();
                
                // 1. Insert User Account
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $userStmt = $pdo->prepare("INSERT INTO users (username, password, role, email) VALUES (?, ?, 'student', ?)");
                $userStmt->execute([$username, $hashed_password, $email]);
                $user_id = $pdo->lastInsertId();
                
                // 2. Insert Student Profile
                $studentStmt = $pdo->prepare("INSERT INTO students (user_id, full_name, student_id_number, phone, counselor_id) VALUES (?, ?, ?, ?, NULL)");
                $studentStmt->execute([$user_id, $full_name, $student_id_number, $phone]);
                
                $pdo->commit();
                
                // Send registration welcome email
                try {
                    require_once 'includes/mail.php';
                    $subject = "Welcome to MindCare Hub!";
                    $body = "<h2>Welcome, " . h($full_name) . "!</h2>" .
                            "<p>Your student mental wellness and counseling account has been registered successfully.</p>" .
                            "<p>Here are your account credentials:</p>" .
                            "<ul>" .
                            "<li><strong>Username:</strong> " . h($username) . "</li>" .
                            "<li><strong>Email:</strong> " . h($email) . "</li>" .
                            "</ul>" .
                            "<p>You can now log in to access the stress assessment test, track your daily moods, write in your personal journal, and book consultations with our professional counselors.</p>" .
                            "<br><p>Best regards,<br>MindCare Hub Team</p>";
                    send_mail($email, $subject, $body);
                } catch (\Exception $mail_ex) {
                    // Suppress mail error so registration succeeds even if mail system has configuration warnings
                }
                
                set_flash('success', 'Registration successful! You can now log in.');
                redirect('login.php');
            }
            
        } catch (\Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = 'System error occurred: ' . $e->getMessage();
        }
    }
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-7 auth-container" style="max-width: 600px;">
        <div class="card card-glass shadow border-0 p-4">
            <div class="text-center mb-4">
                <span class="text-primary-custom fs-1" style="color: var(--primary);"><i class="bi bi-heart-pulse-fill"></i></span>
                <h3 class="fw-bold mt-2">MindCare Hub</h3>
                <p class="text-muted small">Create your student wellness account</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-custom mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo h($error); ?>
                </div>
            <?php endif; ?>
            
            <form action="register.php" method="POST" class="row g-3">
                <div class="col-md-6">
                    <label for="full_name" class="form-label">Full Name <span class="text-danger">*</span></label>
                    <input type="text" name="full_name" id="full_name" class="form-control"  value="<?php echo isset($_POST['full_name']) ? h($_POST['full_name']) : ''; ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label for="student_id_number" class="form-label">Student ID / Roll No <span class="text-danger">*</span></label>
                    <input type="text" name="student_id_number" id="student_id_number" class="form-control"  value="<?php echo isset($_POST['student_id_number']) ? h($_POST['student_id_number']) : ''; ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label for="username" class="form-label">Username <span class="text-danger">*</span></label>
                    <input type="text" name="username" id="username" class="form-control"  value="<?php echo isset($_POST['username']) ? h($_POST['username']) : ''; ?>" required>
                </div>
                
                <div class="col-md-6">
                    <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" id="email" class="form-control"  value="<?php echo isset($_POST['email']) ? h($_POST['email']) : ''; ?>" required>
                </div>
                
                <div class="col-md-12">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input type="text" name="phone" id="phone" class="form-control"  value="<?php echo isset($_POST['phone']) ? h($_POST['phone']) : ''; ?>">
                </div>
                
                <div class="col-md-6">
                    <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" name="password" id="password" class="form-control border-end-0"  required>
                        <span class="input-group-text bg-white border-start-0 text-muted toggle-password" style="cursor: pointer;" data-target="password">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <label for="confirm_password" class="form-label">Confirm Password <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control border-end-0"  required>
                        <span class="input-group-text bg-white border-start-0 text-muted toggle-password" style="cursor: pointer;" data-target="confirm_password">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                </div>
                
                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary-custom w-100 py-2.5 shadow-sm">
                        <i class="bi bi-person-plus me-2"></i> Register Account
                    </button>
                </div>
            </form>
            
            <div class="text-center mt-3">
                <p class="text-muted small mb-0">Already have an account? <a href="login.php" style="color: var(--secondary); font-weight:500;">Sign In instead</a></p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
