<?php
require_once 'includes/config.php';
requireRole('ADMIN');

$pageTitle = 'User Management';
$user = getCurrentUser();
$action = $_GET['action'] ?? 'list';

// Simple content without complex buffering
if ($action === 'add') {
    // Add user form content
    $content = '
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 text-gray-800">Add New User</h1>
            <a href="users-simple.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Users
            </a>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="bi bi-person-plus"></i> Create New User Account
                </h6>
                <small class="text-muted">All fields marked with * are required</small>
            </div>
            <div class="card-body">
                <form method="POST" action="users-simple.php?action=add">
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
                                <label for="fullName" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="fullName" name="fullName" placeholder="Dr. John Smith" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
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
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" minlength="6" required>
                                <small class="form-text text-muted">Minimum 6 characters</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-plus"></i> Create User
                        </button>
                        <a href="users-simple.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    ';
} else {
    // List users content
    $token = $_SESSION['token'];
    $response = makeApiCall(USER_SERVICE_URL, 'GET', null, $token);
    $users = $response['status_code'] === 200 ? $response['data'] : [];
    
    $content = '
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 text-gray-800">User Management</h1>
            <a href="users-simple.php?action=add" class="btn btn-primary">
                <i class="bi bi-plus"></i> Add User
            </a>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">System Users</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Created Date</th>
                            </tr>
                        </thead>
                        <tbody>';
    
    if (empty($users)) {
        $content .= '<tr><td colspan="4" class="text-center">No users found.</td></tr>';
    } else {
        foreach ($users as $u) {
            $content .= '<tr>
                <td>' . htmlspecialchars($u['id']) . '</td>
                <td>' . htmlspecialchars($u['email']) . '</td>
                <td><span class="badge bg-primary">' . htmlspecialchars($u['role']) . '</span></td>
                <td>' . date('M d, Y', strtotime($u['createdAt'])) . '</td>
            </tr>';
        }
    }
    
    $content .= '
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    ';
}

// Handle form submission
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
                header('Location: users-simple.php?success=1');
                exit();
            } else {
                $error = 'Failed to create user: ' . ($response['data']['error'] ?? 'Unknown error');
                $content = '<div class="alert alert-danger">' . $error . '</div>' . $content;
            }
        } else {
            $content = '<div class="alert alert-danger">All fields are required.</div>' . $content;
        }
    }
}

// Check for success message
if (isset($_GET['success'])) {
    $content = '<div class="alert alert-success">User created successfully!</div>' . $content;
}

// Set page content and include layout
$pageContent = $content;
include 'includes/layout.php';
?>
