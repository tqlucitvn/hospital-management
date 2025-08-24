<?php
require_once 'includes/config.php';
requireRole('ADMIN');

$pageTitle = 'User Management';
$user = getCurrentUser();
$action = $_GET['action'] ?? 'list';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submittedAction = $_POST['action'] ?? '';
    
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        if ($submittedAction === 'add') {
            $email = $_POST['email'];
            $password = $_POST['password'];
            $role = $_POST['role'];
            
            $response = makeApiCall(USER_SERVICE_URL . '/register', 'POST', [
                'email' => $email,
                'password' => $password,
                'role' => $role
            ]);
            
            if ($response['status_code'] === 201) {
                header('Location: users.php?success=User created successfully');
                exit();
            } else {
                $error = 'Failed to create user: ' . ($response['data']['error'] ?? 'Unknown error');
            }
        } elseif ($submittedAction === 'edit_role') {
            // Debug: Show all POST data
            if (isset($_GET['debug'])) {
                echo "<pre>POST Data: " . print_r($_POST, true) . "</pre>";
            }
            
            $userId = $_POST['user_id'];
            $newRole = $_POST['role'];
            
            // Debug: Log edit role request
            error_log("Edit role request - User ID: $userId, New Role: $newRole");
            error_log("Edit role URL: " . USER_SERVICE_URL . '/' . $userId . '/role');
            
            $response = makeApiCall(USER_SERVICE_URL . '/' . $userId . '/role', 'PATCH', 
                                   ['role' => $newRole], $_SESSION['token']);
            
            // Debug: Log response
            error_log("Edit role response: " . json_encode($response));
            
            if ($response['status_code'] === 200) {
                header('Location: users.php?success=User role updated successfully');
                exit();
            } else {
                $error = 'Failed to update role: ' . ($response['data']['error'] ?? 'Unknown error') . ' (Status: ' . $response['status_code'] . ')';
            }
        } elseif ($submittedAction === 'deactivate') {
            $userId = $_POST['user_id'];
            
            $response = makeApiCall(USER_SERVICE_URL . '/' . $userId, 'DELETE', null, $_SESSION['token']);
            
            if ($response['status_code'] === 204) {
                header('Location: users.php?success=User deactivated successfully');
                exit();
            } else {
                $error = 'Failed to deactivate user: ' . ($response['data']['error'] ?? 'Unknown error');
            }
        }
    } else {
        $error = 'Invalid CSRF token';
    }
}

// Get users list
$token = $_SESSION['token'];

// Debug: Check if token exists
if (!$token) {
    $error = 'No authentication token found. Please login again.';
    header('Location: login.php');
    exit();
}

$response = makeApiCall(USER_SERVICE_URL, 'GET', null, $token);
$users = $response['status_code'] === 200 ? $response['data'] : [];

// Success message
$success = $_GET['success'] ?? '';
$userId = $_GET['id'] ?? null;

// Handle view action
if ($action === 'view' && $userId) {
    // Call user-service API to get user by ID
    $apiUrl = USER_SERVICE_URL . '/' . $userId;
    echo "<!-- DEBUG: API URL: $apiUrl -->";
    echo "<!-- DEBUG: Token exists: " . (isset($_SESSION['token']) ? 'YES' : 'NO') . " -->";
    
    $userResponse = makeApiCall($apiUrl, 'GET', null, $token);
    
    // Debug: Add logging
    error_log("View user API call: " . $apiUrl);
    error_log("View user response: " . json_encode($userResponse));
    
    // Show debug info on page
    if (isset($_GET['debug'])) {
        echo "<pre>Debug Info:\n";
        echo "API URL: $apiUrl\n";
        echo "Token: " . substr($token, 0, 20) . "...\n";
        echo "Response: " . json_encode($userResponse, JSON_PRETTY_PRINT);
        echo "</pre>";
    }
    
    if ($userResponse['status_code'] === 200) {
        $selectedUser = $userResponse['data'];
    } else {
        $error = 'User not found. API Response: ' . json_encode($userResponse);
    }
}

