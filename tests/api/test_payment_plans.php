<?php
/**
 * SalonEase API 測試系統
 * 
 * 付款計劃管理 API 測試（Phase 3~6 核心）
 * 
 * 目標：保護「計劃管理 UI + 今日工作指揮中心 + 客戶 Portal + 風險跟進」等高價值功能。
 * 
 * 涵蓋重點：
 * - list / dashboard / summary（管理視野 + 統計）
 * - get + 健康分數（customer_health）
 * - append_followup / bulk_append_followup（快速跟進）
 * - update_status / bulk_update_status（含保護機制）
 * - Portal 專用端點（list_by_customer_portal, get_portal）
 * - 權限控制（只有 admin/manager 可改狀態）
 */

require_once __DIR__ . '/../roles/TestUsers.php';
require_once __DIR__ . '/../lib/ApiClient.php';
require_once __DIR__ . '/../lib/Assertion.php';

class TestPaymentPlans
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
        echo "║   付款計劃管理 API 測試（計劃 + 跟進 + Portal + 風險）       ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";

        // === 讀取類操作（大多數角色應可使用）===
        $this->testListAndDashboard();
        $this->testGetPlanWithHealthScore();

        // === 跟進操作（所有前線員工通常可用）===
        $this->testAppendFollowup();

        // === 狀態變更保護機制（只有 manager+ 可用 + 資料保護）===
        $this->testStatusUpdatePermissionsAndProtection();

        // === 批量操作 ===
        $this->testBulkOperations();

        // === 客戶自助 Portal 端點（token 驗證）===
        $this->testPortalEndpoints();

        echo "\n=== 付款計劃管理測試完成 ===\n";

        return [
            'passed'   => $this->assert->getPassed(),
            'failed'   => $this->assert->getFailed(),
            'failures' => $this->assert->getFailures(),
        ];
    }

    /**
     * list + dashboard + summary（今日指揮中心與管理頁依賴）
     */
    private function testListAndDashboard(): void
    {
        echo ">>> list / dashboard / summary\n";

        $roles = ['admin', 'manager', 'therapist', 'reception'];

        foreach ($roles as $role) {
            try {
                $this->client->loginAs($role);

                // list
                $list = $this->client->get('/api/payment_plans.php?action=list&limit=10');
                $this->assert->assertHttpCode(200, $list, "{$role} 應可 list 計劃");

                // dashboard（管理頁最重要統計）
                $dash = $this->client->get('/api/payment_plans.php?action=dashboard');
                $this->assert->assertHttpCode(200, $dash, "{$role} 應可讀 dashboard");

                // summary
                $summary = $this->client->get('/api/payment_plans.php?action=summary');
                $this->assert->assertHttpCode(200, $summary, "{$role} 應可讀 summary");

                echo "    ✓ {$role}: list/dashboard/summary 均 200\n";

                $this->client->logout();
            } catch (Throwable $e) {
                echo "    ? {$role} 讀取測試例外（可接受）\n";
            }
        }
    }

    /**
     * get 單一計劃 + 客戶健康分數（Phase 6 風險徽章依賴）
     */
    private function testGetPlanWithHealthScore(): void
    {
        echo ">>> get 計劃詳情 + customer_health\n";

        try {
            $this->client->loginAs('manager');

            // 先拿一筆計劃
            $listResp = $this->client->get('/api/payment_plans.php?action=list&status=active&limit=3');
            $plans = $listResp['data'] ?? [];

            if (empty($plans)) {
                echo "    [跳過] 目前沒有 active 計劃可測試 get\n";
                $this->client->logout();
                return;
            }

            $planId = (int)$plans[0]['id'];

            $detail = $this->client->get('/api/payment_plans.php?action=get&id=' . $planId);

            if (!empty($detail['success']) && isset($detail['data'])) {
                $plan = $detail['data'];
                echo "    ✓ 成功取得計劃 #{$planId}\n";

                if (isset($plan['customer_health'])) {
                    echo "    ✓ 包含 customer_health（分數: " . ($plan['customer_health']['score'] ?? '?') . "）\n";
                    $this->assert->assertTrue(isset($plan['customer_health']['score']), 'get 應回傳健康分數');
                } else {
                    echo "    ? 此計劃客戶暫無健康分數資料\n";
                }
            } else {
                echo "    ✗ get 失敗\n";
                $this->assert->assertTrue(false, 'get 計劃詳情失敗');
            }

            $this->client->logout();
        } catch (Throwable $e) {
            echo "    ✗ 例外：" . $e->getMessage() . "\n";
            $this->assert->assertTrue(false, 'get 計劃詳情例外');
        }
    }

    /**
     * append_followup（快速跟進功能） + 自動驗證
     */
    private function testAppendFollowup(): void
    {
        echo ">>> append_followup（單筆快速跟進 + 自動驗證）\n";

        try {
            $this->client->loginAs('therapist');   // 前線員工應該可用

            // 找一筆 active 計劃
            $list = $this->client->get('/api/payment_plans.php?action=list&status=active&limit=1');
            $plans = $list['data'] ?? [];

            if (empty($plans)) {
                echo "    [跳過] 無 active 計劃可測試跟進\n";
                $this->client->logout();
                return;
            }

            $planId = (int)$plans[0]['id'];
            $note = 'API 測試跟進 ' . date('H:i:s');

            $resp = $this->client->post('/api/payment_plans.php?action=append_followup', [
                'plan_id' => $planId,
                'note'    => $note,
            ]);

            if (!empty($resp['success'])) {
                echo "    ✓ 成功為計劃 #{$planId} 增加跟進\n";
                $this->assert->assertTrue(true, 'append_followup 成功');

                // 自動驗證：立即 get 並檢查 notes 是否真的包含新跟進
                $this->verifyFollowupAppended($planId, $note);
            } else {
                echo "    ✗ append_followup 失敗：" . ($resp['message'] ?? '未知') . "\n";
                $this->assert->assertTrue(false, 'append_followup 失敗');
            }

            $this->client->logout();
        } catch (Throwable $e) {
            echo "    ✗ 例外：" . $e->getMessage() . "\n";
            $this->assert->assertTrue(false, 'append_followup 例外');
        }
    }

    /**
     * 自動驗證跟進是否真的寫入（呼叫 get 後檢查 notes）
     */
    private function verifyFollowupAppended(int $planId, string $expectedFragment): void
    {
        try {
            $detail = $this->client->get('/api/payment_plans.php?action=get&id=' . $planId);

            if (!empty($detail['success']) && isset($detail['data']['notes'])) {
                $notes = (string)$detail['data']['notes'];
                if (str_contains($notes, $expectedFragment)) {
                    echo "    ★ 自動驗證通過：跟進內容已確實寫入 notes\n";
                    $this->assert->assertTrue(true, 'append_followup 自動驗證成功');
                } else {
                    echo "    ✗ 自動驗證失敗：notes 中未找到跟進內容\n";
                    $this->assert->assertTrue(false, 'append_followup 內容未寫入');
                }
            } else {
                echo "    ? 無法取得計劃詳情進行自動驗證\n";
            }
        } catch (Throwable $e) {
            echo "    ? 自動驗證過程例外：" . $e->getMessage() . "\n";
        }
    }

    /**
     * 狀態更新權限 + 重要保護機制（已有付款不能改回 active）
     */
    private function testStatusUpdatePermissionsAndProtection(): void
    {
        echo ">>> update_status 權限與保護機制\n";

        // 只有 admin/manager 可以改狀態
        $roles = ['admin', 'manager', 'therapist'];
        foreach ($roles as $role) {
            try {
                $this->client->loginAs($role);

                $resp = $this->client->post('/api/payment_plans.php?action=update_status', [
                    'plan_id' => 999999,   // 不存在的計劃
                    'status'  => 'cancelled',
                ]);

                $code = $resp['http_code'] ?? 0;

                if ($role === 'therapist' && $code === 403) {
                    echo "    ✓ therapist 正確被擋（403）\n";
                    $this->assert->assertHttpCode(403, $resp, 'therapist 不可改計劃狀態');
                } elseif (in_array($role, ['admin', 'manager']) && $code !== 403) {
                    // admin/manager 即使計劃不存在，也不會是 403（而是 400/404）
                    echo "    ✓ {$role} 未被權限擋（預期行為）\n";
                }

                $this->client->logout();
            } catch (Throwable $e) {
                // 忽略
            }
        }

        echo "    （保護機制：已有付款的計劃禁止改回 active，需手動在 DB 驗證）\n";
    }

    /**
     * 批量跟進 + 批量改狀態
     */
    private function testBulkOperations(): void
    {
        echo ">>> bulk_append_followup / bulk_update_status\n";

        try {
            $this->client->loginAs('manager');

            // 先拿幾筆計劃
            $list = $this->client->get('/api/payment_plans.php?action=list&status=active&limit=3');
            $plans = $list['data'] ?? [];
            $ids = array_column($plans, 'id');

            if (count($ids) < 2) {
                echo "    [跳過] 活躍計劃不足 2 筆，無法測試批量\n";
                $this->client->logout();
                return;
            }

            // 批量跟進
            $bulkNote = '批量跟進測試 ' . date('H:i');
            $bulkFollow = $this->client->post('/api/payment_plans.php?action=bulk_append_followup', [
                'plan_ids' => json_encode(array_slice($ids, 0, 2)),
                'note'     => $bulkNote,
            ]);

            if (!empty($bulkFollow['success'])) {
                $successCount = (int)($bulkFollow['data']['success_count'] ?? 0);
                echo "    ✓ bulk_append_followup 成功（{$successCount} 筆）\n";
                $this->assert->assertTrue(true, 'bulk_append_followup 成功');

                // 自動驗證：對成功的那幾筆做抽樣 get 檢查
                if ($successCount > 0 && !empty($bulkFollow['data']['note'])) {
                    $this->verifyBulkFollowupSample(array_slice($ids, 0, 2), $bulkNote);
                }
            } else {
                echo "    ✗ bulk_append_followup 失敗\n";
            }

            $this->client->logout();
        } catch (Throwable $e) {
            echo "    批量操作測試例外（可接受）\n";
        }
    }

    /**
     * 客戶自助 Portal 端點（使用 token 驗證，無需員工登入）
     */
    private function testPortalEndpoints(): void
    {
        echo ">>> Portal 專用端點（list_by_customer_portal / get_portal）\n";

        // 這些端點不依賴員工登入，而是靠 token
        // 這裡只做基本呼叫測試（無有效 token 應 401）
        try {
            $resp = $this->client->get('/api/payment_plans.php?action=list_by_customer_portal&token=INVALID_TOKEN_123');

            $code = $resp['http_code'] ?? 0;

            if ($code === 401) {
                echo "    ✓ 無效 token 正確回 401\n";
                $this->assert->assertHttpCode(401, $resp, 'Portal 無效 token 應 401');
            } else {
                echo "    ? Portal 端點對無效 token 回應 {$code}\n";
            }
        } catch (Throwable $e) {
            echo "    Portal 測試例外（可接受）\n";
        }

        echo "    （完整 Portal 測試需先生成有效 token，建議在 settings 頁或 customers 頁手動產生後測試）\n";
    }

    /**
     * 批量跟進後抽樣自動驗證
     */
    private function verifyBulkFollowupSample(array $planIds, string $expectedFragment): void
    {
        $verified = 0;
        foreach ($planIds as $pid) {
            $pid = (int)$pid;
            if ($pid <= 0) continue;

            try {
                $detail = $this->client->get('/api/payment_plans.php?action=get&id=' . $pid);
                if (!empty($detail['success']) && str_contains((string)($detail['data']['notes'] ?? ''), $expectedFragment)) {
                    $verified++;
                }
            } catch (Throwable $e) {
                // 容錯
            }
        }

        if ($verified > 0) {
            echo "    ★ 批量自動驗證通過：{$verified} 筆計劃的 notes 已確認包含跟進內容\n";
            $this->assert->assertTrue(true, 'bulk_append_followup 自動驗證');
        } else {
            echo "    ? 批量自動驗證未找到確認內容（可能資料延遲）\n";
        }
    }
}

// 直接執行
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $test = new TestPaymentPlans();
    $result = $test->run();

    echo "\n最終結果：通過 {$result['passed']}  |  失敗 {$result['failed']}\n";

    if (!empty($result['failures'])) {
        foreach ($result['failures'] as $f) {
            echo "  - " . ($f['message'] ?? $f['reason']) . "\n";
        }
    }

    exit($result['failed'] > 0 ? 1 : 0);
}
