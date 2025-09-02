<?php
require_once 'includes/config.php';
require_once 'includes/language.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = __('error_invalid_csrf');
    } else {
        $email = sanitize($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $error = __('error_fill_all_fields');
        } else {
            // Call login API
            $response = makeApiCall(USER_SERVICE_URL . '/login', 'POST', [
                'email' => $email,
                'password' => $password
            ]);

            // Debug: Show detailed response
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                error_log("Login API Response: " . json_encode($response));
            }

            if ($response['status_code'] === 200 && isset($response['data']['token'])) {
                // Login successful, but we need to get user data
                $_SESSION['token'] = $response['data']['token'];

                // Get user data from token or make another API call
                // For now, we'll decode the JWT to get basic info
                $tokenParts = explode('.', $response['data']['token']);
                if (count($tokenParts) === 3) {
                    $payload = json_decode(base64_decode($tokenParts[1]), true);
                    if ($payload && isset($payload['id'])) {
                        // Create basic user session data
                        $_SESSION['user'] = [
                            'id' => $payload['id'],
                            'role' => $payload['role'] ?? 'USER',
                            'fullName' => 'User', // Will be updated when we implement profile
                            'email' => $email
                        ];
                        $_SESSION['login_time'] = time();

                        // Flash success message
                        $_SESSION['flash_message'] = [
                            'message' => __('welcome_back'),
                            'type' => 'success'
                        ];

                        // Redirect to dashboard
                        header('Location: dashboard.php');
                        exit();
                    }
                }

                // Fallback if JWT decode fails
                $_SESSION['user'] = [
                    'id' => 'unknown',
                    'role' => 'USER',
                    'fullName' => 'User',
                    'email' => $email
                ];
                $_SESSION['login_time'] = time();

                header('Location: dashboard.php');
                exit();
            } else {
                // More detailed error handling
                $errorMessage = __('login_failed');
                if ($response['status_code'] === 0) {
                    $errorMessage = __('connection_failed') . ': ' . ($response['error'] ?: __('network_error'));
                } elseif ($response['status_code'] === 401) {
                    $errorMessage = __('error_invalid_credentials');
                } elseif (isset($response['data']['error'])) {
                    $errorMessage = $response['data']['error'];
                } elseif (isset($response['data']['message'])) {
                    $errorMessage = $response['data']['message'];
                } else {
                    $errorMessage = sprintf(__('server_error_status'), $response['status_code']);
                }

                // Debug info
                if (defined('DEBUG_MODE') && DEBUG_MODE) {
                    $errorMessage .= '<br><small>' . addslashes(__('debug_info')) . ': ' . json_encode($response) . '</small>';
                }

                $error = $errorMessage;
            }
        }
    }
}

$pageTitle = __('login');
?>
<!DOCTYPE html>
<html lang="<?php echo getCurrentLanguage(); ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - <?php echo __('hospital_subtitle'); ?></title>

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        :root {
            --primary-color: #2c5aa0;
            --secondary-color: #e8f4fd;
            --gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        body {
            background: var(--gradient);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .login-container {
            max-width: 400px;
            margin: 0 auto;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, #4a73b8 100%);
            color: white;
            text-align: center;
            padding: 2rem;
        }

        .login-header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .login-header p {
            opacity: 0.9;
            margin: 0;
        }

        .login-body {
            padding: 2rem;
        }

        .form-control {
            border-radius: 15px;
            border: 2px solid #e9ecef;
            padding: 15px 20px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 0.25rem rgba(44, 90, 160, 0.15);
        }

        .input-group .form-control {
            border-right: none;
        }

        .input-group-text {
            background: white;
            border: 2px solid #e9ecef;
            border-left: none;
            border-radius: 0 15px 15px 0;
            color: var(--primary-color);
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary-color) 0%, #4a73b8 100%);
            border: none;
            border-radius: 15px;
            padding: 15px;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(44, 90, 160, 0.3);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .alert {
            border-radius: 15px;
            border: none;
            margin-bottom: 1.5rem;
        }

        .demo-credentials {
            background: rgba(108, 117, 125, 0.1);
            border-radius: 15px;
            padding: 1rem;
            margin-top: 1.5rem;
        }

        .demo-credentials h6 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .demo-credentials small {
            display: block;
            margin-bottom: 0.25rem;
        }

        .floating-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            z-index: -1;
        }

        .shape {
            position: absolute;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            animation: float 6s ease-in-out infinite;
        }

        .shape:nth-child(1) {
            width: 80px;
            height: 80px;
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }

        .shape:nth-child(2) {
            width: 120px;
            height: 120px;
            top: 20%;
            right: 10%;
            animation-delay: 2s;
        }

        .shape:nth-child(3) {
            width: 60px;
            height: 60px;
            bottom: 20%;
            left: 20%;
            animation-delay: 4s;
        }

        .shape:nth-child(4) {
            width: 100px;
            height: 100px;
            bottom: 10%;
            right: 20%;
            animation-delay: 1s;
        }

        @keyframes float {

            0%,
            100% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-20px);
            }
        }

        /* Loading spinner overlay */
        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.9);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
    </style>
