<?php
/**
 * SalonEase 設定檔範例
 * 
 * 使用方法：
 * 1. 複製本檔案為 config.php
 * 2. 填入真實資料庫資訊
 * 3. 切勿將 config.php 加入 git（已在 .gitignore）
 * 
 * 部署到 salonease.ysk.hk 共享主機時，
 * 建議將 config.php 放在 web root 上一層，再用 dirname(__DIR__) 載入
 */

// 資料庫設定（請修改）
define('DB_HOST', 'localhost');
define('DB_NAME', 'salonease');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_CHARSET', 'utf8mb4');

// 應用程式設定
define('APP_NAME', 'SalonEase 美容中心');
define('APP_VERSION', '0.1.0');
define('APP_TIMEZONE', 'Asia/Hong_Kong');

// Session 安全設定（必須在任何 session_start() 之前執行）
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Lax');
}

// 錯誤顯示（上線務必設為 0）
error_reporting(E_ALL);
ini_set('display_errors', 1);   // 開發階段開啟，上線改 0
ini_set('log_errors', 1);

// 時區（使用正確 IANA 名稱 + 防禦性處理，某些共享主機 tzdata 不完整）
$timezone = defined('APP_TIMEZONE') ? APP_TIMEZONE : 'Asia/Hong_Kong';
if (!@date_default_timezone_set($timezone)) {
    // 極端情況 fallback，避免 Notice 影響頁面
    date_default_timezone_set('UTC');
}

// 建立 uploads 目錄（如不存在）
if (!is_dir(__DIR__ . '/uploads')) {
    @mkdir(__DIR__ . '/uploads', 0755, true);
}
