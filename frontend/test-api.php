<?php
require_once 'includes/config.php';

echo "<h1>API Connection Test</h1>";
echo "<style>body { font-family: Arial; padding: 20px; } .success { color: green; } .error { color: red; }</style>";

// Test user service health (no /health endpoint, so test a simple endpoint)
echo "<h2>Testing User Service (localhost:3002/api/users)</h2>";
$response = makeApiCall(USER_SERVICE_URL, 'GET');
echo "<p>Status Code: " . $response['status_code'] . "</p>";
echo "<p>Response: " . json_encode($response['data']) . "</p>";
echo "<p>Error: " . ($response['error'] ?: 'None') . "</p>";

// Test patient service
echo "<h2>Testing Patient Service (localhost:3001/api/patients)</h2>";
$response = makeApiCall(PATIENT_SERVICE_URL, 'GET');
echo "<p>Status Code: " . $response['status_code'] . "</p>";
echo "<p>Response: " . json_encode($response['data']) . "</p>";
echo "<p>Error: " . ($response['error'] ?: 'None') . "</p>";

// Test appointment service
echo "<h2>Testing Appointment Service (localhost:3003/api/appointments)</h2>";
$response = makeApiCall(APPOINTMENT_SERVICE_URL, 'GET');
echo "<p>Status Code: " . $response['status_code'] . "</p>";
echo "<p>Response: " . json_encode($response['data']) . "</p>";
echo "<p>Error: " . ($response['error'] ?: 'None') . "</p>";

// Test prescription service
echo "<h2>Testing Prescription Service (localhost:3005/api/prescriptions)</h2>";
$response = makeApiCall(PRESCRIPTION_SERVICE_URL, 'GET');
echo "<p>Status Code: " . $response['status_code'] . "</p>";
echo "<p>Response: " . json_encode($response['data']) . "</p>";
echo "<p>Error: " . ($response['error'] ?: 'None') . "</p>";

echo "<hr>";
echo "<h2>Test Registration (Create Demo Users)</h2>";

// Create additional demo users
$demoUsers = [
    [
        'email' => 'doctor@hospital.com',
        'password' => 'doctor123',
        'fullName' => 'Dr. John Smith',
        'role' => 'DOCTOR',
        'phone' => '0123456790',
        'address' => 'Medical Department'
    ],
    [
        'email' => 'nurse@hospital.com',
        'password' => 'nurse123',
        'fullName' => 'Nurse Mary Johnson',
        'role' => 'NURSE',
        'phone' => '0123456791',
        'address' => 'Nursing Department'
    ],
    [
        'email' => 'receptionist@hospital.com',
        'password' => 'reception123',
        'fullName' => 'Sarah Wilson',
        'role' => 'RECEPTIONIST',
        'phone' => '0123456792',
        'address' => 'Front Desk'
    ]
];

foreach ($demoUsers as $userData) {
    $registerResponse = makeApiCall(USER_SERVICE_URL . '/register', 'POST', $userData);
    echo "<p><strong>{$userData['fullName']} ({$userData['role']}):</strong> ";
    if ($registerResponse['status_code'] === 201) {
        echo "<span class='success'>✅ Created successfully</span>";
    } else {
        echo "<span class='error'>⚠️ " . (handleApiError($registerResponse) ?: 'May already exist') . "</span>";
    }
    echo "</p>";
}

$registerResponse = makeApiCall(USER_SERVICE_URL . '/register', 'POST', [
    'email' => 'admin@hospital.com',
    'password' => 'admin123',
    'fullName' => 'System Administrator',
    'role' => 'ADMIN',
    'phone' => '0123456789',
    'address' => 'Hospital Administration'
]);
echo "<p><strong>Admin User:</strong> ";
if ($registerResponse['status_code'] === 201) {
    echo "<span class='success'>✅ Created successfully</span>";
} else {
    echo "<span class='error'>⚠️ " . (handleApiError($registerResponse) ?: 'May already exist') . "</span>";
}
echo "</p>";

echo "<hr>";
echo "<h2>Test Login</h2>";
$loginResponse = makeApiCall(USER_SERVICE_URL . '/login', 'POST', [
    'email' => 'admin@hospital.com',
    'password' => 'admin123'
]);
echo "<p>Login Status Code: " . $loginResponse['status_code'] . "</p>";
echo "<p>Login Response: " . json_encode($loginResponse['data']) . "</p>";
echo "<p>Login Error: " . ($loginResponse['error'] ?: 'None') . "</p>";

echo "<br><a href='login.php'>Back to Login</a>";
?>
