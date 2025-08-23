<?php
require_once 'includes/config.php';
requireRole('ADMIN');

$pageTitle = 'User Management';
$user = getCurrentUser();
$action = $_GET['action'] ?? 'list';

// Handle form submission for adding user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'add') {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $userData = [
            'email' => sanitize($_POST['email']),
            'password' => $_POST['password'],
            'role' => $_POST['role']
        ];
        
        if (!empty($userData['email']) && !empty($userData['password']) && !empty($userData['role'])) {
            $response = makeApiCall(USER_SERVICE_URL . '/register', 'POST', $userData, $_SESSION['token']);
            
            if ($response['status_code'] === 201) {
                header('Location: users.php?success=User created successfully');
                exit();
            } else {
                $error = 'Failed to create user: ' . ($response['data']['error'] ?? 'Unknown error');
            }
        } else {
            $error = 'All fields are required.';
        }
    } else {
        $error = 'Invalid CSRF token.';
    }
}

// Get users list
$token = $_SESSION['token'];
$response = makeApiCall(USER_SERVICE_URL, 'GET', null, $token);
$users = $response['status_code'] === 200 ? $response['data'] : [];

// Success message
$success = $_GET['success'] ?? '';

// Set page content based on action
if ($action === 'add') {
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
                        <button class="btn btn-outline-primary" title="View Details">
                            <i class="bi bi-eye"></i>
                        </button>
                        <button class="btn btn-outline-warning" title="Edit Role">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-info" title="Reset Password">
                            <i class="bi bi-key"></i>
                        </button>
                        <button class="btn btn-outline-danger" title="Deactivate">
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

// Include layout
include 'includes/layout.php';
?>
