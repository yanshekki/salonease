<?php
/**
 * Migration: 001 - Initial Schema
 * 建立 SalonEase 所有基礎表格
 */

return [
    'up' => function($pdo) {
        // 執行 schema.sql
        $schemaFile = __DIR__ . '/../sql/schema.sql';
        if (!file_exists($schemaFile)) {
            throw new Exception("找不到 schema.sql");
        }

        $sql = file_get_contents($schemaFile);
        // 分割多個 SQL 語句執行
        $statements = array_filter(explode(';', $sql));

        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement) {
                $pdo->exec($statement);
            }
        }

        echo "✓ 初始資料表建立完成\n";
    },

    'down' => function($pdo) {
        // 危險操作，正式環境不建議使用
        throw new Exception("不支援 rollback 初始 schema");
    }
];