<?php
/**
 * Demo Data Setup Script
 * Tạo demo users và dữ liệu mẫu cho hệ thống
 */

require_once 'includes/config.php';

// Demo users data
$demoUsers = [
    [
        'email' => 'admin@hospital.com',
        'password' => 'admin123',
        'fullName' => 'System Administrator',
        'role' => 'ADMIN',
        'phone' => '0123456789',
        'address' => 'Hospital Administration Office'
    ],
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

$success = [];
$errors = [];

echo "<!DOCTYPE html>
<html lang='vi'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Demo Data Setup</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css' rel='stylesheet'>
</head>
<body class='bg-light'>
    <div class='container mt-5'>
        <div class='row justify-content-center'>
            <div class='col-md-8'>
                <div class='card shadow'>
                    <div class='card-header bg-primary text-white'>
                        <h4 class='mb-0'><i class='bi bi-database'></i> Demo Data Setup</h4>
                    </div>
                    <div class='card-body'>";

// Create demo users
foreach ($demoUsers as $userData) {
    echo "<div class='mb-3'>";
    echo "<h6><i class='bi bi-person-plus'></i> Creating user: {$userData['fullName']} ({$userData['role']})</h6>";
    
    $response = makeApiCall(USER_SERVICE_URL . '/auth/register', 'POST', $userData);
    
    if ($response['status_code'] === 201) {
        $success[] = $userData['fullName'];
        echo "<div class='alert alert-success'>";
        echo "<i class='bi bi-check-circle'></i> ✅ User created successfully!<br>";
        echo "<strong>Email:</strong> {$userData['email']}<br>";
        echo "<strong>Password:</strong> {$userData['password']}<br>";
        echo "<strong>Role:</strong> {$userData['role']}";
        echo "</div>";
    } else {
        $errorMsg = handleApiError($response) ?: 'Unknown error';
        $errors[] = $userData['fullName'] . ': ' . $errorMsg;
        echo "<div class='alert alert-warning'>";
        echo "<i class='bi bi-exclamation-triangle'></i> ⚠️ {$errorMsg}<br>";
        echo "<small>User might already exist or there's a server issue.</small>";
        echo "</div>";
    }
    echo "</div>";
}

// Demo patients data
$demoPatients = [
    [
        'fullName' => 'Nguyễn Văn An',
        'email' => 'nguyenvanan@email.com',
        'phone' => '0901234567',
        'dateOfBirth' => '1990-05-15',
        'gender' => 'MALE',
        'address' => '123 Nguyễn Huệ, Quận 1, TP.HCM',
        'emergencyContact' => '0901234568',
        'medicalHistory' => 'No known allergies'
    ],
    [
        'fullName' => 'Trần Thị Bình',
        'email' => 'tranthibinh@email.com',
        'phone' => '0901234569',
        'dateOfBirth' => '1985-08-22',
        'gender' => 'FEMALE',
        'address' => '456 Lê Lợi, Quận 3, TP.HCM',
        'emergencyContact' => '0901234570',
        'medicalHistory' => 'Diabetes Type 2'
    ],
    [
        'fullName' => 'Lê Minh Cường',
        'email' => 'leminhcuong@email.com',
        'phone' => '0901234571',
        'dateOfBirth' => '1978-12-03',
        'gender' => 'MALE',
        'address' => '789 Pasteur, Quận 1, TP.HCM',
        'emergencyContact' => '0901234572',
        'medicalHistory' => 'Hypertension'
    ]
];

echo "<hr><h5><i class='bi bi-people'></i> Creating Demo Patients</h5>";

// Get admin token for creating patients
$adminLoginResponse = makeApiCall(USER_SERVICE_URL . '/auth/login', 'POST', [
    'email' => 'admin@hospital.com',
    'password' => 'admin123'
]);

if ($adminLoginResponse['status_code'] === 200) {
    $adminToken = $adminLoginResponse['data']['token'];
    
    foreach ($demoPatients as $patientData) {
        echo "<div class='mb-3'>";
        echo "<h6><i class='bi bi-person-heart'></i> Creating patient: {$patientData['fullName']}</h6>";
        
        $response = makeApiCall(PATIENT_SERVICE_URL . '/patients', 'POST', $patientData, $adminToken);
        
        if ($response['status_code'] === 201) {
            echo "<div class='alert alert-success'>";
            echo "<i class='bi bi-check-circle'></i> ✅ Patient created successfully!<br>";
            echo "<strong>Name:</strong> {$patientData['fullName']}<br>";
            echo "<strong>Phone:</strong> {$patientData['phone']}";
            echo "</div>";
        } else {
            $errorMsg = handleApiError($response) ?: 'Unknown error';
            echo "<div class='alert alert-warning'>";
            echo "<i class='bi bi-exclamation-triangle'></i> ⚠️ {$errorMsg}";
            echo "</div>";
        }
        echo "</div>";
    }
} else {
    echo "<div class='alert alert-danger'>";
    echo "<i class='bi bi-x-circle'></i> Could not login as admin to create patients.";
    echo "</div>";
}

echo "                </div>
                    <div class='card-footer'>
                        <h5><i class='bi bi-info-circle'></i> Setup Summary</h5>";

if (!empty($success)) {
    echo "<div class='alert alert-success'>";
    echo "<strong>✅ Successfully created " . count($success) . " users:</strong><br>";
    echo implode(', ', $success);
    echo "</div>";
}

if (!empty($errors)) {
    echo "<div class='alert alert-warning'>";
    echo "<strong>⚠️ Issues with " . count($errors) . " users:</strong><br>";
    foreach ($errors as $error) {
        echo "• " . $error . "<br>";
    }
    echo "</div>";
}

echo "                        <div class='text-center'>
                            <a href='login.php' class='btn btn-primary'>
                                <i class='bi bi-box-arrow-in-right'></i> Go to Login Page
                            </a>
                            <a href='dashboard.php' class='btn btn-success ms-2'>
                                <i class='bi bi-speedometer2'></i> Go to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>";
?>
