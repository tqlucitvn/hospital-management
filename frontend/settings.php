<?php
require_once 'includes/config.php';
requireRole('ADMIN');

$pageTitle = 'System Settings';
$user = getCurrentUser();
$success = $_GET['success'] ?? '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'general_settings':
                // TODO: Save general settings
                $success = 'General settings updated successfully!';
                break;
            case 'notification_settings':
                // TODO: Save notification settings
                $success = 'Notification settings updated successfully!';
                break;
            case 'security_settings':
                // TODO: Save security settings
                $success = 'Security settings updated successfully!';
                break;
            case 'backup_data':
                // TODO: Implement backup
                $success = 'System backup completed successfully!';
                break;
        }
        header("Location: settings.php?success=" . urlencode($success));
        exit();
    }
}

// Mock current settings (in real system, load from database)
$settings = [
    'general' => [
        'hospital_name' => 'Central Hospital',
        'hospital_address' => '123 Medical Street, Health City',
        'hospital_phone' => '+1 (555) 123-4567',
        'hospital_email' => 'info@centralhospital.com',
        'timezone' => 'America/New_York',
        'language' => 'en',
        'date_format' => 'Y-m-d',
        'time_format' => '24h'
    ],
    'appointment' => [
        'slot_duration' => 30,
        'advance_booking_days' => 30,
        'cancellation_hours' => 24,
        'reminder_hours' => 24
    ],
    'notification' => [
        'email_enabled' => true,
        'sms_enabled' => false,
        'appointment_reminders' => true,
        'prescription_alerts' => true,
        'system_notifications' => true
    ],
    'security' => [
        'session_timeout' => 480,
        'password_complexity' => true,
        'two_factor_auth' => false,
        'login_attempts' => 5
    ]
];