// Set page content based on action
if ($action === 'view' && isset($selectedUser)) {
    $pageContent = '
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 text-gray-800">User Details</h1>
            <a href="users.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Users
            </a>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-person"></i> User Information
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>User ID:</strong> ' . htmlspecialchars($selectedUser['id']) . '</p>
                        <p><strong>Email:</strong> ' . htmlspecialchars($selectedUser['email']) . '</p>
                        <p><strong>Role:</strong> <span class="badge bg-primary">' . htmlspecialchars($selectedUser['role']) . '</span></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Created:</strong> ' . date('M d, Y H:i', strtotime($selectedUser['createdAt'])) . '</p>
                        <p><strong>Last Updated:</strong> ' . date('M d, Y H:i', strtotime($selectedUser['updatedAt'])) . '</p>
                    </div>
                </div>
                
                <div class="mt-4">
                    <button class="btn btn-warning me-2" onclick="editRole(\'' . $selectedUser['id'] . '\', \'' . $selectedUser['role'] . '\')">
                        <i class="bi bi-pencil"></i> Edit Role
                    </button>
                    <button class="btn btn-info me-2" onclick="resetPassword(\'' . $selectedUser['id'] . '\')">
                        <i class="bi bi-key"></i> Reset Password
                    </button>
                    <button class="btn btn-danger" onclick="deactivateUser(\'' . $selectedUser['id'] . '\', \'' . $selectedUser['email'] . '\')">
                        <i class="bi bi-person-x"></i> Deactivate User
                    </button>
                </div>
            </div>
        </div>
    </div>';
} elseif ($action === 'add') {
    $pageContent = '
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 text-gray-800">Add New User</h1>
            <a href="users.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Users
            </a>
        </div>

        ' . (isset($error) ? '<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> ' . htmlspecialchars($error) . '</div>' : '') . '

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-person-plus"></i> Create New User Account
                </h6>
            </div>
            <div class="card-body">
                <form method="POST" action="users.php?action=add">
                    <input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" placeholder="user@hospital.com" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Choose user role...</option>
                                    <option value="ADMIN">👑 Administrator</option>
                                    <option value="DOCTOR">👨‍⚕️ Doctor</option>
                                    <option value="NURSE">👩‍⚕️ Nurse</option>
                                    <option value="RECEPTIONIST">📋 Receptionist</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                        <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                        <small class="form-text text-muted">Minimum 6 characters</small>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-plus"></i> Create User
                        </button>
                        <a href="users.php" class="btn btn-secondary">
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
            <h1 class="h3 text-gray-800">User Management</h1>
            <a href="users.php?action=add" class="btn btn-primary">
                <i class="bi bi-plus"></i> Add User
            </a>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-people"></i> System Users
                </h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Created Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>';

    if (empty($users)) {
        $pageContent .= '<tr><td colspan="5" class="text-center text-muted">No users found.</td></tr>';
    } else {
        foreach ($users as $u) {
            $roleClass = 'bg-primary';
            switch($u['role']) {
                case 'ADMIN': $roleClass = 'bg-danger'; break;
                case 'DOCTOR': $roleClass = 'bg-success'; break;
                case 'NURSE': $roleClass = 'bg-info'; break;
                case 'RECEPTIONIST': $roleClass = 'bg-warning text-dark'; break;
            }
            
            $pageContent .= '<tr>
                <td>' . htmlspecialchars($u['id']) . '</td>
                <td>' . htmlspecialchars($u['email']) . '</td>
                <td><span class="badge ' . $roleClass . '">' . htmlspecialchars($u['role']) . '</span></td>
                <td>' . date('M d, Y', strtotime($u['createdAt'])) . '</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" title="View Details" onclick="viewUser(\'' . $u['id'] . '\')">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-warning" title="Edit Role" onclick="editRole(\'' . $u['id'] . '\', \'' . $u['role'] . '\')">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-info" title="Reset Password" onclick="resetPassword(\'' . $u['id'] . '\')">
                            <i class="bi bi-key"></i>
                        </button>
                        <button class="btn btn-outline-danger" title="Deactivate" onclick="deactivateUser(\'' . $u['id'] . '\', \'' . $u['email'] . '\')">
                            <i class="bi bi-person-x"></i>
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
    </div>';
}

// Add modals and JavaScript for user actions
$pageContent .= '
<!-- Edit Role Modal -->
<div class="modal fade" id="editRoleModal" tabindex="-1" aria-labelledby="editRoleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editRoleModalLabel">Edit User Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editRoleForm">
                <div class="modal-body">
                    <input type="hidden" id="editUserId" name="user_id">
                    <input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '">
                    
                    <div class="mb-3">
                        <label for="newRole" class="form-label">New Role</label>
                        <select class="form-select" id="newRole" name="role" required>
                            <option value="">Select Role</option>
                            <option value="ADMIN">👑 Administrator</option>
                            <option value="DOCTOR">👨‍⚕️ Doctor</option>
                            <option value="NURSE">👩‍⚕️ Nurse</option>
                            <option value="RECEPTIONIST">📋 Receptionist</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Role</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Deactivate Confirmation Modal -->
<div class="modal fade" id="deactivateModal" tabindex="-1" aria-labelledby="deactivateModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deactivateModalLabel">Deactivate User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to deactivate user <strong id="deactivateUserEmail"></strong>?</p>
                <p class="text-warning">This action cannot be undone and the user will lose access to the system.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirmDeactivate">Deactivate User</button>
            </div>
        </div>
    </div>
</div>

<script>
// User action functions
function viewUser(userId) {
    window.location.href = "users.php?action=view&id=" + userId;
}

function editRole(userId, currentRole) {
    document.getElementById("editUserId").value = userId;
    document.getElementById("newRole").value = currentRole;
    
    var modal = new bootstrap.Modal(document.getElementById("editRoleModal"));
    modal.show();
}

function resetPassword(userId) {
    alert("Reset Password feature is currently under development. Please contact system administrator to reset user passwords manually.");
}

function deactivateUser(userId, userEmail) {
    document.getElementById("deactivateUserEmail").textContent = userEmail;
    
    var modal = new bootstrap.Modal(document.getElementById("deactivateModal"));
    modal.show();
    
    // Set up confirmation button
    document.getElementById("confirmDeactivate").onclick = function() {
        // Create form and submit
        var form = document.createElement("form");
        form.method = "POST";
        form.action = "users.php";
        
        var actionInput = document.createElement("input");
        actionInput.type = "hidden";
        actionInput.name = "action";
        actionInput.value = "deactivate";
        form.appendChild(actionInput);
        
        var userIdInput = document.createElement("input");
        userIdInput.type = "hidden";
        userIdInput.name = "user_id";
        userIdInput.value = userId;
        form.appendChild(userIdInput);
        
        var csrfInput = document.createElement("input");
        csrfInput.type = "hidden";
        csrfInput.name = "csrf_token";
        csrfInput.value = "' . $_SESSION['csrf_token'] . '";
        form.appendChild(csrfInput);
        
        document.body.appendChild(form);
        form.submit();
    };
}

// Handle edit role form submission
document.getElementById("editRoleForm").addEventListener("submit", function(e) {
    e.preventDefault();
    
    var formData = new FormData(this);
    formData.append("action", "edit_role");
    
    // Create form and submit
    var form = document.createElement("form");
    form.method = "POST";
    form.action = "users.php";
    
    for (var pair of formData.entries()) {
        var input = document.createElement("input");
        input.type = "hidden";
        input.name = pair[0];
        input.value = pair[1];
        form.appendChild(input);
    }
    
    document.body.appendChild(form);
    form.submit();
});
</script>';

// Include layout
include 'includes/layout.php';
?>
