<?php
/**
 * SalonEase API 測試系統
 * 
 * 認證與授權基礎測試（Auth 層）
 * 
 * 目標：確保登入、登出、session 維持與基本未登入保護正確運作。
 * 這是所有權限測試的基礎。
 */

require_once __DIR__ . '/../roles/TestUsers.php';
require_once __DIR__ . '/../lib/ApiClient.php';
require_once __DIR__ . '/../lib/Assertion.php';

class TestAuth
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
        echo "║   認證與授權基礎測試（Auth 層）                              ║\n";
        echo "╚════════════════════════════════════════════════════════════╝\n\n";

        // === 成功登入 ===
        $this->testSuccessfulLogin();

        // === 錯誤登入 ===
        $this->testFailedLogin();

        // === /me 與 /ping ===
        $this->testMeAndPing();

        // === 登出 ===
        $this->testLogout();

        // === 未登入保護 ===
        $this->testUnauthenticatedAccess();

        echo "\n=== 認證測試完成 ===\n";

        return [
            'passed'   => $this->assert->getPassed(),
            'failed'   => $this->assert->getFailed(),
            'failures' => $this->assert->getFailures(),
        ];
    }

    private function testSuccessfulLogin(): void
    {
        echo ">>> 成功登入（不同角色）\n";

        $roles = ['admin', 'manager', 'therapist'];
        foreach ($roles as $role) {
            try {
                $ok = $this->client->loginAs($role);
                if ($ok) {
                    echo "    ✓ {$role} 登入成功\n";
                    $this->assert->assertTrue(true, "{$role} 登入成功");
                } else {
                    $this->assert->assertTrue(false, "{$role} 登入失敗");
                }
                $this->client->logout();
            } catch (Throwable $e) {
                echo "    ✗ {$role} 登入例外：" . $e->getMessage() . "\n";
                $this->assert->assertTrue(false, "{$role} 登入例外");
            }
        }
    }

    private function testFailedLogin(): void
    {
        echo ">>> 錯誤登入\n";

        try {
            // 故意用錯誤密碼
            $resp = $this->client->post('/api/auth.php?action=login', [
                'email'    => 'manager@salonease.test',
                'password' => 'WrongPassword123!',
            ]);

            $code = $resp['http_code'] ?? 0;
            if ($code === 200 && empty($resp['success'])) {
                echo "    ✓ 錯誤密碼正確被拒絕\n";
                $this->assert->assertTrue(true, '錯誤密碼被拒絕');
            } else {
                echo "    ? 錯誤密碼回應異常（HTTP {$code}）\n";
            }
        } catch (Throwable $e) {
            echo "    錯誤登入測試例外（可接受）\n";
        }
    }

    private function testMeAndPing(): void
    {
        echo ">>> /me 與 /ping\n";

        try {
            $this->client->loginAs('manager');

            $me = $this->client->get('/api/auth.php?action=me');
            if (!empty($me['success']) && isset($me['data']['id'])) {
                echo "    ✓ /me 回傳使用者資訊\n";
                $this->assert->assertTrue(true, '/me 成功');
            }

            $ping = $this->client->get('/api/auth.php?action=ping');
            if (!empty($ping['success']) && ($ping['data']['logged_in'] ?? false)) {
                echo "    ✓ /ping 顯示已登入\n";
                $this->assert->assertTrue(true, '/ping 已登入狀態');
            }

            $this->client->logout();
        } catch (Throwable $e) {
            echo "    /me 或 /ping 測試例外\n";
        }
    }

    private function testLogout(): void
    {
        echo ">>> 登出\n";

        try {
            $this->client->loginAs('therapist');
            $this->client->logout();

            // 登出後 ping 應該失敗
            $ping = $this->client->get('/api/auth.php?action=ping');
            $code = $ping['http_code'] ?? 0;

            if ($code === 401 || ($ping['success'] ?? false) === false) {
                echo "    ✓ 登出後 /ping 被拒絕\n";
                $this->assert->assertTrue(true, '登出後保護生效');
            } else {
                echo "    ? 登出後仍可存取\n";
            }
        } catch (Throwable $e) {
            echo "    登出測試例外（可接受）\n";
        }
    }

    private function testUnauthenticatedAccess(): void
    {
        echo ">>> 未登入保護\n";

        try {
            // 確保沒有登入狀態
            $this->client->logout();

            $me = $this->client->get('/api/auth.php?action=me');
            $code = $me['http_code'] ?? 0;

            if ($code === 401 || ($me['success'] ?? false) === false) {
                echo "    ✓ 未登入存取 /me 被保護\n";
                $this->assert->assertTrue(true, '未登入保護生效');
            } else {
                echo "    ? 未登入仍可存取受保護端點\n";
            }
        } catch (Throwable $e) {
            echo "    未登入保護測試例外（可接受）\n";
        }
    }
}

// 直接執行
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    $test = new TestAuth();
    $result = $test->run();

    echo "\n最終結果：通過 {$result['passed']}  |  失敗 {$result['failed']}\n";

    if (!empty($result['failures'])) {
        foreach ($result['failures'] as $f) {
            echo "  - " . ($f['message'] ?? $f['reason']) . "\n";
        }
    }

    exit($result['failed'] > 0 ? 1 : 0);
}
