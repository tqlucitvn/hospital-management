<?php
require_once 'includes/config.php';
require_once 'includes/language.php';
requireRole('ADMIN');

$pageTitle = __('user_management');
$user = getCurrentUser();
$action = $_GET['action'] ?? 'list';

$users = [];
$selectedUser = null;
$error = '';
$success = '';
$pagination = '';

// Helper functions
function getUserStatusClass($isActive = true) {
    return $isActive ? 'badge bg-success' : 'badge bg-danger';
}

function getRoleClass($role) {
    switch(strtoupper($role ?? '')) {
        case 'ADMIN': return 'badge bg-danger';
        case 'DOCTOR': return 'badge bg-primary';
        case 'NURSE': return 'badge bg-info';
        case 'RECEPTIONIST': return 'badge bg-warning text-dark';
        default: return 'badge bg-secondary';
    }
}

function getUserAvatar($fullName = '', $email = '') {
    // Prioritize fullName, then email as fallback
    $name = '';
    if (!empty($fullName) && $fullName !== __('not_provided')) {
        $name = $fullName;
    } elseif (!empty($email)) {
        $name = $email;
    }
    return strtoupper(substr($name, 0, 1));
}

function getDisplayName($userItem) {
    // Check multiple possible field names for user's name
    if (!empty($userItem['fullName']) && $userItem['fullName'] !== __('not_provided')) {
        return $userItem['fullName'];
    } elseif (!empty($userItem['name'])) {
        return $userItem['name'];
    } elseif (!empty($userItem['firstName']) || !empty($userItem['lastName'])) {
        $firstName = $userItem['firstName'] ?? '';
        $lastName = $userItem['lastName'] ?? '';
        $fullName = trim($firstName . ' ' . $lastName);
        if (!empty($fullName)) return $fullName;
    }
    // Fallback to email without domain
    if (!empty($userItem['email'])) {
        return explode('@', $userItem['email'])[0];
    }
    return __('unknown');
}

function getPhoneNumber($userItem) {
    // Check multiple possible field names for phone
    if (!empty($userItem['phoneNumber'])) {
        return $userItem['phoneNumber'];
    } elseif (!empty($userItem['phone'])) {
        return $userItem['phone'];
    } elseif (!empty($userItem['mobile'])) {
        return $userItem['mobile'];
    }
    return null; // Return null instead of "Not provided"
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';
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
            // Create user
            $userData = [
                'email' => sanitize($_POST['email']),
                'password' => $_POST['password'],
                'role' => sanitize($_POST['role']),
                'fullName' => sanitize($_POST['fullName'] ?? ''),
                'phoneNumber' => sanitize($_POST['phoneNumber'] ?? ''),
                'address' => sanitize($_POST['address'] ?? '')
            ];
            
            $response = makeApiCall(USER_SERVICE_URL . '/register', 'POST', $userData);
            
            if ($response['status_code'] === 201) {
                $success = __('user_created_success');
                $action = 'list';
            } else {
                $error = handleApiError($response) ?: __('failed_to_create_user');
            }
        } elseif ($action === 'edit' && isset($_POST['id'])) {
            // Update user
            $userId = $_POST['id'];
            $userData = [
                'email' => sanitize($_POST['email']),
                'role' => sanitize($_POST['role']),
                'fullName' => sanitize($_POST['fullName'] ?? ''),
                'phoneNumber' => sanitize($_POST['phoneNumber'] ?? ''),
                'address' => sanitize($_POST['address'] ?? '')
            ];
            
            // Add password if provided
            if (!empty($_POST['password'])) {
                $userData['password'] = $_POST['password'];
            }
            
            $response = makeApiCall(USER_SERVICE_URL . '/' . $userId, 'PUT', $userData, $token);
                if ($response['status_code'] === 200) {
                // Redirect to the updated user view to show latest data
                header('Location: users.php?action=view&id=' . urlencode($userId) . '&success=' . urlencode(__('user_updated_success')));
                exit();
            } else {
                $error = handleApiError($response) ?: __('failed_to_update_user');
            }
        } elseif ($action === 'update_role' && isset($_POST['user_id'])) {
            // Update user role only
            $userId = $_POST['user_id'];
            $newRole = $_POST['role'];
            
            $response = makeApiCall(USER_SERVICE_URL . '/' . $userId . '/role', 'PATCH', 
                                   ['role' => $newRole], $token);
            
            if ($response['status_code'] === 200) {
                $success = __('user_role_updated_success');
                $action = 'list';
            } else {
                $error = handleApiError($response) ?: __('failed_to_update_user_role');
            }
        }
    }
}

