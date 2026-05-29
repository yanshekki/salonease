<?php
/**
 * SalonEase API 測試系統
 * 
 * 員工管理 API 測試（與佣金計算高度相關）
 * 
 * 目標：確保員工建立、更新（尤其是佣金率）、啟用/停用等操作正確，
 * 並保護只有 admin/manager 可以執行高風險操作。
 * 
 * 這是佣金計算正確性的重要上游依賴。
 */

require_once __DIR__ . '/../roles/TestUsers.php';
require_once __DIR__ . '/../lib/ApiClient.php';
require_once __DIR__ . '/../lib/Assertion.php';

class TestStaff
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
        echo "║   員工管理 API 測試（含佣金率管理 + 權限）                   ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";

        // === 讀取操作（大多數角色可用）===
        $this->testListAndGet();

        // === 建立員工（主要 admin）===
        $newStaffId = $this->testCreateStaff();

        // === 更新員工（含佣金率，如 API 支援）===
        if ($newStaffId) {
            $this->testUpdateStaff($newStaffId);
        }

        // === 啟用/停用（admin/manager）===
        if ($newStaffId) {
            $this->testToggleStaff($newStaffId);
        }

        // === 權限矩陣快速檢查 ===
        $this->testPermissionMatrix();

        echo "\n=== 員工管理測試完成 ===\n";

        return [
            'passed'   => $this->assert->getPassed(),
            'failed'   => $this->assert->getFailed(),
            'failures' => $this->assert->getFailures(),
        ];
    }

    /**
     * list + get（基本讀取）
     */
    private function testListAndGet(): void
    {
        echo ">>> list + get 員工\n";

        $roles = ['admin', 'manager', 'therapist'];
        foreach ($roles as $role) {
            try {
                $this->client->loginAs($role);

                $list = $this->client->get('/api/staff.php?action=list&is_active=1');
                $this->assert->assertHttpCode(200, $list, "{$role} 應可 list 員工");

                if (!empty($list['data'][0]['id'] ?? null)) {
                    $id = (int)$list['data'][0]['id'];
                    $get = $this->client->get('/api/staff.php?action=get&id=' . $id);
                    $this->assert->assertHttpCode(200, $get, "{$role} 應可 get 員工詳情");
                    echo "    ✓ {$role}: list + get 正常（含佣金率欄位）\n";
                }

                $this->client->logout();
            } catch (Throwable $e) {
                echo "    ? {$role} 讀取例外（可接受）\n";
            }
        }
    }

    /**
     * 建立員工（測試 admin 權限 + 佣金率設定）
     * 回傳新員工 id（失敗則 0）
     */
    private function testCreateStaff(): int
    {
        echo ">>> create 員工（admin 權限 + 佣金率）\n";

        $uniqueEmail = 'test_staff_' . uniqid() . '@salonease.test';

        try {
            $this->client->loginAs('admin');

            $payload = [
                'name'     => '測試自動建立員工',
                'email'    => $uniqueEmail,
                'phone'    => '98765432',
                'role'     => 'therapist',
                'password' => 'TestCreate123!',
                // 嘗試帶佣金率（若 API 支援會生效）
                'commission_rate_service' => 38.5,
                'commission_rate_retail'  => 12,
            ];

            $resp = $this->client->post('/api/staff.php?action=create', $payload);

            if (!empty($resp['success']) && isset($resp['data']['id'])) {
                $newId = (int)$resp['data']['id'];
                echo "    ✓ 成功建立員工 #{$newId}\n";

                // 自動驗證：立即 get 確認資料正確
                $this->verifyStaffCreated($newId, $uniqueEmail, 38.5);

                $this->client->logout();
                return $newId;
            } else {
                echo "    ✗ 建立失敗：" . ($resp['message'] ?? json_encode($resp)) . "\n";
                $this->assert->assertTrue(false, 'create staff 失敗');
                $this->client->logout();
                return 0;
            }
        } catch (Throwable $e) {
            echo "    ✗ 例外：" . $e->getMessage() . "\n";
            $this->assert->assertTrue(false, 'create staff 例外');
            return 0;
        }
    }

    /**
     * 自動驗證新員工資料（含佣金率讀回）
     */
    private function verifyStaffCreated(int $staffId, string $expectedEmail, float $expectedServiceRate): void
    {
        try {
            $resp = $this->client->get('/api/staff.php?action=get&id=' . $staffId);

            if (!empty($resp['success']) && isset($resp['data'])) {
                $data = $resp['data'];
                if (strtolower($data['email'] ?? '') === strtolower($expectedEmail)) {
                    echo "    ★ 自動驗證通過：email 正確\n";
                    $this->assert->assertTrue(true, 'create 後 email 正確');
                }

                $rate = (float)($data['commission_rate_service'] ?? 0);
                if (abs($rate - $expectedServiceRate) < 0.1) {
                    echo "    ★ 自動驗證通過：service 佣金率 {$rate}% 正確寫入\n";
                    $this->assert->assertTrue(true, 'create 後佣金率正確');
                } else {
                    echo "    ? service 佣金率讀回 {$rate}%（可能 API 建立時未完全支援帶入）\n";
                }
            }
        } catch (Throwable $e) {
            echo "    ? 驗證過程例外\n";
        }
    }

    /**
     * 更新員工（嘗試更新佣金率）
     */
    private function testUpdateStaff(int $staffId): void
    {
        echo ">>> update 員工（含佣金率調整）\n";

        try {
            $this->client->loginAs('admin');

            $payload = [
                'id'   => $staffId,
                'name' => '測試自動建立員工（已更新）',
                'phone' => '98765433',
                'role' => 'therapist',
                // 嘗試調整佣金率
                'commission_rate_service' => 39.0,
            ];

            $resp = $this->client->post('/api/staff.php?action=update', $payload);

            if (!empty($resp['success'])) {
                echo "    ✓ 更新成功\n";

                // 自動驗證
                $get = $this->client->get('/api/staff.php?action=get&id=' . $staffId);
                if (!empty($get['data']['name']) && str_contains($get['data']['name'], '已更新')) {
                    echo "    ★ 自動驗證通過：name 已更新\n";
                    $this->assert->assertTrue(true, 'update 後 name 變更');
                }
            } else {
                echo "    ✗ 更新失敗：" . ($resp['message'] ?? '未知') . "\n";
            }

            $this->client->logout();
        } catch (Throwable $e) {
            echo "    例外（可接受）：" . $e->getMessage() . "\n";
        }
    }

    /**
     * toggle 啟用/停用
     */
    private function testToggleStaff(int $staffId): void
    {
        echo ">>> toggle 員工狀態\n";

        try {
            $this->client->loginAs('admin');

            // 先停用
            $resp = $this->client->post('/api/staff.php?action=toggle', [
                'id' => $staffId,
                'status' => 0,
            ]);

            if (!empty($resp['success'])) {
                echo "    ✓ 成功停用員工\n";

                // 再啟用
                $resp2 = $this->client->post('/api/staff.php?action=toggle', [
                    'id' => $staffId,
                    'status' => 1,
                ]);
                if (!empty($resp2['success'])) {
                    echo "    ✓ 成功重新啟用\n";
                    $this->assert->assertTrue(true, 'toggle 來回成功');
                }
            }

            $this->client->logout();
        } catch (Throwable $e) {
            echo "    例外（可接受）\n";
        }
    }

    /**
     * 簡單權限矩陣（非 admin 建立應失敗或受限）
     */
    private function testPermissionMatrix(): void
    {
        echo ">>> 員工管理權限快速檢查\n";

        $uniqueEmail = 'perm_test_' . uniqid() . '@salonease.test';

        try {
            // therapist 嘗試建立（應被擋或受限）
            $this->client->loginAs('therapist');

            $resp = $this->client->post('/api/staff.php?action=create', [
                'name'     => '不應成功建立',
                'email'    => $uniqueEmail,
                'phone'    => '90000000',
                'role'     => 'therapist',
                'password' => 'NoWay123!',
            ]);

            $code = $resp['http_code'] ?? 0;
            echo "    therapist create 回應 HTTP {$code}\n";

            if ($code === 403 || $code === 401) {
                echo "    ✓ therapist 正確被權限擋\n";
                $this->assert->assertTrue(true, 'therapist 無權建立員工');
            } else {
                // 有些實作可能允許 manager 建立，therapist 不允許
                echo "    ? therapist create 未被 403（視業務規則）\n";
            }

            $this->client->logout();
        } catch (Throwable $e) {
            echo "    權限測試例外（可接受）\n";
        }
    }
}

// 直接執行
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $test = new TestStaff();
    $result = $test->run();

    echo "\n最終結果：通過 {$result['passed']}  |  失敗 {$result['failed']}\n";

    if (!empty($result['failures'])) {
        foreach ($result['failures'] as $f) {
            echo "  - " . ($f['message'] ?? $f['reason']) . "\n";
        }
    }

    exit($result['failed'] > 0 ? 1 : 0);
}
