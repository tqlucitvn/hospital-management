<?php
require_once 'includes/config.php';
requireAuth();

$pageTitle = 'User Profile';
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
                $success = 'Profile updated successfully!';
                break;
                
            case 'change_password':
                $currentPassword = $_POST['current_password'] ?? '';
                $newPassword = $_POST['new_password'] ?? '';
                $confirmPassword = $_POST['confirm_password'] ?? '';
                
                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    $error = 'All password fields are required.';
                } elseif ($newPassword !== $confirmPassword) {
                    $error = 'New passwords do not match.';
                } elseif (strlen($newPassword) < 6) {
                    $error = 'New password must be at least 6 characters long.';
                } else {
                    // TODO: Verify current password and update
                    $success = 'Password changed successfully!';
                }
                break;
        }
        
        if ($success) {
            header("Location: profile.php?success=" . urlencode($success));
            exit();
        }
    } else {
        $error = 'Invalid CSRF token.';
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
            User Profile
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
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle"></i>
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
                                 class="rounded-circle" width="120" height="120" alt="Profile Picture">
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
                            Member since <?php echo date('F Y', strtotime($profileData['joinDate'])); ?>
                        </small>
                    </p>
                    <button class="btn btn-primary btn-sm" onclick="document.getElementById('avatar-upload').click();">
                        <i class="bi bi-camera"></i> Change Photo
                    </button>
                    <input type="file" id="avatar-upload" accept="image/*" style="display: none;" onchange="handleAvatarUpload(this)">
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-bar-chart"></i>
                        Activity Summary
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="border-end">
                                <div class="h5 text-primary">15</div>
                                <small class="text-muted">Patients Seen</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="h5 text-success">8</div>
                            <small class="text-muted">Appointments</small>
                        </div>
                        <div class="col-6">
                            <div class="border-end">
                                <div class="h5 text-info">12</div>
                                <small class="text-muted">Prescriptions</small>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="h5 text-warning">5</div>
                            <small class="text-muted">This Week</small>
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
                        Profile Information
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="fullName" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="fullName" name="fullName" 
                                           value="<?php echo htmlspecialchars($profileData['fullName']); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email Address</label>
                                    <input type="email" class="form-control" id="email" name="email" 
                                           value="<?php echo htmlspecialchars($profileData['email']); ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="phone" class="form-label">Phone Number</label>
                                    <input type="tel" class="form-control" id="phone" name="phone" 
                                           value="<?php echo htmlspecialchars($profileData['phone']); ?>">
                                </div>
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role</label>
                                    <input type="text" class="form-control" id="role" name="role" 
                                           value="<?php echo getRoleDisplayName($profileData['role']); ?>" readonly>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3"><?php echo htmlspecialchars($profileData['address']); ?></textarea>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update Profile
                        </button>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-key"></i>
                        Change Password
                    </h6>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCsrfToken(); ?>">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="6">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="6">
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-key"></i> Change Password
                        </button>
                    </form>
                </div>
            </div>

            <!-- Account Information -->
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-info-circle"></i>
                        Account Information
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>User ID:</strong></td>
                                    <td><?php echo htmlspecialchars($profileData['id']); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Join Date:</strong></td>
                                    <td><?php echo date('F j, Y', strtotime($profileData['joinDate'])); ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Last Login:</strong></td>
                                    <td><?php echo date('M j, Y g:i A', strtotime($profileData['lastLogin'])); ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless">
                                <tr>
                                    <td><strong>Account Status:</strong></td>
                                    <td><span class="badge bg-success">Active</span></td>
                                </tr>
                                <tr>
                                    <td><strong>Email Verified:</strong></td>
                                    <td><span class="badge bg-success">Verified</span></td>
                                </tr>
                                <tr>
                                    <td><strong>2FA Enabled:</strong></td>
                                    <td><span class="badge bg-secondary">Disabled</span></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <h6>Preferences</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="emailNotifications" checked>
                                    <label class="form-check-label" for="emailNotifications">
                                        Email Notifications
                                    </label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="appointmentReminders" checked>
                                    <label class="form-check-label" for="appointmentReminders">
                                        Appointment Reminders
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="weeklyReports">
                                    <label class="form-check-label" for="weeklyReports">
                                        Weekly Reports
                                    </label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="systemAlerts" checked>
                                    <label class="form-check-label" for="systemAlerts">
                                        System Alerts
                                    </label>
                                </div>
                            </div>
                        </div>
                        <button class="btn btn-outline-primary btn-sm mt-2">
                            <i class="bi bi-save"></i> Save Preferences
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
            alert('Avatar upload functionality will be implemented soon!');
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Password confirmation validation
document.getElementById('confirm_password').addEventListener('input', function() {
    const newPassword = document.getElementById('new_password').value;
    const confirmPassword = this.value;
    
    if (newPassword !== confirmPassword) {
        this.setCustomValidity('Passwords do not match');
    } else {
        this.setCustomValidity('');
    }
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
