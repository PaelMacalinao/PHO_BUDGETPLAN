-- ============================================================
-- PHO Budgeting System — 2026 Consolidated Budget Proposal
-- Normalized Relational Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS `pho_budgeting`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `pho_budgeting`;

-- Drop in reverse dependency order
DROP TABLE IF EXISTS `tbl_budget_proposals`;
DROP TABLE IF EXISTS `tbl_account_codes`;
DROP TABLE IF EXISTS `tbl_programs_units`;
DROP TABLE IF EXISTS `tbl_units`;
DROP TABLE IF EXISTS `tbl_fund_sources`;
DROP TABLE IF EXISTS `tbl_indicators`;

-- ────────────────────────────────────────────────
-- REFERENCE TABLES (Master Data)
-- ────────────────────────────────────────────────

CREATE TABLE `tbl_units` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `unit_name` VARCHAR(255) NOT NULL,
  `created_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_unit_name` (`unit_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_programs_units` (
  `id`           INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `program_name` VARCHAR(255)  NOT NULL,
  `created_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_program_name` (`program_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_account_codes` (
  `id`            INT UNSIGNED                NOT NULL AUTO_INCREMENT,
  `account_code`  VARCHAR(50)                 NOT NULL,
  `account_title` VARCHAR(255)                NOT NULL,
  `expense_class` ENUM('MOOE','CO','PS')      NOT NULL,
  `created_at`    DATETIME                    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME                    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_account_code` (`account_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_fund_sources` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `fund_name`  VARCHAR(255) NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fund_name` (`fund_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_indicators` (
  `id`                    INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `indicator_description` TEXT         NOT NULL,
  `created_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────
-- MAIN TRANSACTION TABLE
-- ────────────────────────────────────────────────

CREATE TABLE `tbl_budget_proposals` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `ppa_description`  TEXT          NOT NULL COMMENT 'Program / Project / Activity description',

  -- Foreign Keys
  `program_id`       INT UNSIGNED  NOT NULL,
  `account_id`       INT UNSIGNED  NOT NULL,
  `fund_source_id`   INT UNSIGNED  NOT NULL,
  `indicator_id`     INT UNSIGNED  NOT NULL,
  `unit_id`          INT UNSIGNED  NOT NULL,

  -- Physical Targets (Quarterly)
  `q1_target`        INT UNSIGNED  NOT NULL DEFAULT 0,
  `q2_target`        INT UNSIGNED  NOT NULL DEFAULT 0,
  `q3_target`        INT UNSIGNED  NOT NULL DEFAULT 0,
  `q4_target`        INT UNSIGNED  NOT NULL DEFAULT 0,
  `target_total`     INT UNSIGNED  NOT NULL DEFAULT 0,

  -- Financial Allocation (Monthly)
  `jan_amt`          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `feb_amt`          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `mar_amt`          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `apr_amt`          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `may_amt`          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `jun_amt`          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `jul_amt`          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `aug_amt`          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `sep_amt`          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `oct_amt`          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `nov_amt`          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `dec_amt`          DECIMAL(15,2) NOT NULL DEFAULT 0.00,
  `total_allocation` DECIMAL(15,2) NOT NULL DEFAULT 0.00,

  -- Other
  `justification`    TEXT          NOT NULL,
  `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),

  CONSTRAINT `fk_bp_program`     FOREIGN KEY (`program_id`)     REFERENCES `tbl_programs_units`(`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_bp_account`     FOREIGN KEY (`account_id`)     REFERENCES `tbl_account_codes`(`id`)  ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_bp_fund_source` FOREIGN KEY (`fund_source_id`) REFERENCES `tbl_fund_sources`(`id`)   ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_bp_indicator`   FOREIGN KEY (`indicator_id`)   REFERENCES `tbl_indicators`(`id`)     ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_bp_unit`        FOREIGN KEY (`unit_id`)        REFERENCES `tbl_units`(`id`)          ON UPDATE CASCADE ON DELETE RESTRICT,

  INDEX `idx_program`     (`program_id`),
  INDEX `idx_account`     (`account_id`),
  INDEX `idx_fund_source` (`fund_source_id`),
  INDEX `idx_indicator`   (`indicator_id`),
  INDEX `idx_unit`        (`unit_id`),
  INDEX `idx_created`     (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────
-- SEED DATA
-- ────────────────────────────────────────────────

INSERT INTO `tbl_units` (`unit_name`) VALUES
  ('PHO CLINIC'),
  ('ADMINISTRATIVE SUPPORT'),
  ('ORAL HEALTH PROGRAM'),
  ('PESU');

INSERT INTO `tbl_programs_units` (`program_name`) VALUES
  ('Provision of Basic Health Services at PHO Public Health Station'),
  ('Provision of Technical Assistance, Monitoring and Planning'),
  ('Response to Health Emergencies'),
  ('Mobilization and Delivery of Logistics to Beneficiaries'),
  ('Networking, Coordination and Communication with Municipal, National and Other Partner Agencies'),
  ('Support to Office/Clinic Operations and Other Dues and Medico - Legal Services');

INSERT INTO `tbl_fund_sources` (`fund_name`) VALUES
  ('General Fund'),
  ('Special Project');

-- MOOE Account Codes (33)
INSERT INTO `tbl_account_codes` (`account_code`, `account_title`, `expense_class`) VALUES
  ('50201010', 'Traveling Expenses - Local',                                          'MOOE'),
  ('50202010', 'Training Expenses',                                                   'MOOE'),
  ('50203010', 'Office Supplies Expenses',                                            'MOOE'),
  ('50203020', 'Accountable Forms Expenses',                                          'MOOE'),
  ('50203050', 'Food Supplies Expenses',                                              'MOOE'),
  ('50203070', 'Drugs and Medicines Expenses',                                        'MOOE'),
  ('50203080', 'Medical, Dental and Laboratory Supplies Expenses',                    'MOOE'),
  ('50203090', 'Fuel, Oil and Lubricants Expenses',                                   'MOOE'),
  ('50203210', 'Semi-Expendable Machinery and Equipment Expenses',                    'MOOE'),
  ('50203220', 'Semi-Expendable Furniture, Fixtures and Books Expenses',              'MOOE'),
  ('50203990', 'Other Supplies and Materials Expenses',                               'MOOE'),
  ('50205010', 'Postage and Courier Services',                                        'MOOE'),
  ('50205020', 'Telephone Expenses',                                                  'MOOE'),
  ('50205030', 'Internet Subscription Expenses',                                      'MOOE'),
  ('50205040', 'Cable, Satellite, Telegraph and Radio Expenses',                      'MOOE'),
  ('50206010', 'Awards/Rewards Expenses',                                             'MOOE'),
  ('50206020', 'Prizes',                                                              'MOOE'),
  ('50212990', 'Other General Services',                                              'MOOE'),
  ('50213040', 'Repairs and Maintenance - Buildings and Other Structures',             'MOOE'),
  ('50213050', 'Repairs and Maintenance - Machinery and Equipment',                   'MOOE'),
  ('50213060', 'Repairs and Maintenance - Transportation Equipment',                  'MOOE'),
  ('50213070', 'Repairs and Maintenance - Furniture and Fixtures',                    'MOOE'),
  ('50213210', 'Repairs and Maintenance - Semi-Expendable Machinery and Equipment',   'MOOE'),
  ('50213220', 'Repairs and Maintenance - Semi-Expendable Furniture, Fixtures and Books', 'MOOE'),
  ('50213990', 'Repairs and Maintenance - Other Property, Plant and Equipment',       'MOOE'),
  ('50216010', 'Taxes, Duties and Licenses',                                          'MOOE'),
  ('50216020', 'Fidelity Bond Premiums',                                              'MOOE'),
  ('50299010', 'Advertising Expenses',                                                'MOOE'),
  ('50299020', 'Printing and Publication Expenses',                                   'MOOE'),
  ('50299040', 'Transportation and Delivery Expenses',                                'MOOE'),
  ('50299060', 'Membership Dues and Contributions to Organizations',                  'MOOE'),
  ('50299990', 'Other Maintenance and Operating Expenses',                            'MOOE'),
  ('50301040', 'Bank Charges',                                                        'MOOE');

-- Capital Outlay Account Codes (9)
INSERT INTO `tbl_account_codes` (`account_code`, `account_title`, `expense_class`) VALUES
  ('10705010', 'Machinery',                                'CO'),
  ('10705020', 'Office Equipment',                         'CO'),
  ('10705070', 'Communication Equipment',                  'CO'),
  ('10705090', 'Disaster Response and Rescue Equipment',   'CO'),
  ('10705110', 'Medical Equipment',                        'CO'),
  ('10705120', 'Printing Equipment',                       'CO'),
  ('10705990', 'Other Machinery and Equipment',            'CO'),
  ('10706010', 'Motor Vehicles',                           'CO'),
  ('10707010', 'Furniture and Fixtures',                   'CO');

-- Personal Services Account Codes (3)
INSERT INTO `tbl_account_codes` (`account_code`, `account_title`, `expense_class`) VALUES
  ('50101010', 'Salaries and Wages - Regular',       'PS'),
  ('50102010', 'Salaries and Wages - Casual',        'PS'),
  ('50103010', 'Personal Economic Relief Allowance', 'PS');

INSERT INTO `tbl_indicators` (`indicator_description`) VALUES
  ('No. of clients/patients examine/treated in Medical & RH Services'),
  ('No. of patients given post exposure prophylaxis'),
  ('No. of patients examine/treated in DOTS services'),
  ('No. of clients attended in SHC'),
  ('No. of clients provided dental services in the clinic'),
  ('No. of clients provided dental services during outreach program'),
  ('No. of clients attended for laboratory examinations'),
  ('No. of office, equipment and facilities cleaned and maintained'),
  ('No. of times monitoring & supervision of health programs, conduct of trainings and technical assistance to RHUs'),
  ('No. of health facility with timely, complete, reliable HIS and functional health systems'),
  ('No. of health facility with functional surveillance system'),
  ('No. of health facilities monitored'),
  ('No. of outreach activities conducted'),
  ('No. of trainings, meetings or seminars conducted'),
  ('No. of compliance to documentary requirement of authorities'),
  ('No. of health emergencies/disaster responded'),
  ('No. of monitoring/technical assistance & delivery of supplies done'),
  ('No. of times of the coordination and networking with partner agencies done'),
  ('No. of reports, documents and other issuances prepared'),
  ('No. of annual books, forms, manuals, IEC materials tarpaulin'),
  ('No. of times doctors attended court hearing'),
  ('No. of health managers attended');
