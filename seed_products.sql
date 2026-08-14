-- Sample data for the `category` and `product` tables (fitnessdb)
-- Import with: mysql -u root fitnessdb < seed_products.sql

USE `fitnessdb`;

INSERT INTO `category` (`category_id`, `name`) VALUES
    (1, 'Dumbbell'),
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
