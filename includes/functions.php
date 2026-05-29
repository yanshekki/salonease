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
 * HTML escape 別名（與 install.php / upgrade.php 相容）
 */
function h(string $str): string
{
    return e($str);
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

/**
 * Phase 5：簡單 Email 發送函式（骨架）
 * 目前先使用 PHP 內建 mail()，未來可替換成 PHPMailer / SMTP 等。
 *
 * @return bool 是否成功嘗試發送（不保證一定到達）
 */
function send_email(string $to, string $subject, string $body, ?string $from = null): bool
{
    $settings = db_query_one("SELECT reminder_email_enabled, reminder_from_email, email FROM settings WHERE id = 1");

    // 如果未啟用實際寄送，只記錄 log
    if (empty($settings['reminder_email_enabled'])) {
        $logMessage = sprintf(
            "[EMAIL - DISABLED] To: %s | Subject: %s\nBody:\n%s\n",
            $to, $subject, $body
        );
        error_log($logMessage);
        return true; // 視為成功（不影響流程）
    }

    if (empty($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        log_error('email', 'Invalid recipient email', ['to' => $to]);
        return false;
    }

    $from = $from ?: ($settings['reminder_from_email'] ?: $settings['email'] ?: 'no-reply@salonease.hk');

    $headers  = "From: {$from}\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "Return-Path: {$from}\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $headers .= "MIME-Version: 1.0\r\n";

    $result = @mail($to, $subject, $body, $headers);

    if (!$result) {
        log_error('email', 'mail() returned false', [
            'to'      => $to,
            'subject' => $subject,
            'from'    => $from
        ]);
    }

    return $result;
}

/**
 * Phase 5：簡單 SMS 發送函式（骨架）
 * 目前為記錄模式，方便測試。正式上線時請替換成真實 SMS 供應商。
 *
 * 推薦供應商（香港/國際）：
 * - Twilio
 * - MessageBird
 * - Nexmo (Vonage)
 * - 香港本地：MessageMedia、亞馬遜 SNS + SNS SMS
 *
 * @param string $phone  手機號碼（建議帶 +852）
 * @param string $message 訊息內容
 * @return bool 是否成功嘗試發送
 */
function send_sms(string $phone, string $message): bool
{
    $phone = preg_replace('/[^0-9+]/', '', $phone);

    if (empty($phone)) {
        log_error('sms', 'Invalid phone number', ['phone' => $phone]);
        return false;
    }

    $settings = db_query_one("
        SELECT reminder_sms_enabled, twilio_account_sid, twilio_auth_token, twilio_from_number 
        FROM settings WHERE id = 1
    ");

    // 如果未啟用 SMS 實際發送，則只記錄
    if (empty($settings['reminder_sms_enabled'])) {
        $logMessage = sprintf(
            "[SMS - DISABLED] To: %s\nMessage:\n%s\n",
            $phone, $message
        );
        error_log($logMessage);
        return true;
    }

    // 如果有完整設定 Twilio，就真正發送
    if (!empty($settings['twilio_account_sid']) && !empty($settings['twilio_auth_token']) && !empty($settings['twilio_from_number'])) {

        if (!class_exists('\Twilio\Rest\Client')) {
            log_error('sms', 'Twilio SDK not installed. Please run: composer require twilio/sdk');
            error_log("[SMS] Twilio SDK not found.");
            return false;
        }

        try {
            $sid   = $settings['twilio_account_sid'];
            $token = $settings['twilio_auth_token'];
            $from  = $settings['twilio_from_number'];

            $client = new \Twilio\Rest\Client($sid, $token);
            $client->messages->create($phone, [
                'from' => $from,
                'body' => $message
            ]);

            return true;
        } catch (Exception $e) {
            log_error('sms', 'Twilio send failed', ['error' => $e->getMessage()]);
            error_log("[SMS] Twilio Error: " . $e->getMessage());
            return false;
        }
    }

    // 沒有設定 Twilio → 維持骨架模式
    $logMessage = sprintf(
        "[SMS - SKELETON] To: %s\nMessage:\n%s\n",
        $phone, $message
    );
    error_log($logMessage);

    return true;
}

/**
 * Phase 5：執行單一付款計劃提醒規則
 * 會根據規則判斷是否需要發送，並寫入通知記錄
 *
 * @param int $ruleId
 * @return array ['success' => bool, 'message' => string, 'notification_id' => ?int]
 */
function executePaymentReminder(int $ruleId): array
{
    $rule = db_query_one("
        SELECT r.*, p.status as plan_status, p.start_date, p.total_installments,
               sa.id as sale_id, c.name as customer_name, c.email as customer_email, c.phone as customer_phone
        FROM plan_reminder_rules r
        JOIN sale_payment_plans p ON p.id = r.plan_id
        JOIN sales sa ON sa.id = p.sale_id
        JOIN customers c ON c.id = sa.customer_id
        WHERE r.id = ?
    ", [$ruleId]);

    if (!$rule) {
        return ['success' => false, 'message' => '找不到提醒規則'];
    }

    if ($rule['plan_status'] !== 'active') {
        return ['success' => false, 'message' => '該計劃已非進行中狀態'];
    }

    // 計算今天是否應該發送
    $today = new DateTime();
    $startDate = new DateTime($rule['start_date']);
    $totalInstallments = (int)$rule['total_installments'];

    // 簡易判斷：假設每月一期（後續可根據 frequency 加強）
    $monthsPassed = ($today->format('Y') - $startDate->format('Y')) * 12 + ($today->format('n') - $startDate->format('n'));
    $currentInstallment = $monthsPassed + 1;

    if ($currentInstallment < 1 || $currentInstallment > $totalInstallments) {
        return ['success' => false, 'message' => '目前不在提醒範圍內'];
    }

    // 計算目標日期
    $targetDate = clone $startDate;
    $targetDate->modify("+{$currentInstallment} month");

    $offset = (int)$rule['offset_days'];
    $shouldSend = false;

    if ($rule['reminder_type'] === 'before_due') {
        $reminderDate = clone $targetDate;
        $reminderDate->modify("-{$offset} days");
        if ($today->format('Y-m-d') === $reminderDate->format('Y-m-d')) {
            $shouldSend = true;
        }
    } else { // after_due
        $reminderDate = clone $targetDate;
        $reminderDate->modify("+{$offset} days");
        if ($today->format('Y-m-d') === $reminderDate->format('Y-m-d')) {
            $shouldSend = true;
        }
    }

    if (!$shouldSend) {
        return ['success' => false, 'message' => '今天不需要發送此提醒'];
    }

    // 檢查今天是否已經發送過（避免重複）
    $alreadySent = db_query_one("
        SELECT id FROM plan_notifications 
        WHERE plan_id = ? AND reminder_rule_id = ? AND DATE(sent_at) = CURDATE()
    ", [$rule['plan_id'], $ruleId]);

    if ($alreadySent) {
        return ['success' => false, 'message' => '今天已經發送過此提醒'];
    }

    $now = date('Y-m-d H:i:s');
    $subject = "付款計劃提醒 - 計劃 #{$rule['plan_id']}";
    $message = "親愛的 {$rule['customer_name']}：\n\n";
    $message .= "您的付款計劃 #{$rule['plan_id']} ";
    $message .= ($rule['reminder_type'] === 'before_due' ? "即將到期" : "已逾期") . "。\n";
    $message .= "請盡快安排付款。\n\n";
    $message .= "SalonEase";

    $anySuccess = false;
    $errors = [];

    // === 發送 Email ===
    if (in_array($rule['channel'], ['email', 'both'])) {
        if (empty($rule['customer_email'])) {
            $errors[] = '客戶沒有 Email';
        } else {
            if (send_email($rule['customer_email'], $subject, $message)) {
                $anySuccess = true;

                db_exec("
                    INSERT INTO plan_notifications 
                    (plan_id, reminder_rule_id, channel, sent_at, status, subject, content, created_at)
                    VALUES (?, ?, 'email', ?, 'sent', ?, ?, NOW())
                ", [
                    $rule['plan_id'], $ruleId, $now, $subject, $message
                ]);
            } else {
                $errors[] = 'Email 發送失敗';
                db_exec("
                    INSERT INTO plan_notifications 
                    (plan_id, reminder_rule_id, channel, sent_at, status, subject, content, error_message, created_at)
                    VALUES (?, ?, 'email', ?, 'failed', ?, ?, ?, NOW())
                ", [
                    $rule['plan_id'], $ruleId, $now, $subject, $message, 'Email 發送失敗'
                ]);
            }
        }
    }

    // === 發送 SMS ===
    if (in_array($rule['channel'], ['sms', 'both'])) {
        $smsMessage = "SalonEase 付款計劃提醒：計劃 #{$rule['plan_id']} " .
                      ($rule['reminder_type'] === 'before_due' ? "即將到期" : "已逾期") .
                      "，請盡快付款。";

        if (send_sms($rule['customer_phone'] ?? '', $smsMessage)) {
            $anySuccess = true;

            db_exec("
                INSERT INTO plan_notifications 
                (plan_id, reminder_rule_id, channel, sent_at, status, content, created_at)
                VALUES (?, ?, 'sms', ?, 'sent', ?, NOW())
            ", [
                $rule['plan_id'], $ruleId, $now, $smsMessage
            ]);
        } else {
            $errors[] = 'SMS 發送失敗';
            db_exec("
                INSERT INTO plan_notifications 
                (plan_id, reminder_rule_id, channel, sent_at, status, content, error_message, created_at)
                VALUES (?, ?, 'sms', ?, 'failed', ?, ?, NOW())
            ", [
                $rule['plan_id'], $ruleId, $now, $smsMessage, 'SMS 發送失敗'
            ]);
        }
    }

    // 更新 last_sent_at（只要有任何一個管道成功就更新）
    if ($anySuccess) {
        db_exec("UPDATE plan_reminder_rules SET last_sent_at = ? WHERE id = ?", [$now, $ruleId]);
    }

    log_activity('plan_reminder.executed', $rule['plan_id'], 'sale_payment_plan', [
        'rule_id'       => $ruleId,
        'type'          => 'scheduled',
        'channels'      => $rule['channel'],
        'any_success'   => $anySuccess
    ]);

    return [
        'success' => $anySuccess,
        'message' => $anySuccess 
            ? '提醒已發送' 
            : (implode('；', $errors) ?: '發送失敗'),
    ];
}

