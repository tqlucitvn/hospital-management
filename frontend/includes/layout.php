<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo __('hospital_subtitle'); ?></title>

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #2c5aa0;
            --secondary-color: #e8f4fd;
            --success-color: #198754;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #0dcaf0;
            --dark-color: #212529;
            --light-color: #f8f9fa;
        }

        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-top: 76px;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color) 0%, #4a73b8 100%);
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1000;
            height: 76px;
        }

        .navbar-brand {
            font-weight: 700;
            font-size: 1.5rem;
        }

        .sidebar {
            min-height: calc(100vh - 76px);
            background: white;
            border-right: 1px solid #dee2e6;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            position: fixed;
            top: 76px;
            left: 0;
            width: 250px;
            z-index: 100;
            overflow-y: auto;
        }

        .sidebar .nav-link {
            color: #495057;
            padding: 12px 20px;
            border-radius: 0;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .sidebar .nav-link:hover {
            background-color: var(--secondary-color);
            color: var(--primary-color);
            border-left: 3px solid var(--primary-color);
        }

        .sidebar .nav-link.active {
            background-color: var(--primary-color);
            color: white;
            border-left: 3px solid #fff;
        }

        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }

        .main-content {
            padding: 20px;
            margin-left: 250px;
            min-height: calc(100vh - 76px);
        }

        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.08);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .card-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #4a73b8 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            font-weight: 600;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, #4a73b8 100%);
            border: none;
            border-radius: 10px;
            padding: 10px 20px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 5px 15px rgba(44, 90, 160, 0.3);
        }

        .table {
            overflow: hidden;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
        }

        .table thead th {
            background: linear-gradient(135deg, var(--primary-color) 0%, #4a73b8 100%);
            color: white;
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }

        .table tbody tr {
            transition: background-color 0.3s ease;
        }

        .table tbody tr:hover {
            background-color: var(--secondary-color);
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            border: 2px solid #e9ecef;
            padding: 12px 15px;
            transition: all 0.3s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(44, 90, 160, 0.15);
        }

        .alert {
            border-radius: 10px;
            border: none;
            padding: 15px 20px;
        }

        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #4a73b8 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 500;
        }

        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px;
            padding: 20px;
            text-align: center;
        }

        .stats-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stats-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .sidebar {
                position: fixed;
                left: -250px;
                width: 250px;
                z-index: 1000;
                transition: left 0.3s ease;
            }

            .sidebar.show {
                left: 0;
            }

            .main-content {
                margin-left: 0 !important;
            }

            .mobile-menu-toggle {
                display: block !important;
            }
        }

        .mobile-menu-toggle {
            display: none;
        }

        /* Loading spinner */
        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.8);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }

        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-color);
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #1e4176;
        }
    </style>

    <?php if (isset($additionalCSS)): ?>
        <?php echo $additionalCSS; ?>
    <?php endif; ?>
</head>

