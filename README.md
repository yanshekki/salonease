# SalonEase 香港小型美容院管理系統

**純 PHP + API-First + 零 Framework**  
專業、簡單、高效率，專為香港小型美容院、醫美中心、SPA（3-15 人團隊）設計。

部署目標：https://salonease.ysk.hk

---

## 核心特色

- **純原生 PHP**：無 Laravel、CodeIgniter、Symfony、Composer（除極度必要）
- **API-First**：所有商業邏輯集中在 `/api/`，前端大量使用 fetch 呼叫
- **完整業務支援**：
  - 零售 + 服務 + 套票（療程卡）
  - 員工三類佣金（服務 / 零售 / 開單）
  - 房間容量 + 預約時間衝突避免
  - 熱感紙收據（58/80mm）+ A4 合約收據（純 CSS @media print，無 PDF 庫）
- **極致操作效率**：全面 Hotkey 支援，減少滑鼠依賴
- **專業現代 UI**：Tailwind CSS（CDN）+ Alpine.js（CDN）+ 原生 JS
- **香港在地化**：所有介面、錯誤、報表皆用繁體中文（香港習慣用語）
- **共享主機友好**：簡單部署，無複雜依賴

---

## 技術棧

| 層級     | 技術                          | 說明                          |
|----------|-------------------------------|-------------------------------|
| 後端     | PHP 8.1+ + PDO                | 純原生，無框架                |
| 資料庫   | MySQL 8.0+ (utf8mb4)          | 完整交易 + 索引優化           |
| 前端     | HTML + Tailwind CDN + Alpine.js CDN | 輕量、零 build             |
| JS       | 原生 fetch + Alpine.js        | 盡量減少外部依賴              |
| 打印     | window.print() + CSS @media   | 熱感紙 + A4，無額外套件       |
| 認證     | $_SESSION + password_hash     | 原生安全實作                  |

---

## 快速開始（本地開發）

1. 複製 `config.example.php` 為 `config.php` 並填入資料庫資訊
2. 建立 MySQL 資料庫（建議 utf8mb4_unicode_ci）
3. 執行 `sql/schema.sql` 建立所有表格
4. （可選）執行 `sql/seeds.sql` 插入測試資料
5. 將專案目錄設為 web root 或使用 PHP 內建伺服器：
   ```bash
   php -S localhost:8000
   ```
6. 瀏覽 http://localhost:8000 即可登入（預設帳號見 seeds）

---

## 目錄結構（極簡清晰）

```
salonease/
├── api/                  # 所有 API 端點（回傳 JSON）
├── includes/             # 共用函式、auth、header/footer
├── assets/               # CSS、JS、圖片（分離管理）
├── sql/                  # schema.sql + seeds.sql
├── uploads/              # 使用者上傳（logo、照片）
├── dashboard.php         # 首頁
├── pos.php               # POS 銷售（核心）
├── appointments.php      # 預約管理
├── customers.php         # 客戶管理
├── ...                   # 其他主要頁面
├── config.php            # 真實設定（絕不 commit）
├── db.php                # PDO 連線
└── .htaccess             # 共享主機安全保護
```

詳見 [完整實施計劃](https://github.com/yanshekki/salonease/blob/main/plan.md)（開發用）。

---

## Git 工作流程（嚴格遵守）

本專案**每完成一段有意義的工作**（無論 plan、寫 code、修改）之後：
1. `git add .`
2. `git commit -m "清晰描述"`
3. `git push origin main`
4. 保持 `main` 永遠是最新的、可部署的狀態

**嚴禁**長期停留在 feature branch。

---

## 常用熱鍵參考

系統極度重視鍵盤操作，以下為常用熱鍵摘要：

### 全域熱鍵
- `?` — 顯示目前頁面完整熱鍵說明
- `Ctrl + K` — 開啟命令面板（快速跳轉）
- `Alt + H` — 返回概覽首頁
- `Alt + P` — 前往 POS 銷售
- `Alt + A` — 前往預約管理
- `Alt + C` — 前往客戶管理
- `Alt + R` — 前往報表
- `Alt + M` — 前往佣金查詢
- `Alt + S` — 前往系統設定
- `F9` — 打印上一張收據（58mm 熱感紙）

### POS 頁專屬
- `S` — 批量指派員工
- `F9` — 打印上一張收據

### 報表 / 佣金頁專屬
- `T` — 切換至「今日」
- `W` — 切換至「本週」
- `M` — 切換至「本月」
- `R` — 重新載入資料
- `F5` — 重新載入資料（不刷新整頁）

**提示**：大部分頁面按 `?` 即可查看即時熱鍵清單。

---

## Phase 開發進度

- [x] Plan Mode 完成 + 使用者批准
- [ ] Phase 0：基礎架構 + 認證 + Shell UI + Hotkey 框架
- [ ] Phase 1：客戶 / 服務 / 產品 / 套票 / 員工 CRUD
- [ ] Phase 2：預約系統（衝突檢查）
- [ ] Phase 3：POS 核心 + 交易 + 打印功能
- [ ] Phase 4：報表 + 設定 + 整體打磨
- [ ] Phase 5：文件 + 部署 + v1.0.0

---

## 授權與貢獻

本專案由 xAI Grok 協助開發，目標做出真正好用、專業、易維護的香港美容院系統。

歡迎任何建議與改進（請先開 Issue 討論）。

---

**SalonEase** — 簡單 · 專業 · 高效 · 專為香港細店而生
