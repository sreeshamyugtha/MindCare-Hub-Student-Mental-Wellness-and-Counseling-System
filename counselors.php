<?php
// admin/counselors.php
$page_title = "Manage Counselors";
$active_nav = "counselors";
require_once '../includes/auth.php';
require_once '../config/db.php';

// Assert Admin Permissions
require_role('admin');

$action = $_GET['action'] ?? 'list';
$counselor_id = intval($_GET['id'] ?? 0);
$edit_counselor = null;

// Upload directory for counselor photos
$upload_dir = __DIR__ . '/../uploads/counselors/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

/**
 * Handles counselor photo upload with validation.
 * Returns the new filename on success, or false on failure (sets flash message).
 */
function handle_photo_upload($file, $upload_dir) {
    // No file uploaded — not an error, just skip
    if (!isset($file) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        set_flash('warning', 'Photo upload failed. Error code: ' . $file['error']);
        return false;
    }
    
    // Validate file size (max 2MB)
    $max_size = 2 * 1024 * 1024; // 2MB
    if ($file['size'] > $max_size) {
        set_flash('danger', 'Photo file size exceeds 2MB limit.');
        return false;
    }
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);
    
    if (!in_array($mime_type, $allowed_types)) {
        set_flash('danger', 'Invalid file type. Only JPG, JPEG, PNG, and WEBP images are allowed.');
        return false;
    }
    
    // Validate extension
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed_extensions)) {
        set_flash('danger', 'Invalid file extension. Only .jpg, .jpeg, .png, .webp are allowed.');
        return false;
    }
    
    // Generate unique filename
    $new_filename = 'counselor_' . uniqid() . '_' . time() . '.' . $ext;
    $dest_path = $upload_dir . $new_filename;
    
    if (move_uploaded_file($file['tmp_name'], $dest_path)) {
        return $new_filename;
    } else {
        set_flash('danger', 'Failed to save uploaded photo.');
        return false;
    }
}

/**
 * Deletes a counselor photo from disk (except default.png).
 */
function delete_counselor_photo($filename, $upload_dir) {
    if (!empty($filename) && $filename !== 'default.png') {
        $path = $upload_dir . $filename;
        if (file_exists($path)) {
            unlink($path);
        }
    }
}

