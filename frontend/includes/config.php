<?php
/**
 * Hospital Management System - Core Configuration
 * Cấu hình chính cho hệ thống quản lý bệnh viện
 */

// =============================================================================
// API ENDPOINTS CONFIGURATION
// =============================================================================
define('USER_SERVICE_URL', 'http://localhost:3002/api/users');
define('PATIENT_SERVICE_URL', 'http://localhost:3001/api/patients');
define('APPOINTMENT_SERVICE_URL', 'http://localhost:3003/api/appointments');
define('PRESCRIPTION_SERVICE_URL', 'http://localhost:3005/api/prescriptions');

// =============================================================================
// SESSION & SECURITY CONFIGURATION
// =============================================================================
session_start();

// Generate CSRF token nếu chưa có
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Regenerate session ID để tăng bảo mật
if (!isset($_SESSION['session_started'])) {
    session_regenerate_id(true);
    $_SESSION['session_started'] = time();
}

// =============================================================================
// API HELPER FUNCTIONS
// =============================================================================

/**
 * Thực hiện API call đến backend services
 * @param string $url - URL endpoint
 * @param string $method - HTTP method (GET, POST, PUT, DELETE)
 * @param array|null $data - Dữ liệu gửi đi
 * @param string|null $token - JWT token
 * @return array - Response array với status_code và data
 */
function makeApiCall($url, $method = 'GET', $data = null, $token = null) {
    // Check if curl is available, fallback to file_get_contents
    if (function_exists('curl_init')) {
        return makeApiCallCurl($url, $method, $data, $token);
    } else {
        return makeApiCallFileGetContents($url, $method, $data, $token);
    }
}

/**
 * API call using cURL
 */
function makeApiCallCurl($url, $method = 'GET', $data = null, $token = null) {
    $ch = curl_init();
    
    // Basic cURL configuration
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    // Headers
    $headers = ['Content-Type: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    // Data for POST/PUT requests
    if ($data && in_array($method, ['POST', 'PUT'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Log API calls for debugging (in development)
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log("API Call (cURL): $method $url - Response: $httpCode");
    }
    
    return [
        'status_code' => $httpCode,
        'data' => json_decode($response, true),
        'error' => $error
    ];
}

/**
 * API call using file_get_contents (fallback)
 */
function makeApiCallFileGetContents($url, $method = 'GET', $data = null, $token = null) {
    // Build headers
    $headers = "Content-Type: application/json\r\n";
    if ($token) {
        $headers .= "Authorization: Bearer $token\r\n";
    }
    
    // Build context options
    $options = [
        'http' => [
            'method' => $method,
            'header' => $headers,
            'timeout' => 30,
            'ignore_errors' => true
        ]
    ];
    
    // Add data for POST/PUT requests
    if ($data && in_array($method, ['POST', 'PUT'])) {
        $options['http']['content'] = json_encode($data);
    }
    
    $context = stream_context_create($options);
    
    // Make the request
    $response = @file_get_contents($url, false, $context);
    
    // Get HTTP response code
    $httpCode = 500; // Default error
    if (isset($http_response_header)) {
        foreach ($http_response_header as $header) {
            if (preg_match('/^HTTP\/\d\.\d\s+(\d+)/', $header, $matches)) {
                $httpCode = (int)$matches[1];
                break;
            }
        }
    }
    
    // Handle connection errors
    if ($response === false) {
        $error = error_get_last();
        $errorMessage = $error ? $error['message'] : 'Connection failed';
        
        // Log API calls for debugging (in development)
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("API Call (file_get_contents): $method $url - Error: $errorMessage");
        }
        
        return [
            'status_code' => 0,
            'data' => null,
            'error' => $errorMessage
        ];
    }
    
    // Log API calls for debugging (in development)
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        error_log("API Call (file_get_contents): $method $url - Response: $httpCode");
    }
    
    return [
        'status_code' => $httpCode,
        'data' => json_decode($response, true),
        'error' => null
    ];
}

// =============================================================================
// AUTHENTICATION FUNCTIONS
// =============================================================================

/**
 * Lấy thông tin user hiện tại từ session
 */
function getCurrentUser() {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

/**
 * Kiểm tra user đã đăng nhập chưa
 */
function isLoggedIn() {
    return isset($_SESSION['token']) && isset($_SESSION['user']);
}

/**
 * Kiểm tra user có role cụ thể không
 * @param string $requiredRole - Role cần kiểm tra
 */
function hasRole($requiredRole) {
    $user = getCurrentUser();
    if (!$user) return false;
    return $user['role'] === $requiredRole;
}

/**
 * Kiểm tra user có một trong các roles không
 * @param array $roles - Danh sách roles
 */
function hasAnyRole($roles) {
    $user = getCurrentUser();
    if (!$user) return false;
    return in_array($user['role'], $roles);
}

/**
 * Yêu cầu đăng nhập, redirect nếu chưa đăng nhập
 */
function requireAuth() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit();
    }
}

/**
 * Yêu cầu role cụ thể
 * @param string $role - Role cần thiết
 */
function requireRole($role) {
    requireAuth();
    if (!hasRole($role)) {
        header('Location: dashboard.php?error=access_denied');
        exit();
    }
}

/**
 * Yêu cầu một trong các roles
 * @param array $roles - Danh sách roles
 */
function requireAnyRole($roles) {
    requireAuth();
    if (!hasAnyRole($roles)) {
        header('Location: dashboard.php?error=access_denied');
        exit();
    }
}

/**
 * Đăng xuất và clear session
 */
