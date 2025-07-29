-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jul 29, 2025 at 11:56 AM
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
-- Database: `ems_database`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `log_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`details`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `announcements`
--

CREATE TABLE `announcements` (
  `announcement_id` int(11) NOT NULL,
  `title` varchar(200) NOT NULL,
  `content` text NOT NULL,
  `category` enum('general','academic','event','urgent','maintenance') DEFAULT 'general',
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `author_id` int(11) DEFAULT NULL,
  `target_audience` enum('all','students','staff','specific') DEFAULT 'all',
  `target_users` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`target_users`)),
  `start_date` datetime DEFAULT current_timestamp(),
  `end_date` datetime DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_pinned` tinyint(1) DEFAULT 0,
  `read_count` int(11) DEFAULT 0,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `announcements`
--

INSERT INTO `announcements` (`announcement_id`, `title`, `content`, `category`, `priority`, `author_id`, `target_audience`, `target_users`, `start_date`, `end_date`, `is_active`, `is_pinned`, `read_count`, `attachments`, `created_at`, `updated_at`) VALUES
(1, '🚀 Campus Innovation Fair Next Week', 'Get ready for the biggest innovation showcase of the year! Students will present their groundbreaking projects and compete for amazing prizes worth over MK 500,000. Registration deadline is this Friday.', 'event', 'high', 1, 'all', NULL, '2025-06-30 09:18:44', NULL, 1, 1, 0, NULL, '2025-06-30 07:18:44', '2025-06-30 07:18:44'),
(2, '📚 Library Extended Hours During Exams', 'Starting next Monday, the library will extend its operating hours to support students during the examination period. New hours: Monday-Sunday 6:00 AM - 11:00 PM. Additional study spaces have been arranged.', 'academic', 'medium', 1, 'all', NULL, '2025-06-30 09:18:44', NULL, 1, 0, 0, NULL, '2025-06-30 07:18:44', '2025-06-30 07:18:44'),
(3, '🏥 Health Center New Services', 'The campus health center is pleased to announce new mental health support services. Free counseling sessions are now available every Tuesday and Thursday from 2:00 PM - 5:00 PM. Book your appointment online.', 'general', 'medium', 1, 'all', NULL, '2025-06-30 09:18:44', NULL, 1, 0, 0, NULL, '2025-06-30 07:18:44', '2025-06-30 07:18:44');

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `event_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(200) DEFAULT NULL,
  `description` text NOT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `start_datetime` datetime NOT NULL,
  `end_datetime` datetime NOT NULL,
  `registration_deadline` datetime DEFAULT NULL,
  `venue` varchar(255) NOT NULL,
  `venue_details` text DEFAULT NULL,
  `category` enum('academic','social','sports','cultural','other') NOT NULL,
  `event_type` enum('workshop','seminar','concert','meeting','conference','other') NOT NULL,
  `is_paid` tinyint(1) DEFAULT 0,
  `price` decimal(10,2) DEFAULT 0.00,
  `is_public` tinyint(1) DEFAULT 1,
  `max_attendees` int(11) DEFAULT NULL,
  `current_attendees` int(11) DEFAULT 0,
  `organizer_id` int(11) DEFAULT NULL,
  `status` enum('draft','pending','approved','rejected','cancelled','completed') NOT NULL DEFAULT 'draft',
  `featured` tinyint(1) DEFAULT 0,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `requirements` text DEFAULT NULL,
  `contact_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`contact_info`)),
  `social_links` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`social_links`)),
  `image` varchar(255) DEFAULT NULL,
  `gallery` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gallery`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`event_id`, `title`, `slug`, `description`, `short_description`, `start_datetime`, `end_datetime`, `registration_deadline`, `venue`, `venue_details`, `category`, `event_type`, `is_paid`, `price`, `is_public`, `max_attendees`, `current_attendees`, `organizer_id`, `status`, `featured`, `tags`, `requirements`, `contact_info`, `social_links`, `image`, `gallery`, `created_at`, `updated_at`) VALUES
(1, 'AI Innovation Summit 2025', 'ai-innovation-summit-2025', 'Join us for the most comprehensive AI conference in Malawi. Featuring keynote speakers from Google, Microsoft, and local tech innovators. Learn about machine learning, neural networks, and the future of AI in Africa.', 'Premier AI conference with industry leaders', '2025-01-28 09:00:00', '2025-01-28 17:00:00', NULL, 'Tech Hub Auditorium', NULL, 'academic', 'seminar', 1, 25000.00, 1, 250, 0, 1, 'approved', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2025-06-30 07:18:44', '2025-06-30 07:18:44'),
(2, 'Cultural Heritage Festival', 'cultural-heritage-festival', 'Celebrate the rich cultural diversity of Malawi and beyond. Experience traditional dances, music, food, and crafts from various ethnic groups. A perfect opportunity to learn and appreciate our heritage.', 'Multicultural celebration with traditional performances', '2025-02-01 10:00:00', '2025-02-01 22:00:00', NULL, 'Campus Main Grounds', NULL, 'cultural', 'other', 0, 0.00, 1, 1000, 0, 1, 'approved', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2025-06-30 07:18:44', '2025-06-30 07:18:44'),
(3, 'Inter-University Sports Championship', 'inter-university-sports-championship', 'The biggest sporting event of the year featuring competitions in football, basketball, volleyball, athletics, and more. Universities from across the region will compete for the championship trophy.', 'Regional university sports competition', '2025-02-05 08:00:00', '2025-02-07 18:00:00', NULL, 'University Sports Complex', NULL, 'sports', 'other', 0, 0.00, 1, 2000, 0, 1, 'approved', 1, NULL, NULL, NULL, NULL, NULL, NULL, '2025-06-30 07:18:44', '2025-06-30 07:18:44');

-- --------------------------------------------------------

--
-- Table structure for table `event_proposals`
--

CREATE TABLE `event_proposals` (
  `proposal_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `proposed_date` date NOT NULL,
  `proposed_start_time` time NOT NULL,
  `proposed_end_time` time NOT NULL,
  `venue` varchar(255) NOT NULL,
  `venue_requirements` text DEFAULT NULL,
  `estimated_attendees` int(11) DEFAULT NULL,
  `target_audience` varchar(255) DEFAULT NULL,
  `is_paid` tinyint(1) DEFAULT 0,
  `category` enum('academic','social','sports','cultural','other') NOT NULL,
  `event_type` enum('workshop','seminar','concert','meeting','conference','other') NOT NULL,
  `additional_notes` text DEFAULT NULL,
  `status` enum('pending','approved','rejected','revisions_requested') NOT NULL DEFAULT 'pending',
  `admin_comments` text DEFAULT NULL,
  `supporting_documents` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `event_registrations`
