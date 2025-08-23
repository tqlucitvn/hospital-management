<?php
require_once 'includes/config.php';

// Require authentication
requireAuth();

$pageTitle = 'Dashboard';
$user = getCurrentUser();

// Initialize stats
$stats = [
    'patients' => ['total' => 0, 'today' => 0],
    'appointments' => ['total' => 0, 'today' => 0, 'pending' => 0],
    'prescriptions' => ['total' => 0, 'pending' => 0],
    'users' => ['total' => 0]
];

$recentActivities = [];
$upcomingAppointments = [];

// Fetch dashboard stats based on user role
try {
    $token = $_SESSION['token'];
    
    // Debug: Add debug mode flag
    $debugMode = isset($_GET['debug']);
    
    // Get patient stats
    if (hasAnyRole(['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST'])) {
        $response = makeApiCall(PATIENT_SERVICE_URL, 'GET', null, $token);
        
        if ($debugMode) {
            echo "<div class='alert alert-info'>Patient API Response: " . json_encode($response) . "</div>";
        }
        
        if ($response['status_code'] === 200 && is_array($response['data'])) {
            $stats['patients']['total'] = count($response['data']);
            
            // Count patients created today
            $today = date('Y-m-d');
            $todayCount = 0;
            foreach ($response['data'] as $patient) {
                if (isset($patient['createdAt']) && strpos($patient['createdAt'], $today) === 0) {
                    $todayCount++;
                }
            }
            $stats['patients']['today'] = $todayCount;
            
            // Get recent patients for activity feed
            $recentPatients = array_slice(array_reverse($response['data']), 0, 5);
            foreach ($recentPatients as $patient) {
                $recentActivities[] = [
                    'type' => 'patient',
                    'message' => 'New patient registered: ' . ($patient['fullName'] ?? 'Unknown'),
                    'time' => isset($patient['createdAt']) ? formatDate($patient['createdAt']) : 'Recently',
                    'icon' => 'person-plus',
                    'color' => 'success'
                ];
            }
        }
    }
    
    // Get appointment stats
    if (hasAnyRole(['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST'])) {
        $response = makeApiCall(APPOINTMENT_SERVICE_URL, 'GET', null, $token);
        
        if ($debugMode) {
            echo "<div class='alert alert-info'>Appointment API Response: " . json_encode($response) . "</div>";
        }
        
        if ($response['status_code'] === 200 && is_array($response['data'])) {
            $stats['appointments']['total'] = count($response['data']);
            
            // Count appointments today and pending
            $today = date('Y-m-d');
            $todayCount = 0;
            $pendingCount = 0;
            
            foreach ($response['data'] as $appointment) {
                // Count today's appointments
                if (isset($appointment['startTime']) && strpos($appointment['startTime'], $today) === 0) {
                    $todayCount++;
                }
                
                // Count pending appointments (SCHEDULED or CONFIRMED)
                if (isset($appointment['status']) && 
                    in_array($appointment['status'], ['SCHEDULED', 'CONFIRMED'])) {
                    $pendingCount++;
                }
            }
            
            $stats['appointments']['today'] = $todayCount;
            $stats['appointments']['pending'] = $pendingCount;
            
            // Get upcoming appointments
            $upcomingAppointments = array_filter($response['data'], function($appt) {
                return isset($appt['startTime']) && 
                       strtotime($appt['startTime']) > time() &&
                       in_array($appt['status'] ?? '', ['SCHEDULED', 'CONFIRMED']);
            });
            
            // Sort by start time and limit to 5
            usort($upcomingAppointments, function($a, $b) {
                return strtotime($a['startTime']) - strtotime($b['startTime']);
            });
            $upcomingAppointments = array_slice($upcomingAppointments, 0, 5);
            
            // Enrich appointments with patient and doctor info
            if (!empty($upcomingAppointments)) {
                // Get patients data
                $patientResponse = makeApiCall(PATIENT_SERVICE_URL, 'GET', null, $token);
                $patients = [];
                if ($patientResponse['status_code'] === 200 && is_array($patientResponse['data'])) {
                    foreach ($patientResponse['data'] as $patient) {
                        if (isset($patient['id']) && is_array($patient)) {
                            $patients[$patient['id']] = $patient;
                        }
                    }
                }
                
                // Get users data (for doctors)
                $userResponse = makeApiCall(USER_SERVICE_URL, 'GET', null, $token);
                $users = [];
                if ($userResponse['status_code'] === 200 && is_array($userResponse['data'])) {
                    foreach ($userResponse['data'] as $user) {
                        if (isset($user['id']) && is_array($user)) {
                            $users[$user['id']] = $user;
                        }
                    }
                }
                
                // Enrich appointment data
                foreach ($upcomingAppointments as &$appointment) {
                    if (isset($appointment['patientId']) && isset($patients[$appointment['patientId']])) {
                        $appointment['patient'] = $patients[$appointment['patientId']];
                    }
                    if (isset($appointment['doctorId']) && isset($users[$appointment['doctorId']])) {
                        $appointment['doctor'] = $users[$appointment['doctorId']];
                    }
                }
                unset($appointment); // Clear reference
            }
        }
    }
    
    // Get prescription stats
    if (hasAnyRole(['ADMIN', 'DOCTOR', 'NURSE'])) {
        $response = makeApiCall(PRESCRIPTION_SERVICE_URL, 'GET', null, $token);
        
        if ($debugMode) {
            echo "<div class='alert alert-info'>Prescription API Response: " . json_encode($response) . "</div>";
        }
        
        if ($response['status_code'] === 200 && is_array($response['data'])) {
            $stats['prescriptions']['total'] = count($response['data']);
            
            // Count pending prescriptions (ISSUED status)
            $pendingCount = 0;
            foreach ($response['data'] as $prescription) {
                if (isset($prescription['status']) && $prescription['status'] === 'ISSUED') {
                    $pendingCount++;
                }
            }
            $stats['prescriptions']['pending'] = $pendingCount;
        }
    }
    
    // Get user stats (for admin only)
    if (hasRole('ADMIN')) {
        $response = makeApiCall(USER_SERVICE_URL, 'GET', null, $token);
        
        if ($debugMode) {
            echo "<div class='alert alert-info'>User API Response: " . json_encode($response) . "</div>";
        }
        
        if ($response['status_code'] === 200 && is_array($response['data'])) {
            $stats['users']['total'] = count($response['data']);
        }
    }
    
    // Sort recent activities by time
    if (!empty($recentActivities)) {
        usort($recentActivities, function($a, $b) {
            return strtotime($b['time']) - strtotime($a['time']);
        });
        $recentActivities = array_slice($recentActivities, 0, 10); // Keep only 10 most recent
    }
    
} catch (Exception $e) {
    // Log error but don't break the dashboard
    error_log("Dashboard stats error: " . $e->getMessage());
}

