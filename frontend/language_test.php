<?php
require_once 'includes/config.php';
require_once 'includes/language.php';

$currentLang = getCurrentLanguage();
$testKey = 'issued';

echo "<h1>Language Test</h1>";
echo "<p>Current Language: $currentLang</p>";
echo "<p>Test Key '$testKey': " . __($testKey) . "</p>";
echo "<p>Session Language: " . ($_SESSION['language'] ?? 'NOT SET') . "</p>";

echo "<br><a href='?lang=vi'>Switch to Vietnamese</a> | <a href='?lang=en'>Switch to English</a>";

if (isset($_GET['lang'])) {
    $lang = $_GET['lang'];
    if (in_array($lang, ['vi', 'en'])) {
        setLanguage($lang);
        echo "<p>Language set to: $lang</p>";
        echo "<script>window.location.reload();</script>";
    }
}
?></content>
<parameter name="filePath">d:\workspaces\HCMUS\hospital-managemnent\backend\frontend\language_test.php
