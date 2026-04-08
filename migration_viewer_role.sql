ALTER TABLE `tbl_users`
  MODIFY COLUMN `role` ENUM('admin','staff','viewer') NOT NULL DEFAULT 'staff';
