<?php
require_once 'includes/config.php';
require_once 'includes/language.php';

// Check authentication
if (!isset($_SESSION['token'])) {
    header('Location: login.php');
    exit;
}

$token = $_SESSION['token'];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __('status_check'); ?> - <?php echo __('hospital_subtitle'); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .status-card { margin-bottom: 20px; }
        .test-result { margin-top: 10px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .warning { color: #ffc107; }
    </style>
</head>
<body>
    <div class="container mt-4">
    <h1 class="mb-4"><i class="fas fa-heartbeat text-primary"></i> <?php echo __('status_check'); ?> - <?php echo __('hospital_subtitle'); ?></h1>
        
        <div class="row">
            <!-- User Data Check -->
            <div class="col-md-6">
                <div class="card status-card">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-users"></i> <?php echo __('users_data_check'); ?></h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $response = makeApiCall(USER_SERVICE_URL, 'GET', null, $token);
                        if ($response['status_code'] === 200 && isset($response['data']) && count($response['data']) > 0) {
                            $user = $response['data'][0];
                            echo "<div class='test-result'>";
                            echo "<strong>" . __('sample_user_data_label') . "</strong><br>";
                            
                            // Test getDisplayName function
                            function getDisplayName($user) {
                                if (!empty($user['fullName'])) return $user['fullName'];
                                if (!empty($user['name'])) return $user['name'];
                                if (!empty($user['firstName']) && !empty($user['lastName'])) {
                                    return trim($user['firstName'] . ' ' . $user['lastName']);
                                }
                                if (!empty($user['firstName'])) return $user['firstName'];
                                if (!empty($user['lastName'])) return $user['lastName'];
                                if (!empty($user['username'])) return $user['username'];
                                return __('not_provided');
                            }
                            
                            function getPhoneNumber($user) {
                                if (!empty($user['phoneNumber'])) return $user['phoneNumber'];
                                if (!empty($user['phone'])) return $user['phone'];
                                if (!empty($user['mobile'])) return $user['mobile'];
                                return __('not_provided');
                            }
                            
                            $displayName = getDisplayName($user);
                            $phoneNumber = getPhoneNumber($user);
                            
                            echo __('name_label') . ": <span class='" . ($displayName !== __('not_provided') ? 'success' : 'error') . "'>" . htmlspecialchars($displayName) . "</span><br>";
                            echo __('phone_label') . ": <span class='" . ($phoneNumber !== __('not_provided') ? 'success' : 'warning') . "'>" . htmlspecialchars($phoneNumber) . "</span><br>";
                            echo __('role_label') . " " . htmlspecialchars($user['role'] ?? __('unknown')) . "<br>";
                            echo "<a href='users.php' class='btn btn-sm btn-primary mt-2'>" . __('view_users_page') . "</a>";
                            echo "</div>";
                        } else {
                            echo "<div class='test-result error'>❌ " . __('failed_to_load_users') . "</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Appointment Data Check -->
            <div class="col-md-6">
                <div class="card status-card">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-calendar-alt"></i> <?php echo __('appointments_data_check'); ?></h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $response = makeApiCall(APPOINTMENT_SERVICE_URL, 'GET', null, $token);
                        if ($response['status_code'] === 200 && isset($response['data']) && count($response['data']) > 0) {
                            $appointment = $response['data'][0];
                            echo "<div class='test-result'>";
                            echo "<strong>" . __('sample_appointment_data') . "</strong><br>";
                            
                            function getPatientName($appointment) {
                                if (isset($appointment['patient']['fullName'])) return $appointment['patient']['fullName'];
                                if (isset($appointment['patient']['name'])) return $appointment['patient']['name'];
                                if (isset($appointment['patient']['firstName']) && isset($appointment['patient']['lastName'])) {
                                    return trim($appointment['patient']['firstName'] . ' ' . $appointment['patient']['lastName']);
                                }
                                if (isset($appointment['patientName'])) return $appointment['patientName'];
                                return __('unknown_patient');
                            }
                            
                            function getDoctorName($appointment) {
                                if (isset($appointment['doctor']['fullName'])) return $appointment['doctor']['fullName'];
                                if (isset($appointment['doctor']['name'])) return $appointment['doctor']['name'];
                                if (isset($appointment['doctor']['firstName']) && isset($appointment['doctor']['lastName'])) {
                                    return trim($appointment['doctor']['firstName'] . ' ' . $appointment['doctor']['lastName']);
                                }
                                if (isset($appointment['doctorName'])) return $appointment['doctorName'];
                                return sprintf(__('doctor_title_name'), __('unknown_doctor'));
                            }
                            
                            $patientName = getPatientName($appointment);
                            $doctorName = getDoctorName($appointment);
                            
                            echo __('patient_label') . ": <span class='" . ($patientName !== __('unknown_patient') ? 'success' : 'error') . "'>" . htmlspecialchars($patientName) . "</span><br>";
                            echo __('doctor_label') . ": <span class='" . ($doctorName !== sprintf(__('doctor_title_name'), __('unknown_doctor')) ? 'success' : 'error') . "'>" . htmlspecialchars($doctorName) . "</span><br>";
                            echo __('status_label') . ": " . htmlspecialchars($appointment['status'] ?? __('unknown')) . "<br>";
                            echo "<a href='appointments.php' class='btn btn-sm btn-success mt-2'>" . __('view_appointments_page') . "</a>";
                            echo "</div>";
                        } else {
                            echo "<div class='test-result error'>❌ " . __('failed_to_load_appointments') . "</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Prescription Data Check -->
            <div class="col-md-6">
                <div class="card status-card">
                    <div class="card-header bg-warning text-dark">
                        <h5><i class="fas fa-prescription-bottle-alt"></i> <?php echo __('prescriptions_data_check'); ?></h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $response = makeApiCall(PRESCRIPTION_SERVICE_URL, 'GET', null, $token);
                        if ($response['status_code'] === 200) {
                            if (isset($response['data']) && count($response['data']) > 0) {
                                $prescription = $response['data'][0];
                                echo "<div class='test-result'>";
                                echo "<strong>" . __('sample_prescription_data') . "</strong><br>";
                                echo __('status_label') . ": " . htmlspecialchars($prescription['status'] ?? __('unknown')) . "<br>";
                                echo __('note_label') . ": " . htmlspecialchars(substr($prescription['note'] ?? __('no_note'), 0, 50)) . "<br>";
                                echo "<span class='success'>✅ " . __('prescriptions_loading_correctly') . "</span><br>";
                                echo "<a href='prescriptions.php' class='btn btn-sm btn-warning mt-2'>" . __('view_prescriptions_page') . "</a>";
                                echo "</div>";
                            } else {
                                echo "<div class='test-result warning'>⚠️ " . __('no_prescriptions_found') . "</div>";
                                echo "<a href='prescriptions.php' class='btn btn-sm btn-warning mt-2'>" . __('view_prescriptions_page') . "</a>";
                            }
                        } else {
                            echo "<div class='test-result error'>❌ " . __('failed_to_load') . " (" . sprintf(__('status_with_code'), ($response['status_code'] ?? 'N/A')) . ")</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Patient Data Check -->
            <div class="col-md-6">
                <div class="card status-card">
                    <div class="card-header bg-info text-white">
                        <h5><i class="fas fa-user-injured"></i> <?php echo __('patients_data_check'); ?></h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $response = makeApiCall(PATIENT_SERVICE_URL, 'GET', null, $token);
                        if ($response['status_code'] === 200 && isset($response['data']) && count($response['data']) > 0) {
                            $patient = $response['data'][0];
                            echo "<div class='test-result'>";
                            echo "<strong>" . __('sample_patient_data') . "</strong><br>";
                            echo __('name_label') . ": " . htmlspecialchars($patient['fullName'] ?? $patient['name'] ?? __('unknown')) . "<br>";
                            echo __('phone_label') . ": " . htmlspecialchars($patient['phoneNumber'] ?? $patient['phone'] ?? __('not_provided')) . "<br>";
                            echo __('age_label') . ": " . htmlspecialchars($patient['age'] ?? __('unknown')) . "<br>";
                            echo "<span class='success'>✅ " . __('patients_loading_correctly') . "</span><br>";
                            echo "<a href='patients.php' class='btn btn-sm btn-info mt-2'>" . __('view_patients') . "</a>";
                            echo "</div>";
                        } else {
                            echo "<div class='test-result error'>❌ " . __('failed_to_load') . "</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-dark text-white">
                        <h5><i class="fas fa-tools"></i> <?php echo __('quick_navigation'); ?></h5>
                    </div>
                        <div class="card-body text-center">
                        <a href="dashboard.php" class="btn btn-primary me-2"><?php echo __('dashboard'); ?></a>
                        <a href="users.php" class="btn btn-secondary me-2"><?php echo __('users'); ?></a>
                        <a href="patients.php" class="btn btn-info me-2"><?php echo __('patients'); ?></a>
                        <a href="appointments.php" class="btn btn-success me-2"><?php echo __('appointments'); ?></a>
                        <a href="prescriptions.php" class="btn btn-warning me-2"><?php echo __('prescriptions'); ?></a>
                        <a href="debug-all-apis.php" class="btn btn-danger"><?php echo __('api_debug'); ?></a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
