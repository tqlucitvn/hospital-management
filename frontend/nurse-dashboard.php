<?php
require_once 'includes/config.php';
require_once 'includes/language.php';

// Require authentication and nurse role
requireAuth();
requireRole('NURSE');

$pageTitle = __('nurse_dashboard');
$user = getCurrentUser();

// Fetch real user data from API for display
$realUserData = $user;
if (isset($user['id']) && function_exists('makeApiCall')) {
    $token = $_SESSION['token'] ?? '';
    if (!empty($token)) {
        $userResponse = makeApiCall(USER_SERVICE_URL . '/me', 'GET', null, $token);
        if ($userResponse['status_code'] === 200 && isset($userResponse['data'])) {
            $realUserData = $userResponse['data'];
        }
    }
}

// Initialize stats
$stats = [
    'patients' => ['total' => 0, 'today' => 0],
    'appointments' => ['total' => 0, 'today' => 0, 'pending' => 0],
    'prescriptions' => ['total' => 0, 'pending' => 0, 'toFill' => 0]
];

$recentActivities = [];
$pendingTasks = [];

// Fetch nurse-specific stats
try {
    $token = $_SESSION['token'];
    
    // Get all patients (nurses can see all patients)
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
    }
    
    // Get all appointments for today's care
    $response = makeApiCall(APPOINTMENT_SERVICE_URL, 'GET', null, $token);
    if ($response['status_code'] === 200 && is_array($response['data'])) {
        $stats['appointments']['total'] = count($response['data']);
        
        $today = date('Y-m-d');
        $todayCount = 0;
        $pendingCount = 0;
        
        foreach ($response['data'] as $appointment) {
            if (isset($appointment['startTime']) && strpos($appointment['startTime'], $today) === 0) {
                $todayCount++;
            }
            if (isset($appointment['status']) && in_array($appointment['status'], ['SCHEDULED', 'CONFIRMED'])) {
                $pendingCount++;
            }
        }
        
        $stats['appointments']['today'] = $todayCount;
        $stats['appointments']['pending'] = $pendingCount;
    }
    
    // Get prescription stats (nurses help with medication)
    $response = makeApiCall(PRESCRIPTION_SERVICE_URL, 'GET', null, $token);
    if ($response['status_code'] === 200 && is_array($response['data'])) {
        $stats['prescriptions']['total'] = count($response['data']);
        
        // Count prescriptions that need filling/preparation
        $pendingCount = 0;
        $toFillCount = 0;
        foreach ($response['data'] as $prescription) {
            if (isset($prescription['status'])) {
                if ($prescription['status'] === 'ISSUED') {
                    $toFillCount++;
                }
                if (in_array($prescription['status'], ['ISSUED', 'PENDING'])) {
                    $pendingCount++;
                }
            }
        }
        $stats['prescriptions']['pending'] = $pendingCount;
        $stats['prescriptions']['toFill'] = $toFillCount;
    }
    
} catch (Exception $e) {
    error_log("Nurse dashboard stats error: " . $e->getMessage());
}

// Start output buffering for page content
ob_start();
?>

