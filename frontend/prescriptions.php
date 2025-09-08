<?php
require_once 'includes/config.php';
require_once 'includes/language.php';
requireAnyRole(['ADMIN', 'DOCTOR', 'NURSE']);

$pageTitle = __('prescription_management');
$user = getCurrentUser();
$action = $_GET['action'] ?? 'list';

// Get preselected appointment ID from URL (when coming from appointments page)
$preselectedAppointmentId = $_GET['appointment_id'] ?? '';
$preselectedAppointment = null;

$prescriptions = [];
$patients = [];
$users = [];
$appointments = [];
$prescription = null;
$error = '';
$success = '';
$pagination = '';

// Helper functions
function getPatientName($patientId, $patients)
{
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
                if (!empty($fullName))
                    return $fullName;
            }
            // Fallback to Patient ID if no name found
        return sprintf(__('patient_fallback_id'), substr($patientId, 0, 8));
        }
    }
    return __('unknown_patient');
}

function formatLocalizedDate($dateString) {
    if (!$dateString) return __('not_provided');
    
    $timestamp = strtotime($dateString);
    if (!$timestamp) return __('not_provided');
    
    // Get current language
    $lang = $_SESSION['language'] ?? 'vi';
    
    if ($lang === 'vi') {
        // Vietnamese format: DD/MM/YYYY HH:MM
        return date('d/m/Y H:i', $timestamp);
    } else {
        // English format: Mon DD, YYYY HH:MM
        return date('M j, Y H:i', $timestamp);
    }
}

function formatLocalizedDayName($dateString) {
    if (!$dateString) return '';
    
    $timestamp = strtotime($dateString);
    if (!$timestamp) return '';
    
    // Get current language
    $lang = $_SESSION['language'] ?? 'vi';
    
    if ($lang === 'vi') {
        $days = [
            'Sunday' => 'Chủ nhật',
            'Monday' => 'Thứ hai', 
            'Tuesday' => 'Thứ ba',
            'Wednesday' => 'Thứ tư',
            'Thursday' => 'Thứ năm',
            'Friday' => 'Thứ sáu',
            'Saturday' => 'Thứ bảy'
        ];
        $englishDay = date('l', $timestamp);
        return $days[$englishDay] ?? $englishDay;
    } else {
        return date('l', $timestamp);
    }
}

function getDoctorName($doctorId, $users)
{
    if (!is_array($users)) {
        return __('unknown_doctor');
    }
    
    // Quick optimization: if there's only one user (current user), check if it matches
    if (count($users) === 1 && isset($users[0]['id']) && $users[0]['id'] === $doctorId) {
        $user = $users[0];
        if (!empty($user['fullName'])) {
            return $user['fullName'];
        }
    }
    
    // Fallback to original logic for multiple users or no match
    foreach ($users as $user) {
        if (is_array($user) && isset($user['id']) && $user['id'] == $doctorId) {
            // Check fullName first (this is the correct field from user service)
            if (!empty($user['fullName'])) {
                return $user['fullName'];
            } elseif (!empty($user['name'])) {
                return $user['name'];
            } elseif (!empty($user['firstName']) || !empty($user['lastName'])) {
                $firstName = $user['firstName'] ?? '';
                $lastName = $user['lastName'] ?? '';
                $fullName = trim($firstName . ' ' . $lastName);
                if (!empty($fullName))
                    return $fullName;
            } elseif (!empty($user['email'])) {
                // Use email without domain as fallback
                return explode('@', $user['email'])[0];
            }
            // Fallback to Doctor ID if no name found
            return sprintf(__('doctor_fallback_id'), substr($doctorId, 0, 8));
        }
    }
    return __('unknown_doctor');
}

function formatDoctorTitle($doctorName) {
    // If name already contains "Bác sĩ" or "Dr.", don't add prefix
    if (strpos($doctorName, 'Bác sĩ') !== false || strpos($doctorName, 'Dr.') !== false) {
        return $doctorName;
    }
    // Otherwise, use the translation template
    return sprintf(__('doctor_title_name'), $doctorName);
}


