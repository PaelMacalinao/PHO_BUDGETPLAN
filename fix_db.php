<?php
/**
 * One-time script: drops program_id FK and column from tbl_budget_proposals.
 * Run once via browser, then delete this file.
 */
require_once __DIR__ . '/config.php';

try {
    $pdo = getConnection();
    $pdo->exec("ALTER TABLE `tbl_budget_proposals` DROP FOREIGN KEY `fk_bp_program`");
    $pdo->exec("ALTER TABLE `tbl_budget_proposals` DROP INDEX `idx_program`");
    $pdo->exec("ALTER TABLE `tbl_budget_proposals` DROP COLUMN `program_id`");
    echo '<h2 style="color:green">Database fixed successfully.</h2>';
    echo '<p><code>program_id</code> column and its FK constraint have been removed from <code>tbl_budget_proposals</code>.</p>';
    echo '<p><strong>Delete this file now:</strong> <code>fix_db.php</code></p>';
} catch (PDOException $e) {
    echo '<h2 style="color:red">Error</h2>';
    echo '<pre>' . htmlspecialchars($e->getMessage()) . '</pre>';
}
