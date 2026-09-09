<?php
// reset_password.php
$page_title = "Reset Password";
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
$email = $_SESSION['reset_email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($email) || empty($code) || empty($password) || empty($confirm_password)) {
        $error = 'Please fill in all fields.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } else {
        try {
            // Verify code and expiration (passing current PHP time to avoid database timezone mismatches)
            $current_time = date('Y-m-d H:i:s');
            $stmt = $pdo->prepare("
                SELECT id 
                FROM users 
                WHERE email = ? AND reset_code = ? AND reset_expires >= ?
            ");
            $stmt->execute([$email, $code, $current_time]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Update password and clear reset columns
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("
                    UPDATE users 
                    SET password = ?, reset_code = NULL, reset_expires = NULL 
                    WHERE id = ?
                ");
                $updateStmt->execute([$hashed_password, $user['id']]);
                
                // Clear session helper
                if (isset($_SESSION['reset_email'])) {
                    unset($_SESSION['reset_email']);
                }
                
                set_flash('success', 'Your password has been successfully reset. You can now log in.');
                redirect('login.php');
            } else {
                $error = 'Invalid email or verification code. It may have expired (valid for 15 mins).';
            }
        } catch (Exception $e) {
            $error = 'System error resetting password: ' . $e->getMessage();
        }
    }
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5 auth-container">
        <div class="card card-glass shadow border-0 p-4">
            <div class="text-center mb-4">
                <span class="text-primary-custom fs-1" style="color: var(--primary);"><i class="bi bi-shield-lock-fill"></i></span>
                <h3 class="fw-bold mt-2">Reset Password</h3>
                <p class="text-muted small">Enter your verification code and choose a new password.</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-custom mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo h($error); ?>
                </div>
            <?php endif; ?>
            
            <form action="reset_password.php" method="POST">
                <!-- Email (Read-only or editable) -->
                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" id="email" class="form-control border-start-0 ps-0" placeholder="yourname@domain.com" value="<?php echo h($email); ?>" required>
                    </div>
                </div>

                <!-- Code -->
                <div class="mb-3">
                    <label for="code" class="form-label">Verification Code (6 Digits)</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-hash"></i></span>
                        <input type="text" name="code" id="code" class="form-control border-start-0 ps-0" placeholder="000000" maxlength="6" pattern="\d{6}" required>
                    </div>
                </div>
                
                <!-- New Password -->
                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="password" class="form-control border-start-0 border-end-0 ps-0" placeholder="••••••••" required>
                        <span class="input-group-text bg-white border-start-0 text-muted toggle-password" style="cursor: pointer;" data-target="password">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="mb-4">
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-lock-check"></i></span>
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control border-start-0 border-end-0 ps-0" placeholder="••••••••" required>
                        <span class="input-group-text bg-white border-start-0 text-muted toggle-password" style="cursor: pointer;" data-target="confirm_password">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary-custom w-100 py-2.5 mb-3 shadow-sm">
                    <i class="bi bi-check-circle me-2"></i> Update Password
                </button>
            </form>
            
            <div class="text-center mt-3">
                <p class="text-muted small mb-0">Remembered your password? <a href="login.php" style="color: var(--secondary); font-weight:500;">Sign In</a></p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
