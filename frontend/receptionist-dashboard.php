<?php
require_once 'includes/config.php';
require_once 'includes/language.php';

// Require authentication and receptionist role
requireAuth();
requireRole('RECEPTIONIST');

$pageTitle = __('receptionist_dashboard');
$user = getCurrentUser();

// Initialize stats
$stats = [
    'patients' => ['total' => 0, 'today' => 0],
    'appointments' => ['total' => 0, 'today' => 0, 'pending' => 0, 'tomorrow' => 0],
    'waitingList' => 0
];

$recentActivities = [];
$todayAppointments = [];

// Fetch receptionist-specific stats
try {
    $token = $_SESSION['token'];
    
    // Get all patients (receptionists manage patient registration)
    $response = makeApiCall(PATIENT_SERVICE_URL, 'GET', null, $token);
    if ($response['status_code'] === 200) {
        if (isset($response['data']['patients']) && is_array($response['data']['patients'])) {
            $patients = $response['data']['patients'];
            $stats['patients']['total'] = count($patients);
        } elseif (is_array($response['data'])) {
            $patients = $response['data'];
            $stats['patients']['total'] = count($patients);
        }
        
        // Count patients created today
        $today = date('Y-m-d');
        $todayCount = 0;
        foreach ($patients as $patient) {
            if (isset($patient['createdAt']) && strpos($patient['createdAt'], $today) === 0) {
                $todayCount++;
            }
        }
        $stats['patients']['today'] = $todayCount;
        
        // Recent patient registrations for activity feed
        $recentPatients = array_slice(array_reverse($patients), 0, 5);
        foreach ($recentPatients as $patient) {
            $recentActivities[] = [
                'type' => 'patient',
                'message' => sprintf(__('new_patient_registered'), ($patient['fullName'] ?? __('unknown'))),
                'time' => isset($patient['createdAt']) ? formatDate($patient['createdAt']) : __('recently'),
                'icon' => 'person-plus',
                'color' => 'success'
            ];
        }
    }
    
    // Get all appointments (receptionists manage scheduling)
    $response = makeApiCall(APPOINTMENT_SERVICE_URL, 'GET', null, $token);
    if ($response['status_code'] === 200 && is_array($response['data'])) {
        $stats['appointments']['total'] = count($response['data']);
        
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        $todayCount = 0;
        $tomorrowCount = 0;
        $pendingCount = 0;
        
        foreach ($response['data'] as $appointment) {
            // Count today's appointments
            if (isset($appointment['startTime']) && strpos($appointment['startTime'], $today) === 0) {
                $todayCount++;
                $todayAppointments[] = $appointment;
            }
            
            // Count tomorrow's appointments
            if (isset($appointment['startTime']) && strpos($appointment['startTime'], $tomorrow) === 0) {
                $tomorrowCount++;
            }
            
            // Count pending appointments
            if (isset($appointment['status']) && in_array($appointment['status'], ['SCHEDULED', 'CONFIRMED'])) {
                $pendingCount++;
            }
        }
        
        $stats['appointments']['today'] = $todayCount;
        $stats['appointments']['tomorrow'] = $tomorrowCount;
        $stats['appointments']['pending'] = $pendingCount;
        
        // Sort today's appointments by time
        usort($todayAppointments, function($a, $b) {
            return strtotime($a['startTime']) - strtotime($b['startTime']);
        });
        $todayAppointments = array_slice($todayAppointments, 0, 10);
    }
    
} catch (Exception $e) {
    error_log("Receptionist dashboard stats error: " . $e->getMessage());
}

// Start output buffering for page content
ob_start();
?>

