-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Sep 20, 2026 at 02:16 AM
-- Server version: 10.6.28-MariaDB-log
-- PHP Version: 8.4.24

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `madahino_madahi`
--

-- --------------------------------------------------------

--
-- Table structure for table `football_age_groups`
--

CREATE TABLE `football_age_groups` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(100) NOT NULL,
  `birth_date_from` date NOT NULL,
  `birth_date_to` date NOT NULL,
  `min_age_at_cutoff` tinyint(3) UNSIGNED DEFAULT NULL,
  `max_age_at_cutoff` tinyint(3) UNSIGNED DEFAULT NULL,
  `sort_order` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `football_age_groups`
--

INSERT INTO `football_age_groups` (`id`, `title`, `birth_date_from`, `birth_date_to`, `min_age_at_cutoff`, `max_age_at_cutoff`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
(1, 'زیر ۹ سال', '2018-01-01', '2019-01-01', NULL, NULL, 0, 'active', '2026-09-12 12:42:25', '2026-09-12 12:42:39'),
(2, 'زیر 11', '2016-01-01', '2016-12-31', NULL, NULL, 0, 'active', '2026-09-12 12:59:35', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `football_attendances`
--

CREATE TABLE `football_attendances` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `session_id` bigint(20) UNSIGNED NOT NULL,
  `player_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL,
  `is_billable` tinyint(1) NOT NULL DEFAULT 1,
  `note` text DEFAULT NULL,
  `recorded_by` bigint(20) UNSIGNED NOT NULL,
  `recorded_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_audit_logs`
--

CREATE TABLE `football_audit_logs` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(100) NOT NULL,
  `entity_id` bigint(20) UNSIGNED DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_chat_messages`
--

CREATE TABLE `football_chat_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `chat_room_id` bigint(20) UNSIGNED NOT NULL,
  `sender_id` bigint(20) UNSIGNED NOT NULL,
  `message_type` varchar(20) NOT NULL DEFAULT 'text',
  `body` text DEFAULT NULL,
  `media_id` bigint(20) UNSIGNED DEFAULT NULL,
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  `edited_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_chat_rooms`
--

CREATE TABLE `football_chat_rooms` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `room_type` varchar(30) NOT NULL,
  `player_id` bigint(20) UNSIGNED DEFAULT NULL,
  `class_id` bigint(20) UNSIGNED DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `unique_key` char(64) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_chat_room_members`
--

CREATE TABLE `football_chat_room_members` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `chat_room_id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `member_role` varchar(20) NOT NULL DEFAULT 'member',
  `joined_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_read_message_id` bigint(20) UNSIGNED DEFAULT NULL,
  `is_muted` tinyint(1) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_classes`
--

CREATE TABLE `football_classes` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `season_id` bigint(20) UNSIGNED DEFAULT NULL,
  `age_group_id` bigint(20) UNSIGNED DEFAULT NULL,
  `coach_id` bigint(20) UNSIGNED DEFAULT NULL,
  `assistant_coach_id` bigint(20) UNSIGNED DEFAULT NULL,
  `capacity` int(10) UNSIGNED DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `location` varchar(255) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `pricing_type` varchar(20) NOT NULL DEFAULT 'monthly',
  `monthly_fee` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `session_fee` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `registration_fee` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_class_schedules`
--

CREATE TABLE `football_class_schedules` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `class_id` bigint(20) UNSIGNED NOT NULL,
  `weekday` tinyint(3) UNSIGNED NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_coaches`
--

CREATE TABLE `football_coaches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `specialty` varchar(150) DEFAULT NULL,
  `license_level` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_credit_transactions`
--

CREATE TABLE `football_credit_transactions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `player_id` bigint(20) UNSIGNED NOT NULL,
  `amount` bigint(20) NOT NULL,
  `transaction_type` varchar(20) NOT NULL,
  `payment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `invoice_id` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `football_device_tokens`
--

CREATE TABLE `football_device_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `platform` varchar(20) NOT NULL,
  `token` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_discounts`
--

CREATE TABLE `football_discounts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `discount_type` varchar(20) NOT NULL,
  `value` bigint(20) UNSIGNED NOT NULL,
  `applies_to` varchar(20) NOT NULL DEFAULT 'any',
  `auto_apply` tinyint(1) NOT NULL DEFAULT 0,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `description` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_discount_targets`
--

CREATE TABLE `football_discount_targets` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `discount_id` bigint(20) UNSIGNED NOT NULL,
  `target_type` varchar(20) NOT NULL,
  `target_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_enrollments`
--

CREATE TABLE `football_enrollments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `class_id` bigint(20) UNSIGNED NOT NULL,
  `player_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `enrolled_at` date NOT NULL,
  `ended_at` date DEFAULT NULL,
  `monthly_fee_override` bigint(20) UNSIGNED DEFAULT NULL,
  `session_fee_override` bigint(20) UNSIGNED DEFAULT NULL,
  `registration_fee_override` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_evaluations`
--

CREATE TABLE `football_evaluations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `session_id` bigint(20) UNSIGNED DEFAULT NULL,
  `player_id` bigint(20) UNSIGNED NOT NULL,
  `coach_id` bigint(20) UNSIGNED NOT NULL,
  `evaluation_type` varchar(20) NOT NULL DEFAULT 'session',
  `technical_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `discipline_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `physical_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `teamwork_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `overall_score` tinyint(3) UNSIGNED DEFAULT NULL,
  `strengths` text DEFAULT NULL,
  `weaknesses` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_guardians`
--

CREATE TABLE `football_guardians` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `national_code` char(10) DEFAULT NULL,
  `address` varchar(500) DEFAULT NULL,
  `emergency_phone` varchar(20) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_guardians_players`
--

CREATE TABLE `football_guardians_players` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `guardian_id` bigint(20) UNSIGNED NOT NULL,
  `player_id` bigint(20) UNSIGNED NOT NULL,
  `relation` varchar(20) DEFAULT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT 0,
  `can_view_reports` tinyint(1) NOT NULL DEFAULT 1,
  `can_pay` tinyint(1) NOT NULL DEFAULT 1,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_installments`
--

CREATE TABLE `football_installments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_id` bigint(20) UNSIGNED NOT NULL,
  `installment_number` smallint(5) UNSIGNED NOT NULL,
  `amount` bigint(20) UNSIGNED NOT NULL,
  `paid_amount` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `due_date` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_invoices`
--

CREATE TABLE `football_invoices` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_number` varchar(30) NOT NULL,
  `player_id` bigint(20) UNSIGNED NOT NULL,
  `season_id` bigint(20) UNSIGNED DEFAULT NULL,
  `invoice_type` varchar(20) NOT NULL,
  `period_start_date` date DEFAULT NULL,
  `period_end_date` date DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `due_date` date DEFAULT NULL,
  `subtotal` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `discount_total` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `paid_total` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `remaining_total` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_invoice_discounts`
--

CREATE TABLE `football_invoice_discounts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_id` bigint(20) UNSIGNED NOT NULL,
  `discount_id` bigint(20) UNSIGNED DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `discount_type` varchar(20) NOT NULL,
  `value` bigint(20) UNSIGNED NOT NULL,
  `amount` bigint(20) UNSIGNED NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_invoice_items`
--

CREATE TABLE `football_invoice_items` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `invoice_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `item_type` varchar(30) NOT NULL,
  `amount` bigint(20) UNSIGNED NOT NULL,
  `quantity` smallint(5) UNSIGNED NOT NULL DEFAULT 1,
  `total` bigint(20) UNSIGNED NOT NULL,
  `class_id` bigint(20) UNSIGNED DEFAULT NULL,
  `session_id` bigint(20) UNSIGNED DEFAULT NULL,
  `attendance_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_login_attempts`
--

CREATE TABLE `football_login_attempts` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `identifier` varchar(30) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `was_successful` tinyint(1) NOT NULL DEFAULT 0,
  `failure_reason` varchar(40) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_matches`
--

CREATE TABLE `football_matches` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `match_type` varchar(30) NOT NULL,
  `class_id` bigint(20) UNSIGNED DEFAULT NULL,
  `age_group_id` bigint(20) UNSIGNED DEFAULT NULL,
  `opponent_team` varchar(255) DEFAULT NULL,
  `match_date` date NOT NULL,
  `match_time` time NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'planned',
  `home_score` smallint(5) UNSIGNED DEFAULT NULL,
  `away_score` smallint(5) UNSIGNED DEFAULT NULL,
  `result` varchar(10) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_match_players`
--

CREATE TABLE `football_match_players` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `match_id` bigint(20) UNSIGNED NOT NULL,
  `player_id` bigint(20) UNSIGNED NOT NULL,
  `invitation_status` varchar(20) NOT NULL DEFAULT 'invited',
  `attendance_status` varchar(20) DEFAULT NULL,
  `jersey_number` smallint(5) UNSIGNED DEFAULT NULL,
  `position` varchar(50) DEFAULT NULL,
  `goals` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `assists` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `yellow_cards` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `red_cards` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `minutes_played` smallint(5) UNSIGNED DEFAULT NULL,
  `rating` decimal(3,1) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_media`
--

CREATE TABLE `football_media` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `uploader_id` bigint(20) UNSIGNED NOT NULL,
  `file_type` varchar(20) NOT NULL,
  `mime_type` varchar(150) NOT NULL,
  `original_name` varchar(255) DEFAULT NULL,
  `stored_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `thumbnail_path` varchar(500) DEFAULT NULL,
  `size_bytes` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `duration_seconds` int(10) UNSIGNED DEFAULT NULL,
  `visibility` varchar(20) NOT NULL DEFAULT 'private',
  `related_type` varchar(30) DEFAULT NULL,
  `related_id` bigint(20) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_media_audiences`
--

CREATE TABLE `football_media_audiences` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `media_id` bigint(20) UNSIGNED NOT NULL,
  `audience_type` varchar(20) NOT NULL,
  `target_id` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_news`
--

CREATE TABLE `football_news` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'draft',
  `publish_at` datetime DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_news_audiences`
--

CREATE TABLE `football_news_audiences` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `news_id` bigint(20) UNSIGNED NOT NULL,
  `audience_type` varchar(20) NOT NULL,
  `role` varchar(20) DEFAULT NULL,
  `target_id` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_notifications`
--

CREATE TABLE `football_notifications` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `body` text DEFAULT NULL,
  `type` varchar(30) NOT NULL DEFAULT 'info',
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_payments`
--

CREATE TABLE `football_payments` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `payment_number` varchar(30) NOT NULL,
  `player_id` bigint(20) UNSIGNED NOT NULL,
  `invoice_id` bigint(20) UNSIGNED DEFAULT NULL,
  `installment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `amount` bigint(20) UNSIGNED NOT NULL,
  `allocated_total` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `payment_method` varchar(30) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'pending',
  `paid_at` datetime DEFAULT NULL,
  `receipt_media_id` bigint(20) UNSIGNED DEFAULT NULL,
  `gateway_name` varchar(50) DEFAULT NULL,
  `gateway_reference` varchar(100) DEFAULT NULL,
  `gateway_status` varchar(30) DEFAULT NULL,
  `confirmed_by` bigint(20) UNSIGNED DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `football_payment_allocations`
--

CREATE TABLE `football_payment_allocations` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `payment_id` bigint(20) UNSIGNED NOT NULL,
  `invoice_id` bigint(20) UNSIGNED NOT NULL,
  `installment_id` bigint(20) UNSIGNED DEFAULT NULL,
  `amount` bigint(20) UNSIGNED NOT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `football_players`
--

CREATE TABLE `football_players` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED DEFAULT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `national_code` char(10) DEFAULT NULL,
  `password_hash` varchar(200) NOT NULL,
  `birth_date` date NOT NULL,
  `gender` varchar(10) DEFAULT NULL,
  `avatar_path` varchar(500) DEFAULT NULL,
  `medical_notes` text DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `deleted_seq` bigint(20) UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_player_seasons`
--

CREATE TABLE `football_player_seasons` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `player_id` bigint(20) UNSIGNED NOT NULL,
  `season_id` bigint(20) UNSIGNED NOT NULL,
  `age_group_id` bigint(20) UNSIGNED NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `assigned_by` bigint(20) UNSIGNED DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_seasons`
--

CREATE TABLE `football_seasons` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(100) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `age_cutoff_date` date NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'inactive',
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_sequences`
--

CREATE TABLE `football_sequences` (
  `name` varchar(30) NOT NULL,
  `period` varchar(10) NOT NULL DEFAULT '',
  `prefix` varchar(10) NOT NULL DEFAULT '',
  `padding` tinyint(3) UNSIGNED NOT NULL DEFAULT 6,
  `current_value` bigint(20) UNSIGNED NOT NULL DEFAULT 0,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_sessions`
--

CREATE TABLE `football_sessions` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `class_id` bigint(20) UNSIGNED NOT NULL,
  `session_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'scheduled',
  `topic` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_settings`
--

CREATE TABLE `football_settings` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `value_type` varchar(20) NOT NULL DEFAULT 'string',
  `description` text DEFAULT NULL,
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `football_users`
--

CREATE TABLE `football_users` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `national_code` char(10) DEFAULT NULL,
  `avatar_path` text NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'active',
  `must_change_password` tinyint(1) NOT NULL DEFAULT 1,
  `failed_login_count` smallint(5) UNSIGNED NOT NULL DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `created_by` bigint(20) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `deleted_at` datetime DEFAULT NULL,
  `deleted_seq` bigint(20) UNSIGNED NOT NULL DEFAULT 0
) ;

-- --------------------------------------------------------

--
-- Table structure for table `football_user_tokens`
--

CREATE TABLE `football_user_tokens` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `user_id` bigint(20) UNSIGNED NOT NULL,
  `token_hash` char(64) NOT NULL,
  `device_name` varchar(150) DEFAULT NULL,
  `platform` varchar(20) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `football_age_groups`
--
ALTER TABLE `football_age_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_football_age_groups_status` (`status`),
  ADD KEY `idx_football_age_groups_birth_dates` (`birth_date_from`,`birth_date_to`);

--
-- Indexes for table `football_attendances`
--
ALTER TABLE `football_attendances`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_football_attendances_session_player` (`session_id`,`player_id`),
  ADD KEY `idx_football_attendances_player_id` (`player_id`),
  ADD KEY `idx_football_attendances_recorded_by` (`recorded_by`),
  ADD KEY `idx_football_attendances_status` (`status`);

--
-- Indexes for table `football_audit_logs`
--
ALTER TABLE `football_audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_football_audit_logs_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_football_audit_logs_user_id` (`user_id`),
  ADD KEY `idx_football_audit_logs_created_at` (`created_at`);

--
-- Indexes for table `football_chat_messages`
--
ALTER TABLE `football_chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_football_chat_messages_room_sent` (`chat_room_id`,`sent_at`),
  ADD KEY `idx_football_chat_messages_sender_id` (`sender_id`),
  ADD KEY `idx_football_chat_messages_media_id` (`media_id`);

--
-- Indexes for table `football_chat_rooms`
--
ALTER TABLE `football_chat_rooms`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_football_chat_rooms_unique_key` (`unique_key`),
  ADD KEY `idx_football_chat_rooms_player_id` (`player_id`),
  ADD KEY `idx_football_chat_rooms_class_id` (`class_id`),
  ADD KEY `idx_football_chat_rooms_created_by` (`created_by`),
  ADD KEY `idx_football_chat_rooms_type_status` (`room_type`,`status`);

--
-- Indexes for table `football_chat_room_members`
--
ALTER TABLE `football_chat_room_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_football_chat_room_members_room_user` (`chat_room_id`,`user_id`),
  ADD KEY `idx_football_chat_room_members_user_id` (`user_id`),
  ADD KEY `idx_football_chat_room_members_last_read_message_id` (`last_read_message_id`);

--
-- Indexes for table `football_classes`
--
ALTER TABLE `football_classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_football_classes_season_id` (`season_id`),
  ADD KEY `idx_football_classes_age_group_id` (`age_group_id`),
  ADD KEY `idx_football_classes_coach_id` (`coach_id`),
  ADD KEY `idx_football_classes_assistant_coach_id` (`assistant_coach_id`),
  ADD KEY `idx_football_classes_status` (`status`),
  ADD KEY `idx_football_classes_created_by` (`created_by`);

--
-- Indexes for table `football_class_schedules`
--
ALTER TABLE `football_class_schedules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_football_class_schedules_class_weekday_time` (`class_id`,`weekday`,`start_time`),
  ADD KEY `idx_football_class_schedules_class_weekday` (`class_id`,`weekday`);

--
-- Indexes for table `football_coaches`
--
ALTER TABLE `football_coaches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_football_coaches_user_id` (`user_id`);

--
-- Indexes for table `football_credit_transactions`
--
ALTER TABLE `football_credit_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_football_credit_transactions_player_id` (`player_id`),
  ADD KEY `idx_football_credit_transactions_payment_id` (`payment_id`),
  ADD KEY `idx_football_credit_transactions_invoice_id` (`invoice_id`),
  ADD KEY `idx_football_credit_transactions_type` (`transaction_type`),
  ADD KEY `idx_football_credit_transactions_created_by` (`created_by`);

--
-- Indexes for table `football_device_tokens`
--
ALTER TABLE `football_device_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_football_device_tokens_token` (`token`),
  ADD KEY `idx_football_device_tokens_user_id` (`user_id`),
  ADD KEY `idx_football_device_tokens_platform_active` (`platform`,`is_active`);

--
-- Indexes for table `football_discounts`
--
ALTER TABLE `football_discounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_football_discounts_status` (`status`),
  ADD KEY `idx_football_discounts_dates` (`start_date`,`end_date`),
  ADD KEY `idx_football_discounts_created_by` (`created_by`);

--
-- Indexes for table `football_discount_targets`
--
ALTER TABLE `football_discount_targets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_football_discount_targets_discount_id` (`discount_id`),
  ADD KEY `idx_football_discount_targets_target` (`target_type`,`target_id`);

--
-- Indexes for table `football_enrollments`
--
ALTER TABLE `football_enrollments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_football_enrollments_class_player` (`class_id`,`player_id`),
  ADD KEY `idx_football_enrollments_player_status` (`player_id`,`status`),
  ADD KEY `idx_football_enrollments_class_status` (`class_id`,`status`),
  ADD KEY `idx_football_enrollments_created_by` (`created_by`);

--
-- Indexes for table `football_evaluations`
--
ALTER TABLE `football_evaluations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_football_evaluations_session_player_type` (`session_id`,`player_id`,`evaluation_type`),
  ADD KEY `idx_football_evaluations_player_id` (`player_id`),
  ADD KEY `idx_football_evaluations_session_player` (`session_id`,`player_id`),
  ADD KEY `idx_football_evaluations_coach_id` (`coach_id`);

--
-- Indexes for table `football_guardians`
--
ALTER TABLE `football_guardians`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `football_guardians_players`
--
ALTER TABLE `football_guardians_players`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_football_guardians_players_guardian_player` (`guardian_id`,`player_id`),
  ADD KEY `idx_football_guardians_players_player_id` (`player_id`),
  ADD KEY `idx_football_guardians_players_status` (`status`);

--
-- Indexes for table `football_installments`
--
ALTER TABLE `football_installments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_football_installments_invoice_number` (`invoice_id`,`installment_number`),
  ADD KEY `idx_football_installments_due_status` (`due_date`,`status`);

--
-- Indexes for table `football_invoices`
--
ALTER TABLE `football_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_football_invoices_invoice_number` (`invoice_number`),
  ADD KEY `idx_football_invoices_player_status` (`player_id`,`status`),
  ADD KEY `idx_football_invoices_due_status` (`due_date`,`status`),
  ADD KEY `idx_football_invoices_season_id` (`season_id`),
  ADD KEY `idx_football_invoices_created_by` (`created_by`);

--
-- Indexes for table `football_invoice_discounts`
--
ALTER TABLE `football_invoice_discounts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_football_invoice_discounts_invoice_id` (`invoice_id`),
  ADD KEY `idx_football_invoice_discounts_discount_id` (`discount_id`);

--
-- Indexes for table `football_invoice_items`
--
ALTER TABLE `football_invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_football_invoice_items_invoice_id` (`invoice_id`),
  ADD KEY `idx_football_invoice_items_class_id` (`class_id`),
  ADD KEY `idx_football_invoice_items_session_id` (`session_id`),
  ADD KEY `idx_football_invoice_items_attendance_id` (`attendance_id`);

--
-- Indexes for table `football_login_attempts`
--
ALTER TABLE `football_login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_football_login_attempts_identifier_time` (`identifier`,`created_at`),
  ADD KEY `idx_football_login_attempts_ip_time` (`ip_address`,`created_at`),
  ADD KEY `idx_football_login_attempts_user_id` (`user_id`);

--
-- Indexes for table `football_matches`
--
ALTER TABLE `football_matches`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_football_matches_date_status` (`match_date`,`status`),
  ADD KEY `idx_football_matches_class_id` (`class_id`),
  ADD KEY `idx_football_matches_age_group_id` (`age_group_id`),
  ADD KEY `idx_football_matches_created_by` (`created_by`);

--
-- Indexes for table `football_match_players`
--
ALTER TABLE `football_match_players`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_football_match_players_match_player` (`match_id`,`player_id`),
  ADD KEY `idx_football_match_players_player_id` (`player_id`);

--
-- Indexes for table `football_media`
--
ALTER TABLE `football_media`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_football_media_uploader_id` (`uploader_id`),
  ADD KEY `idx_football_media_related` (`related_type`,`related_id`),
  ADD KEY `idx_football_media_visibility_status` (`visibility`,`status`);

--
-- Indexes for table `football_media_audiences`
--
ALTER TABLE `football_media_audiences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_football_media_audiences_media_id` (`media_id`),
  ADD KEY `idx_football_media_audiences_audience` (`audience_type`,`target_id`);

--
-- Indexes for table `football_news`
--
ALTER TABLE `football_news`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_football_news_status_publish` (`status`,`publish_at`),
  ADD KEY `idx_football_news_created_by` (`created_by`);

--
-- Indexes for table `football_news_audiences`
--
ALTER TABLE `football_news_audiences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_football_news_audiences_news_id` (`news_id`),
  ADD KEY `idx_football_news_audiences_audience` (`audience_type`,`target_id`),
  ADD KEY `idx_football_news_audiences_role` (`role`);

--
-- Indexes for table `football_notifications`
--
ALTER TABLE `football_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_football_notifications_user_read` (`user_id`,`is_read`),
  ADD KEY `idx_football_notifications_type` (`type`);

--
-- Indexes for table `football_payments`
--
ALTER TABLE `football_payments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_football_payments_payment_number` (`payment_number`),
  ADD KEY `idx_football_payments_player_status` (`player_id`,`status`),
  ADD KEY `idx_football_payments_receipt_media_id` (`receipt_media_id`),
  ADD KEY `idx_football_payments_confirmed_by` (`confirmed_by`),
  ADD KEY `idx_football_payments_created_by` (`created_by`),
  ADD KEY `idx_football_payments_invoice_id` (`invoice_id`),
  ADD KEY `idx_football_payments_installment_id` (`installment_id`);

--
-- Indexes for table `football_payment_allocations`
--
ALTER TABLE `football_payment_allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_football_payment_allocations_payment_id` (`payment_id`),
  ADD KEY `idx_football_payment_allocations_invoice_id` (`invoice_id`),
  ADD KEY `idx_football_payment_allocations_installment_id` (`installment_id`),
  ADD KEY `idx_football_payment_allocations_created_by` (`created_by`);

--
-- Indexes for table `football_players`
--
ALTER TABLE `football_players`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_football_players_national_code` (`national_code`,`deleted_seq`),
  ADD UNIQUE KEY `uq_football_players_user_id` (`user_id`),
  ADD KEY `idx_football_players_birth_date` (`birth_date`),
  ADD KEY `idx_football_players_status` (`status`),
  ADD KEY `idx_football_players_created_by` (`created_by`);

--
-- Indexes for table `football_player_seasons`
--
ALTER TABLE `football_player_seasons`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_football_player_seasons_player_season` (`player_id`,`season_id`),
  ADD KEY `idx_football_player_seasons_age_group_id` (`age_group_id`),
  ADD KEY `idx_football_player_seasons_assigned_by` (`assigned_by`),
  ADD KEY `season_id` (`season_id`);

--
-- Indexes for table `football_seasons`
--
ALTER TABLE `football_seasons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_football_seasons_status` (`status`),
  ADD KEY `idx_football_seasons_created_by` (`created_by`);

--
-- Indexes for table `football_sequences`
--
ALTER TABLE `football_sequences`
  ADD PRIMARY KEY (`name`,`period`);

--
-- Indexes for table `football_sessions`
--
ALTER TABLE `football_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_football_sessions_class_date_time` (`class_id`,`session_date`,`start_time`),
  ADD KEY `idx_football_sessions_class_date_status` (`class_id`,`session_date`,`status`),
  ADD KEY `idx_football_sessions_status` (`status`),
  ADD KEY `idx_football_sessions_created_by` (`created_by`);

--
-- Indexes for table `football_settings`
--
ALTER TABLE `football_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_football_settings_setting_key` (`setting_key`),
  ADD KEY `idx_football_settings_updated_by` (`updated_by`);

--
-- Indexes for table `football_users`
--
ALTER TABLE `football_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_football_users_mobile` (`mobile`,`deleted_seq`),
  ADD UNIQUE KEY `uq_football_users_national_code` (`national_code`,`deleted_seq`),
  ADD KEY `idx_football_users_role_status` (`role`,`status`),
  ADD KEY `idx_football_users_created_by` (`created_by`),
  ADD KEY `idx_football_users_avatar_path` (`avatar_path`(191));

--
-- Indexes for table `football_user_tokens`
--
ALTER TABLE `football_user_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_football_user_tokens_token_hash` (`token_hash`),
  ADD KEY `idx_football_user_tokens_user_id` (`user_id`),
  ADD KEY `idx_football_user_tokens_expires_at` (`expires_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `football_age_groups`
--
ALTER TABLE `football_age_groups`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `football_attendances`
--
ALTER TABLE `football_attendances`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_audit_logs`
--
ALTER TABLE `football_audit_logs`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_chat_messages`
--
ALTER TABLE `football_chat_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_chat_rooms`
--
ALTER TABLE `football_chat_rooms`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_chat_room_members`
--
ALTER TABLE `football_chat_room_members`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_classes`
--
ALTER TABLE `football_classes`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_class_schedules`
--
ALTER TABLE `football_class_schedules`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_coaches`
--
ALTER TABLE `football_coaches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_credit_transactions`
--
ALTER TABLE `football_credit_transactions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_device_tokens`
--
ALTER TABLE `football_device_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_discounts`
--
ALTER TABLE `football_discounts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_discount_targets`
--
ALTER TABLE `football_discount_targets`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_enrollments`
--
ALTER TABLE `football_enrollments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_evaluations`
--
ALTER TABLE `football_evaluations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_guardians`
--
ALTER TABLE `football_guardians`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_guardians_players`
--
ALTER TABLE `football_guardians_players`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_installments`
--
ALTER TABLE `football_installments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_invoices`
--
ALTER TABLE `football_invoices`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_invoice_discounts`
--
ALTER TABLE `football_invoice_discounts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_invoice_items`
--
ALTER TABLE `football_invoice_items`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_login_attempts`
--
ALTER TABLE `football_login_attempts`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_matches`
--
ALTER TABLE `football_matches`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_match_players`
--
ALTER TABLE `football_match_players`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_media`
--
ALTER TABLE `football_media`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_media_audiences`
--
ALTER TABLE `football_media_audiences`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_news`
--
ALTER TABLE `football_news`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_news_audiences`
--
ALTER TABLE `football_news_audiences`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_notifications`
--
ALTER TABLE `football_notifications`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_payments`
--
ALTER TABLE `football_payments`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_payment_allocations`
--
ALTER TABLE `football_payment_allocations`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_players`
--
ALTER TABLE `football_players`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_player_seasons`
--
ALTER TABLE `football_player_seasons`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_seasons`
--
ALTER TABLE `football_seasons`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_sessions`
--
ALTER TABLE `football_sessions`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_settings`
--
ALTER TABLE `football_settings`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_users`
--
ALTER TABLE `football_users`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `football_user_tokens`
--
ALTER TABLE `football_user_tokens`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `football_attendances`
--
ALTER TABLE `football_attendances`
  ADD CONSTRAINT `football_attendances_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `football_sessions` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `football_attendances_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `football_players` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `football_attendances_ibfk_3` FOREIGN KEY (`recorded_by`) REFERENCES `football_users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `football_audit_logs`
--
ALTER TABLE `football_audit_logs`
  ADD CONSTRAINT `football_audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `football_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_chat_messages`
--
ALTER TABLE `football_chat_messages`
  ADD CONSTRAINT `football_chat_messages_ibfk_1` FOREIGN KEY (`chat_room_id`) REFERENCES `football_chat_rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `football_chat_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `football_users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `football_chat_messages_ibfk_3` FOREIGN KEY (`media_id`) REFERENCES `football_media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_chat_rooms`
--
ALTER TABLE `football_chat_rooms`
  ADD CONSTRAINT `football_chat_rooms_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `football_players` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `football_chat_rooms_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `football_classes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `football_chat_rooms_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `football_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_chat_room_members`
--
ALTER TABLE `football_chat_room_members`
  ADD CONSTRAINT `fk_chat_room_members_last_read` FOREIGN KEY (`last_read_message_id`) REFERENCES `football_chat_messages` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `football_chat_room_members_ibfk_1` FOREIGN KEY (`chat_room_id`) REFERENCES `football_chat_rooms` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `football_chat_room_members_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `football_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `football_chat_room_members_ibfk_3` FOREIGN KEY (`last_read_message_id`) REFERENCES `football_chat_messages` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_classes`
--
ALTER TABLE `football_classes`
  ADD CONSTRAINT `football_classes_ibfk_1` FOREIGN KEY (`season_id`) REFERENCES `football_seasons` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `football_classes_ibfk_2` FOREIGN KEY (`age_group_id`) REFERENCES `football_age_groups` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `football_classes_ibfk_3` FOREIGN KEY (`coach_id`) REFERENCES `football_coaches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `football_classes_ibfk_4` FOREIGN KEY (`assistant_coach_id`) REFERENCES `football_coaches` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `football_classes_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `football_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_class_schedules`
--
ALTER TABLE `football_class_schedules`
  ADD CONSTRAINT `football_class_schedules_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `football_classes` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `football_coaches`
--
ALTER TABLE `football_coaches`
  ADD CONSTRAINT `football_coaches_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `football_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `football_credit_transactions`
--
ALTER TABLE `football_credit_transactions`
  ADD CONSTRAINT `football_credit_transactions_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `football_players` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `football_credit_transactions_ibfk_2` FOREIGN KEY (`payment_id`) REFERENCES `football_payments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `football_credit_transactions_ibfk_3` FOREIGN KEY (`invoice_id`) REFERENCES `football_invoices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `football_credit_transactions_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `football_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_device_tokens`
--
ALTER TABLE `football_device_tokens`
  ADD CONSTRAINT `football_device_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `football_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `football_discounts`
--
ALTER TABLE `football_discounts`
  ADD CONSTRAINT `football_discounts_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `football_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_discount_targets`
--
ALTER TABLE `football_discount_targets`
  ADD CONSTRAINT `football_discount_targets_ibfk_1` FOREIGN KEY (`discount_id`) REFERENCES `football_discounts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `football_enrollments`
--
ALTER TABLE `football_enrollments`
  ADD CONSTRAINT `football_enrollments_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `football_classes` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `football_enrollments_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `football_players` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `football_enrollments_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `football_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_evaluations`
--
ALTER TABLE `football_evaluations`
  ADD CONSTRAINT `football_evaluations_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `football_sessions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `football_evaluations_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `football_players` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `football_evaluations_ibfk_3` FOREIGN KEY (`coach_id`) REFERENCES `football_coaches` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `football_guardians_players`
--
ALTER TABLE `football_guardians_players`
  ADD CONSTRAINT `football_guardians_players_ibfk_1` FOREIGN KEY (`guardian_id`) REFERENCES `football_guardians` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `football_guardians_players_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `football_players` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `football_installments`
--
ALTER TABLE `football_installments`
  ADD CONSTRAINT `football_installments_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `football_invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `football_invoices`
--
ALTER TABLE `football_invoices`
  ADD CONSTRAINT `football_invoices_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `football_players` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `football_invoices_ibfk_2` FOREIGN KEY (`season_id`) REFERENCES `football_seasons` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `football_invoices_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `football_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_invoice_discounts`
--
ALTER TABLE `football_invoice_discounts`
  ADD CONSTRAINT `football_invoice_discounts_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `football_invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `football_invoice_discounts_ibfk_2` FOREIGN KEY (`discount_id`) REFERENCES `football_discounts` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_invoice_items`
--
ALTER TABLE `football_invoice_items`
  ADD CONSTRAINT `football_invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `football_invoices` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `football_invoice_items_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `football_classes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `football_invoice_items_ibfk_3` FOREIGN KEY (`session_id`) REFERENCES `football_sessions` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `football_invoice_items_ibfk_4` FOREIGN KEY (`attendance_id`) REFERENCES `football_attendances` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_login_attempts`
--
ALTER TABLE `football_login_attempts`
  ADD CONSTRAINT `football_login_attempts_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `football_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_matches`
--
ALTER TABLE `football_matches`
  ADD CONSTRAINT `football_matches_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `football_classes` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `football_matches_ibfk_2` FOREIGN KEY (`age_group_id`) REFERENCES `football_age_groups` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `football_matches_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `football_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_match_players`
--
ALTER TABLE `football_match_players`
  ADD CONSTRAINT `football_match_players_ibfk_1` FOREIGN KEY (`match_id`) REFERENCES `football_matches` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `football_match_players_ibfk_2` FOREIGN KEY (`player_id`) REFERENCES `football_players` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `football_media`
--
ALTER TABLE `football_media`
  ADD CONSTRAINT `football_media_ibfk_1` FOREIGN KEY (`uploader_id`) REFERENCES `football_users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `football_media_audiences`
--
ALTER TABLE `football_media_audiences`
  ADD CONSTRAINT `football_media_audiences_ibfk_1` FOREIGN KEY (`media_id`) REFERENCES `football_media` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `football_news`
--
ALTER TABLE `football_news`
  ADD CONSTRAINT `football_news_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `football_users` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `football_news_audiences`
--
ALTER TABLE `football_news_audiences`
  ADD CONSTRAINT `football_news_audiences_ibfk_1` FOREIGN KEY (`news_id`) REFERENCES `football_news` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `football_notifications`
--
ALTER TABLE `football_notifications`
  ADD CONSTRAINT `football_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `football_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `football_payments`
--
ALTER TABLE `football_payments`
  ADD CONSTRAINT `fk_payments_installment` FOREIGN KEY (`installment_id`) REFERENCES `football_installments` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_payments_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `football_invoices` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `football_payments_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `football_players` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `football_payments_ibfk_2` FOREIGN KEY (`receipt_media_id`) REFERENCES `football_media` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `football_payments_ibfk_3` FOREIGN KEY (`confirmed_by`) REFERENCES `football_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `football_payments_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `football_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_payment_allocations`
--
ALTER TABLE `football_payment_allocations`
  ADD CONSTRAINT `football_payment_allocations_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `football_payments` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `football_payment_allocations_ibfk_2` FOREIGN KEY (`invoice_id`) REFERENCES `football_invoices` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `football_payment_allocations_ibfk_3` FOREIGN KEY (`installment_id`) REFERENCES `football_installments` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `football_payment_allocations_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `football_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_players`
--
ALTER TABLE `football_players`
  ADD CONSTRAINT `fk_football_players_user` FOREIGN KEY (`user_id`) REFERENCES `football_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  ADD CONSTRAINT `football_players_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `football_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_player_seasons`
--
ALTER TABLE `football_player_seasons`
  ADD CONSTRAINT `football_player_seasons_ibfk_1` FOREIGN KEY (`player_id`) REFERENCES `football_players` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `football_player_seasons_ibfk_2` FOREIGN KEY (`season_id`) REFERENCES `football_seasons` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `football_player_seasons_ibfk_3` FOREIGN KEY (`age_group_id`) REFERENCES `football_age_groups` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `football_player_seasons_ibfk_4` FOREIGN KEY (`assigned_by`) REFERENCES `football_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_seasons`
--
ALTER TABLE `football_seasons`
  ADD CONSTRAINT `football_seasons_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `football_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_sessions`
--
ALTER TABLE `football_sessions`
  ADD CONSTRAINT `football_sessions_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `football_classes` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `football_sessions_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `football_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_settings`
--
ALTER TABLE `football_settings`
  ADD CONSTRAINT `football_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `football_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_users`
--
ALTER TABLE `football_users`
  ADD CONSTRAINT `football_users_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `football_users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `football_user_tokens`
--
ALTER TABLE `football_user_tokens`
  ADD CONSTRAINT `football_user_tokens_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `football_users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
