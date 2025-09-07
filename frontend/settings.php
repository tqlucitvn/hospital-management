<?php
require_once 'includes/config.php';
require_once 'includes/language.php';
requireRole('ADMIN');

$pageTitle = __('system_settings');
$user = getCurrentUser();
$success = $_GET['success'] ?? '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'general_settings':
                // Save general settings (basic server-side handling)
                // Persist selected language into session so UI switches immediately
                if (isset($_POST['language'])) {
                    $lang = $_POST['language'];
                    if (in_array($lang, ['vi', 'en'])) {
                        // setLanguage is defined in includes/language.php
                        setLanguage($lang);
                    }
                }
                // TODO: Persist other general settings to database/config
                $success = __('general_settings_updated');
                break;
            case 'notification_settings':
                // TODO: Save notification settings
                $success = __('notification_settings_updated');
                break;
            case 'security_settings':
                // TODO: Save security settings
                $success = __('security_settings_updated');
                break;
            case 'backup_data':
                // TODO: Implement backup
                $success = __('system_backup_completed');
                break;
        }
        header("Location: settings.php?success=" . urlencode($success));
        exit();
    }
}

// Mock current settings (in real system, load from database)
$settings = [
    'general' => [
        'hospital_name' => 'Bệnh viện Tâm An',
        'hospital_address' => '123 Đường Y Tế, Quận 1, TP.HCM',
        'hospital_phone' => '+84 (028) 123-4567',
        'hospital_email' => 'info@benhvientaman.com',
        'timezone' => 'Asia/Ho_Chi_Minh',
        'language' => 'vi',
        'date_format' => 'd/m/Y',
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
            <?php echo __('system_settings'); ?>
        </h1>
        <div>
            <button class="btn btn-success" onclick="backupSystem()">
                <i class="bi bi-download"></i> <?php echo __('create_system_backup'); ?>
            </button>
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

    <!-- Settings Tabs -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="settingsTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">
                                <i class="bi bi-building"></i> <?php echo __('general'); ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="appointments-tab" data-bs-toggle="tab" data-bs-target="#appointments" type="button" role="tab">
                                <i class="bi bi-calendar-check"></i> <?php echo __('appointments'); ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="notifications-tab" data-bs-toggle="tab" data-bs-target="#notifications" type="button" role="tab">
                                <i class="bi bi-bell"></i> <?php echo __('notifications'); ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button" role="tab">
                                <i class="bi bi-shield-check"></i> <?php echo __('security'); ?>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="system-tab" data-bs-toggle="tab" data-bs-target="#system" type="button" role="tab">
                                <i class="bi bi-cpu"></i> <?php echo __('system'); ?>
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
                                            <label for="hospital_name" class="form-label"><?php echo __('hospital_name'); ?></label>
                                            <input type="text" class="form-control" id="hospital_name" name="hospital_name" 
                                                   value="<?php echo htmlspecialchars($settings['general']['hospital_name']); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="hospital_address" class="form-label"><?php echo __('address'); ?></label>
                                            <textarea class="form-control" id="hospital_address" name="hospital_address" rows="3"><?php echo htmlspecialchars($settings['general']['hospital_address']); ?></textarea>
                                        </div>
                                        <div class="mb-3">
                                            <label for="hospital_phone" class="form-label"><?php echo __('phone'); ?></label>
                                            <input type="tel" class="form-control" id="hospital_phone" name="hospital_phone" 
                                                   value="<?php echo htmlspecialchars($settings['general']['hospital_phone']); ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="hospital_email" class="form-label"><?php echo __('email'); ?></label>
                                            <input type="email" class="form-control" id="hospital_email" name="hospital_email" 
                                                   value="<?php echo htmlspecialchars($settings['general']['hospital_email']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="timezone" class="form-label"><?php echo __('timezone'); ?></label>
                                            <select class="form-control" id="timezone" name="timezone">
                                                <option value="Asia/Ho_Chi_Minh" <?php echo $settings['general']['timezone'] === 'Asia/Ho_Chi_Minh' ? 'selected' : ''; ?>><?php echo __('vietnam_time'); ?></option>
                                                <option value="Asia/Bangkok" <?php echo $settings['general']['timezone'] === 'Asia/Bangkok' ? 'selected' : ''; ?>><?php echo __('thailand_time'); ?></option>
                                                <option value="Asia/Singapore" <?php echo $settings['general']['timezone'] === 'Asia/Singapore' ? 'selected' : ''; ?>><?php echo __('singapore_time'); ?></option>
                                                <option value="Asia/Tokyo" <?php echo $settings['general']['timezone'] === 'Asia/Tokyo' ? 'selected' : ''; ?>><?php echo __('japan_time'); ?></option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="language" class="form-label"><?php echo __('language'); ?></label>
                                            <select class="form-control" id="language" name="language">
                                                <option value="en" <?php echo $settings['general']['language'] === 'en' ? 'selected' : ''; ?>><?php echo __('english'); ?></option>
                                                <option value="vi" <?php echo $settings['general']['language'] === 'vi' ? 'selected' : ''; ?>><?php echo __('language_vietnamese'); ?></option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="date_format" class="form-label"><?php echo __('date_format'); ?></label>
                                            <select class="form-control" id="date_format" name="date_format">
                                                <option value="Y-m-d" <?php echo $settings['general']['date_format'] === 'Y-m-d' ? 'selected' : ''; ?>><?php echo __('format_yyyy_mm_dd'); ?></option>
                                                <option value="m/d/Y" <?php echo $settings['general']['date_format'] === 'm/d/Y' ? 'selected' : ''; ?>><?php echo __('format_mm_dd_yyyy'); ?></option>
                                                <option value="d/m/Y" <?php echo $settings['general']['date_format'] === 'd/m/Y' ? 'selected' : ''; ?>><?php echo __('format_dd_mm_yyyy'); ?></option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="time_format" class="form-label"><?php echo __('time_format'); ?></label>
                                            <select class="form-control" id="time_format" name="time_format">
                                                <option value="24h" <?php echo $settings['general']['time_format'] === '24h' ? 'selected' : ''; ?>><?php echo __('time_format_24'); ?></option>
                                                <option value="12h" <?php echo $settings['general']['time_format'] === '12h' ? 'selected' : ''; ?>><?php echo __('time_format_12'); ?></option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> <?php echo __('save_general_settings'); ?>
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
                                            <label for="slot_duration" class="form-label"><?php echo __('slot_duration_label'); ?></label>
                                            <select class="form-control" id="slot_duration" name="slot_duration">
                                                <option value="15" <?php echo $settings['appointment']['slot_duration'] == 15 ? 'selected' : ''; ?>><?php echo __('minutes_15'); ?></option>
                                                <option value="30" <?php echo $settings['appointment']['slot_duration'] == 30 ? 'selected' : ''; ?>><?php echo __('minutes_30'); ?></option>
                                                <option value="45" <?php echo $settings['appointment']['slot_duration'] == 45 ? 'selected' : ''; ?>><?php echo __('minutes_45'); ?></option>
                                                <option value="60" <?php echo $settings['appointment']['slot_duration'] == 60 ? 'selected' : ''; ?>><?php echo __('minutes_60'); ?></option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="advance_booking_days" class="form-label"><?php echo __('advance_booking_days_label'); ?></label>
                                            <input type="number" class="form-control" id="advance_booking_days" name="advance_booking_days" 
                                                   value="<?php echo $settings['appointment']['advance_booking_days']; ?>" min="1" max="365">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="cancellation_hours" class="form-label"><?php echo __('cancellation_notice_hours'); ?></label>
                                            <input type="number" class="form-control" id="cancellation_hours" name="cancellation_hours" 
                                                   value="<?php echo $settings['appointment']['cancellation_hours']; ?>" min="1" max="168">
                                        </div>
                                        <div class="mb-3">
                                            <label for="reminder_hours" class="form-label"><?php echo __('reminder_notice_hours'); ?></label>
                                            <input type="number" class="form-control" id="reminder_hours" name="reminder_hours" 
                                                   value="<?php echo $settings['appointment']['reminder_hours']; ?>" min="1" max="168">
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> <?php echo __('save_appointment_settings'); ?>
                                </button>
                            </form>
                        </div>

                        <!-- Notification Settings -->
                        <div class="tab-pane fade" id="notifications" role="tabpanel">
                            <form method="POST">
                                <input type="hidden" name="action" value="notification_settings">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6><?php echo __('notification_channels'); ?></h6>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="email_enabled" name="email_enabled" 
                                                   <?php echo $settings['notification']['email_enabled'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="email_enabled">
                                                <i class="bi bi-envelope"></i> <?php echo __('email_notifications'); ?>
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="sms_enabled" name="sms_enabled" 
                                                   <?php echo $settings['notification']['sms_enabled'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="sms_enabled">
                                                <i class="bi bi-phone"></i> <?php echo __('sms_notifications'); ?>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6><?php echo __('notification_types'); ?></h6>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="appointment_reminders" name="appointment_reminders" 
                                                   <?php echo $settings['notification']['appointment_reminders'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="appointment_reminders">
                                                <i class="bi bi-calendar-check"></i> <?php echo __('appointment_reminders'); ?>
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="prescription_alerts" name="prescription_alerts" 
                                                   <?php echo $settings['notification']['prescription_alerts'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="prescription_alerts">
                                                <i class="bi bi-prescription2"></i> <?php echo __('prescription_alerts'); ?>
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="system_notifications" name="system_notifications" 
                                                   <?php echo $settings['notification']['system_notifications'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="system_notifications">
                                                <i class="bi bi-gear"></i> <?php echo __('system_notifications'); ?>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> <?php echo __('save_notification_settings'); ?>
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
                                            <label for="session_timeout" class="form-label"><?php echo __('session_timeout_label'); ?></label>
                                            <select class="form-control" id="session_timeout" name="session_timeout">
                                                <option value="30" <?php echo $settings['security']['session_timeout'] == 30 ? 'selected' : ''; ?>><?php echo __('minutes_30'); ?></option>
                                                <option value="60" <?php echo $settings['security']['session_timeout'] == 60 ? 'selected' : ''; ?>><?php echo __('one_hour'); ?></option>
                                                <option value="240" <?php echo $settings['security']['session_timeout'] == 240 ? 'selected' : ''; ?>><?php echo __('four_hours'); ?></option>
                                                <option value="480" <?php echo $settings['security']['session_timeout'] == 480 ? 'selected' : ''; ?>><?php echo __('eight_hours'); ?></option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label for="login_attempts" class="form-label"><?php echo __('maximum_login_attempts'); ?></label>
                                            <input type="number" class="form-control" id="login_attempts" name="login_attempts" 
                                                   value="<?php echo $settings['security']['login_attempts']; ?>" min="3" max="10">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="password_complexity" name="password_complexity" 
                                                   <?php echo $settings['security']['password_complexity'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="password_complexity">
                                                <i class="bi bi-key"></i> <?php echo __('enforce_password_complexity'); ?>
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="two_factor_auth" name="two_factor_auth" 
                                                   <?php echo $settings['security']['two_factor_auth'] ? 'checked' : ''; ?>>
                                            <label class="form-check-label" for="two_factor_auth">
                                                <i class="bi bi-shield-lock"></i> <?php echo __('two_factor_auth'); ?>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-save"></i> <?php echo __('save_settings'); ?>
                                </button>
                            </form>
                        </div>

                        <!-- System Info -->
                        <div class="tab-pane fade" id="system" role="tabpanel">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6><?php echo __('system_information'); ?></h6>
                                    <table class="table table-sm">
                                        <tr>
                                            <td><strong><?php echo __('php_version'); ?>:</strong></td>
                                            <td><?php echo PHP_VERSION; ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong><?php echo __('server_software'); ?>:</strong></td>
                                            <td><?php echo $_SERVER['SERVER_SOFTWARE'] ?? __('unknown'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong><?php echo __('database'); ?>:</strong></td>
                                            <td><?php echo __('database_postgresql'); ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong><?php echo __('system_uptime'); ?>:</strong></td>
                                            <td><?php echo __('system_uptime_value'); ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <h6><?php echo __('system_actions'); ?></h6>
                                    <div class="d-grid gap-2">
                                        <button class="btn btn-success" onclick="backupSystem()">
                                            <i class="bi bi-download"></i> <?php echo __('create_system_backup'); ?>
                                        </button>
                                        <button class="btn btn-warning" onclick="clearCache()">
                                            <i class="bi bi-arrow-clockwise"></i> <?php echo __('clear_system_cache'); ?>
                                        </button>
                                        <button class="btn btn-info" onclick="checkUpdates()">
                                            <i class="bi bi-arrow-up-circle"></i> <?php echo __('check_for_updates'); ?>
                                        </button>
                                        <button class="btn btn-danger" onclick="restartServices()">
                                            <i class="bi bi-arrow-repeat"></i> <?php echo __('restart_services'); ?>
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
</div>

<script>
function backupSystem() {
    if (confirm('<?php echo addslashes(__("confirm.create_backup")); ?>')) {
        alert('<?php echo addslashes(__("info.backup_initiated")); ?>');
        // TODO: Implement actual backup functionality
    }
}

function clearCache() {
    if (confirm('<?php echo addslashes(__("confirm.clear_cache")); ?>')) {
        alert('<?php echo addslashes(__("info.cache_cleared")); ?>');
        // TODO: Implement cache clearing
    }
}

function checkUpdates() {
    alert('<?php echo addslashes(__("info.check_updates")); ?>');
    // TODO: Implement update checking
}

function restartServices() {
    if (confirm('<?php echo addslashes(__("confirm.restart_services")); ?>')) {
        alert('<?php echo addslashes(__("info.restart_initiated")); ?>');
        // TODO: Implement service restart
    }
}
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
