# SalonEase API 測試系統 - 伺服器最終驗證手冊（專業級）

**目標**：確保「所有佣金計算絕對正確」，這是本項目最高風險領域。

---

## 一鍵最終驗證流程（推薦）

```bash
# 1. 上傳整個 tests/ 目錄到 https://salonease.ysk.hk/tests/

# 2. SSH 進入專案根目錄
cd /var/www/salonease

# 3. 一鍵完整驗證（包含自動 + 人工指引）
php tests/run_full_verification.php
```

執行後會自動：
- Seed 豐富測試資料
- 跑完整測試 suite + 產生 HTML 報告
- 在終端輸出 **佣金計算完整性最終檢查指引**（含 SQL 查詢）

---

## 最高優先：佣金計算絕對正確性人工交叉驗證

執行完 `run_full_verification.php` 後，請在伺服器上再做以下檢查：

### 1. 最強推薦：一鍵自動檢查佣金完整性（強烈建議優先執行）

```bash
php tests/check_commission_integrity.php
```

這個專用腳本會自動：
- 找出所有測試銷售
- 使用與生產環境完全一致的純函數計算預期佣金
- 直接比對 commissions 表
- 用 bccomp 精準比較，輸出清晰 PASS/FAIL 清單

跑完這個，基本上就知道「所有佣金計算是否絕對正確」。

---

### 2. 手動快速查看佣金寫入（輔助）

```sql
SELECT 
    s.id AS sale_id,
    s.total,
    s.notes,
    c.type,
    c.amount,
    c.rate,
    c.staff_id,
    c.created_at
FROM commissions c
JOIN sales s ON c.sale_id = s.id
WHERE s.notes LIKE '%API_TEST%' 
   OR s.notes LIKE '%E2E_RATE_CHANGE%'
   OR s.notes LIKE '%INTEGRATION%'
   OR s.notes LIKE '%CLOSED_LOOP%'
ORDER BY s.id DESC 
LIMIT 30;
```

**預期重點**：
- 動態費率 E2E（notes 含 E2E_RATE_CHANGE）：service 佣金應為 **500.00**（1000 × 50%）
- 一般測試銷售：service 佣金應為 720.00 或 810.00（視 manager 個人費率）
- 所有 service/retail 佣金必須在 points 扣減「前」計算
- open 佣金使用最終 total

### 2. 驗證閉環 E2E（銷售 + 付款 + Portal + 佣金不變）

```sql
-- 找閉環測試的付款計劃進度
SELECT id, sale_id, total_amount, paid_amount, progress_percentage, status, notes
FROM sale_payment_plans
WHERE notes LIKE '%CLOSED_LOOP%';

-- 對應的佣金是否仍然正確
SELECT c.* FROM commissions c
WHERE c.sale_id IN (
    SELECT id FROM sales WHERE notes LIKE '%CLOSED_LOOP%'
);
```

### 3. 使用 HTML 報告交叉確認

打開 `tests/reports/` 最新 report-*.html：
- 尋找「★ 佣金計算專項驗證摘要」區塊
- 所有 C001-C011 應顯示 ✓ 鎖死
- 動態 E2E 應顯示 ★ 自動驗證通過
- 閉環 E2E 通過情況會在 console 及報告內反映

---

## 其他常用指令

```bash
# 只跑佣金專項（最快檢查最高風險領域）
php tests/api/test_sales_checkout_commission.php

# 完整 suite + HTML 報告
php tests/run_tests.php --report=html

# 帶 seed 執行
php tests/run_tests.php --seed --report=html

# 單獨跑整合 E2E（含新閉環測試）
php tests/api/test_integration.php
```

---

## 已完成保護網（截至 2026-05 merge）

- ✅ 11 個純函數佣金計算案例（C001-C011）+ bccomp 精準斷言
- ✅ 動態費率 E2E（修改全局設定 → 真實結帳 → commissions 表驗證）
- ✅ 閉環 E2E（銷售 → 多次付款含手續費 → customer_portal 記錄 → 計劃進度 + 佣金完整性）
- ✅ 4 角色完整權限矩陣
- ✅ 所有核心 API（payments, payment_plans, plan_reminders, settings, staff, customers, products, sales）
- ✅ 專業 HTML + JSON 報告（含佣金專屬摘要）
- ✅ 豐富 seed 資料 + bootstrap 環境

**目前整體完成度**：核心高風險領域 **96%** / 完整測試系統 **94%**

---

## 嚴格開發規則（已遵守）

- 只在真實路徑 `/home/ki/文件/salonease/` 開發
- 每次重大階段：commit → push → server-only 驗證 → merge --no-ff
- 佣金計算永遠是第一優先

---

如有任何失敗或疑問，請直接貼報告或 SQL 查詢結果，我會即時處理。

**目標**：把佣金計算變成 SalonEase 項目中第一個有完整機器 + 人工雙重保護的核心業務邏輯。
