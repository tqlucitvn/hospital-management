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
    // Debug: Log POST data to file
    $debugLog = "POST data: " . print_r($_POST, true) . "\n";
    file_put_contents('debug.log', $debugLog, FILE_APPEND | LOCK_EX);
    
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $appointmentId = $_POST['appointmentId'];
        $newStatus = $_POST['status'];
        
        $debugLog = "Updating appointment ID: $appointmentId to status: $newStatus\n";
        file_put_contents('debug.log', $debugLog, FILE_APPEND | LOCK_EX);
        
        $response = makeApiCall(APPOINTMENT_SERVICE_URL . '/' . $appointmentId . '/status', 'PATCH', 
                               ['status' => $newStatus], $_SESSION['token']);
        
        $debugLog = "API Response: " . print_r($response, true) . "\n";
        file_put_contents('debug.log', $debugLog, FILE_APPEND | LOCK_EX);
        
        if ($response['status_code'] === 200) {
            header('Location: appointments.php?success=Appointment status updated');
            exit();
        } else {
            $error = 'Failed to update status: ' . ($response['data']['error'] ?? 'Unknown error');
            $debugLog = "Update failed: " . $error . "\n";
            file_put_contents('debug.log', $debugLog, FILE_APPEND | LOCK_EX);
        }
    } else {
        $error = 'Invalid CSRF token';
        $debugLog = "CSRF token verification failed\n";
        file_put_contents('debug.log', $debugLog, FILE_APPEND | LOCK_EX);
    }
}

// Get appointments list
$token = $_SESSION['token'];
$response = makeApiCall(APPOINTMENT_SERVICE_URL, 'GET', null, $token);
$appointments = $response['status_code'] === 200 ? $response['data'] : [];

// Debug: Add debug info to page (comment out for production)
$debugInfo = '';
// if ($response['status_code'] !== 200) {
//     $debugInfo = '<div class="alert alert-warning">API Response: ' . $response['status_code'] . '</div>';
// } else if (!empty($appointments)) {
//     $debugInfo = '<div class="alert alert-info">Found ' . count($appointments) . ' appointments. First appointment ID: ' . ($appointments[0]['id'] ?? 'NO_ID') . '</div>';
// }

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
$appointmentId = $_GET['id'] ?? null;

// Handle view and edit actions
if (($action === 'view' || $action === 'edit') && $appointmentId) {
    $response = makeApiCall(APPOINTMENT_SERVICE_URL . '/' . $appointmentId, 'GET', null, $token);
    if ($response['status_code'] === 200) {
        $appointment = $response['data'];
    } else {
        $error = 'Appointment not found.';
    }
}

