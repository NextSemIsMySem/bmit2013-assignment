SET FOREIGN_KEY_CHECKS = 0;
DROP DATABASE IF EXISTS `fitnessdb`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE DATABASE `fitnessdb` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `fitnessdb`;

-- -----------------------------------------------------------------------------
-- Database schema
-- -----------------------------------------------------------------------------

-- 1. User Table
CREATE TABLE IF NOT EXISTS `user` (
    `user_id` INT AUTO_INCREMENT PRIMARY KEY,
    `email` VARCHAR(255) NOT NULL UNIQUE,
    `pending_email` VARCHAR(255) NULL DEFAULT NULL,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `password` VARCHAR(255) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `role` ENUM('superadmin', 'admin', 'member') NOT NULL DEFAULT 'member',
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `email_verified` TINYINT(1) NOT NULL DEFAULT 1,
    `photo` VARCHAR(100) NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. Address Table
CREATE TABLE IF NOT EXISTS `address` (
    `address_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `street` VARCHAR(255) NOT NULL,
    `city` VARCHAR(100) NOT NULL,
    `state` VARCHAR(100) NOT NULL,
    `postal_code` VARCHAR(20) NOT NULL,
    `country` VARCHAR(100) NOT NULL,
    `label` VARCHAR(50) NOT NULL DEFAULT 'Address',
    `latitude` DECIMAL(10,7) NULL,
    `longitude` DECIMAL(10,7) NULL,
    `is_default` BOOLEAN NOT NULL DEFAULT FALSE,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (`user_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 3. Category Table
CREATE TABLE IF NOT EXISTS `category` (
    `category_id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 4. Product Table
CREATE TABLE IF NOT EXISTS `product` (
    `product_id` INT AUTO_INCREMENT PRIMARY KEY,
    `category_id` INT NOT NULL,
    `product_name` VARCHAR(150) NOT NULL,
    `price` DECIMAL(10,2) NOT NULL,
    `weight` DECIMAL(8,2) NOT NULL DEFAULT 0.00,
    `description` TEXT NULL,
    `stock` INT NOT NULL DEFAULT 0,
    `availability` BOOLEAN NOT NULL DEFAULT TRUE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`category_id`) REFERENCES `category`(`category_id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 5. ProductImage Table
CREATE TABLE IF NOT EXISTS `product_image` (
    `product_id` INT NOT NULL,
    `product_imageid` VARCHAR(255) NOT NULL,
    PRIMARY KEY (`product_id`, `product_imageid`),
    FOREIGN KEY (`product_id`) REFERENCES `product`(`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 6. CartItem Table
CREATE TABLE IF NOT EXISTS `cart_item` (
    `user_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `product_id`),
    FOREIGN KEY (`user_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `product`(`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 7. WishlistItem Table
CREATE TABLE IF NOT EXISTS `wishlist_item` (
    `user_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `added_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `product_id`),
    FOREIGN KEY (`user_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `product`(`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 8. VoucherConfiguration Table
CREATE TABLE IF NOT EXISTS `voucher_configuration` (
    `voucher_id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(100) NOT NULL,
    `discount_type` ENUM('fixed', 'percentage') NOT NULL DEFAULT 'fixed',
    `discount_value` DECIMAL(10,2) NULL,
    `discount_percentage` DECIMAL(5,2) NULL,
    `minimum_spend` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `start_date` DATETIME NOT NULL,
    `end_date` DATETIME NOT NULL,
    `status` ENUM('active', 'disabled', 'expired') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 9. Voucher Table (individual codes batch-generated under a configuration)
CREATE TABLE IF NOT EXISTS `voucher` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `voucher_id` INT NOT NULL,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `status` ENUM('active', 'used', 'disabled') NOT NULL DEFAULT 'active',
    `used_at` DATETIME NULL,
    FOREIGN KEY (`voucher_id`) REFERENCES `voucher_configuration`(`voucher_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 10. Order Table
CREATE TABLE IF NOT EXISTS `orders` (
    `order_id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `voucher_id` INT NULL,
    `subtotal` DECIMAL(10,2) NOT NULL,
    `shipping_fee` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `discount_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `shipping_street` VARCHAR(255) NOT NULL,
    `shipping_city` VARCHAR(100) NOT NULL,
    `shipping_state` VARCHAR(100) NOT NULL,
    `shipping_postal_code` VARCHAR(20) NOT NULL,
    `shipping_country` VARCHAR(100) NOT NULL,
    `status` ENUM('pending', 'paid', 'shipped', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    `cancellation_reason` VARCHAR(255) NULL DEFAULT NULL,
    `cancellation_requested_at` TIMESTAMP NULL DEFAULT NULL,
    `cancellation_rejection_reason` VARCHAR(255) NULL DEFAULT NULL,
    `cancellation_rejected_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `user`(`user_id`) ON DELETE RESTRICT,
    FOREIGN KEY (`voucher_id`) REFERENCES `voucher`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 11. OrderProduct Table
CREATE TABLE IF NOT EXISTS `order_product` (
    `order_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `quantity` INT NOT NULL DEFAULT 1,
    `unit_price` DECIMAL(10,2) NOT NULL,
    `final_price` DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (`order_id`, `product_id`),
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `product`(`product_id`) ON DELETE RESTRICT
) ENGINE=InnoDB;

-- 12. Payment Table
CREATE TABLE IF NOT EXISTS `payment` (
    `payment_id` INT AUTO_INCREMENT PRIMARY KEY,
    `order_id` INT NOT NULL UNIQUE,
    `amount` DECIMAL(10,2) NOT NULL,
    `payment_method` VARCHAR(50) NOT NULL,
    `status` ENUM('pending', 'success', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    `transaction_reference` VARCHAR(255) NULL DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `orders`(`order_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 13. StockReminder Table
CREATE TABLE IF NOT EXISTS `stock_reminder` (
    `user_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `shown` BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (`user_id`, `product_id`),
    FOREIGN KEY (`user_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE,
    FOREIGN KEY (`product_id`) REFERENCES `product`(`product_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 14. Token Table (password reset links)
CREATE TABLE IF NOT EXISTS `token` (
    `id`      VARCHAR(100) NOT NULL PRIMARY KEY,
    `expire`  DATETIME NOT NULL,
    `user_id` INT NOT NULL,
    `type`    ENUM('reset', 'verification') NOT NULL DEFAULT 'reset',
    FOREIGN KEY (`user_id`) REFERENCES `user`(`user_id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- -----------------------------------------------------------------------------
-- Seed data
-- -----------------------------------------------------------------------------

-- Sample data for the `user` table (fitnessdb)
-- Password for every account: "password" (SHA1 hash below)
INSERT INTO `user` (`username`, `name`, `email`, `password`, `role`, `created_at`) VALUES
    ('ali_hassan', 'Ali Hassan', 'ali.hassan@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-01-05 09:00:00'),
    ('siti_aminah', 'Siti Aminah', 'siti.aminah@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-01-12 09:00:00'),
    ('wei_chen', 'Wei Chen', 'wei.chen@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-01-18 09:00:00'),
    ('kavitha_raj', 'Kavitha Raj', 'kavitha.raj@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-01-25 09:00:00'),
    ('daryl_goh', 'Daryl Goh', 'daryl.goh@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'admin', '2025-02-01 09:00:00'),
    ('nur_aisyah', 'Nur Aisyah', 'nur.aisyah@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-02-03 09:00:00'),
    ('muthu_samy', 'Muthu Samy', 'muthu.samy@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-02-09 09:00:00'),
    ('jason_tan', 'Jason Tan', 'jason.tan@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-02-14 09:00:00'),
    ('farah_izzati', 'Farah Izzati', 'farah.izzati@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-02-20 09:00:00'),
    ('kumar_velu', 'Kumar Velu', 'kumar.velu@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-02-27 09:00:00'),
    ('hui_ling', 'Hui Ling', 'hui.ling@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-03-02 09:00:00'),
    ('zulkifli_abu', 'Zulkifli Abu', 'zulkifli.abu@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-03-11 09:00:00'),
    ('priya_devi', 'Priya Devi', 'priya.devi@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-03-16 09:00:00'),
    ('benjamin_lee', 'Benjamin Lee', 'benjamin.lee@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-03-22 09:00:00'),
    ('aina_sofea', 'Aina Sofea', 'aina.sofea@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-03-29 09:00:00'),
    ('ravi_chandran', 'Ravi Chandran', 'ravi.chandran@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-04-04 09:00:00'),
    ('michelle_wong', 'Michelle Wong', 'michelle.wong@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-04-10 09:00:00'),
    ('amir_faiz', 'Amir Faiz', 'amir.faiz@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-04-16 09:00:00'),
    ('grace_tan', 'Grace Tan', 'grace.tan@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'admin', '2025-04-21 09:00:00'),
    ('syafiq_rahman', 'Syafiq Rahman', 'syafiq.rahman@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-04-27 09:00:00'),
    ('deepika_nair', 'Deepika Nair', 'deepika.nair@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-05-03 09:00:00'),
    ('hafiz_omar', 'Hafiz Omar', 'hafiz.omar@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-05-09 09:00:00'),
    ('linda_chong', 'Linda Chong', 'linda.chong@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-05-15 09:00:00'),
    ('sarah_lim', 'Sarah Lim', 'sarah.lim@example.com', '5baa61e4c9b93f3f0682250b6cf8331b7ee68fd8', 'member', '2025-05-20 09:00:00');

UPDATE `user`
SET `email_verified` = 1,
    `active` = 1
WHERE `email` LIKE '%@example.com';

-- Admin + superadmin accounts for the `user` table (fitnessdb)
INSERT INTO `user` (`username`, `name`, `email`, `password`, `role`, `active`, `email_verified`, `created_at`) VALUES
    ('superadmin', 'Super Admin', 'superadmin@forgefit.test', 'a29c57c6894dee6e8251510d58c07078ee3f49bf', 'superadmin', 1, 1, '2025-01-01 09:00:00'),
    ('ops_admin', 'Operations Admin', 'ops.admin@forgefit.test', 'a29c57c6894dee6e8251510d58c07078ee3f49bf', 'admin', 1, 1, '2025-01-02 09:00:00'),
    ('support_admin', 'Support Admin', 'support.admin@forgefit.test', 'a29c57c6894dee6e8251510d58c07078ee3f49bf', 'admin', 1, 1, '2025-01-03 09:00:00');

-- Sample data for the `category` and `product` tables (fitnessdb)
INSERT INTO `category` (`category_id`, `name`) VALUES
    (1, 'Equipment'),
    (2, 'Protein Powder'),
    (3, 'Supplements'),
    (4, 'Apparel'),
    (5, 'Snacks & Bars');

INSERT INTO `product` (`product_id`, `category_id`, `product_name`, `price`, `weight`, `description`, `stock`, `availability`, `created_at`, `updated_at`) VALUES
    (1, 1, 'Hex Dumbbell 2.5kg', 29.90, 2.50, 'Rubber-coated hex dumbbell suitable for beginners and light resistance training.', 30, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (2, 1, 'Hex Dumbbell 5kg', 49.90, 5.00, 'Durable rubber-coated dumbbell with an ergonomic grip.', 25, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (3, 1, 'Hex Dumbbell 7.5kg', 69.90, 7.50, 'Medium-weight dumbbell suitable for strength and full-body workouts.', 20, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (4, 1, 'Hex Dumbbell 10kg', 89.90, 10.00, 'Heavy-duty hex dumbbell designed for strength training.', 18, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (5, 1, 'Adjustable Dumbbell Set 20kg', 189.90, 20.00, 'Adjustable dumbbell set with removable weight plates and secure locking collars.', 12, 1, '2026-07-23 13:52:05', '2026-08-06 06:24:36'),
    (6, 1, 'Neoprene Dumbbell Pair 3kg', 59.90, 6.00, 'Pair of soft-grip neoprene dumbbells suitable for home workouts and aerobics.', 22, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (7, 2, 'Whey Protein Chocolate 1kg', 129.90, 1.00, 'Chocolate-flavoured whey protein powder with 24g of protein per serving.', 35, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (8, 2, 'Whey Protein Vanilla 1kg', 129.90, 1.00, 'Vanilla-flavoured whey protein powder suitable for shakes and smoothies.', 28, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (9, 2, 'Whey Protein Strawberry 1kg', 129.90, 1.00, 'Strawberry-flavoured whey protein powder formulated for muscle recovery.', 24, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (10, 2, 'Plant Protein Blend 750g', 109.50, 0.75, 'Dairy-free protein powder made from pea and brown rice protein.', 20, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (11, 2, 'Whey Protein Isolate 900g', 159.90, 0.90, 'High-purity whey protein isolate with low sugar and fat content.', 16, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (12, 2, 'Mass Gainer Chocolate 2kg', 139.90, 2.00, 'High-calorie chocolate protein powder designed to support weight and muscle gain.', 15, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (13, 3, 'Creatine Monohydrate 300g', 69.90, 0.30, 'Micronised creatine monohydrate designed to support strength and performance.', 42, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (14, 3, 'Pre-Workout Citrus 250g', 79.90, 0.25, 'Citrus-flavoured pre-workout supplement formulated for energy and focus.', 18, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (15, 3, 'BCAA Powder Berry 300g', 74.90, 0.30, 'Berry-flavoured branched-chain amino acid powder for workout recovery.', 26, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (16, 3, 'Multivitamin Tablets 60 Pieces', 39.90, 0.10, 'Daily multivitamin tablets containing essential vitamins and minerals.', 45, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (17, 3, 'Fish Oil Omega-3 90 Capsules', 49.90, 0.15, 'Omega-3 fish oil capsules formulated to support general health.', 38, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (18, 3, 'Electrolyte Hydration Mix 20 Sachets', 39.90, 0.20, 'Single-serving electrolyte sachets designed for hydration during exercise.', 50, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (19, 4, 'Unisex Training T-Shirt', 45.00, 0.20, 'Lightweight moisture-wicking training shirt for gym and outdoor exercise.', 40, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (20, 4, 'Men Training Shorts', 55.00, 0.25, 'Breathable training shorts with an elastic waistband and side pockets.', 32, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (21, 4, 'Women High-Waist Leggings', 69.00, 0.30, 'Stretchable high-waist leggings designed for strength and cardio workouts.', 29, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (22, 4, 'Men Compression Shirt', 65.00, 0.22, 'Fitted compression shirt designed to provide support during intense workouts.', 24, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (23, 4, 'Women Sports Bra', 59.00, 0.18, 'Supportive sports bra made with breathable and stretchable fabric.', 27, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (24, 4, 'Unisex Training Jacket', 89.00, 0.45, 'Lightweight training jacket suitable for warm-ups and outdoor activities.', 19, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (25, 5, 'Chocolate Protein Bar 60g', 8.90, 0.06, 'Chocolate protein bar containing 20g of protein.', 80, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (26, 5, 'Peanut Butter Protein Bar 60g', 8.90, 0.06, 'Peanut butter-flavoured protein bar suitable for a convenient snack.', 75, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (27, 5, 'Roasted Mixed Nuts 200g', 15.90, 0.20, 'Lightly roasted mixture of almonds, cashews and peanuts.', 45, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (28, 5, 'High-Protein Granola 300g', 21.90, 0.30, 'Crunchy granola containing oats, nuts, seeds and added protein.', 36, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (29, 5, 'Natural Peanut Butter 350g', 18.90, 0.35, 'Natural peanut butter made from roasted peanuts without added sugar.', 41, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05'),
    (30, 5, 'Baked Protein Cookies 6 Pack', 24.90, 0.36, 'Pack of six baked cookies containing added protein and reduced sugar.', 33, 1, '2026-07-23 13:52:05', '2026-07-23 13:52:05');

ALTER TABLE `product` AUTO_INCREMENT = 31;

-- Seed data for tables not covered by seed_users.sql, seed_admins.sql, and seed_products.sql.
INSERT INTO `address`
    (`user_id`, `street`, `city`, `state`, `postal_code`, `country`, `label`, `latitude`, `longitude`, `is_default`)
SELECT u.user_id, '12 Jalan Damai', 'Kuala Lumpur', 'Wilayah Persekutuan', '50450', 'Malaysia', 'Home', 3.1552, 101.7130, 1
FROM `user` u WHERE u.email = 'ali.hassan@example.com'
UNION ALL
SELECT u.user_id, '8 Jalan Melur', 'Petaling Jaya', 'Selangor', '46200', 'Malaysia', 'Home', 3.1073, 101.6067, 1
FROM `user` u WHERE u.email = 'siti.aminah@example.com'
UNION ALL
SELECT u.user_id, '25 Jalan Mutiara', 'Johor Bahru', 'Johor', '80300', 'Malaysia', 'Home', 1.4927, 103.7414, 1
FROM `user` u WHERE u.email = 'wei.chen@example.com'
UNION ALL
SELECT u.user_id, '17 Jalan Sentosa', 'George Town', 'Penang', '10350', 'Malaysia', 'Home', 5.4141, 100.3288, 1
FROM `user` u WHERE u.email = 'kavitha.raj@example.com'
UNION ALL
SELECT u.user_id, '31 Jalan Kenanga', 'Shah Alam', 'Selangor', '40100', 'Malaysia', 'Office', 3.0738, 101.5183, 0
FROM `user` u WHERE u.email = 'daryl.goh@example.com';

INSERT INTO `product_image` (`product_id`, `product_imageid`) VALUES
    (1, 'hex-dumbbell.jpg'),
    (2, 'hex-dumbbell.jpg'),
    (3, 'hex-dumbbell.jpg'),
    (4, 'hex-dumbbell.jpg'),
    (5, 'adjustable-dumbbell.jpg'),
    (6, 'neoprene-dumbbell.jpg'),
    (7, 'whey-protein-chocolate.jpg'),
    (8, 'whey-protein-vanilla.jpg'),
    (9, 'whey-protein-strawberry.jpg'),
    (10, 'plant-protein-blend.jpg'),
    (11, 'whey-protein-isolate.jpg'),
    (12, 'mass-gainer-chocolate.jpg'),
    (13, 'creatine-monohydrate.jpg'),
    (14, 'preworkout-citrus.jpg'),
    (15, 'bcaa-powder-berry.jpg'),
    (16, 'multivitamin-tablets.jpg'),
    (17, 'fish-oil-omega3.jpg'),
    (18, 'electrolyte-hydration-mix.jpg'),
    (19, 'unisex-training-tshirt.jpg'),
    (20, 'men-training-shorts.jpg'),
    (21, 'women-high-waist-legging.jpg'),
    (22, 'men-compression-shirt.jpg'),
    (23, 'women-sports-bra.jpg'),
    (24, 'unisex-training-jacket.jpg'),
    (25, 'chocolate-protein-bar.jpg'),
    (26, 'peanut-butter-protein-bar.jpg'),
    (27, 'roasted-mixed-nuts.jpg'),
    (28, 'high-protein-granola.jpg'),
    (29, 'natural-peanut butter.jpg'),
    (30, 'baked-protein-cookies.jpg');

INSERT INTO `cart_item` (`user_id`, `product_id`, `quantity`)
SELECT u.user_id, 8, 1 FROM `user` u WHERE u.email = 'ali.hassan@example.com'
UNION ALL
SELECT u.user_id, 13, 2 FROM `user` u WHERE u.email = 'ali.hassan@example.com'
UNION ALL
SELECT u.user_id, 19, 1 FROM `user` u WHERE u.email = 'siti.aminah@example.com'
UNION ALL
SELECT u.user_id, 25, 4 FROM `user` u WHERE u.email = 'wei.chen@example.com';

INSERT INTO `wishlist_item` (`user_id`, `product_id`)
SELECT u.user_id, 5 FROM `user` u WHERE u.email = 'ali.hassan@example.com'
UNION ALL
SELECT u.user_id, 14 FROM `user` u WHERE u.email = 'siti.aminah@example.com'
UNION ALL
SELECT u.user_id, 21 FROM `user` u WHERE u.email = 'wei.chen@example.com'
UNION ALL
SELECT u.user_id, 28 FROM `user` u WHERE u.email = 'kavitha.raj@example.com';

INSERT INTO `voucher_configuration`
    (`voucher_id`, `name`, `discount_type`, `discount_value`, `discount_percentage`, `minimum_spend`, `start_date`, `end_date`, `status`)
VALUES
    (1, 'New Member 10 Percent', 'percentage', NULL, 10.00, 80.00, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 'active'),
    (2, 'Equipment RM20 Off', 'fixed', 20.00, NULL, 150.00, '2026-01-01 00:00:00', '2026-06-30 23:59:59', 'expired'),
    (3, 'Weekend Fitness Deal', 'percentage', NULL, 15.00, 120.00, '2026-08-01 00:00:00', '2026-09-30 23:59:59', 'active');

INSERT INTO `voucher` (`id`, `voucher_id`, `code`, `status`, `used_at`) VALUES
    (1, 1, 'WELCOME10', 'active', NULL),
    (2, 2, 'EQUIP20', 'used', '2026-05-18 14:22:00'),
    (3, 3, 'WEEKEND15', 'disabled', NULL),
    (4, 3, 'FITNESS15', 'active', NULL);

INSERT INTO `orders`
    (`order_id`, `user_id`, `voucher_id`, `subtotal`, `shipping_fee`, `discount_amount`,
     `shipping_street`, `shipping_city`, `shipping_state`, `shipping_postal_code`, `shipping_country`,
     `status`, `cancellation_reason`, `cancellation_requested_at`, `cancellation_rejection_reason`, `cancellation_rejected_at`, `created_at`)
SELECT 1, u.user_id, NULL, 129.90, 8.00, 0.00, '12 Jalan Damai', 'Kuala Lumpur', 'Wilayah Persekutuan', '50450', 'Malaysia', 'paid', NULL, NULL, NULL, NULL, '2026-08-01 10:15:00'
FROM `user` u WHERE u.email = 'ali.hassan@example.com'
UNION ALL
SELECT 2, u.user_id, NULL, 189.90, 0.00, 0.00, '8 Jalan Melur', 'Petaling Jaya', 'Selangor', '46200', 'Malaysia', 'shipped', NULL, NULL, NULL, NULL, '2026-07-22 16:40:00'
FROM `user` u WHERE u.email = 'siti.aminah@example.com'
UNION ALL
SELECT 3, u.user_id, NULL, 59.90, 8.00, 0.00, '25 Jalan Mutiara', 'Johor Bahru', 'Johor', '80300', 'Malaysia', 'completed', NULL, NULL, NULL, NULL, '2026-06-12 09:30:00'
FROM `user` u WHERE u.email = 'wei.chen@example.com'
UNION ALL
SELECT 4, u.user_id, 2, 159.80, 8.00, 20.00, '17 Jalan Sentosa', 'George Town', 'Penang', '10350', 'Malaysia', 'cancelled', 'Changed my mind', '2026-05-18 14:00:00', NULL, NULL, '2026-05-18 13:50:00'
FROM `user` u WHERE u.email = 'kavitha.raj@example.com'
UNION ALL
SELECT 5, u.user_id, NULL, 45.00, 8.00, 0.00, '31 Jalan Kenanga', 'Shah Alam', 'Selangor', '40100', 'Malaysia', 'pending', NULL, NULL, NULL, NULL, '2026-08-20 11:05:00'
FROM `user` u WHERE u.email = 'daryl.goh@example.com'
UNION ALL
SELECT 6, u.user_id, 1, 259.80, 0.00, 25.98, '12 Jalan Damai', 'Kuala Lumpur', 'Wilayah Persekutuan', '50450', 'Malaysia', 'paid', NULL, NULL, NULL, NULL, '2026-08-24 18:20:00'
FROM `user` u WHERE u.email = 'ali.hassan@example.com';

INSERT INTO `order_product` (`order_id`, `product_id`, `quantity`, `unit_price`, `final_price`) VALUES
    (1, 7, 1, 129.90, 129.90),
    (2, 5, 1, 189.90, 189.90),
    (3, 6, 1, 59.90, 59.90),
    (4, 13, 1, 69.90, 69.90),
    (4, 14, 1, 79.90, 79.90),
    (5, 19, 1, 45.00, 45.00),
    (6, 7, 2, 129.90, 259.80);

INSERT INTO `payment` (`order_id`, `amount`, `payment_method`, `status`, `transaction_reference`, `created_at`) VALUES
    (1, 137.90, 'card', 'success', 'DEMO-TXN-0001', '2026-08-01 10:16:00'),
    (2, 189.90, 'online_banking', 'success', 'DEMO-TXN-0002', '2026-07-22 16:42:00'),
    (3, 67.90, 'card', 'success', 'DEMO-TXN-0003', '2026-06-12 09:32:00'),
    (4, 147.80, 'card', 'refunded', 'DEMO-TXN-0004', '2026-05-18 13:52:00'),
    (5, 53.00, 'card', 'pending', NULL, '2026-08-20 11:06:00'),
    (6, 233.82, 'card', 'success', 'DEMO-TXN-0006', '2026-08-24 18:21:00');

INSERT INTO `stock_reminder` (`user_id`, `product_id`, `shown`)
SELECT u.user_id, 12, 0 FROM `user` u WHERE u.email = 'ali.hassan@example.com'
UNION ALL
SELECT u.user_id, 18, 1 FROM `user` u WHERE u.email = 'siti.aminah@example.com'
UNION ALL
SELECT u.user_id, 24, 0 FROM `user` u WHERE u.email = 'wei.chen@example.com';

ALTER TABLE `voucher_configuration` AUTO_INCREMENT = 4;
ALTER TABLE `voucher` AUTO_INCREMENT = 5;
ALTER TABLE `orders` AUTO_INCREMENT = 7;
ALTER TABLE `payment` AUTO_INCREMENT = 7;
