<?php
require_once 'includes/config.php';
requireAuth();

$token = $_SESSION['token'];

echo "<h1>Patient Service Debug</h1>";
echo "<style>body { font-family: Arial; padding: 20px; } .debug { background: #f5f5f5; padding: 10px; margin: 10px 0; border-radius: 5px; }</style>";

// Test 1: Get all patients
echo "<h2>1. Test Get All Patients</h2>";
$response = makeApiCall(PATIENT_SERVICE_URL, 'GET', null, $token);
echo "<div class='debug'>";
echo "<strong>URL:</strong> " . PATIENT_SERVICE_URL . "<br>";
echo "<strong>Status Code:</strong> " . $response['status_code'] . "<br>";
echo "<strong>Response:</strong> <pre>" . json_encode($response['data'], JSON_PRETTY_PRINT) . "</pre>";
echo "</div>";

// Test 2: Get patient stats
echo "<h2>2. Test Get Patient Stats</h2>";
$response = makeApiCall(PATIENT_SERVICE_URL . '/stats', 'GET', null, $token);
echo "<div class='debug'>";
echo "<strong>URL:</strong> " . PATIENT_SERVICE_URL . "/stats<br>";
echo "<strong>Status Code:</strong> " . $response['status_code'] . "<br>";
echo "<strong>Response:</strong> <pre>" . json_encode($response['data'], JSON_PRETTY_PRINT) . "</pre>";
echo "</div>";

// Test 3: Test patient creation
if ($_POST) {
    echo "<h2>3. Test Patient Creation</h2>";
    $testPatient = [
        'fullName' => 'Test Patient Debug',
        'email' => 'testdebug@email.com',
        'phone' => '0901234567',
        'dateOfBirth' => '1990-01-01',
        'gender' => 'MALE',
        'address' => 'Test Address',
        'emergencyContact' => '0901234568',
        'medicalHistory' => 'Test medical history'
    ];
    
    $response = makeApiCall(PATIENT_SERVICE_URL, 'POST', $testPatient, $token);
    echo "<div class='debug'>";
    echo "<strong>URL:</strong> " . PATIENT_SERVICE_URL . "<br>";
    echo "<strong>Data Sent:</strong> <pre>" . json_encode($testPatient, JSON_PRETTY_PRINT) . "</pre>";
    echo "<strong>Status Code:</strong> " . $response['status_code'] . "<br>";
    echo "<strong>Response:</strong> <pre>" . json_encode($response['data'], JSON_PRETTY_PRINT) . "</pre>";
    echo "</div>";
}

// Test 4: Check backend endpoints
echo "<h2>4. Backend Service Status</h2>";
$services = [
    'Patient Service' => PATIENT_SERVICE_URL,
    'User Service' => USER_SERVICE_URL,
    'Appointment Service' => APPOINTMENT_SERVICE_URL,
    'Prescription Service' => PRESCRIPTION_SERVICE_URL
];

foreach ($services as $name => $url) {
    $response = makeApiCall($url, 'GET', null, $token);
    echo "<div class='debug'>";
    echo "<strong>$name:</strong> $url<br>";
    echo "<strong>Status:</strong> " . $response['status_code'] . " ";
    if ($response['status_code'] === 200) {
        echo "<span style='color: green;'>✅ OK</span>";
    } elseif ($response['status_code'] === 401) {
        echo "<span style='color: orange;'>🔒 Auth Required (Normal)</span>";
    } else {
        echo "<span style='color: red;'>❌ Error</span>";
    }
    echo "</div>";
}

?>

<h2>5. Test Patient Creation</h2>
<form method="POST">
    <button type="submit" style="padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 5px;">
        Create Test Patient
    </button>
</form>

<p><a href="patients.php">← Back to Patients</a> | <a href="dashboard.php">← Back to Dashboard</a></p>
