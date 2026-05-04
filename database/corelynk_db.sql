-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 25, 2025 at 08:47 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `corelynk_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `accounts`
--

CREATE TABLE `accounts` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(190) NOT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `type` varchar(50) NOT NULL,
  `currency_code` varchar(10) DEFAULT 'PKR',
  `is_bank` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `accounts`
--

INSERT INTO `accounts` (`id`, `code`, `name`, `account_number`, `type`, `currency_code`, `is_bank`, `is_active`, `parent_id`, `created_at`, `updated_at`) VALUES
(1, '1000', 'Cash in Hand', NULL, 'Asset', 'PKR', 0, 1, NULL, '2025-11-12 12:47:50', '2025-11-17 21:04:22'),
(2, '1100', 'Cash at Banks', NULL, 'Asset', 'PKR', 0, 1, 23, '2025-11-12 12:47:50', '2025-11-20 13:27:53'),
(3, '1200', 'Accounts Receivable', NULL, 'Asset', 'PKR', 0, 1, 2, '2025-11-12 12:47:50', '2025-11-12 13:42:38'),
(4, '1300', 'Inventory', NULL, 'Asset', 'PKR', 0, 1, NULL, '2025-11-12 12:47:50', '2025-11-12 12:47:50'),
(5, '1400', 'Prepaid Expenses', NULL, 'Asset', 'PKR', 0, 1, 4, '2025-11-12 12:47:50', '2025-11-12 13:42:43'),
(6, '1500', 'Fixed Assets', NULL, 'Asset', 'PKR', 0, 1, NULL, '2025-11-12 12:47:50', '2025-11-12 12:47:50'),
(7, '2000', 'Accounts Payable', NULL, 'Liability', 'PKR', 0, 1, NULL, '2025-11-12 12:47:50', '2025-11-12 12:47:50'),
(8, '2100', 'Accrued Expenses', NULL, 'Liability', 'PKR', 0, 1, NULL, '2025-11-12 12:47:50', '2025-11-12 12:47:50'),
(9, '2200', 'Taxes Payable', NULL, 'Liability', 'PKR', 0, 1, 8, '2025-11-12 12:47:50', '2025-11-12 13:42:52'),
(10, '3000', 'Owner\'s Capital', NULL, 'Equity', 'PKR', 0, 1, NULL, '2025-11-12 12:47:50', '2025-11-12 12:47:50'),
(11, '3100', 'Retained Earnings', NULL, 'Equity', 'PKR', 0, 1, NULL, '2025-11-12 12:47:50', '2025-11-12 12:47:50'),
(12, '4000', 'Sales Revenue', NULL, 'Revenue', 'PKR', 0, 1, NULL, '2025-11-12 12:47:50', '2025-11-17 15:09:49'),
(13, '4100', 'Service Revenue', NULL, 'Revenue', 'PKR', 0, 1, NULL, '2025-11-12 12:47:50', '2025-11-17 15:10:12'),
(14, '5000', 'Cost of Goods Sold', NULL, 'Expense', 'PKR', 0, 1, NULL, '2025-11-12 12:47:50', '2025-11-12 12:47:50'),
(15, '5100', 'Rent Expense', NULL, 'Expense', 'PKR', 0, 1, NULL, '2025-11-12 12:47:50', '2025-11-12 12:47:50'),
(16, '5200', 'Salaries Expense', NULL, 'Expense', 'PKR', 0, 1, NULL, '2025-11-12 12:47:50', '2025-11-12 12:47:50'),
(17, '5300', 'Utilities Expense', NULL, 'Expense', 'PKR', 0, 1, NULL, '2025-11-12 12:47:50', '2025-11-12 12:47:50'),
(18, '5400', 'Office Supplies', NULL, 'Expense', 'PKR', 0, 1, NULL, '2025-11-12 12:47:50', '2025-11-12 12:47:50'),
(19, '5500', 'Depreciation Expense', NULL, 'Expense', 'PKR', 0, 1, NULL, '2025-11-12 12:47:50', '2025-11-12 12:47:50'),
(20, '5600', 'Marketing Expense', NULL, 'Expense', 'PKR', 0, 1, NULL, '2025-11-12 12:47:50', '2025-11-12 12:47:50'),
(21, '5700', 'Petty Cash - RI-SKT', NULL, 'Expense', 'PKR', 0, 1, NULL, '2025-11-12 15:13:46', '2025-11-12 15:17:56'),
(22, '5800', 'Refreshment (Breakfask / Brunch / Lunch )', NULL, 'Expense', 'PKR', 0, 1, NULL, '2025-11-12 15:17:38', '2025-11-15 05:39:49'),
(23, '1101', 'Bank Islami', NULL, 'Asset', 'PKR', 1, 1, NULL, '2025-11-12 15:27:17', '2025-11-21 23:30:19'),
(24, '4200', 'Local Services Revenue', NULL, 'Revenue', 'PKR', 0, 1, NULL, '2025-11-14 15:19:06', '2025-11-14 15:19:06'),
(25, '4201', 'Laser Marking Service', NULL, 'Revenue', 'PKR', 0, 1, 24, '2025-11-14 15:19:28', '2025-11-17 15:02:43'),
(26, '5050', 'Bank Charges', NULL, 'Expense', 'PKR', 0, 1, NULL, NULL, '2025-11-24 13:58:56'),
(27, '4050', 'Exchange Gain', NULL, 'Revenue', 'PKR', 0, 1, NULL, NULL, NULL),
(28, '5055', 'Exchange Loss', NULL, 'Expense', 'PKR', 0, 1, 26, NULL, '2025-11-17 15:02:43'),
(29, '1102', 'UBL - 1146', NULL, 'Asset', 'PKR', 1, 1, NULL, '2025-11-17 14:47:38', '2025-11-21 23:30:28'),
(30, '1103', 'UBL Proprietor - Regal', NULL, 'Asset', 'PKR', 1, 1, NULL, '2025-11-17 14:48:30', '2025-11-21 23:30:35'),
(31, '5301', 'Electricity Bills - Office SKT', NULL, 'Expense', 'PKR', 0, 1, 17, '2025-11-17 14:51:03', '2025-11-17 20:58:26'),
(32, '5302', 'Electricity Bills - Office WB', NULL, 'Expense', 'PKR', 0, 1, 17, '2025-11-17 14:51:16', '2025-11-17 20:58:43'),
(33, '5101', 'Office Rent - SKT', NULL, 'Expense', 'PKR', 0, 1, 15, '2025-11-17 14:53:51', '2025-11-17 20:55:47'),
(34, '5102', 'Office Rent - WB', NULL, 'Expense', 'PKR', 0, 1, 15, '2025-11-17 14:54:11', '2025-11-17 20:59:11'),
(35, '5900', 'Traveling \\ Petrol \\ Transport', NULL, 'Expense', 'PKR', 0, 1, NULL, '2025-11-21 23:22:02', '2025-11-21 23:22:02'),
(36, '1104', 'Advance Tax – WHT u/s 147', NULL, 'Asset', 'PKR', 0, 1, NULL, '2025-11-24 13:53:57', '2025-11-24 13:53:57'),
(37, '1105', 'WHT 154 – Export Withholding', NULL, 'Asset', 'PKR', 0, 1, NULL, '2025-11-24 13:56:45', '2025-11-24 13:56:45'),
(38, '5051', 'Bank Fee', NULL, 'Expense', 'PKR', 0, 1, NULL, '2025-11-24 13:59:22', '2025-11-24 13:59:22'),
(39, '5052', 'Export Development Surcharge', NULL, 'Expense', 'PKR', 0, 1, NULL, '2025-11-24 13:59:39', '2025-11-24 13:59:39'),
(40, '5053', 'EDS Handling Charges', NULL, 'Expense', 'PKR', 0, 1, NULL, '2025-11-24 13:59:57', '2025-11-24 13:59:57'),
(41, '5054', 'FED Charges', NULL, 'Expense', 'PKR', 0, 1, NULL, '2025-11-24 14:00:09', '2025-11-24 14:00:09'),
(42, '5056', 'Bank Charges - Intermediary / Correspondent Bank Fees', NULL, 'Expense', 'PKR', 0, 1, NULL, '2025-11-24 14:11:48', '2025-11-24 14:11:48'),
(43, '5057', 'Export FDBC Fee', NULL, 'Expense', 'PKR', 0, 1, NULL, '2025-11-24 14:21:20', '2025-11-24 14:21:20');

-- --------------------------------------------------------

--
-- Table structure for table `cheques`
--

CREATE TABLE `cheques` (
  `id` int(11) NOT NULL,
  `bank_account_id` int(11) NOT NULL,
  `cheque_number` varchar(50) NOT NULL,
  `cheque_date` date NOT NULL,
  `payee_type` varchar(20) NOT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `contact_id` int(11) DEFAULT NULL,
  `payee_name` varchar(190) DEFAULT NULL,
  `delivery_type` varchar(20) DEFAULT 'ac_payee',
  `status` varchar(20) DEFAULT 'draft',
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `posted_entry_id` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `deleted_flag` tinyint(1) DEFAULT 0,
  `deleted_reason` text DEFAULT NULL,
  `deleted_by` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cheque_deletions`
--

CREATE TABLE `cheque_deletions` (
  `id` int(11) NOT NULL,
  `cheque_id` int(11) NOT NULL,
  `cheque_number` varchar(50) DEFAULT NULL,
  `amount` decimal(18,2) DEFAULT NULL,
  `reason` text DEFAULT NULL,
  `deleted_by` varchar(100) DEFAULT NULL,
  `deleted_at` datetime NOT NULL,
  `payload` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`payload`))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cheque_lines`
--

CREATE TABLE `cheque_lines` (
  `id` int(11) NOT NULL,
  `cheque_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cheque_sequences`
--

CREATE TABLE `cheque_sequences` (
  `id` int(11) NOT NULL,
  `bank_account_id` int(11) NOT NULL,
  `prefix` varchar(10) DEFAULT NULL,
  `next_number` int(11) NOT NULL DEFAULT 1,
  `suffix` varchar(10) DEFAULT NULL,
  `last_issued_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `company_settings`
--

