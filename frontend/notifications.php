<?php
require_once 'includes/config.php';
require_once 'includes/language.php';
requireAuth();

$pageTitle = __('notifications');
$user = getCurrentUser();

// Get notifications (mock data for now - in real system would be from database)
    $notifications = [
        [
        'id' => 1,
        'type' => 'appointment',
        'title' => __('new_appointment_scheduled'),
    'message' => sprintf(__('appointment_scheduled_for'), 'John Doe', '2:00 PM today'),
    'time' => sprintf(__('minutes_ago'), 2),
        'read' => false,
        'icon' => 'calendar-check',
        'color' => 'success'
    ],
        [
        'id' => 2,
        'type' => 'prescription',
        'title' => __('prescription_ready'),
    'message' => sprintf(__('prescription_ready_message'), '12345'),
        'time' => sprintf(__('minutes_ago'), 15),
        'read' => false,
        'icon' => 'prescription2',
        'color' => 'info'
    ],
        [
        'id' => 3,
        'type' => 'patient',
        'title' => __('new_patient_registration'),
    'message' => sprintf(__('new_patient_registered'), 'Jane Smith'),
        'time' => sprintf(__('hours_ago'), 1),
        'read' => true,
        'icon' => 'person-plus',
        'color' => 'primary'
    ],
        [
        'id' => 4,
        'type' => 'system',
        'title' => __('system_maintenance'),
    'message' => sprintf(__('scheduled_maintenance_time'), 'tonight at 2:00 AM'),
        'time' => sprintf(__('hours_ago'), 3),
        'read' => true,
        'icon' => 'gear',
        'color' => 'warning'
    ],
        [
        'id' => 5,
        'type' => 'appointment',
        'title' => __('appointment_cancelled'),
    'message' => sprintf(__('appointment_cancelled_message'), 'Smith', '10:00 AM'),
        'time' => sprintf(__('days_ago'), 1),
        'read' => true,
        'icon' => 'calendar-x',
        'color' => 'danger'
    ]
];

// Handle mark as read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'mark_read' && isset($_POST['notification_id'])) {
        // In real system, update database
        $notificationId = (int)$_POST['notification_id'];
        foreach ($notifications as &$notification) {
            if ($notification['id'] === $notificationId) {
                $notification['read'] = true;
                break;
            }
        }
    header('Location: notifications.php?success=' . urlencode(__('notification_marked_read')));
        exit();
    } elseif ($_POST['action'] === 'mark_all_read') {
        // Mark all as read
        foreach ($notifications as &$notification) {
            $notification['read'] = true;
        }
    header('Location: notifications.php?success=' . urlencode(__('all_notifications_marked_read')));
        exit();
    }
}

$success = $_GET['success'] ?? '';

// Start output buffering for page content
ob_start();
?>

