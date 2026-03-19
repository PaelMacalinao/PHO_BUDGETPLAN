-- ============================================================
-- PHO Budgeting System — Version Control Migration
-- Adds yearly budget version support
-- ============================================================

USE `pho_budgeting`;

-- ────────────────────────────────────────────────
-- 1. Create the budget versions table
-- ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `tbl_budget_versions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `year_name`  VARCHAR(50)  NOT NULL,
  `is_active`  TINYINT(1)   NOT NULL DEFAULT 0,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_year_name` (`year_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed the current fiscal year as the first active version
INSERT IGNORE INTO `tbl_budget_versions` (`year_name`, `is_active`) VALUES ('FY 2026', 1);

-- ────────────────────────────────────────────────
-- 2. Add version_id column to budget proposals
-- ────────────────────────────────────────────────
ALTER TABLE `tbl_budget_proposals`
  ADD COLUMN `version_id` INT UNSIGNED NULL AFTER `id`,
  ADD INDEX `idx_version` (`version_id`),
  ADD CONSTRAINT `fk_bp_version` FOREIGN KEY (`version_id`)
      REFERENCES `tbl_budget_versions`(`id`)
      ON UPDATE CASCADE ON DELETE RESTRICT;

-- ────────────────────────────────────────────────
-- 3. Assign all existing proposals to the active version
-- ────────────────────────────────────────────────
UPDATE `tbl_budget_proposals`
   SET `version_id` = (SELECT `id` FROM `tbl_budget_versions` WHERE `is_active` = 1 LIMIT 1)
 WHERE `version_id` IS NULL;