--

CREATE TABLE `event_registrations` (
  `registration_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `registration_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('registered','confirmed','cancelled','attended') DEFAULT 'registered',
  `payment_status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `payment_reference` varchar(100) DEFAULT NULL,
  `ticket_number` varchar(50) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `additional_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`additional_info`)),
  `checked_in_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_registrations`
--

INSERT INTO `event_registrations` (`registration_id`, `user_id`, `event_id`, `registration_date`, `status`, `payment_status`, `payment_reference`, `ticket_number`, `qr_code`, `additional_info`, `checked_in_at`, `notes`, `created_at`, `updated_at`) VALUES
(1, 2, 1, '2025-06-30 07:18:44', 'confirmed', 'completed', NULL, 'EMS-2025-001', NULL, NULL, NULL, NULL, '2025-06-30 07:18:44', '2025-06-30 07:18:44'),
(2, 3, 1, '2025-06-30 07:18:44', 'registered', 'pending', NULL, 'EMS-2025-002', NULL, NULL, NULL, NULL, '2025-06-30 07:18:44', '2025-06-30 07:18:44'),
(3, 4, 2, '2025-06-30 07:18:44', 'confirmed', 'completed', NULL, 'EMS-2025-003', NULL, NULL, NULL, NULL, '2025-06-30 07:18:44', '2025-06-30 07:18:44'),
(4, 2, 3, '2025-06-30 07:18:44', 'registered', 'completed', NULL, 'EMS-2025-004', NULL, NULL, NULL, NULL, '2025-06-30 07:18:44', '2025-06-30 07:18:44');

-- --------------------------------------------------------

--
-- Table structure for table `feedback_comment`
--

