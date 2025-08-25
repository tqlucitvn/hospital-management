<?php
require_once 'includes/config.php';

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
    <title>System Status Check - Hospital Management</title>
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
        <h1 class="mb-4"><i class="fas fa-heartbeat text-primary"></i> Hospital Management System - Status Check</h1>
        
        <div class="row">
            <!-- User Data Check -->
            <div class="col-md-6">
                <div class="card status-card">
                    <div class="card-header bg-primary text-white">
                        <h5><i class="fas fa-users"></i> Users Data Check</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $response = makeApiCall(USER_SERVICE_URL, 'GET', null, $token);
                        if ($response['status_code'] === 200 && isset($response['data']) && count($response['data']) > 0) {
                            $user = $response['data'][0];
                            echo "<div class='test-result'>";
                            echo "<strong>Sample User Data:</strong><br>";
                            
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
                                return 'No Name';
                            }
                            
                            function getPhoneNumber($user) {
                                if (!empty($user['phoneNumber'])) return $user['phoneNumber'];
                                if (!empty($user['phone'])) return $user['phone'];
                                if (!empty($user['mobile'])) return $user['mobile'];
                                return 'Not provided';
                            }
                            
                            $displayName = getDisplayName($user);
                            $phoneNumber = getPhoneNumber($user);
                            
                            echo "Name: <span class='" . ($displayName !== 'No Name' ? 'success' : 'error') . "'>$displayName</span><br>";
                            echo "Phone: <span class='" . ($phoneNumber !== 'Not provided' ? 'success' : 'warning') . "'>$phoneNumber</span><br>";
                            echo "Role: " . ($user['role'] ?? 'Unknown') . "<br>";
                            echo "<a href='users.php' class='btn btn-sm btn-primary mt-2'>View Users Page</a>";
                            echo "</div>";
                        } else {
                            echo "<div class='test-result error'>❌ Failed to load users data</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Appointment Data Check -->
            <div class="col-md-6">
                <div class="card status-card">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-calendar-alt"></i> Appointments Data Check</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $response = makeApiCall(APPOINTMENT_SERVICE_URL, 'GET', null, $token);
                        if ($response['status_code'] === 200 && isset($response['data']) && count($response['data']) > 0) {
                            $appointment = $response['data'][0];
                            echo "<div class='test-result'>";
                            echo "<strong>Sample Appointment Data:</strong><br>";
                            
                            function getPatientName($appointment) {
                                if (isset($appointment['patient']['fullName'])) return $appointment['patient']['fullName'];
                                if (isset($appointment['patient']['name'])) return $appointment['patient']['name'];
                                if (isset($appointment['patient']['firstName']) && isset($appointment['patient']['lastName'])) {
                                    return trim($appointment['patient']['firstName'] . ' ' . $appointment['patient']['lastName']);
                                }
                                if (isset($appointment['patientName'])) return $appointment['patientName'];
                                return 'Unknown Patient';
                            }
                            
                            function getDoctorName($appointment) {
                                if (isset($appointment['doctor']['fullName'])) return $appointment['doctor']['fullName'];
                                if (isset($appointment['doctor']['name'])) return $appointment['doctor']['name'];
                                if (isset($appointment['doctor']['firstName']) && isset($appointment['doctor']['lastName'])) {
                                    return trim($appointment['doctor']['firstName'] . ' ' . $appointment['doctor']['lastName']);
                                }
                                if (isset($appointment['doctorName'])) return $appointment['doctorName'];
                                return 'Dr. Unknown Doctor';
                            }
                            
                            $patientName = getPatientName($appointment);
                            $doctorName = getDoctorName($appointment);
                            
                            echo "Patient: <span class='" . ($patientName !== 'Unknown Patient' ? 'success' : 'error') . "'>$patientName</span><br>";
                            echo "Doctor: <span class='" . ($doctorName !== 'Dr. Unknown Doctor' ? 'success' : 'error') . "'>$doctorName</span><br>";
                            echo "Status: " . ($appointment['status'] ?? 'Unknown') . "<br>";
                            echo "<a href='appointments.php' class='btn btn-sm btn-success mt-2'>View Appointments Page</a>";
                            echo "</div>";
                        } else {
                            echo "<div class='test-result error'>❌ Failed to load appointments data</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Prescription Data Check -->
            <div class="col-md-6">
                <div class="card status-card">
                    <div class="card-header bg-warning text-dark">
                        <h5><i class="fas fa-prescription-bottle-alt"></i> Prescriptions Data Check</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $response = makeApiCall(PRESCRIPTION_SERVICE_URL, 'GET', null, $token);
                        if ($response['status_code'] === 200) {
                            if (isset($response['data']) && count($response['data']) > 0) {
                                $prescription = $response['data'][0];
                                echo "<div class='test-result'>";
                                echo "<strong>Sample Prescription Data:</strong><br>";
                                echo "Status: " . ($prescription['status'] ?? 'Unknown') . "<br>";
                                echo "Note: " . (substr($prescription['note'] ?? 'No note', 0, 50)) . "<br>";
                                echo "<span class='success'>✅ Prescriptions loading correctly</span><br>";
                                echo "<a href='prescriptions.php' class='btn btn-sm btn-warning mt-2'>View Prescriptions Page</a>";
                                echo "</div>";
                            } else {
                                echo "<div class='test-result warning'>⚠️ No prescription data found</div>";
                                echo "<a href='prescriptions.php' class='btn btn-sm btn-warning mt-2'>View Prescriptions Page</a>";
                            }
                        } else {
                            echo "<div class='test-result error'>❌ Failed to load prescriptions (Status: " . $response['status_code'] . ")</div>";
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <!-- Patient Data Check -->
            <div class="col-md-6">
                <div class="card status-card">
                    <div class="card-header bg-info text-white">
                        <h5><i class="fas fa-user-injured"></i> Patients Data Check</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $response = makeApiCall(PATIENT_SERVICE_URL, 'GET', null, $token);
                        if ($response['status_code'] === 200 && isset($response['data']) && count($response['data']) > 0) {
                            $patient = $response['data'][0];
                            echo "<div class='test-result'>";
                            echo "<strong>Sample Patient Data:</strong><br>";
                            echo "Name: " . ($patient['fullName'] ?? $patient['name'] ?? 'Unknown') . "<br>";
                            echo "Phone: " . ($patient['phoneNumber'] ?? $patient['phone'] ?? 'Not provided') . "<br>";
                            echo "Age: " . ($patient['age'] ?? 'Unknown') . "<br>";
                            echo "<span class='success'>✅ Patients loading correctly</span><br>";
                            echo "<a href='patients.php' class='btn btn-sm btn-info mt-2'>View Patients Page</a>";
                            echo "</div>";
                        } else {
                            echo "<div class='test-result error'>❌ Failed to load patients data</div>";
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
                        <h5><i class="fas fa-tools"></i> Quick Navigation</h5>
                    </div>
                    <div class="card-body text-center">
                        <a href="dashboard.php" class="btn btn-primary me-2">Dashboard</a>
                        <a href="users.php" class="btn btn-secondary me-2">Users</a>
                        <a href="patients.php" class="btn btn-info me-2">Patients</a>
                        <a href="appointments.php" class="btn btn-success me-2">Appointments</a>
                        <a href="prescriptions.php" class="btn btn-warning me-2">Prescriptions</a>
                        <a href="debug-all-apis.php" class="btn btn-danger">API Debug</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
