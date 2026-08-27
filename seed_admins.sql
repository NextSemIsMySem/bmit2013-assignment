-- Admin + superadmin accounts for the `user` table (fitnessdb)
--
-- Admin portal (unadvertised, type the URL):  http://localhost:8000/admin/a4mi3.php
-- Members log in at:                          http://localhost:8000/login.php
--
-- Demo password for ALL three accounts below: Admin@123
-- (meets the 8-50 + upper/lower/digit/symbol policy; SHA1-hashed like seed_users.sql)
--
--   superadmin@forgefit.test  / superadmin   -> role: superadmin
--   ops.admin@forgefit.test   / ops.admin    -> role: admin
--   support.admin@forgefit.test / support.admin -> role: admin
--
-- Superadmins are provisioned here only: the Create Admin page can mint
-- `admin` accounts but never another superadmin.
--
-- Import with: mysql -u root fitnessdb < seed_admins.sql
-- Requires the role ENUM to include 'superadmin'. If your database was
-- imported before that change, run this first:
--   ALTER TABLE `user` MODIFY `role` ENUM('superadmin','admin','member') NOT NULL DEFAULT 'member';

USE `fitnessdb`;

INSERT INTO `user` (`username`, `name`, `email`, `password`, `role`, `active`, `created_at`) VALUES
    ('superadmin', 'Super Admin', 'superadmin@forgefit.test', 'a29c57c6894dee6e8251510d58c07078ee3f49bf', 'superadmin', 1, '2025-01-01 09:00:00'),
    ('ops.admin', 'Operations Admin', 'ops.admin@forgefit.test', 'a29c57c6894dee6e8251510d58c07078ee3f49bf', 'admin', 1, '2025-01-02 09:00:00'),
    ('support.admin', 'Support Admin', 'support.admin@forgefit.test', 'a29c57c6894dee6e8251510d58c07078ee3f49bf', 'admin', 1, '2025-01-03 09:00:00');
