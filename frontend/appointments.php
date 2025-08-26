<?php
require_once 'includes/config.php';
requireAnyRole(['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST']);

$pageTitle = 'Appointment Management';
$user = getCurrentUser();
$action = $_GET['action'] ?? 'list';

$appointments = [];
$patients = [];
$users = [];
$appointment = null;
$error = '';
$success = '';
$pagination = '';

// Helper functions
function getPatientName($patientId, $patients) {
    foreach ($patients as $patient) {
        if (is_array($patient) && isset($patient['id']) && $patient['id'] == $patientId) {
            // Check multiple possible field names for patient name
            if (!empty($patient['fullName'])) {
                return $patient['fullName'];
            } elseif (!empty($patient['name'])) {
                return $patient['name'];
            } elseif (!empty($patient['firstName']) || !empty($patient['lastName'])) {
                $firstName = $patient['firstName'] ?? '';
                $lastName = $patient['lastName'] ?? '';
                $fullName = trim($firstName . ' ' . $lastName);
                if (!empty($fullName)) return $fullName;
            }
            // Fallback to Patient ID if no name found
            return 'Patient #' . substr($patientId, 0, 8);
        }
    }
    return 'Unknown Patient';
}

function getDoctorName($doctorId, $users) {
    foreach ($users as $user) {
        if (is_array($user) && isset($user['id']) && $user['id'] == $doctorId) {
            // Check multiple possible field names for doctor name
            if (!empty($user['fullName'])) {
                return $user['fullName'];
            } elseif (!empty($user['name'])) {
                return $user['name'];
            } elseif (!empty($user['firstName']) || !empty($user['lastName'])) {
                $firstName = $user['firstName'] ?? '';
                $lastName = $user['lastName'] ?? '';
                $fullName = trim($firstName . ' ' . $lastName);
                if (!empty($fullName)) return $fullName;
            } elseif (!empty($user['email'])) {
                // Use email without domain as fallback
                return explode('@', $user['email'])[0];
            }
            // Fallback to Doctor ID if no name found
            return 'Doctor #' . substr($doctorId, 0, 8);
        }
    }
    return 'Unknown Doctor';
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid CSRF token.';
    } else {
        $token = $_SESSION['token'];
        
        if ($action === 'add') {
            // Create appointment
            $appointmentData = [
                'patientId' => sanitize($_POST['patientId']),
                'doctorId' => sanitize($_POST['doctorId']),
                'startTime' => $_POST['startTime'],
                'endTime' => $_POST['endTime'],
                'reason' => sanitize($_POST['reason'])
            ];
            
            $response = makeApiCall(APPOINTMENT_SERVICE_URL, 'POST', $appointmentData, $token);
            
            if ($response['status_code'] === 201) {
                $success = 'Appointment created successfully.';
                $action = 'list'; // Switch back to list view
            } else {
                $error = handleApiError($response) ?: 'Failed to create appointment.';
            }
        } elseif ($action === 'edit' && isset($_POST['id'])) {
            // Update appointment
            $appointmentId = $_POST['id'];
            $appointmentData = [
                'patientId' => sanitize($_POST['patientId']),
                'doctorId' => sanitize($_POST['doctorId']),
                'startTime' => $_POST['startTime'],
                'endTime' => $_POST['endTime'],
                'reason' => sanitize($_POST['reason']),
                'status' => sanitize($_POST['status'])
            ];
            
            $response = makeApiCall(APPOINTMENT_SERVICE_URL . '/' . $appointmentId, 'PUT', $appointmentData, $token);
            
            if ($response['status_code'] === 200) {
                $success = 'Appointment updated successfully.';
                $action = 'list';
            } else {
                $error = handleApiError($response) ?: 'Failed to update appointment.';
            }
        }
    }
}

// Handle delete action
if ($action === 'delete' && isset($_GET['id'])) {
    $token = $_SESSION['token'];
    $appointmentId = $_GET['id'];
    
    $response = makeApiCall(APPOINTMENT_SERVICE_URL . '/' . $appointmentId, 'DELETE', null, $token);
    
    if ($response['status_code'] === 200 || $response['status_code'] === 204) {
        $success = 'Appointment deleted successfully.';
    } else {
        $error = handleApiError($response) ?: 'Failed to delete appointment.';
    }
    $action = 'list';
}

