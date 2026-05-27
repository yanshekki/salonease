<?php
/**
 * SalonEase - 共用函式庫
 * 所有格式化、工具函式放在這裡
 */

/**
 * 格式化香港貨幣
 */
function format_money(float $amount): string
{
    return 'HK$ ' . number_format($amount, 2);
}

/**
 * 格式化香港電話（簡單美化）
 */
function format_hk_phone(string $phone): string
{
    $phone = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($phone) === 8) {
        return substr($phone, 0, 4) . ' ' . substr($phone, 4);
    }
    return $phone;
}

/**
 * 格式化日期（香港習慣 dd/mm/YYYY）
 */
function format_date(string $date, bool $withTime = false): string
{
    if (empty($date)) return '';
    $ts = strtotime($date);
    if ($withTime) {
        return date('d/m/Y H:i', $ts);
    }
    return date('d/m/Y', $ts);
}

/**
 * 產生收據編號（簡單實作）
 */
function generate_receipt_no(int $saleId): string
{
    return 'SE' . date('Ymd') . str_pad($saleId, 5, '0', STR_PAD_LEFT);
}

/**
 * 安全輸出文字（防 XSS）
 */
function e(string $string): string
{
    return htmlspecialchars($string, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * 回傳 JSON 成功
 */
function json_success(mixed $data = null, string $message = ''): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => $message,
        'data'    => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 回傳 JSON 失敗
 */
function json_error(string $message, int $code = 400, mixed $extra = null): never
{
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'message' => $message,
        'extra'   => $extra,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 檢查是否為 POST 請求
 */
function is_post(): bool
{
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/**
 * 取得 POST 欄位並 trim
 */
function post(string $key, mixed $default = null): mixed
{
    return isset($_POST[$key]) ? trim($_POST[$key]) : $default;
}

/**
 * 取得 GET 欄位並 trim
 */
function get(string $key, mixed $default = null): mixed
{
    return isset($_GET[$key]) ? trim($_GET[$key]) : $default;
}

/**
 * 簡單記錄操作日誌（後續可擴充）
 */
function log_activity(string $action, ?int $entityId = null, ?string $entityType = null, ?array $details = null): void
{
    // Phase 0 先留空，後續實作 activity_logs 寫入
    // 可在這裡寫入檔案或 DB
}
