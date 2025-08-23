<?php
require_once 'includes/config.php';
requireAnyRole(['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST']);

$pageTitle = 'Appointment Management';
$user = getCurrentUser();
$action = $_GET['action'] ?? 'list';

// Handle form submission for creating appointment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $appointmentData = [
            'patientId' => (int)$_POST['patientId'],
            'doctorId' => (int)$_POST['doctorId'],
            'startTime' => $_POST['startTime'],
            'endTime' => $_POST['endTime'],
            'reason' => sanitize($_POST['reason'])
        ];
        
        if (!empty($appointmentData['patientId']) && !empty($appointmentData['doctorId']) && 
            !empty($appointmentData['startTime']) && !empty($appointmentData['endTime'])) {
            
            $response = makeApiCall(APPOINTMENT_SERVICE_URL, 'POST', $appointmentData, $_SESSION['token']);
            
            if ($response['status_code'] === 201) {
                header('Location: appointments.php?success=Appointment created successfully');
                exit();
            } else {
                $error = 'Failed to create appointment: ' . ($response['data']['error'] ?? 'Unknown error');
            }
        } else {
            $error = 'All fields are required.';
        }
    } else {
        $error = 'Invalid CSRF token.';
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_status') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $appointmentId = $_POST['appointmentId'];
        $newStatus = $_POST['status'];
        
        $response = makeApiCall(APPOINTMENT_SERVICE_URL . '/' . $appointmentId . '/status', 'PATCH', 
                               ['status' => $newStatus], $_SESSION['token']);
        
        if ($response['status_code'] === 200) {
            header('Location: appointments.php?success=Appointment status updated');
            exit();
        } else {
            $error = 'Failed to update status: ' . ($response['data']['error'] ?? 'Unknown error');
        }
    }
}

// Get appointments list
$token = $_SESSION['token'];
$response = makeApiCall(APPOINTMENT_SERVICE_URL, 'GET', null, $token);
$appointments = $response['status_code'] === 200 ? $response['data'] : [];

// Get patients and users (doctors) for dropdowns
$patientsResponse = makeApiCall(PATIENT_SERVICE_URL, 'GET', null, $token);
$patients = [];
if ($patientsResponse['status_code'] === 200) {
    // Handle both old and new API response formats
    $patients = isset($patientsResponse['data']['patients']) ? 
                $patientsResponse['data']['patients'] : 
                (is_array($patientsResponse['data']) ? $patientsResponse['data'] : []);
}

$usersResponse = makeApiCall(USER_SERVICE_URL, 'GET', null, $token);
$users = $usersResponse['status_code'] === 200 ? $usersResponse['data'] : [];

// Ensure we have arrays and filter doctors
if (!is_array($users)) $users = [];
if (!is_array($patients)) $patients = [];

$doctors = array_filter($users, function($u) { 
    return is_array($u) && isset($u['role']) && $u['role'] === 'DOCTOR'; 
});

// Success message
$success = $_GET['success'] ?? '';

