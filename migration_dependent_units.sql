-- ============================================================
-- PHO Budgeting System — Dependent Units Migration
-- Links tbl_units to tbl_fund_sources for dependent dropdowns.
-- Run this AFTER migration_rbac.sql on an existing database.
-- ============================================================

USE `pho_budgeting`;

-- 1. Add fund_source_id column to tbl_units
ALTER TABLE `tbl_units`
  ADD COLUMN `fund_source_id` INT UNSIGNED NULL AFTER `unit_name`,
  ADD INDEX `idx_unit_fund_source` (`fund_source_id`);

ALTER TABLE `tbl_units`
  ADD CONSTRAINT `fk_unit_fund_source`
    FOREIGN KEY (`fund_source_id`) REFERENCES `tbl_fund_sources`(`id`)
    ON UPDATE CASCADE ON DELETE SET NULL;

-- 2. Clear existing data (safe only if no proposals reference old units)
--    If you have existing proposals, run: DELETE FROM tbl_budget_proposals; first.
DELETE FROM `tbl_units`;

-- 3. Lookup fund source IDs dynamically
SET @gf = (SELECT `id` FROM `tbl_fund_sources` WHERE `fund_name` = 'General Fund');
SET @sp = (SELECT `id` FROM `tbl_fund_sources` WHERE `fund_name` = 'Special Project');

-- 4. Seed General Fund units (28)
INSERT INTO `tbl_units` (`unit_name`, `fund_source_id`) VALUES
  ('ORAL HEALTH PROGRAM',                                              @gf),
  ('EXPANDED PROGRAM ON IMMUNIZATION (EPI)',                           @gf),
  ('SENIOR CITIZEN''S PROGRAM',                                        @gf),
  ('HIV PREVENTION AND CONTROL PROGRAM',                               @gf),
  ('FIELD HEALTH SERVICES INFORMATION SYSTEM (FHSIS)',                 @gf),
  ('ENVIRONMENTAL SANITATION AND PUBLIC HEALTH LABORATORY (ESPHL)',    @gf),
  ('LEPROSY CONTROL PROGRAM',                                         @gf),
  ('TRADITIONAL COMPLEMENTARY ALTERNATIVE MEDICINE PROGRAM (TCAM)',    @gf),
  ('FAMILY PLANNING PROGRAM',                                         @gf),
  ('NON-COMMUNICABLE DISEASES PREVENTION CONTROL PROGRAM (NCD)',       @gf),
  ('MATERNAL CARE PROGRAM',                                            @gf),
  ('HEALTH EDUCATION AND PROMOTION UNIT (HEPU)',                       @gf),
  ('NATIONAL VOLUNTARY BLOOD SERVICES PROGRAM (NVBSP)',                @gf),
  ('ADOLESCENT HEALTH AND DEVELOPMENT PROGRAM (AHDP)',                 @gf),
  ('ENVIRONMENTAL SANITATION PROGRAM (ENSAN)',                         @gf),
  ('RABIES PREVENTION AND CONTROL PROGRAM',                            @gf),
  ('PROVINCIAL EPIDEMIOLOGY AND SURVEILLANCE UNIT (PESU)',             @gf),
  ('DISASTER RISK REDUCTION AND MANAGEMENT IN HEALTH (DRRM-H)',       @gf),
  ('INDIGENOUS PEOPLES HEALTH PROGRAM',                                @gf),
  ('TUBERCULOSIS CONTROL PROGRAM (TB)',                                @gf),
  ('EMERGING/RE-EMERGING INFECTIOUS DISEASE PROGRAM (EREID)',          @gf),
  ('ADMINISTRATIVE SUPPORT',                                           @gf),
  ('HEALTH SERVICE DELIVERY DIVISION HEAD',                            @gf),
  ('COLD CHAIN',                                                       @gf),
  ('SUPPLY AND LOGISTICS UNIT',                                        @gf),
  ('HEAD OF OFFICE',                                                   @gf),
  ('HEALTH SYSTEM SUPPORT DIVISION HEAD',                              @gf),
  ('PHO CLINIC',                                                       @gf);

-- 5. Seed Special Project units (6)
INSERT INTO `tbl_units` (`unit_name`, `fund_source_id`) VALUES
  ('MOLECULAR BIOLOGY LABORATORY',                                                    @sp),
  ('PROVINCIAL NUTRITION PROGRAM',                                                    @sp),
  ('COMPREHENSIVE BARANGAY HEALTH WORKER DEVELOPMENT PROJECT (CBHWDP)',               @sp),
  ('COUNTERPART TO BHW MALAMPAYA PROJECT (CBHWMP)',                                   @sp),
  ('KILUSANG LIGTAS MALARIA (KLM)',                                                   @sp),
  ('COUNTERPART TO DOH PROGRAMS (CDOHP)',                                             @sp);
