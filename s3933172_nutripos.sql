-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Oct 28, 2025 at 02:29 AM
-- Server version: 10.5.27-MariaDB-log
-- PHP Version: 8.3.17

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `s3933172_nutripos`
--

-- --------------------------------------------------------

--
-- Table structure for table `catalog_map`
--

CREATE TABLE `catalog_map` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `catalog_object_id` varchar(64) DEFAULT NULL,
  `sku` varchar(64) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `afcd_code` varchar(32) NOT NULL,
  `grams_per_unit` decimal(12,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `catalog_map`
--

INSERT INTO `catalog_map` (`id`, `catalog_object_id`, `sku`, `name`, `afcd_code`, `grams_per_unit`) VALUES
(1, 'VQI4XOJMS3NFQ737NHKURZ2Y', NULL, 'Beef Burger', 'F00BEEF', 250.00),
(2, 'L2V647ARMO5ED44R5Q3HLZHF', NULL, 'Chicken Burger', 'F00CHIK', 230.00),
(3, '5X6E53DJSDOCWXNY7W45AJGI', NULL, 'Veggie Burger', 'F00VEGG', 220.00);

-- --------------------------------------------------------

--
-- Table structure for table `line_items`
--

CREATE TABLE `line_items` (
  `line_item_catalog_object_id` varchar(64) NOT NULL,
  `name` varchar(255) NOT NULL,
  `variation_name` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `line_items`
--

INSERT INTO `line_items` (`line_item_catalog_object_id`, `name`, `variation_name`) VALUES
('2N4IPJSI5FNU3VS2TPZJOJBT', 'Hot Cocoa', 'Regular'),
('5LG4FOXUSVX67LGMJLC4MML4', 'Grilled Chicken Salad', 'Regular'),
('5X6E53DJSDOCWXNY7W45AJGI', 'Veggie Burger', 'Regular'),
('L2V647ARMO5ED44R5Q3HLZHF', 'Chicken Burger', 'Regular'),
('PSUQ6HHURYLTAS3EI3AHQNXN', 'Iced Coffee', 'Regular'),
('SJMI3YIS362BYVNBKD6S5SQX', 'Milkshake', 'Regular'),
('VQI4XOJMS3NFQ737NHKURZ2Y', 'Beef Burger', 'Regular'),
('WBWMIY5WTWS7WOINDOUXBUMP', 'Chicken Wrap', 'Regular');

-- --------------------------------------------------------

--
-- Table structure for table `modifiers`
--

CREATE TABLE `modifiers` (
  `id` bigint(20) NOT NULL,
  `modifier_catalog_object_id` varchar(64) DEFAULT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `modifiers`
--

INSERT INTO `modifiers` (`id`, `modifier_catalog_object_id`, `name`) VALUES
(1, 'IKIN7PVFDYODAVM3PREAWODW', 'Extra Cheese'),
(2, 'ZVVHJZDIHT5Z24ZKRAYMEH4A', 'Gluten Free Bun'),
(3, 'Z3H4DI3YJFPB6WFF3HO6ZUIV', 'Extra Patty'),
(4, 'HAX2ABPN37FVS7IPDKCYD7XK', 'Tomato Sauce'),
(5, 'BIAC6ODTTLSL7A52MKU3CCA6', 'BBQ Sauce'),
(6, '2U5RLA43EDWCMTXMISMJYPM5', 'Large Size');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` varchar(64) NOT NULL,
  `closed_at` datetime DEFAULT NULL,
  `total` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `closed_at`, `total`, `created_at`) VALUES
('0LUCnyP4T3ZRCkW4xnC5mZpbeG8YY', '2025-10-05 17:47:16', '$69.50', '2025-10-05 06:47:20'),
('4Vsq8XKcAhZWndyG5VF4EszHqiEZY', '2025-09-13 13:26:20', '$0.00', '2025-09-18 02:24:05'),
('8lrszfjh900Xb6S0PeoFLIiSdVWZY', '2025-10-05 17:44:41', '$0.00', '2025-10-05 06:44:53'),
('GooL1iuBt6V0HG52MLKvx6SufRHZY', '2025-09-30 12:35:30', '$0.00', '2025-09-30 02:35:37'),
('IFwwDRO4wIqwjOcOJ5eZTGNB0YZZY', '2025-09-22 00:40:08', '$0.00', '2025-09-21 14:40:13'),
('M38WbMDTlZBYSxtSnOmuxvjsQjWZY', '2025-09-22 00:16:41', '$0.00', '2025-09-21 14:16:47'),
('oPzVQDak96MqkE7i4lBdJ6EefmDZY', '2025-09-13 10:22:22', '$0.00', '2025-09-18 02:24:05'),
('QxwEAWBMgbqMkOWlt8o4wh9SJmWZY', '2025-09-13 13:27:39', '$0.00', '2025-09-18 02:24:05'),
('SMBG8mntOz9YtHvaozumoezHPJTZY', '2025-09-22 00:23:15', '$0.00', '2025-09-21 14:23:20'),
('UlvfokvZcvmMdFOogpFJluGOIMYZY', '2025-09-15 12:28:17', '$0.00', '2025-09-18 02:24:05'),
('UtTP2JWt4MC7x6PLYm6oFof5EQZZY', '2025-09-13 10:21:47', '$0.00', '2025-09-18 02:24:05'),
('uuDCi1F4PuZ8OMWUFuAHUW3Q8JWZY', '2025-09-13 10:23:03', '$0.00', '2025-09-18 02:24:05'),
('yK6TQNlh4w3dxMh1FAZn7RySHqLZY', '2025-09-22 00:28:52', '$0.00', '2025-09-21 14:28:55'),
('ySu3MWkV5PiNAvqFYoKRIqotuRJZY', '2025-09-13 10:20:06', '$0.00', '2025-09-18 02:24:05');

-- --------------------------------------------------------

--
-- Table structure for table `ordersold`
--

CREATE TABLE `ordersold` (
  `id` varchar(255) NOT NULL,
  `Timestamp` datetime DEFAULT NULL,
  `Value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `ordersold`
--

INSERT INTO `ordersold` (`id`, `Timestamp`, `Value`) VALUES
('0p8D5CVvogNcMpxv0YIPCENbhEGZY', '2025-08-28 21:18:13', '$0.05'),
('0ZYJlRy83zIgdxELDOsZKSQXcOPZY', '2025-08-28 23:35:31', '0'),
('AhM4ApxPSDmqLfdPvcpzHuQcoVSZY', '2025-09-12 17:44:09', '$0.00'),
('APElw6VX0XtOp6fXHBCbrHuey4FZY', '2025-08-28 23:37:51', '0'),
('awu9o2VPAqrrAHh9Rt3s9pPdUteZY', '2025-08-29 22:26:00', '$0.05'),
('aWzYhw0tAw0mC2EhltmaNS61ZnCZY', '2025-08-31 11:49:09', '$0.25'),
('e470lEZWWoBpNQgaJhb7jX9scGZZY', '2025-08-29 11:49:27', '$0.20'),
('eqVslaV9sQCjDFZZAxMlB7BD6hFZY', '2025-09-12 17:52:40', '$0.00'),
('GmW9iIvpKri31vmfDYmFetruteRZY', '2025-08-28 23:39:39', '0'),
('gPYV1Ytq0iZ7gnYCVqeJiUPpIhLZY', '2025-08-28 23:35:58', '0'),
('graHLlRKcimR0vyEmYUZNfEvQXZZY', '2025-08-28 23:34:25', '0'),
('i02BAcmTQFmKYg98qL4NR5j41q9YY', '2025-08-28 06:49:26', '0'),
('k5ncQ5UdvKun2TmE4M5EPePGklBZY', '2025-09-12 17:59:52', '$0.00'),
('kvSMeo1RPSSjJO1Btfxcx1UKqw9YY', '2025-09-11 21:02:25', '$17.50'),
('m0zChexKzSOf0NHO78n27s72yiZZY', '2025-09-01 19:13:21', '$0.10'),
('o1u9Iai54Wg98ZC5MpzlmD9Cw8QZY', '2025-09-11 13:01:51', '$0.05'),
('ob6WuY1awDLe9kvWEVz4IGJZf5FZY', '2025-08-29 16:43:00', '$0.30'),
('Ouqk9wgVrdLDxQbfmF6zzCbpdv8YY', '2025-09-09 10:24:23', '$0.15'),
('oZt239UIEFeUcV42aUVktKIhzVMZY', '2025-08-29 00:22:46', '$0.15'),
('Q3ewH8J58gr3qOz9p6boV2XjHpbZY', '2025-08-31 11:46:55', '$0.10'),
('Q3iYgzLDfXWgjINnqodurzmgbMfZY', '2025-08-29 22:24:45', '$0.65'),
('Q9XloWZaobxVqEwb7FUrOp0CkRRZY', '2025-09-08 11:21:41', '$0.05'),
('qq9A8eRd1yyi3y3qFGnkNRxDEITZY', '2025-08-31 11:59:11', '$0.05'),
('SC5fVGsUJN6pr705mm6xnlOYpfKZY', '2025-08-31 22:13:47', '$0.05'),
('slumqETPpVo3oSQBsNaYQGimyLUZY', '2025-08-27 14:59:16', '0'),
('uGMj3JUGxl4AiOjQ3RDDVZi9A75YY', '2025-09-01 08:01:18', '$0.15'),
('uqMmFtgy2hbO6XoUIeYg5Yfo8HBZY', '2025-08-29 01:28:02', '$0.05'),
('W49KQuCsg2PnJZQ4nMKZrFQl14MZY', '2025-08-31 22:05:06', '$0.55'),
('yidF9exyDd3wTYB7itfz1v54FmQZY', '2025-08-27 14:58:58', '0'),
('YTvEalFnHP2hH81zm6hFQ3lC4t5YY', '2025-09-12 18:10:24', '$0.00');

-- --------------------------------------------------------

--
-- Table structure for table `order_line_items`
--

CREATE TABLE `order_line_items` (
  `id` bigint(20) NOT NULL,
  `order_id` varchar(64) NOT NULL,
  `line_item_catalog_object_id` varchar(64) NOT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `order_line_items`
--

INSERT INTO `order_line_items` (`id`, `order_id`, `line_item_catalog_object_id`, `quantity`) VALUES
(1, 'UtTP2JWt4MC7x6PLYm6oFof5EQZZY', 'VQI4XOJMS3NFQ737NHKURZ2Y', 1),
(2, 'oPzVQDak96MqkE7i4lBdJ6EefmDZY', 'VQI4XOJMS3NFQ737NHKURZ2Y', 1),
(3, 'uuDCi1F4PuZ8OMWUFuAHUW3Q8JWZY', 'VQI4XOJMS3NFQ737NHKURZ2Y', 1),
(4, 'uuDCi1F4PuZ8OMWUFuAHUW3Q8JWZY', 'L2V647ARMO5ED44R5Q3HLZHF', 1),
(5, 'uuDCi1F4PuZ8OMWUFuAHUW3Q8JWZY', '5X6E53DJSDOCWXNY7W45AJGI', 1),
(6, 'ySu3MWkV5PiNAvqFYoKRIqotuRJZY', 'VQI4XOJMS3NFQ737NHKURZ2Y', 1),
(7, '4Vsq8XKcAhZWndyG5VF4EszHqiEZY', 'L2V647ARMO5ED44R5Q3HLZHF', 3),
(8, 'QxwEAWBMgbqMkOWlt8o4wh9SJmWZY', '5X6E53DJSDOCWXNY7W45AJGI', 2),
(9, 'QxwEAWBMgbqMkOWlt8o4wh9SJmWZY', 'L2V647ARMO5ED44R5Q3HLZHF', 1),
(10, 'QxwEAWBMgbqMkOWlt8o4wh9SJmWZY', 'L2V647ARMO5ED44R5Q3HLZHF', 2),
(11, 'UlvfokvZcvmMdFOogpFJluGOIMYZY', '2N4IPJSI5FNU3VS2TPZJOJBT', 1),
(12, 'M38WbMDTlZBYSxtSnOmuxvjsQjWZY', 'VQI4XOJMS3NFQ737NHKURZ2Y', 1),
(13, 'SMBG8mntOz9YtHvaozumoezHPJTZY', 'VQI4XOJMS3NFQ737NHKURZ2Y', 1),
(14, 'yK6TQNlh4w3dxMh1FAZn7RySHqLZY', 'VQI4XOJMS3NFQ737NHKURZ2Y', 1),
(15, 'IFwwDRO4wIqwjOcOJ5eZTGNB0YZZY', 'VQI4XOJMS3NFQ737NHKURZ2Y', 1),
(16, 'GooL1iuBt6V0HG52MLKvx6SufRHZY', 'VQI4XOJMS3NFQ737NHKURZ2Y', 1),
(17, 'GooL1iuBt6V0HG52MLKvx6SufRHZY', 'L2V647ARMO5ED44R5Q3HLZHF', 1),
(18, '8lrszfjh900Xb6S0PeoFLIiSdVWZY', '5X6E53DJSDOCWXNY7W45AJGI', 1),
(19, '8lrszfjh900Xb6S0PeoFLIiSdVWZY', 'L2V647ARMO5ED44R5Q3HLZHF', 1),
(20, '8lrszfjh900Xb6S0PeoFLIiSdVWZY', 'VQI4XOJMS3NFQ737NHKURZ2Y', 1),
(21, '0LUCnyP4T3ZRCkW4xnC5mZpbeG8YY', '5X6E53DJSDOCWXNY7W45AJGI', 1),
(22, '0LUCnyP4T3ZRCkW4xnC5mZpbeG8YY', 'SJMI3YIS362BYVNBKD6S5SQX', 1),
(23, '0LUCnyP4T3ZRCkW4xnC5mZpbeG8YY', 'PSUQ6HHURYLTAS3EI3AHQNXN', 1),
(24, '0LUCnyP4T3ZRCkW4xnC5mZpbeG8YY', '2N4IPJSI5FNU3VS2TPZJOJBT', 1),
(25, '0LUCnyP4T3ZRCkW4xnC5mZpbeG8YY', '5LG4FOXUSVX67LGMJLC4MML4', 1),
(26, '0LUCnyP4T3ZRCkW4xnC5mZpbeG8YY', 'WBWMIY5WTWS7WOINDOUXBUMP', 1),
(27, '0LUCnyP4T3ZRCkW4xnC5mZpbeG8YY', 'L2V647ARMO5ED44R5Q3HLZHF', 1),
(28, '0LUCnyP4T3ZRCkW4xnC5mZpbeG8YY', 'VQI4XOJMS3NFQ737NHKURZ2Y', 1);

-- --------------------------------------------------------

--
-- Table structure for table `order_line_item_modifiers`
--

CREATE TABLE `order_line_item_modifiers` (
  `order_line_item_id` bigint(20) NOT NULL,
  `modifier_id` bigint(20) NOT NULL,
  `quantity` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `order_line_item_modifiers`
--

INSERT INTO `order_line_item_modifiers` (`order_line_item_id`, `modifier_id`, `quantity`) VALUES
(2, 1, 1),
(2, 2, 1),
(2, 3, 1),
(2, 4, 1),
(3, 1, 1),
(4, 2, 1),
(5, 2, 1),
(7, 1, 1),
(7, 4, 1),
(9, 5, 1),
(10, 4, 1),
(11, 6, 1),
(17, 1, 1);

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(255) NOT NULL,
  `price` decimal(12,2) DEFAULT NULL,
  `square_catalog_object_id` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `price`, `square_catalog_object_id`) VALUES
(123, 'Complete Beef Burger', NULL, NULL),
(124, 'Chicken Burger', NULL, NULL),
(125, 'Veggie Burger', NULL, NULL),
(126, 'Starbuck hot cocoa', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `product_ingredients`
--

CREATE TABLE `product_ingredients` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `afcd_code` varchar(32) NOT NULL,
  `grams_per_unit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `product_ingredients`
--

INSERT INTO `product_ingredients` (`id`, `product_id`, `afcd_code`, `grams_per_unit`, `notes`) VALUES
(1, 123, 'F000472', 120.00, ''),
(2, 123, 'F001353', 80.00, ''),
(3, 123, 'F009193', 30.00, ''),
(4, 123, 'F001013', 25.00, ''),
(5, 123, 'F005192', 15.00, ''),
(6, 123, 'F008083', 15.00, 'Extra $1'),
(7, 123, 'F007987', 15.00, 'Extra $1'),
(8, 123, 'F005441', 20.00, 'Extra $1'),
(9, 123, 'F002414', 30.00, ''),
(10, 124, 'F002595', 120.00, ''),
(11, 124, 'F004362', 80.00, ''),
(12, 124, 'F009190', 30.00, ''),
(13, 124, 'F007987', 15.00, ''),
(14, 124, 'F005441', 20.00, ''),
(15, 124, 'F002414', 30.00, ''),
(16, 125, 'F00VEGG', 220.00, 'placeholder'),
(17, 126, 'F002980', 250.00, ''),
(18, 126, 'F009516', 500.00, ''),
(19, 126, 'F008969', 20.00, '');

-- --------------------------------------------------------

--
-- Table structure for table `product_nutrition_totals`
--

CREATE TABLE `product_nutrition_totals` (
  `product_id` bigint(20) UNSIGNED NOT NULL,
  `energy_kj` decimal(12,2) NOT NULL DEFAULT 0.00,
  `calories_kcal` decimal(12,2) NOT NULL DEFAULT 0.00,
  `protein_g` decimal(12,3) NOT NULL DEFAULT 0.000,
  `fat_g` decimal(12,3) NOT NULL DEFAULT 0.000,
  `carb_g` decimal(12,3) NOT NULL DEFAULT 0.000,
  `sugars_g` decimal(12,3) NOT NULL DEFAULT 0.000,
  `sodium_mg` decimal(12,2) NOT NULL DEFAULT 0.00,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `square_catalog_map`
--

CREATE TABLE `square_catalog_map` (
  `id` int(11) NOT NULL,
  `catalog_object_id` varchar(64) NOT NULL,
  `product_id` int(11) NOT NULL,
  `serve_multiplier` decimal(10,4) NOT NULL DEFAULT 1.0000
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

--
-- Dumping data for table `square_catalog_map`
--

INSERT INTO `square_catalog_map` (`id`, `catalog_object_id`, `product_id`, `serve_multiplier`) VALUES
(1, 'VQI4XOJMS3NFQ737NHKURZ2Y', 123, 1.0000),
(7, 'L2V647ARMO5ED44R5Q3HLZHF', 124, 1.0000),
(8, '5X6E53DJSDOCWXNY7W45AJGI', 125, 1.0000),
(9, '2N4IPJSI5FNU3VS2TPZJOJBT', 126, 1.0000);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(32) NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `email`, `password_hash`, `role`, `created_at`) VALUES
(3, 'admin@nutripos.local', '$2y$10$rhtvl9V6aNpgsUUS0NfG/.z4uPIFIqNYbbM88BEqwFLsUbPZtgN4W', 'admin', '2025-08-14 05:27:08');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `catalog_map`
--
ALTER TABLE `catalog_map`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `catalog_object_id` (`catalog_object_id`);

--
-- Indexes for table `line_items`
--
ALTER TABLE `line_items`
  ADD PRIMARY KEY (`line_item_catalog_object_id`);

--
-- Indexes for table `modifiers`
--
ALTER TABLE `modifiers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `catalog_object_id` (`modifier_catalog_object_id`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `ordersold`
--
ALTER TABLE `ordersold`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `order_line_items`
--
ALTER TABLE `order_line_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `catalog_object_id` (`line_item_catalog_object_id`);

--
-- Indexes for table `order_line_item_modifiers`
--
ALTER TABLE `order_line_item_modifiers`
  ADD PRIMARY KEY (`order_line_item_id`,`modifier_id`),
  ADD KEY `modifier_id` (`modifier_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `square_catalog_object_id` (`square_catalog_object_id`);

--
-- Indexes for table `product_ingredients`
--
ALTER TABLE `product_ingredients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_pi_product` (`product_id`);

--
-- Indexes for table `product_nutrition_totals`
--
ALTER TABLE `product_nutrition_totals`
  ADD PRIMARY KEY (`product_id`);

--
-- Indexes for table `square_catalog_map`
--
ALTER TABLE `square_catalog_map`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `catalog_object_id` (`catalog_object_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `catalog_map`
--
ALTER TABLE `catalog_map`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `modifiers`
--
ALTER TABLE `modifiers`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `order_line_items`
--
ALTER TABLE `order_line_items`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=127;

--
-- AUTO_INCREMENT for table `product_ingredients`
--
ALTER TABLE `product_ingredients`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `square_catalog_map`
--
ALTER TABLE `square_catalog_map`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `order_line_items`
--
ALTER TABLE `order_line_items`
  ADD CONSTRAINT `order_line_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_line_items_ibfk_2` FOREIGN KEY (`line_item_catalog_object_id`) REFERENCES `line_items` (`line_item_catalog_object_id`);

--
-- Constraints for table `order_line_item_modifiers`
--
ALTER TABLE `order_line_item_modifiers`
  ADD CONSTRAINT `order_line_item_modifiers_ibfk_1` FOREIGN KEY (`order_line_item_id`) REFERENCES `order_line_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_line_item_modifiers_ibfk_2` FOREIGN KEY (`modifier_id`) REFERENCES `modifiers` (`id`);

--
-- Constraints for table `product_ingredients`
--
ALTER TABLE `product_ingredients`
  ADD CONSTRAINT `fk_pi_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