<!-- Welcome Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-1"><?php echo sprintf(__('welcome_back_user'), sanitize($user['fullName'] ?? $user['email'] ?? __('receptionist'))); ?> 🏥</h1>
                <p class="text-muted mb-0">
                    <?php echo __('reception_dashboard'); ?> • <?php echo sprintf(__('today_is'), date('l, F j, Y')); ?>
                </p>
            </div>
            <div class="text-end">
                <small class="text-muted"><?php echo isset($_SESSION['login_time']) ? sprintf(__('last_login'), date('M j, Y H:i', $_SESSION['login_time'])) : __('not_available'); ?></small>
            </div>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <!-- Patient Registration -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            <?php echo __('total_patients'); ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['patients']['total']); ?>
                        </div>
                        <div class="text-xs text-muted mt-1">
                            <i class="bi bi-arrow-up text-success"></i>
                            <?php echo $stats['patients']['today']; ?> <?php echo __('registered_today'); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-person-plus text-primary" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Today's Appointments -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-success border-4">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            <?php echo __('todays_appointments'); ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['appointments']['today']); ?>
                        </div>
                        <div class="text-xs text-muted mt-1">
                            <i class="bi bi-calendar-day text-info"></i>
                            <?php echo $stats['appointments']['tomorrow']; ?> <?php echo __('tomorrow'); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-calendar-check text-success" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Pending Appointments -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-warning border-4">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            <?php echo __('pending_appointments'); ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['appointments']['pending']); ?>
                        </div>
                        <div class="text-xs text-muted mt-1">
                            <i class="bi bi-clock text-warning"></i>
                            <?php echo __('need_confirmation'); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-hourglass-split text-warning" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Total Appointments -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-info border-4">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            <?php echo __('appointments'); ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['appointments']['total']); ?>
                        </div>
                        <div class="text-xs text-muted mt-1">
                            <i class="bi bi-calendar-week text-info"></i>
                            <?php echo __('all_scheduled'); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-calendar3 text-info" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Quick Actions -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
                <div class="card-header">
                <h6 class="m-0 font-weight-bold">
                    <i class="bi bi-lightning"></i>
                    <?php echo __('quick_actions'); ?>
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="patients.php?action=add" class="btn btn-outline-primary">
                        <i class="bi bi-person-plus"></i>
                        <?php echo __('add_new_patient'); ?>
                    </a>
                    
                    <a href="appointments.php?action=add" class="btn btn-outline-success">
                        <i class="bi bi-calendar-plus"></i>
                        <?php echo __('schedule_appointment'); ?>
                    </a>
                    
                    <a href="appointments.php" class="btn btn-outline-warning">
                        <i class="bi bi-calendar-check"></i>
                        <?php echo __('manage_appointments'); ?>
                    </a>
                    
                    <a href="patients.php" class="btn btn-outline-info">
                        <i class="bi bi-search"></i>
                        <?php echo __('find_patient'); ?>
                    </a>
                    
                    <a href="profile.php" class="btn btn-outline-secondary">
                        <i class="bi bi-person-circle"></i>
                        <?php echo __('my_profile'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Today's Schedule -->
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold">
                    <i class="bi bi-calendar-day"></i>
                    <?php echo __('todays_appointment_schedule'); ?>
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($todayAppointments)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-calendar-x" style="font-size: 3rem;"></i>
                        <p class="mt-2 mb-0"><?php echo __('no_appointments_today'); ?></p>
                        <small><?php echo __('enjoy_free_time'); ?></small>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th><?php echo __("time"); ?></th>
                                    <th><?php echo __("patient"); ?></th>
                                    <th><?php echo __("doctor"); ?></th>
                                    <th><?php echo __("status"); ?></th>
                                    <th><?php echo __("actions"); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($todayAppointments as $appointment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo date('H:i', strtotime($appointment['startTime'])); ?></strong>
                                    </td>
                                    <td>
                                        <?php if (isset($appointment['patient']['fullName'])): ?>
                                            <?php echo sanitize($appointment['patient']['fullName']); ?>
                                        <?php else: ?>
                                            <small class="text-muted"><?php echo __('patient_id_label'); ?>: <?php echo substr($appointment['patientId'] ?? __('not_provided'), 0, 8); ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($appointment['doctor']['fullName'])): ?>
                                            <?php echo sprintf(__('doctor_title_name'), sanitize($appointment['doctor']['fullName'])); ?>
                                        <?php else: ?>
                                            <small class="text-muted"><?php echo __('doctor_id_label'); ?>: <?php echo substr($appointment['doctorId'] ?? __('not_provided'), 0, 8); ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="<?php echo getAppointmentStatusClass($appointment['status'] ?? 'UNKNOWN'); ?>">
                                            <?php echo ucfirst(strtolower($appointment['status'] ?? __('unknown'))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="appointments.php?id=<?php echo $appointment['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="appointments.php?action=edit&id=<?php echo $appointment['id']; ?>" class="btn btn-outline-warning btn-sm">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center">
                        <a href="appointments.php" class="btn btn-sm btn-outline-primary"><?php echo __("view_all_appointments"); ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Reception Tools -->
<div class="row">
    <div class="col-12">
        <div class="card">
                    <div class="card-header">
                <h6 class="m-0 font-weight-bold">
                    <i class="bi bi-tools"></i>
                    <?php echo __('reception_tools'); ?>
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center p-3">
                            <a href="patients.php" class="text-decoration-none">
                                <i class="bi bi-person-rolodex text-primary" style="font-size: 2rem;"></i>
                                <h6 class="mt-2"><?php echo __("patient_directory"); ?></h6>
                                    <small class="text-muted"><?php echo __('search_and_manage_patients'); ?></small>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3">
                            <a href="appointments.php" class="text-decoration-none">
                                <i class="bi bi-calendar-range text-success" style="font-size: 2rem;"></i>
                                <h6 class="mt-2"><?php echo __("appointment_book"); ?></h6>
                                    <small class="text-muted"><?php echo __('schedule_and_manage'); ?></small>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3">
                            <a href="notifications.php" class="text-decoration-none">
                                <i class="bi bi-telephone text-info" style="font-size: 2rem;"></i>
                                <h6 class="mt-2"><?php echo __("communications"); ?></h6>
                                <small class="text-muted"><?php echo __("patient_notifications"); ?></small>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3">
                            <a href="reports.php" class="text-decoration-none">
                                <i class="bi bi-clipboard-data text-warning" style="font-size: 2rem;"></i>
                                <h6 class="mt-2"><?php echo __("daily_reports"); ?></h6>
                                <small class="text-muted"><?php echo __("generate_summaries"); ?></small>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