// 1. Process Actions: Add / Edit / Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_action = $_POST['form_action'] ?? '';
    
    // Extract parameters
    $full_name = trim($_POST['full_name'] ?? '');
    $specialization = trim($_POST['specialization'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    if ($form_action === 'add') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($username) || empty($password) || empty($email) || empty($full_name) || empty($specialization)) {
            set_flash('danger', 'Please fill in all required fields.');
        } elseif (strlen($password) < 6) {
            set_flash('warning', 'Password must be at least 6 characters.');
        } else {
            // Handle photo upload
            $photo_filename = handle_photo_upload($_FILES['photo'] ?? null, $upload_dir);
            if ($photo_filename === false) {
                // Validation failed — flash already set, re-render form
            } else {
                $photo_value = $photo_filename ?? 'default.png';
                
                try {
                    // Check duplicates
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
                    $stmt->execute([$username, $email]);
                    if ($stmt->fetch()) {
                        set_flash('danger', 'Username or email already exists.');
                        // Clean up uploaded photo if duplicate
                        if ($photo_filename) delete_counselor_photo($photo_filename, $upload_dir);
                    } else {
                        // Insert transaction
                        $pdo->beginTransaction();
                        $hashed = password_hash($password, PASSWORD_DEFAULT);
                        
                        $userStmt = $pdo->prepare("INSERT INTO users (username, password, role, email) VALUES (?, ?, 'counselor', ?)");
                        $userStmt->execute([$username, $hashed, $email]);
                        $user_id = $pdo->lastInsertId();
                        
                        $counselorStmt = $pdo->prepare("INSERT INTO counselors (user_id, full_name, specialization, phone, photo) VALUES (?, ?, ?, ?, ?)");
                        $counselorStmt->execute([$user_id, $full_name, $specialization, $phone, $photo_value]);
                        
                        $pdo->commit();
                        set_flash('success', 'Counselor account and profile created.');
                        redirect('counselors.php');
                    }
                } catch (\Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    if ($photo_filename) delete_counselor_photo($photo_filename, $upload_dir);
                    set_flash('danger', 'Error adding counselor: ' . $e->getMessage());
                }
            }
        }
    } elseif ($form_action === 'edit' && $counselor_id > 0) {
        if (empty($email) || empty($full_name) || empty($specialization)) {
            set_flash('danger', 'Please fill in all required fields.');
        } else {
            // Handle photo upload
            $photo_filename = handle_photo_upload($_FILES['photo'] ?? null, $upload_dir);
            if ($photo_filename === false) {
                // Validation failed — flash already set
            } else {
                try {
                    // Check duplicates for email (ignoring current counselor)
                    $stmt = $pdo->prepare("SELECT c.id FROM counselors c JOIN users u ON c.user_id = u.id WHERE u.email = ? AND c.id != ?");
                    $stmt->execute([$email, $counselor_id]);
                    if ($stmt->fetch()) {
                        set_flash('danger', 'Email is already in use by another user.');
                        if ($photo_filename) delete_counselor_photo($photo_filename, $upload_dir);
                    } else {
                        $pdo->beginTransaction();
                        
                        // Get user ID and current photo
                        $uidStmt = $pdo->prepare("SELECT user_id, photo FROM counselors WHERE id = ?");
                        $uidStmt->execute([$counselor_id]);
                        $row = $uidStmt->fetch();
                        $user_id = $row['user_id'];
                        $old_photo = $row['photo'];
                        
                        // Update user email
                        $upUser = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                        $upUser->execute([$email, $user_id]);
                        
                        // Build counselor update
                        if ($photo_filename) {
                            // Delete old photo from disk
                            delete_counselor_photo($old_photo, $upload_dir);
                            $upCounselor = $pdo->prepare("UPDATE counselors SET full_name = ?, specialization = ?, phone = ?, photo = ? WHERE id = ?");
                            $upCounselor->execute([$full_name, $specialization, $phone, $photo_filename, $counselor_id]);
                        } else {
                            $upCounselor = $pdo->prepare("UPDATE counselors SET full_name = ?, specialization = ?, phone = ? WHERE id = ?");
                            $upCounselor->execute([$full_name, $specialization, $phone, $counselor_id]);
                        }
                        
                        $pdo->commit();
                        set_flash('success', 'Counselor details updated.');
                        redirect('counselors.php');
                    }
                } catch (\Exception $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    if ($photo_filename) delete_counselor_photo($photo_filename, $upload_dir);
                    set_flash('danger', 'Error updating counselor: ' . $e->getMessage());
                }
            }
        }
    }
}

// 2. Fetch specific counselor for Edit Mode
if ($action === 'edit' && $counselor_id > 0) {
    $stmt = $pdo->prepare("
        SELECT c.*, u.username, u.email 
        FROM counselors c
        JOIN users u ON c.user_id = u.id
        WHERE c.id = ?
    ");
    $stmt->execute([$counselor_id]);
    $edit_counselor = $stmt->fetch();
    
    if (!$edit_counselor) {
        set_flash('danger', 'Counselor record not found.');
        redirect('counselors.php');
    }
}

// 3. Process Delete Action
if ($action === 'delete' && $counselor_id > 0) {
    try {
        // Find user_id and photo first
        $uidStmt = $pdo->prepare("SELECT user_id, photo FROM counselors WHERE id = ?");
        $uidStmt->execute([$counselor_id]);
        $row = $uidStmt->fetch();
        
        if ($row) {
            // Delete photo from disk
            delete_counselor_photo($row['photo'], $upload_dir);
            
            // Delete from users (cascade takes care of counselor profile)
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$row['user_id']]);
            set_flash('success', 'Counselor account and profile deleted.');
        } else {
            set_flash('danger', 'User profile not found.');
        }
    } catch (\Exception $e) {
        set_flash('danger', 'Error deleting counselor: ' . $e->getMessage());
    }
    redirect('counselors.php');
}

