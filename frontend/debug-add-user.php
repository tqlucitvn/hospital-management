<?php
require_once 'includes/config.php';
requireRole('ADMIN');

$pageTitle = 'Add User Debug';
$user = getCurrentUser();

ob_start();
?>

<div class="container-fluid">
    <h1>🧪 Add User Form Debug</h1>
    
    <div class="alert alert-info">
        <h4>Testing Add User Form Structure</h4>
        <p>This page tests if the Add User form is rendering correctly.</p>
    </div>
    
    <!-- Simple Add User Form -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">
                <i class="bi bi-person-plus"></i> Create New User Account
            </h6>
        </div>
        <div class="card-body">
            <form method="POST" action="users.php?action=add" class="needs-validation" novalidate>
                <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
                
                <!-- Basic Information -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="email" class="form-label">
                                Email Address <span class="text-danger">*</span>
                            </label>
                            <input type="email" 
                                   class="form-control" 
                                   id="email" 
                                   name="email" 
                                   placeholder="user@hospital.com"
                                   required>
                            <div class="invalid-feedback">
                                Please provide a valid email address.
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="fullName" class="form-label">
                                Full Name <span class="text-danger">*</span>
                            </label>
                            <input type="text" 
                                   class="form-control" 
                                   id="fullName" 
                                   name="fullName" 
                                   placeholder="Dr. John Smith"
                                   required>
                            <div class="invalid-feedback">
                                Please provide the user's full name.
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="role" class="form-label">
                                Role <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="">Choose user role...</option>
                                <option value="ADMIN">👑 Administrator</option>
                                <option value="DOCTOR">👨‍⚕️ Doctor</option>
                                <option value="NURSE">👩‍⚕️ Nurse</option>
                                <option value="RECEPTIONIST">📋 Receptionist</option>
                            </select>
                            <div class="invalid-feedback">
                                Please select a user role.
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="department" class="form-label">
                                Department <span class="text-muted">(Optional)</span>
                            </label>
                            <select class="form-select" id="department" name="department">
                                <option value="">Select department...</option>
                                <option value="EMERGENCY">Emergency</option>
                                <option value="CARDIOLOGY">Cardiology</option>
                                <option value="PEDIATRICS">Pediatrics</option>
                                <option value="SURGERY">Surgery</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="password" class="form-label">
                                Password <span class="text-danger">*</span>
                            </label>
                            <input type="password" 
                                   class="form-control" 
                                   id="password" 
                                   name="password" 
                                   minlength="6" 
                                   required>
                            <div class="invalid-feedback">
                                Password must be at least 6 characters.
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">
                                Confirm Password <span class="text-danger">*</span>
                            </label>
                            <input type="password" 
                                   class="form-control" 
                                   id="confirm_password" 
                                   name="confirm_password" 
                                   minlength="6" 
                                   required>
                            <div class="invalid-feedback">
                                Passwords do not match.
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Form Actions -->
                <div class="d-flex justify-content-between mt-4">
                    <div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-plus"></i> Create User
                        </button>
                        <button type="button" class="btn btn-success" onclick="generateSimplePassword()">
                            <i class="bi bi-key"></i> Generate Password
                        </button>
                    </div>
                    <div>
                        <a href="users.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Debug Info -->
    <div class="card mt-4">
        <div class="card-header">
            <h5>Debug Information</h5>
        </div>
        <div class="card-body">
            <p><strong>Current URL:</strong> <?= $_SERVER['REQUEST_URI'] ?></p>
            <p><strong>Action:</strong> <?= $_GET['action'] ?? 'none' ?></p>
            <p><strong>Bootstrap CSS:</strong> Check if Bootstrap 5 is loading</p>
            <p><strong>Bootstrap Icons:</strong> Check if Bootstrap Icons are loading</p>
        </div>
    </div>
</div>

<script>
function generateSimplePassword() {
    const password = Math.random().toString(36).slice(-8) + '123!';
    document.getElementById('password').value = password;
    document.getElementById('confirm_password').value = password;
    alert('Generated password: ' + password);
}

// Simple form validation
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('.needs-validation');
    
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});
</script>

<?php
$pageContent = ob_get_clean();
include 'includes/layout.php';
?>
