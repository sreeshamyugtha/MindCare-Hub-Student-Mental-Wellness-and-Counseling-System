<?php
// forgot_password.php
$page_title = "Forgot Password";
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
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Look up user by email
        $stmt = $pdo->prepare("SELECT id, username FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user) {
            try {
                // Generate a 6-digit verification code
                $code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires = date('Y-m-d H:i:s', strtotime('+15 minutes'));
                
                // Store code and expiration in users table
                $updateStmt = $pdo->prepare("UPDATE users SET reset_code = ?, reset_expires = ? WHERE email = ?");
                $updateStmt->execute([$code, $expires, $email]);
                
                // Send the verification code email
                require_once 'includes/mail.php';
                $subject = "Password Reset Code - MindCare Hub";
                $body = "<h2>Hello " . h($user['username']) . ",</h2>" .
                        "<p>You are receiving this email because we received a password reset request for your account.</p>" .
                        "<p>Please use the following 6-digit verification code to complete your password reset:</p>" .
                        "<div style='background-color: #f7fafc; border: 1px solid #e2e8f0; padding: 15px; border-radius: 8px; text-align: center; margin: 20px 0;'>" .
                        "<span style='font-size: 24px; font-weight: bold; letter-spacing: 5px; color: #1a365d;'>" . $code . "</span>" .
                        "</div>" .
                        "<p>This verification code is valid for <strong>15 minutes</strong>. If you did not request a password reset, please ignore this email.</p>" .
                        "<br><p>Best regards,<br>MindCare Hub Team</p>";
                
                $mail_sent = send_mail($email, $subject, $body);
                
                // Store email in session to skip asking for email on reset screen
                $_SESSION['reset_email'] = $email;
                if ($mail_sent) {
                    set_flash('success', 'Verification code has been sent to your registered email address.');
                } else {
                    // Display the code directly in local development when mail sending fails
                    set_flash('warning', '<strong>Local Development:</strong> Email delivery is not configured. Your 6-digit verification code is: <strong class="fs-5 text-dark font-monospace bg-light px-2 py-1 border rounded">' . $code . '</strong> (Details logged to <code>logs/emails.log</code>).', true);
                }
                redirect('reset_password.php');
                
            } catch (Exception $e) {
                $error = 'System error generating reset code: ' . $e->getMessage();
            }
        } else {
            $error = 'Email address is not registered in our system.';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5 auth-container">
        <div class="card card-glass shadow border-0 p-4">
            <div class="text-center mb-4">
                <span class="text-primary-custom fs-1" style="color: var(--primary);"><i class="bi bi-key-fill"></i></span>
                <h3 class="fw-bold mt-2">Recover Password</h3>
                <p class="text-muted small">Enter your email address and we'll send a 6-digit verification code to reset your password.</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-custom mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo h($error); ?>
                </div>
            <?php endif; ?>
            
            <form action="forgot_password.php" method="POST">
                <div class="mb-4">
                    <label for="email" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-envelope"></i></span>
                        <input type="email" name="email" id="email" class="form-control border-start-0 ps-0" placeholder="yourname@domain.com" value="<?php echo isset($_POST['email']) ? h($_POST['email']) : ''; ?>" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary-custom w-100 py-2.5 mb-3 shadow-sm">
                    <i class="bi bi-send me-2"></i> Send Verification Code
                </button>
            </form>
            
            <div class="text-center mt-3">
                <p class="text-muted small mb-0">Remembered your password? <a href="login.php" style="color: var(--secondary); font-weight:500;">Sign In</a></p>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
