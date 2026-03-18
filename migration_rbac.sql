-- ============================================================
-- PHO Budgeting System — RBAC Migration
-- Run this on an EXISTING pho_budgeting database.
-- If starting fresh, use database.sql instead (already includes these).
-- ============================================================

USE `pho_budgeting`;

-- 1. Create users table
CREATE TABLE IF NOT EXISTS `tbl_users` (
  `id`         INT UNSIGNED         NOT NULL AUTO_INCREMENT,
  `fullname`   VARCHAR(255)         NOT NULL,
  `username`   VARCHAR(100)         NOT NULL,
  `password`   VARCHAR(255)         NOT NULL,
  `role`       ENUM('admin','staff') NOT NULL DEFAULT 'staff',
  `created_at` DATETIME             NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Seed default users (admin/admin123, staff/staff123)
INSERT INTO `tbl_users` (`fullname`, `username`, `password`, `role`) VALUES
  ('System Administrator', 'admin', '$2y$10$j2/4yo6GmaeaEW.K6XTUXu4tQX.HXtn9Okhw0886He44o9R5bUwty', 'admin'),
  ('Staff User',           'staff', '$2y$10$D5FagNfUiha.KgPwuh91i.VPAae.nxfUE8UxtFKHCD.owNhWl3ds6', 'staff');

-- 3. Add created_by column to budget proposals
ALTER TABLE `tbl_budget_proposals`
  ADD COLUMN `created_by` INT UNSIGNED NULL DEFAULT NULL AFTER `justification`,
  ADD INDEX  `idx_created_by` (`created_by`),
  ADD CONSTRAINT `fk_bp_created_by` FOREIGN KEY (`created_by`) REFERENCES `tbl_users`(`id`) ON UPDATE CASCADE ON DELETE SET NULL;