<!-- Welcome Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-1"><?php echo sprintf(__('welcome_back_nurse'), sanitize($realUserData['fullName'] ?? $realUserData['email'] ?? __('nurse'))); ?> 👩‍⚕️</h1>
                <p class="text-muted mb-0">
                    <?php echo __('nursing_dashboard'); ?> • <?php echo sprintf(__('today_is'), date('l, F j, Y')); ?>
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
    <!-- Patient Care -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            <?php echo __('patient_care'); ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['patients']['total']); ?>
                        </div>
                        <div class="text-xs text-muted mt-1">
                            <i class="bi bi-arrow-up text-success"></i>
                            <?php echo $stats['patients']['today']; ?> <?php echo __('new_today'); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-heart-pulse text-primary" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Today's Appointments -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-start border-success border-4">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            <?php echo __('todays_schedule'); ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['appointments']['today']); ?>
                        </div>
                        <div class="text-xs text-muted mt-1">
                            <i class="bi bi-clock text-warning"></i>
                            <?php echo $stats['appointments']['pending']; ?> <?php echo __('pending'); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-calendar-heart text-success" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Medication Tasks -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-start border-warning border-4">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            <?php echo __('medication_tasks'); ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['prescriptions']['toFill']); ?>
                        </div>
                        <div class="text-xs text-muted mt-1">
                            <i class="bi bi-hourglass text-info"></i>
                            <?php echo __('need_preparation'); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-capsule text-warning" style="font-size: 2rem;"></i>
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
                    <a href="patients.php" class="btn btn-outline-primary">
                        <i class="bi bi-people"></i>
                        <?php echo __('patient_records'); ?>
                    </a>
                    
                    <a href="appointments.php" class="btn btn-outline-success">
                        <i class="bi bi-calendar-check"></i>
                        <?php echo __('todays_appointments'); ?>
                    </a>
                    
                    <a href="prescriptions.php" class="btn btn-outline-warning">
                        <i class="bi bi-prescription2"></i>
                        <?php echo __('medication_tasks'); ?>
                    </a>
                    
                    <a href="patients.php?action=add" class="btn btn-outline-info">
                        <i class="bi bi-person-plus"></i>
                        <?php echo __('register_patient'); ?>
                    </a>
                    
                    <a href="profile.php" class="btn btn-outline-secondary">
                        <i class="bi bi-person-circle"></i>
                        <?php echo __('my_profile'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Patient Care Tasks -->
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-header">
                    <h6 class="m-0 font-weight-bold">
                    <i class="bi bi-clipboard-heart"></i>
                    <?php echo __('todays_care_tasks'); ?>
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-primary">
                            <i class="bi bi-capsule"></i> <?php echo __('medication_tasks'); ?>
                        </h6>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo __('prescriptions_to_prepare'); ?>
                                <span class="badge bg-warning rounded-pill"><?php echo $stats['prescriptions']['toFill']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo __('total_prescriptions'); ?>
                                <span class="badge bg-info rounded-pill"><?php echo $stats['prescriptions']['total']; ?></span>
                            </li>
                        </ul>
                        <div class="mt-3">
                            <a href="prescriptions.php" class="btn btn-sm btn-outline-warning">
                                <i class="bi bi-arrow-right"></i> <?php echo __('view_all'); ?>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-success">
                            <i class="bi bi-calendar-heart"></i> <?php echo __('patient_care'); ?>
                        </h6>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo __('todays_appointments'); ?>
                                <span class="badge bg-success rounded-pill"><?php echo $stats['appointments']['today']; ?></span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <?php echo __('new_patients_today'); ?>
                                <span class="badge bg-primary rounded-pill"><?php echo $stats['patients']['today']; ?></span>
                            </li>
                        </ul>
                        <div class="mt-3">
                            <a href="appointments.php" class="btn btn-sm btn-outline-success">
                                <i class="bi bi-arrow-right"></i> <?php echo __('view_schedule'); ?>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Nursing Tools -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                    <h6 class="m-0 font-weight-bold">
                    <i class="bi bi-tools"></i>
                    <?php echo __('nursing_tools_resources'); ?>
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center p-3">
                            <a href="patients.php" class="text-decoration-none">
                                <i class="bi bi-person-heart text-primary" style="font-size: 2rem;"></i>
                                <h6 class="mt-2"><?php echo __("patient_records"); ?></h6>
                                <small class="text-muted"><?php echo __('view_update_records'); ?></small>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3">
                            <a href="prescriptions.php" class="text-decoration-none">
                                <i class="bi bi-capsule-pill text-warning" style="font-size: 2rem;"></i>
                                <h6 class="mt-2"><?php echo __("medications"); ?></h6>
                                <small class="text-muted"><?php echo __('prepare_track_meds'); ?></small>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3">
                            <a href="appointments.php" class="text-decoration-none">
                                <i class="bi bi-calendar-plus text-success" style="font-size: 2rem;"></i>
                                <h6 class="mt-2"><?php echo __('appointments'); ?></h6>
                                <small class="text-muted"><?php echo __('schedule_assist'); ?></small>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3">
                            <a href="notifications.php" class="text-decoration-none">
                                <i class="bi bi-bell-heart text-info" style="font-size: 2rem;"></i>
                                <h6 class="mt-2"><?php echo __("care_alerts"); ?></h6>
                                <small class="text-muted"><?php echo __('important_notifications'); ?></small>
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