// Handle delete action
if ($action === 'delete' && isset($_GET['id'])) {
    $token = $_SESSION['token'];
    $userId = $_GET['id'];
    
    $response = makeApiCall(USER_SERVICE_URL . '/' . $userId, 'DELETE', null, $token);
    
    if ($response['status_code'] === 200 || $response['status_code'] === 204) {
        $success = __('user_deleted_success');
    } else {
        $error = handleApiError($response) ?: __('failed_to_delete_user');
    }
    $action = 'list';
}

// Fetch data based on action
try {
    $token = $_SESSION['token'];
    
    if ($action === 'list') {
        // Build query parameters for users
        $queryParams = [
            'page' => $page,
            'limit' => $limit
        ];
        if ($search) {
            $queryParams['search'] = $search;
        }
        if ($roleFilter) {
            $queryParams['role'] = $roleFilter;
        }
        $queryString = http_build_query($queryParams);
        
        $response = makeApiCall(USER_SERVICE_URL . '?' . $queryString, 'GET', null, $token);
        
        if ($response['status_code'] === 200) {
            // Handle paginated response
            if (isset($response['data']['users']) && isset($response['data']['total'])) {
                $users = $response['data']['users'];
                $totalUsers = $response['data']['total'];
            } else {
                // Fallback for non-paginated response
                $users = is_array($response['data']) ? $response['data'] : [];
                
                // Apply client-side filtering if API doesn't support it
                if ($search || $roleFilter) {
                    $users = array_filter($users, function($userItem) use ($search, $roleFilter) {
                        $matchesSearch = true;
                        $matchesRole = true;
                        
                        if ($search) {
                            $searchLower = strtolower($search);
                            $matchesSearch = stripos($userItem['email'] ?? '', $search) !== false ||
                                           stripos($userItem['fullName'] ?? '', $search) !== false ||
                                           stripos($userItem['role'] ?? '', $search) !== false;
                        }
                        
                        if ($roleFilter) {
                            $matchesRole = ($userItem['role'] ?? '') === $roleFilter;
                        }
                        
                        return $matchesSearch && $matchesRole;
                    });
                }
                
                $totalUsers = count($users);
                $users = array_slice($users, $offset, $limit);
            }
            
            $totalPages = ceil($totalUsers / $limit);
            
            // Generate pagination
            if ($totalPages > 1) {
                $baseUrl = 'users.php?';
                if ($search) $baseUrl .= 'search=' . urlencode($search) . '&';
                if ($roleFilter) $baseUrl .= 'role=' . urlencode($roleFilter) . '&';
                $pagination = paginate($page, $totalPages, $baseUrl);
            }
        } else {
            $error = handleApiError($response) ?: __('failed_to_load_users');
        }
    } elseif (($action === 'edit' || $action === 'view') && isset($_GET['id'])) {
        $userId = $_GET['id'];
        $response = makeApiCall(USER_SERVICE_URL . '/' . $userId, 'GET', null, $token);
        if ($response['status_code'] === 200) {
            $selectedUser = $response['data'];
    } else {
        $error = handleApiError($response) ?: __('user_not_found');
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
            <?php echo __('user_management'); ?>
        </h1>
        <p class="text-muted mb-0"><?php echo __('manage_system_users_description'); ?></p>
    </div>
    
    <?php if ($action === 'list'): ?>
    <div>
        <a href="users.php?action=add" class="btn btn-primary">
            <i class="bi bi-person-plus"></i>
            <?php echo __('add_user'); ?>
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
<!-- Users List -->
<div class="card">
    <div class="card-header">
        <div class="row align-items-center">
            <div class="col-md-6">
                <h5 class="mb-0">
                    <i class="bi bi-list"></i>
                    <?php echo __('user_list'); ?>
                    <?php if (isset($totalUsers) && $totalUsers > 0): ?>
                    <span class="badge bg-primary ms-2"><?php echo $totalUsers; ?></span>
                    <?php endif; ?>
                </h5>
            </div>
            <div class="col-md-6">
                <!-- Search and Filter Form -->
              <form method="GET" class="d-flex gap-2">
                    <input type="text" 
                           class="form-control form-control-sm" 
                           name="search" 
                   placeholder="<?php echo __('search_users'); ?>" 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <select class="form-select form-select-sm" name="role" style="width: auto;">
               <option value=""><?php echo __('all_roles'); ?></option>
               <option value="ADMIN" <?php echo $roleFilter === 'ADMIN' ? 'selected' : ''; ?>><?php echo __('admin'); ?></option>
               <option value="DOCTOR" <?php echo $roleFilter === 'DOCTOR' ? 'selected' : ''; ?>><?php echo __('doctor'); ?></option>
               <option value="NURSE" <?php echo $roleFilter === 'NURSE' ? 'selected' : ''; ?>><?php echo __('nurse'); ?></option>
               <option value="RECEPTIONIST" <?php echo $roleFilter === 'RECEPTIONIST' ? 'selected' : ''; ?>><?php echo __('receptionist'); ?></option>
                    </select>
                    <button type="submit" class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-search"></i>
                    </button>
                    <?php if ($search || $roleFilter): ?>
                    <a href="users.php" class="btn btn-outline-secondary btn-sm">
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
                            <i class="bi bi-person me-1"></i><?php echo __('name'); ?>
                        </th>
                        <th class="border-0">
                            <i class="bi bi-envelope me-1"></i><?php echo __('email'); ?>
                        </th>
                        <th class="border-0">
                            <i class="bi bi-shield me-1"></i><?php echo __('role'); ?>
                        </th>
                        <th class="border-0">
                            <i class="bi bi-telephone me-1"></i><?php echo __('phone'); ?>
                        </th>
                        <th class="border-0">
                            <i class="bi bi-circle me-1"></i><?php echo __('status'); ?>
                        </th>
                        <th class="border-0">
                            <i class="bi bi-calendar-event me-1"></i><?php echo __('created'); ?>
                        </th>
                        <th class="border-0 text-center">
                            <i class="bi bi-gear me-1"></i><?php echo __('actions'); ?>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr>
                            <td colspan="8" class="text-center py-5">
                            <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2 mb-0"><?php echo __('no_users_found'); ?></p>
                            <?php if ($search || $roleFilter): ?>
                            <small class="text-muted"><?php echo __('try_adjust_search'); ?></small>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($users as $userItem): ?>
                    <?php
                    // Format date
                    $createdDate = isset($userItem['createdAt']) ? date('M j, Y', strtotime($userItem['createdAt'])) : __('not_provided');
                    
                    // Status and role styling
                    $statusClass = getUserStatusClass($userItem['isActive'] ?? true);
                    $roleClass = getRoleClass($userItem['role'] ?? '');
                    $displayName = getDisplayName($userItem);
                    $avatar = getUserAvatar($displayName, $userItem['email'] ?? '');
                    $phoneNumber = getPhoneNumber($userItem);
                    
                    // Role color for avatar
                    $avatarBg = 'bg-primary';
                    switch(strtoupper($userItem['role'] ?? '')) {
                        case 'ADMIN': $avatarBg = 'bg-danger'; break;
                        case 'DOCTOR': $avatarBg = 'bg-primary'; break;
                        case 'NURSE': $avatarBg = 'bg-info'; break;
                        case 'RECEPTIONIST': $avatarBg = 'bg-warning'; break;
                    }
                    ?>
                    <tr>
                        <td class="align-middle">
                            <span class="text-monospace small"><?php echo htmlspecialchars(substr($userItem['id'] ?? __('not_provided'), 0, 8)); ?>...</span>
                        </td>
                        <td class="align-middle">
                            <div class="d-flex align-items-center">
                                <div class="avatar-sm <?php echo $avatarBg; ?> text-white rounded-circle d-flex align-items-center justify-content-center me-2" style="width: 32px; height: 32px; font-size: 0.75rem;">
                                    <?php echo $avatar; ?>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark">
                                        <?php echo htmlspecialchars($displayName); ?>
                                    </div>
                                    <small class="text-muted"><?php echo __('id'); ?>: <?php echo htmlspecialchars(substr($userItem['id'] ?? __('not_provided'), 0, 8)); ?>...</small>
                                </div>
                            </div>
                        </td>
                        <td class="align-middle">
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($userItem['email'] ?? __('not_provided')); ?></div>
                            <?php if ($userItem['email'] === getCurrentUser()['email']): ?>
                            <small class="text-primary"><?php echo __('you_marker'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="align-middle">
                            <span class="<?php echo $roleClass; ?>"><?php echo htmlspecialchars($userItem['role'] ?? __('unknown')); ?></span>
                        </td>
                        <td class="align-middle">
                            <?php if ($phoneNumber): ?>
                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($phoneNumber); ?></div>
                            <?php else: ?>
                            <small class="text-muted"><?php echo __('not_provided'); ?></small>
                            <?php endif; ?>
                        </td>
                        <td class="align-middle">
                            <span class="<?php echo $statusClass; ?>">
                                <?php echo ($userItem['isActive'] ?? true) ? __('active') : __('inactive'); ?>
                            </span>
                        </td>
                        <td class="align-middle">
                            <div class="fw-bold text-dark"><?php echo $createdDate; ?></div>
                            <small class="text-muted"><?php echo isset($userItem['createdAt']) ? date('H:i', strtotime($userItem['createdAt'])) : ''; ?></small>
                        </td>
                        <td class="align-middle text-center">
                            <div class="btn-group btn-group-sm">
                                          <a href="users.php?action=view&id=<?php echo $userItem['id']; ?>" 
                                              class="btn btn-outline-primary btn-sm" 
                                              title="<?php echo __('view_details'); ?>">
                                    <i class="bi bi-eye"></i>
                                </a>
                                          <a href="users.php?action=edit&id=<?php echo $userItem['id']; ?>" 
                                              class="btn btn-outline-warning btn-sm" 
                                              title="<?php echo __('edit_user'); ?>">
                                    <i class="bi bi-pencil"></i>
                                </a>
                <button type="button" 
                    class="btn btn-outline-info btn-sm" 
                    data-bs-toggle="modal" 
                    data-bs-target="#roleModal"
                                        data-user-id="<?php echo $userItem['id']; ?>"
                                        data-current-role="<?php echo $userItem['role'] ?? ''; ?>"
                                        data-user-name="<?php echo htmlspecialchars($userItem['fullName'] ?? $userItem['email'] ?? __('unknown')); ?>"
                    title="<?php echo __('change_role'); ?>">
                                    <i class="bi bi-shield"></i>
                                </button>
                                <?php if ($userItem['email'] !== getCurrentUser()['email']): ?>
                <button type="button" 
                    class="btn btn-outline-danger btn-sm" 
                    onclick="confirmDelete('<?php echo $userItem['id']; ?>', '<?php echo addslashes($userItem['fullName'] ?? $userItem['email'] ?? __('unknown')); ?>')" 
                    title="<?php echo __('delete_user'); ?>">
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
            <?php echo sprintf(__('showing_users_range'), (($page - 1) * $limit + 1), min($page * $limit, $totalUsers ?? 0), $totalUsers ?? 0); ?>
        </div>
        <nav>
            <?php echo $pagination; ?>
        </nav>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($action === 'add' || ($action === 'edit' && $selectedUser)): ?>
<!-- Add/Edit User Form -->
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-<?php echo $action === 'add' ? 'person-plus' : 'pencil'; ?>"></i>
                    <?php echo $action === 'add' ? __('add_user') : __('edit_user'); ?>
                </h5>
            </div>
            
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                    <?php if ($action === 'edit'): ?>
                    <input type="hidden" name="id" value="<?php echo $selectedUser['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                                   <label for="fullName" class="form-label"><?php echo __('full_name'); ?></label>
                            <input type="text" 
                                   class="form-control" 
                                   id="fullName" 
                                   name="fullName" 
                                   value="<?php echo $action === 'edit' ? htmlspecialchars($selectedUser['fullName'] ?? '') : ''; ?>"
                                   placeholder="<?php echo __('enter_full_name'); ?>">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="email" class="form-label"><?php echo __('email_address'); ?> *</label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   value="<?php echo $action === 'edit' ? htmlspecialchars($selectedUser['email'] ?? '') : ''; ?>"
                                   required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="password" class="form-label">
                                <?php echo __('password'); ?> <?php echo $action === 'add' ? '*' : __('leave_blank_to_keep_current'); ?>
                            </label>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   <?php echo $action === 'add' ? 'required' : ''; ?>>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="role" class="form-label"><?php echo __('role'); ?> *</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value=""><?php echo __('select_role'); ?></option>
                                <option value="ADMIN" <?php echo ($action === 'edit' && ($selectedUser['role'] ?? '') === 'ADMIN') ? 'selected' : ''; ?>><?php echo __('admin'); ?></option>
                                <option value="DOCTOR" <?php echo ($action === 'edit' && ($selectedUser['role'] ?? '') === 'DOCTOR') ? 'selected' : ''; ?>><?php echo __('doctor'); ?></option>
                                <option value="NURSE" <?php echo ($action === 'edit' && ($selectedUser['role'] ?? '') === 'NURSE') ? 'selected' : ''; ?>><?php echo __('nurse'); ?></option>
                                <option value="RECEPTIONIST" <?php echo ($action === 'edit' && ($selectedUser['role'] ?? '') === 'RECEPTIONIST') ? 'selected' : ''; ?>><?php echo __('receptionist'); ?></option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="phoneNumber" class="form-label"><?php echo __('phone_number'); ?></label>
                            <input type="tel" 
                                   class="form-control" 
                                   id="phoneNumber" 
                                   name="phoneNumber" 
                                   value="<?php echo $action === 'edit' ? htmlspecialchars($selectedUser['phoneNumber'] ?? '') : ''; ?>"
                                   placeholder="<?php echo __('enter_phone_number'); ?>">
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label for="address" class="form-label"><?php echo __('address'); ?></label>
                            <textarea class="form-control" 
                                      id="address" 
                                      name="address" 
                                      rows="3" 
                                      placeholder="<?php echo __('enter_address'); ?>"><?php echo $action === 'edit' ? htmlspecialchars($selectedUser['address'] ?? '') : ''; ?></textarea>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-end gap-2">
                            <a href="users.php" class="btn btn-outline-secondary">
                            <i class="bi bi-x"></i> <?php echo __('cancel'); ?>
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check"></i> <?php echo $action === 'add' ? __('create_user') : __('update_user'); ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($action === 'view' && $selectedUser): ?>
<!-- View User Details -->
<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="bi bi-person"></i>
                        <?php echo __('user_details'); ?>
                    </h5>
                    <div>
                        <a href="users.php?action=edit&id=<?php echo $selectedUser['id']; ?>" class="btn btn-outline-primary btn-sm">
                            <i class="bi bi-pencil"></i> <?php echo __('edit'); ?>
                        </a>
                        <a href="users.php" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> <?php echo __('back_to_list'); ?>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted"><?php echo __('user_id_label'); ?></label>
                        <p class="fw-bold text-monospace"><?php echo htmlspecialchars($selectedUser['id']); ?></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted"><?php echo __('status'); ?></label>
                        <p><span class="<?php echo getUserStatusClass($selectedUser['isActive'] ?? true); ?>"><?php echo ($selectedUser['isActive'] ?? true) ? __('active') : __('inactive'); ?></span></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted"><?php echo __('full_name'); ?></label>
                        <p class="fw-bold"><?php echo htmlspecialchars($selectedUser['fullName'] ?? __('not_provided')); ?></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted"><?php echo __('email_address'); ?></label>
                        <p class="fw-bold"><?php echo htmlspecialchars($selectedUser['email'] ?? __('not_provided')); ?></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted"><?php echo __('role'); ?></label>
                        <p><span class="<?php echo getRoleClass($selectedUser['role'] ?? ''); ?>"><?php echo htmlspecialchars($selectedUser['role'] ?? __('unknown')); ?></span></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted"><?php echo __('phone_number'); ?></label>
                        <p class="fw-bold"><?php echo htmlspecialchars($selectedUser['phoneNumber'] ?? __('not_provided')); ?></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted"><?php echo __('created'); ?></label>
                        <p class="fw-bold"><?php echo isset($selectedUser['createdAt']) ? date('l, F j, Y \a\t H:i', strtotime($selectedUser['createdAt'])) : __('not_provided'); ?></p>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label text-muted"><?php echo __('updated'); ?></label>
                        <p class="fw-bold"><?php echo isset($selectedUser['updatedAt']) ? date('l, F j, Y \a\t H:i', strtotime($selectedUser['updatedAt'])) : __('not_provided'); ?></p>
                    </div>
                    
                    <?php if ($selectedUser['address'] ?? false): ?>
                    <div class="col-12 mb-3">
                        <label class="form-label text-muted"><?php echo __('address'); ?></label>
                        <div class="card bg-light">
                            <div class="card-body">
                                <p class="mb-0"><?php echo nl2br(htmlspecialchars($selectedUser['address'])); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Action Buttons -->
                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" 
                            class="btn btn-warning" 
                            data-bs-toggle="modal" 
                            data-bs-target="#roleModal"
                            data-user-id="<?php echo $selectedUser['id']; ?>"
                            data-current-role="<?php echo $selectedUser['role'] ?? ''; ?>"
                            data-user-name="<?php echo htmlspecialchars($selectedUser['fullName'] ?? $selectedUser['email'] ?? __('unknown')); ?>">
                        <i class="bi bi-shield"></i> <?php echo __('change_role'); ?>
                    </button>
                    <a href="users.php?action=edit&id=<?php echo $selectedUser['id']; ?>" class="btn btn-primary">
                        <i class="bi bi-pencil"></i> <?php echo __('edit_user'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php elseif ($action === 'view' && !$selectedUser): ?>
<!-- User Not Found -->
<div class="row justify-content-center">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-person-x text-muted" style="font-size: 4rem;"></i>
                    <h3 class="mt-3 text-muted"><?php echo __('user_not_found'); ?></h3>
                <p class="text-muted"><?php echo __('user_not_found_message'); ?></p>
                <a href="users.php" class="btn btn-primary">
                    <i class="bi bi-arrow-left"></i> <?php echo __('back_to_users_list'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<!-- Role Update Modal -->
<div class="modal fade" id="roleModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="users.php?action=update_role">
                <div class="modal-header">
                    <h5 class="modal-title"><?php echo __('change_role'); ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?php echo htmlspecialchars(__('close')); ?>"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">
                    <input type="hidden" name="user_id" id="modalUserId">
                    
                    <p><?php echo __('change_role_for'); ?> <strong id="modalUserName"></strong>:</p>
                    
                    <div class="mb-3">
                        <label for="modalRole" class="form-label"><?php echo __('new_role'); ?></label>
                        <select class="form-select" id="modalRole" name="role" required>
                            <option value="ADMIN"><?php echo __('admin'); ?></option>
                            <option value="DOCTOR"><?php echo __('doctor'); ?></option>
                            <option value="NURSE"><?php echo __('nurse'); ?></option>
                            <option value="RECEPTIONIST"><?php echo __('receptionist'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                    <button type="submit" class="btn btn-primary"><?php echo __('update_role'); ?></button>
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
                <p><?php echo __('are_you_sure'); ?> <strong id="deleteUserName"></strong>?</p>
                <p class="text-danger"><small><?php echo __('action_cannot_undone'); ?></small></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo __('cancel'); ?></button>
                <a href="#" id="confirmDeleteBtn" class="btn btn-danger"><?php echo __('delete_user'); ?></a>
            </div>
        </div>
    </div>
</div>

<script>
// Role modal functionality
document.addEventListener('DOMContentLoaded', function() {
    const roleModal = document.getElementById('roleModal');
    if (roleModal) {
        roleModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const userId = button.getAttribute('data-user-id');
            const currentRole = button.getAttribute('data-current-role');
            const userName = button.getAttribute('data-user-name');
            
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalRole').value = currentRole;
            document.getElementById('modalUserName').textContent = userName;
        });
    }
    
    // Auto-focus first input
    const firstInput = document.querySelector('form input:not([type="hidden"]), form select');
    if (firstInput) {
        firstInput.focus();
    }
});

function confirmDelete(userId, userName) {
    document.getElementById('deleteUserName').textContent = userName;
    document.getElementById('confirmDeleteBtn').href = 'users.php?action=delete&id=' + userId;
    new bootstrap.Modal(document.getElementById('deleteModal')).show();
}
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
