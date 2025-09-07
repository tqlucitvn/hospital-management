<?php
require_once 'includes/config.php';
require_once 'includes/language.php';

// Require Admin, Doctor, Nurse, or Receptionist access
requireAnyRole(['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST']);

$pageTitle = __('patient_management');
$user = getCurrentUser();
$token = $_SESSION['token'];

// Handle different actions
$action = $_GET['action'] ?? 'list';
$patientId = $_GET['id'] ?? null;

$patients = [];
$patient = null;
$error = '';
$success = '';
$pagination = '';

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
        if ($action === 'add' || $action === 'edit') {
            // Create or update patient
            $patientData = [
                'fullName' => sanitize($_POST['fullName']),
                'email' => sanitize($_POST['email']),
                'phone' => sanitize($_POST['phone']),
                'dateOfBirth' => $_POST['dateOfBirth'],
                'gender' => $_POST['gender'],
                'address' => sanitize($_POST['address']),
                'emergencyContact' => sanitize($_POST['emergencyContact']),
                'medicalHistory' => sanitize($_POST['medicalHistory'])
            ];
            
            if ($action === 'add') {
                $response = makeApiCall(PATIENT_SERVICE_URL, 'POST', $patientData, $token);
                
                // Debug: Log the response
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    error_log("Patient Creation Response: " . json_encode($response));
                }
                
                if ($response['status_code'] === 201) {
                    $_SESSION['flash_message'] = ['message' => __('patient_created_success'), 'type' => 'success'];
                    header('Location: patients.php');
                    exit();
                } else {
                    $error = sprintf(__('status_with_code'), $response['status_code']);
                    if (isset($response['data']['error'])) {
                        $error .= ' - ' . $response['data']['error'];
                    }
                    if (isset($response['data']['message'])) {
                        $error .= ' - ' . $response['data']['message'];
                    }
                    // Debug info
                    if (defined('DEBUG_MODE') && DEBUG_MODE) {
                        $error .= '<br><small>' . addslashes(__('debug_info')) . ': ' . json_encode($response) . '</small>';
                    }
                }
            } else {
                // Check edit permission
                if (!hasAnyRole(['ADMIN', 'RECEPTIONIST'])) {
                    $error = __('access_denied');
                } else {
                    $response = makeApiCall(PATIENT_SERVICE_URL . '/' . $patientId, 'PUT', $patientData, $token);
                    if ($response['status_code'] === 200) {
                        $_SESSION['flash_message'] = ['message' => __('patient_updated_success'), 'type' => 'success'];
                        header('Location: patients.php');
                        exit();
                    } else {
                        $error = handleApiError($response) ?: __('failed_to_update_patient');
                    }
                }
            }
        }
    }
}

// Handle delete action
if ($action === 'delete' && $patientId) {
    if (hasAnyRole(['ADMIN'])) { // Only admin can delete
        $response = makeApiCall(PATIENT_SERVICE_URL . '/' . $patientId, 'DELETE', null, $token);
        if ($response['status_code'] === 200) {
            $_SESSION['flash_message'] = ['message' => __('patient_deleted_success'), 'type' => 'success'];
        } else {
            $_SESSION['flash_message'] = ['message' => __('failed_to_delete_patient'), 'type' => 'danger'];
        }
        header('Location: patients.php');
        exit();
    } else {
    $error = __('access_denied_delete_patients');
    }
}

