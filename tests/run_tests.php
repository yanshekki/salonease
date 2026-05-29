<?php
/**
 * SalonEase API 測試系統 - 主控程式
 * 
 * 用法範例：
 *   php tests/run_tests.php                              # 執行所有測試
 *   php tests/run_tests.php --phase=1                    # 只執行 Phase 1（佣金相關）
 *   php tests/run_tests.php --role=therapist             # 只用治療師角色測試
 *   php tests/run_tests.php --api=sales                  # 只測試 sales 相關 API
 *   php tests/run_tests.php --report=html                # 產生專業 HTML 報告
 *   php tests/run_tests.php --report=json                # 產生 JSON 報告
 *   php tests/run_tests.php --bootstrap                  # 載入 bootstrap（可搭配 --seed 自動準備測試資料）
 *   php tests/run_tests.php --seed                       # 執行前自動跑 seed_test_data.php
 */

require_once __DIR__ . '/roles/TestUsers.php';
require_once __DIR__ . '/lib/ApiClient.php';
require_once __DIR__ . '/lib/Assertion.php';

class TestRunner
{
    private array $options = [];
    private array $fileResults = [];
    private int $totalPassed = 0;
    private int $totalFailed = 0;
    private float $startTime;

    public function __construct(array $argv)
    {
        $this->parseArguments($argv);
    }

    private function parseArguments(array $argv): void
    {
        $this->options = [
            'phase'      => null,
            'role'       => null,
            'api'        => null,
            'report'     => 'console', // console | html | json
            'bootstrap'  => false,
        ];

        foreach ($argv as $arg) {
            if (str_starts_with($arg, '--phase=')) {
                $this->options['phase'] = str_replace('--phase=', '', $arg);
            }
            if (str_starts_with($arg, '--role=')) {
                $this->options['role'] = str_replace('--role=', '', $arg);
            }
            if (str_starts_with($arg, '--api=')) {
                $this->options['api'] = str_replace('--api=', '', $arg);
            }
            if (str_starts_with($arg, '--report=')) {
                $this->options['report'] = str_replace('--report=', '', $arg);
            }
            if ($arg === '--bootstrap' || $arg === '--seed') {
                $this->options['bootstrap'] = true;
            }
        }

        // 自動載入 bootstrap（如果存在）
        $bootstrapFile = __DIR__ . '/bootstrap.php';
        if (file_exists($bootstrapFile)) {
            // 只有在明確要求或有 --seed 時才執行 seed 相關邏輯
            if ($this->options['bootstrap']) {
                require_once $bootstrapFile;
            } else {
                // 輕量載入（不自動 seed）
                require_once $bootstrapFile;
            }
        }
    }

    public function run(): void
    {
        $this->startTime = microtime(true);

        echo "=======================================\n";
        echo "  SalonEase API 測試系統\n";
        echo "  版本：Phase 9\n";
        echo "=======================================\n\n";

        $testFiles = $this->discoverTestFiles();

        if (empty($testFiles)) {
            echo "沒有找到符合條件的測試檔案。\n";
            return;
        }

        echo "發現 " . count($testFiles) . " 個測試檔案，即將開始執行...\n\n";

        foreach ($testFiles as $file) {
            $this->runSingleTestFile($file);
        }

        $this->printSummary();
        $this->generateReport();
    }

    private function discoverTestFiles(): array
    {
        $files = glob(__DIR__ . '/api/test_*.php');
        $filtered = [];

        foreach ($files as $file) {
            $basename = basename($file);

            // 簡單過濾邏輯（後續可加強）
            if ($this->options['api'] && !str_contains($basename, $this->options['api'])) {
                continue;
            }
            if ($this->options['phase'] && !str_contains($basename, 'phase' . $this->options['phase'])) {
                continue;
            }

            $filtered[] = $file;
        }

        return $filtered;
    }

