<?php
/**
 * SalonEase API 測試系統 - 主控程式
 * 
 * 用法範例：
 *   php tests/run_tests.php                    # 執行所有測試
 *   php tests/run_tests.php --phase=1          # 只執行 Phase 1（佣金相關）
 *   php tests/run_tests.php --role=therapist   # 只用治療師角色測試
 *   php tests/run_tests.php --api=sales        # 只測試 sales 相關 API
 *   php tests/run_tests.php --report=html      # 輸出 HTML 報告
 */

require_once __DIR__ . '/roles/TestUsers.php';
require_once __DIR__ . '/lib/ApiClient.php';
require_once __DIR__ . '/lib/Assertion.php';

class TestRunner
{
    private array $options = [];
    private array $results = [];
    private int $totalPassed = 0;
    private int $totalFailed = 0;

    public function __construct(array $argv)
    {
        $this->parseArguments($argv);
    }

    private function parseArguments(array $argv): void
    {
        $this->options = [
            'phase'  => null,
            'role'   => null,
            'api'    => null,
            'report' => 'console', // console | html | json
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
        }
    }

    public function run(): void
    {
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

            $this->totalPassed += $result['passed'] ?? 0;
            $this->totalFailed += $result['failed'] ?? 0;

            echo "    通過: " . ($result['passed'] ?? 0) . " | 失敗: " . ($result['failed'] ?? 0) . "\n\n";

            if (!empty($result['failures'])) {
                foreach ($result['failures'] as $failure) {
                    echo "    [FAIL] " . $failure['reason'] . "\n";
                }
            }
        } catch (Throwable $e) {
            echo "    [例外] " . $e->getMessage() . "\n\n";
            $this->totalFailed++;
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
        if ($this->options['report'] === 'html') {
            // TODO: 產生 HTML 報告
            echo "[報告] HTML 報告功能開發中...\n";
        }

        if ($this->options['report'] === 'json') {
            // TODO: 產生 JSON 報告
            echo "[報告] JSON 報告功能開發中...\n";
        }
    }
}

// ====================== 執行入口 ======================
$runner = new TestRunner($argv);
$runner->run();