// Get search and filter parameters
$search = $_GET['search'] ?? '';
$page = (int) ($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = __('invalid_csrf_token');
    } else {
        $token = $_SESSION['token'];

        if ($action === 'add') {
            // Parse medication items from form
            $items = [];
            if (isset($_POST['drugName']) && is_array($_POST['drugName'])) {
                for ($i = 0; $i < count($_POST['drugName']); $i++) {
                    if (!empty($_POST['drugName'][$i])) {
                        $items[] = [
                            'drugName' => sanitize($_POST['drugName'][$i]),
                            'dosage' => sanitize($_POST['dosage'][$i]),
                            'frequency' => sanitize($_POST['frequency'][$i]),
                            'durationDays' => (int) $_POST['durationDays'][$i],
                            'instruction' => sanitize($_POST['instruction'][$i] ?? '')
                        ];
                    }
                }
            }

            $prescriptionData = [
                'patientId' => sanitize($_POST['patientId']),
                'doctorId' => sanitize($_POST['doctorId']),
                'appointmentId' => !empty($_POST['appointmentId']) ? sanitize($_POST['appointmentId']) : null,
                'note' => sanitize($_POST['note'] ?? ''),
                'items' => $items
            ];

            if (!empty($prescriptionData['patientId']) && !empty($prescriptionData['doctorId']) && !empty($items)) {
                $response = makeApiCall(PRESCRIPTION_SERVICE_URL, 'POST', $prescriptionData, $token);

                if ($response['status_code'] === 201) {
                    $success = __('prescription_created_success');
                    $action = 'list';
                } else {
                    $error = handleApiError($response) ?: __('failed_to_create_prescription');
                }
            } else {
                $error = __('patient_doctor_medication_required');
            }
        } elseif ($action === 'edit') {
            // Parse medication items from form - same logic as add
            $items = [];
            if (isset($_POST['drugName']) && is_array($_POST['drugName'])) {
                for ($i = 0; $i < count($_POST['drugName']); $i++) {
                    if (!empty($_POST['drugName'][$i])) {
                        $items[] = [
                            'drugName' => sanitize($_POST['drugName'][$i]),
                            'dosage' => sanitize($_POST['dosage'][$i]),
                            'frequency' => sanitize($_POST['frequency'][$i]),
                            'durationDays' => (int) $_POST['durationDays'][$i],
                            'instruction' => sanitize($_POST['instruction'][$i] ?? '')
                        ];
                    }
                }
            }

            $prescriptionData = [
                'patientId' => sanitize($_POST['patientId']),
                'doctorId' => sanitize($_POST['doctorId']),
                'appointmentId' => !empty($_POST['appointmentId']) ? sanitize($_POST['appointmentId']) : null,
                'note' => sanitize($_POST['note'] ?? ''),
                'items' => $items
            ];

            $prescriptionId = $_GET['id'] ?? '';

            if (!empty($prescriptionData['patientId']) && !empty($prescriptionData['doctorId']) && !empty($items) && !empty($prescriptionId)) {
                $response = makeApiCall(PRESCRIPTION_SERVICE_URL . '/' . $prescriptionId, 'PUT', $prescriptionData, $token);

                if ($response['status_code'] === 200) {
                    $success = __('prescription_updated_success');
                    $action = 'view';
                } else {
                    $error = handleApiError($response) ?: __('failed_to_update_prescription');
                }
            } else {
                $error = __('patient_doctor_medication_required');
            }
        } elseif ($action === 'update_status' && isset($_POST['prescriptionId'])) {
            // Update prescription status
            $prescriptionId = $_POST['prescriptionId'];
            $newStatus = $_POST['status'];

            $response = makeApiCall(
                PRESCRIPTION_SERVICE_URL . '/' . $prescriptionId . '/status',
                'PATCH',
                ['status' => $newStatus],
                $token
            );

                if ($response['status_code'] === 200) {
                    $success = __('prescription_updated_success');
                    $action = 'list';
            } else {
                $error = handleApiError($response) ?: __('prescription_update_failed');
            }
        }
    }
}

// Handle delete action
if ($action === 'delete' && isset($_GET['id'])) {
    $token = $_SESSION['token'];
    $prescriptionId = $_GET['id'];

    $response = makeApiCall(PRESCRIPTION_SERVICE_URL . '/' . $prescriptionId, 'DELETE', null, $token);

    if ($response['status_code'] === 200 || $response['status_code'] === 204) {
        $success = __('prescription_deleted_success');
    } else {
    $error = handleApiError($response) ?: __('failed_to_delete_prescription');
    }
    $action = 'list';
}

