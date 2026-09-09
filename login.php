<?php
// login.php
$page_title = "Sign In";
require_once 'includes/auth.php';
require_once 'config/db.php';

// If already logged in, redirect to respective dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'student') redirect('student/dashboard.php');
    if ($_SESSION['role'] === 'counselor') redirect('counselor/dashboard.php');
    if ($_SESSION['role'] === 'admin') redirect('admin/dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($login_input) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        // Find user by username or email
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$login_input, $login_input]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Setup base session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // Query detailed profile based on user role
            if ($user['role'] === 'student') {
                $profileStmt = $pdo->prepare("SELECT id, full_name, counselor_id FROM students WHERE user_id = ?");
                $profileStmt->execute([$user['id']]);
                $profile = $profileStmt->fetch();
                if ($profile) {
                    $_SESSION['student_id'] = $profile['id'];
                    $_SESSION['full_name'] = $profile['full_name'];
                }
                set_flash('success', 'Welcome back, ' . ($_SESSION['full_name'] ?? $user['username']) . '!');
                redirect('student/dashboard.php');
                
            } elseif ($user['role'] === 'counselor') {
                $profileStmt = $pdo->prepare("SELECT id, full_name FROM counselors WHERE user_id = ?");
                $profileStmt->execute([$user['id']]);
                $profile = $profileStmt->fetch();
                if ($profile) {
                    $_SESSION['counselor_id'] = $profile['id'];
                    $_SESSION['full_name'] = $profile['full_name'];
                }
                set_flash('success', 'Welcome, Dr. ' . ($_SESSION['full_name'] ?? $user['username']) . '!');
                redirect('counselor/dashboard.php');
                
            } elseif ($user['role'] === 'admin') {
                $_SESSION['full_name'] = 'Administrator';
                set_flash('success', 'Admin session started successfully.');
                redirect('admin/dashboard.php');
            }
        } else {
            $error = 'Invalid username/email or password.';
        }
    }
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5 auth-container">
        <div class="card card-glass shadow border-0 p-4">
            <div class="text-center mb-4">
                <span class="text-primary-custom fs-1" style="color: var(--primary);"><i class="bi bi-heart-pulse-fill"></i></span>
                <h3 class="fw-bold mt-2">MindCare Hub</h3>
                <p class="text-muted small">Sign in to manage your wellness and counseling records</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-custom mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo h($error); ?>
                </div>
            <?php endif; ?>
            
            <form action="login.php" method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">Username or Email</label>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-person"></i></span>
                        <input type="text" name="username" id="username" class="form-control border-start-0 ps-0"  value="<?php echo isset($_POST['username']) ? h($_POST['username']) : ''; ?>" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label for="password" class="form-label mb-0">Password</label>
                        <a href="forgot_password.php" class="small text-secondary" style="font-weight: 500; text-decoration: none;">Forgot Password?</a>
                    </div>
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0 text-muted"><i class="bi bi-lock"></i></span>
                        <input type="password" name="password" id="password" class="form-control border-start-0 border-end-0 ps-0" required>
                        <span class="input-group-text bg-white border-start-0 text-muted toggle-password" style="cursor: pointer;" data-target="password">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary-custom w-100 py-2.5 mb-3 shadow-sm">
                    <i class="bi bi-box-arrow-in-right me-2"></i> Sign In
                </button>
            </form>
            
            <div class="text-center mt-3">
                <p class="text-muted small mb-0">Don't have a student account? <a href="register.php" style="color: var(--secondary); font-weight:500;">Register here</a></p>
            </div>
        </div>
        
       
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
