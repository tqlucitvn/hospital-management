<?php
require_once 'includes/config.php';
require_once 'includes/language.php';
requireAnyRole(['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST']);

$pageTitle = __('appointment_management');
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
            return sprintf(__('patient_fallback_id'), substr($patientId, 0, 8));
        }
    }
    return __('unknown_patient');
}

function getDoctorName($doctorId, $users) {
    // Debug: Log the search
    error_log("DEBUG getDoctorName - Looking for doctorId: " . $doctorId . ", Users count: " . count($users));
    
    foreach ($users as $user) {
        if (is_array($user) && isset($user['id']) && $user['id'] == $doctorId) {
            // Check multiple possible field names for doctor name
            if (!empty($user['fullName'])) {
                error_log("DEBUG getDoctorName - Found doctor: " . $user['fullName']);
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
            return sprintf(__('doctor_fallback_id'), substr($doctorId, 0, 8));
        }
    }
    error_log("DEBUG getDoctorName - Doctor not found, returning unknown_doctor");
    return __('unknown_doctor');
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = __('invalid_csrf_token');
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
                $success = __('appointment_created_success');
                $action = 'list'; // Switch back to list view
            } else {
                $error = handleApiError($response) ?: __('failed_to_create_appointment');
            }
        } elseif ($action === 'edit' && isset($_POST['id'])) {
            // Update appointment info
            $appointmentId = $_POST['id'];
            $appointmentData = [
                'patientId' => sanitize($_POST['patientId']),
                'doctorId' => sanitize($_POST['doctorId']),
                'startTime' => $_POST['startTime'],
                'endTime' => $_POST['endTime'],
                'reason' => sanitize($_POST['reason'])
            ];
            $response = makeApiCall(APPOINTMENT_SERVICE_URL . '/' . $appointmentId, 'PUT', $appointmentData, $token);
            if ($response['status_code'] === 200) {
                // Luôn gọi PATCH đổi status nếu có trường status
                if (isset($_POST['status'])) {
                    $statusData = ['status' => sanitize($_POST['status'])];
                    $statusResponse = makeApiCall(APPOINTMENT_SERVICE_URL . '/' . $appointmentId . '/status', 'PATCH', $statusData, $token);
                        if ($statusResponse['status_code'] === 200) {
                        $success = __('appointment_updated_success');
                        $action = 'list';
                    } else {
                        $error = handleApiError($statusResponse) ?: __('failed_to_update_appointment_status');
                    }
                } else {
                    $success = __('appointment_updated_success');
                    $action = 'list';
                }
            } else {
                $error = handleApiError($response) ?: __('failed_to_update_appointment');
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
    $success = __('appointment_deleted_success');
    } else {
    $error = handleApiError($response) ?: __('failed_to_delete_appointment');
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
            $error = handleApiError($response) ?: __('failed_to_load_appointments');
        }
    } elseif (($action === 'edit' || $action === 'view') && isset($_GET['id'])) {
        $appointmentId = $_GET['id'];
        $response = makeApiCall(APPOINTMENT_SERVICE_URL . '/' . $appointmentId, 'GET', null, $token);
        if ($response['status_code'] === 200) {
            $appointment = $response['data'];
        } else {
            $error = handleApiError($response) ?: __('appointment_not_found');
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

        $users = [];
        $usersResponse = makeApiCall(USER_SERVICE_URL . '/doctors', 'GET', null, $token);
        if ($usersResponse['status_code'] === 200) {
            // Doctors endpoint returns array directly
            $users = is_array($usersResponse['data']) ? $usersResponse['data'] : [];
        }
        // Nếu là DOCTOR và không có user nào, thêm chính user hiện tại vào $users
        if ($user['role'] === 'DOCTOR') {
            $found = false;
            foreach ($users as $u) {
                if (isset($u['id']) && $u['id'] == $user['id']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                // Add current user to users array, fetch latest data from API
                $currentUserResponse = makeApiCall(USER_SERVICE_URL . '/me', 'GET', null, $token);
                error_log("DEBUG Add/Edit form - /me API response: " . json_encode($currentUserResponse));
                if ($currentUserResponse['status_code'] === 200 && isset($currentUserResponse['data'])) {
                    $users[] = $currentUserResponse['data'];
                    error_log("DEBUG Add/Edit form - Added real user data: " . json_encode($currentUserResponse['data']));
                } else {
                    // Fallback to session data
                    $users[] = $user;
                    error_log("DEBUG Add/Edit form - Fallback to session data: " . json_encode($user));
                }
            }
        }
    }
    
    // For list view, get basic patient and user data for display
    if (($action === 'list' && !empty($appointments)) || ($action === 'view' && isset($appointment))) {
        $patientsResponse = makeApiCall(PATIENT_SERVICE_URL, 'GET', null, $token);
        if ($patientsResponse['status_code'] === 200) {
            $patients = isset($patientsResponse['data']['patients']) ? 
                        $patientsResponse['data']['patients'] : 
                        (is_array($patientsResponse['data']) ? $patientsResponse['data'] : []);
        }
        
        $usersResponse = makeApiCall(USER_SERVICE_URL . '/doctors', 'GET', null, $token);
        if ($usersResponse['status_code'] === 200) {
            $users = is_array($usersResponse['data']) ? $usersResponse['data'] : [];
        }
        
        // Nếu là DOCTOR và không có user nào trong list, thêm chính user hiện tại vào $users
        if ($user['role'] === 'DOCTOR') {
            $found = false;
            foreach ($users as $u) {
                if (isset($u['id']) && $u['id'] == $user['id']) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                // Add current user to users array, fetch latest data from API
                $currentUserResponse = makeApiCall(USER_SERVICE_URL . '/me', 'GET', null, $token);
                if ($currentUserResponse['status_code'] === 200 && isset($currentUserResponse['data'])) {
                    $users[] = $currentUserResponse['data'];
                } else {
                    // Fallback to session data
                    $users[] = $user;
                }
            }
            
            // Debug: Log users array for doctor
            error_log("DEBUG Doctor view - Users count: " . count($users) . ", Current user ID: " . $user['id']);
            error_log("DEBUG Doctor view - Users: " . json_encode(array_map(function($u) { 
                return ['id' => $u['id'] ?? 'missing', 'fullName' => $u['fullName'] ?? 'missing', 'email' => $u['email'] ?? 'missing']; 
            }, $users)));
        }
    }
    
} catch (Exception $e) {
    $error = sprintf(__('system_error_with_message'), $e->getMessage());
}

// Start output buffering for page content
ob_start();
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-1">
            <i class="bi bi-calendar-check"></i>
            <?php echo __('appointment_management'); ?>
        </h1>
        <p class="text-muted mb-0"><?php echo __('appointment_management_description'); ?></p>
    </div>
    
    <?php if ($action === 'list' && hasAnyRole(['ADMIN', 'DOCTOR', 'RECEPTIONIST'])): ?>
    <div>
        <a href="appointments.php?action=add" class="btn btn-primary">
            <i class="bi bi-calendar-plus"></i>
            <?php echo __('schedule_appointment'); ?>
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i>
    <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i>
    <?php echo htmlspecialchars($success); ?>
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
                    <?php echo __('appointment_list'); ?>
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
                  placeholder="<?php echo __('search_appointments'); ?>" 
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
                                <i class="bi bi-hash me-1"></i><?php echo __('id'); ?>
                            </th>
                            <th class="border-0">
                                <i class="bi bi-person me-1"></i><?php echo __('patient'); ?>
                            </th>
                            <th class="border-0">
                                <i class="bi bi-person-badge me-1"></i><?php echo __('doctor'); ?>
                            </th>
                            <th class="border-0">
                                <i class="bi bi-calendar-event me-1"></i><?php echo __('date'); ?> &amp; <?php echo __('time'); ?>
                            </th>
                            <th class="border-0">
                                <i class="bi bi-clock me-1"></i><?php echo __('duration'); ?>
                            </th>
                            <th class="border-0">
                                <i class="bi bi-flag me-1"></i><?php echo __('status'); ?>
                            </th>
                            <th class="border-0">
                                <i class="bi bi-chat-text me-1"></i><?php echo __('reason'); ?>
                            </th>
                            <th class="border-0 text-center">
                                <i class="bi bi-gear me-1"></i><?php echo __('actions'); ?>
                            </th>
                    </tr>
                </thead>
                <tbody>
                            <?php if (empty($appointments)): ?>
                    <tr>
                        <td colspan="8" class="text-center py-5">
                            <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2 mb-0"><?php echo __("no_appointments_found"); ?></p>
                            <?php if ($search): ?>
                            <small class="text-muted"><?php echo __('try_adjust_search'); ?></small>
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
                    $startTime = isset($appt['startTime']) ? formatDateTimeVietnamese(strtotime($appt['startTime'])) : __('not_provided');
                    $duration = __('not_provided');
                    if (isset($appt['startTime']) && isset($appt['endTime'])) {
                        $start = strtotime($appt['startTime']);
                        $end = strtotime($appt['endTime']);
                        $minutes = ($end - $start) / 60;
                        $duration = sprintf(__('minutes_format'), $minutes);
                    }
                    
                    // Status styling
                    $statusClass = getAppointmentStatusClass($appt['status'] ?? 'UNKNOWN');
                    $statusText = getAppointmentStatusText($appt['status'] ?? 'UNKNOWN');
                    ?>
                    <tr>
                        <td class="align-middle">
                            <span class="text-monospace small"><?php echo htmlspecialchars(substr($appt['id'] ?? __('not_provided'), 0, 8)); ?>...</span>
                        </td>
                        <td class="align-middle">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                    <?php echo strtoupper(substr($patientName, 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($patientName); ?></div>
                                    <small class="text-muted"><?php echo __('patient_id_label'); ?>: <?php echo htmlspecialchars(substr($appt['patientId'] ?? __('not_provided'), 0, 8)); ?>...</small>
                                </div>
                            </div>
                        </td>
                        <td class="align-middle">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                    <?php echo strtoupper(substr($doctorName, 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark"><?php echo sprintf(__('doctor_title_name'), htmlspecialchars($doctorName)); ?></div>
                                    <small class="text-muted"><?php echo __('doctor_id_label'); ?>: <?php echo htmlspecialchars(substr($appt['doctorId'] ?? __('not_provided'), 0, 8)); ?>...</small>
                                </div>
                            </div>
                        </td>
                        <td class="align-middle">
                            <div class="fw-bold text-dark"><?php echo $startTime; ?></div>
                            <small class="text-muted"><?php echo isset($appt['startTime']) ? formatDateVietnamese('day', strtotime($appt['startTime'])) : ''; ?></small>
                        </td>
                        <td class="align-middle">
                            <span class="badge bg-light text-dark"><?php echo $duration; ?></span>
                        </td>
                        <td class="align-middle">
                            <span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                        </td>
                        <td class="align-middle">
                                <div class="text-truncate" style="max-width: 150px;" title="<?php echo htmlspecialchars($appt['reason'] ?? __('no_reason_provided')); ?>">
                                <?php echo htmlspecialchars($appt['reason'] ?? __('no_reason_provided')); ?>
                            </div>
                        </td>
                        <td class="align-middle text-center">
                            <div class="btn-group btn-group-sm">
                                          <a href="appointments.php?action=view&id=<?php echo $appt['id']; ?>" 
                                              class="btn btn-outline-primary btn-sm" 
                                              title="<?php echo __('view'); ?>">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (hasAnyRole(['ADMIN', 'DOCTOR', 'RECEPTIONIST'])): ?>
                                          <a href="appointments.php?action=edit&id=<?php echo $appt['id']; ?>" 
                                              class="btn btn-outline-warning btn-sm" 
                                              title="<?php echo __('edit'); ?>">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php endif; ?>
                                <?php 
                                // Debug: Check prescribe button conditions
                                $hasPrescriberRole = hasAnyRole(['ADMIN', 'DOCTOR']);
                                $hasValidStatus = ($appt['status'] === 'CONFIRMED' || $appt['status'] === 'COMPLETED');
                                error_log("DEBUG Prescribe Button - Appointment {$appt['id']}: Role=$hasPrescriberRole, Status={$appt['status']}, ValidStatus=$hasValidStatus");
                                ?>
                                <?php if ($hasPrescriberRole && $hasValidStatus): ?>
                                <a href="prescriptions.php?action=add&appointment_id=<?php echo $appt['id']; ?>" 
                                   class="btn btn-outline-success btn-sm" 
                                   title="<?php echo __('create_prescription'); ?>">
                                    <i class="bi bi-prescription2"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (hasRole('ADMIN')): ?>
                <button type="button" 
                    class="btn btn-outline-danger btn-sm" 
                    onclick="confirmDelete('<?php echo $appt['id']; ?>', '<?php echo addslashes($patientName); ?>')" 
                    title="<?php echo __('delete'); ?>">
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
                <?php echo sprintf(__('showing_appointments_range'), (($page - 1) * $limit + 1), min($page * $limit, $totalAppointments ?? 0), $totalAppointments ?? 0); ?>
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
                    <?php echo $action === 'add' ? __('schedule_appointment') : __('edit_appointment'); ?>
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
                            <label for="patientId" class="form-label"><?php echo __('patient'); ?> *</label>
                            <select class="form-select js-searchable-select" data-search-placeholder="<?php echo __('search_patients'); ?>" id="patientId" name="patientId" required>
                                <option value=""><?php echo __('select_patient'); ?></option>
                                <?php 
                                $preselectPatientId = $_GET['patient_id'] ?? ($action === 'edit' ? $appointment['patientId'] : '');
                                foreach ($patients as $patient): ?>
                                <option value="<?php echo $patient['id']; ?>" 
                                        <?php echo ($patient['id'] == $preselectPatientId) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($patient['fullName'] ?? __('unknown')); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="doctorId" class="form-label"><?php echo __('doctor'); ?> *</label>
                            <?php 
                            // Determine if doctor field should be disabled
                            // Disable when:
                            // 1. Doctor editing their own appointment
                            // 2. Doctor creating new appointment (they should only create for themselves)
                            $isDoctorFieldDisabled = ($user['role'] === 'DOCTOR' && 
                                                    (($action === 'edit' && $appointment['doctorId'] === $user['id']) || 
                                                     ($action === 'add')));
                            ?>
                            <select class="form-select js-searchable-select" data-search-placeholder="<?php echo __('search_doctors'); ?>" id="doctorId" name="doctorId" required 
                                    <?php echo $isDoctorFieldDisabled ? 'disabled' : ''; ?>>
                                <option value=""><?php echo __('select_doctor'); ?></option>
                                <?php 
                                // Logic for preselecting doctor in form
                                if ($action === 'edit') {
                                    // For edit mode: 
                                    // - If current user is DOCTOR and editing their own appointment, preselect themselves
                                    // - Otherwise, preselect the current doctor of the appointment
                                    if ($user['role'] === 'DOCTOR' && $appointment['doctorId'] === $user['id']) {
                                        $preselectDoctorId = $user['id'];
                                    } else {
                                        $preselectDoctorId = $appointment['doctorId'];
                                    }
                                } else {
                                    // For add mode:
                                    // - If URL has doctor_id parameter, use it
                                    // - If current user is DOCTOR, preselect themselves
                                    // - Otherwise, no preselection
                                    $preselectDoctorId = $_GET['doctor_id'] ?? (($user['role'] === 'DOCTOR') ? $user['id'] : '');
                                }
                                
                                foreach ($users as $doctor): ?>
                                <option value="<?php echo $doctor['id']; ?>" 
                                        <?php echo ($doctor['id'] == $preselectDoctorId) ? 'selected' : ''; ?>>
                                    <?php echo sprintf(__('doctor_title_name'), htmlspecialchars($doctor['fullName'] ?? $doctor['email'] ?? __('unknown'))); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($isDoctorFieldDisabled): ?>
                                <!-- Hidden input to ensure doctorId is submitted when select is disabled -->
                                <input type="hidden" name="doctorId" value="<?php echo htmlspecialchars($preselectDoctorId); ?>">
                                <small class="text-muted">
                                    <?php if ($action === 'edit'): ?>
                                        <?php echo __('doctor_field_locked_edit_message'); ?>
                                    <?php else: ?>
                                        <?php echo __('doctor_field_locked_add_message'); ?>
                                    <?php endif; ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="startTime" class="form-label"><?php echo __('appointment_date'); ?> &amp; <?php echo __('appointment_time'); ?> *</label>
                            <input type="datetime-local" 
                                   class="form-control" 
                                   id="startTime" 
                                   name="startTime" 
                                   value="<?php echo $action === 'edit' ? date('Y-m-d\TH:i', strtotime($appointment['startTime'])) : ''; ?>" 
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="endTime" class="form-label"><?php echo __('appointment_date'); ?> &amp; <?php echo __('appointment_time'); ?> *</label>
                            <input type="datetime-local" 
                                   class="form-control" 
                                   id="endTime" 
                                   name="endTime" 
                                   value="<?php echo $action === 'edit' ? date('Y-m-d\TH:i', strtotime($appointment['endTime'])) : ''; ?>" 
                                   required>
                        </div>
                        
                        <?php if ($action === 'edit'): ?>
                        <div class="col-md-6 mb-3">
                            <label for="status" class="form-label"><?php echo __('status'); ?></label>
                            <select class="form-select" id="status" name="status">
                                <option value="SCHEDULED" <?php echo ($appointment['status'] ?? '') === 'SCHEDULED' ? 'selected' : ''; ?>><?php echo __('pending'); ?></option>
                                <option value="CONFIRMED" <?php echo ($appointment['status'] ?? '') === 'CONFIRMED' ? 'selected' : ''; ?>><?php echo __('confirmed'); ?></option>
                                <option value="COMPLETED" <?php echo ($appointment['status'] ?? '') === 'COMPLETED' ? 'selected' : ''; ?>><?php echo __('completed'); ?></option>
                                <option value="CANCELED" <?php echo ($appointment['status'] ?? '') === 'CANCELED' ? 'selected' : ''; ?>><?php echo __('cancelled'); ?></option>
                            </select>
                        </div>
                        <?php endif; ?>
                        
                        <div class="col-12 mb-3">
                            <label for="reason" class="form-label"><?php echo __('reason'); ?></label>
                            <textarea class="form-control" 
                                      id="reason" 
                                      name="reason" 
                                      rows="3" 
                                      placeholder="<?php echo __('appointment_reason_placeholder'); ?>"><?php echo $action === 'edit' ? htmlspecialchars($appointment['reason'] ?? '') : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                        <a href="appointments.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x"></i> <?php echo __('cancel'); ?>
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check"></i> <?php echo $action === 'add' ? __('schedule_appointment') : __('update_appointment'); ?>
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
                        <?php echo __('appointment_details'); ?>
                    </h5>
                    <div>
                        <?php if (hasAnyRole(['ADMIN', 'DOCTOR', 'RECEPTIONIST'])): ?>
                        <a href="appointments.php?action=edit&id=<?php echo $appointment['id']; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-pencil"></i> <?php echo __('edit'); ?>
                        </a>
                        <?php endif; ?>
                        <a href="appointments.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> <?php echo __('back_to_list'); ?>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted"><?php echo __('appointment_id_label') ?? __('id'); ?></label>
                        <p class="fw-bold text-monospace"><?php echo htmlspecialchars($appointment['id']); ?></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted"><?php echo __("status"); ?></label>
                        <p><span class="<?php echo getAppointmentStatusClass($appointment['status'] ?? 'UNKNOWN'); ?>"><?php echo getAppointmentStatusText($appointment['status'] ?? 'UNKNOWN'); ?></span></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted"><?php echo __("patient"); ?></label>
                        <p class="fw-bold"><?php echo htmlspecialchars(getPatientName($appointment['patientId'], $patients)); ?></p>
                        <small class="text-muted"><?php echo __('patient_id_label'); ?>: <?php echo htmlspecialchars($appointment['patientId']); ?></small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted"><?php echo __("doctor"); ?></label>
                        <p class="fw-bold"><?php echo sprintf(__('doctor_title_name'), htmlspecialchars(getDoctorName($appointment['doctorId'], $users))); ?></p>
                        <small class="text-muted"><?php echo __('doctor_id_label') ?? __('id'); ?>: <?php echo htmlspecialchars($appointment['doctorId']); ?></small>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted"><?php echo __('start_date_time'); ?></label>
                        <p class="fw-bold"><?php echo isset($appointment['startTime']) ? formatDateVietnamese('full', strtotime($appointment['startTime'])) . ' lúc ' . date('H:i', strtotime($appointment['startTime'])) : __('not_provided'); ?></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted"><?php echo __('end_date_time'); ?></label>
                        <p class="fw-bold"><?php echo isset($appointment['endTime']) ? formatDateVietnamese('full', strtotime($appointment['endTime'])) . ' lúc ' . date('H:i', strtotime($appointment['endTime'])) : __('not_provided'); ?></p>
                    </div>
                    
                    <div class="col-12 mb-3">
                        <label class="form-label text-muted"><?php echo __('reason') ?? __('reason'); ?></label>
                        <div class="card bg-light">
                            <div class="card-body">
                                <?php if ($appointment['reason'] ?? false): ?>
                                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($appointment['reason'])); ?></p>
                                <?php else: ?>
                                    <p class="mb-0 text-muted"><?php echo __('no_reason_provided'); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <?php 
                    // Debug: Check prescribe button conditions for detail view
                    $hasPrescriberRole = hasAnyRole(['ADMIN', 'DOCTOR']);
                    $hasValidStatus = ($appointment['status'] === 'CONFIRMED' || $appointment['status'] === 'COMPLETED');
                    error_log("DEBUG Prescribe Button Detail - Appointment {$appointment['id']}: Role=$hasPrescriberRole, Status={$appointment['status']}, ValidStatus=$hasValidStatus");
                    ?>
                    <?php if ($hasPrescriberRole && $hasValidStatus): ?>
                        <a href="prescriptions.php?action=add&appointment_id=<?php echo $appointment['id']; ?>" class="btn btn-success">
                            <i class="bi bi-prescription2"></i> <?php echo __('create_prescription'); ?>
                        </a>
                    <?php endif; ?>
                    <?php if (hasAnyRole(['ADMIN', 'DOCTOR', 'RECEPTIONIST'])): ?>
                        <a href="appointments.php?action=edit&id=<?php echo $appointment['id']; ?>" class="btn btn-primary">
                        <i class="bi bi-pencil"></i> <?php echo __('edit_appointment'); ?>
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
                <h3 class="mt-3 text-muted"><?php echo __('no_appointments_found'); ?></h3>
                <p class="text-muted"><?php echo __('appointment_not_found_message'); ?></p>
                <a href="appointments.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left"></i> <?php echo __('back_to_appointments'); ?>
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
                <h5 class="modal-title"><?php echo __('delete_confirmation'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars(__('close')); ?>"></button>
            </div>
            <div class="modal-body">
                <p><?php echo __('are_you_sure'); ?> <strong id="appointmentPatient"></strong>?</p>
                <p class="text-danger"><small><?php echo __('action_cannot_undone'); ?></small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger"><?php echo __('delete_appointment'); ?></a>
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
    // Reuse lightweight searchable select (same approach as prescriptions)
    function initSearchableSelect(selectEl) {
        if (selectEl.disabled) return;
        const wrapper = document.createElement('div');
        wrapper.className = 'position-relative mb-2';
        selectEl.parentNode.insertBefore(wrapper, selectEl);
        wrapper.appendChild(selectEl);
        selectEl.style.display = 'none';

        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'form-control mb-1';
        input.placeholder = selectEl.getAttribute('data-search-placeholder') || 'Search...';
        wrapper.insertBefore(input, selectEl);

        const dropdown = document.createElement('div');
        dropdown.className = 'searchable-select-dropdown border rounded bg-white shadow-sm position-absolute w-100';
        dropdown.style.zIndex = 1000;
        dropdown.style.maxHeight = '220px';
        dropdown.style.overflowY = 'auto';
        dropdown.style.display = 'none';
        wrapper.appendChild(dropdown);

        const options = Array.from(selectEl.options)
            .filter(o => o.value)
            .map(o => ({ value: o.value, label: o.textContent.trim() }));

        function render(list) {
            dropdown.innerHTML = '';
            if (!list.length) {
                const empty = document.createElement('div');
                empty.className = 'px-2 py-1 text-muted small';
                empty.textContent = '<?php echo addslashes(__('no_results')); ?>';
                dropdown.appendChild(empty);
                return;
            }
            list.forEach(item => {
                const row = document.createElement('div');
                row.className = 'px-2 py-1 searchable-select-item';
                row.style.cursor = 'pointer';
                row.textContent = item.label;
                row.addEventListener('mousedown', e => {
                    e.preventDefault();
                    selectEl.value = item.value;
                    input.value = item.label;
                    dropdown.style.display = 'none';
                });
                dropdown.appendChild(row);
            });
        }

        function filter() {
            const q = input.value.trim().toLowerCase();
            const list = q ? options.filter(o => o.label.toLowerCase().includes(q)) : options.slice(0, 50);
            render(list.slice(0, 100));
        }

        input.addEventListener('focus', () => { dropdown.style.display = 'block'; filter(); });
        input.addEventListener('input', filter);
        input.addEventListener('blur', () => { setTimeout(() => { dropdown.style.display = 'none'; }, 120); });

        if (selectEl.value) {
            const found = options.find(o => o.value === selectEl.value);
            if (found) input.value = found.label;
        }
    }

    document.querySelectorAll('select.js-searchable-select').forEach(initSearchableSelect);
    const firstInput = document.querySelector('form input:not([type="hidden"]), form select');
    if (firstInput) {
        firstInput.focus();
    }
    
    // Enhanced status transition logic for Admin rollback
    const statusSelect = document.getElementById('status');
    if (statusSelect) {
        const currentStatus = statusSelect.value;
        const userRole = '<?php echo $user['role'] ?? ''; ?>';
        
        // Define available transitions
        const transitions = {
            'SCHEDULED': ['CONFIRMED', 'CANCELED'],
            'CONFIRMED': ['COMPLETED', 'CANCELED'],
            'COMPLETED': [],
            'CANCELED': []
        };
        
        // Define admin rollback transitions
        const adminRollback = {
            'CONFIRMED': ['SCHEDULED'],
            'COMPLETED': ['CONFIRMED']
        };
        
        function updateStatusOptions() {
            const current = statusSelect.value;
            const allOptions = statusSelect.querySelectorAll('option');
            
            allOptions.forEach(option => {
                const targetStatus = option.value;
                let isAllowed = false;
                
                // Always allow current status
                if (targetStatus === current) {
                    isAllowed = true;
                }
                // Check standard transitions
                else if (transitions[current] && transitions[current].includes(targetStatus)) {
                    isAllowed = true;
                }
                // Check admin rollback
                else if (userRole === 'ADMIN' && adminRollback[current] && adminRollback[current].includes(targetStatus)) {
                    isAllowed = true;
                    option.style.color = '#dc3545'; // Red color for rollback options
                    option.title = 'Admin rollback - use with caution';
                }
                
                option.disabled = !isAllowed;
                option.style.display = isAllowed ? '' : 'none';
            });
        }
        
        // Initial setup
        updateStatusOptions();
        
        // Update when status changes
        statusSelect.addEventListener('change', updateStatusOptions);
    }
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
