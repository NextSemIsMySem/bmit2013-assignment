-- Seed data for tables not covered by seed_users.sql, seed_admins.sql, and seed_products.sql.
-- Import after the existing user and product seed scripts:
--   mysql -u root fitnessdb < seed_remaining.sql
--
-- This script expects the demo users and products from the existing seed files.

USE `fitnessdb`;

-- 2. Address
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

-- 5. ProductImage
INSERT INTO `product_image` (`product_id`, `product_imageid`) VALUES
    (1, '0069c00fa89710d9.jpg'),
    (2, '27a5b213741c72bb.jpg'),
    (3, '532bbaafa3aba6e8.jpg'),
    (4, '600c7574b0af8cfa.jpg'),
    (5, '6a7d7272ce64a.jpg'),
    (6, '6a7ea7dda9c8f.jpg'),
    (7, '6a7ea80102c50.jpg'),
    (8, '6a7ea98f5bd75.jpg'),
    (9, '748aa9a8a816509d.jpg'),
    (10, '7d26a8cbd9b525e8.jpg'),
    (11, 'db15be332452acdc.jpg'),
    (12, 'e57a741ca73de9a9.jpg'),
    (13, 'f0c7bb165fbda531.jpg'),
    (14, 'fe0fab8f074ff624.jpg');

-- 6. CartItem
INSERT INTO `cart_item` (`user_id`, `product_id`, `quantity`)
SELECT u.user_id, 8, 1 FROM `user` u WHERE u.email = 'ali.hassan@example.com'
UNION ALL
SELECT u.user_id, 13, 2 FROM `user` u WHERE u.email = 'ali.hassan@example.com'
UNION ALL
SELECT u.user_id, 19, 1 FROM `user` u WHERE u.email = 'siti.aminah@example.com'
UNION ALL
SELECT u.user_id, 25, 4 FROM `user` u WHERE u.email = 'wei.chen@example.com';

-- 7. WishlistItem
INSERT INTO `wishlist_item` (`user_id`, `product_id`)
SELECT u.user_id, 5 FROM `user` u WHERE u.email = 'ali.hassan@example.com'
UNION ALL
SELECT u.user_id, 14 FROM `user` u WHERE u.email = 'siti.aminah@example.com'
UNION ALL
SELECT u.user_id, 21 FROM `user` u WHERE u.email = 'wei.chen@example.com'
UNION ALL
SELECT u.user_id, 28 FROM `user` u WHERE u.email = 'kavitha.raj@example.com';

-- 8. VoucherConfiguration
INSERT INTO `voucher_configuration`
    (`voucher_id`, `name`, `discount_type`, `discount_value`, `discount_percentage`, `minimum_spend`, `start_date`, `end_date`, `status`)
VALUES
    (1, 'New Member 10 Percent', 'percentage', NULL, 10.00, 80.00, '2026-01-01 00:00:00', '2026-12-31 23:59:59', 'active'),
    (2, 'Equipment RM20 Off', 'fixed', 20.00, NULL, 150.00, '2026-01-01 00:00:00', '2026-06-30 23:59:59', 'expired'),
    (3, 'Weekend Fitness Deal', 'percentage', NULL, 15.00, 120.00, '2026-08-01 00:00:00', '2026-09-30 23:59:59', 'active');

-- 9. Voucher
INSERT INTO `voucher` (`id`, `voucher_id`, `code`, `status`, `used_at`) VALUES
    (1, 1, 'WELCOME10', 'active', NULL),
    (2, 2, 'EQUIP20', 'used', '2026-05-18 14:22:00'),
    (3, 3, 'WEEKEND15', 'disabled', NULL),
    (4, 3, 'FITNESS15', 'active', NULL);

-- 10. Orders
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

-- 11. OrderProduct
INSERT INTO `order_product` (`order_id`, `product_id`, `quantity`, `unit_price`, `final_price`) VALUES
    (1, 7, 1, 129.90, 129.90),
    (2, 5, 1, 189.90, 189.90),
    (3, 6, 1, 59.90, 59.90),
    (4, 13, 1, 69.90, 69.90),
    (4, 14, 1, 79.90, 79.90),
    (5, 19, 1, 45.00, 45.00),
    (6, 7, 2, 129.90, 259.80);

-- 12. Payment
INSERT INTO `payment` (`order_id`, `amount`, `payment_method`, `status`, `transaction_reference`, `created_at`) VALUES
    (1, 137.90, 'card', 'success', 'DEMO-TXN-0001', '2026-08-01 10:16:00'),
    (2, 189.90, 'online_banking', 'success', 'DEMO-TXN-0002', '2026-07-22 16:42:00'),
    (3, 67.90, 'card', 'success', 'DEMO-TXN-0003', '2026-06-12 09:32:00'),
    (4, 147.80, 'card', 'refunded', 'DEMO-TXN-0004', '2026-05-18 13:52:00'),
    (5, 53.00, 'card', 'pending', NULL, '2026-08-20 11:06:00'),
    (6, 233.82, 'card', 'success', 'DEMO-TXN-0006', '2026-08-24 18:21:00');

-- 13. StockReminder
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
