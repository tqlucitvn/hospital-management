<?php
require_once 'includes/config.php';
requireAnyRole(['ADMIN', 'DOCTOR', 'NURSE']);

$pageTitle = 'Prescription Management';
$user = getCurrentUser();
$action = $_GET['action'] ?? 'list';

// Handle form submission for creating prescription
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        // Parse medication items from form
        $items = [];
        if (isset($_POST['drugName']) && is_array($_POST['drugName'])) {
            for ($i = 0; $i < count($_POST['drugName']); $i++) {
                if (!empty($_POST['drugName'][$i])) {
                    $items[] = [
                        'drugName' => sanitize($_POST['drugName'][$i]),
                        'dosage' => sanitize($_POST['dosage'][$i]),
                        'frequency' => sanitize($_POST['frequency'][$i]),
                        'durationDays' => (int)$_POST['durationDays'][$i],
                        'instruction' => sanitize($_POST['instruction'][$i] ?? '')
                    ];
                }
            }
        }
        
        $prescriptionData = [
            'patientId' => (int)$_POST['patientId'],
            'doctorId' => (int)$_POST['doctorId'],
            'appointmentId' => !empty($_POST['appointmentId']) ? (int)$_POST['appointmentId'] : null,
            'note' => sanitize($_POST['note'] ?? ''),
            'items' => $items
        ];
        
        if (!empty($prescriptionData['patientId']) && !empty($prescriptionData['doctorId']) && !empty($items)) {
            $response = makeApiCall(PRESCRIPTION_SERVICE_URL, 'POST', $prescriptionData, $_SESSION['token']);
            
            if ($response['status_code'] === 201) {
                header('Location: prescriptions.php?success=Prescription created successfully');
                exit();
            } else {
                $error = 'Failed to create prescription: ' . ($response['data']['error'] ?? 'Unknown error');
            }
        } else {
            $error = 'Patient, Doctor, and at least one medication are required.';
        }
    } else {
        $error = 'Invalid CSRF token.';
    }
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'update_status') {
    // Debug: Log POST data
    $debugLog = "Prescription POST data: " . print_r($_POST, true) . "\n";
    file_put_contents('debug.log', $debugLog, FILE_APPEND | LOCK_EX);
    
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $prescriptionId = $_POST['prescriptionId'];
        $newStatus = $_POST['status'];
        
        $debugLog = "Updating prescription ID: $prescriptionId to status: $newStatus\n";
        file_put_contents('debug.log', $debugLog, FILE_APPEND | LOCK_EX);
        
        $response = makeApiCall(PRESCRIPTION_SERVICE_URL . '/' . $prescriptionId . '/status', 'PATCH', 
                               ['status' => $newStatus], $_SESSION['token']);
        
        $debugLog = "Prescription API Response: " . print_r($response, true) . "\n";
        file_put_contents('debug.log', $debugLog, FILE_APPEND | LOCK_EX);
        
        if ($response['status_code'] === 200) {
            header('Location: prescriptions.php?success=Prescription status updated');
            exit();
        } else {
            $error = 'Failed to update status: ' . ($response['data']['error'] ?? 'Unknown error');
            $debugLog = "Prescription update failed: " . $error . "\n";
            file_put_contents('debug.log', $debugLog, FILE_APPEND | LOCK_EX);
        }
    } else {
        $error = 'Invalid CSRF token';
        $debugLog = "Prescription CSRF token verification failed\n";
        file_put_contents('debug.log', $debugLog, FILE_APPEND | LOCK_EX);
    }
}

// Get prescriptions list
$token = $_SESSION['token'];
$response = makeApiCall(PRESCRIPTION_SERVICE_URL, 'GET', null, $token);
$prescriptions = $response['status_code'] === 200 ? $response['data'] : [];

// Get patients and users for dropdowns
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

// Get appointments for optional linking
$appointmentsResponse = makeApiCall(APPOINTMENT_SERVICE_URL, 'GET', null, $token);
$appointments = $appointmentsResponse['status_code'] === 200 ? $appointmentsResponse['data'] : [];