// Start output buffering for page content
ob_start();
?>

<!-- Welcome Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-1">Welcome back, <?php echo sanitize($user['fullName'] ?? $user['email'] ?? 'User'); ?>! 👋</h1>
                <p class="text-muted mb-0">
                    <?php echo getRoleDisplayName($user['role'] ?? 'USER'); ?> • 
                    Today is <?php echo date('l, F j, Y'); ?>
                </p>
            </div>
            <div class="text-end">
                <small class="text-muted">Last login: <?php echo isset($_SESSION['login_time']) ? date('M j, Y H:i', $_SESSION['login_time']) : 'N/A'; ?></small>
            </div>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="row mb-4">
    <?php if (hasAnyRole(['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST'])): ?>
    <!-- Total Patients -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-primary border-4">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Total Patients
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['patients']['total'] ?? 0); ?>
                        </div>
                        <div class="text-xs text-muted mt-1">
                            <i class="bi bi-arrow-up text-success"></i>
                            <?php echo $stats['patients']['today'] ?? 0; ?> new today
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-people text-primary" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (hasAnyRole(['ADMIN', 'DOCTOR', 'RECEPTIONIST'])): ?>
    <!-- Total Appointments -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-success border-4">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Appointments
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['appointments']['total'] ?? 0); ?>
                        </div>
                        <div class="text-xs text-muted mt-1">
                            <i class="bi bi-clock text-warning"></i>
                            <?php echo $stats['appointments']['pending'] ?? 0; ?> pending
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-calendar-check text-success" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (hasAnyRole(['ADMIN', 'DOCTOR', 'NURSE'])): ?>
    <!-- Prescriptions -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-info border-4">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Prescriptions
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['prescriptions']['total'] ?? 0); ?>
                        </div>
                        <div class="text-xs text-muted mt-1">
                            <i class="bi bi-hourglass text-warning"></i>
                            <?php echo $stats['prescriptions']['pending'] ?? 0; ?> pending
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-prescription2 text-info" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if (hasRole('ADMIN')): ?>
    <!-- System Users -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-start border-warning border-4">
            <div class="card-body">
                <div class="row">
                    <div class="col">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            System Users
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($stats['users']['total'] ?? 0); ?>
                        </div>
                        <div class="text-xs text-muted mt-1">
                            <i class="bi bi-shield-check text-success"></i>
                            Active accounts
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="bi bi-person-gear text-warning" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="row">
    <!-- Quick Actions -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold">
                    <i class="bi bi-lightning"></i>
                    Quick Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if (hasAnyRole(['ADMIN', 'RECEPTIONIST'])): ?>
                    <a href="patients.php?action=add" class="btn btn-outline-primary">
                        <i class="bi bi-person-plus"></i>
                        Add New Patient
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasAnyRole(['ADMIN', 'RECEPTIONIST', 'DOCTOR'])): ?>
                    <a href="appointments.php?action=add" class="btn btn-outline-success">
                        <i class="bi bi-calendar-plus"></i>
                        Schedule Appointment
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasAnyRole(['ADMIN', 'DOCTOR'])): ?>
                    <a href="prescriptions.php?action=add" class="btn btn-outline-info">
                        <i class="bi bi-prescription"></i>
                        Create Prescription
                    </a>
                    <?php endif; ?>
                    
                    <?php if (hasRole('ADMIN')): ?>
                    <a href="users.php?action=add" class="btn btn-outline-warning">
                        <i class="bi bi-person-gear"></i>
                        Add User
                    </a>
                    
                    <a href="reports.php" class="btn btn-outline-secondary">
                        <i class="bi bi-graph-up"></i>
                        View Reports
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Activities -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold">
                    <i class="bi bi-activity"></i>
                    Recent Activities
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($recentActivities)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                        <p class="mt-2 mb-0">No recent activities</p>
                    </div>
                <?php else: ?>
                    <div class="activity-feed">
                        <?php foreach (array_slice($recentActivities, 0, 5) as $activity): ?>
                        <div class="activity-item d-flex align-items-start mb-3">
                            <div class="activity-icon me-3">
                                <i class="bi bi-<?php echo $activity['icon']; ?> text-<?php echo $activity['color']; ?>"></i>
                            </div>
                            <div class="activity-content flex-grow-1">
                                <p class="mb-1 small"><?php echo sanitize($activity['message']); ?></p>
                                <small class="text-muted"><?php echo formatDate($activity['time']); ?></small>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center">
                        <a href="#" class="btn btn-sm btn-outline-primary">View All Activities</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Upcoming Appointments -->
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold">
                    <i class="bi bi-calendar2-week"></i>
                    Tomorrow's Appointments
                </h6>
            </div>
            <div class="card-body">
                <?php if (empty($upcomingAppointments)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-calendar-x" style="font-size: 2rem;"></i>
                        <p class="mt-2 mb-0">No appointments tomorrow</p>
                    </div>
                <?php else: ?>
                    <div class="appointments-list">
                        <?php foreach ($upcomingAppointments as $appointment): ?>
                        <div class="appointment-item d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                            <div>
                                <div class="fw-bold"><?php echo formatDate($appointment['startTime'] ?? $appointment['createdAt'] ?? 'N/A'); ?></div>
                                <small class="text-muted">
                                    Patient: <?php 
                                        if (isset($appointment['patient']['fullName'])) {
                                            echo sanitize($appointment['patient']['fullName']);
                                        } elseif (isset($appointment['patientId'])) {
                                            echo "ID: " . substr($appointment['patientId'], 0, 8) . "...";
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?><br>
                                    Doctor: <?php 
                                        if (isset($appointment['doctor']['fullName'])) {
                                            echo sanitize($appointment['doctor']['fullName']);
                                        } elseif (isset($appointment['doctorId'])) {
                                            echo "ID: " . substr($appointment['doctorId'], 0, 8) . "...";
                                        } else {
                                            echo 'N/A';
                                        }
                                    ?>
                                </small>
                            </div>
                            <span class="<?php echo getAppointmentStatusClass($appointment['status'] ?? 'UNKNOWN'); ?>">
                                <?php echo ucfirst(strtolower($appointment['status'] ?? 'Unknown')); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center">
                        <a href="appointments.php" class="btn btn-sm btn-outline-primary">View All Appointments</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Role-specific Dashboard Extensions -->
<?php if (hasRole('ADMIN')): ?>
<!-- Admin Dashboard -->
<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h6 class="m-0 font-weight-bold">
                    <i class="bi bi-gear"></i>
                    System Administration
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="text-center p-3">
                            <i class="bi bi-database text-primary" style="font-size: 2rem;"></i>
                            <h6 class="mt-2">Database Status</h6>
                            <span class="badge bg-success">Healthy</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3">
                            <i class="bi bi-server text-info" style="font-size: 2rem;"></i>
                            <h6 class="mt-2">Services</h6>
                            <span class="badge bg-success">All Online</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3">
                            <i class="bi bi-shield-check text-success" style="font-size: 2rem;"></i>
                            <h6 class="mt-2">Security</h6>
                            <span class="badge bg-success">Secure</span>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="text-center p-3">
                            <i class="bi bi-speedometer2 text-warning" style="font-size: 2rem;"></i>
                            <h6 class="mt-2">Performance</h6>
                            <span class="badge bg-warning">Good</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