// Handle edit form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'edit' && $appointmentId) {
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
            
            $response = makeApiCall(APPOINTMENT_SERVICE_URL . '/' . $appointmentId, 'PUT', $appointmentData, $_SESSION['token']);
            
            if ($response['status_code'] === 200) {
                header('Location: appointments.php?success=Appointment updated successfully');
                exit();
            } else {
                $error = 'Failed to update appointment: ' . ($response['data']['error'] ?? 'Unknown error');
            }
        } else {
            $error = 'All fields are required.';
        }
    } else {
        $error = 'Invalid CSRF token.';
    }
}

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
} elseif ($action === 'view' && isset($appointment)) {
    // View appointment details
    $startTime = new DateTime($appointment['startTime']);
    $endTime = new DateTime($appointment['endTime']);
    $duration = $startTime->diff($endTime);
    $durationText = $duration->h . 'h ' . $duration->i . 'm';
    
    // Get patient and doctor names
    $patientName = 'Unknown Patient';
    $doctorName = 'Unknown Doctor';
    
    foreach ($patients as $p) {
        if ($p['id'] == $appointment['patientId']) {
            $patientName = $p['firstName'] . ' ' . $p['lastName'];
            break;
        }
    }
    
    foreach ($doctors as $d) {
        if ($d['id'] == $appointment['doctorId']) {
            $doctorName = 'Dr. ' . $d['firstName'] . ' ' . $d['lastName'];
            break;
        }
    }
    
    $statusClass = match($appointment['status']) {
        'SCHEDULED' => 'bg-warning',
        'CONFIRMED' => 'bg-success',
        'COMPLETED' => 'bg-primary',
        'CANCELED' => 'bg-danger',
        default => 'bg-secondary'
    };
    
    $pageContent = '
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 text-gray-800">Appointment Details</h1>
            <div>
                <a href="appointments.php?action=edit&id=' . $appointment['id'] . '" class="btn btn-warning">
                    <i class="bi bi-pencil"></i> Edit
                </a>
                <a href="appointments.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Appointment Information</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Appointment ID:</strong></td>
                                <td>' . htmlspecialchars($appointment['id']) . '</td>
                            </tr>
                            <tr>
                                <td><strong>Patient:</strong></td>
                                <td>' . htmlspecialchars($patientName) . '</td>
                            </tr>
                            <tr>
                                <td><strong>Doctor:</strong></td>
                                <td>' . htmlspecialchars($doctorName) . '</td>
                            </tr>
                            <tr>
                                <td><strong>Status:</strong></td>
                                <td><span class="badge ' . $statusClass . '">' . htmlspecialchars($appointment['status']) . '</span></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <td><strong>Date & Time:</strong></td>
                                <td>' . $startTime->format('M d, Y H:i') . '</td>
                            </tr>
                            <tr>
                                <td><strong>Duration:</strong></td>
                                <td>' . $durationText . '</td>
                            </tr>
                            <tr>
                                <td><strong>End Time:</strong></td>
                                <td>' . $endTime->format('H:i') . '</td>
                            </tr>
                            <tr>
                                <td><strong>Reason:</strong></td>
                                <td>' . htmlspecialchars($appointment['reason'] ?? 'No reason specified') . '</td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>';
} elseif ($action === 'edit' && isset($appointment)) {
    // Edit appointment form
    $startTime = new DateTime($appointment['startTime']);
    $endTime = new DateTime($appointment['endTime']);
    
    $pageContent = '
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 text-gray-800">Edit Appointment</h1>
            <a href="appointments.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Appointments
            </a>
        </div>

        ' . (isset($error) ? '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> ' . htmlspecialchars($error) . '</div>' : '') . '

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Edit Appointment Details</h6>
            </div>
            <div class="card-body">
                <form method="POST" action="appointments.php?action=edit&id=' . $appointment['id'] . '">
                    <input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="patientId" class="form-label">Patient <span class="text-danger">*</span></label>
                                <select class="form-select" id="patientId" name="patientId" required>
                                    <option value="">Select Patient</option>';
                                    
    foreach ($patients as $patient) {
        $selected = $patient['id'] == $appointment['patientId'] ? 'selected' : '';
        $pageContent .= '<option value="' . $patient['id'] . '" ' . $selected . '>' . 
                       htmlspecialchars($patient['firstName'] . ' ' . $patient['lastName']) . '</option>';
    }
    
    $pageContent .= '
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="doctorId" class="form-label">Doctor <span class="text-danger">*</span></label>
                                <select class="form-select" id="doctorId" name="doctorId" required>
                                    <option value="">Select Doctor</option>';
                                    
    foreach ($doctors as $doctor) {
        $selected = $doctor['id'] == $appointment['doctorId'] ? 'selected' : '';
        $pageContent .= '<option value="' . $doctor['id'] . '" ' . $selected . '>' . 
                       htmlspecialchars('Dr. ' . $doctor['firstName'] . ' ' . $doctor['lastName']) . '</option>';
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
                                <input type="datetime-local" class="form-control" id="startTime" name="startTime" 
                                       value="' . $startTime->format('Y-m-d\TH:i') . '" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="endTime" class="form-label">End Time <span class="text-danger">*</span></label>
                                <input type="datetime-local" class="form-control" id="endTime" name="endTime" 
                                       value="' . $endTime->format('Y-m-d\TH:i') . '" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reason" class="form-label">Reason for Visit</label>
                        <textarea class="form-control" id="reason" name="reason" rows="3">' . 
                        htmlspecialchars($appointment['reason'] ?? '') . '</textarea>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-save"></i> Update Appointment
                        </button>
                        <a href="appointments.php" class="btn btn-secondary">
                            <i class="bi bi-x"></i> Cancel
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
        ' . (!empty($error) ? '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> ' . htmlspecialchars($error) . '</div>' : '') . '
        ' . $debugInfo . '
        
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
                    <button class="btn btn-outline-success status-btn" data-id="' . $appt['id'] . '" data-status="CONFIRMED" title="Confirm">
                        <i class="bi bi-check"></i>
                    </button>
                    <button class="btn btn-outline-danger status-btn" data-id="' . $appt['id'] . '" data-status="CANCELED" title="Cancel">
                        <i class="bi bi-x"></i>
                    </button>';
            } elseif ($appt['status'] === 'CONFIRMED') {
                $pageContent .= '
                    <button class="btn btn-outline-primary status-btn" data-id="' . $appt['id'] . '" data-status="COMPLETED" title="Complete">
                        <i class="bi bi-check2-all"></i>
                    </button>
                    <button class="btn btn-outline-danger status-btn" data-id="' . $appt['id'] . '" data-status="CANCELED" title="Cancel">
                        <i class="bi bi-x"></i>
                    </button>';
            }
            
            $pageContent .= '
                        <button class="btn btn-outline-info view-btn" data-id="' . $appt['id'] . '" title="View Details">
                            <i class="bi bi-eye"></i>
                        </button>';
            
            // Add edit button for admin/receptionist
            if (in_array($user['role'], ['ADMIN', 'RECEPTIONIST'])) {
                $pageContent .= '
                        <button class="btn btn-outline-warning edit-btn" data-id="' . $appt['id'] . '" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </button>';
            }
            
            $pageContent .= '
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
    </div>';
}

