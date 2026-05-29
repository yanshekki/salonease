<?php
/**
 * SalonEase - 付款方法管理 API（Phase 1）
 *
 * GET  /api/payment_methods.php?action=list[&active=1]
 * POST /api/payment_methods.php?action=create
 * POST /api/payment_methods.php?action=update
 * POST /api/payment_methods.php?action=toggle
 * POST /api/payment_methods.php?action=reorder
 * POST /api/payment_methods.php?action=delete
 *
 * 所有管理操作僅限 admin / manager。
 * 完整支援手續費計算模型 + 即時建議手續費預覽。
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../db.php';

require_login();

/**
 * 計算建議手續費（後端權威計算，永不信任前端）
 */
function calculate_suggested_fee(array $method, float $baseAmount): float
{
    $model = $method['fee_model'] ?? 'none';
    $fixed = (float)($method['fee_fixed'] ?? 0);
    $percent = (float)($method['fee_percent'] ?? 0);

    if ($model === 'none') {
        return 0.00;
    }
    if ($model === 'fixed') {
        return round($fixed, 2);
    }
    if ($model === 'percent') {
        return round($baseAmount * $percent / 100, 2);
    }
    if ($model === 'fixed_plus_percent') {
        return round($fixed + ($baseAmount * $percent / 100), 2);
    }
    return 0.00;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Phase 8: 公開取得活躍付款方法（供客戶 Portal 使用）
if ($action === 'list_active_public') {
    $methods = db_query("
        SELECT id, name, code, fee_model, fee_fixed, fee_percent 
        FROM payment_methods 
        WHERE is_active = 1 
        ORDER BY sort_order ASC, id ASC
    ");
    json_success($methods);
    exit;
}

switch ($action) {

    case 'list':
        $activeOnly = (int)get('active', 0) === 1;
        $includeInactive = (int)get('include_inactive', 0) === 1;

        $sql = "SELECT * FROM payment_methods WHERE 1=1";
        $params = [];

        if ($activeOnly && !$includeInactive) {
            $sql .= " AND is_active = 1";
        }

        $sql .= " ORDER BY sort_order ASC, id ASC";

        $methods = db_query($sql, $params);

        // 附加建議手續費範例（以 HK$1,000 為例，方便管理頁即時顯示）
        $exampleAmount = 1000.00;
        foreach ($methods as &$m) {
            $m['suggested_fee_example'] = calculate_suggested_fee($m, $exampleAmount);
            $m['example_base_amount'] = $exampleAmount;
            // 清理不需要的前端欄位
            unset($m['created_at'], $m['updated_at']); // 管理頁用 updated_at 判斷即可
        }

        json_success($methods);
        break;

    case 'create':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        if (!in_array($_SESSION['staff_role'] ?? '', ['admin', 'manager'])) {
            json_error('只有管理員或店長可以新增付款方法', 403);
        }

        $code = strtolower(trim(post('code', '')));
        $name = sanitize_string(post('name', ''), 60);
        $fee_model = post('fee_model', 'none');
        $fee_fixed = (float)post('fee_fixed', 0);
        $fee_percent = (float)post('fee_percent', 0);
        $sort_order = (int)post('sort_order', 100);
        $notes = sanitize_string(post('notes', ''), 500);

        // 驗證
        if ($err = validate_required($code, '代碼')) json_error($err);
        if ($err = validate_required($name, '名稱')) json_error($err);
        if ($err = validate_length($code, '代碼', 30, 2)) json_error($err);
        if ($err = validate_length($name, '名稱', 60, 2)) json_error($err);
        if (!in_array($fee_model, ['none', 'fixed', 'percent', 'fixed_plus_percent'], true)) {
            json_error('手續費計算方式無效');
        }
        if ($fee_fixed < 0) json_error('固定手續費不可為負數');
        if ($fee_percent < 0 || $fee_percent > 100) json_error('百分比費率必須在 0~100 之間');

        // 檢查 code 是否已存在
        $exists = db_query_one("SELECT id FROM payment_methods WHERE code = ?", [$code]);
        if ($exists) {
            json_error('此代碼已存在，請使用其他代碼');
        }

        db_exec(
            "INSERT INTO payment_methods (code, name, fee_model, fee_fixed, fee_percent, sort_order, notes, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, 1)",
            [$code, $name, $fee_model, $fee_fixed, $fee_percent, $sort_order, $notes]
        );

        $newId = (int)db_last_id();

        log_activity('payment_method.created', $newId, 'payment_method', [
            'code' => $code,
            'name' => $name,
            'fee_model' => $fee_model
        ]);

        json_success(['id' => $newId], '付款方法新增成功');
        break;

    case 'update':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        if (!in_array($_SESSION['staff_role'] ?? '', ['admin', 'manager'])) {
            json_error('只有管理員或店長可以修改付款方法', 403);
        }

        $id = (int)post('id');
        $name = sanitize_string(post('name', ''), 60);
        $fee_model = post('fee_model', 'none');
        $fee_fixed = (float)post('fee_fixed', 0);
        $fee_percent = (float)post('fee_percent', 0);
        $sort_order = (int)post('sort_order', 100);
        $notes = sanitize_string(post('notes', ''), 500);

        if ($err = validate_required($id, '付款方法 ID')) json_error($err);
        if ($err = validate_required($name, '名稱')) json_error($err);
        if ($err = validate_length($name, '名稱', 60, 2)) json_error($err);
        if (!in_array($fee_model, ['none', 'fixed', 'percent', 'fixed_plus_percent'], true)) {
            json_error('手續費計算方式無效');
        }
        if ($fee_fixed < 0) json_error('固定手續費不可為負數');
        if ($fee_percent < 0 || $fee_percent > 100) json_error('百分比費率必須在 0~100 之間');

        $affected = db_exec(
            "UPDATE payment_methods 
             SET name = ?, fee_model = ?, fee_fixed = ?, fee_percent = ?, sort_order = ?, notes = ?
             WHERE id = ?",
            [$name, $fee_model, $fee_fixed, $fee_percent, $sort_order, $notes, $id]
        );

        if ($affected === 0) {
            json_error('找不到該付款方法或資料未變更');
        }

        log_activity('payment_method.updated', $id, 'payment_method', [
            'name' => $name,
            'fee_model' => $fee_model
        ]);

        json_success(null, '付款方法已更新');
        break;

    case 'toggle':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        if (!in_array($_SESSION['staff_role'] ?? '', ['admin', 'manager'])) {
            json_error('只有管理員或店長可以變更狀態', 403);
        }

        $id = (int)post('id');
        $newStatus = (int)post('status'); // 0 或 1

        if ($err = validate_required($id, '付款方法 ID')) json_error($err);

        db_exec("UPDATE payment_methods SET is_active = ? WHERE id = ?", [$newStatus, $id]);

        log_activity('payment_method.toggled', $id, 'payment_method', [
            'new_status' => $newStatus ? 'active' : 'inactive'
        ]);

        json_success(null, $newStatus ? '付款方法已啟用' : '付款方法已停用');
        break;

    case 'reorder':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        if (!in_array($_SESSION['staff_role'] ?? '', ['admin', 'manager'])) {
            json_error('只有管理員或店長可以調整排序', 403);
        }

        $order = $_POST['order'] ?? [];
        if (!is_array($order) || empty($order)) {
            json_error('排序資料格式錯誤');
        }

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("UPDATE payment_methods SET sort_order = ? WHERE id = ?");
            $position = 10;
            foreach ($order as $id) {
                $stmt->execute([$position, (int)$id]);
                $position += 10;
            }
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        log_activity('payment_method.reordered', null, 'payment_method', [
            'new_order_count' => count($order)
        ]);

        json_success(null, '排序已更新');
        break;

    case 'delete':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        if (!in_array($_SESSION['staff_role'] ?? '', ['admin', 'manager'])) {
            json_error('只有管理員或店長可以刪除付款方法', 403);
        }

        $id = (int)post('id');
        if ($err = validate_required($id, '付款方法 ID')) json_error($err);

        // Phase 1 暫時無 payments 表，未來 Phase 2 會加強使用檢查
        // 目前僅做基本保護：禁止刪除 id <= 8 的預設方法（保留種子資料完整性）
        if ($id <= 8) {
            json_error('系統預設付款方法不可刪除，建議改為「停用」');
        }

        $affected = db_exec("DELETE FROM payment_methods WHERE id = ?", [$id]);

        if ($affected === 0) {
            json_error('找不到該付款方法');
        }

        log_activity('payment_method.deleted', $id, 'payment_method', []);

        json_success(null, '付款方法已刪除');
        break;

    case 'calculate_fee':
        // 工具型 API：給前端即時試算使用（POS 未來會用到）
        $method_id = (int)get('method_id');
        $amount = (float)get('amount', 0);

        if ($method_id <= 0 || $amount <= 0) {
            json_error('請提供有效的付款方法與金額');
        }

        $method = db_query_one("SELECT * FROM payment_methods WHERE id = ?", [$method_id]);
        if (!$method || !$method['is_active']) {
            json_error('付款方法不存在或已停用');
        }

        $fee = calculate_suggested_fee($method, $amount);

        json_success([
            'method_id' => $method_id,
            'base_amount' => $amount,
            'fee_amount' => $fee,
            'total_with_fee' => round($amount + $fee, 2),
            'fee_model' => $method['fee_model'],
            'fee_borne_by_default' => 'merchant' // Phase 1 預設商戶承擔
        ]);
        break;

    default:
        json_error('未知的操作', 400);
}