// Fetch data based on action
try {
    $token = $_SESSION['token'];
    
    if ($action === 'list') {
        // Build query parameters for appointments
        $queryParams = [
            'page' => $page,
            'limit' => $limit
        ];
        if ($search) {
            $queryParams['search'] = $search;
        }
        $queryString = http_build_query($queryParams);
        
        $response = makeApiCall(APPOINTMENT_SERVICE_URL . '?' . $queryString, 'GET', null, $token);
        
        if ($response['status_code'] === 200) {
            // Handle paginated response
            if (isset($response['data']['appointments']) && isset($response['data']['total'])) {
                $appointments = $response['data']['appointments'];
                $totalAppointments = $response['data']['total'];
            } else {
                // Fallback for non-paginated response
                $appointments = is_array($response['data']) ? $response['data'] : [];
                
                // Apply client-side search if API doesn't support it
                if ($search) {
                    $appointments = array_filter($appointments, function($apt) use ($search) {
                        $searchLower = strtolower($search);
                        return stripos($apt['reason'] ?? '', $search) !== false ||
                               stripos($apt['patientId'] ?? '', $search) !== false ||
                               stripos($apt['doctorId'] ?? '', $search) !== false ||
                               stripos($apt['status'] ?? '', $search) !== false;
                    });
                }
                
                $totalAppointments = count($appointments);
                $appointments = array_slice($appointments, $offset, $limit);
            }
            
            $totalPages = ceil($totalAppointments / $limit);
            
            // Generate pagination
            if ($totalPages > 1) {
                $baseUrl = 'appointments.php?';
                if ($search) $baseUrl .= 'search=' . urlencode($search) . '&';
                $pagination = paginate($page, $totalPages, $baseUrl);
            }
        } else {
            $error = handleApiError($response) ?: 'Failed to load appointments.';
        }
    } elseif (($action === 'edit' || $action === 'view') && isset($_GET['id'])) {
        $appointmentId = $_GET['id'];
        $response = makeApiCall(APPOINTMENT_SERVICE_URL . '/' . $appointmentId, 'GET', null, $token);
        if ($response['status_code'] === 200) {
            $appointment = $response['data'];
        } else {
            $error = handleApiError($response) ?: 'Appointment not found.';
        }
    }
    
    // Get patients and users for dropdowns (for add/edit forms)
    if ($action === 'add' || $action === 'edit') {
        $patientsResponse = makeApiCall(PATIENT_SERVICE_URL, 'GET', null, $token);
        if ($patientsResponse['status_code'] === 200) {
            $patients = isset($patientsResponse['data']['patients']) ? 
                        $patientsResponse['data']['patients'] : 
                        (is_array($patientsResponse['data']) ? $patientsResponse['data'] : []);
        }
        
        $usersResponse = makeApiCall(USER_SERVICE_URL, 'GET', null, $token);
        if ($usersResponse['status_code'] === 200) {
            $users = is_array($usersResponse['data']) ? $usersResponse['data'] : [];
            // Filter doctors
            $users = array_filter($users, function($user) {
                return isset($user['role']) && $user['role'] === 'DOCTOR';
            });
        }
    }
    
    // For list view, get basic patient and user data for display
    if ($action === 'list' && !empty($appointments)) {
        $patientsResponse = makeApiCall(PATIENT_SERVICE_URL, 'GET', null, $token);
        if ($patientsResponse['status_code'] === 200) {
            $patients = isset($patientsResponse['data']['patients']) ? 
                        $patientsResponse['data']['patients'] : 
                        (is_array($patientsResponse['data']) ? $patientsResponse['data'] : []);
        }
        
        $usersResponse = makeApiCall(USER_SERVICE_URL, 'GET', null, $token);
        if ($usersResponse['status_code'] === 200) {
            $users = is_array($usersResponse['data']) ? $usersResponse['data'] : [];
        }
    }
    
} catch (Exception $e) {
    $error = 'System error: ' . $e->getMessage();
}