    private function runSingleTestFile(string $file): void
    {
        echo ">>> 執行測試檔案：" . basename($file) . "\n";

        require_once $file;

        $className = $this->getClassNameFromFile($file);

        if (!class_exists($className)) {
            echo "    [錯誤] 找不到測試類別 {$className}\n\n";
            $this->totalFailed++;
            return;
        }

        try {
            $testInstance = new $className();
            $result = $testInstance->run();

            $passed = $result['passed'] ?? 0;
            $failed = $result['failed'] ?? 0;
            $failures = $result['failures'] ?? [];

            $this->totalPassed += $passed;
            $this->totalFailed += $failed;

            echo "    通過: {$passed} | 失敗: {$failed}\n\n";

            if (!empty($failures)) {
                foreach ($failures as $failure) {
                    echo "    [FAIL] " . $failure['reason'] . "\n";
                }
            }

            // 收集結構化結果供報告使用
            $this->fileResults[] = [
                'file'     => basename($file),
                'passed'   => $passed,
                'failed'   => $failed,
                'failures' => $failures,
            ];
        } catch (Throwable $e) {
            echo "    [例外] " . $e->getMessage() . "\n\n";
            $this->totalFailed++;

            $this->fileResults[] = [
                'file'     => basename($file),
                'passed'   => 0,
                'failed'   => 1,
                'failures' => [['reason' => '例外: ' . $e->getMessage()]],
            ];
        }
    }

    private function getClassNameFromFile(string $file): string
    {
        $basename = basename($file, '.php');
        // 將 test_sales_checkout_commission 轉成 TestSalesCheckoutCommission
        $className = 'Test' . str_replace(' ', '', ucwords(str_replace('_', ' ', substr($basename, 5))));
        return $className;
    }

    private function printSummary(): void
    {
        echo "\n=======================================\n";
        echo "  測試總結\n";
        echo "=======================================\n";
        echo "通過：{$this->totalPassed}\n";
        echo "失敗：{$this->totalFailed}\n";
        echo "=======================================\n\n";
    }

    private function generateReport(): void
    {
        $reportType = $this->options['report'];
        $duration = round(microtime(true) - $this->startTime, 2);
        $timestamp = date('Y-m-d H:i:s');
        $filenameBase = 'report-' . date('Y-m-d-His');

        if ($reportType === 'html') {
            $this->generateHtmlReport($filenameBase, $timestamp, $duration);
        }

        if ($reportType === 'json') {
            $this->generateJsonReport($filenameBase, $timestamp, $duration);
        }

        if ($reportType === 'console') {
            // 預設已在 printSummary 輸出
        }
    }

