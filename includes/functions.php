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
 * 記錄操作日誌（Audit Log）
 * Phase 1 實作：寫入 audit_logs 表
 */
function log_activity(string $action, ?int $entityId = null, ?string $entityType = null, ?array $details = null): void
{
    try {
        $staffId = $_SESSION['staff_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

        db_exec(
            "INSERT INTO audit_logs (staff_id, action, entity_type, entity_id, details, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [
                $staffId,
                $action,
                $entityType,
                $entityId,
                $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                $ip,
                $userAgent
            ]
        );
    } catch (Throwable $e) {
        // 審計日誌失敗不應影響主要業務流程
        error_log("Audit log failed: " . $e->getMessage());
    }
}

/**
 * ============================================================
 * Phase 1：集中式輸入驗證函式庫（Input Validation Helpers）
 * 目標：統一各 API / 表單的驗證邏輯，提升安全性與一致性
 * 用法：$err = validate_xxx(...); if ($err) json_error($err);
 * ============================================================
 */

/**
 * 必填驗證
 */
function validate_required(mixed $value, string $label = '此欄位'): ?string
{
    if (is_string($value)) {
        $value = trim($value);
    }
    if (empty($value) && $value !== 0 && $value !== '0') {
        return "{$label}為必填";
    }
    return null;
}

/**
 * Email 格式驗證
 */
function validate_email(string $email): ?string
{
    $email = trim($email);
    if ($email === '') {
        return null; // 允許空（由 required 另處理）
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '電郵格式不正確';
    }
    if (strlen($email) > 100) {
        return '電郵長度不可超過 100 字元';
    }
    return null;
}

/**
 * 香港電話驗證（支援 8 位數字，允許空格/連字號）
 */
function validate_hk_phone(string $phone): ?string
{
    $phone = trim($phone);
    if ($phone === '') {
        return null;
    }
    $digits = preg_replace('/[^0-9]/', '', $phone);
    if (strlen($digits) !== 8) {
        return '電話號碼必須為 8 位數字（香港格式）';
    }
    // 可擴充：檢查常見香港號碼開頭（不強制）
    return null;
}

/**
 * 金額驗證（正數，可選是否允許 0）
 */
function validate_money(mixed $amount, string $label = '金額', bool $allowZero = false): ?string
{
    if (!is_numeric($amount)) {
        return "{$label}必須為有效數字";
    }
    $amount = (float)$amount;
    if ($amount < 0) {
        return "{$label}不可為負數";
    }
    if (!$allowZero && $amount <= 0) {
        return "{$label}必須大於 0";
    }
    // 合理上限（防溢位，1 億）
    if ($amount > 100000000) {
        return "{$label}數值過大";
    }
    return null;
}

/**
 * 正整數驗證（ID、數量等）
 */
function validate_positive_int(mixed $value, string $label = '數值', int $min = 1): ?string
{
    if (!is_numeric($value) || (int)$value != $value) {
        return "{$label}必須為整數";
    }
    $value = (int)$value;
    if ($value < $min) {
        return "{$label}必須 ≥ {$min}";
    }
    return null;
}

/**
 * 日期字串驗證（YYYY-MM-DD）
 */
function validate_date(string $date): ?string
{
    $date = trim($date);
    if ($date === '') {
        return null;
    }
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return '日期格式必須為 YYYY-MM-DD';
    }
    $ts = strtotime($date);
    if (!$ts || date('Y-m-d', $ts) !== $date) {
        return '日期無效';
    }
    return null;
}

/**
 * 字串長度驗證
 */
function validate_length(string $value, string $label, int $max, int $min = 0): ?string
{
    $len = mb_strlen(trim($value));
    if ($min > 0 && $len < $min) {
        return "{$label}至少需 {$min} 字元";
    }
    if ($len > $max) {
        return "{$label}不可超過 {$max} 字元";
    }
    return null;
}

/**
 * 簡單 Sanitize 字串（去除前後空白 + 截斷）
 */
function sanitize_string(string $value, int $maxLen = 255): string
{
    $value = trim($value);
    if (mb_strlen($value) > $maxLen) {
        $value = mb_substr($value, 0, $maxLen);
    }
    return $value;
}

/**
 * Phase 1 簡單集中錯誤記錄（不影響主流程）
 */
function log_error(string $context, string $message, array $extra = []): void
{
    $log = sprintf(
        "[%s] %s | extra: %s | ip: %s | staff: %s",
        $context,
        $message,
        json_encode($extra, JSON_UNESCAPED_UNICODE),
        $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        $_SESSION['staff_id'] ?? 'guest'
    );
    error_log($log);
}
