<?php
require_once 'includes/config.php';
require_once 'includes/language.php';
requireAuth();

$pageTitle = __('user_profile');
$user = getCurrentUser();
$success = $_GET['success'] ?? '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_profile':
                $profileData = [
                    'fullName' => sanitize($_POST['fullName'] ?? ''),
                    'email' => sanitize($_POST['email'] ?? ''),
                    'phone' => sanitize($_POST['phone'] ?? ''),
                    'address' => sanitize($_POST['address'] ?? '')
                ];
                
                // TODO: Update user profile via API
                $success = __('profile_updated_success');
                break;
                
            case 'change_password':
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    $error = __('all_password_fields_required');
                } elseif ($newPassword !== $confirmPassword) {
                    $error = __('passwords_do_not_match');
                } elseif (strlen($newPassword) < 6) {
                    $error = __('password_minimum_length');
                } else {
                    // TODO: Verify current password and update
                    $success = __('password_changed_success');
                }
                break;
        }
        
        if ($success) {
            header("Location: profile.php?success=" . urlencode($success));
            exit();
        }
    } else {
        $error = __('invalid_csrf_token');
    }
}

// Mock user data (in real system, fetch from API)
$profileData = [
    'id' => $user['id'] ?? 'user123',
    'fullName' => $user['fullName'] ?? 'John Doe',
    'email' => $user['email'] ?? 'john@example.com',
    'role' => $user['role'] ?? 'DOCTOR',
    'phone' => '+1 (555) 123-4567',
    'address' => '123 Medical Street, Health City',
    'joinDate' => '2023-01-15',
    'lastLogin' => '2024-08-24 10:30:00',
    'avatar' => null
];

// Start output buffering for page content
ob_start();
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-gray-800">
            <i class="bi bi-person-circle"></i>
            <?php echo __('user_profile'); ?>
        </h1>
        <div>
            <span class="badge bg-<?php echo getRoleBadgeClass($profileData['role']); ?> fs-6">
                <?php echo getRoleDisplayName($profileData['role']); ?>
            </span>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo htmlspecialchars(__('close')); ?>"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo htmlspecialchars(__('close')); ?>"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Profile Info -->
        <div class="col-lg-4">
            <div class="card shadow mb-4">
                <div class="card-body text-center">
                    <div class="mb-3">
                        <?php if ($profileData['avatar']): ?>
                            <img src="<?php echo htmlspecialchars($profileData['avatar']); ?>" 
                                 class="rounded-circle" width="120" height="120" alt="<?php echo htmlspecialchars(__('profile_picture_alt')); ?>">
                        <?php else: ?>
                            <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" 
                                 style="width: 120px; height: 120px; font-size: 3rem;">
                                <i class="bi bi-person"></i>
                            </div>
                        <?php endif; ?>
                    </div>
                    <h5 class="card-title"><?php echo htmlspecialchars($profileData['fullName']); ?></h5>
                    <p class="text-muted"><?php echo getRoleDisplayName($profileData['role']); ?></p>
                    <p class="card-text">
                        <small class="text-muted">
                            <i class="bi bi-calendar-plus"></i>
                            <?php echo __('member_since'); ?> <?php echo date('F Y', strtotime($profileData['joinDate'])); ?>
                        </small>
                    </p>
                    <button class="btn btn-primary btn-sm" onclick="document.getElementById('avatar-upload').click();">
                        <i class="bi bi-camera"></i> <?php echo __('change_photo'); ?>
                    </button>
                    <input type="file" id="avatar-upload" accept="image/*" style="display: none;" onchange="handleAvatarUpload(this)">
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-bar-chart"></i>
                        <?php echo __('activity_summary'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="border-end">
                                <div class="h5 text-primary">15</div>
                                <small class="text-muted"><?php echo __("patients_seen"); ?></small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="h5 text-success">8</div>
                            <small class="text-muted"><?php echo __('appointments'); ?></small>
                        </div>
                        <div class="col-6">
                            <div class="border-end">
                                <div class="h5 text-info">12</div>
                                <small class="text-muted"><?php echo __("prescriptions"); ?></small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="h5 text-warning">5</div>
                            <small class="text-muted"><?php echo __("this_week"); ?></small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Profile Forms -->
        <div class="col-lg-8">
            <!-- Profile Information -->
            <div class="card shadow mb-4">
                <div class="card-header">
                        <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-person-gear"></i>
                        <?php echo __('profile_information'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="fullName" class="form-label"><?php echo __("full_name"); ?></label>
                                    <input type="text" class="form-control" id="fullName" name="fullName" 
                                           value="<?php echo htmlspecialchars($profileData['fullName']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label"><?php echo __("email_address"); ?></label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($profileData['email']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label"><?php echo __('phone_number'); ?></label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($profileData['phone']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="role" class="form-label"><?php echo __('role'); ?></label>
                                    <input type="text" class="form-control" id="role" name="role" 
                                           value="<?php echo getRoleDisplayName($profileData['role']); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label"><?php echo __('address'); ?></label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($profileData['address']); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> <?php echo __('update_profile'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-key"></i>
                        <?php echo __('change_password'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label"><?php echo __('current_password'); ?></label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label"><?php echo __('new_password'); ?></label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label"><?php echo __('confirm_new_password'); ?></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-key"></i> <?php echo __('change_password'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Account Information -->
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-info-circle"></i>
                        <?php echo __('account_information'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong><?php echo __('user_id'); ?>:</strong></td>
                                    <td><?php echo htmlspecialchars($profileData['id']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo __('join_date'); ?>:</strong></td>
                                    <td><?php echo date('F j, Y', strtotime($profileData['joinDate'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo __('last_login_label'); ?>:</strong></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($profileData['lastLogin'])); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong><?php echo __('account_status'); ?>:</strong></td>
                                    <td><span class="badge bg-success"><?php echo __('active'); ?></span></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo __('email_verified'); ?>:</strong></td>
                                    <td><span class="badge bg-success"><?php echo __('verified'); ?></span></td>
                                </tr>
                                <tr>
                                    <td><strong><?php echo __('twofa_enabled'); ?>:</strong></td>
                                    <td><span class="badge bg-secondary"><?php echo __('disabled'); ?></span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                        <div class="mt-3">
                        <h6><?php echo __('preferences'); ?></h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="emailNotifications" checked>
                                    <label class="form-check-label" for="emailNotifications">
                                        <?php echo __('email_notifications'); ?>
                                    </label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="appointmentReminders" checked>
                                    <label class="form-check-label" for="appointmentReminders">
                                        <?php echo __('appointment_reminders'); ?>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="weeklyReports">
                                    <label class="form-check-label" for="weeklyReports">
                                        <?php echo __('weekly_reports'); ?>
                                    </label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="systemAlerts" checked>
                                    <label class="form-check-label" for="systemAlerts">
                                        <?php echo __('system_alerts'); ?>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-outline-primary btn-sm mt-2">
                            <i class="bi bi-save"></i> <?php echo __('save_preferences'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function handleAvatarUpload(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            // TODO: Upload avatar to server
            alert('<?php echo addslashes(__('avatar_upload_soon')); ?>');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('<?php echo addslashes(__("passwords_do_not_match")); ?>');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