// 4. Fetch all counselors for list view
$counselorsList = $pdo->query("
    SELECT c.*, u.email, u.username 
    FROM counselors c
    JOIN users u ON c.user_id = u.id
    ORDER BY c.created_at DESC
")->fetchAll();

require_once '../includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-6">
        <h1 class="fw-bold">Manage Counselors</h1>
        <p class="text-muted">Register system counselors, update medical profiles, and manage records.</p>
    </div>
    <div class="col-sm-6 text-sm-end">
        <?php if ($action !== 'add' && $action !== 'edit'): ?>
            <a href="counselors.php?action=add" class="btn btn-primary-custom">
                <i class="bi bi-person-badge me-1"></i> Register Counselor
            </a>
        <?php else: ?>
            <a href="counselors.php" class="btn btn-outline-secondary" style="border-radius:8px;">
                <i class="bi bi-arrow-left me-1"></i> Back to Counselor List
            </a>
        <?php endif; ?>
    </div>
</div>

<div class="row">
    <!-- CRUD FORMS (Add / Edit) -->
    <?php if ($action === 'add' || $action === 'edit'): ?>
        <div class="col-lg-7 mx-auto">
            <div class="card card-glass border-0 p-4 shadow-sm">
                <h4 class="fw-bold mb-4">
                    <i class="bi <?php echo ($action === 'edit') ? 'bi-person-gear' : 'bi-person-badge-fill'; ?> text-primary me-2"></i>
                    <?php echo ($action === 'edit') ? 'Edit Counselor Profile' : 'Register New Counselor'; ?>
                </h4>
                
                <form action="counselors.php<?php echo ($action === 'edit') ? '?action=edit&id=' . $counselor_id : ''; ?>" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="form_action" value="<?php echo h($action); ?>">
                    
                    <!-- Profile Photo Upload Section -->
                    <div class="mb-4 text-center">
                        <label class="form-label fw-semibold d-block">Profile Photo</label>
                        <div class="photo-preview-container mb-2">
                            <?php
                            $current_photo = ($action === 'edit' && !empty($edit_counselor['photo'])) 
                                ? $edit_counselor['photo'] 
                                : 'default.png';
                            $photo_url = '../uploads/counselors/' . h($current_photo);
                            ?>
                            <img id="photoPreview" src="<?php echo $photo_url; ?>" 
                                 alt="Counselor Photo" 
                                 class="avatar-photo avatar-photo-xl shadow-sm">
                        </div>
                        <div>
                            <label for="photoInput" class="btn btn-outline-secondary btn-sm photo-upload-label" style="border-radius:8px;">
                                <i class="bi bi-camera me-1"></i> <?php echo ($action === 'edit') ? 'Change Photo' : 'Upload Photo'; ?>
                            </label>
                            <input type="file" name="photo" id="photoInput" class="d-none" accept=".jpg,.jpeg,.png,.webp">
                            <div class="text-muted small mt-1" style="font-size:0.7rem;">JPG, PNG, or WEBP • Max 2MB</div>
                        </div>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label for="full_name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="full_name" id="full_name" class="form-control"  value="<?php echo ($action === 'edit') ? h($edit_counselor['full_name']) : ''; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="specialization" class="form-label fw-semibold">Specialization <span class="text-danger">*</span></label>
                            <input type="text" name="specialization" id="specialization" class="form-control"  value="<?php echo ($action === 'edit') ? h($edit_counselor['specialization']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="row g-3 mb-3">
                        <?php if ($action === 'add'): ?>
                            <div class="col-md-6">
                                <label for="username" class="form-label fw-semibold">Account Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" id="username" class="form-control"  required>
                            </div>
                        <?php endif; ?>
                        <div class="<?php echo ($action === 'add') ? 'col-md-6' : 'col-12'; ?>">
                            <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" id="email" class="form-control"  value="<?php echo ($action === 'edit') ? h($edit_counselor['email']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="phone" class="form-label fw-semibold">Phone Number</label>
                        <input type="text" name="phone" id="phone" class="form-control"  value="<?php echo ($action === 'edit') ? h($edit_counselor['phone']) : ''; ?>">
                    </div>

                    <?php if ($action === 'add'): ?>
                        <div class="mb-4">
                            <label for="password" class="form-label fw-semibold">Password <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <input type="password" name="password" id="password" class="form-control border-end-0" required>
                                <span class="input-group-text bg-white border-start-0 text-muted toggle-password" style="cursor: pointer;" data-target="password">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <a href="counselors.php" class="btn btn-outline-secondary" style="border-radius:8px;">Cancel</a>
                        <button type="submit" class="btn btn-primary-custom">
                            <i class="bi bi-check2-circle"></i> <?php echo ($action === 'edit') ? 'Update Profile' : 'Register Counselor'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <!-- LIST VIEW -->
    <?php else: ?>
        <div class="col-12">
            <div class="card card-glass border-0 p-4 shadow-sm">
                <h4 class="fw-bold mb-3"><i class="bi bi-person-video2 text-primary me-2"></i> Active Counselor Directory</h4>
                
                <?php if (count($counselorsList) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Counselor Details</th>
                                    <th>Specialization</th>
                                    <th>Phone</th>
                                    <th>Registered Date</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($counselorsList as $row): ?>
                                    <?php 
                                    $photo_file = !empty($row['photo']) ? $row['photo'] : 'default.png';
                                    $photo_src = '../uploads/counselors/' . h($photo_file);
                                    ?>
                                    <tr>
                                        <td>
                                            <img src="<?php echo $photo_src; ?>" alt="<?php echo h($row['full_name']); ?>" class="avatar-photo avatar-photo-md">
                                        </td>
                                        <td>
                                            <span class="fw-bold text-dark d-block">Dr. <?php echo h($row['full_name']); ?></span>
                                            <span class="text-muted small" style="font-size:0.75rem;">User: <?php echo h($row['username']); ?> | <?php echo h($row['email']); ?></span>
                                        </td>
                                        <td class="fw-semibold text-secondary small"><?php echo h($row['specialization']); ?></td>
                                        <td class="small text-muted"><?php echo !empty($row['phone']) ? h($row['phone']) : '-'; ?></td>
                                        <td class="small text-muted"><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                                        <td class="text-center">
                                            <div class="d-flex justify-content-center gap-2">
                                                <a href="counselors.php?action=edit&id=<?php echo $row['id']; ?>" class="text-primary fs-5" title="Edit Profile">
                                                    <i class="bi bi-pencil-square"></i>
                                                </a>
                                                <a href="counselors.php?action=delete&id=<?php echo $row['id']; ?>" class="text-danger fs-5" onclick="return confirm('Are you sure you want to delete this counselor? They will be removed from all assigned students!');" title="Delete Counselor">
                                                    <i class="bi bi-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted small text-center py-4 mb-0">No counselors registered in the system.</p>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Photo Preview JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const photoInput = document.getElementById('photoInput');
    const photoPreview = document.getElementById('photoPreview');
    
    if (photoInput && photoPreview) {
        photoInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            // Client-side validation
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
            if (!allowedTypes.includes(file.type)) {
                alert('Invalid file type. Only JPG, PNG, and WEBP images are allowed.');
                photoInput.value = '';
                return;
            }
            
            const maxSize = 2 * 1024 * 1024; // 2MB
            if (file.size > maxSize) {
                alert('File size exceeds 2MB limit.');
                photoInput.value = '';
                return;
            }
            
            // Show preview
            const reader = new FileReader();
            reader.onload = function(event) {
                photoPreview.src = event.target.result;
            };
            reader.readAsDataURL(file);
        });
    }
});
</script>

<?php require_once '../includes/footer.php'; ?>