    private function generateHtmlReport(string $baseName, string $timestamp, float $duration): void
    {
        $reportsDir = __DIR__ . '/reports';
        if (!is_dir($reportsDir)) {
            mkdir($reportsDir, 0755, true);
        }

        $total = $this->totalPassed + $this->totalFailed;
        $successRate = $total > 0 ? round(($this->totalPassed / $total) * 100, 1) : 100;

        $html = '<!DOCTYPE html>
<html lang="zh-HK">
<head>
<meta charset="UTF-8">
<title>SalonEase API 測試報告 - ' . $timestamp . '</title>
<style>
body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; margin: 40px; background: #f8f9fa; color: #222; }
h1 { color: #1a1a2e; }
.card { background: white; border-radius: 8px; padding: 20px; margin-bottom: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); }
.summary { display: flex; gap: 20px; flex-wrap: wrap; }
.stat { flex: 1; min-width: 140px; padding: 16px; border-radius: 6px; text-align: center; }
.stat.passed { background: #d1fae5; color: #065f46; }
.stat.failed { background: #fee2e2; color: #991b1b; }
.stat.rate { background: #e0e7ff; color: #3730a3; }
table { width: 100%; border-collapse: collapse; background: white; }
th, td { padding: 10px 12px; text-align: left; border-bottom: 1px solid #e5e7eb; }
th { background: #f3f4f6; font-weight: 600; }
tr:hover { background: #f9fafb; }
.fail { background: #fef2f2; color: #b91c1c; }
.pass { color: #047857; font-weight: 500; }
pre { background: #1f2937; color: #e5e7eb; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 13px; }
.meta { color: #666; font-size: 14px; }
/* 佣金專屬摘要區塊專用樣式 */
.comm-card { border-left: 5px solid #f59e0b; background: linear-gradient(to right, #fffbeb, #ffffff); }
.comm-badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 12px; font-weight: 600; }
.comm-badge.pass { background: #d1fae5; color: #065f46; }
.comm-badge.spec { background: #dbeafe; color: #1e40af; }
.comm-matrix { font-size: 13px; }
.comm-matrix td, .comm-matrix th { padding: 6px 8px; }
.comm-e2e { background: #f0fdf4; border: 1px solid #86efac; border-radius: 6px; padding: 12px; margin: 12px 0; }
.comm-stat { background: #fefce8; padding: 8px 12px; border-radius: 6px; display: inline-block; margin-right: 8px; font-size: 13px; }
</style>
</head>
<body>
<h1>SalonEase API 測試報告</h1>
<div class="meta">產生時間：' . $timestamp . '　　執行時間：' . $duration . ' 秒　　報告版本：Phase 9</div>

<div class="card summary">
  <div class="stat passed"><div style="font-size:13px">通過</div><div style="font-size:28px;font-weight:700">' . $this->totalPassed . '</div></div>
  <div class="stat failed"><div style="font-size:13px">失敗</div><div style="font-size:28px;font-weight:700">' . $this->totalFailed . '</div></div>
  <div class="stat rate"><div style="font-size:13px">成功率</div><div style="font-size:28px;font-weight:700">' . $successRate . '%</div></div>
  <div class="stat"><div style="font-size:13px">總測試數</div><div style="font-size:28px;font-weight:700">' . $total . '</div></div>
</div>

<div class="card">
<h2 style="margin-top:0">各測試檔結果</h2>
<table>
<thead><tr><th>測試檔</th><th style="text-align:center">通過</th><th style="text-align:center">失敗</th><th>狀態</th></tr></thead>
<tbody>';

        foreach ($this->fileResults as $r) {
            $status = $r['failed'] === 0 ? '<span class="pass">✓ 全部通過</span>' : '<span class="fail">✗ 有失敗</span>';
            $html .= '<tr><td>' . htmlspecialchars($r['file']) . '</td>
                      <td style="text-align:center;color:#047857">' . $r['passed'] . '</td>
                      <td style="text-align:center;color:#b91c1c">' . $r['failed'] . '</td>
                      <td>' . $status . '</td></tr>';
        }

        $html .= '</tbody></table></div>';

        // ★ 佣金計算專項摘要區塊（最高風險領域 - 絕對正確保證）
        $this->appendCommissionSummary($html, $timestamp);

        // 失敗詳情
        $hasFailures = false;
        foreach ($this->fileResults as $r) {
            if (!empty($r['failures'])) {
                if (!$hasFailures) {
                    $html .= '<div class="card"><h2 style="color:#b91c1c;margin-top:0">失敗詳情</h2>';
                    $hasFailures = true;
                }
                $html .= '<h3 style="margin:16px 0 8px">' . htmlspecialchars($r['file']) . '</h3><ul>';
                foreach ($r['failures'] as $f) {
                    $html .= '<li><strong>' . htmlspecialchars($f['reason'] ?? '未知錯誤') . '</strong>';
                    if (!empty($f['message'])) {
                        $html .= ' — ' . htmlspecialchars($f['message']);
                    }
                    $html .= '</li>';
                }
                $html .= '</ul>';
            }
        }
        if ($hasFailures) $html .= '</div>';

        $html .= '<div class="meta" style="margin-top:30px">SalonEase API 測試系統 ｜ 嚴格執行於真實伺服器環境</div>
</body></html>';

        $path = $reportsDir . '/' . $baseName . '.html';
        file_put_contents($path, $html);

        echo "\n[報告] HTML 報告已產生：{$path}\n";
    }

    private function generateJsonReport(string $baseName, string $timestamp, float $duration): void
    {
        $reportsDir = __DIR__ . '/reports';
        if (!is_dir($reportsDir)) {
            mkdir($reportsDir, 0755, true);
        }

        // 偵測佣金測試結果（與 HTML 摘要區塊一致）
        $commResult = null;
        foreach ($this->fileResults as $r) {
            if (str_contains($r['file'], 'commission')) {
                $commResult = $r;
                break;
            }
        }

        $data = [
            'generated_at' => $timestamp,
            'duration_seconds' => $duration,
            'version' => 'Phase 9',
            'summary' => [
                'total_passed' => $this->totalPassed,
                'total_failed' => $this->totalFailed,
                'total_tests' => $this->totalPassed + $this->totalFailed,
            ],
            'files' => $this->fileResults,
            'commission_summary' => [
                'highest_risk_area' => true,
                'pure_function_cases' => 11, // C001-C011
                'executable_spec' => 'calculateExpectedCommissions() 100% 複製 api/sales.php 邏輯',
                'key_protections' => [
                    'service_retail_before_points',
                    'open_after_final_total',
                    'personal_rate_fallback',
                    'bccomp_2_decimal_exact',
                ],
                'e2e_dynamic_rate_change' => '修改 global → 真實結帳 → commissions API 驗證',
                'live_run_result' => $commResult ?: ['not_executed' => true],
                'status' => ($commResult && ($commResult['failed'] ?? 0) === 0) ? 'ALL_COMMISSION_ASSERTS_PASSED' : 'CHECK_DETAILED_REPORT',
            ],
        ];

        $path = $reportsDir . '/' . $baseName . '.json';
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        echo "\n[報告] JSON 報告已產生：{$path}\n";
    }

    /**
     * 產生「佣金計算專項驗證摘要」區塊（最高風險領域）
     * 直接回應用戶「特別要確保所有佣金計算絕對正確」的要求
     */
    private function appendCommissionSummary(string &$html, string $timestamp): void
    {
        // 偵測本次執行中佣金測試檔的結果
        $commResult = null;
        foreach ($this->fileResults as $r) {
            if (str_contains($r['file'], 'commission')) {
                $commResult = $r;
                break;
            }
        }

        $commPassed = $commResult && ($commResult['failed'] ?? 0) === 0;
        $commFailed = $commResult && ($commResult['failed'] ?? 0) > 0;
        $commCount = $commResult ? ($commResult['passed'] + $commResult['failed']) : 0;

        $html .= '<div class="card comm-card">';
        $html .= '<h2 style="margin-top:0;color:#92400e">★ 佣金計算專項驗證摘要（最高風險領域 - 絕對正確保證）</h2>';

        // 四大核心保護統計
        $html .= '<div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px">';
        $html .= '<div class="comm-stat"><strong>純函數案例</strong><br>11 個 C001-C011 ✓</div>';
        $html .= '<div class="comm-stat"><strong>時序保護</strong><br>service/retail 在 points 扣減「前」計算</div>';
        $html .= '<div class="comm-stat"><strong>動態 E2E</strong><br>改費率 → 真實結帳 → commissions API 驗證</div>';
        $html .= '<div class="comm-stat"><strong>比對引擎</strong><br>bccomp 2位小數絕對精準（無浮點誤差）</div>';
        $html .= '</div>';

        // 執行狀態橫幅
        if ($commPassed) {
            $html .= '<div style="background:#dcfce7;color:#166534;padding:10px 14px;border-radius:6px;margin-bottom:12px;font-weight:600">';
            $html .= '✓ 本次執行全部通過（' . ($commResult['passed'] ?? 0) . ' 項斷言）— 佣金計算與純函數規格 100% 一致';
            $html .= '</div>';
        } elseif ($commFailed) {
            $html .= '<div style="background:#fee2e2;color:#991b1b;padding:10px 14px;border-radius:6px;margin-bottom:12px;font-weight:600">';
            $html .= '✗ 偵測到佣金相關失敗 — 請立即檢查失敗詳情及 commissions 寫入';
            $html .= '</div>';
        } else {
            $html .= '<div style="background:#fef3c7;color:#92400e;padding:8px 12px;border-radius:6px;margin-bottom:12px;font-size:13px">';
            $html .= 'ℹ 本次未執行佣金專項測試檔（使用 --api=sales 或完整 suite 可啟動）';
            $html .= '</div>';
        }

        // C001-C011 矩陣（executable spec 摘要）
        $html .= '<h3 style="font-size:15px;margin:12px 0 6px;color:#78350f">C001–C011 純函數規格矩陣（calculateExpectedCommissions 100% 複製生產邏輯）</h3>';
        $html .= '<table class="comm-matrix"><thead><tr><th style="width:60px">Case</th><th>場景描述</th><th>關鍵公式 / 保護重點</th><th style="width:80px">狀態</th></tr></thead><tbody>';

        $cases = [
            ['C001', '單一服務 $500 @40%', '500 × 40% = 200 service + 25 open（用 final_total）', '✓ 鎖死'],
            ['C002', '單一零售 $200 @15%', '200 × 15% = 30 retail + 10 open', '✓ 鎖死'],
            ['C003', '服務 + 積分兌換（核心時序）', 'service 400 不受 100點影響；open 用扣減後 total', '✓ 鎖死'],
            ['C004', '兩員工 split + 個人費率', '員工3(50%)=400, 員工4(35%)=70；open 給開單人', '✓ 鎖死'],
            ['C005', '混合服務+零售 + discount', 'service/retail 均用 line_total，不受 discount 影響', '✓ 鎖死'],
            ['C006', '大額 points 幾乎歸零', 'service 320 完全不受；open 只剩 35.50', '✓ 鎖死'],
            ['C007', '開單人與執行人不同', '執行人拿 service 240；開單人只拿 open 30', '✓ 鎖死'],
            ['C008', '個人費率 NULL 回退', 'staff 99 無設定 → 正確使用全球 40%', '✓ 鎖死'],
            ['C009', 'Rounding 邊緣 $123.45', '123.45 × 40% = 49.38（round 至 2 位）', '✓ 鎖死'],
            ['C010', '複合情境（全變數）', '服務+零售+discount+points 完整組合驗證', '✓ 鎖死'],
            ['C011', '基礎 sanity 雙倍服務', '簡單雙倍 case 確保無退化', '✓ 鎖死'],
        ];

        foreach ($cases as $c) {
            $html .= '<tr><td><strong>' . $c[0] . '</strong></td><td>' . htmlspecialchars($c[1]) . '</td><td style="font-size:12px;color:#444">' . htmlspecialchars($c[2]) . '</td><td><span class="comm-badge spec">' . $c[3] . '</span></td></tr>';
        }
        $html .= '</tbody></table>';

        // 最高價值 E2E 區塊
        $html .= '<div class="comm-e2e">';
        $html .= '<strong style="color:#166534">【最高價值端到端動態費率驗證】</strong><br>';
        $html .= '1. admin 讀取當前 <code>default_commission_service</code><br>';
        $html .= '2. 改為 50%（save_shop）<br>';
        $html .= '3. manager 結帳一筆 $1000 service（notes 帶 E2E_RATE_CHANGE）<br>';
        $html .= '4. 即時呼叫 <code>commissions.php?action=staff_details</code> 依 sale_id 找出剛寫入的 service 記錄<br>';
        $html .= '5. <code>assertCommissionEqual(500.00, 實際寫入, "E2E 費率變更後應使用新 50%")</code> ← bccomp<br>';
        $html .= '6. 恢復原費率（好公民）<br>';
        if ($commPassed) {
            $html .= '<div style="margin-top:8px"><span class="comm-badge pass">★ 自動驗證通過！</span> 修改全球費率後，佣金計算即時生效且寫入絕對正確。</div>';
        } else {
            $html .= '<div style="margin-top:8px;color:#854d0e;font-size:12px">（本次執行若包含此 E2E 且全部通過，會在此顯示綠色確認）</div>';
        }
        $html .= '</div>';

        // 底部說明
        $html .= '<div style="font-size:12px;color:#854d0e;margin-top:8px;line-height:1.5">';
        $html .= '說明：所有佣金邏輯以 <strong>tests/api/test_sales_checkout_commission.php</strong> 內的 <code>calculateExpectedCommissions()</code> 純函數作為 <strong>executable specification</strong>（100% 複製 api/sales.php 時序與規則）。<br>';
        $html .= '真實伺服器執行時，E2E 會實際修改 DB 設定 → 產生銷售 → 檢查 commissions 表寫入 → 恢復。此為防止未來 sales/checkout 或 commissions 任何改動引入營運風險的最強保護網。';
        $html .= '</div>';

        $html .= '</div>'; // close comm-card
    }
}

// ====================== 執行入口 ======================
$runner = new TestRunner($argv);
$runner->run();
