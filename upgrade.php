<?php
/**
 * SalonEase - 專業一鍵資料庫升級工具
 * 
 * 設計目標：
 * - 日後任何 DB 變更（新增欄位、索引、新表、seed 更新）都能一鍵安全執行
 * - 永不破壞現有數據（所有 migration 必須冪等 + 交易保護）
 * - 詳細記錄 + 清晰說明
 * - 僅限已登入管理員執行
 * 
 * 使用方法：
 *   1. 放置新 migration 檔案到 /migrations/ 目錄（格式：00X_描述.php）
 *   2. 登入後瀏覽 /upgrade.php
 *   3. 按「開始一鍵升級」
 * 
 * Migration 檔案格式範例：
 *   return [
 *       'description' => '為 products 表新增 cost 欄位及索引',
 *       'up' => function(PDO $pdo) {
 *           // 安全寫法（冪等）
 *           try { $pdo->exec("ALTER TABLE products ADD COLUMN cost DECIMAL(8,2) DEFAULT NULL"); } catch (PDOException $e) {}
 *           $pdo->exec("CREATE INDEX IF NOT EXISTS idx_xxx ON ...");
 *       }
 *   ];
 * 
 * 嚴禁在 migration 中使用 DROP TABLE / TRUNCATE！
 */

// 安全起見，升級一律需要登入
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/db.php';
require_login();

$currentUser = get_current_user();
if (!$currentUser || !in_array($currentUser['role'], ['admin', 'manager'])) {
    http_response_code(403);
    include __DIR__ . '/includes/header.php';
    echo '<div class="max-w-md mx-auto mt-16 p-8 bg-red-50 border border-red-200 rounded-3xl text-center">';
    echo '<div class="text-4xl mb-3">🔒</div>';
    echo '<div class="font-semibold text-xl">權限不足</div>';
    echo '<p class="mt-2 text-red-600">只有管理員或經理可以執行資料庫升級。</p>';
    echo '<a href="/dashboard.php" class="mt-6 inline-block text-red-600 hover:underline">返回控制台</a>';
    echo '</div>';
    include __DIR__ . '/includes/footer.php';
    exit;
}

$pdo = db();

