<?php
// Language system for multilingual support
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

function getCurrentLanguage() {
	if (isset($_SESSION['language'])) {
		return $_SESSION['language'];
	}
	// Default language
	return 'vi';
}

function setLanguage($lang) {
	$_SESSION['language'] = $lang;
}

function csrf_field() {
	if (!isset($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
	return '<input type="hidden" name="csrf_token" value="' . $_SESSION['csrf_token'] . '" />';
}

function __($key) {
	$lang = getCurrentLanguage();
	$translations = [
		'vi' => [
			'login' => 'Đăng nhập',
			'logout' => 'Đăng xuất',
			'profile' => 'Hồ sơ',
			'settings' => 'Cài đặt',
			'dashboard' => 'Bảng điều khiển',
			'patients' => 'Bệnh nhân',
			'appointments' => 'Cuộc hẹn',
			'prescriptions' => 'Đơn thuốc',
			'users' => 'Người dùng',
			'reports' => 'Báo cáo',
		],
		'en' => [
			'login' => 'Login',
			'logout' => 'Logout',
			'profile' => 'Profile',
			'settings' => 'Settings',
			'dashboard' => 'Dashboard',
			'patients' => 'Patients',
			'appointments' => 'Appointments',
			'prescriptions' => 'Prescriptions',
			'users' => 'Users',
			'reports' => 'Reports',
		]
	];
	return $translations[$lang][$key] ?? $key;
}
