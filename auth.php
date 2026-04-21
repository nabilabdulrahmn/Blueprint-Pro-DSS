<?php
// DeKukis — Session Guard
// Include this file at the top of any page that requires login.
session_start();

if (!isset($_SESSION['user_id']) || !$_SESSION['user_id']) {
    header("Location: login.php");
    exit;
}

$loggedInUserId = $_SESSION['user_id'];
$loggedInUserName = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
$loggedInFullName = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : '';