</head>

<body>
    <!-- Floating Background Shapes -->
    <div class="floating-shapes">
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
        <div class="shape"></div>
    </div>
    <!-- Language Switcher Top Right -->
    <div style="position: fixed; top: 24px; right: 32px; z-index: 2000;">
        <?php include __DIR__ . '/includes/language-switcher.php'; ?>
    </div>
    <!-- Loading Spinner -->
    <div class="spinner-overlay" id="loadingSpinner">
        <div class="spinner-border text-primary" style="width: 3rem; height: 3rem;" role="status">
            <span class="visually-hidden"><?php echo __('loading'); ?></span>
        </div>
    </div>

    <div class="container">
        <div class="login-container">
            <div class="card login-card">
                <!-- Header -->
                <div class="login-header">
                    <i class="bi bi-hospital" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                    <h1><?php echo __('site_short'); ?></h1>
                    <p><?php echo __('hospital_subtitle'); ?></p>
                </div>

                <!-- Body -->
                <div class="login-body">
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

                    <form method="POST" id="loginForm">
                        <input type="hidden" name="csrf_token" value="<?php echo getCsrfToken(); ?>">

                        <!-- Email -->
                        <div class="mb-3">
                            <label for="email" class="form-label"><?php echo __('email_address'); ?></label>
                            <div class="input-group">
                                <input type="email" class="form-control" id="email" name="email"
                                    placeholder="<?php echo __('enter_email'); ?>"
                                    value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>"
                                    required>
                                <span class="input-group-text">
                                    <i class="bi bi-envelope"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Password -->
                        <div class="mb-3">
                            <label for="password" class="form-label"><?php echo __('password'); ?></label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="password" name="password"
                                    placeholder="<?php echo __('enter_password'); ?>" required>
                                <span class="input-group-text" id="togglePassword" style="cursor: pointer;">
                                    <i class="bi bi-eye" id="toggleIcon"></i>
                                </span>
                            </div>
                        </div>

                        <!-- Remember Me -->
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" id="rememberMe" name="remember_me">
                            <label class="form-check-label" for="rememberMe">
                                <?php echo __('remember_me'); ?>
                            </label>
                        </div>

                        <!-- Login Button -->
                        <button type="submit" class="btn btn-primary btn-login">
                            <i class="bi bi-box-arrow-in-right"></i>
                            <?php echo __('login'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center mt-3">
                <small class="text-white">
                    © 2024 <?php echo __('hospital_subtitle'); ?>. <?php echo __('all_rights_reserved'); ?>
                </small>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Password toggle functionality
        document.getElementById('togglePassword').addEventListener('click', function () {
            const passwordField = document.getElementById('password');
            const toggleIcon = document.getElementById('toggleIcon');

            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleIcon.className = 'bi bi-eye-slash';
            } else {
                passwordField.type = 'password';
                toggleIcon.className = 'bi bi-eye';
            }
        });

        // Form submission with loading
        document.getElementById('loginForm').addEventListener('submit', function () {
            document.getElementById('loadingSpinner').style.display = 'flex';
        });

        // Demo credential buttons
        function fillDemoCredentials(role) {
            const credentials = {
                'admin': { email: 'admin@hospital.com', password: 'admin123' },
                'doctor': { email: 'doctor@hospital.com', password: 'doctor123' },
                'nurse': { email: 'nurse@hospital.com', password: 'nurse123' },
                'receptionist': { email: 'receptionist@hospital.com', password: 'reception123' }
            };

            if (credentials[role]) {
                document.getElementById('email').value = credentials[role].email;
                document.getElementById('password').value = credentials[role].password;
            }
        }

        // Add click handlers to demo credentials
        document.addEventListener('DOMContentLoaded', function () {
            const demoCredentials = document.querySelectorAll('.demo-credentials small');
            demoCredentials.forEach(function (element, index) {
                element.style.cursor = 'pointer';
                element.addEventListener('click', function () {
                    const roles = ['admin', 'doctor', 'nurse', 'receptionist'];
                    fillDemoCredentials(roles[index]);
                });
            });
        });

        // Enter key in email field focuses password
        document.getElementById('email').addEventListener('keypress', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('password').focus();
            }
        });
    </script>
</body>

</html>