CREATE TABLE `company_settings` (
  `id` int(11) NOT NULL,
  `name` varchar(150) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `tagline` varchar(255) DEFAULT NULL,
  `contact` varchar(150) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `base_currency` varchar(10) DEFAULT 'PKR',
  `secondary_currency` varchar(10) DEFAULT 'USD',
  `use_demo_data` tinyint(1) DEFAULT 1,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `company_settings`
--

INSERT INTO `company_settings` (`id`, `name`, `address`, `tagline`, `contact`, `email`, `base_currency`, `secondary_currency`, `use_demo_data`, `updated_at`) VALUES
(1, 'Regal Impex', 'Rehmat Pura, Near Chiragah Masjid Dalowali Sialkot, 051310 ', 'Committed to Customer Satisfaction', '+923006111852', 'regalimpextools@gmail.com', 'PKR', 'USD', 0, '2025-11-22 13:04:12');

-- --------------------------------------------------------

--
-- Table structure for table `components`
--

CREATE TABLE `components` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  `current_stock` decimal(10,3) DEFAULT 0.000,
  `minimum_stock` decimal(10,3) DEFAULT 0.000,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `component_stock_transactions`
--

CREATE TABLE `component_stock_transactions` (
  `id` int(10) UNSIGNED NOT NULL,
  `component_id` int(10) UNSIGNED NOT NULL,
  `transaction_type` enum('in','out','adjustment') NOT NULL,
  `quantity` decimal(10,3) NOT NULL,
  `unit_cost` decimal(10,2) DEFAULT 0.00,
  `reference_type` enum('purchase','work_order','adjustment','return') NOT NULL,
  `reference_id` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `component_usage`
--

CREATE TABLE `component_usage` (
  `id` int(10) UNSIGNED NOT NULL,
  `work_order_id` int(10) UNSIGNED NOT NULL,
  `component_id` int(10) UNSIGNED NOT NULL,
  `quantity_required` decimal(10,3) NOT NULL,
  `quantity_used` decimal(10,3) DEFAULT 0.000,
  `quantity_remaining` decimal(10,3) DEFAULT 0.000,
  `issued_by` int(10) UNSIGNED DEFAULT NULL,
  `issued_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `credit_notes`
--

CREATE TABLE `credit_notes` (
  `id` int(11) NOT NULL,
  `party_type` varchar(30) NOT NULL,
  `party_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `reference` varchar(100) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `applied_amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `status` varchar(30) NOT NULL DEFAULT 'open',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `currencies`
--

CREATE TABLE `currencies` (
  `code` varchar(10) NOT NULL,
  `name` varchar(100) NOT NULL,
  `symbol` varchar(10) DEFAULT NULL,
  `is_base` tinyint(1) DEFAULT 0,
  `decimals` int(11) DEFAULT 2
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `currencies`
--

INSERT INTO `currencies` (`code`, `name`, `symbol`, `is_base`, `decimals`) VALUES
('EUR', 'Euro', '€', 0, 2),
('PKR', 'Pakistani Rupee', '₨', 1, 2),
('USD', 'US Dollar', '$', 0, 2);

-- --------------------------------------------------------

--
-- Table structure for table `employees`
--

CREATE TABLE `employees` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_code` varchar(20) DEFAULT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `department` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employees`
--

INSERT INTO `employees` (`id`, `employee_code`, `first_name`, `last_name`, `phone`, `email`, `department`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'EMP001', 'Ramzan', 'Butt', '', '', 'Production', 1, '2025-10-09 10:48:28', '2025-10-09 12:14:35'),
(2, 'EMP002', 'Zargham', 'Ali', '', '', 'Production', 1, '2025-11-02 15:18:45', '2025-11-02 15:18:45'),
(3, 'EMP003', 'M', 'Affaq', '', '', 'Production', 1, '2025-11-13 08:35:28', '2025-11-13 08:35:28');

-- --------------------------------------------------------

--
-- Table structure for table `employee_skills`
--

CREATE TABLE `employee_skills` (
  `id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL,
  `skill_name` varchar(100) NOT NULL,
  `proficiency_level` varchar(50) DEFAULT 'basic',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `employee_skills`
--

INSERT INTO `employee_skills` (`id`, `employee_id`, `skill_name`, `proficiency_level`, `created_at`, `updated_at`) VALUES
(2, 1, 'Laser Marking, Packing', 'intermediate', '2025-10-09 12:14:35', '2025-10-09 12:14:35'),
(3, 2, 'Laser Marking', 'basic', '2025-11-02 15:18:45', '2025-11-02 15:18:45'),
(4, 3, 'Finishing', 'basic', '2025-11-13 08:35:28', '2025-11-13 08:35:28'),
(5, 3, 'Edge Sharpening', 'basic', '2025-11-13 08:35:28', '2025-11-13 08:35:28');

-- --------------------------------------------------------

--
-- Table structure for table `exchange_rate`
--

CREATE TABLE `exchange_rate` (
  `id` int(11) NOT NULL,
  `base_code` varchar(10) NOT NULL,
  `quote_code` varchar(10) NOT NULL,
  `rate` decimal(15,6) NOT NULL,
  `as_of` date NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `exchange_rate`
--

INSERT INTO `exchange_rate` (`id`, `base_code`, `quote_code`, `rate`, `as_of`, `created_at`) VALUES
(1, 'USD', 'PKR', 280.000000, '2025-11-14', '2025-11-14 15:04:35'),
(2, 'USD', 'PKR', 270.000000, '2025-11-14', '2025-11-14 15:06:45');

-- --------------------------------------------------------

--
-- Table structure for table `fiscal_year`
--

CREATE TABLE `fiscal_year` (
  `id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `fiscal_year`
--

INSERT INTO `fiscal_year` (`id`, `start_date`, `end_date`, `is_active`, `created_at`) VALUES
(1, '2025-07-01', '2026-06-30', 1, '2025-11-14 15:06:10');

-- --------------------------------------------------------

--
-- Table structure for table `gate_passes`
--

CREATE TABLE `gate_passes` (
  `id` int(10) UNSIGNED NOT NULL,
  `gate_pass_number` varchar(100) NOT NULL,
  `type` varchar(20) NOT NULL,
  `recipient_type` varchar(20) DEFAULT 'vendor',
  `recipient_name` varchar(150) DEFAULT NULL,
  `vendor_id` int(11) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `items` text DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending',
  `expected_date` datetime DEFAULT NULL,
  `actual_date` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `completed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `journal_entries`
--

CREATE TABLE `journal_entries` (
  `id` int(11) NOT NULL,
  `entry_date` date NOT NULL,
  `memo` varchar(255) DEFAULT NULL,
  `currency_code` varchar(10) DEFAULT 'PKR',
  `total_debits` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total_credits` decimal(18,2) NOT NULL DEFAULT 0.00,
  `usd_amount` decimal(18,2) DEFAULT NULL,
  `usd_fee` decimal(18,2) DEFAULT NULL,
  `usd_system_rate` decimal(18,6) DEFAULT NULL,
  `usd_bank_rate` decimal(18,6) DEFAULT NULL,
  `usd_net_converted` decimal(18,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `journal_entries`
--

INSERT INTO `journal_entries` (`id`, `entry_date`, `memo`, `currency_code`, `total_debits`, `total_credits`, `usd_amount`, `usd_fee`, `usd_system_rate`, `usd_bank_rate`, `usd_net_converted`) VALUES
(1, '2025-11-24', 'Journal Entry', 'PKR', 10297245.00, 10297245.00, NULL, NULL, NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `journal_lines`
--

CREATE TABLE `journal_lines` (
  `id` int(11) NOT NULL,
  `entry_id` int(11) NOT NULL,
  `account_id` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `debit` decimal(18,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(18,2) NOT NULL DEFAULT 0.00,
  `currency_code` varchar(10) DEFAULT 'PKR',
  `fx_rate` decimal(18,8) DEFAULT NULL,
  `base_amount` decimal(18,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `journal_lines`
--

INSERT INTO `journal_lines` (`id`, `entry_id`, `account_id`, `description`, `debit`, `credit`, `currency_code`, `fx_rate`, `base_amount`) VALUES
(1, 1, 23, 'usd rate 281', 10030822.00, 0.00, 'PKR', 1.00000000, 10030822.00),
(2, 1, 42, 'Bank charges $61', 17141.00, 0.00, 'PKR', 1.00000000, 17141.00),
(3, 1, 39, 'Journal Entry', 25700.00, 0.00, 'PKR', 1.00000000, 25700.00),
(4, 1, 40, 'Journal Entry', 80.00, 0.00, 'PKR', 1.00000000, 80.00),
(5, 1, 41, 'Journal Entry', 2480.00, 0.00, 'PKR', 1.00000000, 2480.00),
(6, 1, 36, 'Journal Entry', 102801.00, 0.00, 'PKR', 1.00000000, 102801.00),
(7, 1, 37, 'Journal Entry', 102801.00, 0.00, 'PKR', 1.00000000, 102801.00),
(8, 1, 43, 'Journal Entry', 15420.00, 0.00, 'PKR', 1.00000000, 15420.00),
(9, 1, 12, 'Journal Entry', 0.00, 10297245.00, 'PKR', 1.00000000, 10297245.00);

-- --------------------------------------------------------

--
-- Table structure for table `migrations`
--

CREATE TABLE `migrations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `version` varchar(255) NOT NULL,
  `class` varchar(255) NOT NULL,
  `group` varchar(255) NOT NULL,
  `namespace` varchar(255) NOT NULL,
  `time` int(11) NOT NULL,
  `batch` int(11) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `migrations`
--

INSERT INTO `migrations` (`id`, `version`, `class`, `group`, `namespace`, `time`, `batch`) VALUES
(1, '2025-11-13-000001', 'App\\Database\\Migrations\\CreateCreditNotes', 'default', 'App', 1763971203, 1),
(2, '2025-11-13-000002', 'App\\Database\\Migrations\\CreatePurchaseOrders', 'default', 'App', 1763971203, 1),
(3, '2025-11-13-000003', 'App\\Database\\Migrations\\CreatePurchaseOrderLines', 'default', 'App', 1763971203, 1),
(4, '2025-11-13-000010', 'App\\Database\\Migrations\\AddProcessResponsibility', 'default', 'App', 1763971203, 1),
(5, '2025-11-13-000011', 'App\\Database\\Migrations\\AddBatchEmployees', 'default', 'App', 1763971203, 1),
(6, '2025-11-13-000012', 'App\\Database\\Migrations\\AddBatchAssigneeDepartment', 'default', 'App', 1763971204, 1);

-- --------------------------------------------------------

--
-- Table structure for table `payment_methods`
--

CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL,
  `method_name` varchar(100) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `processes`
--

CREATE TABLE `processes` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED DEFAULT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `sequence_order` int(11) NOT NULL DEFAULT 1,
  `is_vendor_process` tinyint(1) NOT NULL DEFAULT 0,
  `vendor_id` int(10) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `standard_time_minutes` int(11) DEFAULT 0,
  `responsibility_mode` varchar(20) DEFAULT NULL,
  `responsibility_department` varchar(100) DEFAULT NULL,
  `qc_checklist` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`qc_checklist`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `process_template_id` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `processes`
--

INSERT INTO `processes` (`id`, `product_id`, `category_id`, `name`, `sequence_order`, `is_vendor_process`, `vendor_id`, `description`, `standard_time_minutes`, `responsibility_mode`, `responsibility_department`, `qc_checklist`, `is_active`, `created_at`, `updated_at`, `process_template_id`) VALUES
(21, NULL, 1, 'Temper (HRC)', 1, 1, 3, '', 0, NULL, NULL, NULL, 1, '2025-11-02 17:31:33', '2025-11-03 12:10:02', NULL),
(22, NULL, 1, 'Laser Cutting', 1, 1, 4, '', 0, NULL, NULL, NULL, 1, '2025-11-02 17:31:49', '2025-11-03 12:09:05', NULL),
(23, NULL, 1, 'Knife Bevel Marking with Laser', 1, 0, NULL, '', 0, NULL, NULL, NULL, 1, '2025-11-02 17:32:16', '2025-11-02 17:32:16', NULL),
(24, NULL, 1, 'Surface Griding', 1, 1, 5, '', 0, NULL, NULL, NULL, 1, '2025-11-02 17:32:45', '2025-11-03 12:09:50', NULL),
(25, NULL, 1, 'Whole Burr Cleaning', 1, 0, NULL, '', 0, NULL, NULL, NULL, 1, '2025-11-02 17:33:00', '2025-11-02 17:33:00', NULL),
(26, NULL, 1, 'Blank Level Checking & Fixing', 1, 0, NULL, '', 0, NULL, NULL, NULL, 1, '2025-11-02 17:33:30', '2025-11-03 12:07:38', NULL),
(27, NULL, 1, 'Bevel Making', 1, 1, 8, '', 0, NULL, NULL, NULL, 1, '2025-11-02 17:34:15', '2025-11-03 12:07:27', NULL),
(28, NULL, 2, 'Finishing - In-House (Bevel) ', 1, 0, NULL, '', 0, NULL, NULL, NULL, 1, '2025-11-02 17:34:40', '2025-11-10 08:06:55', NULL),
(29, NULL, 1, 'Handle Hole Making', 1, 0, NULL, '', 0, NULL, NULL, NULL, 1, '2025-11-02 17:34:57', '2025-11-03 12:08:10', NULL),
(30, NULL, 1, 'Handle Surface Griding', 1, 0, NULL, '', 0, NULL, NULL, NULL, 1, '2025-11-02 17:35:04', '2025-11-03 12:08:30', NULL),
(31, NULL, 2, 'Handle Alignment', 1, 0, NULL, '', 0, NULL, NULL, NULL, 1, '2025-11-02 17:35:18', '2025-11-03 12:07:59', NULL),
(32, NULL, 2, 'Knife & Handle Number Marking', 1, 0, NULL, '', 0, NULL, NULL, NULL, 1, '2025-11-02 17:35:29', '2025-11-03 12:08:49', NULL),
(33, NULL, 2, 'Sharpening', 1, 1, 9, '', 0, NULL, NULL, NULL, 1, '2025-11-02 17:36:12', '2025-11-03 12:09:38', NULL),
(34, NULL, 2, 'Packing', 1, 0, NULL, '', 0, NULL, NULL, NULL, 1, '2025-11-02 17:36:25', '2025-11-03 12:09:14', NULL),
(35, NULL, 2, 'Plasma Coloring', 1, 1, 6, '', 0, NULL, NULL, NULL, 1, '2025-11-02 17:37:42', '2025-11-03 12:09:28', NULL),
(36, NULL, 2, 'Finishing - OutSrc (Bevel)', 1, 1, 10, '', 0, NULL, NULL, NULL, 1, '2025-11-10 07:37:07', '2025-11-10 08:07:25', NULL),
(37, NULL, 2, 'Finishing - In-House (Up/Dwn + Sides)', 1, 0, NULL, '', 0, 'employees', NULL, NULL, 1, '2025-11-10 08:04:28', '2025-11-13 11:10:14', NULL),
(38, NULL, 2, 'Finishing - OutSrc (Up/Dwn + Sides)', 1, 0, NULL, '', 0, NULL, NULL, NULL, 1, '2025-11-10 08:05:22', '2025-11-10 08:07:36', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `process_batches`
--

CREATE TABLE `process_batches` (
  `id` int(10) UNSIGNED NOT NULL,
  `work_order_item_id` int(10) UNSIGNED NOT NULL,
  `process_id` int(10) UNSIGNED NOT NULL,
  `vendor_id` int(10) UNSIGNED DEFAULT NULL,
  `batch_code` varchar(100) DEFAULT NULL,
  `batch_number` varchar(100) DEFAULT NULL,
  `planned_qty` int(10) UNSIGNED DEFAULT 0,
  `actual_qty` int(10) UNSIGNED DEFAULT 0,
  `status` varchar(50) DEFAULT 'open',
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `process_batches`
--

INSERT INTO `process_batches` (`id`, `work_order_item_id`, `process_id`, `vendor_id`, `batch_code`, `batch_number`, `planned_qty`, `actual_qty`, `status`, `created_by`, `started_at`, `completed_at`, `created_at`, `updated_at`) VALUES
(62, 9, 22, 4, 'LC-2201', NULL, 247, 0, 'planned', NULL, NULL, NULL, '2025-10-15 08:39:00', '2025-11-07 08:39:46'),
(63, 9, 22, 4, 'LC-2202', NULL, 1457, 0, 'planned', NULL, NULL, NULL, '2025-10-17 08:40:00', '2025-11-07 08:40:40'),
(64, 9, 24, 5, 'SG-2401', NULL, 99, 0, 'planned', NULL, NULL, NULL, '2025-10-30 05:30:00', '2025-11-08 14:47:44'),
(65, 9, 24, 5, 'SG-2402', NULL, 50, 0, 'planned', NULL, NULL, NULL, '2025-10-31 05:30:00', '2025-11-08 14:54:07'),
(66, 9, 24, 5, 'SG-2403', NULL, 50, 0, 'planned', NULL, NULL, NULL, '2025-10-31 07:50:00', '2025-11-10 10:51:39'),
(68, 9, 24, 5, 'SG-2405', NULL, 99, 0, 'planned', NULL, NULL, NULL, '2025-11-01 05:30:00', '2025-11-10 10:55:59'),
(69, 9, 35, 6, 'PC-3501', NULL, 103, 0, 'planned', NULL, NULL, NULL, '2025-10-27 05:09:00', '2025-11-10 11:11:17'),
(70, 9, 21, 3, 'HRC-2101', NULL, 71, 0, 'planned', NULL, NULL, NULL, '2025-10-21 05:12:00', '2025-11-10 11:14:00'),
(71, 9, 24, 5, 'SG-2406', NULL, 80, 0, 'planned', NULL, NULL, NULL, '2025-11-03 04:09:00', '2025-11-10 12:17:07'),
(72, 9, 24, 5, 'SG-2407', NULL, 120, 0, 'planned', NULL, NULL, NULL, '2025-11-04 08:30:00', '2025-11-10 12:18:07'),
(73, 9, 21, 3, 'HRC-2102', NULL, 114, 0, 'planned', NULL, NULL, NULL, '2025-11-08 04:17:00', '2025-11-10 13:18:55'),
(76, 9, 21, 3, 'HRC-2103', NULL, 299, 0, 'planned', NULL, NULL, NULL, '2025-11-05 04:18:00', '2025-11-13 16:19:20'),
(77, 9, 24, 5, 'SG-2408', NULL, 100, 0, 'planned', NULL, NULL, NULL, '2025-11-05 07:33:00', '2025-11-14 14:34:53'),
(78, 9, 24, 5, 'SG-2409', NULL, 220, 0, 'planned', NULL, NULL, NULL, '2025-11-06 04:30:00', '2025-11-14 14:36:10'),
(79, 9, 24, 5, 'SG-2410', NULL, 54, 0, 'planned', NULL, NULL, NULL, '2025-11-06 03:45:00', '2025-11-14 14:37:12'),
(80, 9, 24, 5, 'SG-2411', NULL, 96, 0, 'planned', NULL, NULL, NULL, '2025-11-08 05:30:00', '2025-11-14 14:42:02'),
(81, 9, 24, 5, 'SG-2412', NULL, 125, 0, 'planned', NULL, NULL, NULL, '2025-11-10 06:45:00', '2025-11-14 14:43:05'),
(82, 9, 24, 5, 'SG-2413', NULL, 140, 0, 'planned', NULL, NULL, NULL, '2025-11-12 04:00:00', '2025-11-14 14:46:21'),
(83, 9, 21, 3, 'HRC-2104', NULL, 196, 0, 'planned', NULL, NULL, NULL, '2025-11-17 09:02:00', '2025-11-17 13:02:26'),
(84, 9, 35, 7, 'PC-3502', NULL, 93, 0, 'planned', NULL, NULL, NULL, '2025-11-17 10:02:00', '2025-11-17 13:03:14'),
(85, 9, 21, 3, 'HRC-2105', NULL, 240, 0, 'planned', NULL, NULL, NULL, '2025-11-19 07:36:00', '2025-11-22 12:40:22');

-- --------------------------------------------------------

--
-- Table structure for table `process_batch_employees`
--

CREATE TABLE `process_batch_employees` (
  `id` int(11) UNSIGNED NOT NULL,
  `batch_id` int(11) UNSIGNED NOT NULL,
  `employee_id` int(11) UNSIGNED NOT NULL,
  `department` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `process_batch_logs`
--

CREATE TABLE `process_batch_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `process_batch_id` int(10) UNSIGNED NOT NULL,
  `log_date` date DEFAULT NULL,
  `log_type` varchar(50) DEFAULT 'progress',
  `qty_received` int(10) UNSIGNED DEFAULT 0,
  `qty_completed` int(10) UNSIGNED DEFAULT 0,
  `qty_rejected` int(10) UNSIGNED DEFAULT 0,
  `qty_scrapped` int(10) UNSIGNED DEFAULT 0,
  `qty_for_repair` int(10) UNSIGNED DEFAULT 0,
  `accepted_qty` int(10) UNSIGNED DEFAULT 0,
  `repaired_qty` int(10) UNSIGNED DEFAULT 0,
  `rejected_qty` int(10) UNSIGNED DEFAULT 0,
  `employee_id` int(10) UNSIGNED DEFAULT NULL,
  `vendor_id` int(10) UNSIGNED DEFAULT NULL,
  `operator_id` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `process_batch_logs`
--

INSERT INTO `process_batch_logs` (`id`, `process_batch_id`, `log_date`, `log_type`, `qty_received`, `qty_completed`, `qty_rejected`, `qty_scrapped`, `qty_for_repair`, `accepted_qty`, `repaired_qty`, `rejected_qty`, `employee_id`, `vendor_id`, `operator_id`, `notes`, `created_at`) VALUES
(42, 62, '2025-10-23', 'progress', 247, 247, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-10-23 08:39:00'),
(43, 63, '2025-10-29', 'progress', 1457, 1457, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-10-29 13:40:00'),
(44, 64, '2025-10-30', 'progress', 51, 51, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-10-30 10:30:00'),
(45, 64, '2025-10-31', 'progress', 48, 48, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-10-31 07:52:00'),
(46, 65, '2025-10-31', 'progress', 50, 50, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-10-31 07:54:00'),
(47, 66, '2025-10-31', 'progress', 50, 50, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-10-31 10:51:00'),
(48, 68, '2025-11-01', 'progress', 99, 99, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-11-01 10:00:00'),
(49, 69, '2025-10-31', 'progress', 100, 100, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-10-31 05:09:00'),
(50, 70, '2025-10-24', 'progress', 71, 71, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-10-24 09:13:00'),
(51, 71, '2025-11-03', 'progress', 80, 80, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-11-03 09:09:00'),
(52, 73, '2025-11-10', 'progress', 114, 114, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-11-10 11:27:00'),
(56, 76, '2025-11-06', 'progress', 100, 100, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-11-06 13:52:00'),
(57, 76, '2025-11-07', 'progress', 198, 198, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-11-07 11:31:00'),
(58, 72, '2025-11-05', 'progress', 120, 120, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-11-05 10:32:00'),
(59, 77, '2025-11-05', 'progress', 100, 100, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-11-05 10:00:00'),
(60, 78, '2025-11-06', 'progress', 120, 120, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-11-06 08:35:00'),
(61, 78, '2025-11-06', 'progress', 80, 80, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-11-06 10:15:00'),
(62, 78, '2025-11-08', 'progress', 20, 20, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-11-08 05:30:00'),
(63, 79, '2025-11-08', 'progress', 54, 54, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-11-08 05:00:00'),
(64, 80, '2025-11-10', 'progress', 96, 96, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-11-10 10:30:00'),
(65, 81, '2025-11-10', 'progress', 125, 125, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-11-10 10:30:00'),
(66, 82, '2025-11-12', 'progress', 40, 40, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, '(rework=0)', '2025-11-12 10:00:00'),
(67, 83, '2025-11-19', 'progress', 196, 196, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, 'Sent by Ramzan & Idrees Butt (rework=0)', '2025-11-19 06:31:00'),
(68, 85, '2025-11-20', 'progress', 233, 233, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, 'Sent By Idrees Butt  (rework=0)', '2025-11-20 12:40:00'),
(69, 85, '2025-11-21', 'progress', 7, 7, 0, 0, 0, 0, 0, 0, NULL, NULL, NULL, 'Taken By us when  we went all together on 21-11-2025 (rework=0)', '2025-11-21 12:41:00');

-- --------------------------------------------------------

--
-- Table structure for table `process_batch_releases`
--

CREATE TABLE `process_batch_releases` (
  `id` int(10) UNSIGNED NOT NULL,
  `process_batch_id` int(10) UNSIGNED NOT NULL,
  `released_qty` decimal(10,3) NOT NULL,
  `released_by` int(10) UNSIGNED DEFAULT NULL,
  `released_at` datetime DEFAULT NULL,
  `gatepass_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `process_categories`
--

CREATE TABLE `process_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `process_categories`
--

INSERT INTO `process_categories` (`id`, `name`, `description`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Prepration Stage', '', 1, '2025-10-08 14:36:05', '2025-10-08 14:36:05'),
(2, 'Finishing Stage', '', 1, '2025-10-08 14:36:16', '2025-10-08 14:36:16');

-- --------------------------------------------------------

--
-- Table structure for table `process_employee_assignments`
--

CREATE TABLE `process_employee_assignments` (
  `id` int(10) UNSIGNED NOT NULL,
  `process_id` int(10) UNSIGNED NOT NULL,
  `employee_id` int(10) UNSIGNED NOT NULL,
  `created_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `process_employee_assignments`
--

INSERT INTO `process_employee_assignments` (`id`, `process_id`, `employee_id`, `created_at`) VALUES
(4, 37, 3, '2025-11-13 16:10:14');

-- --------------------------------------------------------

--
-- Table structure for table `process_templates`
--

CREATE TABLE `process_templates` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `is_vendor_process` tinyint(1) DEFAULT 0,
  `vendor_id` int(10) UNSIGNED DEFAULT NULL,
  `standard_time_minutes` int(11) DEFAULT 0,
  `qc_checklist` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`qc_checklist`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `process_templates`
--

INSERT INTO `process_templates` (`id`, `name`, `description`, `category`, `category_id`, `is_vendor_process`, `vendor_id`, `standard_time_minutes`, `qc_checklist`, `created_at`, `updated_at`) VALUES
(1, 'CNC Cutting', 'Computer Numerical Control cutting operation', 'machining', NULL, 0, NULL, 45, NULL, '2025-10-09 14:54:50', '2025-10-09 14:54:50'),
(2, 'Manual Cutting', 'Manual cutting using hand tools', 'machining', NULL, 0, NULL, 60, NULL, '2025-10-09 14:54:50', '2025-10-09 14:54:50'),
(3, 'Drilling Operations', 'Various drilling operations for holes', 'machining', NULL, 0, NULL, 30, NULL, '2025-10-09 14:54:50', '2025-10-09 14:54:50'),
(4, 'Turning Operations', 'Lathe turning operations', 'machining', NULL, 0, NULL, 40, NULL, '2025-10-09 14:54:50', '2025-10-09 14:54:50'),
(5, 'Manual Assembly', 'Hand assembly of components', 'assembly', NULL, 0, NULL, 90, NULL, '2025-10-09 14:54:50', '2025-10-09 14:54:50'),
(6, 'Automated Assembly', 'Machine-assisted assembly process', 'assembly', NULL, 0, NULL, 60, NULL, '2025-10-09 14:54:50', '2025-10-09 14:54:50'),
(7, 'Sub-Assembly Creation', 'Creating intermediate assemblies', 'assembly', NULL, 0, NULL, 75, NULL, '2025-10-09 14:54:50', '2025-10-09 14:54:50'),
(8, 'Surface Preparation', 'Cleaning and surface prep before finishing', 'finishing', NULL, 0, NULL, 25, NULL, '2025-10-09 14:54:50', '2025-10-09 14:54:50'),
(9, 'Painting/Coating', 'Application of paint or protective coating', 'finishing', NULL, 0, NULL, 35, NULL, '2025-10-09 14:54:50', '2025-10-09 14:54:50'),
(10, 'Polishing', 'Surface polishing and buffing', 'finishing', NULL, 0, NULL, 40, NULL, '2025-10-09 14:54:50', '2025-10-09 14:54:50'),
(11, 'Incoming Inspection', 'Quality check of incoming materials', 'quality', NULL, 0, NULL, 20, NULL, '2025-10-09 14:54:50', '2025-10-09 14:54:50'),
(12, 'In-Process Inspection', 'Quality checks during manufacturing', 'quality', NULL, 0, NULL, 15, NULL, '2025-10-09 14:54:50', '2025-10-09 14:54:50'),
(13, 'Final Inspection', 'Final quality verification before shipping', 'quality', NULL, 0, NULL, 30, NULL, '2025-10-09 14:54:50', '2025-10-09 14:54:50'),
(14, 'Standard Packaging', 'Regular product packaging', 'packaging', NULL, 0, NULL, 15, NULL, '2025-10-09 14:54:50', '2025-10-09 14:54:50'),
(15, 'Custom Packaging', 'Special packaging requirements', 'packaging', NULL, 0, NULL, 25, NULL, '2025-10-09 14:54:50', '2025-10-09 14:54:50'),
(16, 'Function Testing', 'Operational function verification', 'testing', NULL, 0, NULL, 45, NULL, '2025-10-09 14:54:50', '2025-10-09 14:54:50'),
(17, 'Stress Testing', 'Product stress and durability testing', 'testing', NULL, 0, NULL, 60, NULL, '2025-10-09 14:54:50', '2025-10-09 14:54:50'),
(18, 'CNC Cutting', 'Computer Numerical Control cutting operation', 'machining', NULL, 0, NULL, 45, '[\"Check dimensions\", \"Surface finish quality\", \"Tool wear inspection\"]', '2025-10-13 18:25:16', '2025-10-13 18:25:16'),
(19, 'Manual Cutting', 'Manual cutting using hand tools', 'machining', NULL, 0, NULL, 60, '[\"Measurement accuracy\", \"Edge quality\", \"Safety compliance\"]', '2025-10-13 18:25:16', '2025-10-13 18:25:16'),
(20, 'Drilling Operations', 'Various drilling operations for holes', 'machining', NULL, 0, NULL, 30, '[\"Hole diameter\", \"Depth accuracy\", \"Burr removal\"]', '2025-10-13 18:25:16', '2025-10-13 18:25:16'),
(21, 'Turning Operations', 'Lathe turning operations', 'machining', NULL, 0, NULL, 40, '[\"Dimensional accuracy\", \"Surface finish\", \"Concentricity\"]', '2025-10-13 18:25:16', '2025-10-13 18:25:16'),
(22, 'Manual Assembly', 'Hand assembly of components', 'assembly', NULL, 0, NULL, 90, '[\"Component fit\", \"Fastener torque\", \"Assembly sequence\"]', '2025-10-13 18:25:16', '2025-10-13 18:25:16'),
(23, 'Automated Assembly', 'Machine-assisted assembly process', 'assembly', NULL, 0, NULL, 60, '[\"Program verification\", \"Component alignment\", \"Cycle time\"]', '2025-10-13 18:25:16', '2025-10-13 18:25:16'),
(24, 'Sub-Assembly Creation', 'Creating intermediate assemblies', 'assembly', NULL, 0, NULL, 75, '[\"Sub-assembly function\", \"Component count\", \"Documentation\"]', '2025-10-13 18:25:16', '2025-10-13 18:25:16'),
(25, 'Surface Preparation', 'Cleaning and surface prep before finishing', 'finishing', NULL, 0, NULL, 25, '[\"Surface cleanliness\", \"Contamination check\", \"Drying time\"]', '2025-10-13 18:25:16', '2025-10-13 18:25:16'),
(26, 'Painting/Coating', 'Application of paint or protective coating', 'finishing', NULL, 0, NULL, 35, '[\"Coverage uniformity\", \"Thickness measurement\", \"Drying/Curing\"]', '2025-10-13 18:25:16', '2025-10-13 18:25:16'),
(27, 'Polishing', 'Surface polishing and buffing', 'finishing', NULL, 0, NULL, 40, '[\"Surface smoothness\", \"Scratch removal\", \"Final appearance\"]', '2025-10-13 18:25:16', '2025-10-13 18:25:16'),
(28, 'Incoming Inspection', 'Quality check of incoming materials', 'quality', NULL, 0, NULL, 20, '[\"Material certification\", \"Visual inspection\", \"Dimensional check\"]', '2025-10-13 18:25:16', '2025-10-13 18:25:16'),
(29, 'In-Process Inspection', 'Quality checks during manufacturing', 'quality', NULL, 0, NULL, 15, '[\"Process parameters\", \"Intermediate dimensions\", \"Defect identification\"]', '2025-10-13 18:25:16', '2025-10-13 18:25:16'),
(30, 'Final Inspection', 'Final quality verification before shipping', 'quality', NULL, 0, NULL, 30, '[\"Final dimensions\", \"Function test\", \"Packaging check\"]', '2025-10-13 18:25:16', '2025-10-13 18:25:16'),
(31, 'Standard Packaging', 'Regular product packaging', 'packaging', NULL, 0, NULL, 15, '[\"Package integrity\", \"Label accuracy\", \"Protection adequacy\"]', '2025-10-13 18:25:16', '2025-10-13 18:25:16'),
(32, 'Custom Packaging', 'Special packaging requirements', 'packaging', NULL, 0, NULL, 25, '[\"Custom requirements met\", \"Special handling\", \"Documentation\"]', '2025-10-13 18:25:16', '2025-10-13 18:25:16'),
(33, 'Function Testing', 'Operational function verification', 'testing', NULL, 0, NULL, 45, '[\"Performance parameters\", \"Safety features\", \"Calibration\"]', '2025-10-13 18:25:16', '2025-10-13 18:25:16'),
(34, 'Stress Testing', 'Product stress and durability testing', 'testing', NULL, 0, NULL, 60, '[\"Load capacity\", \"Cycle count\", \"Failure modes\"]', '2025-10-13 18:25:16', '2025-10-13 18:25:16');

-- --------------------------------------------------------

--
-- Table structure for table `process_vendors`
--

CREATE TABLE `process_vendors` (
  `id` int(10) UNSIGNED NOT NULL,
  `process_id` int(10) UNSIGNED NOT NULL,
  `vendor_id` int(10) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `process_vendors`
--

INSERT INTO `process_vendors` (`id`, `process_id`, `vendor_id`, `is_active`, `created_at`, `updated_at`) VALUES
(12, 27, 8, 1, '2025-11-03 17:07:27', '2025-11-03 22:07:27'),
(13, 22, 4, 1, '2025-11-03 17:09:05', '2025-11-03 22:09:05'),
(14, 35, 6, 1, '2025-11-03 17:09:28', '2025-11-03 22:09:28'),
(15, 35, 7, 1, '2025-11-03 17:09:28', '2025-11-03 22:09:28'),
(16, 33, 9, 1, '2025-11-03 17:09:38', '2025-11-03 22:09:38'),
(17, 24, 5, 1, '2025-11-03 17:09:50', '2025-11-03 22:09:50'),
(18, 21, 3, 1, '2025-11-03 17:10:02', '2025-11-03 22:10:02'),
(19, 21, 6, 1, '2025-11-03 17:10:02', '2025-11-03 22:10:02'),
(25, 36, 10, 1, '2025-11-10 13:07:25', '2025-11-10 18:07:25'),
(26, 38, 11, 1, '2025-11-10 13:07:36', '2025-11-10 18:07:36'),
(27, 38, 12, 1, '2025-11-10 13:07:36', '2025-11-10 18:07:36');

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `code` varchar(50) NOT NULL,
  `category_id` int(10) UNSIGNED DEFAULT NULL,
  `unit` varchar(20) NOT NULL DEFAULT 'pcs',
  `description` text DEFAULT NULL,
  `images` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `name`, `code`, `category_id`, `unit`, `description`, `images`, `is_active`, `created_at`, `updated_at`) VALUES
(5, '13\" Tactical Knife', 'RI-K-1001', NULL, 'PCS', 'D2 Steel Knife', '[\"1762007784_aa6f17ecbb80112dca01.png\"]', 1, '2025-11-01 09:36:24', '2025-11-01 09:36:24');

-- --------------------------------------------------------

--
-- Table structure for table `product_categories`
--

CREATE TABLE `product_categories` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `product_processes`
--

CREATE TABLE `product_processes` (
  `id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `process_template_id` int(10) UNSIGNED DEFAULT NULL,
  `process_id` int(10) UNSIGNED DEFAULT NULL,
  `sequence_order` int(11) NOT NULL DEFAULT 1,
  `custom_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `product_processes`
--

INSERT INTO `product_processes` (`id`, `product_id`, `process_template_id`, `process_id`, `sequence_order`, `custom_notes`, `created_at`, `updated_at`) VALUES
(17, 5, NULL, 27, 4, NULL, '2025-11-02 17:38:02', '2025-11-02 17:40:02'),
(18, 5, NULL, 26, 2, NULL, '2025-11-02 17:38:12', '2025-11-02 17:40:02'),
(19, 5, NULL, 28, 5, NULL, '2025-11-02 17:38:17', '2025-11-02 17:40:02'),
(20, 5, NULL, 31, 6, NULL, '2025-11-02 17:38:22', '2025-11-02 17:40:02'),
(21, 5, NULL, 30, 7, NULL, '2025-11-02 17:38:24', '2025-11-02 17:40:02'),
(22, 5, NULL, 23, 8, NULL, '2025-11-02 17:38:28', '2025-11-02 17:40:02'),
(23, 5, NULL, 32, 9, NULL, '2025-11-02 17:38:32', '2025-11-02 17:40:02'),
(24, 5, NULL, 29, 10, NULL, '2025-11-02 17:38:36', '2025-11-02 17:40:02'),
(25, 5, NULL, 22, 1, NULL, '2025-11-02 17:38:42', '2025-11-02 17:40:02'),
(26, 5, NULL, 34, 11, NULL, '2025-11-02 17:38:47', '2025-11-02 17:40:02'),
(27, 5, NULL, 35, 12, NULL, '2025-11-02 17:38:51', '2025-11-02 17:40:02'),
(28, 5, NULL, 33, 13, NULL, '2025-11-02 17:38:55', '2025-11-02 17:40:02'),
(29, 5, NULL, 24, 3, NULL, '2025-11-02 17:39:00', '2025-11-02 17:40:02'),
(30, 5, NULL, 21, 14, NULL, '2025-11-02 17:39:04', '2025-11-02 17:40:02'),
(31, 5, NULL, 25, 15, NULL, '2025-11-02 17:39:08', '2025-11-02 17:40:02'),
(32, 5, NULL, 37, 16, NULL, '2025-11-12 09:23:59', '2025-11-12 09:23:59'),
(33, 5, NULL, 38, 17, NULL, '2025-11-12 09:24:05', '2025-11-12 09:24:05'),
(34, 5, NULL, 36, 18, NULL, '2025-11-12 09:24:15', '2025-11-12 09:24:15');

-- --------------------------------------------------------

--
-- Table structure for table `purchase_orders`
--

CREATE TABLE `purchase_orders` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'draft',
  `currency_code` varchar(10) DEFAULT 'PKR',
  `subtotal` decimal(18,2) NOT NULL DEFAULT 0.00,
  `tax_total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `total` decimal(18,2) NOT NULL DEFAULT 0.00,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `purchase_order_lines`
--

CREATE TABLE `purchase_order_lines` (
  `id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `description` varchar(255) DEFAULT NULL,
  `qty` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `unit_price` decimal(18,4) NOT NULL DEFAULT 0.0000,
  `tax_code_id` int(11) DEFAULT NULL,
  `line_total` decimal(18,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `qc_records`
--

CREATE TABLE `qc_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `process_run_id` int(10) UNSIGNED NOT NULL,
  `qc_checklist_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`qc_checklist_data`)),
  `quantity_checked` int(10) UNSIGNED NOT NULL,
  `quantity_passed` int(10) UNSIGNED DEFAULT 0,
  `quantity_failed` int(10) UNSIGNED DEFAULT 0,
  `qc_decision` enum('pass','rework','reject') NOT NULL,
  `remarks` text DEFAULT NULL,
  `inspected_by` int(10) UNSIGNED DEFAULT NULL,
  `inspected_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `rework_records`
--

CREATE TABLE `rework_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `qc_record_id` int(10) UNSIGNED NOT NULL,
  `original_process_run_id` int(10) UNSIGNED NOT NULL,
  `rework_process_run_id` int(10) UNSIGNED NOT NULL,
  `quantity_reworked` int(10) UNSIGNED NOT NULL,
  `reason` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `scrap_records`
--

CREATE TABLE `scrap_records` (
  `id` int(10) UNSIGNED NOT NULL,
  `process_run_id` int(10) UNSIGNED NOT NULL,
  `quantity_scrapped` int(10) UNSIGNED NOT NULL,
  `reason` text NOT NULL,
  `estimated_cost` decimal(10,2) DEFAULT 0.00,
  `actual_cost` decimal(10,2) DEFAULT 0.00,
  `recorded_by` int(10) UNSIGNED DEFAULT NULL,
  `recorded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `security_settings`
--

CREATE TABLE `security_settings` (
  `id` int(11) NOT NULL,
  `backdate_password_hash` varchar(255) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `security_settings`
--

INSERT INTO `security_settings` (`id`, `backdate_password_hash`, `updated_at`) VALUES
(1, '$2y$10$MjvMFL7CBBALhnSiifu78uZ3oINsn/1htaTH7mNDSO8pjgFGQkYci', '2025-11-14 20:26:03');

-- --------------------------------------------------------

--
-- Table structure for table `sessions`
--

CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `timestamp` int(10) UNSIGNED DEFAULT NULL,
  `data` blob DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sessions`
--

INSERT INTO `sessions` (`id`, `ip_address`, `timestamp`, `data`) VALUES
('ci_session:00560b36d1dacdefc613ea6075c81f49', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333936393534303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f32223b),
('ci_session:01bd58ccb128fa4860ca32745e02878c', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333535343239313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:0369dca73050c0bee59ca0c5317e8f4e', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831373031313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f76656e646f7273223b5f5f63695f766172737c613a303a7b7d),
('ci_session:0460b76ed51e617449e2563b818292ad', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333431343136383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33363a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e67223b5f5f63695f766172737c613a303a7b7d),
('ci_session:05724f44f115ee201badd9315fef4a83', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333634343137313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:06094be9ae04588596f965f9a27ddcea', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333937303637303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f32223b),
('ci_session:062b290da3221ddde2f570259b269137', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333431323130333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33363a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e67223b5f5f63695f766172737c613a303a7b7d),
('ci_session:070deecf329312f57c8f3b9411330581', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333938393235363b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b),
('ci_session:07ca54983c434da752bf7fe72bd4fa22', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333736303036373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:089cd9601547fb4b80780ebcbfc7b3bf', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333938373434323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f31223b),
('ci_session:09af193525de7a02422996d3c3e626f1', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333339323833323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c73223b5f5f63695f766172737c613a303a7b7d),
('ci_session:0a21886abd1cf049497c6a17afb4c043', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831313638373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f33223b),
('ci_session:0b123d15c59092eb71c32d9449ce1210', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333534393838323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:0c6925bd30d5c5cd67b0621f68060805', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333633393338303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35383a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f7265706f7274732f62616c616e63652d7368656574223b5f5f63695f766172737c613a303a7b7d),
('ci_session:0d66c0d7ae3c57269a070732bd9164d8', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333832323337323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f34223b),
('ci_session:0ed4fd55f3776410b05fe5611dba9ecb', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333535303735343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:0f2aa9dccecd0f3ca8a8a062a945e5fe', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333736313837303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:11716d126968dc64b82c9aaa81f6936a', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333535333530383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:12241725510b4c044c4de3db09e214eb', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333938333031303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b),
('ci_session:12f910a482d757623b5174bfaa834c27', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333634333832393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f637265617465223b),
('ci_session:147f2178272f3f4aa79903018fe4919c', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831303132373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f33223b),
('ci_session:14e50827133b8d3626034f80c096e35a', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736343034363532343b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f31223b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b),
('ci_session:1598602a9b52c4a05215cd2e5ab8501a', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333635323331303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:1667b7c397edbc8eb4d1226b98b1bd2b', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831333639333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34373a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f332f706466223b),
('ci_session:16a25d1ed260712ae527aae75f571140', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333437393632303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f76696577223b5f5f63695f766172737c613a303a7b7d),
('ci_session:16b4b37405833e0591c8bf345e427a79', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333830383432323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f33223b),
('ci_session:17212f3302b9e4a8388028a5a1090308', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333633373030303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35383a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f7265706f7274732f62616c616e63652d7368656574223b5f5f63695f766172737c613a303a7b7d),
('ci_session:183d27f115e0452bb24866919970636a', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831313338333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f33223b),
('ci_session:1b882f5eddab1043c4e68008d5f48469', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333634313931303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35383a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f7265706f7274732f62616c616e63652d7368656574223b5f5f63695f766172737c613a303a7b7d),
('ci_session:1cfc6aab337523499dda681f085469a0', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333634373538373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:1f0f43bf011ab073da3df656119db901', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333830393130333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f33223b),
('ci_session:1f935330c7bd13a87776798633fd8b01', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333535333837313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:21818e8d3285df4f5221aaa82e134aa9', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831323030383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f33223b),
('ci_session:2187b264a8dc3cd3e3f53458775ad1a6', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333437363639373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f637265617465223b5f5f63695f766172737c613a303a7b7d),
('ci_session:224a5bf45b92dcdabcb6476917cd0804', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333432323631333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f637265617465223b5f5f63695f766172737c613a303a7b7d),
('ci_session:2525925d0daffb652a29924e6e890355', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333938323636353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b),
('ci_session:271b4173dccf505c963ceadc2faa021e', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333431393438373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f637265617465223b5f5f63695f766172737c613a303a7b7d),
('ci_session:283dc4cad4739cb8aeee0415ff0c634e', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333432303533383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:28c434f3ebee5bc4bb4f5569f4b38fd4', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333431333431343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33363a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e67223b5f5f63695f766172737c613a303a7b7d),
('ci_session:28f5940c5ba42510040eaa3487ee9e95', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333535363132323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:2a889e9ff54192e1424cf5ec392d3147', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736343034373031373b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f31223b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b),
('ci_session:2aac0c08cfc603d8a3df5c70159b01de', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333735373530383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:2bbd88ee7b8f50a4b28584d82d89ebb8', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333534383932303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:2bee79456645ca1fa7d48ded7806b9a8', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333437343736323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f637265617465223b5f5f63695f766172737c613a303a7b7d),
('ci_session:2e72b610b6abfbd0e90522a08e66bc21', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333939323033343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b),
('ci_session:2e975a292ff9c6e85e45c591371249a2', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333634393037303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:30bfe3f30fc949b01b4df907d7bb5181', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831373734303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f76656e646f72732f31312f65646974223b5f5f63695f766172737c613a303a7b7d),
('ci_session:314c70367c56fd39b1814108c1ce17a2', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333939353838333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33363a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e67223b5f5f63695f766172737c613a303a7b7d),
('ci_session:322a020aef024fa32506b6e185064a2b', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333735363634353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:32b3c992960a7017c9cd13ec8587a9b0', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333438373330343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33363a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e67223b5f5f63695f766172737c613a303a7b7d),
('ci_session:32b408958b737e5ddf53ac6124c0ba91', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333438363630373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:34f657d54a821d7660c404a96c16986e', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333938303938313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b),
('ci_session:360d819c635741a289b4fa9d9065103f', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333737303736323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35383a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f7265706f7274732f62616c616e63652d7368656574223b5f5f63695f766172737c613a303a7b7d),
('ci_session:393a996804611a2f1795c9ee72c11e5c', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333830353834353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f33223b),
('ci_session:3962c7e12018598d0aea15d25077ddcb', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333535353736373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:3aed30973d5c279f4d9edf0a33952490', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333431393739343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33363a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e67223b5f5f63695f766172737c613a303a7b7d),
('ci_session:3afb40e02e73fe7ac2bce1c71a1daad1', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333432313535353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e74732f32392f65646974223b5f5f63695f766172737c613a303a7b7d),
('ci_session:3c2c7258947f9087a461c9abd8c0d013', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333432323631333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f637265617465223b5f5f63695f766172737c613a303a7b7d),
('ci_session:3d227615742a7b280b6d5b14e2358951', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333736343437303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:3eec12963d581e38beb6ca3a5dada8fb', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333939333932303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b),
('ci_session:4170c5588768948a5265189a0f714ae9', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333830393432373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f33223b),
('ci_session:426113ab32019c06a274db264c0f18cb', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333432303939353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:43e65ab83ffab0150b3b64f2c90bf69a', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333437373139303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34343a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f63686571756573223b5f5f63695f766172737c613a303a7b7d),
('ci_session:44c47602450f876e9e8249bc1b5d4ce3', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333634333531313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35383a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f7265706f7274732f62616c616e63652d7368656574223b5f5f63695f766172737c613a303a7b7d),
('ci_session:46661b08c75204f070c1c0c6d06ee055', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333938353234353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b),
('ci_session:47f2e65fa576f2f7af9afe53424288ca', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333939353838333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33363a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e67223b5f5f63695f766172737c613a303a7b7d),
('ci_session:483ae60958726df1a3a1fd511e475df2', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333935393838313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f34223b),
('ci_session:48b4dd52a967965fcd2a85c93663b7f8', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333431313537373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33363a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e67223b5f5f63695f766172737c613a303a7b7d),
('ci_session:49d4a19f8ae9f67f9450142bf23dd8bd', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736343034373534313b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f31223b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b),
('ci_session:4ca49675b906c1e089eee00ef74764a8', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333634383731333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:4d27d3df8a68e99d17ddfbf37427a29e', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333832333139333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34373a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f342f706466223b),
('ci_session:4d4eced69b67456802761c8ec995d2fa', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333938383234363b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f31223b),
('ci_session:4e33e83a4e437d633f350946a5523b37', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333832303235323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f76656e646f72732f31312f65646974223b5f5f63695f766172737c613a303a7b7d),
('ci_session:511dbe98d190ecb6461ce9b9502f4a77', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831393537383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f76656e646f72732f342f65646974223b),
('ci_session:5169bfb3bfca1789e409415ba6ea348b', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333937323039383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f32223b),
('ci_session:516e5b7b1e0dbaf1b071c277e0acb879', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333830343831353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f322f706466223b),
('ci_session:52aba34ba625aad0a8ee84fe3b387865', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333830323834333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34343a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f63686571756573223b5f5f63695f766172737c613a303a7b7d),
('ci_session:52f1b0d457ad04a36d9e6c08b5370e2f', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333634343637383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:534b98ab301858913f5bb4d7107ae6d5', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333830393736363b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f33223b),
('ci_session:5401532f69df0d8095bb81dd805a915a', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333432313935353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f637265617465223b5f5f63695f766172737c613a303a7b7d),
('ci_session:5610a15e35d71f7599fe07167a519df8', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831343232373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34373a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f332f706466223b),
('ci_session:566ea3cd80b75b99f6c6689613cfe939', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736343034393131303b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f31223b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b),
('ci_session:579cd1fb9ab848330d013e7be33ffe75', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333830363934353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f332f706466223b),
('ci_session:58df24d1d402f25ce4b2d99d1f5d2c18', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333634393430363b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:5923f3b52611bab690306bc9efc4d1af', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333437363236393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f637265617465223b5f5f63695f766172737c613a303a7b7d),
('ci_session:593f3851220af99be627e6f052c6a2e4', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333737313337303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f332f706466223b);
INSERT INTO `sessions` (`id`, `ip_address`, `timestamp`, `data`) VALUES
('ci_session:5a2832fc8590eeab37e661514139a613', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333736333430373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:5b0f939674d74d382bcace1daa72cf53', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333633383331363b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35383a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f7265706f7274732f62616c616e63652d7368656574223b5f5f63695f766172737c613a303a7b7d),
('ci_session:5c6ac3f1e15fab9bfe120e53a90e5096', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333737333035353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f322f706466223b),
('ci_session:62dc91bbc8ff19e893109290c8d6aadd', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333938373738373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c73223b5f5f63695f766172737c613a303a7b7d),
('ci_session:6372602514fe7cf9b6c31a9797f64622', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333634353130393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:63a891f7b0900f0e6bc2dca16f87c23a', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333535333033313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:65c7602098fcebf1509c85da1acf14e5', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333938363333393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f31223b),
('ci_session:67a0d4d75abc6e517b9a342d667ffd26', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333437303432353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f637265617465223b5f5f63695f766172737c613a303a7b7d),
('ci_session:6813a805572288a87d66c295948eaab7', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333830363232323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f33223b),
('ci_session:684a3b707ec8958683c848c7bc983e61', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831323431343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f33223b),
('ci_session:69a3898d6b30ca301b9e8ad28bf3b347', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831333035353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34373a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f332f706466223b),
('ci_session:6ad04a7ea4a18a6d623201f35f6b2238', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333431333732323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c73223b5f5f63695f766172737c613a303a7b7d),
('ci_session:6b12613cde84e8eac46b411ed228b1a3', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831363130313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33363a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e67223b5f5f63695f766172737c613a303a7b7d),
('ci_session:6b65e28e32190f8debf0e02dbd7b093a', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333830353137303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f322f706466223b),
('ci_session:6cce3a5cebccb06677a2a5bf7e77e9fc', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333939333233353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b),
('ci_session:6eca8e2d0fe113b00cc17b8d5e453245', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333534393330353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:705d06ff57b3a21e9e61f07c9829c2ee', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831383633383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f76656e646f72732f31312f65646974223b5f5f63695f766172737c613a303a7b7d),
('ci_session:706d35bdf2be3d2901f96423897ecba6', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333535323634353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:71198fc7a72f0c3864c74b6e1bd5ae4e', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333832303639303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f76656e646f7273223b5f5f63695f766172737c613a303a7b7d),
('ci_session:7261acdcb172f7f244383f2317c7d944', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333437353335323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f637265617465223b5f5f63695f766172737c613a303a7b7d),
('ci_session:72cb0a18f97cf7cb96b0248f8afc91a3', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333832323832313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34373a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f342f706466223b),
('ci_session:7536987c612c2c0e78c32d2ec0df55c6', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333635313934353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:77cbacfe690609e82bf36c9aca477e14', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736343034353734383b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f31223b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b),
('ci_session:7bfdadf72ad657532e7872227adc6624', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333736383438323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35383a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f7265706f7274732f62616c616e63652d7368656574223b),
('ci_session:7d57e8085e81e1d4310d1c6fcd34073b', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333736373831313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35383a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f7265706f7274732f62616c616e63652d7368656574223b5f5f63695f766172737c613a303a7b7d),
('ci_session:7ee10875e78247132a44d74ca92ca930', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333937323039383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f31223b),
('ci_session:80108ed16f671e4662f257706cd6726f', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333535313437393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34343a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f63686571756573223b),
('ci_session:8063e2f9676b0e7d1d6610059b031fc2', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333939343234343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b),
('ci_session:8088987afb095ba2071e571ba435f168', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333634363834333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:81a77db24ff55f89498e6f00ad007ddd', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333736393032373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35383a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f7265706f7274732f62616c616e63652d7368656574223b),
('ci_session:81c7374e09bb30836daa6c68158e6b7f', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333938383833303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b),
('ci_session:822fc3cfe4b426817042ba24649c744b', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333438363032373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:837fc1a5b372d559d243835c0033be89', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333736313235303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:843f11d5aa8c6ada8c271f89deae37d8', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333938353634353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f31223b),
('ci_session:84ea211870a4a22e8e4bcc6771e076fd', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333832313633353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f34223b),
('ci_session:86dc30b5ccc445ce318c174e23654bfe', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333830373730333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f33223b),
('ci_session:880ce55fff48d98a4b0541d1598bb23b', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333431323734303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33363a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e67223b5f5f63695f766172737c613a303a7b7d),
('ci_session:88aef8b9ef005d22f59357c3262e9cef', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333431353935323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b),
('ci_session:89acbf8dcfb41805d5088f8bca6c0b3e', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333431333130363b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b),
('ci_session:8ae016b9e14fa4e95052865f4488cd39', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333634383330333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:8cbff5e2e5a79a6541e8899b781a007a', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333535353032343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:8cf2c1b649f9a5d7a369a1c6651e30aa', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333737313036333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c73223b5f5f63695f766172737c613a303a7b7d),
('ci_session:8e162be18313816a1dd6fd6a701c3a93', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736343035363439393b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f31223b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b),
('ci_session:8e841b5e95a39fda48806ed7f78d896a', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831363433373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33343a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f73657474696e6773223b),
('ci_session:8f3e9b281537d58bcbffd6d8d2b4f239', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333535343637393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:8f648271b9771b9ad5f4e9ab2c349d77', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333438343635353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:8f89ba1c9f669a5a464fdb5c9ea58273', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333735383330353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:8fe77af40b348dc958624eff508b097e', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333936313738313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f747269616c2d62616c616e6365223b5f5f63695f766172737c613a303a7b7d),
('ci_session:90473220cee4efde47b39dc3e33b21c0', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333735363938343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:90c12fee0cfd91a982fb54b542db9f1a', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333438363931303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34343a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f63686571756573223b),
('ci_session:933c685562d5dbcf26f84c401061046f', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333938313438393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c73223b5f5f63695f766172737c613a303a7b7d),
('ci_session:94b6715b1ac3ba75372a9aa4aefc2f06', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333830383739373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f33223b),
('ci_session:9507b19af36b35618405ed757f8ccddf', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333735383934363b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:956b78f02d409f0cfa25634699509a02', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333535353435353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:9669192f1ae5f6089af345db9ddb5008', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333936333131373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f34223b),
('ci_session:97f5619b57b8c7ea5db3d347032896df', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333437333932393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f637265617465223b5f5f63695f766172737c613a303a7b7d),
('ci_session:9a999162f85836680d5a62605fb328d1', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333936393038303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c73223b5f5f63695f766172737c613a303a7b7d),
('ci_session:9aa17a9f6895c1fe94a8dac75c229208', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333736363731323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:9b2b94cc6884517d6e40c38699c3dbc3', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333736343937393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:9bce90a2f5c0a82c2ceec1c8846cb71f', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831333335393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f33223b),
('ci_session:9ccfc8dc38ddf5c89c64f4df810ddf27', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333535303339363b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:9db7e881d5823c70f8fef7fd47a994fb', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333737333035353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f322f706466223b),
('ci_session:9e36b88ef74465caa02f71e8b5c2e457', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333437383832343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34343a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f63686571756573223b),
('ci_session:9ece44c8c068460ddbc7a88caf2f1bf9', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333535373137363b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:9f8d8cedde86b97813fe8696da19c4d4', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333438353035333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:a39a0760d0ae3c907ca77559d47c669e', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736343035363439393b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c73223b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b),
('ci_session:a458ac37d5996c2d5c29c20558e79bda', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333937313336313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b),
('ci_session:a59c58de6c81e5509d5697ffb0a53d08', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333937303332353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f32223b),
('ci_session:a5ce2ee627a62d62e5225c49d9ca37e5', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831353736323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6c6f6773223b),
('ci_session:a7cd523f92108978a4416752c4c35749', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333936323338333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f34223b),
('ci_session:a82d0ecb81f2a6252c15f33f712a449a', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831353237323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6c6f6773223b),
('ci_session:aa448efffdbdcd1fe2439333ba7ecdd4', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333437393230393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34343a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f63686571756573223b5f5f63695f766172737c613a303a7b7d),
('ci_session:aac51fa91dbf07edb0bf68352c4a4314', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333432303233313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:ad676263a4ead828c14e123e3f66975a', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333737323734303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f332f706466223b),
('ci_session:ad7abc2c43369f55c3c99fa2a2688556', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333936393838383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f32223b),
('ci_session:ad94c1d47bf0ddc0f7ac811eddb4d410', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333534373435343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:aef3742ca127f1b38d1292430016ea1e', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333535313038383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:b0ddbaee428c9188710e8e72bd9883a1', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333736393632393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35383a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f7265706f7274732f62616c616e63652d7368656574223b),
('ci_session:b1e54ef6b1c9024dcc8c37075d16ff1f', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333832313038343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f34223b),
('ci_session:b202556770ec15165202e1b323af958d', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333535373137363b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:b4d3fd9601e67811dce2fe2078eb24b0', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333534373738373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:b8b5343a1775a2f341b153820fb776df', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333736343136313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:b8e51522d81dcfbdaae777006cdde622', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333634363433393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b),
('ci_session:b9f0a0bc7ce9613eeaec1ce0b15ccaca', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333936333432353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f34223b),
('ci_session:ba3b533b2661c2849d5f494db102e261', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333737303434383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34343a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f63686571756573223b),
('ci_session:bac4bcc0e1215996beccca0ef6aa98bb', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333634393837393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:bbb239a6687daa0ac3779d9e2c34ffa5', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333431363837373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33363a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e67223b5f5f63695f766172737c613a303a7b7d);
INSERT INTO `sessions` (`id`, `ip_address`, `timestamp`, `data`) VALUES
('ci_session:bbfbf16a1418d1e8859544fd53d993f7', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831393234313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f5f63695f766172737c613a303a7b7d5f63695f70726576696f75735f75726c7c733a34313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f76656e646f72732f31312f65646974223b),
('ci_session:bc11c78cdc89946a99f9286c9b1527f9', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333431343632353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c73223b5f5f63695f766172737c613a303a7b7d),
('ci_session:bc734d27e2d35698312e0c555531b070', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333936383431313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c73223b5f5f63695f766172737c613a303a7b7d),
('ci_session:bfdc5bff76ed89b592b3318dfb273c03', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831393932363b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f76656e646f72732f31312f65646974223b5f5f63695f766172737c613a303a7b7d),
('ci_session:bfe4f4f1f432cc98e07e378d7d4f393f', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831303837303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f33223b),
('ci_session:c058c58ce81acf09f45b135cc50b1ef9', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333431373936333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f637265617465223b5f5f63695f766172737c613a303a7b7d),
('ci_session:c10de696c7f4ea2acfc2fe3f5a3a43e1', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333431383738353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f637265617465223b5f5f63695f766172737c613a303a7b7d),
('ci_session:c10e260e085e8210c035cf53ca67ff8b', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333936363737393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b),
('ci_session:c1803e36b94a3aae948c351d4d7ed191', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333634373136393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e74732f686965726172636879223b5f5f63695f766172737c613a303a7b7d),
('ci_session:c2c89ffb0095144ce348327b5b46c1a9', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333830383037303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f33223b),
('ci_session:c2f19523b1b4e12e04b0fd443c366330', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831393234393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f76656e646f72732f31312f65646974223b5f5f63695f766172737c613a303a7b7d),
('ci_session:c3fa440d3e323fbd890ffa29cfb09320', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333939303432303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f31223b),
('ci_session:c48d504e70543773baff58829805cb97', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831343737393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6c6f6773223b),
('ci_session:c55d360c8b087dac51a372f687bfd779', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333635313038363b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f637265617465223b5f5f63695f766172737c613a303a7b7d),
('ci_session:c57e89beb0a8f71271494b2fabd75de3', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333736373138313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:c5f422ebf4d40abde763015711065008', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333535313932313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:c6b2f903efbd55d0fc44738947782168', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333431343934363b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33363a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e67223b5f5f63695f766172737c613a303a7b7d),
('ci_session:c7e99d1abda2726949f7ed5e6da47961', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333438303539383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f76696577223b),
('ci_session:ca5f039e8a2f3163626e1ddbeda609aa', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333736333739373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:cbb15f1782df6959c14f8d90af8da1a3', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333936373134343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f34223b),
('ci_session:cc778dc1dbfebd5951f8651314cb2e81', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831383933393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33363a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f76656e646f72732f3131223b5f5f63695f766172737c613a303a7b7d),
('ci_session:ccb23737d4f6a5f7804a794a2dad3e5c', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333939313231343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b5f5f63695f766172737c613a303a7b7d),
('ci_session:cd5f6cbde59ed2a20620c22e83ad4e03', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333736363431303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b),
('ci_session:cef54ded081fc08251300138999cb467', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831373338333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33343a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f73657474696e6773223b5f5f63695f766172737c613a303a7b7d),
('ci_session:cf79f4c758b3675fce66e97689257d82', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333830323339373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34343a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f63686571756573223b5f5f63695f766172737c613a303a7b7d),
('ci_session:cf853dc8a3473f328ce51ea8df588ce0', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333339323833323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b),
('ci_session:d0b45d7685afcae78bfffcc002c03a66', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333936383734383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f32223b),
('ci_session:d0be05843fbb9d922433cf8d7e924b3a', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333438333836353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c73223b5f5f63695f766172737c613a303a7b7d),
('ci_session:d15e27e6b079956809cbffeaee798558', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736343034393537373b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f31223b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b),
('ci_session:d2f27828240948a24b5b3da3def59832', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333939353535343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b),
('ci_session:d331479905a5ec00b450a1e75330eaa2', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736343034383038393b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f31223b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b),
('ci_session:d450b7cc2d8e998025e1e4502b952741', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333830343337323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34343a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f63686571756573223b5f5f63695f766172737c613a303a7b7d),
('ci_session:d5ad99f9a84f7145e61e00ad30611e16', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333438303033333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f76696577223b),
('ci_session:d6f215b9540f995bed2d1fb4ba88314c', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333735393435303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:d7c4be220a4080a92501618c34674a24', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333832333139333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34343a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f63686571756573223b5f5f63695f766172737c613a303a7b7d),
('ci_session:d916b6b9a90d7f57c2cbd9e8d41dc84b', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333431393134373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f637265617465223b5f5f63695f766172737c613a303a7b7d),
('ci_session:db9ee992adfe30e99cd95d0a94869a0c', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333737323139323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34343a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f63686571756573223b5f5f63695f766172737c613a303a7b7d),
('ci_session:dd24cdb9d6bdfa6634b2ccbd56a1ba25', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333938363030383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f31223b),
('ci_session:dde0cf4776c5e4430762d134bfdb23a6', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333431373137383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a33363a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f76656e646f72732f3131223b5f5f63695f766172737c613a303a7b7d),
('ci_session:de2639bdaac42a9a223fd112059a01ae', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333937303938343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b),
('ci_session:df3a94c2264df5dc4ee8c38cd68e9768', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333731343230313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f5f63695f766172737c613a303a7b7d5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b),
('ci_session:e107bd0e93d246678903bead71c2bcd2', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333936373637313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f34223b),
('ci_session:e2a00826d412b427f204b82ac91aeee6', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333736383136343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f747269616c2d62616c616e6365223b5f5f63695f766172737c613a303a7b7d),
('ci_session:e355430558c06536f6b086b591b831e1', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831383232353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f76656e646f72732f31312f65646974223b),
('ci_session:e397ab24d74e7228cbdb42ca4987e7eb', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333938393537383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b),
('ci_session:e39a260921aa6d85322cae729d3e5338', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333431323433383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b),
('ci_session:e6a0e8da96c6b4643b43bc3ec15b8244', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333431353532343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c73223b5f5f63695f766172737c613a303a7b7d),
('ci_session:e7a4e964b195ee4240dfeb866df7679f', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333938303531393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c73223b5f5f63695f766172737c613a303a7b7d),
('ci_session:e81c3afde587eb28619bc4b5a1a4797e', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333939323736323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b737563636573737c733a31353a224163636f756e742075706461746564223b5f5f63695f766172737c613a313a7b733a373a2273756363657373223b733a333a226f6c64223b7d),
('ci_session:e8f3c12d71ee3a5dec2172f233fd5d40', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333736393332383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35383a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f7265706f7274732f62616c616e63652d7368656574223b5f5f63695f766172737c613a303a7b7d),
('ci_session:eb2ebfd082abb41f0502836719b4fd59', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831303535313b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f33223b),
('ci_session:ecb05a0769635836033993569f2f0fa9', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333634313038373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35383a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f7265706f7274732f62616c616e63652d7368656574223b5f5f63695f766172737c613a303a7b7d),
('ci_session:eccdb58c28168cbe149dcb8aed306418', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333939323433373b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:ed01b228bf857d4b11ba293c567a8910', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333437333130343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f637265617465223b5f5f63695f766172737c613a303a7b7d),
('ci_session:edc75395bc052ec1cf5070110a60a059', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333534363737343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:edde40c087a55608ed09a2bef0a67041', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333937313636343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b),
('ci_session:f0400f921e2868c9e9f052047e810f33', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333830353532333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f322f706466223b),
('ci_session:f1978cb49f3ed8e5c0166988e59d34c9', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333736303433333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:f19d103127b3c40e5d9a52ef0460d452', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736343035363038303b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f31223b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b),
('ci_session:f275a670d1b8b66a9a4c2401a3b3b21f', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333736353830353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b),
('ci_session:f2c03a387d0cf1c18c4170d429515b90', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333635323331303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:f32493b997e1e6d7bde3f217dcac661b', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333939303931333b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f31223b),
('ci_session:f429fc03dbe6d35c5ce8b3b439e7e4e2', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333431383431303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35313a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f637265617465223b5f5f63695f766172737c613a303a7b7d),
('ci_session:f450f993ab8f7f0ff78b88bc56b64762', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333534383038393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:f4a8f8a884552780c9d82b0038485b0d', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333634363032353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b5f5f63695f766172737c613a303a7b7d),
('ci_session:f4ef46e15c00afbe27a7582ba83c497c', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333830373235303b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f33223b),
('ci_session:f5047a2b3f5d138099bc4024131dd4f9', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333438373330343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:f5ec62d24f73036937ba9ce495f1a7c4', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333938343039353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f31223b),
('ci_session:f633995779521c794ab51b4eb698cc2f', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736343034363134373b5f63695f70726576696f75735f75726c7c733a35353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c732f766f75636865722f31223b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b),
('ci_session:f9b05fb13ed6e2b8c406bae26a4c2f18', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333736393939363b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35383a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f7265706f7274732f62616c616e63652d7368656574223b),
('ci_session:fb6b3b3a42cff3a32a29b0712239f58b', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333737313730363b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c73223b5f5f63695f766172737c613a303a7b7d),
('ci_session:fc092a0acf91bfe391ddaf6788218b62', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333832313938393b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34373a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f342f706466223b),
('ci_session:fd37afb8dc345184da8f1f696cf5ecde', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333535363836323b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a35303a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f636865717565732f312f706466223b),
('ci_session:fe087a8be1cc945dee119ef33c6032a8', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333936363435353b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f34223b),
('ci_session:feaa8fd2685b63977f94b36857508682', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333938323935363b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f5f63695f766172737c613a303a7b7d5f63695f70726576696f75735f75726c7c733a34393a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6a6f75726e616c2d6c697465223b),
('ci_session:ff00bd3200b8e2ef38941c134c4792b0', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333939333630383b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34353a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f6163636f756e74696e672f6163636f756e7473223b737563636573737c733a31353a224163636f756e742063726561746564223b5f5f63695f766172737c613a313a7b733a373a2273756363657373223b733a333a226f6c64223b7d),
('ci_session:ff6371376b050663134e420054edb489', '127.0.0.1', 4294967295, 0x5f5f63695f6c6173745f726567656e65726174657c693a313736333831323735343b757365725f69647c693a313b757365726e616d657c733a343a2264656d6f223b656d61696c7c733a31363a2264656d6f406578616d706c652e636f6d223b726f6c657c733a353a2261646d696e223b66697273745f6e616d657c733a343a2244656d6f223b6c6173745f6e616d657c733a353a2241646d696e223b6c6f676765645f696e7c623a313b5f63695f70726576696f75735f75726c7c733a34333a22687474703a2f2f6c6f63616c686f73742f636f72656c796e6b2f72656365697074732f6368657175652f33223b);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(10) UNSIGNED NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `updated_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tax_codes`
--

CREATE TABLE `tax_codes` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(150) NOT NULL,
  `rate` decimal(9,4) NOT NULL DEFAULT 0.0000,
  `is_compound` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` enum('admin','planner','production','qc','stores','accounts','viewer') NOT NULL DEFAULT 'viewer',
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `first_name`, `last_name`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(2, 'admin', 'admin@admin.com', '$2y$10$8gSUVGvu6SULuIcKnVkGtuthBl1iFnozRswP9Po.irYLzh.UJeVB.', 'System', 'Administrator', 'admin', 1, NULL, '2025-10-07 18:04:23', '2025-10-07 18:04:23');

-- --------------------------------------------------------

--
-- Table structure for table `vendors`
--

CREATE TABLE `vendors` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendors`
--

INSERT INTO `vendors` (`id`, `name`, `contact_person`, `phone`, `email`, `address`, `is_active`, `created_at`, `updated_at`) VALUES
(3, 'Waqar Temp (HRC)', 'Hayat Khan', '', '', '', 1, '2025-11-02 15:11:23', '2025-11-02 15:11:23'),
(4, 'Daska Laser Cutting', 'Abdullah', '', '', '', 1, '2025-11-02 15:11:53', '2025-11-02 15:11:53'),
(5, 'Tevta Center - WB', '', '', '', '', 1, '2025-11-02 15:12:13', '2025-11-02 15:12:13'),
(6, 'Waqar (Color)', 'Shafi / Shoib', '', '', '', 1, '2025-11-02 15:12:40', '2025-11-22 09:12:59'),
(7, 'Iftekhar', 'Adil', '', '', '', 1, '2025-11-02 15:12:52', '2025-11-02 15:12:52'),
(8, 'Baber Bevel Maker', '', '', '', NULL, 1, '2025-11-02 17:33:53', '2025-11-02 17:33:53'),
(9, 'Jaber', '', '', '', '', 1, '2025-11-02 17:35:51', '2025-11-02 17:35:51'),
(10, 'Sajid', '', '', '', '', 1, '2025-11-10 07:36:07', '2025-11-10 07:36:07'),
(11, 'Test', '', '', '', '', 1, '2025-11-10 08:04:57', '2025-11-22 09:06:19'),
(12, 'Shah', '', '', '', NULL, 1, '2025-11-10 08:05:11', '2025-11-10 08:05:11');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_contacts`
--

CREATE TABLE `vendor_contacts` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `name` varchar(190) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `cnic` varchar(25) DEFAULT NULL,
  `email` varchar(190) DEFAULT NULL,
  `designation` varchar(100) DEFAULT NULL,
  `is_primary` tinyint(1) DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `vendor_contacts`
--

INSERT INTO `vendor_contacts` (`id`, `vendor_id`, `name`, `phone`, `cnic`, `email`, `designation`, `is_primary`, `notes`, `created_at`, `updated_at`) VALUES
(1, 4, 'Faiz', '', '', '', '', 0, '', '2025-11-22 13:51:23', '2025-11-22 13:51:23'),
(3, 6, 'Faiz', '', '', '', '', 0, '', '2025-11-22 14:11:48', '2025-11-22 14:12:55');

-- --------------------------------------------------------

--
-- Table structure for table `vendor_gatepasses`
--

CREATE TABLE `vendor_gatepasses` (
  `id` int(10) UNSIGNED NOT NULL,
  `gatepass_number` varchar(50) NOT NULL,
  `process_run_id` int(10) UNSIGNED NOT NULL,
  `vendor_id` int(10) UNSIGNED NOT NULL,
  `type` enum('out','in') NOT NULL,
  `quantity_sent` int(10) UNSIGNED DEFAULT 0,
  `quantity_received` int(10) UNSIGNED DEFAULT 0,
  `dispatch_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_payments`
--

CREATE TABLE `vendor_payments` (
  `id` int(11) NOT NULL,
  `vendor_id` int(11) NOT NULL,
  `cheque_id` int(11) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vendor_payment_allocations`
--

CREATE TABLE `vendor_payment_allocations` (
  `id` int(11) NOT NULL,
  `payment_id` int(11) NOT NULL,
  `purchase_order_id` int(11) NOT NULL,
  `amount` decimal(18,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `work_orders`
--

CREATE TABLE `work_orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `wo_number` varchar(50) NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `customer_name` varchar(100) NOT NULL,
  `quantity_ordered` int(10) UNSIGNED NOT NULL,
  `quantity_completed` int(10) UNSIGNED DEFAULT 0,
  `due_date` date NOT NULL,
  `status` enum('planned','in_progress','on_hold','completed','cancelled') DEFAULT 'planned',
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `notes` text DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `work_orders`
--

INSERT INTO `work_orders` (`id`, `wo_number`, `product_id`, `customer_name`, `quantity_ordered`, `quantity_completed`, `due_date`, `status`, `priority`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(18, 'S00077', 5, 'RI-79', 2000, 0, '2025-12-15', 'planned', 'high', '', NULL, '2025-11-04 14:05:18', '2025-11-04 14:05:18');

-- --------------------------------------------------------

--
-- Table structure for table `work_order_items`
--

CREATE TABLE `work_order_items` (
  `id` int(10) UNSIGNED NOT NULL,
  `work_order_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(10) UNSIGNED NOT NULL,
  `quantity_ordered` int(10) UNSIGNED NOT NULL,
  `quantity_completed` int(10) UNSIGNED DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `work_order_items`
--

INSERT INTO `work_order_items` (`id`, `work_order_id`, `product_id`, `quantity_ordered`, `quantity_completed`, `created_at`, `updated_at`) VALUES
(9, 18, 5, 2000, 0, '2025-11-04 09:05:18', '2025-11-04 09:05:18');

-- --------------------------------------------------------

--
-- Table structure for table `work_order_process_runs`
--

CREATE TABLE `work_order_process_runs` (
  `id` int(10) UNSIGNED NOT NULL,
  `work_order_id` int(10) UNSIGNED NOT NULL,
  `process_id` int(10) UNSIGNED NOT NULL,
  `run_number` int(11) NOT NULL DEFAULT 1,
  `quantity_in` int(10) UNSIGNED NOT NULL,
  `quantity_out` int(10) UNSIGNED DEFAULT 0,
  `quantity_scrap` int(10) UNSIGNED DEFAULT 0,
  `quantity_pending` int(10) UNSIGNED DEFAULT 0,
  `status` enum('pending','in_progress','completed','on_hold','cancelled') DEFAULT 'pending',
  `started_at` timestamp NULL DEFAULT NULL,
  `completed_at` timestamp NULL DEFAULT NULL,
  `operator_id` int(10) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `accounts`
--
ALTER TABLE `accounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_code` (`code`);

--
-- Indexes for table `cheques`
--
ALTER TABLE `cheques`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_bank` (`bank_account_id`),
  ADD KEY `idx_vendor` (`vendor_id`),
  ADD KEY `idx_date` (`cheque_date`);

--
-- Indexes for table `cheque_deletions`
--
ALTER TABLE `cheque_deletions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cheque` (`cheque_id`),
  ADD KEY `idx_deleted_at` (`deleted_at`);

--
-- Indexes for table `cheque_lines`
--
ALTER TABLE `cheque_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cheque` (`cheque_id`),
  ADD KEY `idx_account` (`account_id`);

--
-- Indexes for table `cheque_sequences`
--
ALTER TABLE `cheque_sequences`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `bank_account_id` (`bank_account_id`);

--
-- Indexes for table `company_settings`
--
ALTER TABLE `company_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `components`
--
ALTER TABLE `components`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `component_stock_transactions`
--
ALTER TABLE `component_stock_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `component_id` (`component_id`);

--
-- Indexes for table `component_usage`
--
ALTER TABLE `component_usage`
  ADD PRIMARY KEY (`id`),
  ADD KEY `work_order_id` (`work_order_id`),
  ADD KEY `component_id` (`component_id`);

--
-- Indexes for table `credit_notes`
--
ALTER TABLE `credit_notes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_party` (`party_type`,`party_id`);

--
-- Indexes for table `currencies`
--
ALTER TABLE `currencies`
  ADD PRIMARY KEY (`code`);

--
-- Indexes for table `employees`
--
ALTER TABLE `employees`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `employee_code` (`employee_code`);

--
-- Indexes for table `employee_skills`
--
ALTER TABLE `employee_skills`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `exchange_rate`
--
ALTER TABLE `exchange_rate`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pair_date` (`base_code`,`quote_code`,`as_of`);

--
-- Indexes for table `fiscal_year`
--
ALTER TABLE `fiscal_year`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `gate_passes`
--
ALTER TABLE `gate_passes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `journal_entries`
--
ALTER TABLE `journal_entries`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `journal_lines`
--
ALTER TABLE `journal_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_entry` (`entry_id`),
  ADD KEY `idx_account` (`account_id`);

--
-- Indexes for table `migrations`
--
ALTER TABLE `migrations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `payment_methods`
--
ALTER TABLE `payment_methods`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `processes`
--
ALTER TABLE `processes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `fk_processes_process_template_id` (`process_template_id`),
  ADD KEY `idx_processes_category` (`category_id`),
  ADD KEY `idx_processes_vendor` (`vendor_id`);

--
-- Indexes for table `process_batches`
--
ALTER TABLE `process_batches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `work_order_item_id` (`work_order_item_id`),
  ADD KEY `process_id` (`process_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_pb_vendor` (`vendor_id`);

--
-- Indexes for table `process_batch_employees`
--
ALTER TABLE `process_batch_employees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `batch_id` (`batch_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `process_batch_logs`
--
ALTER TABLE `process_batch_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `process_batch_id` (`process_batch_id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `operator_id` (`operator_id`);

--
-- Indexes for table `process_batch_releases`
--
ALTER TABLE `process_batch_releases`
  ADD PRIMARY KEY (`id`),
  ADD KEY `process_batch_id` (`process_batch_id`),
  ADD KEY `released_by` (`released_by`);

--
-- Indexes for table `process_categories`
--
ALTER TABLE `process_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_process_categories_name` (`name`);

--
-- Indexes for table `process_employee_assignments`
--
ALTER TABLE `process_employee_assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `process_id` (`process_id`),
  ADD KEY `employee_id` (`employee_id`);

--
-- Indexes for table `process_templates`
--
ALTER TABLE `process_templates`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `vendor_id` (`vendor_id`),
  ADD KEY `idx_process_templates_name` (`name`);

--
-- Indexes for table `process_vendors`
--
ALTER TABLE `process_vendors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_process_vendor` (`process_id`,`vendor_id`),
  ADD KEY `idx_pv_process` (`process_id`),
  ADD KEY `idx_pv_vendor` (`vendor_id`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `idx_products_code` (`code`);

--
-- Indexes for table `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `product_processes`
--
ALTER TABLE `product_processes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product_processes_product` (`product_id`),
  ADD KEY `idx_product_processes_template` (`process_template_id`),
  ADD KEY `idx_product_processes_process` (`process_id`),
  ADD KEY `idx_product_processes_sequence` (`product_id`,`sequence_order`);

--
-- Indexes for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vendor` (`vendor_id`);

--
-- Indexes for table `purchase_order_lines`
--
ALTER TABLE `purchase_order_lines`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_po` (`po_id`);

--
-- Indexes for table `qc_records`
--
ALTER TABLE `qc_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `rework_records`
--
ALTER TABLE `rework_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `scrap_records`
--
ALTER TABLE `scrap_records`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `security_settings`
--
ALTER TABLE `security_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sessions`
--
ALTER TABLE `sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ci_sessions_timestamp` (`timestamp`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`);

--
-- Indexes for table `tax_codes`
--
ALTER TABLE `tax_codes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_users_role` (`role`);

--
-- Indexes for table `vendors`
--
ALTER TABLE `vendors`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `vendor_contacts`
--
ALTER TABLE `vendor_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vendor` (`vendor_id`);

--
-- Indexes for table `vendor_gatepasses`
--
ALTER TABLE `vendor_gatepasses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `gatepass_number` (`gatepass_number`);

--
-- Indexes for table `vendor_payments`
--
ALTER TABLE `vendor_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_vendor` (`vendor_id`);

--
-- Indexes for table `vendor_payment_allocations`
--
ALTER TABLE `vendor_payment_allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_payment` (`payment_id`),
  ADD KEY `idx_po` (`purchase_order_id`);

--
-- Indexes for table `work_orders`
--
ALTER TABLE `work_orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `wo_number` (`wo_number`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_work_orders_status` (`status`);

--
-- Indexes for table `work_order_items`
--
ALTER TABLE `work_order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `work_order_id` (`work_order_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Indexes for table `work_order_process_runs`
--
ALTER TABLE `work_order_process_runs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `work_order_id` (`work_order_id`),
  ADD KEY `process_id` (`process_id`),
  ADD KEY `operator_id` (`operator_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `accounts`
--
ALTER TABLE `accounts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `cheques`
--
ALTER TABLE `cheques`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cheque_deletions`
--
ALTER TABLE `cheque_deletions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `cheque_lines`
--
ALTER TABLE `cheque_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cheque_sequences`
--
ALTER TABLE `cheque_sequences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `company_settings`
--
ALTER TABLE `company_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `components`
--
ALTER TABLE `components`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `component_stock_transactions`
--
ALTER TABLE `component_stock_transactions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `component_usage`
--
ALTER TABLE `component_usage`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `credit_notes`
--
ALTER TABLE `credit_notes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employees`
--
ALTER TABLE `employees`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `employee_skills`
--
ALTER TABLE `employee_skills`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `exchange_rate`
--
ALTER TABLE `exchange_rate`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `fiscal_year`
--
ALTER TABLE `fiscal_year`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `gate_passes`
--
ALTER TABLE `gate_passes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `journal_entries`
--
ALTER TABLE `journal_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `journal_lines`
--
ALTER TABLE `journal_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `migrations`
--
ALTER TABLE `migrations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `payment_methods`
--
ALTER TABLE `payment_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `processes`
--
ALTER TABLE `processes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `process_batches`
--
ALTER TABLE `process_batches`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=86;

--
-- AUTO_INCREMENT for table `process_batch_employees`
--
ALTER TABLE `process_batch_employees`
  MODIFY `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `process_batch_logs`
--
ALTER TABLE `process_batch_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `process_batch_releases`
--
ALTER TABLE `process_batch_releases`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `process_categories`
--
ALTER TABLE `process_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `process_employee_assignments`
--
ALTER TABLE `process_employee_assignments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `process_templates`
--
ALTER TABLE `process_templates`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `process_vendors`
--
ALTER TABLE `process_vendors`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=28;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `product_processes`
--
ALTER TABLE `product_processes`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `purchase_orders`
--
ALTER TABLE `purchase_orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `purchase_order_lines`
--
ALTER TABLE `purchase_order_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `qc_records`
--
ALTER TABLE `qc_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rework_records`
--
ALTER TABLE `rework_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `scrap_records`
--
ALTER TABLE `scrap_records`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `security_settings`
--
ALTER TABLE `security_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tax_codes`
--
ALTER TABLE `tax_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `vendors`
--
ALTER TABLE `vendors`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `vendor_contacts`
--
ALTER TABLE `vendor_contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `vendor_gatepasses`
--
ALTER TABLE `vendor_gatepasses`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_payments`
--
ALTER TABLE `vendor_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `vendor_payment_allocations`
--
ALTER TABLE `vendor_payment_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `work_orders`
--
ALTER TABLE `work_orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `work_order_items`
--
ALTER TABLE `work_order_items`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `work_order_process_runs`
--
ALTER TABLE `work_order_process_runs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `component_stock_transactions`
--
ALTER TABLE `component_stock_transactions`
  ADD CONSTRAINT `component_stock_transactions_ibfk_1` FOREIGN KEY (`component_id`) REFERENCES `components` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `component_usage`
--
ALTER TABLE `component_usage`
  ADD CONSTRAINT `component_usage_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `component_usage_ibfk_2` FOREIGN KEY (`component_id`) REFERENCES `components` (`id`);

--
-- Constraints for table `employee_skills`
--
ALTER TABLE `employee_skills`
  ADD CONSTRAINT `employee_skills_ibfk_1` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `processes`
--
ALTER TABLE `processes`
  ADD CONSTRAINT `fk_processes_process_template_id` FOREIGN KEY (`process_template_id`) REFERENCES `process_templates` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_processes_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `processes_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `processes_ibfk_3` FOREIGN KEY (`category_id`) REFERENCES `process_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `process_batches`
--
ALTER TABLE `process_batches`
  ADD CONSTRAINT `fk_pb_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `process_batches_ibfk_1` FOREIGN KEY (`work_order_item_id`) REFERENCES `work_order_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `process_batches_ibfk_2` FOREIGN KEY (`process_id`) REFERENCES `processes` (`id`),
  ADD CONSTRAINT `process_batches_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `process_batch_logs`
--
ALTER TABLE `process_batch_logs`
  ADD CONSTRAINT `process_batch_logs_ibfk_1` FOREIGN KEY (`process_batch_id`) REFERENCES `process_batches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `process_batch_logs_ibfk_2` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `process_batch_logs_ibfk_3` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `process_batch_logs_ibfk_4` FOREIGN KEY (`operator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `process_batch_releases`
--
ALTER TABLE `process_batch_releases`
  ADD CONSTRAINT `process_batch_releases_ibfk_1` FOREIGN KEY (`process_batch_id`) REFERENCES `process_batches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `process_batch_releases_ibfk_2` FOREIGN KEY (`released_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `process_templates`
--
ALTER TABLE `process_templates`
  ADD CONSTRAINT `process_templates_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `process_categories` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `process_templates_ibfk_2` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `process_vendors`
--
ALTER TABLE `process_vendors`
  ADD CONSTRAINT `fk_pv_process` FOREIGN KEY (`process_id`) REFERENCES `processes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_pv_vendor` FOREIGN KEY (`vendor_id`) REFERENCES `vendors` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `product_categories` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_processes`
--
ALTER TABLE `product_processes`
  ADD CONSTRAINT `product_processes_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_processes_ibfk_2` FOREIGN KEY (`process_template_id`) REFERENCES `process_templates` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_processes_ibfk_3` FOREIGN KEY (`process_id`) REFERENCES `processes` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `work_orders`
--
ALTER TABLE `work_orders`
  ADD CONSTRAINT `work_orders_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`),
  ADD CONSTRAINT `work_orders_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `work_order_items`
--
ALTER TABLE `work_order_items`
  ADD CONSTRAINT `work_order_items_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `work_order_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Constraints for table `work_order_process_runs`
--
ALTER TABLE `work_order_process_runs`
  ADD CONSTRAINT `work_order_process_runs_ibfk_1` FOREIGN KEY (`work_order_id`) REFERENCES `work_orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `work_order_process_runs_ibfk_2` FOREIGN KEY (`process_id`) REFERENCES `processes` (`id`),
  ADD CONSTRAINT `work_order_process_runs_ibfk_3` FOREIGN KEY (`operator_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
