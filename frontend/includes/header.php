<?php
/**
 * Common Header Layout với hỗ trợ đa ngôn ngữ
 */

// Đảm bảo config đã được load
if (!function_exists('getCurrentUser')) {
    require_once __DIR__ . '/config.php';
}
require_once __DIR__ . '/language.php';

$user = getCurrentUser();
$currentLanguage = getCurrentLanguage();

// Fetch real user data from API for display
$realUserData = $user;
if (isset($user['id']) && function_exists('makeApiCall')) {
    $token = $_SESSION['token'] ?? '';
    if (!empty($token)) {
        $userResponse = makeApiCall(USER_SERVICE_URL . '/me', 'GET', null, $token);
        if ($userResponse['status_code'] === 200 && isset($userResponse['data'])) {
            $realUserData = $userResponse['data'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?php echo $currentLanguage; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?><?php echo __('hospital_subtitle'); ?></title>
    
    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        :root {
            --primary-color: #2c5aa0;
            --secondary-color: #e8f4fd;
            --sidebar-width: 280px;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        .sidebar {
            background: linear-gradient(135deg, var(--primary-color) 0%, #4a73b8 100%);
            min-height: 100vh;
            width: var(--sidebar-width);
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            transition: all 0.3s ease;
        }
        
        .sidebar .brand {
            padding: 1.5rem;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar .brand h3 {
            color: white;
            margin: 0;
            font-weight: 600;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            padding: 12px 20px;
            border-radius: 0;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar .nav-link i {
            width: 20px;
            margin-right: 10px;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            transition: all 0.3s ease;
        }
        
        .top-nav {
            background: white;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 1rem 1.5rem;
            margin-bottom: 2rem;
        }
        
        .language-switcher {
            margin-left: auto;
        }
        
        .user-menu .dropdown-toggle::after {
            display: none;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
    
    <?php if (isset($additionalCSS)): ?>
        <?php echo $additionalCSS; ?>
    <?php endif; ?>
</head>
<body>
    <!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="brand">
            <i class="bi bi-hospital" style="font-size: 2rem; color: white; margin-bottom: 0.5rem;"></i>
            <h3><?php echo __('site_short'); ?></h3>
            <small style="color: rgba(255, 255, 255, 0.7);"><?php echo __('hospital_subtitle'); ?></small>
        </div>
        
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'active' : ''; ?>" 
                   href="dashboard.php">
                    <i class="bi bi-speedometer2"></i>
                    <?php echo __('dashboard'); ?>
                </a>
            </li>
            
            <?php if (hasAnyRole(['ADMIN', 'DOCTOR', 'NURSE', 'RECEPTIONIST'])): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'patients.php' ? 'active' : ''; ?>" 
                   href="patients.php">
                    <i class="bi bi-people"></i>
                    <?php echo __('patients'); ?>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasAnyRole(['ADMIN', 'DOCTOR', 'RECEPTIONIST'])): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'appointments.php' ? 'active' : ''; ?>" 
                   href="appointments.php">
                    <i class="bi bi-calendar-check"></i>
                    <?php echo __('appointments'); ?>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasAnyRole(['ADMIN', 'DOCTOR'])): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'prescriptions.php' ? 'active' : ''; ?>" 
                   href="prescriptions.php">
                    <i class="bi bi-capsule"></i>
                    <?php echo __('prescriptions'); ?>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasRole('ADMIN')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>" 
                   href="users.php">
                    <i class="bi bi-person-badge"></i>
                    <?php echo __('users'); ?>
                </a>
            </li>
            <?php endif; ?>
            
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>" 
                   href="reports.php">
                    <i class="bi bi-bar-chart"></i>
                    <?php echo __('reports'); ?>
                </a>
            </li>
            
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'notifications.php' ? 'active' : ''; ?>" 
                   href="notifications.php">
                    <i class="bi bi-bell"></i>
                    <?php echo __('notifications'); ?>
                </a>
            </li>
            
            <?php if (hasRole('ADMIN')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'system-status.php' ? 'active' : ''; ?>" 
                   href="system-status.php">
                    <i class="bi bi-activity"></i>
                    <?php echo __('system_status'); ?>
                </a>
            </li>
            <?php endif; ?>
            
            <?php if (hasRole('ADMIN')): ?>
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>" 
                   href="settings.php">
                    <i class="bi bi-gear"></i>
                    <?php echo __('settings'); ?>
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <nav class="top-nav d-flex align-items-center">
            <button class="btn btn-outline-secondary d-md-none" id="sidebarToggle">
                <i class="bi bi-list"></i>
            </button>
            
            <h4 class="mb-0 ms-3 ms-md-0"><?php echo isset($pageTitle) ? $pageTitle : __('dashboard'); ?></h4>
            
            <div class="ms-auto d-flex align-items-center">
                <!-- Language Switcher -->
                <div class="me-3">
                    <?php include __DIR__ . '/language-switcher.php'; ?>
                </div>
                
                <!-- Notifications -->
                <div class="dropdown me-3">
                    <button class="btn btn-outline-secondary position-relative" type="button" 
                            id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-bell"></i>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                            3
                            <span class="visually-hidden"><?php echo __('unread_messages'); ?></span>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown">
                        <li><h6 class="dropdown-header"><?php echo __('notifications'); ?></h6></li>
                        <li><a class="dropdown-item" href="#"><?php echo __('sample_notification_1'); ?></a></li>
                        <li><a class="dropdown-item" href="#"><?php echo __('sample_notification_2'); ?></a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="notifications.php"><?php echo __('view_all'); ?></a></li>
                    </ul>
                </div>
                
                <!-- User Menu -->
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle user-menu" type="button" 
                            id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-person-circle"></i>
                        <?php echo htmlspecialchars($realUserData['fullName'] ?? $realUserData['email'] ?? __('user')); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userMenuDropdown">
                        <li>
                            <a class="dropdown-item" href="profile.php">
                                <i class="bi bi-person"></i>
                                <?php echo __('profile'); ?>
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="logout.php">
                                <i class="bi bi-box-arrow-right"></i>
                                <?php echo __('logout'); ?>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        
        <!-- Page Content -->
        <div class="container-fluid px-4">
            <?php if (isset($_SESSION['flash_message'])): ?>
                <?php
                    // Normalize flash message: accept string or ['message'=>..., 'type'=>...]
                    $__flash = $_SESSION['flash_message'];
                    $__flash_type = $_SESSION['flash_type'] ?? null;
                    if (is_array($__flash)) {
                        $flash_message_text = $__flash['message'] ?? '';
                        $flash_message_type = $__flash['type'] ?? $__flash_type ?? 'info';
                    } else {
                        $flash_message_text = $__flash;
                        $flash_message_type = $__flash_type ?? 'info';
                    }
                ?>
                <div class="alert alert-<?php echo htmlspecialchars($flash_message_type); ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($flash_message_text); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo htmlspecialchars(__('close')); ?>"></button>
                </div>
                <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
            <?php endif; ?>