<div class="container-fluid">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-gray-800">
            <i class="bi bi-bell"></i>
            <?php echo __('notifications'); ?>
        </h1>
        <div>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-check-all"></i> <?php echo __('mark_all_as_read'); ?>
                </button>
            </form>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo htmlspecialchars(__('close')); ?>"></button>
        </div>
    <?php endif; ?>

    <!-- Notification Stats -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-start border-primary border-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                                <?php echo __('total_notifications'); ?>
                                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo count($notifications); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-bell text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-start border-warning border-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                <?php echo __('unread_notifications'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo count(array_filter($notifications, fn($n) => !$n['read'])); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-bell-fill text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notifications List -->
    <div class="card shadow">
        <div class="card-header">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="bi bi-list"></i>
                <?php echo __('all_notifications'); ?>
            </h6>
        </div>
        <div class="card-body p-0">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-bell text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3"><?php echo __('no_notifications_available'); ?></p>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="list-group-item <?php echo !$notification['read'] ? 'bg-light' : ''; ?>">
                            <div class="d-flex align-items-start">
                                <div class="me-3">
                                    <div class="rounded-circle bg-<?php echo $notification['color']; ?> text-white d-flex align-items-center justify-content-center" 
                                         style="width: 40px; height: 40px;">
                                        <i class="bi bi-<?php echo $notification['icon']; ?>"></i>
                                    </div>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1 <?php echo !$notification['read'] ? 'fw-bold' : ''; ?>">
                                                <?php echo htmlspecialchars($notification['title']); ?>
                                                <?php if (!$notification['read']): ?>
                                                    <span class="badge bg-primary ms-2"><?php echo __('new_badge'); ?></span>
                                                <?php endif; ?>
                                            </h6>
                                            <p class="mb-1 text-muted">
                                                <?php echo htmlspecialchars($notification['message']); ?>
                                            </p>
                                            <small class="text-muted">
                                                <i class="bi bi-clock"></i>
                                                <?php echo $notification['time']; ?>
                                            </small>
                                        </div>
                                        <div class="dropdown">
                                            <button class="btn btn-link btn-sm" type="button" data-bs-toggle="dropdown">
                                                <i class="bi bi-three-dots-vertical"></i>
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end">
                                                <?php if (!$notification['read']): ?>
                                                    <li>
                                                        <form method="POST">
                                                            <input type="hidden" name="action" value="mark_read">
                                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                            <button type="submit" class="dropdown-item">
                                                                <i class="bi bi-check"></i> <?php echo __('mark_as_read'); ?>
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" onclick="deleteNotification(<?php echo $notification['id']; ?>)">
                                                        <i class="bi bi-trash"></i> <?php echo __('delete'); ?>
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notification Settings -->
    <div class="row mt-4">
        <div class="col-lg-6">
            <div class="card shadow">
                <div class="card-header">
                            <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-gear"></i>
                        <?php echo __('notification_settings'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <form>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="emailNotifications" checked>
                            <label class="form-check-label" for="emailNotifications">
                                <i class="bi bi-envelope"></i> <?php echo __('email_notifications'); ?>
                            </label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="appointmentReminders" checked>
                            <label class="form-check-label" for="appointmentReminders">
                                <i class="bi bi-calendar-check"></i> <?php echo __('appointment_reminders'); ?>
                            </label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="prescriptionAlerts" checked>
                            <label class="form-check-label" for="prescriptionAlerts">
                                <i class="bi bi-prescription2"></i> <?php echo __('prescription_alerts'); ?>
                            </label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="systemUpdates">
                            <label class="form-check-label" for="systemUpdates">
                                <i class="bi bi-gear"></i> <?php echo __('system_updates'); ?>
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> <?php echo __('save_settings'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-lg-6">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-info-circle"></i>
                        <?php echo __('notification_types'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-success text-white me-2 d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                    <i class="bi bi-calendar-check"></i>
                                </div>
                                <small><?php echo __('appointments'); ?></small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-info text-white me-2 d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                    <i class="bi bi-prescription2"></i>
                                </div>
                                <small><?php echo __("prescriptions"); ?></small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-primary text-white me-2 d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                    <i class="bi bi-person-plus"></i>
                                </div>
                                <small><?php echo __('patient_updates'); ?></small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-warning text-white me-2 d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                    <i class="bi bi-gear"></i>
                                </div>
                                <small><?php echo __('system'); ?></small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function deleteNotification(id) {
    if (confirm('<?php echo addslashes(__('are_you_sure')); ?>')) {
        // TODO: Implement delete functionality
    alert('<?php echo addslashes(__('delete_notification_placeholder')); ?>'.replace('%s', id));
    }
}

// Update notification count in navbar
document.addEventListener('DOMContentLoaded', function() {
    const unreadCount = <?php echo count(array_filter($notifications, fn($n) => !$n['read'])); ?>;
    const notificationCountElement = document.getElementById('notificationCount');
    
    if (unreadCount > 0) {
        notificationCountElement.textContent = unreadCount;
        notificationCountElement.style.display = 'inline';
    }
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
