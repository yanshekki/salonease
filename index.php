<?php
/**
 * SalonEase - 入口
 * 自動導向登入或 Dashboard
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['staff_id'])) {
    header('Location: /dashboard.php');
} else {
    header('Location: /login.php');
}
exit;
