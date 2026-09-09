<?php
// student/profile.php
$page_title = "Manage Profile";
$active_nav = "profile";
require_once '../includes/auth.php';
require_once '../config/db.php';

// Assert Student Permissions
require_role('student');

$student_id = $_SESSION['student_id'];
$user_id = $_SESSION['user_id'];

// Process Profile Information Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $full_name = trim($_POST['full_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($full_name)) {
        set_flash('danger', 'Full name is required.');
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE students SET full_name = ?, phone = ? WHERE id = ?");
            $stmt->execute([$full_name, $phone, $student_id]);
            
            $_SESSION['full_name'] = $full_name; // update current session presentation name
            set_flash('success', 'Profile information updated successfully.');
            redirect('profile.php');
        } catch (\Exception $e) {
            set_flash('danger', 'Error updating profile details: ' . $e->getMessage());
        }
    }
}

// Process Security Password Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_security'])) {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';
    
    if (empty($current_pass) || empty($new_pass) || empty($confirm_pass)) {
        set_flash('danger', 'All password fields are required.');
    } elseif ($new_pass !== $confirm_pass) {
        set_flash('warning', 'New passwords do not match.');
    } elseif (strlen($new_pass) < 6) {
        set_flash('warning', 'New password must be at least 6 characters long.');
    } else {
        try {
            // Retrieve current user password
            $pwdStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $pwdStmt->execute([$user_id]);
            $hash = $pwdStmt->fetchColumn();
            
            if (password_verify($current_pass, $hash)) {
                // Update credentials
                $new_hash = password_hash($new_pass, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $updateStmt->execute([$new_hash, $user_id]);
                
                set_flash('success', 'Account credentials updated successfully.');
                redirect('profile.php');
            } else {
                set_flash('danger', 'Incorrect current password entered.');
            }
        } catch (\Exception $e) {
            set_flash('danger', 'Security update failed: ' . $e->getMessage());
        }
    }
}

// Fetch current details
$stmt = $pdo->prepare("
    SELECT s.*, u.username, u.email 
    FROM students s
    JOIN users u ON s.user_id = u.id
    WHERE s.id = ?
");
$stmt->execute([$student_id]);
$profile = $stmt->fetch();

require_once '../includes/header.php';
?>

<div class="row mb-4">
    <div class="col-12">
        <h1 class="fw-bold">My Account Settings</h1>
        <p class="text-muted">Manage your profile details and security credentials.</p>
    </div>
</div>

<div class="row g-4">
    <!-- Profile Info Card -->
    <div class="col-lg-6">
        <div class="card card-glass border-0 p-4 shadow-sm h-100">
            <h4 class="fw-bold mb-4"><i class="bi bi-person-circle text-primary me-2"></i> Profile Details</h4>
            
            <form action="profile.php" method="POST">
                <input type="hidden" name="update_profile" value="1">
                
                <div class="row mb-3">
                    <div class="col-sm-6">
                        <label class="form-label text-muted small">Username</label>
                        <input type="text" class="form-control bg-light" value="<?php echo h($profile['username']); ?>" readonly>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label text-muted small">Student ID Number</label>
                        <input type="text" class="form-control bg-light" value="<?php echo h($profile['student_id_number']); ?>" readonly>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted small">Registered Email Address</label>
                    <input type="text" class="form-control bg-light" value="<?php echo h($profile['email']); ?>" readonly>
                </div>
                
                <div class="mb-3">
                    <label for="full_name" class="form-label fw-semibold">Full Name</label>
                    <input type="text" name="full_name" id="full_name" class="form-control" value="<?php echo h($profile['full_name']); ?>" required>
                </div>
                
                <div class="mb-4">
                    <label for="phone" class="form-label fw-semibold">Phone Number</label>
                    <input type="text" name="phone" id="phone" class="form-control" value="<?php echo h($profile['phone']); ?>">
                </div>
                
                <button type="submit" class="btn btn-primary-custom w-100 py-2">
                    <i class="bi bi-check2-circle me-1"></i> Save Profile Details
                </button>
            </form>
        </div>
    </div>
    
    <!-- Security Card -->
    <div class="col-lg-6">
        <div class="card card-glass border-0 p-4 shadow-sm h-100">
            <h4 class="fw-bold mb-4"><i class="bi bi-shield-lock-fill text-secondary me-2"></i> Password & Security</h4>
            
            <form action="profile.php" method="POST">
                <input type="hidden" name="update_security" value="1">
                
                <div class="mb-3">
                    <label for="current_password" class="form-label fw-semibold">Current Password</label>
                    <div class="input-group">
                        <input type="password" name="current_password" id="current_password" class="form-control border-end-0" placeholder="••••••••" required>
                        <span class="input-group-text bg-white border-start-0 text-muted toggle-password" style="cursor: pointer;" data-target="current_password">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="new_password" class="form-label fw-semibold">New Password</label>
                    <div class="input-group">
                        <input type="password" name="new_password" id="new_password" class="form-control border-end-0" placeholder="Minimum 6 characters" required>
                        <span class="input-group-text bg-white border-start-0 text-muted toggle-password" style="cursor: pointer;" data-target="new_password">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="confirm_password" class="form-label fw-semibold">Confirm New Password</label>
                    <div class="input-group">
                        <input type="password" name="confirm_password" id="confirm_password" class="form-control border-end-0" placeholder="Repeat new password" required>
                        <span class="input-group-text bg-white border-start-0 text-muted toggle-password" style="cursor: pointer;" data-target="confirm_password">
                            <i class="bi bi-eye"></i>
                        </span>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-secondary-custom w-100 py-2">
                    <i class="bi bi-key-fill me-1"></i> Update Security Credentials
                </button>
            </form>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