// Fetch data based on action
try {
    if ($action === 'list') {
        // Build query parameters
        $queryParams = [
            'page' => $page,
            'limit' => $limit
        ];
        if ($search) {
            $queryParams['search'] = $search;
        }
        $queryString = http_build_query($queryParams);

        $response = makeApiCall(PATIENT_SERVICE_URL . '?' . $queryString, 'GET', null, $token);
        $allPatients = [];
        if ($response['status_code'] === 200) {
            $allPatients = $response['data']['patients'] ?? [];
            $totalPatients = $response['data']['total'] ?? 0;
            $totalPages = ceil($totalPatients / $limit);

            // Nếu là Doctor, chỉ lấy bệnh nhân liên quan đến mình
            if ($user['role'] === 'DOCTOR' && isset($user['id'])) {
                // Lấy danh sách appointment của doctor
                $apptResponse = makeApiCall(APPOINTMENT_SERVICE_URL, 'GET', ['doctorId' => $user['id']], $token);
                $patientIds = [];
                if ($apptResponse['status_code'] === 200 && is_array($apptResponse['data'])) {
                    foreach ($apptResponse['data'] as $appt) {
                        if (isset($appt['patientId'])) {
                            $patientIds[$appt['patientId']] = true;
                        }
                    }
                }
                // Lọc bệnh nhân theo danh sách patientIds
                $filteredPatients = array_filter($allPatients, function($p) use ($patientIds) {
                    return isset($patientIds[$p['id']]);
                });
                $totalPatients = count($filteredPatients);
                // Phân trang lại cho Doctor
                $patients = array_slice(array_values($filteredPatients), $offset, $limit);
                $totalPages = ceil($totalPatients / $limit);
            } else {
                $patients = $allPatients;
            }

            // Generate pagination
            if ($totalPages > 1) {
                $baseUrl = 'patients.php?';
                if ($search) $baseUrl .= 'search=' . urlencode($search) . '&';
                $pagination = paginate($page, $totalPages, $baseUrl);
            }
        } else {
            $error = handleApiError($response) ?: __('failed_to_load');
        }
    } elseif ($action === 'edit' && $patientId) {
        if (!hasAnyRole(['ADMIN', 'RECEPTIONIST'])) {
            $error = __('access_denied');
        } else {
            $response = makeApiCall(PATIENT_SERVICE_URL . '/' . $patientId, 'GET', null, $token);
            if ($response['status_code'] === 200) {
                $patient = $response['data'];
            } else {
                $error = handleApiError($response) ?: __('patient_not_found');
            }
        }
    } elseif ($action === 'view' && $patientId) {
        // Handle view patient details
        $response = makeApiCall(PATIENT_SERVICE_URL . '/' . $patientId, 'GET', null, $token);
        
        // Debug logging for view action
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Patient View Response for ID $patientId: " . json_encode($response));
        }
        
        if ($response['status_code'] === 200) {
            $patient = $response['data'];
        } else {
            $error = handleApiError($response) ?: __('patient_not_found');
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
            <i class="bi bi-people"></i>
            <?php echo __('patient_management'); ?>
        </h1>
        <p class="text-muted mb-0"><?php echo __('manage_patient_records'); ?></p>
    </div>
    
    <?php if ($action === 'list' && hasAnyRole(['ADMIN', 'NURSE', 'RECEPTIONIST'])): ?>
    <div>
        <a href="patients.php?action=add" class="btn btn-primary">
            <i class="bi bi-person-plus"></i>
            <?php echo __('add_new_patient'); ?>
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i>
    <?php echo htmlspecialchars($error); ?>
        
        <!-- Debug info for development -->
        <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
        <hr>
            <small>
            <strong><?php echo __('debug_info'); ?>:</strong><br>
            <?php echo __('action'); ?>: <?php echo $action; ?><br>
            <?php echo __('patient_id_label'); ?>: <?php echo $patientId ?? __('not_provided'); ?><br>
            <?php echo __('patient_data_label'); ?>: <?php echo $patient ? __('found') : __('not_found'); ?>
        </small>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i>
    <?php echo htmlspecialchars($success); ?>
    </div>
<?php endif; ?>

<?php if ($action === 'list'): ?>
<!-- Patient List View -->
<div class="card">
    <div class="card-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                        <h5 class="mb-0">
                    <i class="bi bi-list"></i>
                    <?php echo __('patient_list'); ?>
                </h5>
            </div>
            <div class="col-md-6">
                <!-- Search Form -->
                <form method="GET" class="d-flex">
              <input type="text" 
                  class="form-control form-control-sm me-2" 
                  name="search" 
                  placeholder="<?php echo __('search_patients'); ?>" 
                  value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-search"></i>
                    </button>
                    <?php if ($search): ?>
                    <a href="patients.php" class="btn btn-outline-secondary btn-sm ms-1">
                        <i class="bi bi-x"></i>
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    
    <div class="card-body p-0">
        <?php if (empty($patients)): ?>
        <div class="text-center py-5">
            <i class="bi bi-people text-muted" style="font-size: 4rem;"></i>
                <h5 class="text-muted mt-3"><?php echo __("no_patients_found"); ?></h5>
            <p class="text-muted">
                <?php echo $search ? __('try_adjust_search') : __('start_by_adding_first_patient'); ?>
            </p>
            <?php if (hasAnyRole(['ADMIN', 'NURSE', 'RECEPTIONIST'])): ?>
            <a href="patients.php?action=add" class="btn btn-primary">
                <i class="bi bi-person-plus"></i>
                <?php echo __('add_first_patient'); ?>
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th><?php echo __('patient_info'); ?></th>
                        <th><?php echo __('contact'); ?></th>
                        <th><?php echo __('age_gender'); ?></th>
                        <th><?php echo __('registration_date'); ?></th>
                        <th width="120"><?php echo __('actions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($patients as $p): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center">
                                <div class="avatar-circle bg-primary text-white me-3">
                                    <?php echo strtoupper(substr($p['fullName'], 0, 2)); ?>
                                </div>
                                <div>
                                    <h6 class="mb-0"><?php echo sanitize($p['fullName']); ?></h6>
                                    <small class="text-muted"><?php echo __('id'); ?>: <?php echo sanitize($p['id']); ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div>
                                <i class="bi bi-envelope text-muted"></i>
                                <?php echo sanitize($p['email']); ?>
                            </div>
                            <div>
                                <i class="bi bi-telephone text-muted"></i>
                                <?php echo sanitize($p['phone']); ?>
                            </div>
                        </td>
                        <td>
                            <div>
                                <?php 
                                $age = $p['dateOfBirth'] ? date_diff(date_create($p['dateOfBirth']), date_create('today'))->y : null;
                                echo $age ? ($age . ' ' . __('years')) : __('not_provided');
                                ?>
                            </div>
                            <div>
                                <span class="badge <?php echo $p['gender'] === 'MALE' ? 'bg-info' : 'bg-pink'; ?>">
                                    <?php echo __(strtolower($p['gender'])); ?>
                                </span>
                            </div>
                        </td>
                        <td>
                            <?php echo formatDate($p['createdAt']); ?>
                        </td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="patients.php?action=view&id=<?php echo $p['id']; ?>" 
                                   class="btn btn-outline-info"
                                   title="<?php echo __('view_details'); ?>">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (hasAnyRole(['ADMIN', 'RECEPTIONIST'])): ?>
                                <a href="patients.php?action=edit&id=<?php echo $p['id']; ?>" 
                                   class="btn btn-outline-primary"
                                   title="<?php echo __('edit'); ?>">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (hasRole('ADMIN')): ?>
                                        <button type="button" 
                                        class="btn btn-outline-danger"
                                        title="<?php echo __('delete'); ?>"
                                        onclick="confirmDelete('<?php echo $p['id']; ?>', '<?php echo addslashes($p['fullName']); ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <?php if ($pagination): ?>
        <div class="card-footer">
            <?php echo $pagination; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php elseif ($action === 'add' || $action === 'edit'): ?>
<!-- Add/Edit Patient Form -->
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-<?php echo $action === 'add' ? 'person-plus' : 'pencil'; ?>"></i>
                    <?php echo $action === 'add' ? __('add_new_patient') : __('edit_patient'); ?>
                </h5>
            </div>
            
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                    
                    <div class="row">
                        <!-- Personal Information -->
                        <div class="col-12">
                            <h6 class="text-primary mb-3"><?php echo __('personal_information'); ?></h6>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="fullName" class="form-label"><?php echo __('full_name'); ?> *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="fullName" 
                                   name="fullName" 
                                   value="<?php echo $patient ? sanitize($patient['fullName']) : ''; ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label"><?php echo __('email'); ?> *</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo $patient ? sanitize($patient['email']) : ''; ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label"><?php echo __('phone_number'); ?> *</label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="phone" 
                                   name="phone" 
                                   value="<?php echo $patient ? sanitize($patient['phone']) : ''; ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="emergencyContact" class="form-label"><?php echo __('emergency_contact'); ?></label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="emergencyContact" 
                                   name="emergencyContact" 
                                   value="<?php echo $patient ? sanitize($patient['emergencyContact']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="dateOfBirth" class="form-label"><?php echo __('date_of_birth'); ?> *</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="dateOfBirth" 
                                   name="dateOfBirth" 
                                   value="<?php echo $patient && $patient['dateOfBirth'] ? date('Y-m-d', strtotime($patient['dateOfBirth'])) : ''; ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="gender" class="form-label"><?php echo __('gender'); ?> *</label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value=""><?php echo __('select_gender'); ?></option>
                                <option value="MALE" <?php echo ($patient && $patient['gender'] === 'MALE') ? 'selected' : ''; ?>><?php echo __('male'); ?></option>
                                <option value="FEMALE" <?php echo ($patient && $patient['gender'] === 'FEMALE') ? 'selected' : ''; ?>><?php echo __('female'); ?></option>
                                <option value="OTHER" <?php echo ($patient && $patient['gender'] === 'OTHER') ? 'selected' : ''; ?>><?php echo __('other'); ?></option>
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="address" class="form-label"><?php echo __('address'); ?></label>
                            <textarea class="form-control" 
                                      id="address" 
                                      name="address" 
                                      rows="2"><?php echo $patient ? sanitize($patient['address']) : ''; ?></textarea>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="medicalHistory" class="form-label"><?php echo __('medical_history'); ?></label>
                            <textarea class="form-control" 
                                      id="medicalHistory" 
                                      name="medicalHistory" 
                                      rows="3"
                                      placeholder="<?php echo __('medical_history_placeholder'); ?>"><?php echo $patient ? sanitize($patient['medicalHistory']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="patients.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i>
                            <?php echo __('back_to_list'); ?>
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check"></i>
                            <?php echo $action === 'add' ? __('create_patient') : __('update_patient'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($action === 'view' && !$patient): ?>
<!-- Patient Not Found -->
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
                <div class="card-body text-center py-5">
                <i class="bi bi-person-x text-muted" style="font-size: 4rem;"></i>
                <h3 class="mt-3 text-muted"><?php echo __('patient_not_found'); ?></h3>
                <p class="text-muted"><?php echo __('patient_not_found_message'); ?></p>
                <a href="patients.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left"></i> <?php echo __('back_to_list'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<?php elseif ($action === 'view' && $patient): ?>
<!-- Patient Details View -->
<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-person-badge"></i>
                        <?php echo __('patient_details'); ?>
                    </h5>
                    <div>
                        <?php if (hasAnyRole(['ADMIN', 'RECEPTIONIST'])): ?>
                        <a href="patients.php?action=edit&id=<?php echo $patient['id']; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-pencil"></i> <?php echo __('edit'); ?>
                        </a>
                        <?php endif; ?>
                        <a href="patients.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> <?php echo __('back_to_list'); ?>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="row">
                    <!-- Patient Avatar & Basic Info -->
                    <div class="col-md-4 text-center mb-4">
                        <div class="patient-avatar mb-3">
                            <div class="avatar-circle mx-auto bg-primary text-white d-flex align-items-center justify-content-center" style="width: 120px; height: 120px; font-size: 3rem;">
                                <?php echo strtoupper(substr($patient['fullName'] ?? 'P', 0, 1)); ?>
                            </div>
                        </div>
                        <h4><?php echo sanitize($patient['fullName'] ?? __('not_provided')); ?></h4>
                        <p class="text-muted mb-0"><?php echo __('patient_id_label'); ?>: <?php echo substr($patient['id'] ?? __('not_provided'), 0, 8); ?>...</p>
                        <p class="text-muted">
                            <i class="bi bi-calendar"></i>
                            <?php echo __('registered'); ?>: <?php echo isset($patient['createdAt']) ? date('M j, Y', strtotime($patient['createdAt'])) : __('not_provided'); ?>
                        </p>
                    </div>
                    
                    <!-- Patient Details -->
                    <div class="col-md-8">
                        <div class="row">
                            <!-- Personal Information -->
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="bi bi-person"></i> <?php echo __('personal_information'); ?>
                                </h6>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted"><?php echo __('full_name'); ?></label>
                                <p class="fw-bold"><?php echo sanitize($patient['fullName'] ?? __('not_provided')); ?></p>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted"><?php echo __('email'); ?></label>
                                <p class="fw-bold">
                                    <?php if ($patient['email'] ?? false): ?>
                                        <a href="mailto:<?php echo $patient['email']; ?>"><?php echo sanitize($patient['email']); ?></a>
                                    <?php else: ?>
                                        <?php echo __('not_provided'); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted"><?php echo __('phone'); ?></label>
                                <p class="fw-bold">
                                    <?php if ($patient['phone'] ?? false): ?>
                                        <a href="tel:<?php echo $patient['phone']; ?>"><?php echo sanitize($patient['phone']); ?></a>
                                    <?php else: ?>
                                        <?php echo __('not_provided'); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted"><?php echo __('date_of_birth'); ?></label>
                                <p class="fw-bold">
                                    <?php 
                                    if ($patient['dateOfBirth'] ?? false) {
                                        $dob = date('M j, Y', strtotime($patient['dateOfBirth']));
                                        $age = floor((time() - strtotime($patient['dateOfBirth'])) / 31556926);
                                        echo $dob . ' <span class="text-muted">(' . $age . ' ' . __('years') . ')</span>';
                                    } else {
                                        echo __('not_provided');
                                    }
                                    ?>
                                </p>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted"><?php echo __("gender"); ?></label>
                                <p class="fw-bold">
                                    <i class="bi bi-<?php echo ($patient['gender'] ?? '') === 'male' ? 'gender-male text-primary' : 'gender-female text-pink'; ?>"></i>
                                    <?php echo ucfirst(__(strtolower($patient['gender'] ?? 'not_provided'))); ?>
                                </p>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted"><?php echo __('emergency_contact'); ?></label>
                                <p class="fw-bold"><?php echo sanitize($patient['emergencyContact'] ?? __('not_provided')); ?></p>
                            </div>
                        </div>
                        
                        <!-- Address -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="bi bi-geo-alt"></i> <?php echo __('address_information'); ?>
                                </h6>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label text-muted"><?php echo __('address'); ?></label>
                                <p class="fw-bold"><?php echo nl2br(sanitize($patient['address'] ?? __('not_provided'))); ?></p>
                            </div>
                        </div>
                        
                        <!-- Medical History -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="bi bi-heart-pulse"></i> <?php echo __('medical_information'); ?>
                                </h6>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label text-muted"><?php echo __('medical_history'); ?></label>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <?php if ($patient['medicalHistory'] ?? false): ?>
                                            <p class="mb-0"><?php echo nl2br(sanitize($patient['medicalHistory'])); ?></p>
                                        <?php else: ?>
                                            <p class="mb-0 text-muted"><?php echo __('no_medical_history_recorded'); ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="d-flex gap-2 justify-content-end">
                            <?php if (hasAnyRole(['ADMIN', 'DOCTOR', 'RECEPTIONIST'])): ?>
                            <a href="appointments.php?action=add&patient_id=<?php echo $patient['id']; ?><?php echo ($user['role'] === 'DOCTOR') ? '&doctor_id=' . $user['id'] : ''; ?>" class="btn btn-outline-success">
                                <i class="bi bi-calendar-plus"></i> <?php echo __('schedule_appointment'); ?>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAnyRole(['ADMIN', 'DOCTOR'])): ?>
                            <a href="prescriptions.php?action=add&patient_id=<?php echo $patient['id']; ?>" class="btn btn-outline-info">
                                <i class="bi bi-prescription"></i> <?php echo __('create_prescription'); ?>
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAnyRole(['ADMIN', 'RECEPTIONIST'])): ?>
                            <a href="patients.php?action=edit&id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                                <i class="bi bi-pencil"></i> <?php echo __('edit_patient'); ?>
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- Additional CSS -->
<style>
.avatar-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
}

.bg-pink {
    background-color: #e91e63 !important;
}

.table th {
    border-top: none;
    font-weight: 600;
    background-color: #f8f9fa;
}

/* Patient View Styles */
.patient-avatar .avatar-circle {
    border-radius: 50%;
    border: 4px solid #fff;
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
}

.patient-details .form-label {
    font-size: 0.875rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.25rem;
}

.patient-details p.fw-bold {
    font-size: 1rem;
    color: #333;
    margin-bottom: 0;
}
</style>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?php echo __('delete_confirmation'); ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars(__('close')); ?>"></button>
            </div>
            <div class="modal-body">
                <p><?php echo __('are_you_sure'); ?> <strong id="patientName"></strong>?</p>
                <p class="text-danger"><small><?php echo __('action_cannot_undone'); ?></small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger"><?php echo __('delete_patient'); ?></a>
            </div>
        </div>
    </div>
</div>

<script>
function confirmDelete(patientId, patientName) {
    document.getElementById('patientName').textContent = patientName;
    document.getElementById('confirmDeleteBtn').href = 'patients.php?action=delete&id=' + patientId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}

// Auto-focus first input
document.addEventListener('DOMContentLoaded', function() {
    const firstInput = document.querySelector('form input:not([type="hidden"])');
    if (firstInput) {
        firstInput.focus();
    }
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
