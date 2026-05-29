<?php
/**
 * SalonEase API 測試系統
 * 
 * 權限矩陣測試（最高嚴謹度要求之一）
 * 
 * 目標：系統性驗證不同角色對敏感 API 的存取控制是否正確。
 * 防止權限提升漏洞、誤開放管理功能給一般員工等嚴重問題。
 * 
 * 測試原則：
 * - 每個重要 action 都要有 admin/manager/therapist/reception 的 200 或 403 預期
 * - 使用 ApiClient 的多角色隔離 Cookie 機制
 * - 失敗時清楚顯示「哪個角色不應該有權限卻成功」或「應該有權限卻被拒」
 */

require_once __DIR__ . '/../roles/TestUsers.php';
require_once __DIR__ . '/../lib/ApiClient.php';
require_once __DIR__ . '/../lib/Assertion.php';

class TestPermissionsMatrix
{
    private ApiClient $client;
    private Assertion $assert;

    /** @var array<string, array{path: string, method: string, data?: array, expect: array<string, int>}> */
    private array $testCases;

    public function __construct()
    {
        $this->client = new ApiClient();
        $this->client->setDebug(false);
        $this->assert = new Assertion();

        $this->defineTestCases();
    }

    public function run(): array
    {
        echo "╔════════════════════════════════════════════════════════════╗\n";
        echo "║   權限矩陣測試 (Permissions Matrix)                          ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";

        $roles = ['admin', 'manager', 'therapist', 'reception'];

        foreach ($roles as $role) {
            echo "\n>>> 測試角色：{$role}\n";
            try {
                if (!$this->client->loginAs($role)) {
                    echo "    [跳過] 登入失敗\n";
                    continue;
                }
            } catch (Throwable $e) {
                echo "    [跳過] 登入例外：{$e->getMessage()}\n";
                continue;
            }

            foreach ($this->testCases as $name => $case) {
                $this->runOneCase($role, $name, $case);
            }

            $this->client->logout();
        }

        echo "\n=== 權限矩陣測試完成 ===\n";

        return [
            'passed' => $this->assert->getPassed(),
            'failed' => $this->assert->getFailed(),
            'failures' => $this->assert->getFailures(),
        ];
    }

    private function defineTestCases(): void
    {
        $this->testCases = [
            // === 銷售相關（前線員工應該可以做）===
            'sales.checkout' => [
                'path'   => '/api/sales.php?action=checkout',
                'method' => 'POST',
                'data'   => $this->getMinimalCheckoutPayload(),
                'expect' => [
                    'admin'     => 200,
                    'manager'   => 200,
                    'therapist' => 200,
                    'reception' => 200,   // 前台通常可收銀
                ],
            ],

            // === 設定（只有 admin 可改全局佣金率等）===
            'settings.save' => [
                'path'   => '/api/settings.php?action=save',
                'method' => 'POST',
                'data'   => [
                    'default_commission_service' => 40,
                    'default_commission_retail'  => 15,
                    'default_commission_open'    => 5,
                ],
                'expect' => [
                    'admin'     => 200,
                    'manager'   => 403,
                    'therapist' => 403,
                    'reception' => 403,
                ],
            ],

            // === 員工管理（極高風險，只有 admin）===
            'staff.create' => [
                'path'   => '/api/staff.php?action=create',
                'method' => 'POST',
                'data'   => [
                    'name'     => '測試臨時員工',
                    'email'    => 'temp_test_' . uniqid() . '@salonease.test',
                    'phone'    => '91234567',
                    'role'     => 'therapist',
                    'password' => 'TempPass123!',
                ],
                'expect' => [
                    'admin'     => 200,
                    'manager'   => 403,
                    'therapist' => 403,
                    'reception' => 403,
                ],
            ],

            'staff.toggle' => [
                'path'   => '/api/staff.php?action=toggle',
                'method' => 'POST',
                'data'   => ['id' => 2, 'status' => 1],   // 嘗試啟用 id=2（測試帳號）
                'expect' => [
                    'admin'     => 200,
                    'manager'   => 403,
                    'therapist' => 403,
                    'reception' => 403,
                ],
            ],

            // === 佣金報表（admin + manager 可看）===
            'commissions.summary' => [
                'path'   => '/api/commissions.php?action=summary&from=' . date('Y-m-d') . '&to=' . date('Y-m-d'),
                'method' => 'GET',
                'expect' => [
                    'admin'     => 200,
                    'manager'   => 200,
                    'therapist' => 403,
                    'reception' => 403,
                ],
            ],

            // === 付款計劃相關（前線員工通常需要操作）===
            'payment_plans.list' => [
                'path'   => '/api/payment_plans.php?action=list&limit=5',
                'method' => 'GET',
                'expect' => [
                    'admin'     => 200,
                    'manager'   => 200,
                    'therapist' => 200,
                    'reception' => 200,
                ],
            ],

            // === 提醒系統（大多數角色可操作）===
            'plan_reminders.stats' => [
                'path'   => '/api/plan_reminders.php?action=stats',
                'method' => 'GET',
                'expect' => [
                    'admin'     => 200,
                    'manager'   => 200,
                    'therapist' => 200,
                    'reception' => 200,
                ],
            ],
        ];
    }

    private function runOneCase(string $role, string $name, array $case): void
    {
        $expectedCode = $case['expect'][$role] ?? 0;

        try {
            if ($case['method'] === 'POST') {
                $resp = $this->client->post($case['path'], $case['data'] ?? []);
            } else {
                $resp = $this->client->get($case['path']);
            }

            $actualCode = $resp['http_code'] ?? 0;

            $this->assert->assertHttpCode($expectedCode, $resp, "權限測試失敗：{$role} → {$name}");

            $status = ($actualCode === $expectedCode) ? '✓' : '✗';
            echo "    {$status} {$name}  → 預期 {$expectedCode}，實際 {$actualCode}\n";

        } catch (Throwable $e) {
            // 某些情況下 cURL 會拋例外（例如 403 時伺服器可能回傳非 JSON）
            // 這裡做寬鬆處理，只記錄
            echo "    ? {$name}  → 例外/非預期回應: " . $e->getMessage() . "\n";
            // 仍算一次失敗，讓整體報告反映問題
            $this->assert->assertHttpCode($expectedCode, ['http_code' => 0], "例外: {$role} → {$name}");
        }
    }

    /**
     * 產生一個最小可用的結帳 payload（避免 400 錯誤干擾權限測試）
     */
    private function getMinimalCheckoutPayload(): array
    {
        return [
            'customer_id'     => 1,
            'items'           => [
                [
                    'type'       => 'service',
                    'ref_id'     => 1,
                    'name'       => '權限測試用服務',
                    'unit_price' => '100.00',
                    'qty'        => 1,
                ],
            ],
            'discount'        => 0,
            'points_used'     => 0,
            'payment_mode'    => 'full',
            'payment_method_id' => 1,
            'notes'           => 'PERMISSION_TEST_' . date('His'),
        ];
    }
}

// 直接執行支援
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $test = new TestPermissionsMatrix();
    $result = $test->run();

    echo "\n最終結果：通過 {$result['passed']}  |  失敗 {$result['failed']}\n";

    if (!empty($result['failures'])) {
        echo "\n失敗詳情：\n";
        foreach ($result['failures'] as $f) {
            echo "  - " . ($f['message'] ?? $f['reason']) . "\n";
        }
    }

    exit($result['failed'] > 0 ? 1 : 0);
}
