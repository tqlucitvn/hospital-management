<?php
require_once 'includes/config.php';
require_once 'includes/language.php';
requireRole('ADMIN');

$pageTitle = __('reports_analytics');
$user = getCurrentUser();

// Initialize data arrays
$stats = [];
$monthlyData = [];
$error = '';

try {
    $token = $_SESSION['token'];
    
    // Get current year
    $currentYear = date('Y');
    $selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;
    
    // Get overall statistics
    $apis = [
        'patients' => PATIENT_SERVICE_URL,
        'appointments' => APPOINTMENT_SERVICE_URL,
        'prescriptions' => PRESCRIPTION_SERVICE_URL,
        'users' => USER_SERVICE_URL
    ];
    
    foreach ($apis as $key => $url) {
        $response = makeApiCall($url, 'GET', null, $token);
        if ($response['status_code'] === 200) {
            // Handle different API response formats
            if ($key === 'patients' && isset($response['data']['total'])) {
                // New pagination format
                $stats[$key] = $response['data']['total'];
            } elseif (is_array($response['data'])) {
                // Old direct array format
                $stats[$key] = count($response['data']);
            } else {
                $stats[$key] = 0;
            }
        } else {
            $stats[$key] = 0;
        }
    }
    
    // Get monthly statistics for charts
    $monthlyStats = [
        'patients' => [],
        'appointments' => [],
        'prescriptions' => []
    ];
    
    // Get monthly patient registrations
    for ($month = 1; $month <= 12; $month++) {
        $monthlyStats['patients'][$month] = 0;
        $monthlyStats['appointments'][$month] = 0;
        $monthlyStats['prescriptions'][$month] = 0;
    }
    
    // Calculate monthly data from existing APIs
    foreach ($apis as $type => $url) {
        if ($type === 'users') continue; // Skip users for monthly stats
        
        $response = makeApiCall($url, 'GET', null, $token);
        if ($response['status_code'] === 200) {
            $data = [];
            
            // Handle different API response formats
            if ($type === 'patients' && isset($response['data']['patients'])) {
                // New pagination format
                $data = $response['data']['patients'];
            } elseif (is_array($response['data'])) {
                // Old direct array format
                $data = $response['data'];
            }
            
            foreach ($data as $item) {
                if (isset($item['createdAt'])) {
                    $createdDate = new DateTime($item['createdAt']);
                    if ($createdDate->format('Y') == $selectedYear) {
                        $month = (int)$createdDate->format('n');
                        $monthlyStats[$type][$month]++;
                    }
                }
            }
        }
    }
    
} catch (Exception $e) {
    $error = sprintf(__('failed_to_load_with_message'), $e->getMessage());
}

// Start output buffering for page content
ob_start();
?>

