<?php
/**
 * SalonEase - 簡單資料庫手動備份（純 PHP 實作）
 * 不依賴 mysqldump 命令，權限友善
 */

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../config.php';

function create_database_backup(): string
{
    $pdo = Database::getConnection();

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);

    $backup = "-- SalonEase Database Backup\n";
    $backup .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $backup .= "-- Database: " . DB_NAME . "\n\n";
    $backup .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        // 結構
        $create = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $backup .= "DROP TABLE IF EXISTS `$table`;\n";
        $backup .= $create['Create Table'] . ";\n\n";

        // 資料
        $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        if ($rows) {
            $columns = array_keys($rows[0]);
            $colList = '`' . implode('`, `', $columns) . '`';

            foreach ($rows as $row) {
                $values = array_map(function ($v) use ($pdo) {
                    if ($v === null) return 'NULL';
                    return $pdo->quote($v);
                }, array_values($row));

                $backup .= "INSERT INTO `$table` ($colList) VALUES (" . implode(', ', $values) . ");\n";
            }
            $backup .= "\n";
        }
    }

    $backup .= "SET FOREIGN_KEY_CHECKS=1;\n";

    // 儲存到 uploads/backup/
    $backupDir = __DIR__ . '/../uploads/backup';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }

    $filename = 'salonease_backup_' . date('Ymd_His') . '.sql';
    $filepath = $backupDir . '/' . $filename;

    file_put_contents($filepath, $backup);

    // 壓縮成 gz
    $gzFile = $filepath . '.gz';
    $gz = gzopen($gzFile, 'w9');
    gzwrite($gz, $backup);
    gzclose($gz);

    // 刪除原始 sql（保留 gz）
    unlink($filepath);

    return $gzFile;
}