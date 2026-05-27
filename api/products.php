<?php
/**
 * SalonEase - 零售產品管理 API
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../db.php';

require_login();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'list':
        $search = trim(get('search', ''));
        $category = get('category', '');
        $status = get('status', '');

        $sql = "SELECT id, name, sku, price, cost, stock_qty, category, is_active FROM products WHERE 1=1";
        $params = [];

        if ($search !== '') {
            $sql .= " AND (name LIKE ? OR sku LIKE ?)";
            $like = "%{$search}%";
            $params[] = $like;
            $params[] = $like;
        }
        if ($category !== '') {
            $sql .= " AND category = ?";
            $params[] = $category;
        }
        if ($status !== '') {
            $sql .= " AND is_active = ?";
            $params[] = (int)$status;
        }

        $sql .= " ORDER BY is_active DESC, name ASC";

        $products = db_query($sql, $params);
        json_success($products);
        break;

    case 'create':
        if (!is_post()) json_error('只接受 POST 請求', 405);

        $name = trim(post('name'));
        $sku = trim(post('sku'));
        $price = (float)post('price');
        $cost = (float)post('cost', 0);
        $stock = (int)post('stock_qty', 0);
        $category = trim(post('category'));

        if (!$name || $price <= 0) {
            json_error('產品名稱與售價為必填');
        }

        db_exec(
            "INSERT INTO products (name, sku, price, cost, stock_qty, category, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)",
            [$name, $sku, $price, $cost, $stock, $category]
        );

        json_success(['id' => (int)db_last_id()], '產品新增成功');
        break;

    case 'update':
        if (!is_post()) json_error('只接受 POST 請求', 405);

        $id = (int)post('id');
        $name = trim(post('name'));
        $sku = trim(post('sku'));
        $price = (float)post('price');
        $cost = (float)post('cost', 0);
        $stock = (int)post('stock_qty', 0);
        $low_stock = post('low_stock_threshold') !== '' ? (int)post('low_stock_threshold') : null;
        $category = trim(post('category'));

        if (!$id || !$name) {
            json_error('缺少必要資料');
        }

        db_exec(
            "UPDATE products SET name = ?, sku = ?, price = ?, cost = ?, stock_qty = ?, low_stock_threshold = ?, category = ? WHERE id = ?",
            [$name, $sku, $price, $cost, $stock, $low_stock, $category, $id]
        );

        json_success(null, '產品資料已更新');
        break;

    case 'toggle':
        if (!is_post()) json_error('只接受 POST 請求', 405);

        $id = (int)post('id');
        $newStatus = (int)post('status');

        db_exec("UPDATE products SET is_active = ? WHERE id = ?", [$newStatus, $id]);
        json_success(null, $newStatus ? '產品已啟用' : '產品已停用');
        break;

    default:
        json_error('未知的操作', 400);
}