// Always include the status form and JavaScript for action buttons
$pageContent .= '
<!-- Debug Form Test -->
<div class="alert alert-info mb-3">
    <strong>Debug:</strong> 
    <button type="button" class="btn btn-sm btn-secondary" onclick="testFormSubmit()">Test Form Submit</button>
    <div id="debugOutput"></div>
</div>

<!-- Status Update Form (Hidden) -->
<form id="statusForm" method="POST" action="appointments.php?action=update_status" style="display: none;">
    <input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">
    <input type="hidden" name="appointmentId" id="statusAppointmentId">
    <input type="hidden" name="status" id="statusValue">
</form>

<script>
function testFormSubmit() {
    console.log("Testing form submit...");
    document.getElementById("statusAppointmentId").value = "test-id-123";
    document.getElementById("statusValue").value = "CONFIRMED";
    
    const form = document.getElementById("statusForm");
    console.log("Form:", form);
    console.log("Form action:", form.action);
    console.log("Form method:", form.method);
    
    // Add visible indicator
    document.getElementById("debugOutput").innerHTML = "<br>Form submitted with test data. Check page reload.";
    
    form.submit();
}

document.addEventListener("DOMContentLoaded", function() {
    console.log("Appointment JavaScript loaded");
    
    // Status update buttons
    document.querySelectorAll(".status-btn").forEach(button => {
        button.addEventListener("click", function() {
            const appointmentId = this.getAttribute("data-id");
            const status = this.getAttribute("data-status");
            updateStatus(appointmentId, status);
        });
    });
    
    // View details buttons
    document.querySelectorAll(".view-btn").forEach(button => {
        button.addEventListener("click", function() {
            const appointmentId = this.getAttribute("data-id");
            viewDetails(appointmentId);
        });
    });
    
    // Edit buttons
    document.querySelectorAll(".edit-btn").forEach(button => {
        button.addEventListener("click", function() {
            const appointmentId = this.getAttribute("data-id");
            editAppointment(appointmentId);
        });
    });
});

function updateStatus(appointmentId, status) {
    console.log("updateStatus called with:", appointmentId, status);
    console.log("appointmentId type:", typeof appointmentId);
    console.log("appointmentId length:", appointmentId ? appointmentId.length : "null");
    
    if (confirm("Are you sure you want to update this appointment status to " + status + "?")) {
        console.log("User confirmed, updating status");
        console.log("Setting form values...");
        
        const form = document.getElementById("statusForm");
        const appointmentIdField = document.getElementById("statusAppointmentId");
        const statusField = document.getElementById("statusValue");
        
        if (!form || !appointmentIdField || !statusField) {
            console.error("Form elements not found:", {form, appointmentIdField, statusField});
            alert("Error: Form elements not found");
            return;
        }
        
        appointmentIdField.value = appointmentId;
        statusField.value = status;
        
        console.log("Form appointmentId value:", appointmentIdField.value);
        console.log("Form status value:", statusField.value);
        console.log("Form action:", form.action);
        console.log("Submitting form...");
        
        form.submit();
        console.log("Form submitted");
    } else {
        console.log("User cancelled");
    }
}

function viewDetails(appointmentId) {
    console.log("viewDetails called with:", appointmentId);
    console.log("appointmentId type:", typeof appointmentId);
    console.log("appointmentId value:", JSON.stringify(appointmentId));
    
    // Validate appointment ID (removed length restriction for cuid)
    if (!appointmentId || appointmentId === "NO_ID") {
        console.error("Invalid appointment ID:", appointmentId);
        alert("Error: Invalid appointment ID. ID: " + appointmentId + ", Type: " + typeof appointmentId);
        return;
    }
    
    console.log("Redirecting to view page with ID:", appointmentId);
    window.location.href = "appointments.php?action=view&id=" + appointmentId;
}

function editAppointment(appointmentId) {
    console.log("editAppointment called with:", appointmentId);
    window.location.href = "appointments.php?action=edit&id=" + appointmentId;
}
</script>';

// Include layout
include 'includes/layout.php';
?>
