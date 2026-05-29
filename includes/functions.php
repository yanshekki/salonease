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

    // Phase 7 A：生產級實作 — 使用 Twilio REST API 直接 cURL（無需 Composer SDK）
    if (!empty($settings['twilio_account_sid']) && !empty($settings['twilio_auth_token']) && !empty($settings['twilio_from_number'])) {
        $sid   = $settings['twilio_account_sid'];
        $token = $settings['twilio_auth_token'];
        $from  = $settings['twilio_from_number'];

        $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

        $postFields = http_build_query([
            'From' => $from,
            'To'   => $phone,
            'Body' => $message
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, "{$sid}:{$token}");
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 8);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            log_error('sms', 'Twilio cURL error', ['error' => $curlError, 'phone' => $phone]);
            error_log("[SMS] Twilio cURL Error: " . $curlError);
            return false;
        }

        $data = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300 && isset($data['sid'])) {
            // 成功
            return true;
        } else {
            $errMsg = $data['message'] ?? $response;
            log_error('sms', 'Twilio API error', [
                'http_code' => $httpCode,
                'response'  => $errMsg,
                'phone'     => $phone
            ]);
            error_log("[SMS] Twilio API Error (HTTP {$httpCode}): " . $errMsg);
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
               sa.id as sale_id, c.id as customer_id, c.name as customer_name, c.email as customer_email, c.phone as customer_phone
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

    // Phase 8: 加入客戶自助 Portal 連結
    $portalLink = '';
    if (!empty($rule['customer_id'])) {
        $portalToken = generateCustomerPortalToken($rule['customer_id']);
        $portalLink = "\n\n查看你的付款計劃詳情及記錄付款：\nhttps://salonease.ysk.hk/customer_portal.php?token={$portalToken}";
    }

    $message = "親愛的 {$rule['customer_name']}：\n\n";
    $message .= "您的付款計劃 #{$rule['plan_id']} ";
    $message .= ($rule['reminder_type'] === 'before_due' ? "即將到期" : "已逾期") . "。\n";
    $message .= "請盡快安排付款。";
    $message .= $portalLink;
    $message .= "\n\nSalonEase";

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
        if (!empty($rule['customer_id'])) {
            $smsPortalToken = generateCustomerPortalToken($rule['customer_id']);
            $smsMessage .= " 詳情：https://salonease.ysk.hk/customer_portal.php?token={$smsPortalToken}";
        }

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

/**
 * Phase 7 A：重試單一失敗的提醒記錄
 * 會使用儲存的 content/subject 重新發送，並更新 retry_count 及 last_retry_at
 */
function retryNotification(int $notificationId): array
{
    $n = db_query_one("
        SELECT pn.*, 
               c.name as customer_name, c.email as customer_email, c.phone as customer_phone,
               p.id as plan_id
        FROM plan_notifications pn
        LEFT JOIN sale_payment_plans p ON p.id = pn.plan_id
        LEFT JOIN sales s ON s.id = p.sale_id
        LEFT JOIN customers c ON c.id = s.customer_id
        WHERE pn.id = ?
    ", [$notificationId]);

    if (!$n) {
        return ['success' => false, 'message' => '找不到通知記錄'];
    }

    if ($n['status'] !== 'failed') {
        return ['success' => false, 'message' => '只有失敗的記錄才能重試'];
    }

    $retryCount = (int)($n['retry_count'] ?? 0);
    if ($retryCount >= 3) {
        return ['success' => false, 'message' => '已達到最大重試次數（3）'];
    }

    $now = date('Y-m-d H:i:s');
    $success = false;
    $errorMsg = '';

    if ($n['channel'] === 'email') {
        $subject = $n['subject'] ?: '付款計劃提醒';
        $body = $n['content'] ?: '';
        if (empty($n['customer_email'])) {
            $errorMsg = '客戶沒有 Email';
        } elseif (send_email($n['customer_email'], $subject, $body)) {
            $success = true;
        } else {
            $errorMsg = 'Email 重試發送失敗';
        }
    } else { // sms
        $smsBody = $n['content'] ?: '';
        if (empty($n['customer_phone'])) {
            $errorMsg = '客戶沒有電話';
        } elseif (send_sms($n['customer_phone'], $smsBody)) {
            $success = true;
        } else {
            $errorMsg = 'SMS 重試發送失敗';
        }
    }

    $newRetryCount = $retryCount + 1;
    $newStatus = $success ? 'sent' : 'failed';
    $newError = $success ? null : (($n['error_message'] ? $n['error_message'] . '；' : '') . $errorMsg);

    db_exec("
        UPDATE plan_notifications 
        SET status = ?, 
            retry_count = ?, 
            last_retry_at = ?, 
            error_message = ?
        WHERE id = ?
    ", [$newStatus, $newRetryCount, $now, $newError, $notificationId]);

    log_activity('plan_reminder.retried', $n['plan_id'] ?? 0, 'plan_notification', [
        'notification_id' => $notificationId,
        'success' => $success,
        'retry_count' => $newRetryCount
    ]);

    return [
        'success' => $success,
        'message' => $success ? '重試發送成功' : $errorMsg,
        'new_status' => $newStatus,
        'retry_count' => $newRetryCount
    ];
}

/**
 * Phase 7 A 最終收尾：計算提醒系統健康分數（0-100）
 * 用於 settings 頁顯示，讓用戶一目了然系統狀態。
 */
function calculateReminderHealthScore(): array
{
    $score = 100;
    $factors = [];

    // 1. 最近7天失敗率扣分
    $stats7 = db_query_one("
        SELECT 
            SUM(CASE WHEN status='sent' THEN 1 ELSE 0 END) as sent,
            SUM(CASE WHEN status='failed' THEN 1 ELSE 0 END) as failed
        FROM plan_notifications 
        WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $total7 = (int)($stats7['sent'] ?? 0) + (int)($stats7['failed'] ?? 0);
    if ($total7 > 0) {
        $failRate = (int)$stats7['failed'] / $total7;
        if ($failRate > 0.15) {
            $score -= 25;
            $factors[] = '7天失敗率偏高';
        } elseif ($failRate > 0.05) {
            $score -= 10;
            $factors[] = '7天有少量失敗';
        }
    }

    // 2. 待重試數扣分
    $pending = db_query_one("SELECT COUNT(*) as cnt FROM plan_notifications WHERE status='failed' AND retry_count < 3");
    $pendingCount = (int)($pending['cnt'] ?? 0);
    if ($pendingCount >= 10) {
        $score -= 30;
        $factors[] = '大量待重試提醒';
    } elseif ($pendingCount >= 3) {
        $score -= 15;
        $factors[] = '有待重試提醒';
    }

    // 3. 最後執行時間（超過2天沒跑扣分）
    $lastRun = db_query_one("SELECT MAX(sent_at) as last_sent FROM plan_notifications");
    if ($lastRun && $lastRun['last_sent']) {
        $days = (time() - strtotime($lastRun['last_sent'])) / 86400;
        if ($days > 2) {
            $score -= 20;
            $factors[] = '超過2天未執行提醒';
        } elseif ($days > 1) {
            $score -= 8;
            $factors[] = '昨日未執行提醒';
        }
    } else {
        $score -= 15;
        $factors[] = '無執行記錄';
    }

    $score = max(0, min(100, $score));

    if (empty($factors)) {
        $factors[] = '系統運行良好';
    }

    return [
        'score' => $score,
        'factors' => $factors,
        'pending_retries' => $pendingCount,
        'last_run' => $lastRun['last_sent'] ?? null
    ];
}

/**
 * Phase 8: 產生客戶 Portal 安全存取 token（有限期）
 */
function generateCustomerPortalToken(int $customerId, int $daysValid = 45): string
{
    $token = bin2hex(random_bytes(32)); // 64 chars

    $expiresAt = date('Y-m-d H:i:s', time() + ($daysValid * 86400));

    db_exec("
        INSERT INTO customer_portal_tokens (customer_id, token, expires_at, created_at)
        VALUES (?, ?, ?, NOW())
    ", [$customerId, $token, $expiresAt]);

    return $token;
}

/**
 * Phase 8: 驗證客戶 Portal token
 * 回傳客戶基本資料 + token 資訊，或 null（無效/過期）
 */
function validateCustomerPortalToken(string $token): ?array
{
    $row = db_query_one("
        SELECT t.*, c.id as customer_id, c.name, c.phone, c.email
        FROM customer_portal_tokens t
        JOIN customers c ON c.id = t.customer_id
        WHERE t.token = ? 
          AND t.expires_at > NOW()
        LIMIT 1
    ", [$token]);

    if (!$row) {
        return null;
    }

    // 可選：標記使用（不強制 one-time）
    // db_exec("UPDATE customer_portal_tokens SET used_at = NOW() WHERE id = ?", [$row['id']]);

    return [
        'customer_id' => (int)$row['customer_id'],
        'name'        => $row['name'],
        'phone'       => $row['phone'],
        'email'       => $row['email'],
        'token'       => $row['token'],
        'expires_at'  => $row['expires_at']
    ];
}

/* ==================== Phase 6: 付款計劃進階分析與預測 ==================== */

/**
 * 計算單一計劃的未來現金流預測（簡化版）
 * 目前假設每月一期（後續可根據 frequency 加強）
 *
 * @param int $planId
 * @param int $daysAhead 預測未來多少天
 * @return array
 */
function calculatePlanCashFlowForecast(int $planId, int $daysAhead = 90): array
{
    $plan = db_query_one("
        SELECT spp.*, 
               COALESCE(SUM(CASE WHEN p.is_refund=0 THEN p.amount ELSE 0 END), 0) as paid_amount,
               COUNT(CASE WHEN p.is_refund=0 THEN 1 END) as payments_made
        FROM sale_payment_plans spp
        LEFT JOIN payments p ON p.plan_id = spp.id
        WHERE spp.id = ? AND spp.status = 'active'
        GROUP BY spp.id
    ", [$planId]);

    if (!$plan) {
        return ['error' => '計劃不存在或非進行中'];
    }

    $total = (int)$plan['total_installments'];
    $amount = (float)$plan['installment_amount'];
    $made = (int)$plan['payments_made'];
    $remaining = $total - $made;
    $frequency = $plan['frequency'] ?: 'monthly'; // installment 預設 monthly

    if ($remaining <= 0) {
        return ['expected_collections' => 0, 'periods' => [], 'monthly' => []];
    }

    $startDate = new DateTime($plan['start_date']);
    $today = new DateTime();
    $endDate = (clone $today)->modify("+$daysAhead days");

    $forecast = [];
    $monthly = [];
    $totalExpected = 0;

    $currentInstallment = $made + 1;
    $currentDate = clone $startDate;

    // 根據 frequency 計算每期應付日期
    $intervalMap = [
        'weekly'    => '+1 week',
        'biweekly'  => '+2 weeks',
        'monthly'   => '+1 month',
        'quarterly' => '+3 months',
    ];
    $interval = $intervalMap[$frequency] ?? '+1 month';

    // 推進到目前已付的最後一期日期
    for ($i = 1; $i <= $made; $i++) {
        $currentDate->modify($interval);
    }

    for ($i = 0; $i < $remaining; $i++) {
        $dueDate = clone $currentDate;
        $dueDate->modify($interval);

        if ($dueDate > $endDate) break;

        if ($dueDate >= $today) {
            $monthKey = $dueDate->format('Y-m');

            $forecast[] = [
                'date' => $dueDate->format('Y-m-d'),
                'amount' => $amount,
                'installment_no' => $currentInstallment
            ];

            if (!isset($monthly[$monthKey])) $monthly[$monthKey] = 0;
            $monthly[$monthKey] += $amount;

            $totalExpected += $amount;
        }

        $currentInstallment++;
        $currentDate = $dueDate;
    }

    ksort($monthly);

    return [
        'plan_id' => $planId,
        'plan_type' => $plan['plan_type'],
        'frequency' => $frequency,
        'remaining_installments' => $remaining,
        'expected_collections' => round($totalExpected, 2),
        'periods' => $forecast,
        'monthly' => $monthly
    ];
}

/**
 * 計算客戶付款健康分數（0-100）
 */
function calculateCustomerPaymentHealthScore(int $customerId): array
{
    $plans = db_query("
        SELECT spp.id, spp.status, spp.created_at, spp.notes,
               COALESCE(SUM(CASE WHEN p.is_refund=0 THEN p.amount ELSE 0 END), 0) as paid,
               spp.installment_amount * spp.total_installments as total_expected,
               COUNT(CASE WHEN p.is_refund=0 THEN 1 END) as payments_made
        FROM sale_payment_plans spp
        LEFT JOIN payments p ON p.plan_id = spp.id
        JOIN sales s ON s.id = spp.sale_id
        WHERE s.customer_id = ?
        GROUP BY spp.id
    ", [$customerId]);

    if (empty($plans)) {
        return ['score' => 50, 'factors' => ['無歷史計劃資料']];
    }

    $totalPlans = count($plans);
    $completed = 0;
    $active = 0;
    $cancelled = 0;
    $totalPaid = 0;
    $totalExpected = 0;
    $overdueKeywords = 0;
    $recentPlans = 0;

    $now = new DateTime();

    foreach ($plans as $p) {
        if ($p['status'] === 'completed') $completed++;
        if ($p['status'] === 'active') $active++;
        if ($p['status'] === 'cancelled') $cancelled++;

        $totalPaid += (float)$p['paid'];
        $totalExpected += (float)$p['total_expected'];

        // 計算逾期相關跟進次數
        if (!empty($p['notes'])) {
            $overdueKeywords += substr_count(strtolower($p['notes']), '逾期') +
                                substr_count(strtolower($p['notes']), '遲繳') +
                                substr_count(strtolower($p['notes']), '拖欠');
        }

        // 最近計劃加權（6個月內）
        $created = new DateTime($p['created_at']);
        if ($now->diff($created)->m + ($now->diff($created)->y * 12) <= 6) {
            $recentPlans++;
        }
    }

    $completionRate = $totalPlans > 0 ? ($completed / $totalPlans) : 0;
    $paymentProgress = $totalExpected > 0 ? min(1, $totalPaid / $totalExpected) : 0;

    // 實用版健康分數計算
    $score = 55;
    $score += ($completionRate * 28);
    $score += ($paymentProgress * 22);

    // 逾期扣分（強力）
    $score -= min(25, $overdueKeywords * 4);

    // 取消計劃扣分
    $score -= ($cancelled * 6);

    // 近期活躍計劃給予小加分（表示有持續往來）
    if ($recentPlans > 0 && $active > 0) {
        $score += 5;
    }

    $score = max(10, min(100, round($score)));

    $factors = [];
    if ($completionRate >= 0.85) $factors[] = '高完成率';
    if ($paymentProgress >= 0.92) $factors[] = '付款紀律良好';
    if ($overdueKeywords >= 2) $factors[] = '曾多次逾期';
    if ($cancelled > 0) $factors[] = '曾取消計劃';
    if ($score >= 85) $factors[] = '優良客戶';

    return [
        'score' => $score,
        'factors' => $factors ?: ['一般水平'],
        'stats' => [
            'total_plans' => $totalPlans,
            'completed' => $completed,
            'active' => $active,
            'cancelled' => $cancelled,
            'overall_progress' => round($paymentProgress * 100, 1),
            'overdue_followups' => $overdueKeywords
        ]
    ];
}

