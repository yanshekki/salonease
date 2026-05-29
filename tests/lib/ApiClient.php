<?php
/**
 * SalonEase API 測試系統 - API 客戶端
 * 
 * 負責與 API 進行互動，支援多角色登入切換。
 * 使用 cURL 處理 Cookie 以維持 session。
 */

class ApiClient
{
    private string $baseUrl;
    private ?string $cookieFile = null;
    private ?array $currentUser = null;
    private bool $debug = false;

    public function __construct(string $baseUrl = 'https://salonease.ysk.hk')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * 設定是否開啟 debug 模式（輸出請求細節）
     */
    public function setDebug(bool $debug): void
    {
        $this->debug = $debug;
    }

    /**
     * 使用指定角色登入
     */
    public function loginAs(string $role): bool
    {
        $user = TestUsers::get($role);
        
        // 建立臨時 cookie 檔案
        if ($this->cookieFile) {
            @unlink($this->cookieFile);
        }
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'salonease_test_cookie_');

        $response = $this->post('/api/auth.php?action=login', [
            'email'    => $user['email'],
            'password' => $user['password'],
        ]);

        if ($response['success'] ?? false) {
            $this->currentUser = $user;
            $this->currentUser['role'] = $role;
            if ($this->debug) {
                echo "[ApiClient] 已登入為 {$role} ({$user['name']})\n";
            }
            return true;
        }

        throw new Exception("登入失敗 [{$role}]: " . ($response['message'] ?? '未知錯誤'));
    }

    /**
     * 登出目前用戶
     */
    public function logout(): void
    {
        if ($this->currentUser) {
            $this->post('/api/auth.php?action=logout', []);
            $this->currentUser = null;
        }
    }

    /**
     * 取得目前登入用戶資訊
     */
    public function getCurrentUser(): ?array
    {
        return $this->currentUser;
    }

    /**
     * 發送 GET 請求
     */
    public function get(string $path, array $params = []): array
    {
        $url = $this->baseUrl . $path;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $this->request('GET', $url);
    }

    /**
     * 發送 POST 請求
     */
    public function post(string $path, array $data = []): array
    {
        $url = $this->baseUrl . $path;
        return $this->request('POST', $url, $data);
    }

    /**
     * 核心請求方法
     */
    private function request(string $method, string $url, array $postData = []): array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($this->debug) {
            echo "[ApiClient] {$method} {$url} | HTTP {$httpCode}\n";
            if (!empty($postData)) {
                echo "  POST Data: " . json_encode($postData, JSON_UNESCAPED_UNICODE) . "\n";
            }
        }

        if ($error) {
            throw new Exception("cURL 錯誤: {$error}");
        }

        $decoded = json_decode($response, true);

        if ($decoded === null) {
            // 某些錯誤可能不是 JSON
            return [
                'success' => false,
                'http_code' => $httpCode,
                'raw_response' => $response,
                'message' => '回應不是有效的 JSON'
            ];
        }

        $decoded['http_code'] = $httpCode;
        return $decoded;
    }

    /**
     * 根據 email 查找對應的 staff_id（測試常用）
     */
    public function getStaffIdByEmail(string $email): ?int
    {
        try {
            $resp = $this->get('/api/staff.php?action=list&search=' . urlencode($email));
            if (!empty($resp['success']) && is_array($resp['data'] ?? null)) {
                foreach ($resp['data'] as $s) {
                    if (strtolower($s['email'] ?? '') === strtolower($email)) {
                        return (int)$s['id'];
                    }
                }
            }
        } catch (Throwable $e) {
            // 測試環境容錯
        }
        return null;
    }

    /**
     * 清理 cookie 檔案
     */
    public function __destruct()
    {
        if ($this->cookieFile && file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }
}