// Start output buffering for page content
ob_start();
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-gray-800">
            <i class="bi bi-gear"></i>
            System Settings
        </h1>
        <div>
            <button class="btn btn-success" onclick="backupSystem()">
                <i class="bi bi-download"></i> Backup System
            </button>
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

    <!-- Settings Tabs -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="settingsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                                <i class="bi bi-building"></i> General
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="appointments-tab" data-bs-toggle="tab" data-bs-target="#appointments" type="button" role="tab">
                                <i class="bi bi-calendar-check"></i> Appointments
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                                <i class="bi bi-bell"></i> Notifications
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                                <i class="bi bi-shield-check"></i> Security
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                                <i class="bi bi-cpu"></i> System
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="settingsTabContent">
                        <!-- General Settings -->
                        <div class="tab-pane fade show active" id="general" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="general_settings">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="hospital_name" class="form-label">Hospital Name</label>
                                            <input type="text" class="form-control" id="hospital_name" name="hospital_name" 
                                                   value="<?php echo htmlspecialchars($settings['general']['hospital_name']); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="hospital_address" class="form-label">Address</label>
                                            <textarea class="form-control" id="hospital_address" name="hospital_address" rows="3"><?php echo htmlspecialchars($settings['general']['hospital_address']); ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="hospital_phone" class="form-label">Phone</label>
                                            <input type="tel" class="form-control" id="hospital_phone" name="hospital_phone" 
                                                   value="<?php echo htmlspecialchars($settings['general']['hospital_phone']); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="hospital_email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="hospital_email" name="hospital_email" 
                                                   value="<?php echo htmlspecialchars($settings['general']['hospital_email']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="timezone" class="form-label">Timezone</label>
                                            <select class="form-control" id="timezone" name="timezone">
                                                <option value="America/New_York" <?php echo $settings['general']['timezone'] === 'America/New_York' ? 'selected' : ''; ?>>Eastern Time</option>
                                                <option value="America/Chicago" <?php echo $settings['general']['timezone'] === 'America/Chicago' ? 'selected' : ''; ?>>Central Time</option>
                                                <option value="America/Denver" <?php echo $settings['general']['timezone'] === 'America/Denver' ? 'selected' : ''; ?>>Mountain Time</option>
                                                <option value="America/Los_Angeles" <?php echo $settings['general']['timezone'] === 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="language" class="form-label">Language</label>
                                            <select class="form-control" id="language" name="language">
                                                <option value="en" <?php echo $settings['general']['language'] === 'en' ? 'selected' : ''; ?>>English</option>
                                                <option value="vi" <?php echo $settings['general']['language'] === 'vi' ? 'selected' : ''; ?>>Tiếng Việt</option>
                                                <option value="es" <?php echo $settings['general']['language'] === 'es' ? 'selected' : ''; ?>>Español</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="date_format" class="form-label">Date Format</label>
                                            <select class="form-control" id="date_format" name="date_format">
                                                <option value="Y-m-d" <?php echo $settings['general']['date_format'] === 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD</option>
                                                <option value="m/d/Y" <?php echo $settings['general']['date_format'] === 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY</option>
                                                <option value="d/m/Y" <?php echo $settings['general']['date_format'] === 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="time_format" class="form-label">Time Format</label>
                                            <select class="form-control" id="time_format" name="time_format">
                                                <option value="24h" <?php echo $settings['general']['time_format'] === '24h' ? 'selected' : ''; ?>>24 Hour</option>
                                                <option value="12h" <?php echo $settings['general']['time_format'] === '12h' ? 'selected' : ''; ?>>12 Hour (AM/PM)</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Save General Settings
                                </button>
                            </form>
                        </div>

                        <!-- Appointment Settings -->
                        <div class="tab-pane fade" id="appointments" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="appointment_settings">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="slot_duration" class="form-label">Appointment Slot Duration (minutes)</label>
                                            <select class="form-control" id="slot_duration" name="slot_duration">
                                                <option value="15" <?php echo $settings['appointment']['slot_duration'] == 15 ? 'selected' : ''; ?>>15 minutes</option>
                                                <option value="30" <?php echo $settings['appointment']['slot_duration'] == 30 ? 'selected' : ''; ?>>30 minutes</option>
                                                <option value="45" <?php echo $settings['appointment']['slot_duration'] == 45 ? 'selected' : ''; ?>>45 minutes</option>
                                                <option value="60" <?php echo $settings['appointment']['slot_duration'] == 60 ? 'selected' : ''; ?>>60 minutes</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="advance_booking_days" class="form-label">Advance Booking (days)</label>
                                            <input type="number" class="form-control" id="advance_booking_days" name="advance_booking_days" 
                                                   value="<?php echo $settings['appointment']['advance_booking_days']; ?>" min="1" max="365">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="cancellation_hours" class="form-label">Cancellation Notice (hours)</label>
                                            <input type="number" class="form-control" id="cancellation_hours" name="cancellation_hours" 
                                                   value="<?php echo $settings['appointment']['cancellation_hours']; ?>" min="1" max="168">
                                        </div>
                                        <div class="mb-3">
                                            <label for="reminder_hours" class="form-label">Reminder Notice (hours)</label>
                                            <input type="number" class="form-control" id="reminder_hours" name="reminder_hours" 
                                                   value="<?php echo $settings['appointment']['reminder_hours']; ?>" min="1" max="168">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Save Appointment Settings
                                </button>
                            </form>
                        </div>

                        <!-- Notification Settings -->
                        <div class="tab-pane fade" id="notifications" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="notification_settings">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Notification Channels</h6>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="email_enabled" name="email_enabled" 
                                                   <?php echo $settings['notification']['email_enabled'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_enabled">
                                                <i class="bi bi-envelope"></i> Email Notifications
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="sms_enabled" name="sms_enabled" 
                                                   <?php echo $settings['notification']['sms_enabled'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="sms_enabled">
                                                <i class="bi bi-phone"></i> SMS Notifications
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Notification Types</h6>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="appointment_reminders" name="appointment_reminders" 
                                                   <?php echo $settings['notification']['appointment_reminders'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="appointment_reminders">
                                                <i class="bi bi-calendar-check"></i> Appointment Reminders
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="prescription_alerts" name="prescription_alerts" 
                                                   <?php echo $settings['notification']['prescription_alerts'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="prescription_alerts">
                                                <i class="bi bi-prescription2"></i> Prescription Alerts
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="system_notifications" name="system_notifications" 
                                                   <?php echo $settings['notification']['system_notifications'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="system_notifications">
                                                <i class="bi bi-gear"></i> System Notifications
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Save Notification Settings
                                </button>
                            </form>
                        </div>

                        <!-- Security Settings -->
                        <div class="tab-pane fade" id="security" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="security_settings">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="session_timeout" class="form-label">Session Timeout (minutes)</label>
                                            <select class="form-control" id="session_timeout" name="session_timeout">
                                                <option value="30" <?php echo $settings['security']['session_timeout'] == 30 ? 'selected' : ''; ?>>30 minutes</option>
                                                <option value="60" <?php echo $settings['security']['session_timeout'] == 60 ? 'selected' : ''; ?>>1 hour</option>
                                                <option value="240" <?php echo $settings['security']['session_timeout'] == 240 ? 'selected' : ''; ?>>4 hours</option>
                                                <option value="480" <?php echo $settings['security']['session_timeout'] == 480 ? 'selected' : ''; ?>>8 hours</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="login_attempts" class="form-label">Maximum Login Attempts</label>
                                            <input type="number" class="form-control" id="login_attempts" name="login_attempts" 
                                                   value="<?php echo $settings['security']['login_attempts']; ?>" min="3" max="10">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="password_complexity" name="password_complexity" 
                                                   <?php echo $settings['security']['password_complexity'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="password_complexity">
                                                <i class="bi bi-key"></i> Enforce Password Complexity
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="two_factor_auth" name="two_factor_auth" 
                                                   <?php echo $settings['security']['two_factor_auth'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="two_factor_auth">
                                                <i class="bi bi-shield-lock"></i> Two-Factor Authentication
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> Save Security Settings
                                </button>
                            </form>
                        </div>

                        <!-- System Info -->
                        <div class="tab-pane fade" id="system" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>System Information</h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong>PHP Version:</strong></td>
                                            <td><?php echo PHP_VERSION; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Server Software:</strong></td>
                                            <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Database:</strong></td>
                                            <td>PostgreSQL (Connected)</td>
                                        </tr>
                                        <tr>
                                            <td><strong>System Uptime:</strong></td>
                                            <td>12 days, 5 hours</td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6>System Actions</h6>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-success" onclick="backupSystem()">
                                            <i class="bi bi-download"></i> Create System Backup
                                        </button>
                                        <button class="btn btn-warning" onclick="clearCache()">
                                            <i class="bi bi-arrow-clockwise"></i> Clear System Cache
                                        </button>
                                        <button class="btn btn-info" onclick="checkUpdates()">
                                            <i class="bi bi-arrow-up-circle"></i> Check for Updates
                                        </button>
                                        <button class="btn btn-danger" onclick="restartServices()">
                                            <i class="bi bi-arrow-repeat"></i> Restart Services
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function backupSystem() {
    if (confirm('Create a complete system backup? This may take several minutes.')) {
        alert('System backup initiated. You will be notified when complete.');
        // TODO: Implement actual backup functionality
    }
}

function clearCache() {
    if (confirm('Clear all system cache? This will improve performance but may temporarily slow down the system.')) {
        alert('System cache cleared successfully!');
        // TODO: Implement cache clearing
    }
}

function checkUpdates() {
    alert('Checking for system updates...\nNo updates available. System is up to date.');
    // TODO: Implement update checking
}

function restartServices() {
    if (confirm('Restart all microservices? This will temporarily disrupt system operations.')) {
        alert('Services restart initiated. System will be back online in 2-3 minutes.');
        // TODO: Implement service restart
    }
}
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
