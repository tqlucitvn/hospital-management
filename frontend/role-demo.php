<?php
require_once 'includes/config.php';

$pageTitle = 'Role Permission Demo';
$user = getCurrentUser();

// Start output buffering for page content
ob_start();
?>

<div class="row mb-4">
    <div class="col-12">
        <h1>🔐 Role-Based Access Control Demo</h1>
        <p class="lead">Demonstrating different user roles and their permissions</p>
    </div>
</div>

<?php if (isLoggedIn()): ?>
<!-- Current User Info -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-person-circle"></i> Current User Session</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Email:</strong> <?php echo sanitize($user['email']); ?></p>
                        <p><strong>Role:</strong> 
                            <span class="badge bg-primary"><?php echo getRoleDisplayName($user['role']); ?></span>
                        </p>
                        <p><strong>User ID:</strong> <?php echo sanitize($user['id']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Login Time:</strong> <?php echo isset($_SESSION['login_time']) ? date('Y-m-d H:i:s', $_SESSION['login_time']) : 'N/A'; ?></p>
                        <p><strong>Session Token:</strong> 
                            <small class="text-muted"><?php echo substr($_SESSION['token'] ?? 'N/A', 0, 50) . '...'; ?></small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Role Permissions Matrix -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-shield-check"></i> Permission Matrix</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>Feature</th>
                                <th class="text-center">👑 Admin</th>
                                <th class="text-center">👨‍⚕️ Doctor</th>
                                <th class="text-center">👩‍⚕️ Nurse</th>
                                <th class="text-center">📋 Receptionist</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><i class="bi bi-speedometer2"></i> Dashboard</td>
                                <td class="text-center">✅</td>
                                <td class="text-center">✅</td>
                                <td class="text-center">✅</td>
                                <td class="text-center">✅</td>
                            </tr>
                            <tr>
                                <td><i class="bi bi-people"></i> Patient Management</td>
                                <td class="text-center">✅</td>
                                <td class="text-center">✅</td>
                                <td class="text-center">✅</td>
                                <td class="text-center">✅</td>
                            </tr>
                            <tr>
                                <td><i class="bi bi-calendar-check"></i> Appointments</td>
                                <td class="text-center">✅</td>
                                <td class="text-center">✅</td>
                                <td class="text-center">❌</td>
                                <td class="text-center">✅</td>
                            </tr>
                            <tr>
                                <td><i class="bi bi-prescription2"></i> Prescriptions</td>
                                <td class="text-center">✅</td>
                                <td class="text-center">✅</td>
                                <td class="text-center">✅</td>
                                <td class="text-center">❌</td>
                            </tr>
                            <tr>
                                <td><i class="bi bi-person-gear"></i> User Management</td>
                                <td class="text-center">✅</td>
                                <td class="text-center">❌</td>
                                <td class="text-center">❌</td>
                                <td class="text-center">❌</td>
                            </tr>
                            <tr>
                                <td><i class="bi bi-graph-up"></i> Reports & Analytics</td>
                                <td class="text-center">✅</td>
                                <td class="text-center">❌</td>
                                <td class="text-center">❌</td>
                                <td class="text-center">❌</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Permission Tests -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-check2-square"></i> Your Current Permissions</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>✅ You CAN access:</h6>
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-speedometer2"></i> Dashboard</span>
                                <span class="badge bg-success">Allowed</span>
                            </li>
                            
                            <?php if (hasAnyRole(['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST'])): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-people"></i> Patient Management</span>
                                <span class="badge bg-success">Allowed</span>
                            </li>
                            <?php endif; ?>
                            
                            <?php if (hasAnyRole(['ADMIN', 'RECEPTIONIST', 'DOCTOR'])): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-calendar-check"></i> Appointments</span>
                                <span class="badge bg-success">Allowed</span>
                            </li>
                            <?php endif; ?>
                            
                            <?php if (hasAnyRole(['ADMIN', 'DOCTOR', 'NURSE'])): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-prescription2"></i> Prescriptions</span>
                                <span class="badge bg-success">Allowed</span>
                            </li>
                            <?php endif; ?>
                            
                            <?php if (hasRole('ADMIN')): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-person-gear"></i> User Management</span>
                                <span class="badge bg-success">Allowed</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-graph-up"></i> Reports</span>
                                <span class="badge bg-success">Allowed</span>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    
                    <div class="col-md-6">
                        <h6>❌ You CANNOT access:</h6>
                        <ul class="list-group list-group-flush">
                            <?php if (!hasAnyRole(['ADMIN', 'RECEPTIONIST', 'DOCTOR'])): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-calendar-check"></i> Appointments</span>
                                <span class="badge bg-danger">Denied</span>
                            </li>
                            <?php endif; ?>
                            
                            <?php if (!hasAnyRole(['ADMIN', 'DOCTOR', 'NURSE'])): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-prescription2"></i> Prescriptions</span>
                                <span class="badge bg-danger">Denied</span>
                            </li>
                            <?php endif; ?>
                            
                            <?php if (!hasRole('ADMIN')): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-person-gear"></i> User Management</span>
                                <span class="badge bg-danger">Denied</span>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-graph-up"></i> Reports</span>
                                <span class="badge bg-danger">Denied</span>
                            </li>
                            <?php endif; ?>
                            
                            <?php if (hasRole('ADMIN')): ?>
                            <li class="list-group-item">
                                <em class="text-muted">As Admin, you have access to all features!</em>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Test Different Roles -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5><i class="bi bi-arrow-repeat"></i> Test Different Roles</h5>
            </div>
            <div class="card-body">
                <p>To test different role permissions, logout and login with these demo accounts:</p>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">👑 Administrator</h6>
                                <p class="card-text">
                                    <strong>Email:</strong> admin@hospital.com<br>
                                    <strong>Password:</strong> admin123<br>
                                    <small class="text-muted">Full system access</small>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">👨‍⚕️ Doctor</h6>
                                <p class="card-text">
                                    <strong>Email:</strong> doctor@hospital.com<br>
                                    <strong>Password:</strong> doctor123<br>
                                    <small class="text-muted">Medical operations</small>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">👩‍⚕️ Nurse</h6>
                                <p class="card-text">
                                    <strong>Email:</strong> nurse@hospital.com<br>
                                    <strong>Password:</strong> nurse123<br>
                                    <small class="text-muted">Patient care & prescriptions</small>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <div class="card bg-light">
                            <div class="card-body">
                                <h6 class="card-title">📋 Receptionist</h6>
                                <p class="card-text">
                                    <strong>Email:</strong> receptionist@hospital.com<br>
                                    <strong>Password:</strong> reception123<br>
                                    <small class="text-muted">Front desk operations</small>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <a href="logout.php?token=<?php echo getCsrfToken(); ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-box-arrow-right"></i> Logout to Test Other Roles
                    </a>
                    <a href="test-api.php" class="btn btn-outline-primary ms-2">
                        <i class="bi bi-gear"></i> Create Demo Users
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