// Fetch data based on action
try {
    $token = $_SESSION['token'];

    if ($action === 'list') {
        // Build query parameters for prescriptions
        $queryParams = [
            'page' => $page,
            'limit' => $limit
        ];
        if ($search) {
            $queryParams['search'] = $search;
        }
        $queryString = http_build_query($queryParams);

        $response = makeApiCall(PRESCRIPTION_SERVICE_URL . '?' . $queryString, 'GET', null, $token);

        if ($response['status_code'] === 200) {
            // Handle paginated response
            if (isset($response['data']['prescriptions']) && isset($response['data']['total'])) {
                $prescriptions = $response['data']['prescriptions'];
                $totalPrescriptions = $response['data']['total'];
            } else {
                // Fallback for non-paginated response
                $prescriptions = is_array($response['data']) ? $response['data'] : [];

                // Apply client-side search if API doesn't support it
                if ($search) {
                    $prescriptions = array_filter($prescriptions, function ($pres) use ($search) {
                        $searchLower = strtolower($search);
                        return stripos($pres['note'] ?? '', $search) !== false ||
                            stripos($pres['patientId'] ?? '', $search) !== false ||
                            stripos($pres['doctorId'] ?? '', $search) !== false ||
                            stripos($pres['status'] ?? '', $search) !== false;
                    });
                }

                $totalPrescriptions = count($prescriptions);
                $prescriptions = array_slice($prescriptions, $offset, $limit);
            }

            $totalPages = ceil($totalPrescriptions / $limit);

            // Generate pagination
            if ($totalPages > 1) {
                $baseUrl = 'prescriptions.php?';
                if ($search)
                    $baseUrl .= 'search=' . urlencode($search) . '&';
                $pagination = paginate($page, $totalPages, $baseUrl);
            }
            
            // Load doctor user list for mapping doctor names:
            // - If role is DOCTOR: only need current user
            // - If role is NURSE or ADMIN: need full doctor list (or all users) to resolve names
            if (hasRole('DOCTOR')) {
                $currentUserResponse = makeApiCall(USER_SERVICE_URL . '/me', 'GET', null, $token);
                if ($currentUserResponse['status_code'] === 200) {
                    $currentUser = $currentUserResponse['data'];
                    $users = [$currentUser];
                } else {
                    $users = [];
                }
            } else {
                // Nurse / Admin
                $doctorsResponse = makeApiCall(USER_SERVICE_URL . '/doctors', 'GET', null, $token);
                if ($doctorsResponse['status_code'] === 200 && is_array($doctorsResponse['data'])) {
                    $users = $doctorsResponse['data'];
                } else {
                    // Fallback: fetch all users
                    $allUsersResponse = makeApiCall(USER_SERVICE_URL, 'GET', null, $token);
                    $users = ($allUsersResponse['status_code'] === 200 && is_array($allUsersResponse['data'])) ? $allUsersResponse['data'] : [];
                }
            }
            
            // Load patients for patient names in list view
            $patientsResponse = makeApiCall(PATIENT_SERVICE_URL, 'GET', null, $token);
            if ($patientsResponse['status_code'] === 200) {
                $patients = isset($patientsResponse['data']['patients']) ?
                    $patientsResponse['data']['patients'] :
                    (is_array($patientsResponse['data']) ? $patientsResponse['data'] : []);
            }
        } else {
            $error = handleApiError($response) ?: __('failed_to_load');
            // Debug: Add more error details
            if (isset($_GET['debug'])) {
                $error .= ' (' . sprintf(__('status_with_code'), $response['status_code']) . ', URL: ' . PRESCRIPTION_SERVICE_URL . ')';
            }
        }
    } elseif (($action === 'edit' || $action === 'view') && isset($_GET['id'])) {
        $prescriptionId = $_GET['id'];
        $response = makeApiCall(PRESCRIPTION_SERVICE_URL . '/' . $prescriptionId, 'GET', null, $token);
        if ($response['status_code'] === 200) {
            $prescription = $response['data'];
            
            // Load users and patients for view/edit mode
            $patientsResponse = makeApiCall(PATIENT_SERVICE_URL, 'GET', null, $token);
            if ($patientsResponse['status_code'] === 200) {
                $patients = isset($patientsResponse['data']['patients']) ?
                    $patientsResponse['data']['patients'] :
                    (is_array($patientsResponse['data']) ? $patientsResponse['data'] : []);
            }

            // Load current user info for view/edit mode
            $currentUserResponse = makeApiCall(USER_SERVICE_URL . '/me', 'GET', null, $token);
            if ($currentUserResponse['status_code'] === 200) {
                $currentUser = $currentUserResponse['data'];
                $users = [$currentUser]; // Create array with single user for consistency
            } else {
                $users = [];
            }
        } else {
            $error = handleApiError($response) ?: __('prescription_not_found');
        }
    }

    // Get patients, users, and appointments for dropdowns (additional data for add/edit)
    if ($action === 'add' || $action === 'edit') {
        // Always ensure patients loaded (previous logic failed because $patients pre-declared as [])
        if (empty($patients)) {
            $patientsResponse = makeApiCall(PATIENT_SERVICE_URL, 'GET', null, $token);
            if ($patientsResponse['status_code'] === 200) {
                $patients = isset($patientsResponse['data']['patients']) ?
                    $patientsResponse['data']['patients'] :
                    (is_array($patientsResponse['data']) ? $patientsResponse['data'] : []);
            }
        }

        if (empty($users)) {
            // For add/edit mode, load users based on role
            if (hasRole('ADMIN')) {
                $usersResponse = makeApiCall(USER_SERVICE_URL . '/doctors', 'GET', null, $token);
                if ($usersResponse['status_code'] === 200) {
                    $users = is_array($usersResponse['data']) ? $usersResponse['data'] : [];
                }
            } else {
                $currentUserResponse = makeApiCall(USER_SERVICE_URL . '/me', 'GET', null, $token);
                if ($currentUserResponse['status_code'] === 200) {
                    $currentUser = $currentUserResponse['data'];
                    $users = [$currentUser];
                }
            }
        }

        $appointmentsResponse = makeApiCall(APPOINTMENT_SERVICE_URL, 'GET', null, $token);
        if ($appointmentsResponse['status_code'] === 200) {
            $appointments = isset($appointmentsResponse['data']['appointments']) ?
                $appointmentsResponse['data']['appointments'] :
                (is_array($appointmentsResponse['data']) ? $appointmentsResponse['data'] : []);
        }
        
        // If we have a preselected appointment ID, fetch its details
        if (!empty($preselectedAppointmentId)) {
            foreach ($appointments as $appointment) {
                if ($appointment['id'] === $preselectedAppointmentId) {
                    $preselectedAppointment = $appointment;
                    break;
                }
            }
        }
    }

    // For list view, get basic patient and user data for display
    if (($action === 'list' && !empty($prescriptions)) || ($action === 'view' && isset($prescription))) {
        $patientsResponse = makeApiCall(PATIENT_SERVICE_URL, 'GET', null, $token);
        if ($patientsResponse['status_code'] === 200) {
            $patients = isset($patientsResponse['data']['patients']) ?
                $patientsResponse['data']['patients'] :
                (is_array($patientsResponse['data']) ? $patientsResponse['data'] : []);
        }

        if (hasRole('DOCTOR')) {
            $currentUserResponse = makeApiCall(USER_SERVICE_URL . '/me', 'GET', null, $token);
            if ($currentUserResponse['status_code'] === 200) {
                $users = [$currentUserResponse['data']];
            }
        } else {
            $doctorsResponse = makeApiCall(USER_SERVICE_URL . '/doctors', 'GET', null, $token);
            if ($doctorsResponse['status_code'] === 200 && is_array($doctorsResponse['data'])) {
                $users = $doctorsResponse['data'];
            } else {
                $usersResponse = makeApiCall(USER_SERVICE_URL, 'GET', null, $token);
                if ($usersResponse['status_code'] === 200) {
                    $users = is_array($usersResponse['data']) ? $usersResponse['data'] : [];
                }
            }
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
            <i class="bi bi-prescription2"></i>
            <?php echo __('prescription_management'); ?>
        </h1>
        <p class="text-muted mb-0"><?php echo __('prescription_management_description'); ?></p>
    </div>

    <?php if ($action === 'list' && hasAnyRole(['ADMIN', 'DOCTOR'])): ?>
        <div>
            <a href="prescriptions.php?action=add" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i>
                <?php echo __('add_prescription'); ?>
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
    <!-- Prescriptions List -->
    <div class="card">
        <div class="card-header">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <h5 class="mb-0">
                        <i class="bi bi-list"></i>
                        <?php echo __('prescriptions_list'); ?>
                        <?php if (isset($totalPrescriptions) && $totalPrescriptions > 0): ?>
                            <span class="badge bg-primary ms-2"><?php echo $totalPrescriptions; ?></span>
                        <?php endif; ?>
                    </h5>
                </div>
                <div class="col-md-6">
                    <!-- Search Form -->
                    <form method="GET" class="d-flex">
                        <input type="text" class="form-control form-control-sm me-2" name="search"
                            placeholder="<?php echo __('search_prescriptions'); ?>" value="<?php echo htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-search"></i>
                        </button>
                        <?php if ($search): ?>
                            <a href="prescriptions.php" class="btn btn-outline-secondary btn-sm ms-1">
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
                                <i class="bi bi-flag me-1"></i><?php echo __('status'); ?>
                            </th>
                            <th class="border-0">
                                <i class="bi bi-calendar-event me-1"></i><?php echo __('created'); ?>
                            </th>
                            <th class="border-0">
                                <i class="bi bi-capsule me-1"></i><?php echo __('medications'); ?>
                            </th>
                            <th class="border-0 text-center">
                                <i class="bi bi-gear me-1"></i><?php echo __('actions'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($prescriptions)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="bi bi-prescription2 text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2 mb-0"><?php echo __('no_prescriptions_found'); ?></p>
                                    <?php if ($search): ?>
                                        <small class="text-muted"><?php echo __('try_adjust_search'); ?></small>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($prescriptions as $pres): ?>
                                <?php
                                // Get patient and doctor names
                                $patientName = getPatientName($pres['patientId'], $patients);
                                $doctorName = getDoctorName($pres['doctorId'], $users);

                                // Format date
                                $createdDate = formatLocalizedDate($pres['createdAt']);
                                $dayName = formatLocalizedDayName($pres['createdAt']);

                                // Status styling
                                $statusClass = getPrescriptionStatusClass($pres['status'] ?? 'UNKNOWN');
                                $statusText = getPrescriptionStatusText($pres['status'] ?? 'UNKNOWN');

                                // Count medications
                                $medicationCount = isset($pres['itemsCount']) ? (int)$pres['itemsCount'] : 0;
 ?>
                                <tr>
                                    <td class="align-middle">
                                        <span class="text-monospace small"><?php echo htmlspecialchars(substr($pres['id'] ?? __('not_provided'), 0, 8)); ?>...</span>
                                    </td>
                                    <td class="align-middle">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2"
                                                style="width: 32px; height: 32px; font-size: 0.75rem;">
                                                <?php echo strtoupper(substr($patientName, 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($patientName); ?></div>
                                                <small class="text-muted"><?php echo __('patient_id_label'); ?>: <?php echo htmlspecialchars(substr($pres['patientId'] ?? __('not_provided'), 0, 8)); ?>...</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="align-middle">
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-2"
                                                style="width: 32px; height: 32px; font-size: 0.75rem;">
                                                <?php echo strtoupper(substr($doctorName, 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars(formatDoctorTitle($doctorName)); ?>
                                                </div>
                                                <small class="text-muted"><?php echo __('doctor_id_label'); ?>: <?php echo htmlspecialchars(substr($pres['doctorId'] ?? __('not_provided'), 0, 8)); ?>...</small>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="align-middle">
                                        <span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                    </td>
                                    <td class="align-middle">
                                        <div class="fw-bold text-dark"><?php echo $createdDate; ?></div>
                                        <small class="text-muted"><?php echo $dayName; ?></small>
                                    </td>
                                    <td class="align-middle">
                                            <span class="badge bg-light text-dark">
                                            <i class="bi bi-capsule me-1"></i><?php echo $medicationCount; ?>
                                            <?php echo $medicationCount !== 1 ? __('items') : __('item'); ?>
                                        </span>
                                    </td>
                                    <td class="align-middle text-center">
                                        <div class="btn-group btn-group-sm">
                                            <a href="prescriptions.php?action=view&id=<?php echo $pres['id']; ?>"
                                                class="btn btn-outline-primary btn-sm" title="<?php echo __('view_details'); ?>">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <?php if (hasAnyRole(['ADMIN', 'DOCTOR']) && ($pres['status'] ?? '') !== 'DISPENSED' && ($pres['status'] ?? '') !== 'COMPLETED'): ?>
                                                <a href="prescriptions.php?action=edit&id=<?php echo $pres['id']; ?>"
                                                    class="btn btn-outline-warning btn-sm" title="<?php echo __('edit_prescription'); ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                            <?php endif; ?>
                                            <?php 
                                            // Show status change button:
                                            // ADMIN: any transition except already COMPLETED/CANCELED
                                            // DOCTOR: can change while not DISPENSED/COMPLETED and target not DISPENSED
                                            // NURSE: only if current status ISSUED or PENDING (to mark DISPENSED)
                                            $currentStatus = $pres['status'] ?? ''; 
                                            $showStatusBtn = false; 
                                            if (hasRole('ADMIN') && !in_array($currentStatus, ['COMPLETED','CANCELED'])) {
                                                $showStatusBtn = true; 
                                            } elseif (hasRole('DOCTOR') && !in_array($currentStatus, ['DISPENSED','COMPLETED','CANCELED'])) {
                                                $showStatusBtn = true; 
                                            } elseif (hasRole('NURSE') && in_array($currentStatus, ['ISSUED','PENDING'])) {
                                                $showStatusBtn = true; 
                                            }
                                            if ($showStatusBtn): ?>
                                                <button type="button" class="btn btn-outline-info btn-sm" data-bs-toggle="modal" data-bs-target="#statusModal" 
                                                    data-prescription-id="<?php echo $pres['id']; ?>" data-current-status="<?php echo $currentStatus; ?>" title="<?php echo __('update_status'); ?>">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if (hasRole('ADMIN')): ?>
                                                <button type="button" class="btn btn-outline-danger btn-sm"
                                                    onclick="confirmDelete('<?php echo $pres['id']; ?>', '<?php echo addslashes($patientName); ?>')"
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
                    <?php echo sprintf(__('showing_prescriptions_range'), (($page - 1) * $limit + 1), min($page * $limit, $totalPrescriptions ?? 0), $totalPrescriptions ?? 0); ?>
                </div>
                <nav>
                    <?php echo $pagination; ?>
                </nav>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($action === 'add'): ?>
    <!-- Add Prescription Form -->
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-header">
                        <h5 class="mb-0">
                        <i class="bi bi-plus-circle"></i>
                        <?php echo __('new_prescription'); ?>
                    </h5>
                </div>

<?php elseif ($action === 'edit' && $prescription): ?>
    <!-- Edit Prescription Form -->
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-pencil"></i>
                        <?php echo __('edit_prescription'); ?>
                    </h5>
                </div>

<?php endif; ?>

<!-- Shared Form for Add/Edit Prescription -->
<?php if ($action === 'add' || ($action === 'edit' && $prescription)): ?>
                <div class="card-body">
                    <form method="POST" id="prescriptionForm">
                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="patientId" class="form-label"><?php echo __('patient'); ?> *</label>
                                <select class="form-select js-searchable-select" data-search-placeholder="<?php echo __('search_patients'); ?>" id="patientId" name="patientId" required <?php echo ($preselectedAppointment || $action === 'edit') ? 'disabled' : ''; ?>>
                                    <option value=""><?php echo __('select_patient'); ?></option>
                                    <?php 
                                    $selectedPatientId = $preselectedAppointment ? $preselectedAppointment['patientId'] : 
                                                       ($action === 'edit' && $prescription ? $prescription['patientId'] : 
                                                       ($_GET['patient_id'] ?? ''));
                                    foreach ($patients as $patient): ?>
                                        <option value="<?php echo $patient['id']; ?>" <?php echo ($patient['id'] == $selectedPatientId) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($patient['fullName'] ?? __('unknown')); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($preselectedAppointment || $action === 'edit'): ?>
                                    <input type="hidden" name="patientId" value="<?php echo htmlspecialchars($selectedPatientId); ?>">
                                    <small class="text-muted"><?php echo $action === 'edit' ? __('patient_cannot_be_changed') : __('patient_selected_from_appointment'); ?></small>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="doctorId" class="form-label"><?php echo __('doctor'); ?> *</label>
                                <select class="form-select js-searchable-select" data-search-placeholder="<?php echo __('search_doctors'); ?>" id="doctorId" name="doctorId" required <?php echo ($preselectedAppointment && $user['role'] === 'DOCTOR') || $action === 'edit' ? 'disabled' : ''; ?>>
                                    <option value=""><?php echo __('select_doctor'); ?></option>
                                    <?php 
                                    $selectedDoctorId = '';
                                    if ($action === 'edit' && $prescription) {
                                        $selectedDoctorId = $prescription['doctorId'];
                                    } elseif ($preselectedAppointment) {
                                        $selectedDoctorId = $preselectedAppointment['doctorId'];
                                    } elseif ($user['role'] === 'DOCTOR') {
                                        $selectedDoctorId = $user['id'];
                                    }
                                    
                                    foreach ($users as $doctor): ?>
                                            <option value="<?php echo $doctor['id']; ?>" <?php echo ($doctor['id'] == $selectedDoctorId) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars(formatDoctorTitle($doctor['fullName'] ?? $doctor['email'] ?? __('unknown'))); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (($preselectedAppointment && $user['role'] === 'DOCTOR') || $action === 'edit'): ?>
                                    <input type="hidden" name="doctorId" value="<?php echo htmlspecialchars($selectedDoctorId); ?>">
                                    <small class="text-muted"><?php echo $action === 'edit' ? __('doctor_cannot_be_changed') : __('doctor_selected_from_appointment'); ?></small>
                                <?php endif; ?>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label for="appointmentId" class="form-label"><?php echo __('related_appointment'); ?></label>
                                <select class="form-select" id="appointmentId" name="appointmentId" <?php echo ($preselectedAppointment || $action === 'edit') ? 'disabled' : ''; ?>>
                                    <option value=""><?php echo __('select_appointment_optional'); ?></option>
                                    <?php 
                                    $selectedAppointmentId = $preselectedAppointment ? $preselectedAppointment['id'] : 
                                                           ($action === 'edit' && $prescription ? $prescription['appointmentId'] : '');
                                    foreach ($appointments as $appointment): ?>
                                        <option value="<?php echo $appointment['id']; ?>" <?php echo ($appointment['id'] === $selectedAppointmentId) ? 'selected' : ''; ?>>
                                            <?php
                                            $aptPatientName = getPatientName($appointment['patientId'], $patients);
                                            $aptDate = isset($appointment['startTime']) ? date('M j, Y H:i', strtotime($appointment['startTime'])) : __('not_provided');
                                            echo htmlspecialchars($aptPatientName . ' - ' . $aptDate);
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($preselectedAppointment || $action === 'edit'): ?>
                                    <input type="hidden" name="appointmentId" value="<?php echo htmlspecialchars($selectedAppointmentId); ?>">
                                    <small class="text-muted"><?php echo $action === 'edit' ? __('appointment_cannot_be_changed') : __('appointment_selected_automatically'); ?></small>
                                <?php endif; ?>
                            </div>

                            <div class="col-12 mb-4">
                                <label class="form-label"><?php echo __('medications'); ?> *</label>
                                <div id="medicationsContainer">
                                    <?php if ($action === 'edit' && $prescription && isset($prescription['items'])): ?>
                                        <?php foreach ($prescription['items'] as $index => $item): ?>
                                            <div class="medication-item border rounded p-3 mb-3">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <h6 class="mb-0"><?php echo sprintf(__('medication_item_title'), $index + 1); ?></h6>
                                                    <button type="button" class="btn btn-outline-danger btn-sm remove-medication"
                                                        style="<?php echo $index === 0 ? 'display: none;' : ''; ?>">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                                <div class="row">
                                                    <div class="col-md-3 mb-2">
                                                        <label class="form-label"><?php echo __('medication_name'); ?></label>
                                                        <input type="text" class="form-control" name="drugName[]"
                                                            placeholder="<?php echo __('medication_name'); ?>" value="<?php echo htmlspecialchars($item['drugName'] ?? ''); ?>" required>
                                                    </div>
                                                    <div class="col-md-2 mb-2">
                                                        <label class="form-label"><?php echo __('dosage'); ?></label>
                                                        <input type="text" class="form-control" name="dosage[]"
                                                            placeholder="<?php echo __('dosage_example'); ?>" value="<?php echo htmlspecialchars($item['dosage'] ?? ''); ?>" required>
                                                    </div>
                                                    <div class="col-md-2 mb-2">
                                                        <label class="form-label"><?php echo __('frequency'); ?></label>
                                                        <input type="text" class="form-control" name="frequency[]"
                                                            placeholder="<?php echo __('frequency_example'); ?>" value="<?php echo htmlspecialchars($item['frequency'] ?? ''); ?>" required>
                                                    </div>
                                                    <div class="col-md-2 mb-2">
                                                        <label class="form-label"><?php echo __('duration'); ?> (<?php echo __('days'); ?>)</label>
                                                        <input type="number" class="form-control" name="durationDays[]" min="1"
                                                            value="<?php echo htmlspecialchars($item['durationDays'] ?? ''); ?>" required>
                                                    </div>
                                                    <div class="col-md-3 mb-2">
                                                        <label class="form-label"><?php echo __('instructions'); ?></label>
                                                        <input type="text" class="form-control" name="instruction[]"
                                                            placeholder="<?php echo __('instruction_example'); ?>" value="<?php echo htmlspecialchars($item['instruction'] ?? ''); ?>">
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="medication-item border rounded p-3 mb-3">
                                            <div class="d-flex justify-content-between align-items-center mb-3">
                                                <h6 class="mb-0"><?php echo sprintf(__('medication_item_title'), 1); ?></h6>
                                                <button type="button" class="btn btn-outline-danger btn-sm remove-medication"
                                                    style="display: none;">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                            <div class="row">
                                                <div class="col-md-3 mb-2">
                                                    <label class="form-label"><?php echo __('medication_name'); ?></label>
                                                    <input type="text" class="form-control" name="drugName[]"
                                                        placeholder="<?php echo __('medication_name'); ?>" required>
                                                </div>
                                                <div class="col-md-2 mb-2">
                                                    <label class="form-label"><?php echo __('dosage'); ?></label>
                                                    <input type="text" class="form-control" name="dosage[]"
                                                        placeholder="<?php echo __('dosage_example'); ?>" required>
                                                </div>
                                                <div class="col-md-2 mb-2">
                                                    <label class="form-label"><?php echo __('frequency'); ?></label>
                                                    <input type="text" class="form-control" name="frequency[]"
                                                        placeholder="<?php echo __('frequency_example'); ?>" required>
                                                </div>
                                                <div class="col-md-2 mb-2">
                                                    <label class="form-label"><?php echo __('duration'); ?> (<?php echo __('days'); ?>)</label>
                                                    <input type="number" class="form-control" name="durationDays[]" min="1"
                                                        required>
                                                </div>
                                                <div class="col-md-3 mb-2">
                                                    <label class="form-label"><?php echo __('instructions'); ?></label>
                                                    <input type="text" class="form-control" name="instruction[]"
                                                        placeholder="<?php echo __('instruction_example'); ?>">
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <button type="button" class="btn btn-outline-primary" id="addMedication">
                                    <i class="bi bi-plus"></i> <?php echo __('add_another_medication'); ?>
                                </button>
                            </div>

                            <div class="col-12 mb-3">
                                <label for="note" class="form-label"><?php echo __('prescription_notes'); ?></label>
                                <textarea class="form-control" id="note" name="note" rows="3"
                                    placeholder="<?php echo __('prescription_notes_placeholder'); ?>"><?php echo ($action === 'edit' && $prescription) ? htmlspecialchars($prescription['note'] ?? '') : ''; ?></textarea>
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2">
                            <a href="prescriptions.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x"></i> <?php echo __('cancel'); ?>
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check"></i> <?php echo $action === 'add' ? __('create_prescription') : __('update_prescription'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($action === 'view' && $prescription): ?>
    <!-- View Prescription Details -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-prescription2"></i>
                            <?php echo __('prescription_details'); ?>
                        </h5>
                        <div>
                            <?php if (hasAnyRole(['ADMIN', 'DOCTOR']) && ($prescription['status'] ?? '') !== 'DISPENSED' && ($prescription['status'] ?? '') !== 'COMPLETED'): ?>
                                <a href="prescriptions.php?action=edit&id=<?php echo $prescription['id']; ?>" class="btn btn-warning btn-sm">
                                    <i class="bi bi-pencil"></i> <?php echo __('edit_prescription'); ?>
                                </a>
                            <?php endif; ?>
                            <a href="prescriptions.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-left"></i> <?php echo __('back_to_list'); ?>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted"><?php echo __('prescription_id'); ?></label>
                            <p class="fw-bold text-monospace"><?php echo htmlspecialchars($prescription['id']); ?></p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted"><?php echo __('status'); ?></label>
                            <p><span
                                    class="<?php echo getPrescriptionStatusClass($prescription['status'] ?? 'UNKNOWN'); ?>"><?php echo getPrescriptionStatusText($prescription['status'] ?? 'UNKNOWN'); ?></span>
                            </p>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted"><?php echo __('patient'); ?></label>
                            <p class="fw-bold">
                                <?php echo htmlspecialchars(getPatientName($prescription['patientId'], $patients)); ?></p>
                            <small class="text-muted"><?php echo __('patient_id_label'); ?>: <?php echo htmlspecialchars($prescription['patientId']); ?></small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted"><?php echo __('doctor'); ?></label>
                            <p class="fw-bold"><?php echo htmlspecialchars(formatDoctorTitle(getDoctorName($prescription['doctorId'], $users))); ?></p>
                            <small class="text-muted"><?php echo __('doctor_id_label') ?? __('id'); ?>: <?php echo htmlspecialchars($prescription['doctorId']); ?></small>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted"><?php echo __('created_date_label'); ?></label>
                            <p class="fw-bold">
                                <?php echo formatLocalizedDate($prescription['createdAt']); ?>
                            </p>
                            <small class="text-muted"><?php echo formatLocalizedDayName($prescription['createdAt']); ?></small>
                        </div>

                        <?php if (!empty($prescription['dispensedAt'])): ?>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-muted"><?php echo __('dispensed'); ?> <?php echo __('by') ?? 'by'; ?></label>
                            <p class="fw-bold">
                                <?php echo htmlspecialchars($prescription['dispensedBy'] ?? ''); ?>
                                <small class="text-muted d-block mt-1"><?php echo formatLocalizedDate($prescription['dispensedAt']); ?> (<?php echo formatLocalizedDayName($prescription['dispensedAt']); ?>)</small>
                            </p>
                        </div>
                        <?php endif; ?>

                        <?php if ($prescription['appointmentId'] ?? false): ?>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted"><?php echo __('related_appointment'); ?></label>
                                <p class="fw-bold"><?php echo htmlspecialchars($prescription['appointmentId']); ?></p>
                            </div>
                        <?php endif; ?>

                        <div class="col-12 mb-4">
                            <label class="form-label text-muted"><?php echo __('prescribed_medications'); ?></label>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <?php if (!empty($prescription['items'])): ?>
                                        <?php foreach ($prescription['items'] as $index => $item): ?>
                                            <div
                                                class="d-flex justify-content-between align-items-start mb-3 <?php echo $index > 0 ? 'border-top pt-3' : ''; ?>">
                                                <div>
                                                    <h6 class="mb-1">
                                                        <?php echo htmlspecialchars($item['drugName'] ?? __('unknown_drug')); ?></h6>
                                                    <p class="text-muted mb-1">
                                                        <strong><?php echo __('dosage_label'); ?>:</strong>
                                                        <?php echo htmlspecialchars($item['dosage'] ?? __('not_provided')); ?> |
                                                        <strong><?php echo __('frequency_label'); ?>:</strong>
                                                        <?php echo htmlspecialchars($item['frequency'] ?? __('not_provided')); ?> |
                                                        <strong><?php echo __('duration_label'); ?>:</strong>
                                                        <?php echo htmlspecialchars($item['durationDays'] ?? __('not_provided')); ?> <?php echo __('days') ?? 'days'; ?>
                                                    </p>
                                                    <?php if ($item['instruction'] ?? false): ?>
                                                        <p class="text-muted mb-0">
                                                            <strong><?php echo __('instructions_label'); ?>:</strong>
                                                            <?php echo htmlspecialchars($item['instruction']); ?>
                                                        </p>
                                                    <?php endif; ?>
                                                </div>
                                                <span class="badge bg-primary">#<?php echo $index + 1; ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <p class="mb-0 text-muted"><?php echo __('no_medications_prescribed'); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($prescription['note'] ?? false): ?>
                            <div class="col-12 mb-3">
                                <label class="form-label text-muted"><?php echo __('prescription_notes'); ?></label>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($prescription['note'])); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-end gap-2 mt-4">
                        <?php 
                        $viewCurrent = $prescription['status'] ?? ''; 
                        $allowStatusView = false; 
                        if (hasRole('ADMIN') && !in_array($viewCurrent, ['COMPLETED','CANCELED'])) { $allowStatusView = true; }
                        elseif (hasRole('DOCTOR') && !in_array($viewCurrent, ['DISPENSED','COMPLETED','CANCELED'])) { $allowStatusView = true; }
                        elseif (hasRole('NURSE') && in_array($viewCurrent, ['ISSUED','PENDING'])) { $allowStatusView = true; }
                        if ($allowStatusView): ?>
                            <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#statusModal"
                                data-prescription-id="<?php echo $prescription['id']; ?>" data-current-status="<?php echo $viewCurrent; ?>">
                                <i class="bi bi-arrow-repeat"></i> <?php echo __('update_status'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

<?php elseif ($action === 'view' && !$prescription): ?>
    <!-- Prescription Not Found -->
    <div class="row justify-content-center">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="bi bi-prescription2 text-muted" style="font-size: 4rem;"></i>
                    <h3 class="mt-3 text-muted"><?php echo __('prescription_not_found'); ?></h3>
                    <p class="text-muted"><?php echo __('prescription_not_found_message'); ?></p>
                    <a href="prescriptions.php" class="btn btn-primary">
                        <i class="bi bi-arrow-left"></i> <?php echo __('back_to_prescriptions_list'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>

<?php endif; ?>

<!-- Status Update Modal -->
<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="prescriptions.php?action=update_status">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('update_status'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars(__('close')); ?>"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                    <input type="hidden" name="prescriptionId" id="modalPrescriptionId">

                    <div class="mb-3">
                        <label for="modalStatus" class="form-label"><?php echo __('new_status'); ?></label>
                        <select class="form-select" id="modalStatus" name="status" required>
                            <?php if (hasRole('ADMIN')): ?>
                                <option value="ISSUED"><?php echo __('issued'); ?></option>
                                <option value="PENDING"><?php echo __('pending'); ?></option>
                                <option value="DISPENSED"><?php echo __('dispensed'); ?></option>
                                <option value="COMPLETED"><?php echo __('completed'); ?></option>
                                <option value="CANCELED"><?php echo __('canceled'); ?></option>
                            <?php elseif (hasRole('DOCTOR')): ?>
                                <option value="ISSUED"><?php echo __('issued'); ?></option>
                                <option value="PENDING"><?php echo __('pending'); ?></option>
                                <option value="COMPLETED"><?php echo __('completed'); ?></option>
                                <option value="CANCELED"><?php echo __('canceled'); ?></option>
                            <?php elseif (hasRole('NURSE')): ?>
                                <option value="DISPENSED"><?php echo __('dispensed'); ?></option>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo __('update_status'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('delete_confirmation'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars(__('close')); ?>"></button>
            </div>
            <div class="modal-body">
                <p><?php echo __('are_you_sure'); ?> <strong id="prescriptionPatient"></strong>?</p>
                <p class="text-danger"><small><?php echo __('action_cannot_undone'); ?></small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger"><?php echo __('delete_prescription'); ?></a>
            </div>
        </div>
    </div>
</div>

<script>
    // Status modal functionality
    document.addEventListener('DOMContentLoaded', function () {
        // Lightweight searchable select (no external lib). Works for moderate option counts.
        function initSearchableSelect(selectEl) {
            if (selectEl.disabled) return; // skip if disabled
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
                .filter(o => o.value) // skip placeholder
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
                    row.addEventListener('mousedown', e => { // mousedown to avoid blur race
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

            input.addEventListener('focus', () => {
                dropdown.style.display = 'block';
                filter();
            });
            input.addEventListener('input', filter);
            input.addEventListener('blur', () => {
                setTimeout(() => { dropdown.style.display = 'none'; }, 120);
            });

            // Pre-fill if an option was selected
            if (selectEl.value) {
                const found = options.find(o => o.value === selectEl.value);
                if (found) input.value = found.label;
            }
        }

        document.querySelectorAll('select.js-searchable-select').forEach(initSearchableSelect);

        const statusModal = document.getElementById('statusModal');
        if (statusModal) {
            statusModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const prescriptionId = button.getAttribute('data-prescription-id');
                const currentStatus = button.getAttribute('data-current-status');

                document.getElementById('modalPrescriptionId').value = prescriptionId;
                // Chọn đúng option status, kể cả khi value không khớp do chữ hoa/thường
                const statusSelect = document.getElementById('modalStatus');
                Array.from(statusSelect.options).forEach(opt => {
                    opt.selected = (opt.value.toUpperCase() === (currentStatus || '').toUpperCase());
                });
            });
        }

        // Dynamic medication form
        let medicationCount = document.querySelectorAll('.medication-item').length || 1;

        document.getElementById('addMedication').addEventListener('click', function () {
            medicationCount++;
            const container = document.getElementById('medicationsContainer');
            const medicationItem = document.createElement('div');
            medicationItem.className = 'medication-item border rounded p-3 mb-3';
            medicationItem.innerHTML = `
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><?php echo sprintf(__('medication_item_title'), '${medicationCount}'); ?></h6>
                <button type="button" class="btn btn-outline-danger btn-sm remove-medication">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
            <div class="row">
                <div class="col-md-3 mb-2">
                    <label class="form-label"><?php echo __('medication_name'); ?></label>
                    <input type="text" class="form-control" name="drugName[]" placeholder="<?php echo __('medication_name'); ?>" required>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label"><?php echo __('dosage'); ?></label>
                    <input type="text" class="form-control" name="dosage[]" placeholder="<?php echo __('dosage_example'); ?>" required>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label"><?php echo __('frequency'); ?></label>
                    <input type="text" class="form-control" name="frequency[]" placeholder="<?php echo __('frequency_example'); ?>" required>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="form-label"><?php echo __('duration'); ?> (<?php echo __('days') ?? 'Days'; ?>)</label>
                    <input type="number" class="form-control" name="durationDays[]" min="1" required>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="form-label"><?php echo __('instructions'); ?></label>
                    <input type="text" class="form-control" name="instruction[]" placeholder="<?php echo __('instruction_example'); ?>">
                </div>
            </div>
        `;

            container.appendChild(medicationItem);
            updateRemoveButtons();
        });

        // Remove medication functionality
        document.addEventListener('click', function (e) {
            if (e.target.closest('.remove-medication')) {
                e.target.closest('.medication-item').remove();
                updateRemoveButtons();
                updateMedicationNumbers();
            }
        });

        function updateRemoveButtons() {
            const items = document.querySelectorAll('.medication-item');
            items.forEach((item, index) => {
                const removeBtn = item.querySelector('.remove-medication');
                if (items.length > 1) {
                    removeBtn.style.display = 'block';
                } else {
                    removeBtn.style.display = 'none';
                }
            });
        }

        function updateMedicationNumbers() {
            const items = document.querySelectorAll('.medication-item');
            const medTitleTemplate = "<?php echo addslashes(sprintf(__('medication_item_title'), '%s')); ?>";
            items.forEach((item, index) => {
                const header = item.querySelector('h6');
                header.textContent = medTitleTemplate.replace('%s', index + 1);
            });
            medicationCount = items.length;
        }
    });

    function confirmDelete(prescriptionId, patientName) {
        document.getElementById('prescriptionPatient').textContent = patientName;
        document.getElementById('confirmDeleteBtn').href = 'prescriptions.php?action=delete&id=' + prescriptionId;
        new bootstrap.Modal(document.getElementById('deleteModal')).show();
    }
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>