CREATE TABLE `feedback_comment` (
  `comment_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `guest_email` varchar(100) DEFAULT NULL,
  `comment` text NOT NULL,
  `is_anonymous` tinyint(1) DEFAULT 0,
  `is_approved` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `feedback_rating`
--

CREATE TABLE `feedback_rating` (
  `rating_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `guest_email` varchar(100) DEFAULT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `guest_rsvps`
--

CREATE TABLE `guest_rsvps` (
  `rsvp_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `attendance_status` enum('confirmed','maybe','declined') NOT NULL DEFAULT 'confirmed',
  `additional_guests` int(11) DEFAULT 0,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `guest_subscribers`
--

CREATE TABLE `guest_subscribers` (
  `subscriber_id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `subscription_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `is_active` tinyint(1) DEFAULT 1,
  `unsubscribe_token` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `notification_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('event_confirmation','event_reminder','event_update','event_cancellation','proposal_status','system') NOT NULL,
  `category` enum('event','announcement','system','reminder') DEFAULT 'system',
  `related_id` int(11) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `action_url` varchar(255) DEFAULT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int(11) NOT NULL,
  `ticket_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('mpamba','airtel_money','credit_card','bank_transfer','cash') NOT NULL,
  `transaction_reference` varchar(100) DEFAULT NULL,
  `payment_status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `payment_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `refund_amount` decimal(10,2) DEFAULT NULL,
  `refund_date` datetime DEFAULT NULL,
  `refund_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tickets`
--

CREATE TABLE `tickets` (
  `ticket_id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ticket_type` varchar(50) NOT NULL,
  `price` decimal(10,2) DEFAULT 0.00,
  `payment_status` enum('pending','completed','refunded','cancelled') NOT NULL DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `qr_code` varchar(255) DEFAULT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `used_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `user_id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `role` enum('admin','organizer','user','guest') NOT NULL DEFAULT 'user',
  `department` varchar(100) DEFAULT NULL,
  `phone_number` varchar(20) DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `profile_image` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `email_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` datetime DEFAULT NULL,
  `reset_token` varchar(100) DEFAULT NULL,
  `reset_token_expires` datetime DEFAULT NULL,
  `preferences` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`preferences`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`user_id`, `username`, `email`, `password`, `first_name`, `last_name`, `role`, `department`, `phone_number`, `date_of_birth`, `gender`, `profile_image`, `bio`, `email_verified`, `verification_token`, `last_login`, `login_attempts`, `locked_until`, `reset_token`, `reset_token_expires`, `preferences`, `created_at`, `updated_at`) VALUES
(1, 'admin', 'admin@unilia.ac.mw', '$2y$10$8tPjdlv.7XDmvW93bFPeAO6FZFVlsJ5XYh5hxQUC9HbSVZlhXu3Uu', 'System', 'Administrator', 'admin', NULL, NULL, NULL, NULL, NULL, 'System Administrator for EMS Platform', 1, NULL, '2025-06-30 09:18:43', 0, NULL, NULL, NULL, '{\"theme\": \"dark\", \"notifications\": true}', '2025-06-16 10:11:40', '2025-06-30 07:18:43'),
(2, 'john_doe', 'john@student.ems.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John', 'Doe', 'user', NULL, '+265991234567', NULL, NULL, NULL, 'Computer Science student passionate about AI and machine learning.', 1, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-06-30 07:18:44', '2025-06-30 07:18:44'),
(3, 'jane_smith', 'jane@student.ems.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane', 'Smith', 'user', NULL, '+265991234568', NULL, NULL, NULL, 'Business Administration student and event organizer.', 1, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-06-30 07:18:44', '2025-06-30 07:18:44'),
(4, 'mike_wilson', 'mike@student.ems.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike', 'Wilson', 'organizer', NULL, '+265991234569', NULL, NULL, NULL, 'Event coordinator and sports enthusiast.', 1, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-06-30 07:18:44', '2025-06-30 07:18:44'),
(7, 'Isaac', 'cen-01-23-22@unilia.ac.mw', '$2y$10$tFA8mnJx7LEt/dFiEGVyW.m07Stl7dLPVRzY1gPze423AVrEzbWba', 'isaac', 'chipeta', 'user', 'Computer Science', '0882222224', NULL, NULL, NULL, NULL, 0, 'a652f9', '2025-07-15 17:42:26', 0, NULL, NULL, NULL, NULL, '2025-07-11 12:40:24', '2025-07-29 09:49:06'),
(8, 'kennethmsosa', 'ictekwe@unilia.ac.mw', '$2y$10$x5YLiOS4tiWAOayCpqieLuHyZmD1WZAlybrvJqA7ChHNqdS6nVuxO', 'Kenneth', 'Msosa', 'user', 'Computer Science', '0991086161', NULL, NULL, NULL, NULL, 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-18 13:25:19', '2025-07-18 13:27:19'),
(10, 'kondwaniKennethMsosa12454', 'kkmsosa@unilia.ac.mw', '$2y$10$DVaEC7D4ZYdLWlLGCeogJe257qAWZd4fbsmBwPfXKv/hEko9Zkv86', 'Kondwani', 'Msosa', 'user', 'Computer Science', '0991086161', NULL, NULL, NULL, NULL, 0, NULL, NULL, 0, NULL, NULL, NULL, NULL, '2025-07-29 08:46:12', '2025-07-29 09:35:11');

-- --------------------------------------------------------

--
-- Table structure for table `user_preferences`
--

CREATE TABLE `user_preferences` (
  `preference_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notification_email` tinyint(1) DEFAULT 1,
  `notification_sms` tinyint(1) DEFAULT 0,
  `theme` enum('light','dark','auto') DEFAULT 'dark',
  `language` varchar(10) DEFAULT 'en',
  `timezone` varchar(50) DEFAULT 'UTC',
  `event_reminders` tinyint(1) DEFAULT 1,
  `marketing_emails` tinyint(1) DEFAULT 0,
  `accessibility_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`accessibility_settings`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_preferences`
--

INSERT INTO `user_preferences` (`preference_id`, `user_id`, `notification_email`, `notification_sms`, `theme`, `language`, `timezone`, `event_reminders`, `marketing_emails`, `accessibility_settings`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 0, 'dark', 'en', 'UTC', 1, 0, NULL, '2025-06-30 07:18:44', '2025-06-30 07:18:44'),
(2, 2, 1, 0, 'light', 'en', 'UTC', 1, 0, NULL, '2025-06-30 07:18:44', '2025-06-30 07:18:44'),
(3, 3, 0, 0, 'dark', 'en', 'UTC', 1, 0, NULL, '2025-06-30 07:18:44', '2025-06-30 07:18:44'),
(4, 4, 1, 0, 'auto', 'en', 'UTC', 0, 0, NULL, '2025-06-30 07:18:44', '2025-06-30 07:18:44');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `session_id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `expires_at` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `is_active` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`log_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `announcements`
--
ALTER TABLE `announcements`
  ADD PRIMARY KEY (`announcement_id`),
  ADD KEY `author_id` (`author_id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_active` (`is_active`),
  ADD KEY `idx_start_date` (`start_date`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`event_id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `organizer_id` (`organizer_id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_start_date` (`start_datetime`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_featured` (`featured`);

--
-- Indexes for table `event_proposals`
--
ALTER TABLE `event_proposals`
  ADD PRIMARY KEY (`proposal_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD PRIMARY KEY (`registration_id`),
  ADD UNIQUE KEY `unique_user_event` (`user_id`,`event_id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_event_id` (`event_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_ticket_number` (`ticket_number`);

--
-- Indexes for table `feedback_comment`
--
ALTER TABLE `feedback_comment`
  ADD PRIMARY KEY (`comment_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `feedback_rating`
--
ALTER TABLE `feedback_rating`
  ADD PRIMARY KEY (`rating_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `guest_rsvps`
--
ALTER TABLE `guest_rsvps`
  ADD PRIMARY KEY (`rsvp_id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `guest_subscribers`
--
ALTER TABLE `guest_subscribers`
  ADD PRIMARY KEY (`subscriber_id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`notification_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `ticket_id` (`ticket_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `tickets`
--
ALTER TABLE `tickets`
  ADD PRIMARY KEY (`ticket_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`user_id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_email_verified` (`email_verified`);

--
-- Indexes for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD PRIMARY KEY (`preference_id`),
  ADD UNIQUE KEY `unique_user_preference` (`user_id`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`session_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_expires` (`expires_at`),
  ADD KEY `idx_active` (`is_active`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `log_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `announcements`
--
ALTER TABLE `announcements`
  MODIFY `announcement_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `event_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `event_proposals`
--
ALTER TABLE `event_proposals`
  MODIFY `proposal_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `event_registrations`
--
ALTER TABLE `event_registrations`
  MODIFY `registration_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `feedback_comment`
--
ALTER TABLE `feedback_comment`
  MODIFY `comment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `feedback_rating`
--
ALTER TABLE `feedback_rating`
  MODIFY `rating_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `guest_rsvps`
--
ALTER TABLE `guest_rsvps`
  MODIFY `rsvp_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `guest_subscribers`
--
ALTER TABLE `guest_subscribers`
  MODIFY `subscriber_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `notification_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tickets`
--
ALTER TABLE `tickets`
  MODIFY `ticket_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `user_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `user_preferences`
--
ALTER TABLE `user_preferences`
  MODIFY `preference_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `announcements`
--
ALTER TABLE `announcements`
  ADD CONSTRAINT `announcements_ibfk_1` FOREIGN KEY (`author_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `events`
--
ALTER TABLE `events`
  ADD CONSTRAINT `events_ibfk_1` FOREIGN KEY (`organizer_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `event_proposals`
--
ALTER TABLE `event_proposals`
  ADD CONSTRAINT `event_proposals_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `event_registrations`
--
ALTER TABLE `event_registrations`
  ADD CONSTRAINT `event_registrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `event_registrations_ibfk_2` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE;

--
-- Constraints for table `feedback_comment`
--
ALTER TABLE `feedback_comment`
  ADD CONSTRAINT `feedback_comment_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_comment_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `feedback_rating`
--
ALTER TABLE `feedback_rating`
  ADD CONSTRAINT `feedback_rating_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `feedback_rating_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL;

--
-- Constraints for table `guest_rsvps`
--
ALTER TABLE `guest_rsvps`
  ADD CONSTRAINT `guest_rsvps_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`ticket_id`) ON DELETE SET NULL,
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `tickets`
--
ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`event_id`) REFERENCES `events` (`event_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `tickets_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_preferences`
--
ALTER TABLE `user_preferences`
  ADD CONSTRAINT `user_preferences_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`user_id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
