<?php
/**
 * SalonEase - 零售產品管理 API
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../db.php';

require_login();

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {

    case 'list':
        $search = trim(get('search', ''));
        $category = get('category', '');
        $status = get('status', '');

        // 取得全域低庫存預設門檻
        $globalThreshold = db_query_one("SELECT default_low_stock_threshold FROM settings WHERE id = 1");
        $globalLowStock = (int)($globalThreshold['default_low_stock_threshold'] ?? 5);

        $sql = "SELECT id, name, sku, price, cost, stock_qty, low_stock_threshold, category, is_active FROM products WHERE 1=1";
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

        // 計算有效門檻（per-product 優先，否則用全域）
        foreach ($products as &$p) {
            $p['effective_low_stock_threshold'] = $p['low_stock_threshold'] !== null 
                ? (int)$p['low_stock_threshold'] 
                : $globalLowStock;
        }

        json_success($products);
        break;

    case 'create':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $name = sanitize_string(post('name', ''));
        $sku = sanitize_string(post('sku', ''));
        $price = (float)post('price', 0);
        $cost = (float)post('cost', 0);
        $stock = (int)post('stock_qty', 0);
        $category = sanitize_string(post('category', ''));

        if ($err = validate_required($name, '產品名稱')) json_error($err);
        if ($err = validate_money($price, '售價')) json_error($err);
        if ($err = validate_money($cost, '成本', true)) json_error($err);
        if ($err = validate_length($name, '產品名稱', 100, 1)) json_error($err);
        if ($err = validate_length($sku, 'SKU', 50)) json_error($err);

        db_exec(
            "INSERT INTO products (name, sku, price, cost, stock_qty, category, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)",
            [$name, $sku, $price, $cost, $stock, $category]
        );

        $newId = db_last_id();
        log_activity('product.created', $newId, 'product', [
            'name' => $name,
            'price' => $price,
            'sku' => $sku
        ]);

        json_success(['id' => (int)$newId], '產品新增成功');
        break;

    case 'update':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $id = (int)post('id');
        $name = sanitize_string(post('name', ''));
        $sku = sanitize_string(post('sku', ''));
        $price = (float)post('price', 0);
        $cost = (float)post('cost', 0);
        $stock = (int)post('stock_qty', 0);
        $low_stock = post('low_stock_threshold') !== '' ? (int)post('low_stock_threshold') : null;
        $category = sanitize_string(post('category', ''));

        if ($err = validate_required($id, '產品 ID')) json_error($err);
        if ($err = validate_required($name, '產品名稱')) json_error($err);
        if ($err = validate_money($price, '售價')) json_error($err);
        if ($err = validate_length($name, '產品名稱', 100, 1)) json_error($err);
        if ($err = validate_length($sku, 'SKU', 50)) json_error($err);

        db_exec(
            "UPDATE products SET name = ?, sku = ?, price = ?, cost = ?, stock_qty = ?, low_stock_threshold = ?, category = ? WHERE id = ?",
            [$name, $sku, $price, $cost, $stock, $low_stock, $category, $id]
        );

        log_activity('product.updated', $id, 'product', [
            'name' => $name,
            'price' => $price
        ]);

        json_success(null, '產品資料已更新');
        break;

    case 'toggle':
        if (!is_post()) json_error('只接受 POST 請求', 405);
        require_csrf();

        $id = (int)post('id');
        $newStatus = (int)post('status');

        db_exec("UPDATE products SET is_active = ? WHERE id = ?", [$newStatus, $id]);
        json_success(null, $newStatus ? '產品已啟用' : '產品已停用');
        break;

    default:
        json_error('未知的操作', 400);
}
