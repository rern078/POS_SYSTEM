-- phpMyAdmin SQL Dump
-- version 5.1.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1:3306
-- Generation Time: Jul 04, 2025 at 04:13 AM
-- Server version: 5.7.36
-- PHP Version: 8.0.13

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `pos_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

DROP TABLE IF EXISTS `orders`;
CREATE TABLE IF NOT EXISTS `orders` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

DROP TABLE IF EXISTS `order_items`;
CREATE TABLE IF NOT EXISTS `order_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `quantity` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `product_id` (`product_id`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

DROP TABLE IF EXISTS `products`;
CREATE TABLE IF NOT EXISTS `products` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_code` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `price` decimal(10,2) NOT NULL,
  `discount_price` decimal(10,2) DEFAULT NULL,
  `stock_quantity` int(11) DEFAULT '0',
  `category` varchar(50) DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `product_code` (`product_code`)
) ENGINE=MyISAM AUTO_INCREMENT=24 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `product_code`, `name`, `description`, `price`, `discount_price`, `stock_quantity`, `category`, `image_path`, `created_at`, `updated_at`) VALUES
(1, 'PROD001', 'Coca Cola', 'Refreshing carbonated soft drink - 330ml can', '2.50', '2.00', 100, 'Beverages', 'images/coke.jpg', '2025-07-04 03:39:03', '2025-07-04 03:47:30'),
(2, 'PROD002', 'Pepsi', 'Classic cola drink - 330ml can', '2.50', NULL, 85, 'Beverages', 'images/pepsi.jpg', '2025-07-04 03:39:03', '2025-07-04 03:47:30'),
(3, 'PROD003', 'Sprite', 'Lemon-lime flavored soft drink - 330ml can', '2.00', '1.75', 60, 'Beverages', 'images/sprite.jpg', '2025-07-04 03:39:03', '2025-07-04 03:47:30'),
(4, 'PROD004', 'Red Bull', 'Energy drink - 250ml can', '3.50', '3.00', 45, 'Beverages', 'images/redbull.jpg', '2025-07-04 03:39:03', '2025-07-04 03:47:30'),
(5, 'PROD005', 'Mineral Water', 'Pure drinking water - 500ml bottle', '1.00', NULL, 200, 'Beverages', 'images/water.jpg', '2025-07-04 03:39:03', '2025-07-04 03:47:30'),
(6, 'PROD006', 'Lays Classic', 'Original potato chips - 150g bag', '3.00', '2.50', 75, 'Snacks', 'images/lays.jpg', '2025-07-04 03:39:03', '2025-07-04 03:47:30'),
(7, 'PROD007', 'Doritos Nacho', 'Cheese flavored tortilla chips - 180g bag', '4.50', '3.99', 50, 'Snacks', 'images/doritos.jpg', '2025-07-04 03:39:03', '2025-07-04 03:47:30'),
(8, 'PROD008', 'Pringles Original', 'Stackable potato chips - 110g can', '5.00', '4.50', 40, 'Snacks', 'images/pringles.jpg', '2025-07-04 03:39:03', '2025-07-04 03:47:30'),
(9, 'PROD009', 'Oreo', 'Chocolate sandwich cookies', '3.50', '3.00', 1, 'Snacks', 'images/snickers.jpg', '2025-07-04 03:39:03', '2025-07-04 03:47:44'),
(10, 'PROD010', 'KitKat', 'Chocolate wafer bar - 4 finger pack', '2.25', '2.00', 65, 'Snacks', 'images/kitkat.jpg', '2025-07-04 03:39:03', '2025-07-04 03:47:30'),
(11, 'PROD011', 'Oreo Cookies', 'Chocolate sandwich cookies - 137g pack', '3.50', '3.00', 55, 'Snacks', 'images/oreo.jpg', '2025-07-04 03:47:30', '2025-07-04 03:47:30'),
(12, 'PROD012', 'Twix', 'Chocolate bar with caramel and cookie - 50g', '2.50', '2.25', 70, 'Snacks', 'images/twix.jpg', '2025-07-04 03:47:30', '2025-07-04 03:47:30'),
(13, 'PROD013', 'M&M\'s Peanut', 'Chocolate candies with peanuts - 100g bag', '3.00', '2.75', 45, 'Snacks', 'images/mms.jpg', '2025-07-04 03:47:30', '2025-07-04 03:47:30'),
(14, 'PROD014', 'White Bread', 'Fresh white bread loaf - 500g', '2.50', NULL, 30, 'Groceries', 'images/bread.jpg', '2025-07-04 03:47:30', '2025-07-04 03:47:30'),
(15, 'PROD015', 'Milk', 'Fresh whole milk - 1 liter', '3.00', '2.75', 25, 'Groceries', 'images/milk.jpg', '2025-07-04 03:47:30', '2025-07-04 03:47:30'),
(16, 'PROD016', 'Eggs', 'Fresh farm eggs - 12 pieces', '4.50', '4.00', 40, 'Groceries', 'images/eggs.jpg', '2025-07-04 03:47:30', '2025-07-04 03:47:30'),
(17, 'PROD017', 'Butter', 'Salted butter - 250g block', '3.75', '3.50', 35, 'Groceries', 'images/butter.jpg', '2025-07-04 03:47:30', '2025-07-04 03:47:30'),
(18, 'PROD018', 'Toothpaste', 'Fresh mint toothpaste - 100ml tube', '4.00', '3.50', 60, 'Personal Care', 'images/toothpaste.jpg', '2025-07-04 03:47:30', '2025-07-04 03:47:30'),
(19, 'PROD019', 'Shampoo', 'Moisturizing shampoo - 400ml bottle', '8.50', '7.99', 30, 'Personal Care', 'images/shampoo.jpg', '2025-07-04 03:47:30', '2025-07-04 03:47:30'),
(20, 'PROD020', 'Soap Bar', 'Antibacterial soap bar - 100g', '2.00', '1.75', 80, 'Personal Care', 'images/soap.jpg', '2025-07-04 03:47:30', '2025-07-04 03:47:30'),
(21, 'PROD021', 'Testing', 'Testing', '20.00', '10.00', 0, 'Testing', 'images/products/product_21_686753bc59b78.jpg', '2025-07-04 03:59:12', '2025-07-04 04:08:28'),
(22, 'PROD023', 'Testing1', 'Testing1', '30.00', '15.00', 0, 'Beverages', 'images/products/product_22_686753b1796ed.jpg', '2025-07-04 04:00:48', '2025-07-04 04:09:25');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','cashier','manager') DEFAULT 'cashier',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=MyISAM AUTO_INCREMENT=5 DEFAULT CHARSET=latin1;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `role`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@pos.com', '$2y$10$8gz7vcKRBkGSkHkTfEYy..lspHoGvttz4ioaRko2jzDyFDql0Yr.C', 'admin', '2025-07-04 02:37:13', '2025-07-04 02:37:13'),
(2, 'chamrern', 'chamrern@gmail.com', '$2y$10$.iFJi/8wG305GsIfVeK.pe90nMZK9WZl9OKzTYp9EBEo.BECtB6jm', 'admin', '2025-07-04 02:48:03', '2025-07-04 02:48:03'),
(3, 'users', 'user@gmail.com', '$2y$10$ErUu0aA6Zx18A7I8Wl8lrOl9g.vwGW/JcfMYGRZ/9FqDxaTGIfGFO', 'cashier', '2025-07-04 02:48:36', '2025-07-04 02:48:36'),
(4, 'manager', 'manager@gmail.com', '$2y$10$b3FaHdMMEAnnRP9EGQ4LkeqrnVVnyecveJVnl6dOibAsX5St0J.KS', 'manager', '2025-07-04 02:49:03', '2025-07-04 02:49:03');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
