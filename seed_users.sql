-- Sample data for the `user` table (fitnessdb)
-- Password for every account: "password" (bcrypt hash below)
-- Import with: mysql -u root fitnessdb < seed_users.sql

USE `fitnessdb`;

INSERT INTO `user` (`username`, `name`, `email`, `password`, `role`, `created_at`) VALUES
    ('ali.hassan', 'Ali Hassan', 'ali.hassan@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-01-05 09:00:00'),
    ('siti.aminah', 'Siti Aminah', 'siti.aminah@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-01-12 09:00:00'),
    ('wei.chen', 'Wei Chen', 'wei.chen@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-01-18 09:00:00'),
    ('kavitha.raj', 'Kavitha Raj', 'kavitha.raj@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-01-25 09:00:00'),
    ('daryl.goh', 'Daryl Goh', 'daryl.goh@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'admin', '2025-02-01 09:00:00'),
    ('nur.aisyah', 'Nur Aisyah', 'nur.aisyah@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-02-03 09:00:00'),
    ('muthu.samy', 'Muthu Samy', 'muthu.samy@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-02-09 09:00:00'),
    ('jason.tan', 'Jason Tan', 'jason.tan@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-02-14 09:00:00'),
    ('farah.izzati', 'Farah Izzati', 'farah.izzati@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-02-20 09:00:00'),
    ('kumar.velu', 'Kumar Velu', 'kumar.velu@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-02-27 09:00:00'),
    ('hui.ling', 'Hui Ling', 'hui.ling@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-03-02 09:00:00'),
    ('admin.root', 'System Administrator', 'admin.root@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'admin', '2025-03-05 09:00:00'),
    ('zulkifli.abu', 'Zulkifli Abu', 'zulkifli.abu@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-03-11 09:00:00'),
    ('priya.devi', 'Priya Devi', 'priya.devi@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-03-16 09:00:00'),
    ('benjamin.lee', 'Benjamin Lee', 'benjamin.lee@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-03-22 09:00:00'),
    ('aina.sofea', 'Aina Sofea', 'aina.sofea@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-03-29 09:00:00'),
    ('ravi.chandran', 'Ravi Chandran', 'ravi.chandran@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-04-04 09:00:00'),
    ('michelle.wong', 'Michelle Wong', 'michelle.wong@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-04-10 09:00:00'),
    ('amir.faiz', 'Amir Faiz', 'amir.faiz@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-04-16 09:00:00'),
    ('grace.tan', 'Grace Tan', 'grace.tan@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'admin', '2025-04-21 09:00:00'),
    ('syafiq.rahman', 'Syafiq Rahman', 'syafiq.rahman@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-04-27 09:00:00'),
    ('deepika.nair', 'Deepika Nair', 'deepika.nair@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-05-03 09:00:00'),
    ('hafiz.omar', 'Hafiz Omar', 'hafiz.omar@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-05-09 09:00:00'),
    ('linda.chong', 'Linda Chong', 'linda.chong@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-05-15 09:00:00'),
    ('sarah.lim', 'Sarah Lim', 'sarah.lim@example.com', '$2y$10$Sj3MAmM1ciN41hQ.ti04HeBdeVZcKff1TuurnmDEhnnxuKSUm.iNK', 'customer', '2025-05-20 09:00:00');
