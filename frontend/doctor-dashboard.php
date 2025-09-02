<?php
require_once 'includes/config.php';
require_once 'includes/language.php';

// Require authentication and doctor role
requireAuth();
requireRole('DOCTOR');

$pageTitle = __('doctor_dashboard');
$user = getCurrentUser();

// Initialize stats
$stats = [
    'patients' => ['total' => 0, 'today' => 0],
    'appointments' => ['total' => 0, 'today' => 0, 'pending' => 0],
    'prescriptions' => ['total' => 0, 'pending' => 0]
];

$recentActivities = [];
$upcomingAppointments = [];

// Fetch doctor-specific stats
try {
    $token = $_SESSION['token'];
    $doctorId = $user['id'];
    
    // Get doctor's appointments
    $response = makeApiCall(APPOINTMENT_SERVICE_URL, 'GET', null, $token);
    if ($response['status_code'] === 200 && is_array($response['data'])) {
        // Filter appointments for this doctor
        $doctorAppointments = array_filter($response['data'], function($appt) use ($doctorId) {
            return isset($appt['doctorId']) && $appt['doctorId'] === $doctorId;
        });
        
        $stats['appointments']['total'] = count($doctorAppointments);
        
        // Count today's appointments and pending
        $today = date('Y-m-d');
        $todayCount = 0;
        $pendingCount = 0;
        
        foreach ($doctorAppointments as $appointment) {
            if (isset($appointment['startTime']) && strpos($appointment['startTime'], $today) === 0) {
                $todayCount++;
            }
            if (isset($appointment['status']) && in_array($appointment['status'], ['SCHEDULED', 'CONFIRMED'])) {
                $pendingCount++;
            }
        }
        
        $stats['appointments']['today'] = $todayCount;
        $stats['appointments']['pending'] = $pendingCount;
        
        // Get upcoming appointments for this doctor
        $upcomingAppointments = array_filter($doctorAppointments, function($appt) {
            return isset($appt['startTime']) && 
                   strtotime($appt['startTime']) > time() &&
                   in_array($appt['status'] ?? '', ['SCHEDULED', 'CONFIRMED']);
        });
        
        usort($upcomingAppointments, function($a, $b) {
            return strtotime($a['startTime']) - strtotime($b['startTime']);
        });
        $upcomingAppointments = array_slice($upcomingAppointments, 0, 5);
    }
    
    // Get doctor's prescriptions
    $response = makeApiCall(PRESCRIPTION_SERVICE_URL, 'GET', null, $token);
    if ($response['status_code'] === 200 && is_array($response['data'])) {
        // Filter prescriptions for this doctor
        $doctorPrescriptions = array_filter($response['data'], function($presc) use ($doctorId) {
            return isset($presc['doctorId']) && $presc['doctorId'] === $doctorId;
        });
        
        $stats['prescriptions']['total'] = count($doctorPrescriptions);
        
        // Count pending prescriptions
        $pendingCount = 0;
        foreach ($doctorPrescriptions as $prescription) {
            if (isset($prescription['status']) && $prescription['status'] === 'ISSUED') {
                $pendingCount++;
            }
        }
        $stats['prescriptions']['pending'] = $pendingCount;
    }
    
    // Get patients count (all patients that doctor can see)
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
    
} catch (Exception $e) {
    error_log("Doctor dashboard stats error: " . $e->getMessage());
}

// Start output buffering for page content
ob_start();
?>

