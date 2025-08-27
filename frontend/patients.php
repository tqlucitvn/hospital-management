<?php
require_once 'includes/config.php';

// Require Admin, Doctor, Nurse, or Receptionist access
requireAnyRole(['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST']);

$pageTitle = 'Patient Management';
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
        $error = 'Invalid CSRF token.';
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
                    $_SESSION['flash_message'] = ['message' => 'Patient created successfully!', 'type' => 'success'];
                    header('Location: patients.php');
                    exit();
                } else {
                    $error = 'Failed to create patient. Status: ' . $response['status_code'];
                    if (isset($response['data']['error'])) {
                        $error .= ' - ' . $response['data']['error'];
                    }
                    if (isset($response['data']['message'])) {
                        $error .= ' - ' . $response['data']['message'];
                    }
                    // Debug info
                    if (defined('DEBUG_MODE') && DEBUG_MODE) {
                        $error .= '<br><small>Debug: ' . json_encode($response) . '</small>';
                    }
                }
            } else {
                $response = makeApiCall(PATIENT_SERVICE_URL . '/' . $patientId, 'PUT', $patientData, $token);
                if ($response['status_code'] === 200) {
                    $_SESSION['flash_message'] = ['message' => 'Patient updated successfully!', 'type' => 'success'];
                    header('Location: patients.php');
                    exit();
                } else {
                    $error = handleApiError($response) ?: 'Failed to update patient.';
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
            $_SESSION['flash_message'] = ['message' => 'Patient deleted successfully!', 'type' => 'success'];
        } else {
            $_SESSION['flash_message'] = ['message' => 'Failed to delete patient.', 'type' => 'danger'];
        }
        header('Location: patients.php');
        exit();
    } else {
        $error = 'Access denied. Only administrators can delete patients.';
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
            $error = handleApiError($response) ?: 'Failed to load patients.';
        }
    } elseif ($action === 'edit' && $patientId) {
        $response = makeApiCall(PATIENT_SERVICE_URL . '/' . $patientId, 'GET', null, $token);
        if ($response['status_code'] === 200) {
            $patient = $response['data'];
        } else {
            $error = handleApiError($response) ?: 'Patient not found.';
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
            $error = handleApiError($response) ?: 'Patient not found.';
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
            <i class="bi bi-people"></i>
            Patient Management
        </h1>
        <p class="text-muted mb-0">Manage patient records and information</p>
    </div>
    
    <?php if ($action === 'list' && hasAnyRole(['ADMIN', 'RECEPTIONIST'])): ?>
    <div>
        <a href="patients.php?action=add" class="btn btn-primary">
            <i class="bi bi-person-plus"></i>
            Add New Patient
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i>
        <?php echo $error; ?>
        
        <!-- Debug info for development -->
        <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
        <hr>
        <small>
            <strong>Debug Info:</strong><br>
            Action: <?php echo $action; ?><br>
            Patient ID: <?php echo $patientId ?? 'N/A'; ?><br>
            Patient Data: <?php echo $patient ? 'Found' : 'Not Found'; ?>
        </small>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i>
        <?php echo $success; ?>
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
                    Patient List
                </h5>
            </div>
            <div class="col-md-6">
                <!-- Search Form -->
                <form method="GET" class="d-flex">
                    <input type="text" 
                           class="form-control form-control-sm me-2" 
                           name="search" 
                           placeholder="Search patients..." 
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
            <h5 class="text-muted mt-3">No patients found</h5>
            <p class="text-muted">
                <?php echo $search ? 'Try adjusting your search criteria.' : 'Start by adding your first patient.'; ?>
            </p>
            <?php if (hasAnyRole(['ADMIN', 'RECEPTIONIST'])): ?>
            <a href="patients.php?action=add" class="btn btn-primary">
                <i class="bi bi-person-plus"></i>
                Add First Patient
            </a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Patient Info</th>
                        <th>Contact</th>
                        <th>Age/Gender</th>
                        <th>Registration Date</th>
                        <th width="120">Actions</th>
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
                                    <small class="text-muted">ID: <?php echo sanitize($p['id']); ?></small>
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
                                $age = $p['dateOfBirth'] ? date_diff(date_create($p['dateOfBirth']), date_create('today'))->y : 'N/A';
                                echo $age . ' years';
                                ?>
                            </div>
                            <div>
                                <span class="badge <?php echo $p['gender'] === 'MALE' ? 'bg-info' : 'bg-pink'; ?>">
                                    <?php echo ucfirst(strtolower($p['gender'])); ?>
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
                                   title="View Details">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php if (hasAnyRole(['ADMIN', 'RECEPTIONIST'])): ?>
                                <a href="patients.php?action=edit&id=<?php echo $p['id']; ?>" 
                                   class="btn btn-outline-primary"
                                   title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <?php endif; ?>
                                <?php if (hasRole('ADMIN')): ?>
                                <button type="button" 
                                        class="btn btn-outline-danger"
                                        title="Delete"
                                        onclick="confirmDelete('<?php echo $p['id']; ?>', '<?php echo sanitize($p['fullName']); ?>')">
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
                    <?php echo $action === 'add' ? 'Add New Patient' : 'Edit Patient'; ?>
                </h5>
            </div>
            
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                    
                    <div class="row">
                        <!-- Personal Information -->
                        <div class="col-12">
                            <h6 class="text-primary mb-3">Personal Information</h6>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="fullName" class="form-label">Full Name *</label>
                            <input type="text" 
                                   class="form-control" 
                                   id="fullName" 
                                   name="fullName" 
                                   value="<?php echo $patient ? sanitize($patient['fullName']) : ''; ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label">Email *</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo $patient ? sanitize($patient['email']) : ''; ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="phone" class="form-label">Phone Number *</label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="phone" 
                                   name="phone" 
                                   value="<?php echo $patient ? sanitize($patient['phone']) : ''; ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="emergencyContact" class="form-label">Emergency Contact</label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="emergencyContact" 
                                   name="emergencyContact" 
                                   value="<?php echo $patient ? sanitize($patient['emergencyContact']) : ''; ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="dateOfBirth" class="form-label">Date of Birth *</label>
                            <input type="date" 
                                   class="form-control" 
                                   id="dateOfBirth" 
                                   name="dateOfBirth" 
                                   value="<?php echo $patient && $patient['dateOfBirth'] ? date('Y-m-d', strtotime($patient['dateOfBirth'])) : ''; ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="gender" class="form-label">Gender *</label>
                            <select class="form-select" id="gender" name="gender" required>
                                <option value="">Select Gender</option>
                                <option value="MALE" <?php echo ($patient && $patient['gender'] === 'MALE') ? 'selected' : ''; ?>>Male</option>
                                <option value="FEMALE" <?php echo ($patient && $patient['gender'] === 'FEMALE') ? 'selected' : ''; ?>>Female</option>
                                <option value="OTHER" <?php echo ($patient && $patient['gender'] === 'OTHER') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" 
                                      id="address" 
                                      name="address" 
                                      rows="2"><?php echo $patient ? sanitize($patient['address']) : ''; ?></textarea>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="medicalHistory" class="form-label">Medical History</label>
                            <textarea class="form-control" 
                                      id="medicalHistory" 
                                      name="medicalHistory" 
                                      rows="3"
                                      placeholder="Enter any relevant medical history, allergies, or conditions..."><?php echo $patient ? sanitize($patient['medicalHistory']) : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                        <a href="patients.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i>
                            Back to List
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check"></i>
                            <?php echo $action === 'add' ? 'Create Patient' : 'Update Patient'; ?>
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
                <h3 class="mt-3 text-muted">Patient Not Found</h3>
                <p class="text-muted">The patient you're looking for could not be found or you don't have permission to view it.</p>
                <a href="patients.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left"></i> Back to Patient List
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
                        Patient Details
                    </h5>
                    <div>
                        <?php if (hasAnyRole(['ADMIN', 'RECEPTIONIST'])): ?>
                        <a href="patients.php?action=edit&id=<?php echo $patient['id']; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-pencil"></i> Edit
                        </a>
                        <?php endif; ?>
                        <a href="patients.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Back to List
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
                        <h4><?php echo sanitize($patient['fullName'] ?? 'N/A'); ?></h4>
                        <p class="text-muted mb-0">Patient ID: <?php echo substr($patient['id'] ?? 'N/A', 0, 8); ?>...</p>
                        <p class="text-muted">
                            <i class="bi bi-calendar"></i>
                            Registered: <?php echo isset($patient['createdAt']) ? date('M j, Y', strtotime($patient['createdAt'])) : 'N/A'; ?>
                        </p>
                    </div>
                    
                    <!-- Patient Details -->
                    <div class="col-md-8">
                        <div class="row">
                            <!-- Personal Information -->
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="bi bi-person"></i> Personal Information
                                </h6>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Full Name</label>
                                <p class="fw-bold"><?php echo sanitize($patient['fullName'] ?? 'N/A'); ?></p>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Email</label>
                                <p class="fw-bold">
                                    <?php if ($patient['email'] ?? false): ?>
                                        <a href="mailto:<?php echo $patient['email']; ?>"><?php echo sanitize($patient['email']); ?></a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Phone</label>
                                <p class="fw-bold">
                                    <?php if ($patient['phone'] ?? false): ?>
                                        <a href="tel:<?php echo $patient['phone']; ?>"><?php echo sanitize($patient['phone']); ?></a>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Date of Birth</label>
                                <p class="fw-bold">
                                    <?php 
                                    if ($patient['dateOfBirth'] ?? false) {
                                        $dob = date('M j, Y', strtotime($patient['dateOfBirth']));
                                        $age = floor((time() - strtotime($patient['dateOfBirth'])) / 31556926);
                                        echo $dob . ' <span class="text-muted">(' . $age . ' years old)</span>';
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </p>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Gender</label>
                                <p class="fw-bold">
                                    <i class="bi bi-<?php echo ($patient['gender'] ?? '') === 'male' ? 'gender-male text-primary' : 'gender-female text-pink'; ?>"></i>
                                    <?php echo ucfirst($patient['gender'] ?? 'N/A'); ?>
                                </p>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Emergency Contact</label>
                                <p class="fw-bold"><?php echo sanitize($patient['emergencyContact'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                        
                        <!-- Address -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="bi bi-geo-alt"></i> Address Information
                                </h6>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label text-muted">Address</label>
                                <p class="fw-bold"><?php echo nl2br(sanitize($patient['address'] ?? 'N/A')); ?></p>
                            </div>
                        </div>
                        
                        <!-- Medical History -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6 class="text-primary mb-3">
                                    <i class="bi bi-heart-pulse"></i> Medical Information
                                </h6>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label text-muted">Medical History</label>
                                <div class="card bg-light">
                                    <div class="card-body">
                                        <?php if ($patient['medicalHistory'] ?? false): ?>
                                            <p class="mb-0"><?php echo nl2br(sanitize($patient['medicalHistory'])); ?></p>
                                        <?php else: ?>
                                            <p class="mb-0 text-muted">No medical history recorded.</p>
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
                                <i class="bi bi-calendar-plus"></i> Schedule Appointment
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAnyRole(['ADMIN', 'DOCTOR'])): ?>
                            <a href="prescriptions.php?action=add&patient_id=<?php echo $patient['id']; ?>" class="btn btn-outline-info">
                                <i class="bi bi-prescription"></i> Create Prescription
                            </a>
                            <?php endif; ?>
                            
                            <?php if (hasAnyRole(['ADMIN', 'RECEPTIONIST'])): ?>
                            <a href="patients.php?action=edit&id=<?php echo $patient['id']; ?>" class="btn btn-primary">
                                <i class="bi bi-pencil"></i> Edit Patient
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
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete patient <strong id="patientName"></strong>?</p>
                <p class="text-danger"><small>This action cannot be undone.</small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger">Delete Patient</a>
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
