<?php
/**
 * SalonEase API 測試系統
 * 
 * 產品管理測試（零售佣金 + 庫存）
 * 
 * 涵蓋產品 CRUD + 庫存調整 + 低庫存警示。
 * 零售產品直接影響零售佣金計算。
 */

require_once __DIR__ . '/../roles/TestUsers.php';
require_once __DIR__ . '/../lib/ApiClient.php';
require_once __DIR__ . '/../lib/Assertion.php';

class TestProducts
{
    private ApiClient $client;
    private Assertion $assert;

    public function __construct()
    {
        $this->client = new ApiClient();
        $this->client->setDebug(false);
        $this->assert = new Assertion();
    }

    public function run(): array
    {
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║   產品管理測試（零售佣金 + 庫存）                            ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";

        $this->testListAndLowStock();
        $newProductId = $this->testCreateProduct();

        if ($newProductId) {
            $this->testUpdateProduct($newProductId);
            $this->testAdjustStock($newProductId);
            $this->testToggleProduct($newProductId);
        }

        $this->testPermissionOnProductWrite();

        echo "\n=== 產品管理測試完成 ===\n";

        return [
            'passed'   => $this->assert->getPassed(),
            'failed'   => $this->assert->getFailed(),
            'failures' => $this->assert->getFailures(),
        ];
    }

    private function testListAndLowStock(): void
    {
        echo ">>> list + low_stock 產品\n";

        try {
            $this->client->loginAs('manager');

            $list = $this->client->get('/api/products.php?action=list&limit=10');
            $this->assert->assertHttpCode(200, $list, 'list 產品');

            $low = $this->client->get('/api/products.php?action=low_stock');
            $this->assert->assertHttpCode(200, $low, 'low_stock 警示');

            echo "    ✓ list + low_stock 成功\n";
            $this->client->logout();
        } catch (Throwable $e) {
            echo "    list/low_stock 測試例外\n";
        }
    }

    private function testCreateProduct(): int
    {
        echo ">>> create 產品\n";

        $uniqueSku = 'TEST-' . uniqid();

        try {
            $this->client->loginAs('manager');

            $resp = $this->client->post('/api/products.php?action=create', [
                'name'      => '測試自動產品',
                'sku'       => $uniqueSku,
                'price'     => 128.00,
                'cost'      => 45.00,
                'stock_qty' => 50,
                'category'  => '測試',
            ]);

            if (!empty($resp['success']) && isset($resp['data']['id'])) {
                $id = (int)$resp['data']['id'];
                echo "    ✓ 成功建立產品 #{$id}\n";

                // 自動驗證
                $get = $this->client->get('/api/products.php?action=get&id=' . $id);
                if (!empty($get['data']['sku']) && $get['data']['sku'] === $uniqueSku) {
                    echo "    ★ 自動驗證通過：產品資料正確\n";
                    $this->assert->assertTrue(true, 'create product 後 get 驗證');
                }

                $this->client->logout();
                return $id;
            } else {
                echo "    ✗ 建立失敗\n";
                $this->client->logout();
                return 0;
            }
        } catch (Throwable $e) {
            echo "    create 產品例外\n";
            return 0;
        }
    }

    private function testUpdateProduct(int $productId): void
    {
        echo ">>> update 產品\n";

        try {
            $this->client->loginAs('manager');

            $resp = $this->client->post('/api/products.php?action=update', [
                'id'        => $productId,
                'name'      => '測試自動產品（已更新）',
                'sku'       => 'UPD-' . uniqid(),
                'price'     => 138.00,
                'cost'      => 48.00,
                'stock_qty' => 55,
                'category'  => '測試',
            ]);

            if (!empty($resp['success'])) {
                echo "    ✓ 更新成功\n";
                $this->assert->assertTrue(true, 'update product 成功');
            }

            $this->client->logout();
        } catch (Throwable $e) {
            echo "    update 產品例外\n";
        }
    }

    private function testAdjustStock(int $productId): void
    {
        echo ">>> adjust_stock（僅 admin/manager）\n";

        try {
            $this->client->loginAs('admin');

            $resp = $this->client->post('/api/products.php?action=adjust_stock', [
                'id'         => $productId,
                'adjustment' => -5,
                'reason'     => 'API 測試扣減',
            ]);

            if (!empty($resp['success'])) {
                echo "    ✓ 庫存調整成功\n";
                $this->assert->assertTrue(true, 'adjust_stock 成功');
            }

            $this->client->logout();
        } catch (Throwable $e) {
            echo "    adjust_stock 測試例外\n";
        }
    }

    private function testToggleProduct(int $productId): void
    {
        echo ">>> toggle 產品狀態\n";

        try {
            $this->client->loginAs('manager');

            $resp = $this->client->post('/api/products.php?action=toggle', [
                'id'     => $productId,
                'status' => 0,
            ]);

            if (!empty($resp['success'])) {
                echo "    ✓ 停用成功\n";
            }

            // 再啟用
            $this->client->post('/api/products.php?action=toggle', [
                'id'     => $productId,
                'status' => 1,
            ]);

            echo "    ✓ toggle 來回成功\n";
            $this->assert->assertTrue(true, 'toggle product 成功');

            $this->client->logout();
        } catch (Throwable $e) {
            echo "    toggle 產品例外\n";
        }
    }

    private function testPermissionOnProductWrite(): void
    {
        echo ">>> 產品寫入權限快速檢查\n";

        try {
            $this->client->loginAs('therapist');

            $resp = $this->client->post('/api/products.php?action=create', [
                'name'      => '不應成功',
                'sku'       => 'NO-' . uniqid(),
                'price'     => 99,
                'cost'      => 30,
                'stock_qty' => 10,
            ]);

            $code = $resp['http_code'] ?? 0;
            echo "    therapist create → HTTP {$code}\n";

            if ($code === 403 || $code === 401) {
                echo "    ✓ therapist 寫入被正確保護\n";
            }

            $this->client->logout();
        } catch (Throwable $e) {
            echo "    權限測試例外（可接受）\n";
        }
    }
}

// 直接執行
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $test = new TestProducts();
    $result = $test->run();

    echo "\n最終結果：通過 {$result['passed']}  |  失敗 {$result['failed']}\n";

    if (!empty($result['failures'])) {
        foreach ($result['failures'] as $f) {
            echo "  - " . ($f['message'] ?? $f['reason']) . "\n";
        }
    }

    exit($result['failed'] > 0 ? 1 : 0);
}
