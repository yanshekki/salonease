<?php 
require_once __DIR__ . '/includes/auth.php'; 
require_login(); 
$pageTitle = '系統設定'; 
$extraJs = 'settings.js';   // 稍後可獨立抽離，目前先用內聯 Alpine
include __DIR__ . '/includes/header.php'; 
?>
<div class="max-w-3xl mx-auto" x-data="shopSettings()">
    <h2 class="text-xl font-semibold mb-6">系統設定</h2>

    <!-- 店舖基本資訊（A 選擇重點） -->
    <div class="bg-white rounded-2xl border border-gray-100 p-6 mb-8">
        <div class="flex items-center justify-between mb-4">
            <div>
                <div class="font-semibold text-lg">店舖基本資訊</div>
                <div class="text-sm text-[#5A5A5C]">收據、打印會自動使用以下資料</div>
            </div>
            <div class="text-xs px-3 py-1 bg-[#F8F5F0] rounded-xl text-[#8A8A8C]">即時生效</div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">店舖名稱</label>
                <input type="text" x-model="form.salon_name" 
                       class="salon-input w-full" placeholder="SalonEase 美容中心">
            </div>
            <div class="md:col-span-2">
                <label class="block text-sm font-medium mb-1">地址</label>
                <input type="text" x-model="form.address" 
                       class="salon-input w-full" placeholder="香港九龍尖沙咀彌敦道 100 號 8 樓">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1">電話</label>
                <input type="text" x-model="form.phone" 
                       class="salon-input w-full" placeholder="2123 4567">
            </div>
            <div class="flex items-end">
                <button @click="saveShop()" 
                        :disabled="saving"
                        class="salon-btn w-full md:w-auto px-8 disabled:opacity-60">
                    <span x-text="saving ? '儲存中...' : '儲存店舖資訊'"></span>
                </button>
                <span x-show="saved" x-cloak class="ml-3 text-sm text-[#8FA68F]">✓ 已儲存</span>
            </div>
        </div>

        <div class="mt-4 text-xs text-[#8A8A8C]">
            修改後，所有新打印的收據（熱感紙及 A4）都會即時顯示最新資料。
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <a href="/customers.php" class="block p-5 bg-white border border-gray-100 rounded-2xl hover:border-[#8FA68F] transition group">
            <div class="text-2xl mb-2">👥</div>
            <div class="font-semibold group-hover:text-[#8FA68F]">客戶管理</div>
            <div class="text-sm text-[#5A5A5C] mt-1">客戶資料、新增與編輯</div>
        </a>

        <a href="/staff.php" class="block p-5 bg-white border border-gray-100 rounded-2xl hover:border-[#8FA68F] transition group">
            <div class="text-2xl mb-2">🧑‍💼</div>
            <div class="font-semibold group-hover:text-[#8FA68F]">員工管理</div>
            <div class="text-sm text-[#5A5A5C] mt-1">新增、編輯、啟用/停用員工帳號及角色</div>
        </a>

        <a href="/rooms.php" class="block p-5 bg-white border border-gray-100 rounded-2xl hover:border-[#8FA68F] transition group">
            <div class="text-2xl mb-2">🏠</div>
            <div class="font-semibold group-hover:text-[#8FA68F]">房間管理</div>
            <div class="text-sm text-[#5A5A5C] mt-1">管理房間名稱與容量（用於預約）</div>
        </a>

        <a href="/services.php" class="block p-5 bg-white border border-gray-100 rounded-2xl hover:border-[#8FA68F] transition group">
            <div class="text-2xl mb-2">💆</div>
            <div class="font-semibold group-hover:text-[#8FA68F]">服務項目管理</div>
            <div class="text-sm text-[#5A5A5C] mt-1">管理療程名稱、時長與價格</div>
        </a>

        <a href="/products.php" class="block p-5 bg-white border border-gray-100 rounded-2xl hover:border-[#8FA68F] transition group">
            <div class="text-2xl mb-2">🛍️</div>
            <div class="font-semibold group-hover:text-[#8FA68F]">零售產品管理</div>
            <div class="text-sm text-[#5A5A5C] mt-1">管理產品、售價與庫存</div>
        </a>

        <a href="/packages.php" class="block p-5 bg-white border border-gray-100 rounded-2xl hover:border-[#8FA68F] transition group">
            <div class="text-2xl mb-2">🎫</div>
            <div class="font-semibold group-hover:text-[#8FA68F]">套票管理</div>
            <div class="text-sm text-[#5A5A5C] mt-1">療程卡（套票）定義與定價</div>
        </a>
    </div>
</div>

<script>
function shopSettings() {
    return {
        form: {
            salon_name: 'SalonEase 美容中心',
            address: '',
            phone: ''
        },
        saving: false,
        saved: false,

        init() {
            this.loadShop();
        },

        async loadShop() {
            try {
                const res = await SalonEase.fetch('/api/settings.php?action=get');
                if (res.data) {
                    this.form.salon_name = res.data.salon_name || 'SalonEase 美容中心';
                    this.form.address = res.data.address || '';
                    this.form.phone = res.data.phone || '';
                }
            } catch (e) {
                console.warn('載入店舖資訊失敗', e);
            }
        },

        async saveShop() {
            this.saving = true;
            this.saved = false;

            try {
                const res = await SalonEase.fetch('/api/settings.php?action=save_shop', {
                    method: 'POST',
                    body: {
                        salon_name: this.form.salon_name,
                        address: this.form.address,
                        phone: this.form.phone
                    }
                });

                SalonEase.toast('店舖資訊已成功更新');
                this.saved = true;

                // 3 秒後隱藏成功提示
                setTimeout(() => { this.saved = false; }, 3000);
            } catch (err) {
                SalonEase.toast(err.message || '儲存失敗', 'error');
            } finally {
                this.saving = false;
            }
        }
    }
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