// Start output buffering for page content
ob_start();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">
            <i class="bi bi-calendar-check"></i>
            Appointment Management
        </h1>
        <p class="text-muted mb-0">Schedule and manage patient appointments</p>
    </div>
    
    <?php if ($action === 'list' && hasAnyRole(['ADMIN', 'DOCTOR', 'RECEPTIONIST'])): ?>
    <div>
        <a href="appointments.php?action=add" class="btn btn-primary">
            <i class="bi bi-calendar-plus"></i>
            Schedule Appointment
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i>
        <?php echo $error; ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i>
        <?php echo $success; ?>
    </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
<!-- Appointments List -->
<div class="card">
    <div class="card-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-0">
                    <i class="bi bi-list"></i>
                    Appointments List
                    <?php if (isset($totalAppointments) && $totalAppointments > 0): ?>
                    <span class="badge bg-primary ms-2"><?php echo $totalAppointments; ?></span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="col-md-6">
                <!-- Search Form -->
                <form method="GET" class="d-flex">
                    <input type="text" 
                           class="form-control form-control-sm me-2" 
                           name="search" 
                           placeholder="Search appointments..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-search"></i>
                    </button>
                    <?php if ($search): ?>
                    <a href="appointments.php" class="btn btn-outline-secondary btn-sm ms-1">
                        <i class="bi bi-x"></i>
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="border-0">
                            <i class="bi bi-hash me-1"></i>ID
                        </th>
                        <th class="border-0">
                            <i class="bi bi-person me-1"></i>Patient
                        </th>
                        <th class="border-0">
                            <i class="bi bi-person-badge me-1"></i>Doctor
                        </th>
                        <th class="border-0">
                            <i class="bi bi-calendar-event me-1"></i>Date & Time
                        </th>
                        <th class="border-0">
                            <i class="bi bi-clock me-1"></i>Duration
                        </th>
                        <th class="border-0">
                            <i class="bi bi-flag me-1"></i>Status
                        </th>
                        <th class="border-0">
                            <i class="bi bi-chat-text me-1"></i>Reason
                        </th>
                        <th class="border-0 text-center">
                            <i class="bi bi-gear me-1"></i>Actions
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($appointments)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2 mb-0">No appointments found</p>
                            <?php if ($search): ?>
                            <small class="text-muted">Try adjusting your search criteria</small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($appointments as $appt): ?>
                    <?php
                    // Get patient and doctor names
                    $patientName = getPatientName($appt['patientId'], $patients);
                    $doctorName = getDoctorName($appt['doctorId'], $users);
                    
                    // Format dates
                    $startTime = isset($appt['startTime']) ? date('M j, Y H:i', strtotime($appt['startTime'])) : 'N/A';
                    $duration = 'N/A';
                    if (isset($appt['startTime']) && isset($appt['endTime'])) {
                        $start = strtotime($appt['startTime']);
                        $end = strtotime($appt['endTime']);
                        $minutes = ($end - $start) / 60;
                        $duration = $minutes . ' min';
                    }
                    
                    // Status styling
                    $statusClass = getAppointmentStatusClass($appt['status'] ?? 'UNKNOWN');
                    $statusText = ucfirst(strtolower($appt['status'] ?? 'Unknown'));
                    ?>
                    <tr>
                        <td class="align-middle">
                            <span class="text-monospace small"><?php echo htmlspecialchars(substr($appt['id'] ?? 'N/A', 0, 8)); ?>...</span>
                        </td>
                        <td class="align-middle">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                    <?php echo strtoupper(substr($patientName, 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($patientName); ?></div>
                                    <small class="text-muted">ID: <?php echo htmlspecialchars(substr($appt['patientId'] ?? 'N/A', 0, 8)); ?>...</small>
                                </div>
                            </div>
                        </td>
                        <td class="align-middle">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                    <?php echo strtoupper(substr($doctorName, 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">Dr. <?php echo htmlspecialchars($doctorName); ?></div>
                                    <small class="text-muted">ID: <?php echo htmlspecialchars(substr($appt['doctorId'] ?? 'N/A', 0, 8)); ?>...</small>
                                </div>
                            </div>
                        </td>
                        <td class="align-middle">
                            <div class="fw-bold text-dark"><?php echo $startTime; ?></div>
                            <small class="text-muted"><?php echo isset($appt['startTime']) ? date('l', strtotime($appt['startTime'])) : ''; ?></small>
                        </td>
                        <td class="align-middle">
                            <span class="badge bg-light text-dark"><?php echo $duration; ?></span>
                        </td>
                        <td class="align-middle">
                            <span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                        </td>
                        <td class="align-middle">
                            <div class="text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($appt['reason'] ?? 'No reason provided'); ?>">
                                <?php echo htmlspecialchars($appt['reason'] ?? 'No reason provided'); ?>
                            </div>
                        </td>
                        <td class="align-middle text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="appointments.php?action=view&id=<?php echo $appt['id']; ?>" 
                                   class="btn btn-outline-primary btn-sm" 
                                   title="View Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (hasAnyRole(['ADMIN', 'DOCTOR', 'RECEPTIONIST'])): ?>
                                <a href="appointments.php?action=edit&id=<?php echo $appt['id']; ?>" 
                                   class="btn btn-outline-warning btn-sm" 
                                   title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (hasRole('ADMIN')): ?>
                                <button type="button" 
                                        class="btn btn-outline-danger btn-sm" 
                                        onclick="confirmDelete('<?php echo $appt['id']; ?>', '<?php echo htmlspecialchars($patientName); ?>')" 
                                        title="Delete">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if ($pagination): ?>
    <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="text-muted">
            Showing <?php echo (($page - 1) * $limit + 1); ?> to <?php echo min($page * $limit, $totalAppointments ?? 0); ?> of <?php echo $totalAppointments ?? 0; ?> appointments
        </div>
        <nav>
            <?php echo $pagination; ?>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($action === 'add' || ($action === 'edit' && $appointment)): ?>
<!-- Add/Edit Appointment Form -->
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-<?php echo $action === 'add' ? 'calendar-plus' : 'pencil'; ?>"></i>
                    <?php echo $action === 'add' ? 'Schedule New Appointment' : 'Edit Appointment'; ?>
                </h5>
            </div>
            
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                    <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo $appointment['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="patientId" class="form-label">Patient *</label>
                            <select class="form-select" id="patientId" name="patientId" required>
                                <option value="">Select a patient...</option>
                                <?php 
                                $preselectPatientId = $_GET['patient_id'] ?? ($action === 'edit' ? $appointment['patientId'] : '');
                                foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>" 
                                        <?php echo ($patient['id'] == $preselectPatientId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($patient['fullName'] ?? 'Unknown'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="doctorId" class="form-label">Doctor *</label>
                            <select class="form-select" id="doctorId" name="doctorId" required>
                                <option value="">Select a doctor...</option>
                                <?php foreach ($users as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>" 
                                        <?php echo ($action === 'edit' && $appointment['doctorId'] == $doctor['id']) ? 'selected' : ''; ?>>
                                    Dr. <?php echo htmlspecialchars($doctor['fullName'] ?? $doctor['email'] ?? 'Unknown'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="startTime" class="form-label">Start Date & Time *</label>
                            <input type="datetime-local" 
                                   class="form-control" 
                                   id="startTime" 
                                   name="startTime" 
                                   value="<?php echo $action === 'edit' ? date('Y-m-d\TH:i', strtotime($appointment['startTime'])) : ''; ?>" 
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="endTime" class="form-label">End Date & Time *</label>
                            <input type="datetime-local" 
                                   class="form-control" 
                                   id="endTime" 
                                   name="endTime" 
                                   value="<?php echo $action === 'edit' ? date('Y-m-d\TH:i', strtotime($appointment['endTime'])) : ''; ?>" 
                                   required>
                        </div>
                        
                        <?php if ($action === 'edit'): ?>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="SCHEDULED" <?php echo ($appointment['status'] ?? '') === 'SCHEDULED' ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="CONFIRMED" <?php echo ($appointment['status'] ?? '') === 'CONFIRMED' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="COMPLETED" <?php echo ($appointment['status'] ?? '') === 'COMPLETED' ? 'selected' : ''; ?>>Completed</option>
                                <option value="CANCELED" <?php echo ($appointment['status'] ?? '') === 'CANCELED' ? 'selected' : ''; ?>>Canceled</option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-12 mb-3">
                            <label for="reason" class="form-label">Reason for Visit</label>
                            <textarea class="form-control" 
                                      id="reason" 
                                      name="reason" 
                                      rows="3" 
                                      placeholder="Enter the reason for this appointment..."><?php echo $action === 'edit' ? htmlspecialchars($appointment['reason'] ?? '') : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <a href="appointments.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check"></i> <?php echo $action === 'add' ? 'Schedule Appointment' : 'Update Appointment'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($action === 'view' && $appointment): ?>
<!-- View Appointment Details -->
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-event"></i>
                        Appointment Details
                    </h5>
                    <div>
                        <?php if (hasAnyRole(['ADMIN', 'DOCTOR', 'RECEPTIONIST'])): ?>
                        <a href="appointments.php?action=edit&id=<?php echo $appointment['id']; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <?php endif; ?>
                        <a href="appointments.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Appointment ID</label>
                        <p class="fw-bold text-monospace"><?php echo htmlspecialchars($appointment['id']); ?></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Status</label>
                        <p><span class="<?php echo getAppointmentStatusClass($appointment['status'] ?? 'UNKNOWN'); ?>"><?php echo ucfirst(strtolower($appointment['status'] ?? 'Unknown')); ?></span></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Patient</label>
                        <p class="fw-bold"><?php echo htmlspecialchars(getPatientName($appointment['patientId'], $patients)); ?></p>
                        <small class="text-muted">ID: <?php echo htmlspecialchars($appointment['patientId']); ?></small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Doctor</label>
                        <p class="fw-bold">Dr. <?php echo htmlspecialchars(getDoctorName($appointment['doctorId'], $users)); ?></p>
                        <small class="text-muted">ID: <?php echo htmlspecialchars($appointment['doctorId']); ?></small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">Start Date & Time</label>
                        <p class="fw-bold"><?php echo isset($appointment['startTime']) ? date('l, F j, Y \a\t H:i', strtotime($appointment['startTime'])) : 'N/A'; ?></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted">End Date & Time</label>
                        <p class="fw-bold"><?php echo isset($appointment['endTime']) ? date('l, F j, Y \a\t H:i', strtotime($appointment['endTime'])) : 'N/A'; ?></p>
                    </div>
                    
                    <div class="col-12 mb-3">
                        <label class="form-label text-muted">Reason for Visit</label>
                        <div class="card bg-light">
                            <div class="card-body">
                                <?php if ($appointment['reason'] ?? false): ?>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($appointment['reason'])); ?></p>
                                <?php else: ?>
                                    <p class="mb-0 text-muted">No reason provided.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <?php if (hasAnyRole(['ADMIN', 'DOCTOR', 'RECEPTIONIST'])): ?>
                    <a href="appointments.php?action=edit&id=<?php echo $appointment['id']; ?>" class="btn btn-primary">
                        <i class="bi bi-pencil"></i> Edit Appointment
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($action === 'view' && !$appointment): ?>
<!-- Appointment Not Found -->
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-calendar-x text-muted" style="font-size: 4rem;"></i>
                <h3 class="mt-3 text-muted">Appointment Not Found</h3>
                <p class="text-muted">The appointment you're looking for could not be found or you don't have permission to view it.</p>
                <a href="appointments.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left"></i> Back to Appointments List
                </a>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the appointment for <strong id="appointmentPatient"></strong>?</p>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete Appointment</a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(appointmentId, patientName) {
    document.getElementById('appointmentPatient').textContent = patientName;
    document.getElementById('confirmDeleteBtn').href = 'appointments.php?action=delete&id=' + appointmentId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Auto-focus first input
document.addEventListener('DOMContentLoaded', function() {
    const firstInput = document.querySelector('form input:not([type="hidden"]), form select');
    if (firstInput) {
        firstInput.focus();
    }
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
