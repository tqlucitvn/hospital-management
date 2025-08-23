<?php
require_once 'includes/config.php';
requireAuth();

$pageTitle = 'Notifications';
$user = getCurrentUser();

// Get notifications (mock data for now - in real system would be from database)
$notifications = [
    [
        'id' => 1,
        'type' => 'appointment',
        'title' => 'New Appointment Scheduled',
        'message' => 'Appointment scheduled for John Doe at 2:00 PM today',
        'time' => '2 minutes ago',
        'read' => false,
        'icon' => 'calendar-check',
        'color' => 'success'
    ],
    [
        'id' => 2,
        'type' => 'prescription',
        'title' => 'Prescription Ready',
        'message' => 'Prescription #12345 is ready for pickup',
        'time' => '15 minutes ago',
        'read' => false,
        'icon' => 'prescription2',
        'color' => 'info'
    ],
    [
        'id' => 3,
        'type' => 'patient',
        'title' => 'New Patient Registration',
        'message' => 'Jane Smith has been registered as a new patient',
        'time' => '1 hour ago',
        'read' => true,
        'icon' => 'person-plus',
        'color' => 'primary'
    ],
    [
        'id' => 4,
        'type' => 'system',
        'title' => 'System Maintenance',
        'message' => 'Scheduled maintenance will occur tonight at 2:00 AM',
        'time' => '3 hours ago',
        'read' => true,
        'icon' => 'gear',
        'color' => 'warning'
    ],
    [
        'id' => 5,
        'type' => 'appointment',
        'title' => 'Appointment Cancelled',
        'message' => 'Dr. Smith appointment at 10:00 AM has been cancelled',
        'time' => '1 day ago',
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
        header('Location: notifications.php?success=Notification marked as read');
        exit();
    } elseif ($_POST['action'] === 'mark_all_read') {
        // Mark all as read
        foreach ($notifications as &$notification) {
            $notification['read'] = true;
        }
        header('Location: notifications.php?success=All notifications marked as read');
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
            Notifications
        </h1>
        <div>
            <form method="POST" style="display: inline;">
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-check-all"></i> Mark All as Read
                </button>
            </form>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
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
                                Total Notifications
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
                                Unread Notifications
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
                All Notifications
            </h6>
        </div>
        <div class="card-body p-0">
            <?php if (empty($notifications)): ?>
                <div class="text-center py-5">
                    <i class="bi bi-bell text-muted" style="font-size: 3rem;"></i>
                    <p class="text-muted mt-3">No notifications available.</p>
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
                                                    <span class="badge bg-primary ms-2">New</span>
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
                                                                <i class="bi bi-check"></i> Mark as Read
                                                            </button>
                                                        </form>
                                                    </li>
                                                <?php endif; ?>
                                                <li>
                                                    <a class="dropdown-item text-danger" href="#" onclick="deleteNotification(<?php echo $notification['id']; ?>)">
                                                        <i class="bi bi-trash"></i> Delete
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
                        Notification Settings
                    </h6>
                </div>
                <div class="card-body">
                    <form>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="emailNotifications" checked>
                            <label class="form-check-label" for="emailNotifications">
                                <i class="bi bi-envelope"></i> Email Notifications
                            </label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="appointmentReminders" checked>
                            <label class="form-check-label" for="appointmentReminders">
                                <i class="bi bi-calendar-check"></i> Appointment Reminders
                            </label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="prescriptionAlerts" checked>
                            <label class="form-check-label" for="prescriptionAlerts">
                                <i class="bi bi-prescription2"></i> Prescription Alerts
                            </label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="systemUpdates">
                            <label class="form-check-label" for="systemUpdates">
                                <i class="bi bi-gear"></i> System Updates
                            </label>
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Save Settings
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
                        Notification Types
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-success text-white me-2 d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                    <i class="bi bi-calendar-check"></i>
                                </div>
                                <small>Appointments</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-info text-white me-2 d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                    <i class="bi bi-prescription2"></i>
                                </div>
                                <small>Prescriptions</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-primary text-white me-2 d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                    <i class="bi bi-person-plus"></i>
                                </div>
                                <small>Patient Updates</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="d-flex align-items-center">
                                <div class="rounded-circle bg-warning text-white me-2 d-flex align-items-center justify-content-center" style="width: 30px; height: 30px;">
                                    <i class="bi bi-gear"></i>
                                </div>
                                <small>System</small>
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
    if (confirm('Are you sure you want to delete this notification?')) {
        // TODO: Implement delete functionality
        alert('Delete notification #' + id + ' - functionality will be implemented');
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
