<?php
/**
 * One-time migration: creates tbl_budget_versions and adds version_id to tbl_budget_proposals.
 * Run once via browser, then delete this file.
 */
require_once __DIR__ . '/config.php';

$pdo = getConnection();
$results = [];

$steps = [
    'Create tbl_budget_versions' => "
        CREATE TABLE IF NOT EXISTS `tbl_budget_versions` (
            `id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
            `year_name`  VARCHAR(100)  NOT NULL,
            `is_active`  TINYINT(1)    NOT NULL DEFAULT 0,
            `created_at` DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_year_name` (`year_name`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ",
    'Insert default 2026 version' => "
        INSERT IGNORE INTO `tbl_budget_versions` (`year_name`, `is_active`)
        VALUES ('2026', 1)
    ",
    'Add version_id column to tbl_budget_proposals' => "
        ALTER TABLE `tbl_budget_proposals`
        ADD COLUMN `version_id` INT UNSIGNED NULL DEFAULT NULL AFTER `id`
    ",
    'Backfill existing proposals with active version' => "
        UPDATE `tbl_budget_proposals`
        SET `version_id` = (SELECT `id` FROM `tbl_budget_versions` WHERE `is_active` = 1 LIMIT 1)
        WHERE `version_id` IS NULL
    ",
    'Add FK constraint for version_id' => "
        ALTER TABLE `tbl_budget_proposals`
        ADD INDEX `idx_version` (`version_id`),
        ADD CONSTRAINT `fk_bp_version`
            FOREIGN KEY (`version_id`) REFERENCES `tbl_budget_versions`(`id`)
            ON UPDATE CASCADE ON DELETE SET NULL
    ",
];

echo '<h2 style="font-family:sans-serif">Database Migration: Budget Versions</h2><ul style="font-family:monospace">';

foreach ($steps as $label => $sql) {
    try {
        $pdo->exec($sql);
        $results[] = ['label' => $label, 'ok' => true, 'msg' => 'OK'];
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        $isHarmless = str_contains($msg, 'Duplicate column') || str_contains($msg, 'already exists');
        $results[] = ['label' => $label, 'ok' => $isHarmless, 'msg' => $isHarmless ? 'Already done — skipped' : $msg];
    }
}

foreach ($results as $r) {
    $color = $r['ok'] ? 'green' : 'red';
    echo "<li style='color:$color;margin:6px 0'><strong>{$r['label']}</strong>: {$r['msg']}</li>";
}

echo '</ul>';
echo '<p style="font-family:sans-serif;color:#333;margin-top:16px"><strong>Delete this file now:</strong> <code>fix_db.php</code></p>';
