<?php
/**
 * SalonEase - 登出
 */
require_once __DIR__ . '/includes/auth.php';

logout();
header('Location: /login.php');
exit;
