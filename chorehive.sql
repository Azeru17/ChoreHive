-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 19, 2026 at 01:51 AM
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
-- Database: `chorehive`
--

-- --------------------------------------------------------

--
-- Table structure for table `app_sessions`
--

CREATE TABLE `app_sessions` (
  `session_id` varchar(128) NOT NULL,
  `session_data` mediumblob NOT NULL,
  `expires_at` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `audit_log`
--

CREATE TABLE `audit_log` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `actor_name` varchar(100) NOT NULL,
  `action` varchar(80) NOT NULL,
  `details` varchar(500) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_log`
--

INSERT INTO `audit_log` (`id`, `user_id`, `actor_name`, `action`, `details`, `created_at`) VALUES
(372, 1, 'Administrator', 'audit_cleared', 'Admin Administrator cleared the audit log (0 entries removed)', '2026-10-18 22:47:45'),
(373, 1, 'Administrator', 'badge_awarded', 'Admin Administrator awarded \'Deep Cleaning Expert\' to worker #11', '2026-10-18 22:48:06'),
(374, 16, 'Eliazer G. Emabtic', 'login', 'Eliazer G. Emabtic logged in', '2026-10-18 22:50:05'),
(375, 16, 'Eliazer G. Emabtic', 'booking_created', 'Booking #45 created by client Eliazer G. Emabtic (service 1; payment gcash; worker #11)', '2026-10-18 22:52:07'),
(376, 16, 'Eliazer G. Emabtic', 'payment_demo_completed', 'SIMULATION ONLY: client Eliazer G. Emabtic simulated GCash payment of ₱350.00 for booking #45; no funds transferred', '2026-10-18 22:52:37'),
(377, 11, 'The Rock', 'login', 'The Rock logged in', '2026-10-18 22:52:59'),
(378, 16, 'Eliazer G. Emabtic', 'login', 'Eliazer G. Emabtic logged in', '2026-10-18 22:56:53'),
(379, 11, 'The Rock', 'login', 'The Rock logged in', '2026-10-18 22:57:29'),
(380, 11, 'The Rock', 'job_started', 'Worker The Rock started booking #45', '2026-10-18 22:57:33'),
(381, 16, 'Eliazer G. Emabtic', 'login', 'Eliazer G. Emabtic logged in', '2026-10-18 22:58:46'),
(382, 16, 'Eliazer G. Emabtic', 'job_start_confirmed', 'Client Eliazer G. Emabtic confirmed the job start for booking #45', '2026-10-18 22:58:58'),
(383, 11, 'The Rock', 'login', 'The Rock logged in', '2026-10-18 22:59:43'),
(384, 11, 'The Rock', 'job_finished', 'Worker The Rock finished booking #45', '2026-10-18 22:59:47'),
(385, 16, 'Eliazer G. Emabtic', 'login', 'Eliazer G. Emabtic logged in', '2026-10-18 23:00:07'),
(386, 16, 'Eliazer G. Emabtic', 'job_verified', 'Client Eliazer G. Emabtic verified simulation-only booking #45', '2026-10-18 23:00:16'),
(387, 11, 'The Rock', 'login', 'The Rock logged in', '2026-10-18 23:00:35'),
(388, 16, 'Eliazer G. Emabtic', 'login', 'Eliazer G. Emabtic logged in', '2026-10-18 23:01:29'),
(389, 16, 'Eliazer G. Emabtic', 'profile_updated', 'Eliazer G. Emabtic updated their client profile', '2026-10-18 23:09:06'),
(390, 16, 'Eliazer G. Emabtic', 'profile_updated', 'Eliazer G. Emabtic updated their client profile', '2026-10-18 23:09:40'),
(391, 1, 'Administrator', 'login', 'Administrator logged in', '2026-10-18 23:10:06'),
(392, 16, 'Eliazer G. Emabtic', 'login', 'Eliazer G. Emabtic logged in', '2026-10-18 23:12:50'),
(393, 1, 'Administrator', 'login', 'Administrator logged in', '2026-10-18 23:13:20'),
(394, 1, 'Administrator', 'admin_chat_viewed', 'Admin Administrator opened conversation #124 between client #16 and worker #11', '2026-10-18 23:13:26'),
(395, 1, 'Administrator', 'login', 'Administrator logged in', '2026-10-18 23:14:56');

-- --------------------------------------------------------

--
-- Table structure for table `badges`
--

CREATE TABLE `badges` (
  `id` int(11) NOT NULL,
  `worker_id` int(11) NOT NULL,
  `name` varchar(80) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `earned_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `badges`
--

INSERT INTO `badges` (`id`, `worker_id`, `name`, `description`, `earned_at`) VALUES
(16, 11, 'Deep Cleaning Expert', 'Awarded by admin.', '2026-10-18 22:48:06');

-- --------------------------------------------------------

--
-- Table structure for table `bookings`
--

CREATE TABLE `bookings` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `worker_id` int(11) DEFAULT NULL,
  `service_id` int(11) NOT NULL,
  `schedule` datetime NOT NULL,
  `address` varchar(255) NOT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','assigned','in_progress','awaiting_verification','completed','cancelled') DEFAULT 'pending',
  `client_started_ack` tinyint(1) NOT NULL DEFAULT 0,
  `payment_status` enum('unpaid','paid','demo_paid') NOT NULL DEFAULT 'unpaid',
  `payment_method` enum('cash','gcash','maya') NOT NULL DEFAULT 'cash',
  `payment_reference` varchar(80) DEFAULT NULL,
  `cancel_reason` varchar(255) DEFAULT NULL COMMENT 'Why the booking was cancelled/declined',
  `paid_at` datetime DEFAULT NULL,
  `paymongo_checkout_id` varchar(80) DEFAULT NULL,
  `paymongo_payment_id` varchar(80) DEFAULT NULL,
  `total_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `address_lat` decimal(10,7) DEFAULT NULL,
  `address_lng` decimal(10,7) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookings`
--

INSERT INTO `bookings` (`id`, `client_id`, `worker_id`, `service_id`, `schedule`, `address`, `notes`, `status`, `client_started_ack`, `payment_status`, `payment_method`, `payment_reference`, `cancel_reason`, `paid_at`, `paymongo_checkout_id`, `paymongo_payment_id`, `total_price`, `address_lat`, `address_lng`, `created_at`) VALUES
(45, 16, 11, 1, '2026-10-19 06:50:00', 'Purok Matatag, Polomolok, South Cotabato', 'Get inside.', 'completed', 1, 'demo_paid', 'gcash', 'CH-DEMO-GCASH-45-B4D05F', NULL, '2026-10-19 06:52:37', NULL, NULL, 350.00, 6.2150321, 125.0691245, '2026-10-18 22:52:06');

-- --------------------------------------------------------

--
-- Table structure for table `booking_photos`
--

CREATE TABLE `booking_photos` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `stage` enum('before','after') NOT NULL,
  `filepath` varchar(255) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `booking_photos`
--

INSERT INTO `booking_photos` (`id`, `booking_id`, `stage`, `filepath`, `uploaded_at`) VALUES
(27, 45, 'before', 'b45_before_e11de9abdc497446.jpg', '2026-10-18 22:54:49'),
(28, 45, 'before', 'b45_before_a2c918b8e95c35da.jpg', '2026-10-18 22:55:51'),
(29, 45, 'after', 'b45_after_9f454051865fca24.jpg', '2026-10-18 22:58:30');

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` bigint(20) NOT NULL,
  `thread_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_deleted` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `thread_id`, `sender_id`, `message`, `created_at`, `is_deleted`) VALUES
(53, 124, 16, 'I would like to hire you.', '2026-10-18 23:13:01', 0);

-- --------------------------------------------------------

--
-- Table structure for table `chat_threads`
--

CREATE TABLE `chat_threads` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `worker_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_threads`
--

INSERT INTO `chat_threads` (`id`, `client_id`, `worker_id`, `created_at`, `updated_at`) VALUES
(124, 16, 11, '2026-10-18 23:12:52', '2026-10-18 23:13:01');

-- --------------------------------------------------------

--
-- Table structure for table `checklist_items`
--

CREATE TABLE `checklist_items` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `task` varchar(255) NOT NULL,
  `done` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `checklist_items`
--

INSERT INTO `checklist_items` (`id`, `booking_id`, `task`, `done`) VALUES
(85, 45, 'Clean Kitchen Sink', 1),
(86, 45, 'Mob all floors', 1),
(87, 45, 'Arrange chairs', 1);

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `message` text NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `read_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `message`, `is_read`, `created_at`, `read_at`) VALUES
(2, 'juan', 'juan@worker.test', 'hi', 1, '2026-10-15 12:55:27', '2026-10-18 20:32:18'),
(3, 'ka buang', 'angcx@gmail.com', 'way lami', 1, '2026-10-18 11:13:02', '2026-10-18 20:32:14'),
(4, 'Azer', 'eliazerembatic@gmail.com', 'pakyu', 1, '2026-10-18 11:14:15', '2026-10-18 20:32:07');