// 確保 migrations 表存在（相容舊安裝）
$pdo->exec("
    CREATE TABLE IF NOT EXISTS migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration VARCHAR(255) NOT NULL UNIQUE,
        batch INT NOT NULL,
        executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// 取得已執行清單
$executed = $pdo->query("SELECT migration FROM migrations ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

// 掃描所有 migration 檔案
$migrationFiles = glob(__DIR__ . '/migrations/*.php');
sort($migrationFiles);

$pending = [];
$pendingDetails = [];
foreach ($migrationFiles as $file) {
    $name = basename($file, '.php');
    if (!in_array($name, $executed, true)) {
        $pending[] = $file;
        // 嘗試讀取描述
        $migration = @include $file; // 安全 include
        $desc = is_array($migration) && isset($migration['description']) ? $migration['description'] : '資料庫結構更新';
        $pendingDetails[] = ['name' => $name, 'file' => $file, 'description' => $desc];
    }
}

$hasPending = !empty($pending);
$currentVersion = $pdo->query("SELECT COALESCE(MAX(migration), 'v0.0.0') FROM migrations")->fetchColumn();
$lastUpgrade = $pdo->query("SELECT MAX(executed_at) FROM migrations")->fetchColumn();

// POST 處理升級
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $hasPending) {
    header('Content-Type: text/html; charset=utf-8');
    ?>
    <!DOCTYPE html>
    <html><head><meta charset="UTF-8"><title>SalonEase 正在升級...</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>body{font-family:'Noto Sans TC',system-ui} .log{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,monospace;font-size:13.5px}</style>
    </head><body class="bg-[#FDF8F3] text-[#2C2C2E]">
    <div class="max-w-4xl mx-auto p-8">
        <div class="flex items-center gap-3 mb-6">
            <div class="w-9 h-9 bg-[#2C2C2E] text-white rounded-2xl flex items-center justify-center font-bold">S</div>
            <div class="font-semibold text-2xl tracking-tight">SalonEase 資料庫升級</div>
        </div>
        <h1 class="text-3xl font-semibold mb-1">正在執行一鍵升級</h1>
        <div class="text-[#5A5A5C]">請勿關閉此頁面 · 所有操作均在交易保護下進行</div>
        
        <div class="mt-6 bg-white border border-[#EDE5DC] rounded-3xl p-6 shadow-sm max-h-[560px] overflow-auto log" id="log">
    <?php
    ob_implicit_flush(true);
    ob_flush();

    function log_line($msg, $type = 'info') {
        $icon = $type === 'ok' ? '✓' : ($type === 'err' ? '✗' : ($type === 'warn' ? '⚠' : '→'));
        $cls = $type === 'ok' ? 'text-[#2e7d32]' : ($type === 'err' ? 'text-[#c62828]' : ($type === 'warn' ? 'text-[#b8860b]' : 'text-[#5A5A5C]'));
        echo "<div class='py-1 border-b border-[#f4ede3] $cls'>[$icon] " . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . "</div>\n";
        ob_flush();
    }

    try {
        $batch = (int)$pdo->query("SELECT COALESCE(MAX(batch), 0) FROM migrations")->fetchColumn() + 1;
        log_line("建立新批次 #$batch");

        foreach ($pendingDetails as $mig) {
            $name = $mig['name'];
            $file = $mig['file'];
            $desc = $mig['description'];
            
            log_line("開始執行：$name", 'warn');
            log_line("說明：$desc");

            $migration = require $file;

            if (!is_array($migration) || !isset($migration['up']) || !is_callable($migration['up'])) {
                throw new Exception("$name 格式錯誤，缺少有效的 up() 函式");
            }

            // 包在交易中執行單一 migration（安全）
            $pdo->beginTransaction();
            try {
                $migration['up']($pdo);
                $pdo->prepare("INSERT INTO migrations (migration, batch) VALUES (?, ?)")->execute([$name, $batch]);
                $pdo->commit();
                log_line("$name 執行成功", 'ok');
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw new Exception("$name 失敗：" . $e->getMessage());
            }
        }

        log_line('全部升級完成！', 'ok');
        echo '</div>';
        echo '<div class="mt-6 flex gap-3">';
        echo '<a href="/dashboard.php" class="flex-1 text-center bg-[#2C2C2E] text-white py-3.5 rounded-2xl font-medium">返回控制台</a>';
        echo '<a href="/upgrade.php" class="flex-1 text-center border border-[#2C2C2E] py-3.5 rounded-2xl font-medium">再次檢查更新</a>';
        echo '</div></div></body></html>';
        exit;

    } catch (Throwable $e) {
        echo '</div><div class="mt-6 p-6 bg-red-50 border border-red-200 rounded-3xl">';
        echo '<div class="font-semibold text-red-700 mb-2">升級過程中發生錯誤</div>';
        echo '<div class="text-sm text-red-600">' . htmlspecialchars($e->getMessage()) . '</div>';
        echo '<div class="mt-4 text-xs text-red-500">建議：檢查最後一筆 migration 的語法，或手動從 migrations 表刪除最後一筆記錄後重試。</div>';
        echo '</div></div></body></html>';
        exit;
    }
}

// ==================== UI ====================
include __DIR__ . '/includes/header.php';
?>
<div class="max-w-4xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-3xl font-semibold tracking-tight">資料庫升級</h1>
            <p class="text-[#5A5A5C] mt-1">一鍵套用所有待執行的結構更新，永不破壞現有數據</p>
        </div>
        <div class="text-right text-xs">
            <div class="text-[#8A8A8C]">目前版本</div>
            <div class="font-mono text-lg font-semibold text-[#2C2C2E]"><?= h($currentVersion) ?></div>
        </div>
    </div>

    <!-- 安全提示 -->
    <div class="bg-[#F8F5F0] border border-[#EDE5DC] rounded-3xl p-5 mb-8 text-sm">
        <div class="font-medium mb-2">🛡️ 專業升級保障</div>
        <ul class="space-y-1 text-[#5A5A5C]">
            <li>• 所有更新皆以交易包裹，失敗自動回滾</li>
            <li>• Migration 必須設計為冪等（重複執行也不會出錯）</li>
            <li>• 嚴禁在 migration 中使用 DROP / TRUNCATE / DELETE 危險操作</li>
            <li>• 升級前建議先備份資料庫（共享主機通常有自動備份）</li>
        </ul>
    </div>

    <?php if (!$hasPending): ?>
        <div class="bg-white border border-[#EDE5DC] rounded-3xl p-10 text-center">
            <div class="text-6xl mb-4 opacity-75">✅</div>
            <div class="text-2xl font-semibold">你的資料庫已是最新版本</div>
            <p class="mt-3 text-[#5A5A5C]">沒有待執行的更新。日後有新功能需要調整結構時，只需將新的 migration 檔案放入 <span class="font-mono">/migrations/</span> 目錄即可。</p>
            
            <div class="mt-8 inline-flex gap-3">
                <a href="/dashboard.php" class="px-7 py-3 border border-[#2C2C2E] rounded-2xl text-sm font-medium hover:bg-[#F8F5F0]">返回控制台</a>
                <a href="/settings.php" class="px-7 py-3 bg-[#2C2C2E] text-white rounded-2xl text-sm font-medium">系統設定</a>
            </div>
        </div>
    <?php else: ?>
        <div class="bg-white border border-[#EDE5DC] rounded-3xl overflow-hidden">
            <div class="px-7 py-5 border-b bg-[#F8F5F0] flex items-center justify-between">
                <div>
                    <span class="font-semibold">發現 <?= count($pending) ?> 個待執行的更新</span>
                </div>
                <div class="text-xs text-[#8A8A8C]">最後升級時間：<?= $lastUpgrade ? date('Y-m-d H:i', strtotime($lastUpgrade)) : '從未' ?></div>
            </div>

            <div class="p-7">
                <div class="text-sm text-[#5A5A5C] mb-3">以下更新將按順序執行：</div>
                
                <div class="space-y-3">
                    <?php foreach ($pendingDetails as $idx => $m): ?>
                    <div class="flex gap-4 p-4 bg-[#F8F5F0] rounded-2xl border border-[#EDE5DC]">
                        <div class="font-mono text-xs pt-1 w-16 text-[#8A8A8C]"><?= h($m['name']) ?></div>
                        <div class="flex-1 text-sm"><?= h($m['description']) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="mt-7 border-t pt-6">
                    <form method="post" onsubmit="return confirm('確定要立即執行以上 ' + <?= count($pending) ?> + ' 項資料庫更新？此操作不可逆轉，但系統會盡力保護數據完整性。');">
                        <button type="submit" 
                                class="w-full py-4 bg-[#2C2C2E] hover:bg-black text-white font-medium text-lg rounded-2xl transition">
                            開始一鍵升級（共 <?= count($pending) ?> 項）
                        </button>
                    </form>
                    <div class="text-center text-xs text-[#8A8A8C] mt-3">執行過程會即時顯示詳細日誌 · 失敗時會顯示錯誤並回滾該步驟</div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- 開發者說明 -->
    <div class="mt-8 text-xs text-[#8A8A8C] bg-white border border-[#EDE5DC] rounded-3xl p-5">
        <div class="font-medium text-[#5A5A5C] mb-2">如何新增日後更新（給開發者）</div>
        <ol class="list-decimal ml-4 space-y-0.5">
            <li>在 <code class="bg-[#F8F5F0] px-1">migrations/</code> 建立新檔案，例如 <code>002_add_low_stock_alert_sent.php</code></li>
            <li>檔案內容回傳陣列，包含 <code>description</code> 及 <code>up</code> 函式</li>
            <li>使用 <code>CREATE INDEX IF NOT EXISTS</code>、<code>ALTER TABLE ... ADD COLUMN IF NOT EXISTS</code>（MySQL 8.0+）或 try-catch 包裹 ALTER 確保冪等</li>
            <li>絕對不要包含 DROP / TRUNCATE / 刪除資料的語句</li>
            <li>提交 git 後，客戶端只需重新上傳此檔案，再次進入 upgrade.php 即可一鍵套用</li>
        </ol>
        <div class="mt-3 text-[#8FA68F]">目前已內建 migrations/001_initial_schema.php（安裝時自動執行）</div>
    </div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>
<?php
// 簡單尾巴，避免 header 之後的 PHP 警告
if (isset($footerIncluded)) { /* no-op */ }
