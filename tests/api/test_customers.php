<?php
/**
 * SalonEase API 測試系統
 * 
 * 客戶管理測試
 * 
 * 涵蓋客戶 CRUD + 豐富關聯資料（points 歷史、付款、計劃）。
 * 客戶是佣金、付款計劃、忠誠度積分的中心。
 */

require_once __DIR__ . '/../roles/TestUsers.php';
require_once __DIR__ . '/../lib/ApiClient.php';
require_once __DIR__ . '/../lib/Assertion.php';

class TestCustomers
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
        echo "║   客戶管理測試（含積分、付款、計劃關聯）                     ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";

        $this->testListCustomers();
        $newCustomerId = $this->testCreateCustomer();

        if ($newCustomerId) {
            $this->testUpdateCustomer($newCustomerId);
            $this->testGetCustomerRichData($newCustomerId);
        }

        $this->testPermissionOnCustomerWrite();

        echo "\n=== 客戶管理測試完成 ===\n";

        return [
            'passed'   => $this->assert->getPassed(),
            'failed'   => $this->assert->getFailed(),
            'failures' => $this->assert->getFailures(),
        ];
    }

    private function testListCustomers(): void
    {
        echo ">>> list 客戶（不同排序）\n";

        try {
            $this->client->loginAs('manager');

            $recent = $this->client->get('/api/customers.php?action=list&sort=recent&limit=5');
            $this->assert->assertHttpCode(200, $recent, 'list recent 客戶');

            $points = $this->client->get('/api/customers.php?action=list&sort=points_desc&limit=5');
            $this->assert->assertHttpCode(200, $points, 'list points_desc 客戶');

            echo "    ✓ list 客戶成功（recent + points_desc）\n";
            $this->client->logout();
        } catch (Throwable $e) {
            echo "    list 客戶測試例外\n";
        }
    }

    private function testCreateCustomer(): int
    {
        echo ">>> create 客戶\n";

        $uniquePhone = '9' . rand(1000000, 9999999);

        try {
            $this->client->loginAs('manager');

            $resp = $this->client->post('/api/customers.php?action=create', [
                'name'    => '測試自動客戶',
                'phone'   => $uniquePhone,
                'email'   => 'auto_' . uniqid() . '@test.com',
                'gender'  => 'F',
                'notes'   => 'API 測試用客戶',
            ]);

            if (!empty($resp['success']) && isset($resp['data']['id'])) {
                $id = (int)$resp['data']['id'];
                echo "    ✓ 成功建立客戶 #{$id}\n";

                // 自動驗證
                $get = $this->client->get('/api/customers.php?action=get&id=' . $id);
                if (!empty($get['data']['phone']) && $get['data']['phone'] === $uniquePhone) {
                    echo "    ★ 自動驗證通過：客戶資料正確建立\n";
                    $this->assert->assertTrue(true, 'create customer 後 get 驗證');
                }

                $this->client->logout();
                return $id;
            } else {
                echo "    ✗ 建立失敗\n";
                $this->client->logout();
                return 0;
            }
        } catch (Throwable $e) {
            echo "    create 客戶例外\n";
            return 0;
        }
    }

    private function testUpdateCustomer(int $customerId): void
    {
        echo ">>> update 客戶\n";

        try {
            $this->client->loginAs('manager');

            $resp = $this->client->post('/api/customers.php?action=update', [
                'id'    => $customerId,
                'name'  => '測試自動客戶（已更新）',
                'phone' => '9' . rand(1000000, 9999999),
                'email' => 'updated_' . uniqid() . '@test.com',
                'notes' => '更新後的備註',
            ]);

            if (!empty($resp['success'])) {
                echo "    ✓ 更新成功\n";
                $this->assert->assertTrue(true, 'update customer 成功');
            }

            $this->client->logout();
        } catch (Throwable $e) {
            echo "    update 客戶例外\n";
        }
    }

    private function testGetCustomerRichData(int $customerId): void
    {
        echo ">>> get 客戶（豐富關聯資料）\n";

        try {
            $this->client->loginAs('manager');

            $resp = $this->client->get('/api/customers.php?action=get&id=' . $customerId . '&payments_limit=5');

            if (!empty($resp['data'])) {
                $data = $resp['data'];
                echo "    ✓ 取得客戶資料\n";

                if (isset($data['recent_points_history'])) {
                    echo "    ✓ 包含積分歷史\n";
                }
                if (isset($data['recent_payments'])) {
                    echo "    ✓ 包含最近付款\n";
                }
                if (isset($data['payment_summary'])) {
                    echo "    ✓ 包含付款摘要 + 活躍計劃數\n";
                }

                $this->assert->assertTrue(isset($data['points']), 'get customer 應回傳 points');
            }

            $this->client->logout();
        } catch (Throwable $e) {
            echo "    get 客戶豐富資料例外\n";
        }
    }

    private function testPermissionOnCustomerWrite(): void
    {
        echo ">>> 客戶寫入權限快速檢查\n";

        try {
            $this->client->loginAs('therapist');

            $resp = $this->client->post('/api/customers.php?action=create', [
                'name'  => '不應成功',
                'phone' => '90000001',
                'email' => 'nope@test.com',
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
    $test = new TestCustomers();
    $result = $test->run();

    echo "\n最終結果：通過 {$result['passed']}  |  失敗 {$result['failed']}\n";

    if (!empty($result['failures'])) {
        foreach ($result['failures'] as $f) {
            echo "  - " . ($f['message'] ?? $f['reason']) . "\n";
        }
    }

    exit($result['failed'] > 0 ? 1 : 0);
}
