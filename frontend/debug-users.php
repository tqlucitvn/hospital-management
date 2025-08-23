<?php
require_once 'includes/config.php';

// Require Admin access only for testing
requireRole('ADMIN');

$token = $_SESSION['token'];

echo "<h1>🧪 User Management API Debug</h1>";
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h3>Debug Mode: " . (defined('DEBUG_MODE') && DEBUG_MODE ? 'ON' : 'OFF') . "</h3>";
echo "<p><strong>Current User:</strong> " . htmlspecialchars($_SESSION['user']['email'] ?? 'Unknown') . "</p>";
echo "<p><strong>User Role:</strong> " . htmlspecialchars($_SESSION['user']['role'] ?? 'Unknown') . "</p>";
echo "<p><strong>Token:</strong> " . htmlspecialchars(substr($token, 0, 20)) . "...</p>";
echo "</div>";

echo "<hr>";

// Test 1: Get all users
echo "<h2>🔍 Test 1: Get All Users</h2>";
echo "<p>Testing: <code>GET " . USER_SERVICE_URL . "</code></p>";

$response = makeApiCall(USER_SERVICE_URL, 'GET', null, $token);

echo "<div style='background: #e9ecef; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>Response Status: " . $response['status_code'] . "</h4>";
echo "<h4>Response Data:</h4>";
echo "<pre>" . json_encode($response['data'], JSON_PRETTY_PRINT) . "</pre>";
echo "</div>";

if ($response['status_code'] === 200 && !empty($response['data'])) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>✅ SUCCESS: Found " . count($response['data']) . " users</h4>";
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr style='background: #007bff; color: white;'>";
    echo "<th style='padding: 10px;'>ID</th>";
    echo "<th style='padding: 10px;'>Email</th>";
    echo "<th style='padding: 10px;'>Role</th>";
    echo "<th style='padding: 10px;'>Created At</th>";
    echo "</tr>";
    
    foreach ($response['data'] as $user) {
        echo "<tr>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($user['id']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td style='padding: 8px;'>" . htmlspecialchars($user['role']) . "</td>";
        echo "<td style='padding: 8px;'>" . date('Y-m-d H:i:s', strtotime($user['createdAt'])) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>❌ FAILED: Could not fetch users</h4>";
    echo "</div>";
}

echo "<hr>";

// Test 2: Create a test user
echo "<h2>➕ Test 2: Create Test User</h2>";
echo "<p>Testing: <code>POST " . USER_SERVICE_URL . "/register</code></p>";

$testUserData = [
    'email' => 'test.nurse.' . time() . '@hospital.com',
    'password' => 'password123',
    'role' => 'NURSE'
];

echo "<div style='background: #e9ecef; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>Request Data:</h4>";
echo "<pre>" . json_encode($testUserData, JSON_PRETTY_PRINT) . "</pre>";
echo "</div>";

$response = makeApiCall(USER_SERVICE_URL . '/register', 'POST', $testUserData, $token);

echo "<div style='background: #e9ecef; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<h4>Response Status: " . $response['status_code'] . "</h4>";
echo "<h4>Response Data:</h4>";
echo "<pre>" . json_encode($response['data'], JSON_PRETTY_PRINT) . "</pre>";
echo "</div>";

if ($response['status_code'] === 201) {
    echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>✅ SUCCESS: User created successfully!</h4>";
    $createdUserId = $response['data']['id'] ?? null;
    echo "<p><strong>Created User ID:</strong> " . $createdUserId . "</p>";
    echo "</div>";
    
    // Test 3: Update user role
    if ($createdUserId) {
        echo "<hr>";
        echo "<h2>✏️ Test 3: Update User Role</h2>";
        echo "<p>Testing: <code>PATCH " . USER_SERVICE_URL . "/" . $createdUserId . "/role</code></p>";
        
        $roleUpdateData = ['role' => 'DOCTOR'];
        
        echo "<div style='background: #e9ecef; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>Request Data:</h4>";
        echo "<pre>" . json_encode($roleUpdateData, JSON_PRETTY_PRINT) . "</pre>";
        echo "</div>";
        
        $response = makeApiCall(USER_SERVICE_URL . '/' . $createdUserId . '/role', 'PATCH', $roleUpdateData, $token);
        
        echo "<div style='background: #e9ecef; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
        echo "<h4>Response Status: " . $response['status_code'] . "</h4>";
        echo "<h4>Response Data:</h4>";
        echo "<pre>" . json_encode($response['data'], JSON_PRETTY_PRINT) . "</pre>";
        echo "</div>";
        
        if ($response['status_code'] === 200) {
            echo "<div style='background: #d4edda; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h4>✅ SUCCESS: User role updated successfully!</h4>";
            echo "<p><strong>New Role:</strong> " . $response['data']['role'] . "</p>";
            echo "</div>";
        } else {
            echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
            echo "<h4>❌ FAILED: Could not update user role</h4>";
            echo "</div>";
        }
    }
} else {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>❌ FAILED: Could not create user</h4>";
    echo "<p>Error: " . ($response['data']['error'] ?? 'Unknown error') . "</p>";
    echo "</div>";
}

echo "<hr>";

// Test 4: API Endpoints summary
echo "<h2>📋 API Endpoints Summary</h2>";
echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h4>Available User Service Endpoints:</h4>";
echo "<ul>";
echo "<li><code>GET " . USER_SERVICE_URL . "</code> - List all users (Admin only)</li>";
echo "<li><code>POST " . USER_SERVICE_URL . "/register</code> - Create new user (Admin only)</li>";
echo "<li><code>POST " . USER_SERVICE_URL . "/login</code> - User login (Public)</li>";
echo "<li><code>PATCH " . USER_SERVICE_URL . "/{id}/role</code> - Update user role (Admin only)</li>";
echo "</ul>";

echo "<h4>Role Types:</h4>";
echo "<ul>";
echo "<li><span style='background: #dc3545; color: white; padding: 2px 8px; border-radius: 3px;'>ADMIN</span> - Full system access</li>";
echo "<li><span style='background: #007bff; color: white; padding: 2px 8px; border-radius: 3px;'>DOCTOR</span> - Medical staff</li>";
echo "<li><span style='background: #28a745; color: white; padding: 2px 8px; border-radius: 3px;'>NURSE</span> - Nursing staff</li>";
echo "<li><span style='background: #17a2b8; color: white; padding: 2px 8px; border-radius: 3px;'>RECEPTIONIST</span> - Front desk</li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p><a href='users.php' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🔗 Go to User Management</a></p>";
echo "<p><a href='dashboard.php' style='background: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🏠 Back to Dashboard</a></p>";
?>
