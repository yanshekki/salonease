<?php
/**
 * SalonEase API 測試系統 - 斷言工具
 * 
 * 提供比 PHPUnit 更適合此項目的輕量斷言，特別加強金錢計算的精準比較。
 */

class Assertion
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    /**
     * 斷言兩個值相等（嚴格比較）
     */
    public function assertEquals($expected, $actual, string $message = ''): void
    {
        if ($expected === $actual) {
            $this->passed++;
            return;
        }

        $this->fail("預期值與實際值不相等", $expected, $actual, $message);
    }

    /**
     * 專為金錢計算設計的精準比較（避免浮點數誤差）
     * 
     * @param float $expected 預期金額
     * @param float $actual   實際金額
     * @param float $tolerance 容許誤差（預設 0.01，即 1 分錢）
     */
    public function assertMoneyEquals(float $expected, float $actual, float $tolerance = 0.01, string $message = ''): void
    {
        $diff = abs($expected - $actual);

        if ($diff <= $tolerance) {
            $this->passed++;
            return;
        }

        $this->fail(
            "佣金金額計算錯誤（誤差超過 {$tolerance}）",
            number_format($expected, 2),
            number_format($actual, 2),
            $message . " | 差異: " . number_format($diff, 4)
        );
    }

    /**
     * 專為佣金計算設計的高精度比較（使用 bccomp 避免任何浮點誤差）
     * 所有佣金測試應優先使用此方法。
     * 
     * @param float $expected  預期佣金金額（2位小數）
     * @param float $actual    實際佣金金額
     * @param string $message  失敗時額外說明
     */
    public function assertCommissionEqual(float $expected, float $actual, string $message = ''): void
    {
        $expStr = number_format($expected, 2, '.', '');
        $actStr = number_format($actual, 2, '.', '');

        if (function_exists('bccomp')) {
            // bccomp 回傳 0 表示相等（scale=2）
            if (bccomp($expStr, $actStr, 2) === 0) {
                $this->passed++;
                return;
            }
        } else {
            // 後備：使用 0.005 容差（半仙級）
            if (abs($expected - $actual) < 0.005) {
                $this->passed++;
                return;
            }
        }

        $this->fail(
            "佣金計算絕對誤差（bccomp 失敗）",
            $expStr,
            $actStr,
            $message . " | 預期: {$expStr} 實際: {$actStr}"
        );
    }

    /**
     * 斷言值為真
     */
    public function assertTrue($value, string $message = ''): void
    {
        if ($value === true) {
            $this->passed++;
            return;
        }
        $this->fail("預期值應為 true", true, $value, $message);
    }

    /**
     * 斷言值為假
     */
    public function assertFalse($value, string $message = ''): void
    {
        if ($value === false) {
            $this->passed++;
            return;
        }
        $this->fail("預期值應為 false", false, $value, $message);
    }

    /**
     * 斷言 HTTP 狀態碼或 API 回應中的 error code
     */
    public function assertHttpCode(int $expectedCode, array $response, string $message = ''): void
    {
        $actualCode = $response['http_code'] ?? 0;
        if ($actualCode === $expectedCode) {
            $this->passed++;
            return;
        }
        $this->fail("HTTP 狀態碼不符", $expectedCode, $actualCode, $message);
    }

    /**
     * 記錄失敗
     */
    private function fail(string $reason, $expected, $actual, string $extraMessage = ''): void
    {
        $this->failed++;
        $this->failures[] = [
            'reason'   => $reason,
            'expected' => $expected,
            'actual'   => $actual,
            'message'  => $extraMessage,
            'trace'    => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3),
        ];
    }

    /**
     * 取得目前通過數
     */
    public function getPassed(): int
    {
        return $this->passed;
    }

    /**
     * 取得目前失敗數
     */
    public function getFailed(): int
    {
        return $this->failed;
    }

    /**
     * 取得所有失敗詳情
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    /**
     * 是否全部通過
     */
    public function allPassed(): bool
    {
        return $this->failed === 0;
    }

    /**
     * 重置計數器
     */
    public function reset(): void
    {
        $this->passed = 0;
        $this->failed = 0;
        $this->failures = [];
    }
}
