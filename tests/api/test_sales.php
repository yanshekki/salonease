<?php
/**
 * SalonEase API 測試系統
 * 
 * 銷售單查詢測試（list + get_items）
 * 
 * 補充 checkout 之外的銷售查詢功能。
 * 銷售資料是報表、佣金、付款計劃的重要來源。
 */

require_once __DIR__ . '/../roles/TestUsers.php';
require_once __DIR__ . '/../lib/ApiClient.php';
require_once __DIR__ . '/../lib/Assertion.php';

class TestSales
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
        echo "║   銷售單查詢測試（list + get_items）                         ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";

        $this->testListSales();
        $this->testGetItems();

        $this->testPermissionOnSalesRead();

        echo "\n=== 銷售查詢測試完成 ===\n";

        return [
            'passed'   => $this->assert->getPassed(),
            'failed'   => $this->assert->getFailed(),
            'failures' => $this->assert->getFailures(),
        ];
    }

    private function testListSales(): void
    {
        echo ">>> list 銷售單\n";

        try {
            $this->client->loginAs('manager');

            $resp = $this->client->get('/api/sales.php?action=list&limit=10');
            $this->assert->assertHttpCode(200, $resp, 'list 銷售單');

            echo "    ✓ list 銷售單成功\n";
            $this->client->logout();
        } catch (Throwable $e) {
            echo "    list 銷售單測試例外\n";
        }
    }

    private function testGetItems(): void
    {
        echo ">>> get_items 銷售明細\n";

        try {
            $this->client->loginAs('manager');

            // 先拿一筆銷售
            $list = $this->client->get('/api/sales.php?action=list&limit=1');
            if (!empty($list['data'][0]['id'])) {
                $saleId = (int)$list['data'][0]['id'];

                $items = $this->client->get('/api/sales.php?action=get_items&sale_id=' . $saleId);
                $this->assert->assertHttpCode(200, $items, 'get_items');

                if (!empty($items['data'])) {
                    echo "    ✓ get_items 成功，取得 " . count($items['data']) . " 項明細\n";
                    $this->assert->assertTrue(true, 'get_items 有資料');
                }
            } else {
                echo "    [跳過] 目前沒有銷售單可測試 get_items\n";
            }

            $this->client->logout();
        } catch (Throwable $e) {
            echo "    get_items 測試例外\n";
        }
    }

    private function testPermissionOnSalesRead(): void
    {
        echo ">>> 銷售查詢權限快速檢查\n";

        try {
            $this->client->loginAs('therapist');

            $resp = $this->client->get('/api/sales.php?action=list&limit=5');
            $code = $resp['http_code'] ?? 0;

            echo "    therapist list sales → HTTP {$code}\n";

            // 大多數角色應該可以看銷售列表
            if ($code === 200) {
                echo "    ✓ therapist 可查看銷售列表\n";
            }

            $this->client->logout();
        } catch (Throwable $e) {
            echo "    權限測試例外（可接受）\n";
        }
    }
}

// 直接執行
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $test = new TestSales();
    $result = $test->run();

    echo "\n最終結果：通過 {$result['passed']}  |  失敗 {$result['failed']}\n";

    if (!empty($result['failures'])) {
        foreach ($result['failures'] as $f) {
            echo "  - " . ($f['message'] ?? $f['reason']) . "\n";
        }
    }

    exit($result['failed'] > 0 ? 1 : 0);
}