<!-- Welcome Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-1"><?php echo sprintf(__('welcome_back_doctor'), sanitize($user['fullName'] ?? $user['email'] ?? __('doctor'))); ?></h1>
                <p class="text-muted mb-0">
                    <?php echo sprintf(__('doctor_dashboard_today'), date('l, F j, Y')); ?>
                </p>
            </div>
            <div class="text-end">
                <small class="text-muted"><?php echo sprintf(__('last_login'), isset($_SESSION['login_time']) ? date('M j, Y H:i', $_SESSION['login_time']) : __('not_provided')); ?></small>
            </div>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <!-- My Appointments -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            <?php echo __('my_appointments'); ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['appointments']['total']); ?>
                        </div>
                        <div class="text-xs text-muted mt-1">
                            <i class="bi bi-calendar-day text-success"></i>
                            <?php echo $stats['appointments']['today']; ?> <?php echo __('today'); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-calendar-check text-primary" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- My Prescriptions -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-start border-success border-4">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            <?php echo __('my_prescriptions'); ?>
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['prescriptions']['total']); ?>
                        </div>
                        <div class="text-xs text-muted mt-1">
                            <i class="bi bi-hourglass text-warning"></i>
                            <?php echo $stats['prescriptions']['pending']; ?> <?php echo __('pending'); ?>
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-prescription2 text-success" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Total Patients -->
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-start border-info border-4">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            <?php echo __('patients'); ?>
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
                        <i class="bi bi-people text-info" style="font-size: 2rem;"></i>
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
                    <a href="appointments.php" class="btn btn-outline-primary">
                        <i class="bi bi-calendar-week"></i>
                        <?php echo __('view_my_appointments'); ?>
                    </a>
                    
                    <a href="appointments.php?action=add" class="btn btn-outline-success">
                        <i class="bi bi-calendar-plus"></i>
                        <?php echo __('schedule_appointment'); ?>
                    </a>
                    
                    <a href="prescriptions.php" class="btn btn-outline-info">
                        <i class="bi bi-prescription"></i>
                        <?php echo __('my_prescriptions'); ?>
                    </a>
                    
                    <a href="prescriptions.php?action=add" class="btn btn-outline-warning">
                        <i class="bi bi-plus-circle"></i>
                        <?php echo __('create_prescription'); ?>
                    </a>
                    
                    <a href="patients.php" class="btn btn-outline-secondary">
                        <i class="bi bi-people"></i>
                        <?php echo __('view_patients'); ?>
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
                    <?php echo __('todays_schedule'); ?>
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($upcomingAppointments)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-calendar-x" style="font-size: 3rem;"></i>
                            <p class="mt-2 mb-0"><?php echo __('no_appointments_today'); ?></p>
                            <small><?php echo __('enjoy_free_time'); ?></small>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th><?php echo __("time"); ?></th>
                                    <th><?php echo __("patient"); ?></th>
                                    <th><?php echo __("status"); ?></th>
                                    <th><?php echo __("actions"); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingAppointments as $appointment): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo date('H:i', strtotime($appointment['startTime'])); ?></strong><br>
                                        <small class="text-muted"><?php echo date('M j', strtotime($appointment['startTime'])); ?></small>
                                    </td>
                                    <td>
                                        <?php if (isset($appointment['patient']['fullName'])): ?>
                                            <?php echo sanitize($appointment['patient']['fullName']); ?>
                                        <?php else: ?>
                                            <small class="text-muted"><?php echo __('patient_id_label'); ?>: <?php echo substr($appointment['patientId'] ?? __('not_provided'), 0, 8); ?>...</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="<?php echo getAppointmentStatusClass($appointment['status'] ?? 'UNKNOWN'); ?>">
                                            <?php echo ucfirst(strtolower($appointment['status'] ?? __('unknown'))); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="appointments.php?id=<?php echo $appointment['id']; ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i>
                                        </a>
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

<!-- Additional Doctor Tools -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold">
                    <i class="bi bi-tools"></i>
                    <?php echo __('doctor_tools'); ?>
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center p-3">
                            <a href="prescriptions.php" class="text-decoration-none">
                                <i class="bi bi-prescription2 text-primary" style="font-size: 2rem;"></i>
                                <h6 class="mt-2"><?php echo __("prescriptions"); ?></h6>
                                <small class="text-muted"><?php echo __("manage_prescriptions"); ?></small>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3">
                            <a href="patients.php" class="text-decoration-none">
                                <i class="bi bi-person-heart text-info" style="font-size: 2rem;"></i>
                                <h6 class="mt-2"><?php echo __("patient_records"); ?></h6>
                                <small class="text-muted"><?php echo __("view_patient_history"); ?></small>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3">
                            <a href="appointments.php" class="text-decoration-none">
                                <i class="bi bi-calendar-medical text-success" style="font-size: 2rem;"></i>
                                <h6 class="mt-2"><?php echo __('appointments'); ?></h6>
                                <small class="text-muted"><?php echo __('schedule_and_manage'); ?></small>
                            </a>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3">
                            <a href="profile.php" class="text-decoration-none">
                                <i class="bi bi-person-circle text-warning" style="font-size: 2rem;"></i>
                                <h6 class="mt-2"><?php echo __("my_profile"); ?></h6>
                                <small class="text-muted"><?php echo __("update_information"); ?></small>
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
