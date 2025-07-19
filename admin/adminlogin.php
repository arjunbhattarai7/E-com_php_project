<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    // CSRF check
    if (!isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        $_SESSION['admin_login_error'] = "Security error. Please try again.";
        header("Location: ../index.php");
        exit;
    }

    // Validate inputs
    if (empty($email) || empty($password)) {
        $_SESSION['admin_login_error'] = "All fields are required.";
        header("Location: ../index.php");
        exit;
    }

    // Hardcoded admin credentials (in production, use database with hashed passwords)
    $admin_email = 'admin@bookhaven.com';
    $admin_password = 'securepassword123';

    if ($email === $admin_email && $password === $admin_password) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id'] = 1;
        unset($_SESSION['admin_login_error']);
        header("Location: admin.php");
        exit;
    } else {
        $_SESSION['admin_login_error'] = "Invalid admin credentials.";
        header("Location: ../index.php");
        exit;
    }
} else {
    header("Location: ../index.php");
    exit;
}