function logout() {
    // Call logout API nếu có token (optional, backend không có logout endpoint)
    if (isset($_SESSION['token'])) {
        // makeApiCall(USER_SERVICE_URL . '/auth/logout', 'POST', null, $_SESSION['token']);
        // Skip API call since backend doesn't have logout endpoint
    }
    
    session_destroy();
    header('Location: login.php');
    exit();
}

// =============================================================================
// UTILITY FUNCTIONS
// =============================================================================

/**
 * Format ngày tháng cho hiển thị
 * @param string $dateString - ISO date string
 */
function formatDate($dateString) {
    if (!$dateString) return 'N/A';
    return date('d/m/Y H:i', strtotime($dateString));
}

/**
 * Format ngày tháng cho input datetime-local
 * @param string $dateString - ISO date string
 */
function formatDateForInput($dateString) {
    if (!$dateString) return '';
    return date('Y-m-d\TH:i', strtotime($dateString));
}

/**
 * Làm sạch input để tránh XSS
 * @param string $input - Input cần làm sạch
 */
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Lấy CSRF token
 */
function getCsrfToken() {
    return $_SESSION['csrf_token'];
}

/**
 * Tạo/lấy CSRF token
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token - Token cần verify
 */
function verifyCsrfToken($token) {
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Hiển thị alert message
 * @param string $message - Nội dung message
 * @param string $type - Loại alert (success, danger, warning, info)
 */
function showAlert($message, $type = 'info') {
    echo "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>";
    echo '<i class="bi bi-' . getAlertIcon($type) . '"></i> ';
    echo sanitize($message);
    echo '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
    echo '</div>';
}

/**
 * Lấy icon cho alert
 * @param string $type - Loại alert
 */
function getAlertIcon($type) {
    $icons = [
        'success' => 'check-circle',
        'danger' => 'exclamation-triangle',
        'warning' => 'exclamation-triangle',
        'info' => 'info-circle'
    ];
    return $icons[$type] ?? 'info-circle';
}

// =============================================================================
// ROLE & STATUS MAPPINGS
// =============================================================================

/**
 * Lấy tên hiển thị của role
 * @param string $role - Role code
 */
function getRoleDisplayName($role) {
    $roles = [
        'ADMIN' => 'Administrator',
        'DOCTOR' => 'Doctor',
        'NURSE' => 'Nurse',
        'RECEPTIONIST' => 'Receptionist'
    ];
    return $roles[$role] ?? $role;
}

/**
 * Lấy CSS class cho appointment status
 * @param string $status - Status code
 */
function getAppointmentStatusClass($status) {
    $classes = [
        'SCHEDULED' => 'badge bg-primary',
        'CONFIRMED' => 'badge bg-success',
        'CANCELLED' => 'badge bg-danger',
        'COMPLETED' => 'badge bg-info'
    ];
    return $classes[$status] ?? 'badge bg-secondary';
}

/**
 * Lấy CSS class cho prescription status
 * @param string $status - Status code
 */
function getPrescriptionStatusClass($status) {
    $classes = [
        'PENDING' => 'badge bg-warning',
        'DISPENSED' => 'badge bg-success',
        'CANCELLED' => 'badge bg-danger'
    ];
    return $classes[$status] ?? 'badge bg-secondary';
}

// =============================================================================
// ERROR HANDLING
// =============================================================================

/**
 * Xử lý lỗi từ API response
 * @param array $response - API response
 */
function handleApiError($response) {
    if ($response['status_code'] >= 400) {
        // Log error for debugging
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("API Error: " . json_encode($response));
        }
        
        $errorMessage = 'An error occurred';
        if (isset($response['data']['message'])) {
            $errorMessage = $response['data']['message'];
        } elseif (isset($response['data']['error'])) {
            $errorMessage = $response['data']['error'];
        }
        return $errorMessage;
    }
    return null;
}

// =============================================================================
// PAGINATION HELPER
// =============================================================================

/**
 * Tạo pagination HTML
 * @param int $currentPage - Trang hiện tại
 * @param int $totalPages - Tổng số trang
 * @param string $baseUrl - Base URL
 */
function paginate($currentPage, $totalPages, $baseUrl) {
    if ($totalPages <= 1) return '';
    
    $pagination = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous button
    if ($currentPage > 1) {
        $pagination .= '<li class="page-item">';
        $pagination .= '<a class="page-link" href="' . $baseUrl . '&page=' . ($currentPage - 1) . '">';
        $pagination .= '<i class="bi bi-chevron-left"></i> Previous</a></li>';
    }
    
    // Page numbers
    $start = max(1, $currentPage - 2);
    $end = min($totalPages, $currentPage + 2);
    
    if ($start > 1) {
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=1">1</a></li>';
        if ($start > 2) {
            $pagination .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $start; $i <= $end; $i++) {
        $active = $i == $currentPage ? 'active' : '';
        $pagination .= '<li class="page-item ' . $active . '">';
        $pagination .= '<a class="page-link" href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a></li>';
    }
    
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) {
            $pagination .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $pagination .= '<li class="page-item"><a class="page-link" href="' . $baseUrl . '&page=' . $totalPages . '">' . $totalPages . '</a></li>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $pagination .= '<li class="page-item">';
        $pagination .= '<a class="page-link" href="' . $baseUrl . '&page=' . ($currentPage + 1) . '">';
        $pagination .= 'Next <i class="bi bi-chevron-right"></i></a></li>';
    }
    
    $pagination .= '</ul></nav>';
    return $pagination;
}

// =============================================================================
// DEBUG CONFIGURATION
// =============================================================================
// Enable for development debugging
define('DEBUG_MODE', true);

// Enable error reporting for debugging
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
}

?>