// Ensure we have arrays
if (!is_array($users)) $users = [];
if (!is_array($patients)) $patients = [];
if (!is_array($appointments)) $appointments = [];

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
            <h1 class="h3 text-gray-800">Create New Prescription</h1>
            <a href="prescriptions.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Prescriptions
            </a>
        </div>

        ' . (isset($error) ? '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> ' . htmlspecialchars($error) . '</div>' : '') . '

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-prescription2"></i> Prescription Details
                </h6>
            </div>
            <div class="card-body">
                <form method="POST" action="prescriptions.php?action=add" id="prescriptionForm">
                    <input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="patientId" class="form-label">Patient <span class="text-danger">*</span></label>
                                <select class="form-select" id="patientId" name="patientId" required>
                                    <option value="">Choose patient...</option>';
    
    foreach ($patients as $patient) {
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
                                <label for="appointmentId" class="form-label">Related Appointment (Optional)</label>
                                <select class="form-select" id="appointmentId" name="appointmentId">
                                    <option value="">No related appointment</option>';
    
    foreach ($appointments as $appt) {
        if (is_array($appt) && isset($appt['id'])) {
            $reason = isset($appt['reason']) ? $appt['reason'] : 'Appointment';
            $startTime = isset($appt['startTime']) ? date('M d, Y', strtotime($appt['startTime'])) : '';
            $displayText = "#{$appt['id']} - {$reason}" . ($startTime ? " ({$startTime})" : '');
            
            $pageContent .= '<option value="' . htmlspecialchars($appt['id']) . '">' . 
                           htmlspecialchars($displayText) . '</option>';
        }
    }
    
    $pageContent .= '
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="note" class="form-label">General Notes</label>
                                <textarea class="form-control" id="note" name="note" rows="2" placeholder="General prescription notes..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Medications Section -->
                    <hr>
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5><i class="bi bi-capsule"></i> Medications</h5>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addMedication()">
                            <i class="bi bi-plus"></i> Add Medication
                        </button>
                    </div>
                    
                    <div id="medicationsContainer">
                        <!-- Initial medication row -->
                        <div class="medication-item border p-3 mb-3 rounded">
                            <div class="row">
                                <div class="col-md-3">
                                    <label class="form-label">Drug Name <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="drugName[]" placeholder="e.g., Paracetamol" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Dosage <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" name="dosage[]" placeholder="e.g., 500mg" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Frequency <span class="text-danger">*</span></label>
                                    <select class="form-select" name="frequency[]" required>
                                        <option value="">Choose...</option>
                                        <option value="Once daily">Once daily</option>
                                        <option value="Twice daily">Twice daily</option>
                                        <option value="Three times daily">Three times daily</option>
                                        <option value="Four times daily">Four times daily</option>
                                        <option value="Every 4 hours">Every 4 hours</option>
                                        <option value="Every 6 hours">Every 6 hours</option>
                                        <option value="Every 8 hours">Every 8 hours</option>
                                        <option value="As needed">As needed</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Duration (Days) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control" name="durationDays[]" min="1" max="90" required>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Instructions</label>
                                    <input type="text" class="form-control" name="instruction[]" placeholder="After meals">
                                </div>
                                <div class="col-md-1 d-flex align-items-end">
                                    <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeMedication(this)">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-prescription2"></i> Create Prescription
                        </button>
                        <a href="prescriptions.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    function addMedication() {
        const container = document.getElementById("medicationsContainer");
        const newItem = container.firstElementChild.cloneNode(true);
        
        // Clear values
        newItem.querySelectorAll("input, select").forEach(input => input.value = "");
        
        container.appendChild(newItem);
    }
    
    function removeMedication(button) {
        const container = document.getElementById("medicationsContainer");
        if (container.children.length > 1) {
            button.closest(".medication-item").remove();
        } else {
            alert("At least one medication is required.");
        }
    }
    </script>';

} elseif ($action === 'view' && isset($_GET['id'])) {
    // View single prescription with details
    $prescriptionId = $_GET['id'];
    $response = makeApiCall(PRESCRIPTION_SERVICE_URL . '/' . $prescriptionId, 'GET', null, $token);
    
    if ($response['status_code'] === 200) {
        $prescription = $response['data'];
        
        // Get patient and doctor names
        $patientName = 'Unknown Patient';
        $doctorName = 'Unknown Doctor';
        
        foreach ($patients as $patient) {
            if (is_array($patient) && isset($patient['id']) && $patient['id'] == $prescription['patientId']) {
                $patientName = isset($patient['fullName']) ? $patient['fullName'] : 'Unknown Patient';
                break;
            }
        }
        
        foreach ($users as $user) {
            if (is_array($user) && isset($user['id']) && $user['id'] == $prescription['doctorId']) {
                $userEmail = isset($user['email']) ? $user['email'] : 'Unknown';
                $doctorName = 'Dr. ' . $userEmail;
                break;
            }
        }
        
        $statusClass = 'bg-secondary';
        switch($prescription['status']) {
            case 'ISSUED': $statusClass = 'bg-info'; break;
            case 'FILLED': $statusClass = 'bg-success'; break;
            case 'CANCELED': $statusClass = 'bg-danger'; break;
        }
        
        $pageContent = '
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 text-gray-800">Prescription #' . $prescription['id'] . '</h1>
                <a href="prescriptions.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Prescriptions
                </a>
            </div>

            <div class="row">
                <div class="col-md-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="bi bi-prescription2"></i> Prescription Details
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Patient:</strong> ' . htmlspecialchars($patientName) . '
                                </div>
                                <div class="col-md-6">
                                    <strong>Doctor:</strong> ' . htmlspecialchars($doctorName) . '
                                </div>
                            </div>
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <strong>Status:</strong> <span class="badge ' . $statusClass . '">' . $prescription['status'] . '</span>
                                </div>
                                <div class="col-md-6">
                                    <strong>Created:</strong> ' . date('M d, Y H:i', strtotime($prescription['createdAt'])) . '
                                </div>
                            </div>
                            ' . (!empty($prescription['note']) ? '<div class="mb-3"><strong>Notes:</strong> ' . htmlspecialchars($prescription['note']) . '</div>' : '') . '
                            
                            <h6 class="mt-4"><i class="bi bi-capsule"></i> Medications:</h6>
                            <div class="table-responsive">
                                <table class="table table-bordered">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Drug Name</th>
                                            <th>Dosage</th>
                                            <th>Frequency</th>
                                            <th>Duration</th>
                                            <th>Instructions</th>
                                        </tr>
                                    </thead>
                                    <tbody>';
        
        foreach ($prescription['items'] as $item) {
            $pageContent .= '<tr>
                <td><strong>' . htmlspecialchars($item['drugName']) . '</strong></td>
                <td>' . htmlspecialchars($item['dosage']) . '</td>
                <td>' . htmlspecialchars($item['frequency']) . '</td>
                <td>' . $item['durationDays'] . ' days</td>
                <td>' . htmlspecialchars($item['instruction'] ?? '-') . '</td>
            </tr>';
        }
        
        $pageContent .= '
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Actions</h6>
                        </div>
                        <div class="card-body">';
        
        // Status update buttons
        if ($prescription['status'] === 'ISSUED') {
            $pageContent .= '
                            <button class="btn btn-success btn-sm mb-2 w-100" onclick="updateStatus(\'' . $prescription['id'] . '\', \'FILLED\')">
                                <i class="bi bi-check"></i> Mark as Filled
                            </button>
                            <button class="btn btn-danger btn-sm mb-2 w-100" onclick="updateStatus(\'' . $prescription['id'] . '\', \'CANCELED\')">
                                <i class="bi bi-x"></i> Cancel Prescription
                            </button>';
        }
        
        $pageContent .= '
                            <button class="btn btn-outline-primary btn-sm mb-2 w-100" onclick="window.print()">
                                <i class="bi bi-printer"></i> Print Prescription
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>';
        
    } else {
        $pageContent = '<div class="container-fluid">
            <div class="alert alert-danger">Prescription not found.</div>
            <a href="prescriptions.php" class="btn btn-secondary">Back to Prescriptions</a>
        </div>';
    }

} else {
    // List view
    $pageContent = '
    <div class="container-fluid">
        ' . (!empty($success) ? '<div class="alert alert-success"><i class="bi bi-check-circle"></i> ' . htmlspecialchars($success) . '</div>' : '') . '
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 text-gray-800">Prescription Management</h1>
            <a href="prescriptions.php?action=add" class="btn btn-primary">
                <i class="bi bi-plus"></i> Create Prescription
            </a>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-prescription2"></i> All Prescriptions
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
                                <th>Status</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>';

    if (empty($prescriptions)) {
        $pageContent .= '<tr><td colspan="6" class="text-center text-muted">No prescriptions found.</td></tr>';
    } else {
        foreach ($prescriptions as $pres) {
            // Get patient and doctor names
            $patientName = 'Unknown Patient';
            $doctorName = 'Unknown Doctor';
            
            foreach ($patients as $patient) {
                if (is_array($patient) && isset($patient['id']) && $patient['id'] == $pres['patientId']) {
                    $patientName = isset($patient['fullName']) ? $patient['fullName'] : 'Unknown Patient';
                    break;
                }
            }
            
            foreach ($users as $user) {
                if (is_array($user) && isset($user['id']) && $user['id'] == $pres['doctorId']) {
                    $userEmail = isset($user['email']) ? $user['email'] : 'Unknown';
                    $doctorName = 'Dr. ' . $userEmail;
                    break;
                }
            }
            
            // Status badge
            $statusClass = 'bg-secondary';
            switch($pres['status']) {
                case 'ISSUED': $statusClass = 'bg-info'; break;
                case 'FILLED': $statusClass = 'bg-success'; break;
                case 'CANCELED': $statusClass = 'bg-danger'; break;
            }
            
            $pageContent .= '<tr>
                <td>' . htmlspecialchars($pres['id']) . '</td>
                <td>' . htmlspecialchars($patientName) . '</td>
                <td>' . htmlspecialchars($doctorName) . '</td>
                <td><span class="badge ' . $statusClass . '">' . htmlspecialchars($pres['status']) . '</span></td>
                <td>' . date('M d, Y H:i', strtotime($pres['createdAt'])) . '</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <a href="prescriptions.php?action=view&id=' . $pres['id'] . '" class="btn btn-outline-primary" title="View Details">
                            <i class="bi bi-eye"></i>
                        </a>';
            
            // Status update buttons
            if ($pres['status'] === 'ISSUED') {
                $pageContent .= '
                        <button class="btn btn-outline-success" onclick="updateStatus(\'' . $pres['id'] . '\', \'FILLED\')" title="Mark as Filled">
                            <i class="bi bi-check"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="updateStatus(\'' . $pres['id'] . '\', \'CANCELED\')" title="Cancel">
                            <i class="bi bi-x"></i>
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
    </div>
    
    <!-- Status Update Form (Hidden) -->
    <form id="statusForm" method="POST" action="prescriptions.php?action=update_status" style="display: none;">
        <input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">
        <input type="hidden" name="prescriptionId" id="statusPrescriptionId">
        <input type="hidden" name="status" id="statusValue">
    </form>
    
    <script>
    function updateStatus(prescriptionId, status) {
        if (confirm("Are you sure you want to update this prescription status to " + status + "?")) {
            document.getElementById("statusPrescriptionId").value = prescriptionId;
            document.getElementById("statusValue").value = status;
            document.getElementById("statusForm").submit();
        }
    }
    </script>';
}

// Include layout
include 'includes/layout.php';
?>
