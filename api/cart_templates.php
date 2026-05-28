<?php
/**
 * SalonEase - 常用購物車組合 API
 * 用於命令面板快速儲存與載入常用療程組合
 */

require_once __DIR__ . '/../includes/auth.php';
require_login();

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$currentStaffId = $_SESSION['staff_id'] ?? 0;

if (!$currentStaffId) {
    json_error('請重新登入', 401);
}

switch ($action) {

    case 'list':
        // 取得目前員工的所有有效模板
        $templates = db_query(
            "SELECT id, name, items, created_at 
             FROM cart_templates 
             WHERE staff_id = ? AND is_active = 1 
             ORDER BY name ASC",
            [$currentStaffId]
        );

        // 解析 JSON items 方便前端使用
        foreach ($templates as &$t) {
            $t['items'] = json_decode($t['items'], true) ?: [];
        }

        json_success($templates);
        break;

    case 'create':
        if (!is_post()) json_error('只接受 POST 請求', 405);

        $name = trim(post('name', ''));
        $itemsJson = post('items', '');

        if (!$name) {
            json_error('請輸入組合名稱');
        }

        if (!$itemsJson) {
            json_error('購物車內容不可為空');
        }

        // 驗證 items 是合法 JSON
        $items = json_decode($itemsJson, true);
        if (!is_array($items) || count($items) === 0) {
            json_error('購物車內容格式不正確');
        }

        // 簡單清理 items，只保留必要欄位
        $cleanItems = [];
        foreach ($items as $item) {
            if (!isset($item['type'], $item['ref_id'], $item['name'], $item['unit_price'])) continue;

            $cleanItems[] = [
                'type'       => $item['type'],
                'ref_id'     => (int)$item['ref_id'],
                'name'       => $item['name'],
                'unit_price' => (float)$item['unit_price'],
                'qty'        => max(1, (int)($item['qty'] ?? 1))
            ];
        }

        if (empty($cleanItems)) {
            json_error('沒有可儲存的有效項目');
        }

        db_exec(
            "INSERT INTO cart_templates (staff_id, name, items) VALUES (?, ?, ?)",
            [$currentStaffId, $name, json_encode($cleanItems, JSON_UNESCAPED_UNICODE)]
        );

        json_success([
            'id' => (int)db_last_id(),
            'name' => $name
        ], '常用組合已儲存');
        break;

    case 'apply':
        // 根據模板 ID 回傳項目，讓前端載入到購物車
        $id = (int)get('id', 0);

        if (!$id) {
            json_error('缺少模板 ID');
        }

        $template = db_query_one(
            "SELECT name, items FROM cart_templates WHERE id = ? AND staff_id = ? AND is_active = 1",
            [$id, $currentStaffId]
        );

        if (!$template) {
            json_error('找不到此常用組合或你沒有權限使用');
        }

        $items = json_decode($template['items'], true) ?: [];

        json_success([
            'name' => $template['name'],
            'items' => $items
        ]);
        break;

    case 'delete':
        if (!is_post()) json_error('只接受 POST 請求', 405);

        $id = (int)post('id', 0);

        if (!$id) {
            json_error('缺少模板 ID');
        }

        db_exec(
            "UPDATE cart_templates SET is_active = 0 WHERE id = ? AND staff_id = ?",
            [$id, $currentStaffId]
        );

        json_success(null, '常用組合已刪除');
        break;

    default:
        json_error('未知的操作', 400);
}