// Set page content based on action
if ($action === 'add') {
    $pageContent = '
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 text-gray-800">Schedule New Appointment</h1>
            <a href="appointments.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Appointments
            </a>
        </div>

        ' . (isset($error) ? '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> ' . htmlspecialchars($error) . '</div>' : '') . '

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-calendar-plus"></i> New Appointment Details
                </h6>
            </div>
            <div class="card-body">
                <form method="POST" action="appointments.php?action=add">
                    <input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="patientId" class="form-label">Patient <span class="text-danger">*</span></label>
                                <select class="form-select" id="patientId" name="patientId" required>
                                    <option value="">Choose patient...</option>';
    
    foreach ($patients as $patient) {
        // Ensure patient is an array and has required fields
        if (is_array($patient) && isset($patient['id'])) {
            $fullName = isset($patient['fullName']) ? $patient['fullName'] : 'Unknown Patient';
            $email = isset($patient['email']) ? $patient['email'] : '';
            $displayText = $fullName . ($email ? ' - ' . $email : '');
            
            $pageContent .= '<option value="' . htmlspecialchars($patient['id']) . '">' . 
                           htmlspecialchars($displayText) . '</option>';
        }
    }
    
    $pageContent .= '
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="doctorId" class="form-label">Doctor <span class="text-danger">*</span></label>
                                <select class="form-select" id="doctorId" name="doctorId" required>
                                    <option value="">Choose doctor...</option>';
    
    foreach ($doctors as $doctor) {
        // Ensure doctor is an array and has required fields
        if (is_array($doctor) && isset($doctor['id'])) {
            $email = isset($doctor['email']) ? $doctor['email'] : 'Unknown Doctor';
            $displayText = 'Dr. ' . $email;
            
            $pageContent .= '<option value="' . htmlspecialchars($doctor['id']) . '">' . 
                           htmlspecialchars($displayText) . '</option>';
        }
    }
    
    $pageContent .= '
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="startTime" class="form-label">Start Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="startTime" name="startTime" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="endTime" class="form-label">End Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="endTime" name="endTime" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Visit</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3" placeholder="Describe the reason for this appointment..."></textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-calendar-plus"></i> Schedule Appointment
                        </button>
                        <a href="appointments.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>';
} else {
    // List view
    $pageContent = '
    <div class="container-fluid">
        ' . (!empty($success) ? '<div class="alert alert-success"><i class="bi bi-check-circle"></i> ' . htmlspecialchars($success) . '</div>' : '') . '
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 text-gray-800">Appointment Management</h1>
            <a href="appointments.php?action=add" class="btn btn-primary">
                <i class="bi bi-plus"></i> Schedule Appointment
            </a>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-calendar-event"></i> All Appointments
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Patient</th>
                                <th>Doctor</th>
                                <th>Date & Time</th>
                                <th>Duration</th>
                                <th>Status</th>
                                <th>Reason</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>';

    if (empty($appointments)) {
        $pageContent .= '<tr><td colspan="8" class="text-center text-muted">No appointments found.</td></tr>';
    } else {
        foreach ($appointments as $appt) {
            // Get patient and doctor names
            $patientName = 'Unknown Patient';
            $doctorName = 'Unknown Doctor';
            
            foreach ($patients as $patient) {
                if (is_array($patient) && isset($patient['id']) && $patient['id'] == $appt['patientId']) {
                    $patientName = isset($patient['fullName']) ? $patient['fullName'] : 'Unknown Patient';
                    break;
                }
            }
            
            foreach ($users as $user) {
                if (is_array($user) && isset($user['id']) && $user['id'] == $appt['doctorId']) {
                    $userEmail = isset($user['email']) ? $user['email'] : 'Unknown';
                    $doctorName = 'Dr. ' . $userEmail;
                    break;
                }
            }
            
            // Status badge
            $statusClass = 'bg-secondary';
            switch($appt['status']) {
                case 'SCHEDULED': $statusClass = 'bg-info'; break;
                case 'CONFIRMED': $statusClass = 'bg-success'; break;
                case 'COMPLETED': $statusClass = 'bg-primary'; break;
                case 'CANCELED': $statusClass = 'bg-danger'; break;
            }
            
            // Calculate duration
            $startTime = new DateTime($appt['startTime']);
            $endTime = new DateTime($appt['endTime']);
            $duration = $startTime->diff($endTime);
            $durationText = $duration->h . 'h ' . $duration->i . 'm';
            
            $pageContent .= '<tr>
                <td>' . htmlspecialchars($appt['id']) . '</td>
                <td>' . htmlspecialchars($patientName) . '</td>
                <td>' . htmlspecialchars($doctorName) . '</td>
                <td>' . $startTime->format('M d, Y H:i') . '</td>
                <td>' . $durationText . '</td>
                <td><span class="badge ' . $statusClass . '">' . htmlspecialchars($appt['status']) . '</span></td>
                <td>' . htmlspecialchars($appt['reason'] ?? '-') . '</td>
                <td>
                    <div class="btn-group btn-group-sm">';
            
            // Status update buttons based on current status
            if ($appt['status'] === 'SCHEDULED') {
                $pageContent .= '
                    <button class="btn btn-outline-success" onclick="updateStatus(' . $appt['id'] . ', \'CONFIRMED\')" title="Confirm">
                        <i class="bi bi-check"></i>
                    </button>
                    <button class="btn btn-outline-danger" onclick="updateStatus(' . $appt['id'] . ', \'CANCELED\')" title="Cancel">
                        <i class="bi bi-x"></i>
                    </button>';
            } elseif ($appt['status'] === 'CONFIRMED') {
                $pageContent .= '
                    <button class="btn btn-outline-primary" onclick="updateStatus(' . $appt['id'] . ', \'COMPLETED\')" title="Complete">
                        <i class="bi bi-check2-all"></i>
                    </button>
                    <button class="btn btn-outline-danger" onclick="updateStatus(' . $appt['id'] . ', \'CANCELED\')" title="Cancel">
                        <i class="bi bi-x"></i>
                    </button>';
            }
            
            $pageContent .= '
                        <button class="btn btn-outline-info" title="View Details">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </td>
            </tr>';
        }
    }

    $pageContent .= '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status Update Form (Hidden) -->
    <form id="statusForm" method="POST" action="appointments.php?action=update_status" style="display: none;">
        <input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">
        <input type="hidden" name="appointmentId" id="statusAppointmentId">
        <input type="hidden" name="status" id="statusValue">
    </form>
    
    <script>
    function updateStatus(appointmentId, status) {
        if (confirm("Are you sure you want to update this appointment status to " + status + "?")) {
            document.getElementById("statusAppointmentId").value = appointmentId;
            document.getElementById("statusValue").value = status;
            document.getElementById("statusForm").submit();
        }
    }
    </script>';
}

// Include layout
include 'includes/layout.php';
?>
