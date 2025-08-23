<?php
require_once 'includes/config.php';

echo "<h1>Simple Login Test</h1>";
echo "<style>body { font-family: Arial; padding: 20px; }</style>";

if ($_POST) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    echo "<h2>Attempting login...</h2>";
    echo "<p>Email: $email</p>";
    echo "<p>Password: [hidden]</p>";
    
    $response = makeApiCall(USER_SERVICE_URL . '/login', 'POST', [
        'email' => $email,
        'password' => $password
    ]);
    
    echo "<h3>API Response:</h3>";
    echo "<pre>" . json_encode($response, JSON_PRETTY_PRINT) . "</pre>";
    
    if ($response['status_code'] === 200) {
        echo "<h3 style='color: green;'>✅ Login Successful!</h3>";
        $_SESSION['token'] = $response['data']['token'];
        echo "<p><a href='dashboard.php'>Go to Dashboard</a></p>";
    } else {
        echo "<h3 style='color: red;'>❌ Login Failed</h3>";
    }
}
?>

<form method="POST">
    <h2>Test Login</h2>
    <p>
        <label>Email:</label><br>
        <input type="email" name="email" value="admin@hospital.com" required style="width: 300px; padding: 8px;">
    </p>
    <p>
        <label>Password:</label><br>
        <input type="password" name="password" value="admin123" required style="width: 300px; padding: 8px;">
    </p>
    <p>
        <button type="submit" style="padding: 10px 20px;">Test Login</button>
    </p>
</form>

<p><a href="login.php">Back to Login Page</a></p>