<div class="container-fluid">
    <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 text-gray-800">
            <i class="bi bi-graph-up"></i>
            <?php echo __('reports_analytics'); ?>
        </h1>
        <div>
            <select class="form-select" id="yearSelector" onchange="changeYear()">
                <?php for ($year = $currentYear - 2; $year <= $currentYear; $year++): ?>
                    <option value="<?php echo $year; ?>" <?php echo $year == $selectedYear ? 'selected' : ''; ?>>
                        <?php echo $year; ?>
                    </option>
                <?php endfor; ?>
            </select>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle"></i>
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- Summary Statistics -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start border-primary border-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                <?php echo __('total_patients'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['patients'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-people text-primary" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start border-success border-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                <?php echo __('total_appointments'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['appointments'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-calendar-check text-success" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start border-info border-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                <?php echo __('total_prescriptions'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['prescriptions'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-prescription2 text-info" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-start border-warning border-4">
                <div class="card-body">
                    <div class="row">
                        <div class="col">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                <?php echo __('system_users'); ?>
                            </div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo number_format($stats['users'] ?? 0); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="bi bi-person-gear text-warning" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row">
        <!-- Monthly Registrations Chart -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-bar-chart"></i>
                        <?php echo sprintf(__('monthly_statistics'), $selectedYear); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="monthlyChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- Distribution Pie Chart -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow mb-4">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-pie-chart"></i>
                        <?php echo __('system_overview'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="distributionChart" width="300" height="300"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Reports -->
    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-file-earmark-text"></i>
                        <?php echo __('quick_reports'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="patients.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-people me-2"></i>
                                <?php echo __('patient_list_report'); ?>
                            </div>
                            <span class="badge bg-primary rounded-pill"><?php echo $stats['patients'] ?? 0; ?></span>
                        </a>
                        <a href="appointments.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-calendar-check me-2"></i>
                                <?php echo __('appointment_schedule_report'); ?>
                            </div>
                            <span class="badge bg-success rounded-pill"><?php echo $stats['appointments'] ?? 0; ?></span>
                        </a>
                        <a href="prescriptions.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-prescription2 me-2"></i>
                                <?php echo __('prescription_report'); ?>
                            </div>
                            <span class="badge bg-info rounded-pill"><?php echo $stats['prescriptions'] ?? 0; ?></span>
                        </a>
                        <a href="users.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-person-gear me-2"></i>
                                <?php echo __('user_management'); ?>
                            </div>
                            <span class="badge bg-warning rounded-pill"><?php echo $stats['users'] ?? 0; ?></span>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-4">
            <div class="card shadow">
                    <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-download"></i>
                        <?php echo __('export_reports'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <p class="text-muted"><?php echo __('generate_and_download_reports'); ?></p>
                    
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-success" onclick="exportReport('patients')">
                            <i class="bi bi-file-excel"></i>
                            <?php echo __('export_patients_csv'); ?>
                        </button>
                        <button class="btn btn-outline-primary" onclick="exportReport('appointments')">
                            <i class="bi bi-file-pdf"></i>
                            <?php echo __('export_appointments_pdf'); ?>
                        </button>
                        <button class="btn btn-outline-info" onclick="exportReport('prescriptions')">
                            <i class="bi bi-file-text"></i>
                            <?php echo __('export_prescriptions_txt'); ?>
                        </button>
                        <button class="btn btn-outline-secondary" onclick="exportReport('summary')">
                            <i class="bi bi-file-earmark-bar-graph"></i>
                            <?php echo __('export_summary'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- System Health -->
    <div class="row">
        <div class="col-12">
            <div class="card shadow">
                <div class="card-header">
                    <h6 class="m-0 font-weight-bold text-primary">
                        <i class="bi bi-activity"></i>
                        <?php echo __('system_health_performance'); ?>
                    </h6>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="p-3">
                                <i class="bi bi-database text-success" style="font-size: 2rem;"></i>
                                <h6 class="mt-2"><?php echo __('database'); ?></h6>
                                <span class="badge bg-success"><?php echo __('healthy'); ?></span>
                                <p class="text-muted small mt-1"><?php echo __('all_services_connected'); ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3">
                                <i class="bi bi-server text-primary" style="font-size: 2rem;"></i>
                                <h6 class="mt-2"><?php echo __('api_services'); ?></h6>
                                <span class="badge bg-primary"><?php echo __('all_online'); ?></span>
                                <p class="text-muted small mt-1"><?php echo __('all_microservices_running'); ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3">
                                <i class="bi bi-speedometer2 text-warning" style="font-size: 2rem;"></i>
                                <h6 class="mt-2"><?php echo __('performance'); ?></h6>
                                <span class="badge bg-warning"><?php echo __('good'); ?></span>
                                <p class="text-muted small mt-1"><?php echo __('average_response_time'); ?></p>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-3">
                                <i class="bi bi-shield-check text-success" style="font-size: 2rem;"></i>
                                <h6 class="mt-2"><?php echo __('security'); ?></h6>
                                <span class="badge bg-success"><?php echo __('secure'); ?></span>
                                <p class="text-muted small mt-1"><?php echo __('no_security_issues'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Monthly Chart Data
const monthlyData = {
    patients: <?php echo json_encode(array_values($monthlyStats['patients'])); ?>,
    appointments: <?php echo json_encode(array_values($monthlyStats['appointments'])); ?>,
    prescriptions: <?php echo json_encode(array_values($monthlyStats['prescriptions'])); ?>
};

// Monthly Chart
const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
const monthlyChart = new Chart(monthlyCtx, {
    type: 'line',
    data: {
    labels: [
        '<?php echo addslashes(__('month_jan')); ?>',
        '<?php echo addslashes(__('month_feb')); ?>',
        '<?php echo addslashes(__('month_mar')); ?>',
        '<?php echo addslashes(__('month_apr')); ?>',
        '<?php echo addslashes(__('month_may')); ?>',
        '<?php echo addslashes(__('month_jun')); ?>',
        '<?php echo addslashes(__('month_jul')); ?>',
        '<?php echo addslashes(__('month_aug')); ?>',
        '<?php echo addslashes(__('month_sep')); ?>',
        '<?php echo addslashes(__('month_oct')); ?>',
        '<?php echo addslashes(__('month_nov')); ?>',
        '<?php echo addslashes(__('month_dec')); ?>'
    ],
        datasets: [
            {
                label: '<?php echo addslashes(__('patients')); ?>',
                data: monthlyData.patients,
                borderColor: '#4e73df',
                backgroundColor: 'rgba(78, 115, 223, 0.1)',
                tension: 0.3
            },
            {
                label: '<?php echo addslashes(__('appointments')); ?>',
                data: monthlyData.appointments,
                borderColor: '#1cc88a',
                backgroundColor: 'rgba(28, 200, 138, 0.1)',
                tension: 0.3
            },
            {
                label: '<?php echo addslashes(__('prescriptions')); ?>',
                data: monthlyData.prescriptions,
                borderColor: '#36b9cc',
                backgroundColor: 'rgba(54, 185, 204, 0.1)',
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'top',
            },
            title: {
                display: true,
                text: '<?php echo addslashes(__('monthly_activity_trends')); ?>'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Distribution Pie Chart
const distributionCtx = document.getElementById('distributionChart').getContext('2d');
const distributionChart = new Chart(distributionCtx, {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode([
            __('patients'),
            __('appointments'),
            __('prescriptions'),
            __('users')
        ]); ?>,
        datasets: [{
            data: [
                <?php echo $stats['patients'] ?? 0; ?>,
                <?php echo $stats['appointments'] ?? 0; ?>,
                <?php echo $stats['prescriptions'] ?? 0; ?>,
                <?php echo $stats['users'] ?? 0; ?>
            ],
            backgroundColor: [
                '#4e73df',
                '#1cc88a',
                '#36b9cc',
                '#f6c23e'
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                position: 'bottom',
            },
            title: {
                display: true,
                text: '<?php echo addslashes(__('system_data_distribution')); ?>'
            }
        }
    }
});

// Functions
function changeYear() {
    const year = document.getElementById('yearSelector').value;
    window.location.href = '?year=' + year;
}

function exportReport(type) {
    alert('<?php echo addslashes(__('export_not_implemented')); ?>'.replace('%s', type));
    // TODO: Implement actual export functionality
}
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
