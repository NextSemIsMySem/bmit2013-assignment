-- Migration for databases imported BEFORE the superadmin role existed.
-- Adds 'superadmin' to the role ENUM without touching existing rows.
--
-- Run with: mysql -u root fitnessdb < migrate_superadmin.sql
-- Then seed the admin accounts: mysql -u root fitnessdb < seed_admins.sql
--
-- A fresh `mysql -u root < fitnessdb.sql` already includes this change and
-- does not need the statement below.

USE `fitnessdb`;

ALTER TABLE `user` MODIFY `role` ENUM('superadmin','admin','member') NOT NULL DEFAULT 'member';
