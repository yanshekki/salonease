<?php 
require_once __DIR__ . '/includes/auth.php'; 
require_login(); 
$pageTitle = '系統設定'; 
include __DIR__ . '/includes/header.php'; 
?>
<div class="max-w-3xl mx-auto">
    <h2 class="text-xl font-semibold mb-6">系統設定</h2>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <a href="/staff.php" class="block p-5 bg-white border border-gray-100 rounded-2xl hover:border-[#8FA68F] transition group">
            <div class="text-2xl mb-2">👥</div>
            <div class="font-semibold group-hover:text-[#8FA68F]">員工管理</div>
            <div class="text-sm text-[#5A5A5C] mt-1">新增、編輯、啟用/停用員工帳號及角色</div>
        </a>

        <a href="/rooms.php" class="block p-5 bg-white border border-gray-100 rounded-2xl hover:border-[#8FA68F] transition group">
            <div class="text-2xl mb-2">🏠</div>
            <div class="font-semibold group-hover:text-[#8FA68F]">房間管理</div>
            <div class="text-sm text-[#5A5A5C] mt-1">管理房間名稱與容量（用於預約）</div>
        </a>

        <div class="p-5 bg-white border border-gray-100 rounded-2xl opacity-60">
            <div class="text-2xl mb-2">💰</div>
            <div class="font-semibold">佣金率設定</div>
            <div class="text-sm text-[#5A5A5C] mt-1">（Phase 1 後續實作）</div>
        </div>

        <div class="p-5 bg-white border border-gray-100 rounded-2xl opacity-60">
            <div class="text-2xl mb-2">🏪</div>
            <div class="font-semibold">美容院基本資料</div>
            <div class="text-sm text-[#5A5A5C] mt-1">（Phase 1 後續實作）</div>
        </div>
    </div>
</div>
<?php $extraJs = 'hotkeys.js'; include __DIR__ . '/includes/footer.php'; ?>
