<?php
session_start();
if (isset($_SESSION['role'])) {
    $role = $_SESSION['role'];
    if ($role === 'admin') header('Location: /CampusConnect/admin/dashboard.php');
    elseif ($role === 'company') header('Location: /CampusConnect/company/');
    else header('Location: /CampusConnect/student/');
    exit;
}
require __DIR__ . '/home.php';