<body>
    <!-- Loading Spinner -->
    <div class="spinner-overlay" id="loadingSpinner">
            <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden"><?php echo __('loading'); ?></span>
        </div>
    </div>

    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <!-- Mobile menu toggle -->
            <button class="navbar-toggler d-lg-none" type="button" id="sidebarToggle">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Brand -->
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-hospital"></i>
                <?php echo __('site_short'); ?>
            </a>

            <!-- Right side menu -->
            <div class="navbar-nav ms-auto">
                <!-- Notifications -->
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="notifications.php" id="notificationDropdown" role="button"
                        data-bs-toggle="dropdown">
                        <i class="bi bi-bell"></i>
                        <span class="badge bg-danger" id="notificationCount" style="display: none;">0</span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <h6 class="dropdown-header"><?php echo __('recent_notifications'); ?></h6>
                        </li>
                        <li><a class="dropdown-item" href="notifications.php">
                                <i class="bi bi-calendar-check text-success"></i>
                                <?php echo __('new_appointment_scheduled'); ?>
                            </a></li>
                        <li><a class="dropdown-item" href="notifications.php">
                                <i class="bi bi-prescription2 text-info"></i>
                                <?php echo __('prescription_ready'); ?>
                            </a></li>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item text-center" href="notifications.php">
                                <i class="bi bi-bell"></i> <?php echo __('view_all_notifications'); ?>
                            </a></li>
                    </ul>
                </div>

                <!-- Language Switcher -->
                <?php include __DIR__ . '/language-switcher.php'; ?>

                <!-- User menu -->
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
                        data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                        <?php echo htmlspecialchars(getCurrentUser()['fullName'] ?? __('user')); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="profile.php"><i class="bi bi-person"></i> <?php echo __('profile'); ?></a></li>
                        <?php if (hasRole('ADMIN')): ?>
                        <li><a class="dropdown-item" href="settings.php"><i class="bi bi-gear"></i> <?php echo __('settings'); ?></a></li>
                        <?php endif; ?>
                        <li>
                            <hr class="dropdown-divider">
                        </li>
                        <li><a class="dropdown-item" href="logout.php"><i class="bi bi-box-arrow-right"></i> <?php echo __('logout'); ?></a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="d-flex">
        <!-- Sidebar -->
        <nav class="sidebar" id="sidebar">
            <div class="position-sticky pt-3">
                <ul class="nav flex-column">
                    <!-- Dashboard -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>"
                            href="dashboard.php">
                            <i class="bi bi-speedometer2"></i>
                            <?php echo __('dashboard'); ?>
                        </a>
                    </li>

                    <?php if (hasAnyRole(['ADMIN', 'RECEPTIONIST', 'DOCTOR', 'NURSE'])): ?>
                        <!-- Patients -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'patients.php' ? 'active' : ''; ?>"
                                href="patients.php">
                                <i class="bi bi-people"></i>
                                <?php echo __('patients'); ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (hasAnyRole(['ADMIN', 'RECEPTIONIST', 'DOCTOR'])): ?>
                        <!-- Appointments -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'appointments.php' ? 'active' : ''; ?>"
                                href="appointments.php">
                                <i class="bi bi-calendar-check"></i>
                                <?php echo __('appointments'); ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (hasAnyRole(['ADMIN', 'DOCTOR', 'NURSE'])): ?>
                        <!-- Prescriptions -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'prescriptions.php' ? 'active' : ''; ?>"
                                href="prescriptions.php">
                                <i class="bi bi-prescription2"></i>
                                <?php echo __('prescriptions'); ?>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (hasRole('ADMIN')): ?>
                        <!-- User Management (Admin only) -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>"
                                href="users.php">
                                <i class="bi bi-person-gear"></i>
                                <?php echo __('user_management'); ?>
                            </a>
                        </li>

                        <!-- Reports -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>"
                                href="reports.php">
                                <i class="bi bi-graph-up"></i>
                                <?php echo __('reports'); ?>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>

                <!-- Quick Actions -->
                <div class="mt-4 px-3">
                    <h6 class="text-muted text-uppercase fw-bold"><?php echo __('quick_actions'); ?></h6>

                    <?php if (hasAnyRole(['ADMIN', 'RECEPTIONIST'])): ?>
                        <button class="btn btn-primary btn-sm w-100 mb-2" onclick="quickAddPatient()">
                            <i class="bi bi-person-plus"></i> <?php echo __('add_patient'); ?>
                        </button>
                    <?php endif; ?>

                    <?php if (hasAnyRole(['ADMIN', 'RECEPTIONIST', 'DOCTOR'])): ?>
                        <button class="btn btn-success btn-sm w-100 mb-2" onclick="quickAddAppointment()">
                            <i class="bi bi-calendar-plus"></i> <?php echo __('new_appointment'); ?>
                        </button>
                    <?php endif; ?>

                    <?php if (hasAnyRole(['ADMIN', 'DOCTOR'])): ?>
                        <button class="btn btn-info btn-sm w-100" onclick="quickAddPrescription()">
                            <i class="bi bi-prescription"></i> <?php echo __('new_prescription'); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </nav>

        <!-- Main content -->
        <main class="main-content flex-grow-1">
            <?php
            // Display any flash messages
            if (isset($_SESSION['flash_message'])) {
                showAlert($_SESSION['flash_message']['message'], $_SESSION['flash_message']['type']);
                unset($_SESSION['flash_message']);
            }

            // Display any error messages from URL
            if (isset($_GET['error'])) {
                $errorMessage = match ($_GET['error']) {
                    'access_denied' => __('access_denied'),
                    'session_expired' => __('session_expired'),
                    'invalid_request' => __('invalid_request'),
                    default => __('operation_failed')
                };
                showAlert($errorMessage, 'danger');
            }

            // Display any success messages from URL
            if (isset($_GET['success'])) {
                $successMessage = match ($_GET['success']) {
                    'updated' => __('record_updated_success'),
                    'created' => __('record_created_success'),
                    'deleted' => __('record_deleted_success'),
                    default => __('operation_successful')
                };
                showAlert($successMessage, 'success');
            }
            ?>

            <!-- Page content will be inserted here -->
            <?php if (isset($pageContent)): ?>
                <?php echo $pageContent; ?>
            <?php endif; ?>
        </main>
    </div>

    <!-- Bootstrap 5.3 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom JavaScript -->
    <script>
        // Mobile sidebar toggle
        document.getElementById('sidebarToggle').addEventListener('click', function () {
            document.getElementById('sidebar').classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function (event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.getElementById('sidebarToggle');

            if (window.innerWidth <= 768 &&
                !sidebar.contains(event.target) &&
                !toggle.contains(event.target)) {
                sidebar.classList.remove('show');
            }
        });


        // Mobile sidebar toggle
        document.addEventListener('DOMContentLoaded', function () {
            const sidebarToggle = document.getElementById('sidebarToggle');
            const sidebar = document.getElementById('sidebar');

            if (sidebarToggle && sidebar) {
                sidebarToggle.addEventListener('click', function () {
                    sidebar.classList.toggle('show');
                });

                // Close sidebar when clicking outside on mobile
                document.addEventListener('click', function (e) {
                    if (window.innerWidth <= 992) {
                        if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                            sidebar.classList.remove('show');
                        }
                    }
                });
            }
        });

        // Show/hide loading spinner
        function showLoading() {
            document.getElementById('loadingSpinner').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loadingSpinner').style.display = 'none';
        }

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function () {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function (alert) {
                setTimeout(function () {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });

        // Quick action functions (to be implemented)
        function quickAddPatient() {
            window.location.href = 'patients.php?action=add';
        }

        function quickAddAppointment() {
            window.location.href = 'appointments.php?action=add';
        }

        function quickAddPrescription() {
            window.location.href = 'prescriptions.php?action=add';
        }

        // Notification checking (placeholder)
        function checkNotifications() {
            // TODO: Implement real-time notification checking
            // This would typically use WebSocket or periodic AJAX calls
        }

        // Initialize notifications check
        setInterval(checkNotifications, 30000); // Check every 30 seconds
    </script>

    <?php if (isset($additionalJS)): ?>
        <?php echo $additionalJS; ?>
    <?php endif; ?>
</body>

</html>