-- --------------------------------------------------------

--
-- Table structure for table `favorite_workers`
--

CREATE TABLE `favorite_workers` (
  `client_id` int(11) NOT NULL,
  `worker_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback`
--

CREATE TABLE `feedback` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `rating` tinyint(4) NOT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `feedback`
--

INSERT INTO `feedback` (`id`, `booking_id`, `client_id`, `rating`, `comment`, `created_at`) VALUES
(11, 45, 16, 5, 'Very fast and good working person.', '2026-10-18 23:02:10');

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `attempt_key` varchar(255) NOT NULL,
  `attempts` int(11) DEFAULT 1,
  `last_attempt` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `media_files`
--

CREATE TABLE `media_files` (
  `filename` varchar(255) NOT NULL,
  `mime_type` varchar(40) NOT NULL,
  `file_data` mediumblob NOT NULL,
  `visibility` enum('public','private') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(120) DEFAULT NULL,
  `message` varchar(500) NOT NULL,
  `action_url` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `action_url`, `is_read`, `created_at`) VALUES
(172, 16, NULL, 'Welcome to ChoreHive, Eliazer G. Emabtic! Your client account is ready.', NULL, 1, '2026-10-18 22:26:24'),
(173, 12, NULL, 'Welcome to ChoreHive, Johnny Sins! Your worker profile is pending administrator review and will not appear in the marketplace until approved.', NULL, 1, '2026-10-18 22:32:33'),
(174, 12, NULL, 'Your ChoreHive profile was approved for marketplace listing by an administrator.', NULL, 0, '2026-10-18 22:35:13'),
(175, 13, NULL, 'Welcome to ChoreHive, Slow Tiffany! Your worker profile is pending administrator review and will not appear in the marketplace until approved.', NULL, 1, '2026-10-18 22:39:01'),
(176, 13, NULL, 'Your ChoreHive profile was approved for marketplace listing by an administrator.', NULL, 0, '2026-10-18 22:40:11'),
(177, 11, NULL, 'Welcome to ChoreHive, The Rock! Your worker profile is pending administrator review and will not appear in the marketplace until approved.', NULL, 1, '2026-10-18 22:45:04'),
(178, 11, NULL, 'Your ChoreHive profile was approved for marketplace listing by an administrator.', NULL, 1, '2026-10-18 22:46:56'),
(179, 11, NULL, 'You earned a new badge: \'Deep Cleaning Expert\'. Keep up the great work!', NULL, 1, '2026-10-18 22:48:06'),
(180, 11, NULL, 'Booking #45 is reserved for you but waiting for the customer\'s GCash/Maya payment before you start work.', 'worker/job.php?id=45', 1, '2026-10-18 22:52:07'),
(181, 16, NULL, 'Booking #45 created: Basic Home Cleaning on Oct 19, 6:50 AM. Payment method: GCash (PayMongo).', 'client/booking.php?id=45', 1, '2026-10-18 22:52:07'),
(182, 11, NULL, 'DEMO ONLY: simulated GCash payment for booking #45. No money was transferred.', 'worker/job.php?id=45', 1, '2026-10-18 22:52:37'),
(183, 16, NULL, 'DEMO ONLY: simulated GCash payment for booking #45. No money was transferred and this is not a real receipt.', 'client/booking.php?id=45', 1, '2026-10-18 22:52:37'),
(184, 16, NULL, 'The Rock uploaded new before photo(s) for your booking #45 (Basic Home Cleaning). Tap to view.', 'client/booking.php?id=45', 1, '2026-10-18 22:54:49'),
(185, 16, NULL, 'The Rock uploaded new before photo(s) for your booking #45 (Basic Home Cleaning). Tap to view.', 'client/booking.php?id=45', 1, '2026-10-18 22:55:51'),
(186, 16, NULL, 'The Rock started working on your booking #45. Open the booking and press Confirm Job to acknowledge the start.', 'client/booking.php?id=45', 1, '2026-10-18 22:57:33'),
(187, 16, NULL, 'The Rock uploaded new after photo(s) for your booking #45 (Basic Home Cleaning). Tap to view.', 'client/booking.php?id=45', 1, '2026-10-18 22:58:30'),
(188, 11, NULL, 'The customer confirmed that you started booking #45 (Basic Home Cleaning). You can mark the job done once it\'s finished.', 'worker/job.php?id=45', 1, '2026-10-18 22:58:58'),
(189, 16, NULL, 'You confirmed the worker started booking #45. You\'ll be asked to confirm the finished job next.', 'client/booking.php?id=45', 1, '2026-10-18 22:58:58'),
(190, 16, NULL, 'Your booking #45 is ready for review — check the after photos and confirm the selected payment flow.', 'client/booking.php?id=45', 1, '2026-10-18 22:59:47'),
(191, 11, NULL, 'The customer verified booking #45 and marked it complete. The online payment was only simulated; no money was transferred.', 'worker/job.php?id=45', 1, '2026-10-18 23:00:16'),
(192, 16, NULL, 'Booking #45 has been verified and completed. The payment simulation record is available.', 'client/booking.php?id=45', 1, '2026-10-18 23:00:16'),
(193, 11, NULL, 'Eliazer G. Emabtic rated booking #45 5/5 stars. \"Very fast and good working person.\"', 'worker/job.php?id=45', 0, '2026-10-18 23:02:10'),
(194, 16, NULL, 'Thanks! Your 5/5 rating for booking #45 was saved.', 'client/booking.php?id=45', 1, '2026-10-18 23:02:10'),
(195, 11, NULL, 'New message from Eliazer G. Emabtic: I would like to hire you.', 'chat.php?user_id=16', 0, '2026-10-18 23:13:01');

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `token_hash` char(64) NOT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `used` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `role_id_counters`
--

CREATE TABLE `role_id_counters` (
  `role` enum('client','worker','admin') NOT NULL,
  `next_id` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_id_counters`
--

INSERT INTO `role_id_counters` (`role`, `next_id`) VALUES
('client', 17),
('worker', 18),
('admin', 3);

-- --------------------------------------------------------

--
-- Table structure for table `role_id_freed`
--

CREATE TABLE `role_id_freed` (
  `id` int(11) NOT NULL,
  `role` enum('client','worker','admin') NOT NULL,
  `freed_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `role_id_freed`
--

INSERT INTO `role_id_freed` (`id`, `role`, `freed_id`) VALUES
(15, 'client', 7),
(8, 'client', 14),
(7, 'client', 15),
(13, 'worker', 8),
(12, 'worker', 9),
(14, 'worker', 10),
(5, 'admin', 2);

-- --------------------------------------------------------

--
-- Table structure for table `saved_addresses`
--

CREATE TABLE `saved_addresses` (
  `id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `label` varchar(60) NOT NULL,
  `address` varchar(255) NOT NULL,
  `address_lat` decimal(10,7) DEFAULT NULL,
  `address_lng` decimal(10,7) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `base_price` decimal(10,2) NOT NULL,
  `duration_hours` int(11) DEFAULT 2
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `name`, `description`, `base_price`, `duration_hours`) VALUES
(1, 'Basic Home Cleaning', 'Sweeping, mopping, dusting, trash disposal.', 350.00, 2),
(2, 'Deep Cleaning', 'Thorough cleaning of kitchens, bathrooms, and hard-to-reach areas.', 700.00, 4),
(3, 'Move-in/Move-out Cleaning', 'Full unit cleaning for tenants and landlords.', 900.00, 5),
(4, 'Laundry & Ironing', 'Washing, drying, folding, and ironing.', 300.00, 3);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(120) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('client','worker','admin') NOT NULL DEFAULT 'client',
  `worker_approved` tinyint(1) NOT NULL DEFAULT 0,
  `phone` varchar(30) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `is_new` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `worker_approved`, `phone`, `address`, `city`, `bio`, `avatar`, `is_new`, `created_at`) VALUES
(1, 'Administrator', 'admin@chorehive.test', '$2y$10$eCcdevjWXEbau2UcRR4bm.2Zg1XWLLfZUq.rB/lde7BCyTjQOAghG', 'admin', 0, '0908814444', 'Purok Matatag, Polomolok, South Cotabato', 'Polomolok', NULL, 'av1_c294a815d680.jpg', 0, '2026-10-12 14:43:27'),
(11, 'The Rock', 'rock@gmail.com', '$2y$10$SFcfb2Up5qL0TfOljOSK..7ALwN1TxeLYxKFEQoq4m0XpU8BZ3BDu', 'worker', 1, '09097501827', 'Cannery brgy.Malipayon, Polomolok, South Cotabato', 'Polomolok', '', 'av11_505ce6800ec2.jpg', 0, '2026-10-18 22:45:04'),
(12, 'Johnny Sins', 'jonny@gmail.com', '$2y$10$FjDO8OuOhxUE3XFWrdWD3.2zX6r.CQSQId4HVrylBUgky2X4FqCq.', 'worker', 1, '09489321654', 'Purok Malabarbas, Polomolok, South Cotabato', 'Polomolok', '', 'av12_33c28fdc0262.jpg', 0, '2026-10-18 22:32:33'),
(13, 'Slow Tiffany', 'tiffany@gmail.com', '$2y$10$9WB44fCmqlaKtI.xMOk/euKpbD6C0GkEB8L9snuRzD/k/0gwIG18q', 'worker', 1, '0908814444', 'Purok Pag-ibig, Polomolok, South Cotabato', 'Polomolok', '', 'av13_33328c2c7fc1.jpg', 0, '2026-10-18 22:39:01'),
(16, 'Eliazer G. Emabtic', 'eliazerembatic@gmail.com', '$2y$10$WWOnp7RT.ovj4sTZmfeeM.0Bb/wezVuGRHRJii0WzS2fyI0CuDaF.', 'client', 0, '09854999384', 'Purok Matatag, Polomolok, South Cotabato', 'Polomolok', NULL, 'av16_08f47fe86007.jpg', 0, '2026-10-18 22:26:24');

-- --------------------------------------------------------

--
-- Table structure for table `worker_finance_entries`
--

CREATE TABLE `worker_finance_entries` (
  `id` int(11) NOT NULL,
  `worker_id` int(11) DEFAULT NULL,
  `booking_id` int(11) DEFAULT NULL,
  `entry_type` enum('payout','bonus','deduction','discount','expense') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `note` varchar(255) NOT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `worker_portfolio`
--

CREATE TABLE `worker_portfolio` (
  `id` int(11) NOT NULL,
  `worker_id` int(11) NOT NULL,
  `image` varchar(255) NOT NULL,
  `caption` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `worker_presence`
--

CREATE TABLE `worker_presence` (
  `worker_id` int(11) NOT NULL,
  `last_seen` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `worker_presence`
--

INSERT INTO `worker_presence` (`worker_id`, `last_seen`) VALUES
(11, '2026-10-19 07:01:19'),
(12, '2026-10-19 06:33:17'),
(13, '2026-10-19 06:39:35');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `app_sessions`
--
ALTER TABLE `app_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `idx_app_sessions_expiry` (`expires_at`);

--
-- Indexes for table `audit_log`
--
ALTER TABLE `audit_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `badges`
--
ALTER TABLE `badges`
  ADD PRIMARY KEY (`id`),
  ADD KEY `worker_id` (`worker_id`);

--
-- Indexes for table `bookings`
--
ALTER TABLE `bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `worker_id` (`worker_id`),
  ADD KEY `service_id` (`service_id`);

--
-- Indexes for table `booking_photos`
--
ALTER TABLE `booking_photos`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_thread_message` (`thread_id`,`id`),
  ADD KEY `sender_id` (`sender_id`);

--
-- Indexes for table `chat_threads`
--
ALTER TABLE `chat_threads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_client_worker` (`client_id`,`worker_id`),
  ADD KEY `worker_id` (`worker_id`);

--
-- Indexes for table `checklist_items`
--
ALTER TABLE `checklist_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_contact_messages_unread_created` (`is_read`,`created_at`),
  ADD KEY `idx_contact_messages_created` (`created_at`);

--
-- Indexes for table `favorite_workers`
--
ALTER TABLE `favorite_workers`
  ADD PRIMARY KEY (`client_id`,`worker_id`),
  ADD KEY `idx_fav_worker` (`worker_id`);

--
-- Indexes for table `feedback`
--
ALTER TABLE `feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `booking_id` (`booking_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`attempt_key`);

--
-- Indexes for table `media_files`
--
ALTER TABLE `media_files`
  ADD PRIMARY KEY (`filename`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `role_id_counters`
--
ALTER TABLE `role_id_counters`
  ADD PRIMARY KEY (`role`);

--
-- Indexes for table `role_id_freed`
--
ALTER TABLE `role_id_freed`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_freed` (`role`,`freed_id`);

--
-- Indexes for table `saved_addresses`
--
ALTER TABLE `saved_addresses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_savedaddr_client` (`client_id`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `worker_finance_entries`
--
ALTER TABLE `worker_finance_entries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_finance_worker_date` (`worker_id`,`created_at`),
  ADD KEY `idx_finance_type_date` (`entry_type`,`created_at`),
  ADD KEY `fk_finance_booking` (`booking_id`),
  ADD KEY `fk_finance_admin` (`created_by`);

--
-- Indexes for table `worker_portfolio`
--
ALTER TABLE `worker_portfolio`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_portfolio_worker` (`worker_id`);

--
-- Indexes for table `worker_presence`
--
ALTER TABLE `worker_presence`
  ADD PRIMARY KEY (`worker_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_log`
--
ALTER TABLE `audit_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=396;

--
-- AUTO_INCREMENT for table `badges`
--
ALTER TABLE `badges`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `bookings`
--
ALTER TABLE `bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `booking_photos`
--
ALTER TABLE `booking_photos`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=30;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `chat_threads`
--
ALTER TABLE `chat_threads`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=130;

--
-- AUTO_INCREMENT for table `checklist_items`
--
ALTER TABLE `checklist_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=88;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `feedback`
--
ALTER TABLE `feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=196;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `role_id_freed`
--
ALTER TABLE `role_id_freed`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `saved_addresses`
--
ALTER TABLE `saved_addresses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `worker_finance_entries`
--
ALTER TABLE `worker_finance_entries`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `worker_portfolio`
--
ALTER TABLE `worker_portfolio`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `badges`
--
ALTER TABLE `badges`
  ADD CONSTRAINT `badges_ibfk_1` FOREIGN KEY (`worker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `bookings`
--
ALTER TABLE `bookings`
  ADD CONSTRAINT `bookings_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bookings_ibfk_2` FOREIGN KEY (`worker_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `bookings_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`);

--
-- Constraints for table `booking_photos`
--
ALTER TABLE `booking_photos`
  ADD CONSTRAINT `booking_photos_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`thread_id`) REFERENCES `chat_threads` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_threads`
--
ALTER TABLE `chat_threads`
  ADD CONSTRAINT `chat_threads_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_threads_ibfk_2` FOREIGN KEY (`worker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `checklist_items`
--
ALTER TABLE `checklist_items`
  ADD CONSTRAINT `checklist_items_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `favorite_workers`
--
ALTER TABLE `favorite_workers`
  ADD CONSTRAINT `fk_fav_client` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_fav_worker` FOREIGN KEY (`worker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback`
--
ALTER TABLE `feedback`
  ADD CONSTRAINT `feedback_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `saved_addresses`
--
ALTER TABLE `saved_addresses`
  ADD CONSTRAINT `fk_savedaddr_client` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `worker_finance_entries`
--
ALTER TABLE `worker_finance_entries`
  ADD CONSTRAINT `fk_finance_admin` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_finance_booking` FOREIGN KEY (`booking_id`) REFERENCES `bookings` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_finance_worker` FOREIGN KEY (`worker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `worker_portfolio`
--
ALTER TABLE `worker_portfolio`
  ADD CONSTRAINT `fk_portfolio_worker` FOREIGN KEY (`worker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `worker_presence`
--
ALTER TABLE `worker_presence`
  ADD CONSTRAINT `worker_presence_ibfk_1` FOREIGN KEY (`worker_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
