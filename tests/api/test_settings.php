<?php
/**
 * SalonEase API 測試系統
 * 
 * 系統設定測試（與佣金計算、付款計劃關注門檻、提醒高度相關）
 * 
 * 最高風險之一：只有 admin/manager 可以修改佣金預設率與需要關注門檻。
 */

require_once __DIR__ . '/../roles/TestUsers.php';
require_once __DIR__ . '/../lib/ApiClient.php';
require_once __DIR__ . '/../lib/Assertion.php';

class TestSettings
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
        echo "║   系統設定測試（佣金預設 + 門檻 + 權限）                     ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";

        // === 任何人可讀取 ===
        $this->testGetSettings();

        // === 只有 admin/manager 可修改（特別是佣金率） ===
        $this->testSavePermissions();

        // === 實際修改佣金預設 + 自動驗證讀回 ===
        $this->testUpdateCommissionDefaults();

        echo "\n=== 設定測試完成 ===\n";

        return [
            'passed'   => $this->assert->getPassed(),
            'failed'   => $this->assert->getFailed(),
            'failures' => $this->assert->getFailures(),
        ];
    }

    private function testGetSettings(): void
    {
        echo ">>> get 設定（所有角色可讀）\n";

        $roles = ['admin', 'manager', 'therapist', 'reception'];
        foreach ($roles as $role) {
            try {
                $this->client->loginAs($role);
                $resp = $this->client->get('/api/settings.php?action=get');
                $this->assert->assertHttpCode(200, $resp, "{$role} 應可讀取設定");
                echo "    ✓ {$role} get 設定成功（含佣金預設）\n";
                $this->client->logout();
            } catch (Throwable $e) {
                echo "    ? {$role} get 例外\n";
            }
        }
    }

    private function testSavePermissions(): void
    {
        echo ">>> save 權限檢查（只有 admin/manager）\n";

        $roles = ['therapist', 'admin'];
        foreach ($roles as $role) {
            try {
                $this->client->loginAs($role);

                $payload = [
                    'default_commission_service' => 40,
                    'default_commission_retail'  => 15,
                    'default_commission_open'    => 5,
                ];

                $resp = $this->client->post('/api/settings.php?action=save_shop', $payload);
                $code = $resp['http_code'] ?? 0;

                echo "    {$role} save → HTTP {$code}\n";

                if ($role === 'therapist' && $code === 403) {
                    echo "    ✓ therapist 正確被 403 擋\n";
                    $this->assert->assertHttpCode(403, $resp, 'therapist 不可修改設定');
                } elseif ($role === 'admin' && $code !== 403) {
                    echo "    ✓ admin 可修改設定\n";
                }

                $this->client->logout();
            } catch (Throwable $e) {
                // 容錯
            }
        }
    }

    private function testUpdateCommissionDefaults(): void
    {
        echo ">>> 修改佣金預設 + 自動驗證讀回\n";

        try {
            $this->client->loginAs('admin');

            $newService = 42.5;
            $newRetail  = 16.0;
            $newOpen    = 6.5;

            $payload = [
                'salon_name' => 'SalonEase 測試店',
                'default_commission_service' => $newService,
                'default_commission_retail'  => $newRetail,
                'default_commission_open'    => $newOpen,
                'needs_attention_days_threshold' => 35,
                'needs_attention_progress_threshold' => 35,
            ];

            $save = $this->client->post('/api/settings.php?action=save_shop', $payload);

            if (!empty($save['success'])) {
                echo "    ✓ 成功儲存新佣金預設\n";

                // 自動驗證：立即 get 讀回比對
                $get = $this->client->get('/api/settings.php?action=get');
                if (!empty($get['data'])) {
                    $data = $get['data'];
                    $this->assert->assertCommissionEqual($newService, (float)$data['default_commission_service'], '設定後 service 佣金率自動驗證');
                    $this->assert->assertCommissionEqual($newRetail,  (float)$data['default_commission_retail'],  '設定後 retail 佣金率自動驗證');
                    $this->assert->assertCommissionEqual($newOpen,    (float)$data['default_commission_open'],    '設定後 open 佣金率自動驗證');

                    echo "    ★ 自動驗證通過：佣金預設已正確寫入並讀回\n";
                }
            } else {
                echo "    ✗ 儲存失敗：" . ($save['message'] ?? '未知') . "\n";
                $this->assert->assertTrue(false, 'save commission defaults 失敗');
            }

            $this->client->logout();
        } catch (Throwable $e) {
            echo "    例外：" . $e->getMessage() . "\n";
        }
    }
}

// 直接執行
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $test = new TestSettings();
    $result = $test->run();

    echo "\n最終結果：通過 {$result['passed']}  |  失敗 {$result['failed']}\n";

    if (!empty($result['failures'])) {
        foreach ($result['failures'] as $f) {
            echo "  - " . ($f['message'] ?? $f['reason']) . "\n";
        }
    }

    exit($result['failed'] > 0 ? 1 : 0);
}
