-- phpMyAdmin SQL Dump
-- version 5.2.3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3307
-- Generation Time: Aug 04, 2026 at 07:05 PM
-- Server version: 8.4.3
-- PHP Version: 8.3.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `markii_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `actor_user_id` bigint UNSIGNED DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` bigint UNSIGNED NOT NULL,
  `metadata_json` json DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `clinic_id`, `actor_user_id`, `action`, `entity_type`, `entity_id`, `metadata_json`, `created_at`) VALUES
(1, 1, 2, 'QUEUE_OPENED', 'queue', 1, '{\"queue_date\": \"2026-08-03\"}', '2026-08-03 08:00:00'),
(2, 1, 2, 'QUEUE_ENTRY_ADDED', 'queue_entry', 1, '{\"source\": \"secretary\", \"patient_id\": 1}', '2026-08-03 08:05:00'),
(3, 1, 2, 'VISIT_COMPLETED', 'visit', 1, '{\"doctor_id\": 1, \"patient_id\": 2, \"queue_entry_id\": 2}', '2026-08-03 08:45:00'),
(4, 1, 2, 'VISIT_COMPLETED', 'visit', 2, '{\"doctor_id\": 1, \"patient_id\": 5, \"queue_entry_id\": 5}', '2026-08-03 09:15:00'),
(5, 1, 3, 'VISIT_COMPLETED', 'visit', 3, '{\"doctor_id\": 1, \"patient_id\": 9, \"queue_entry_id\": 9}', '2026-08-03 10:00:00'),
(6, 1, 2, 'QUEUE_ENTRY_REJOINED', 'queue_entry', 13, '{\"patient_id\": 13, \"new_arrival_number\": 13, \"previous_arrival_number\": 6}', '2026-08-03 10:30:00'),
(7, 1, 1, 'SETTINGS_UPDATED', 'clinic', 1, '{\"doctor_id\": 1, \"new_timezone\": \"Africa/Algiers\"}', '2026-07-29 17:22:09'),
(8, 1, 1, 'USER_CREATED', 'user', 5, '{\"account_type\": \"doctor\"}', '2025-11-03 17:22:09'),
(9, 1, 1, 'USER_CREATED', 'user', 6, '{\"access_level\": \"queue_only\", \"account_type\": \"secretary\"}', '2026-08-03 17:22:09'),
(10, 1, 5, 'VISIT_COMPLETED', 'visit', 78, '{\"doctor_id\": 2, \"queue_entry_id\": 135}', '2026-08-03 09:20:00'),
(11, 1, 1, 'USER_LOGGED_IN', 'user', 1, '{\"ip_hash\": \"c955e288c5f98fc8845d127f714962f28315bfe7ef90ac683c76127e7e9aa6a6\", \"remembered_device\": false, \"selected_doctor_id\": 1}', '2026-08-03 17:27:39'),
(12, 1, 1, 'USER_LOGGED_IN', 'user', 1, '{\"ip_hash\": \"c955e288c5f98fc8845d127f714962f28315bfe7ef90ac683c76127e7e9aa6a6\", \"remembered_device\": false, \"selected_doctor_id\": 1}', '2026-08-03 18:03:53'),
(13, 1, 1, 'USER_LOGGED_OUT', 'user', 1, NULL, '2026-08-03 18:09:06'),
(14, 1, 2, 'USER_LOGGED_IN', 'user', 2, '{\"ip_hash\": \"c955e288c5f98fc8845d127f714962f28315bfe7ef90ac683c76127e7e9aa6a6\", \"remembered_device\": false, \"selected_doctor_id\": 1}', '2026-08-03 18:09:16'),
(15, 1, 2, 'USER_LOGGED_OUT', 'user', 2, NULL, '2026-08-03 18:09:25'),
(16, 1, 5, 'USER_LOGGED_IN', 'user', 5, '{\"ip_hash\": \"c955e288c5f98fc8845d127f714962f28315bfe7ef90ac683c76127e7e9aa6a6\", \"remembered_device\": false, \"selected_doctor_id\": 2}', '2026-08-03 18:09:35'),
(17, 1, 5, 'USER_LOGGED_OUT', 'user', 5, NULL, '2026-08-03 18:10:29'),
(18, 1, 3, 'USER_LOGGED_IN', 'user', 3, '{\"ip_hash\": \"c955e288c5f98fc8845d127f714962f28315bfe7ef90ac683c76127e7e9aa6a6\", \"remembered_device\": false, \"selected_doctor_id\": 1}', '2026-08-03 18:10:38'),
(19, 1, 3, 'USER_LOGGED_OUT', 'user', 3, NULL, '2026-08-03 18:10:43'),
(20, 1, 4, 'USER_LOGGED_IN', 'user', 4, '{\"ip_hash\": \"c955e288c5f98fc8845d127f714962f28315bfe7ef90ac683c76127e7e9aa6a6\", \"remembered_device\": false, \"selected_doctor_id\": 1}', '2026-08-03 18:10:51'),
(21, 1, 4, 'USER_LOGGED_OUT', 'user', 4, NULL, '2026-08-03 18:10:57'),
(22, 1, 1, 'USER_LOGGED_IN', 'user', 1, '{\"ip_hash\": \"c955e288c5f98fc8845d127f714962f28315bfe7ef90ac683c76127e7e9aa6a6\", \"remembered_device\": false, \"selected_doctor_id\": 1}', '2026-08-03 18:22:16'),
(23, 1, 1, 'USER_LOGGED_OUT', 'user', 1, NULL, '2026-08-03 18:33:57'),
(24, 1, 1, 'USER_LOGGED_IN', 'user', 1, '{\"ip_hash\": \"c955e288c5f98fc8845d127f714962f28315bfe7ef90ac683c76127e7e9aa6a6\", \"remembered_device\": false, \"selected_doctor_id\": 1}', '2026-08-03 18:34:45'),
(25, 1, 1, 'USER_LOGGED_IN', 'user', 1, '{\"ip_hash\": \"c955e288c5f98fc8845d127f714962f28315bfe7ef90ac683c76127e7e9aa6a6\", \"remembered_device\": false, \"selected_doctor_id\": 1}', '2026-08-04 15:50:58'),
(26, 1, 1, 'USER_LOGGED_IN', 'user', 1, '{\"ip_hash\": \"3002cad60b771b5020ff1199268edc45e10303c2f14db2c49c609bda774fb82e\", \"remembered_device\": false, \"selected_doctor_id\": 1}', '2026-08-04 19:56:19');

-- --------------------------------------------------------

--
-- Table structure for table `appointments`
--

CREATE TABLE `appointments` (
  `id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `doctor_id` bigint UNSIGNED NOT NULL,
  `patient_id` bigint UNSIGNED DEFAULT NULL,
  `display_name` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `start_at` datetime NOT NULL,
  `end_at` datetime DEFAULT NULL,
  `status` enum('scheduled','confirmed','done','canceled','no_show') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'scheduled',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_by_user_id` bigint UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `appointments`
--

INSERT INTO `appointments` (`id`, `clinic_id`, `doctor_id`, `patient_id`, `display_name`, `phone`, `start_at`, `end_at`, `status`, `reason`, `notes`, `created_by_user_id`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 21, 'Amina Ouali', '+213551000021', '2026-08-04 09:00:00', '2026-08-04 09:20:00', 'confirmed', 'Contrôle administratif', 'Rendez-vous de démonstration.', 2, '2026-08-03 17:22:09', '2026-08-03 17:22:09'),
(2, 1, 1, 24, 'Youcef Brahimi', '+213771000024', '2026-08-05 10:30:00', '2026-08-05 10:50:00', 'scheduled', 'Première consultation', NULL, 3, '2026-08-03 17:22:09', '2026-08-03 17:22:09');

-- --------------------------------------------------------

--
-- Table structure for table `billing_accounts`
--

CREATE TABLE `billing_accounts` (
  `id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `pricing_model` enum('per_visit','subscription','hybrid') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'per_visit',
  `currency` char(3) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'DZD',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `billing_accounts`
--

INSERT INTO `billing_accounts` (`id`, `clinic_id`, `pricing_model`, `currency`, `created_at`) VALUES
(1, 1, 'subscription', 'DZD', '2025-08-03 17:22:09');

-- --------------------------------------------------------

--
-- Table structure for table `billing_events`
--

CREATE TABLE `billing_events` (
  `id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `doctor_id` bigint UNSIGNED DEFAULT NULL,
  `visit_id` bigint UNSIGNED DEFAULT NULL,
  `event_type` enum('visit_done','sms_sent','subscription_fee') COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `status` enum('pending','invoiced','paid','void') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pending',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clinics`
--

CREATE TABLE `clinics` (
  `id` bigint UNSIGNED NOT NULL,
  `name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `slug` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` enum('solo','clinic','hospital_simple') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'solo',
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `wilaya` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `timezone` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Africa/Algiers',
  `status` enum('active','suspended') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clinics`
--

INSERT INTO `clinics` (`id`, `name`, `slug`, `type`, `address`, `city`, `wilaya`, `phone`, `timezone`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Clinique Médicale El Amal', 'clinique-el-amal', 'clinic', '18, rue Didouche Mourad', 'Alger Centre', 'Alger', '021234567', 'Africa/Algiers', 'active', '2025-08-03 17:22:09', '2026-08-03 17:22:09');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_profiles`
--

CREATE TABLE `doctor_profiles` (
  `id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `display_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `specialty` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `license_number` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `doctor_profiles`
--

INSERT INTO `doctor_profiles` (`id`, `clinic_id`, `user_id`, `display_name`, `specialty`, `license_number`, `address`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'Dr Karim Benali', 'Médecine générale', 'ALG-MG-24871', '18, rue Didouche Mourad, Alger Centre', 1, '2025-08-03 17:22:09', '2026-08-03 17:22:09'),
(2, 1, 5, 'Dr Leila Mansouri', 'Pédiatrie', 'ALG-PED-31904', '18, rue Didouche Mourad, Alger Centre', 1, '2025-11-03 17:22:09', '2026-08-03 17:22:09');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_public_registration_exceptions`
--

CREATE TABLE `doctor_public_registration_exceptions` (
  `id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `doctor_id` bigint UNSIGNED NOT NULL,
  `exception_date` date NOT NULL,
  `mode` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `slot_order` smallint UNSIGNED NOT NULL DEFAULT '1',
  `registration_open_time` time DEFAULT NULL,
  `registration_close_time` time DEFAULT NULL,
  `reason_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `public_message_override` varchar(500) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `doctor_public_registration_hours`
--

CREATE TABLE `doctor_public_registration_hours` (
  `id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `doctor_id` bigint UNSIGNED NOT NULL,
  `day_of_week` tinyint UNSIGNED NOT NULL,
  `slot_order` smallint UNSIGNED NOT NULL DEFAULT '1',
  `registration_open_time` time NOT NULL,
  `registration_close_time` time NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ;

--
-- Dumping data for table `doctor_public_registration_hours`
--

INSERT INTO `doctor_public_registration_hours` (`id`, `clinic_id`, `doctor_id`, `day_of_week`, `slot_order`, `registration_open_time`, `registration_close_time`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 1, '08:00:00', '12:00:00', 1, '2026-08-03 17:22:09', '2026-08-03 17:22:09'),
(2, 1, 1, 2, 1, '08:00:00', '12:00:00', 1, '2026-08-03 17:22:09', '2026-08-03 17:22:09'),
(3, 1, 1, 3, 1, '08:00:00', '12:00:00', 1, '2026-08-03 17:22:09', '2026-08-03 17:22:09'),
(4, 1, 1, 4, 1, '08:00:00', '12:00:00', 1, '2026-08-03 17:22:09', '2026-08-03 17:22:09'),
(5, 1, 1, 6, 1, '08:00:00', '12:00:00', 1, '2026-08-03 17:22:09', '2026-08-03 17:22:09');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_public_registration_messages`
--

CREATE TABLE `doctor_public_registration_messages` (
  `id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `doctor_id` bigint UNSIGNED NOT NULL,
  `message_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `message_text` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `doctor_public_registration_messages`
--

INSERT INTO `doctor_public_registration_messages` (`id`, `clinic_id`, `doctor_id`, `message_code`, `message_text`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 'registration_open', 'Les inscriptions sont ouvertes. Vous pouvez rejoindre la liste d’attente.', 1, '2026-08-03 17:22:09', '2026-08-03 17:22:09'),
(2, 1, 1, 'registration_closed', 'Les nouvelles inscriptions sont temporairement fermées. Veuillez contacter le cabinet.', 1, '2026-08-03 17:22:09', '2026-08-03 17:22:09'),
(3, 1, 1, 'queue_paused', 'La prise en charge est temporairement en pause.', 1, '2026-08-03 17:22:09', '2026-08-03 17:22:09'),
(4, 1, 1, 'day_completed', 'La liste d’attente est terminée pour aujourd’hui.', 1, '2026-08-03 17:22:09', '2026-08-03 17:22:09'),
(5, 1, 1, 'qr_disabled', 'L’inscription par QR code est temporairement indisponible.', 1, '2026-08-03 17:22:09', '2026-08-03 17:22:09'),
(6, 1, 1, 'outside_schedule', 'Les inscriptions en ligne ne sont pas disponibles à cette heure.', 1, '2026-08-03 17:22:09', '2026-08-03 17:22:09'),
(7, 1, 1, 'registration_success', 'Votre inscription à la liste d’attente a bien été enregistrée.', 1, '2026-08-03 17:22:09', '2026-08-03 17:22:09'),
(8, 1, 1, 'day_not_open', 'La liste du jour n’est pas encore ouverte. Veuillez revenir plus tard ou contacter le cabinet.', 1, '2026-08-03 18:03:59', '2026-08-03 18:03:59'),
(24, 1, 2, 'day_not_open', 'La liste du jour n’est pas encore ouverte. Veuillez revenir plus tard ou contacter le cabinet.', 1, '2026-08-03 18:09:41', '2026-08-03 18:09:41'),
(25, 1, 2, 'registration_open', 'Les inscriptions sont ouvertes. Vous pouvez rejoindre la liste d’attente.', 1, '2026-08-03 18:09:41', '2026-08-03 18:09:41'),
(26, 1, 2, 'registration_closed', 'Les nouvelles inscriptions sont temporairement fermées. Veuillez revenir plus tard ou contacter le cabinet.', 1, '2026-08-03 18:09:41', '2026-08-03 18:09:41'),
(27, 1, 2, 'queue_paused', 'La prise en charge est temporairement en pause. Les nouvelles inscriptions ne sont pas disponibles pour le moment.', 1, '2026-08-03 18:09:41', '2026-08-03 18:09:41'),
(28, 1, 2, 'day_completed', 'La liste d’attente est terminée pour aujourd’hui. Veuillez revenir lors de la prochaine journée d’ouverture.', 1, '2026-08-03 18:09:41', '2026-08-03 18:09:41'),
(29, 1, 2, 'qr_disabled', 'L’inscription par QR code est temporairement indisponible pour ce médecin.', 1, '2026-08-03 18:09:41', '2026-08-03 18:09:41'),
(30, 1, 2, 'outside_schedule', 'Les inscriptions en ligne ne sont pas disponibles à cette heure.', 1, '2026-08-03 18:09:41', '2026-08-03 18:09:41'),
(31, 1, 2, 'registration_success', 'Votre inscription à la liste d’attente a bien été enregistrée.', 1, '2026-08-03 18:09:42', '2026-08-03 18:09:42');

-- --------------------------------------------------------

--
-- Table structure for table `doctor_public_registration_settings`
--

CREATE TABLE `doctor_public_registration_settings` (
  `id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `doctor_id` bigint UNSIGNED NOT NULL,
  `public_registration_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `guest_registration_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `phone_required` tinyint(1) NOT NULL DEFAULT '1',
  `birth_date_required` tinyint(1) NOT NULL DEFAULT '0',
  `privacy_consent_required` tinyint(1) NOT NULL DEFAULT '1',
  `automatic_schedule_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `public_sessions_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `public_session_duration_minutes` int UNSIGNED NOT NULL DEFAULT '720',
  `queue_notifications_enabled` tinyint(1) NOT NULL DEFAULT '0',
  `max_public_registrations_per_day` int UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `doctor_public_registration_settings`
--

INSERT INTO `doctor_public_registration_settings` (`id`, `clinic_id`, `doctor_id`, `public_registration_enabled`, `guest_registration_enabled`, `phone_required`, `birth_date_required`, `privacy_consent_required`, `automatic_schedule_enabled`, `public_sessions_enabled`, `public_session_duration_minutes`, `queue_notifications_enabled`, `max_public_registrations_per_day`, `created_at`, `updated_at`) VALUES
(1, 1, 1, 1, 1, 1, 0, 1, 0, 1, 720, 0, 40, '2026-02-03 17:22:09', '2026-08-03 17:22:09'),
(2, 1, 2, 1, 1, 1, 0, 1, 0, 1, 720, 0, 30, '2026-02-03 17:22:09', '2026-08-03 17:22:09');

-- --------------------------------------------------------

--
-- Table structure for table `files`
--

CREATE TABLE `files` (
  `id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `patient_id` bigint UNSIGNED DEFAULT NULL,
  `visit_id` bigint UNSIGNED DEFAULT NULL,
  `file_type` enum('lab','imaging','other') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'other',
  `storage_path` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `original_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mime_type` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `size_bytes` bigint UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT '0.00',
  `status` enum('draft','sent','paid','overdue') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `invoice_items`
--

CREATE TABLE `invoice_items` (
  `id` bigint UNSIGNED NOT NULL,
  `invoice_id` bigint UNSIGNED NOT NULL,
  `billing_event_id` bigint UNSIGNED DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `amount` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `medical_records`
--

CREATE TABLE `medical_records` (
  `id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `visit_id` bigint UNSIGNED NOT NULL,
  `chief_complaint` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `diagnosis` text COLLATE utf8mb4_unicode_ci,
  `notes` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `channel` enum('sms','whatsapp','email','push') COLLATE utf8mb4_unicode_ci NOT NULL,
  `to_value` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_code` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload_json` json DEFAULT NULL,
  `status` enum('queued','sent','failed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queued',
  `provider_message_id` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `error_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_tokens`
--

CREATE TABLE `password_reset_tokens` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `selector` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `external_ref` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `full_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `birth_date` date DEFAULT NULL,
  `gender` enum('M','F','U') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notes_non_medical` text COLLATE utf8mb4_unicode_ci,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patients`
--

INSERT INTO `patients` (`id`, `clinic_id`, `external_ref`, `full_name`, `birth_date`, `gender`, `phone`, `email`, `address`, `notes_non_medical`, `created_at`, `updated_at`) VALUES
(1, 1, 'PAT-0001', 'Amine Benali', '1991-03-12', 'M', '+213551000001', 'amine.benali@example.test', 'Bab Ezzouar, Alger', 'Préfère être contacté par téléphone.', '2026-07-03 17:22:09', '2026-08-03 17:22:09'),
(2, 1, 'PAT-0002', 'Sara Khelifi', '1988-07-25', 'F', '+213551000002', 'sara.khelifi@example.test', 'Bir Mourad Raïs, Alger', 'Disponible surtout le matin.', '2026-07-02 17:22:09', '2026-08-03 17:22:09'),
(3, 1, 'PAT-0003', 'Nadia Alloune', '1994-01-18', 'F', '+213551000003', NULL, 'Blida', 'Téléphone partagé avec un membre de la famille.', '2026-07-01 17:22:09', '2026-08-03 17:22:09'),
(4, 1, 'PAT-0004', 'Karim Touati', '1985-11-03', 'M', '+213551000004', 'karim.touati@example.test', 'El Harrach, Alger', 'Aucun commentaire administratif.', '2026-06-30 17:22:09', '2026-08-03 17:22:09'),
(5, 1, 'PAT-0005', 'Yasmine Bensaid', '1997-05-09', 'F', '+213551000005', NULL, 'Sétif', 'Première visite enregistrée via la secrétaire.', '2026-06-29 17:22:09', '2026-08-03 17:22:09'),
(6, 1, 'PAT-0006', 'Walid Merabet', '1990-08-21', 'M', '+213551000006', 'walid.merabet@example.test', 'Constantine', 'Appeler avant toute modification de rendez-vous.', '2026-06-28 17:22:09', '2026-08-03 17:22:09'),
(7, 1, 'PAT-0007', 'Lina Rahmani', '1996-12-14', 'F', '+213551000007', NULL, 'Tipaza', 'Patiente régulière.', '2026-06-27 17:22:09', '2026-08-03 17:22:09'),
(8, 1, 'PAT-0008', 'Riad Cherif', '1983-04-30', 'M', '+213551000008', 'riad.cherif@example.test', 'Hydra, Alger', 'Se déplace depuis une autre commune.', '2026-06-26 17:22:09', '2026-08-03 17:22:09'),
(9, 1, 'PAT-0009', 'Meriem Hamdi', '1992-10-06', 'F', '+213551000009', NULL, 'Boumerdès', 'Contact téléphonique confirmé.', '2026-06-25 17:22:09', '2026-08-03 17:22:09'),
(10, 1, 'PAT-0010', 'Sofiane Meziane', '1989-02-27', 'M', '+213551000010', 'sofiane.meziane@example.test', 'Tizi Ouzou', 'Préfère les passages en fin de matinée.', '2026-06-24 17:22:09', '2026-08-03 17:22:09'),
(11, 1, 'PAT-0011', 'Samira Bouzid', '1978-06-16', 'F', '+213661000011', NULL, 'Kouba, Alger', 'Accompagnée habituellement par sa fille.', '2026-06-23 17:22:09', '2026-08-03 17:22:09'),
(12, 1, 'PAT-0012', 'Mourad Saadi', '1969-09-04', 'M', '+213661000012', NULL, 'Birkhadem, Alger', 'Numéro vérifié lors du dernier passage.', '2026-06-22 17:22:09', '2026-08-03 17:22:09'),
(13, 1, 'PAT-0013', 'Ines Belkacem', '2001-11-22', 'F', '+213661000013', 'ines.belkacem@example.test', 'Dely Ibrahim, Alger', 'Inscription publique testée.', '2026-06-21 17:22:09', '2026-08-03 17:22:09'),
(14, 1, 'PAT-0014', 'Abdelkader Boudiaf', '1958-01-30', 'M', '+213661000014', NULL, 'Béjaïa', 'Contact de la famille disponible sur demande.', '2026-06-20 17:22:09', '2026-08-03 17:22:09'),
(15, 1, 'PAT-0015', 'Lila Mansouri', '1981-04-08', 'F', '+213661000015', NULL, 'Draria, Alger', 'Patiente suivie régulièrement.', '2026-06-19 17:22:09', '2026-08-03 17:22:09'),
(16, 1, 'PAT-0016', 'Nabil Ait Ali', '1975-03-19', 'M', '+213661000016', 'nabil.aitali@example.test', 'Bouzareah, Alger', 'Adresse confirmée.', '2026-06-18 17:22:09', '2026-08-03 17:22:09'),
(17, 1, 'PAT-0017', 'Chahrazad Drikeche', '1993-08-11', 'F', '+213771000017', NULL, 'Rouiba, Alger', 'Téléphone principal uniquement.', '2026-06-17 17:22:09', '2026-08-03 17:22:09'),
(18, 1, 'PAT-0018', 'Hocine Lamri', '1987-02-05', 'M', '+213771000018', 'hocine.lamri@example.test', 'Réghaïa, Alger', 'Préfère recevoir les informations par courriel.', '2026-06-16 17:22:09', '2026-08-03 17:22:09'),
(19, 1, 'PAT-0019', 'Baya Ziani', '1965-12-02', 'F', '+213771000019', NULL, 'Cheraga, Alger', 'Venue plusieurs fois au cabinet.', '2026-06-15 17:22:09', '2026-08-03 17:22:09'),
(20, 1, 'PAT-0020', 'Samir Hadj', '1999-07-27', 'M', '+213771000020', 'samir.hadj@example.test', 'Bordj El Kiffan, Alger', 'Dossier administratif complet.', '2026-06-14 17:22:09', '2026-08-03 17:22:09'),
(21, 1, 'PAT-0021', 'Amina Ouali', '1986-10-13', 'F', '+213551000021', NULL, 'El Biar, Alger', 'Peut être ajoutée rapidement à la liste du jour.', '2026-06-13 17:22:09', '2026-08-03 17:22:09'),
(22, 1, 'PAT-0022', 'Farid Meziane', '1972-05-15', 'M', '+213551000022', NULL, 'Hussein Dey, Alger', 'Patient régulier.', '2026-06-12 17:22:09', '2026-08-03 17:22:09'),
(23, 1, 'PAT-0023', 'Kahina Ait Ahmed', '1995-09-09', 'F', '+213661000023', 'kahina.aitahmed@example.test', 'Aïn Benian, Alger', 'Données administratives mises à jour.', '2026-06-11 17:22:09', '2026-08-03 17:22:09'),
(24, 1, 'PAT-0024', 'Youcef Brahimi', '1980-01-21', 'M', '+213771000024', NULL, 'Dar El Beïda, Alger', 'Nouveau patient du mois.', '2026-06-10 17:22:09', '2026-08-03 17:22:09');

-- --------------------------------------------------------

--
-- Table structure for table `patient_contacts`
--

CREATE TABLE `patient_contacts` (
  `id` bigint UNSIGNED NOT NULL,
  `patient_id` bigint UNSIGNED NOT NULL,
  `type` enum('phone','email','emergency') COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_primary` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patient_contacts`
--

INSERT INTO `patient_contacts` (`id`, `patient_id`, `type`, `value`, `is_primary`, `created_at`) VALUES
(1, 1, 'phone', '+213551000001', 1, '2026-07-04 17:22:09'),
(2, 3, 'emergency', '+213551009903', 0, '2026-07-14 17:22:09'),
(3, 14, 'emergency', '+213661009914', 0, '2026-07-19 17:22:09');

-- --------------------------------------------------------

--
-- Table structure for table `patient_public_sessions`
--

CREATE TABLE `patient_public_sessions` (
  `id` bigint UNSIGNED NOT NULL,
  `queue_entry_id` bigint UNSIGNED NOT NULL,
  `session_token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_ip_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `patient_public_sessions`
--

INSERT INTO `patient_public_sessions` (`id`, `queue_entry_id`, `session_token_hash`, `expires_at`, `last_used_at`, `revoked_at`, `created_ip_hash`, `user_agent`, `created_at`, `updated_at`) VALUES
(1, 11, '98f13708210194c475687be6106a3b84f9b1f8b2f2b3e3b3cf841094c147f2bc', '2026-08-04 05:22:09', '2026-08-03 17:07:09', NULL, 'a3f5c4d2e1b09876543210fedcba9876543210fedcba9876543210fedcba9876', 'Mozilla/5.0 MARKI demo mobile', '2026-08-03 17:02:09', '2026-08-03 17:22:09');

-- --------------------------------------------------------

--
-- Table structure for table `platform_admins`
--

CREATE TABLE `platform_admins` (
  `id` bigint UNSIGNED NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','disabled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `failed_login_attempts` smallint UNSIGNED NOT NULL DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `password_changed_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `platform_admins`
--

INSERT INTO `platform_admins` (`id`, `email`, `password_hash`, `full_name`, `status`, `failed_login_attempts`, `locked_until`, `last_login_at`, `password_changed_at`, `created_at`, `updated_at`) VALUES
(1, 'platform@marki.test', '$2y$12$taYOGaBOlzZUE4bgzH8FLubMMFj2fv/kbumEcWcPyOEsDcbv3EgjC', 'Administrateur MARKI', 'active', 0, NULL, '2026-08-04 15:50:45', '2026-08-03 17:22:09', '2026-08-03 17:22:09', '2026-08-04 15:50:45');

-- --------------------------------------------------------

--
-- Table structure for table `platform_admin_activity_logs`
--

CREATE TABLE `platform_admin_activity_logs` (
  `id` bigint UNSIGNED NOT NULL,
  `platform_admin_id` bigint UNSIGNED DEFAULT NULL,
  `action` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `metadata_json` longtext COLLATE utf8mb4_unicode_ci,
  `ip_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `platform_admin_activity_logs`
--

INSERT INTO `platform_admin_activity_logs` (`id`, `platform_admin_id`, `action`, `metadata_json`, `ip_hash`, `created_at`) VALUES
(1, 1, 'PLATFORM_LOGIN_SUCCEEDED', '{\"remembered_device\":false}', 'c955e288c5f98fc8845d127f714962f28315bfe7ef90ac683c76127e7e9aa6a6', '2026-08-03 17:28:48'),
(2, 1, 'STRUCTURE_INVITATION_CREATED', '{\"invitation_id\":1,\"recipient_email\":null,\"expires_at\":\"2026-08-06 17:28:52\"}', NULL, '2026-08-03 17:28:52'),
(3, 1, 'PLATFORM_LOGIN_SUCCEEDED', '{\"remembered_device\":false}', 'c955e288c5f98fc8845d127f714962f28315bfe7ef90ac683c76127e7e9aa6a6', '2026-08-03 18:07:57'),
(4, 1, 'STRUCTURE_INVITATION_REVOKED', '{\"invitation_id\":1}', NULL, '2026-08-03 18:08:22'),
(5, 1, 'PLATFORM_LOGIN_SUCCEEDED', '{\"remembered_device\":false}', 'c955e288c5f98fc8845d127f714962f28315bfe7ef90ac683c76127e7e9aa6a6', '2026-08-03 18:12:03'),
(6, 1, 'PLATFORM_LOGOUT', NULL, 'c955e288c5f98fc8845d127f714962f28315bfe7ef90ac683c76127e7e9aa6a6', '2026-08-03 18:16:24'),
(7, 1, 'PLATFORM_LOGIN_SUCCEEDED', '{\"remembered_device\":false}', 'c955e288c5f98fc8845d127f714962f28315bfe7ef90ac683c76127e7e9aa6a6', '2026-08-03 18:18:01'),
(8, 1, 'PLATFORM_LOGIN_SUCCEEDED', '{\"remembered_device\":false}', 'c955e288c5f98fc8845d127f714962f28315bfe7ef90ac683c76127e7e9aa6a6', '2026-08-04 15:50:45');

-- --------------------------------------------------------

--
-- Table structure for table `platform_admin_sessions`
--

CREATE TABLE `platform_admin_sessions` (
  `id` bigint UNSIGNED NOT NULL,
  `platform_admin_id` bigint UNSIGNED NOT NULL,
  `selector` char(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `validator_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expires_at` datetime NOT NULL,
  `last_used_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `ip_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `visit_id` bigint UNSIGNED NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescription_items`
--

CREATE TABLE `prescription_items` (
  `id` bigint UNSIGNED NOT NULL,
  `prescription_id` bigint UNSIGNED NOT NULL,
  `medication_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dose` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `frequency` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `duration_days` int DEFAULT NULL,
  `instructions` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `public_links`
--

CREATE TABLE `public_links` (
  `id` bigint UNSIGNED NOT NULL,
  `public_id` char(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `doctor_id` bigint UNSIGNED NOT NULL,
  `type` enum('qr','public_link') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'qr',
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_version` smallint UNSIGNED NOT NULL DEFAULT '1',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `activated_at` datetime DEFAULT NULL,
  `created_by_user_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deactivated_at` datetime DEFAULT NULL,
  `deactivated_by_user_id` bigint UNSIGNED DEFAULT NULL,
  `last_scanned_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `revoked_by_user_id` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `public_links`
--

INSERT INTO `public_links` (`id`, `public_id`, `clinic_id`, `doctor_id`, `type`, `token_hash`, `token_version`, `is_active`, `activated_at`, `created_by_user_id`, `created_at`, `updated_at`, `deactivated_at`, `deactivated_by_user_id`, `last_scanned_at`, `revoked_at`, `revoked_by_user_id`) VALUES
(1, 'a1b2c3d4e5f6478899aabbccddeeff00', 1, 1, 'qr', 'f8e352e3c5bca7ae42340944e0f467bc10fc7dddb6c269e7dd0635e35eaa4f11', 1, 1, '2026-02-03 17:22:09', 1, '2026-02-03 17:22:09', '2026-08-04 15:51:26', NULL, NULL, '2026-08-04 15:51:26', NULL, NULL),
(2, 'f9ec1a54f5c2af8b006c9193d0777781', 1, 2, 'qr', 'cc6f1c4891781c8ade60c977105a25161c9860a4d2c4897b7f046ab68b688149', 1, 1, '2026-08-03 18:09:42', 5, '2026-08-03 18:09:42', '2026-08-03 18:22:50', NULL, NULL, '2026-08-03 18:22:50', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `public_link_events`
--

CREATE TABLE `public_link_events` (
  `id` bigint UNSIGNED NOT NULL,
  `public_link_id` bigint UNSIGNED NOT NULL,
  `queue_id` bigint UNSIGNED DEFAULT NULL,
  `queue_entry_id` bigint UNSIGNED DEFAULT NULL,
  `event_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `result_code` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `metadata_json` json DEFAULT NULL,
  `ip_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `public_link_events`
--

INSERT INTO `public_link_events` (`id`, `public_link_id`, `queue_id`, `queue_entry_id`, `event_type`, `result_code`, `metadata_json`, `ip_hash`, `user_agent`, `created_at`) VALUES
(1, 1, 1, NULL, 'scan', 'accepted', '{\"doctor_id\": 1}', 'a3f5c4d2e1b09876543210fedcba9876543210fedcba9876543210fedcba9876', 'Mozilla/5.0 MARKI demo mobile', '2026-08-03 17:00:09'),
(2, 1, 1, 11, 'registered', 'success', '{\"source\": \"qr\", \"patient_id\": 11}', 'a3f5c4d2e1b09876543210fedcba9876543210fedcba9876543210fedcba9876', 'Mozilla/5.0 MARKI demo mobile', '2026-08-03 17:02:09'),
(3, 1, 1, NULL, 'scan', 'accepted', '{\"doctor_id\": 1, \"availability_code\": \"registration_open\"}', '1d24aedb071f046a71ad728ba227b7a28bf4958b6c26b7cfdb0031e556b25f0f', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-03 18:04:18'),
(4, 2, 17, NULL, 'scan', 'accepted', '{\"doctor_id\": 2, \"availability_code\": \"registration_open\"}', 'd78d79adadd5c78ee26b78e3788e6023231cb57b7241549f6f3aa39889de643a', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-08-03 18:10:01'),
(5, 2, 17, NULL, 'scan', 'accepted', '{\"doctor_id\": 2, \"availability_code\": \"registration_open\"}', 'd78d79adadd5c78ee26b78e3788e6023231cb57b7241549f6f3aa39889de643a', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-08-03 18:22:38'),
(6, 2, 17, NULL, 'scan', 'accepted', '{\"doctor_id\": 2, \"availability_code\": \"registration_open\"}', 'd78d79adadd5c78ee26b78e3788e6023231cb57b7241549f6f3aa39889de643a', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-08-03 18:22:50'),
(7, 1, 1, NULL, 'scan', 'accepted', '{\"doctor_id\": 1, \"availability_code\": \"registration_open\"}', 'd78d79adadd5c78ee26b78e3788e6023231cb57b7241549f6f3aa39889de643a', 'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Mobile Safari/537.36', '2026-08-03 18:22:54'),
(8, 1, 19, NULL, 'scan', 'accepted', '{\"doctor_id\": 1, \"availability_code\": \"registration_open\"}', '1d24aedb071f046a71ad728ba227b7a28bf4958b6c26b7cfdb0031e556b25f0f', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36', '2026-08-04 15:51:26');

-- --------------------------------------------------------

--
-- Table structure for table `queues`
--

CREATE TABLE `queues` (
  `id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `doctor_id` bigint UNSIGNED NOT NULL,
  `queue_date` date NOT NULL,
  `registration_status` enum('open','closed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `day_status` enum('active','paused','completed') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `registration_status_before_completion` enum('open','closed') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `day_status_before_completion` enum('active','paused') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('open','closed','archived') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'open',
  `opened_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `paused_at` datetime DEFAULT NULL,
  `resumed_at` datetime DEFAULT NULL,
  `completed_at` datetime DEFAULT NULL,
  `opened_by_user_id` bigint UNSIGNED DEFAULT NULL,
  `closed_by_user_id` bigint UNSIGNED DEFAULT NULL,
  `paused_by_user_id` bigint UNSIGNED DEFAULT NULL,
  `completed_by_user_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `queues`
--

INSERT INTO `queues` (`id`, `clinic_id`, `doctor_id`, `queue_date`, `registration_status`, `day_status`, `registration_status_before_completion`, `day_status_before_completion`, `status`, `opened_at`, `closed_at`, `paused_at`, `resumed_at`, `completed_at`, `opened_by_user_id`, `closed_by_user_id`, `paused_by_user_id`, `completed_by_user_id`, `created_at`, `updated_at`) VALUES
(1, 1, 1, '2026-08-03', 'open', 'active', NULL, NULL, 'open', '2026-08-03 08:00:00', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-08-03 07:55:00', '2026-08-03 17:22:09'),
(2, 1, 1, '2026-08-02', 'closed', 'completed', 'closed', 'active', 'closed', '2026-08-02 08:00:00', '2026-08-02 12:30:00', NULL, NULL, '2026-08-02 13:00:00', 2, 2, NULL, 2, '2026-08-02 07:55:00', '2026-08-02 13:00:00'),
(3, 1, 1, '2026-08-01', 'closed', 'completed', 'closed', 'active', 'closed', '2026-08-01 08:00:00', '2026-08-01 12:30:00', NULL, NULL, '2026-08-01 13:00:00', 2, 3, NULL, 2, '2026-08-01 07:55:00', '2026-08-01 13:00:00'),
(4, 1, 1, '2026-07-31', 'closed', 'completed', 'closed', 'active', 'closed', '2026-07-31 08:00:00', '2026-07-31 12:30:00', NULL, NULL, '2026-07-31 13:00:00', 2, 2, NULL, 1, '2026-07-31 07:55:00', '2026-07-31 13:00:00'),
(5, 1, 1, '2026-07-30', 'closed', 'completed', 'closed', 'active', 'closed', '2026-07-30 08:00:00', '2026-07-30 12:30:00', NULL, NULL, '2026-07-30 13:00:00', 2, 3, NULL, 2, '2026-07-30 07:55:00', '2026-07-30 13:00:00'),
(6, 1, 1, '2026-07-29', 'closed', 'completed', 'closed', 'active', 'closed', '2026-07-29 08:00:00', '2026-07-29 12:30:00', '2026-07-29 09:55:00', '2026-07-29 10:10:00', '2026-07-29 13:00:00', 2, 2, 2, 2, '2026-07-29 07:55:00', '2026-07-29 13:00:00'),
(7, 1, 1, '2026-07-28', 'closed', 'completed', 'closed', 'active', 'closed', '2026-07-28 08:00:00', '2026-07-28 12:30:00', NULL, NULL, '2026-07-28 13:00:00', 2, 3, NULL, 1, '2026-07-28 07:55:00', '2026-07-28 13:00:00'),
(8, 1, 1, '2026-07-27', 'closed', 'paused', NULL, NULL, 'open', '2026-07-27 08:00:00', '2026-07-27 10:15:00', '2026-07-27 10:20:00', NULL, NULL, 2, 2, 2, NULL, '2026-07-27 07:55:00', '2026-07-27 10:20:00'),
(9, 1, 1, '2026-07-26', 'closed', 'completed', 'closed', 'active', 'closed', '2026-07-26 08:00:00', '2026-07-26 12:30:00', NULL, NULL, '2026-07-26 13:00:00', 2, 3, NULL, 2, '2026-07-26 07:55:00', '2026-07-26 13:00:00'),
(10, 1, 1, '2026-07-25', 'closed', 'completed', 'closed', 'active', 'closed', '2026-07-25 08:00:00', '2026-07-25 12:30:00', NULL, NULL, '2026-07-25 13:00:00', 2, 2, NULL, 1, '2026-07-25 07:55:00', '2026-07-25 13:00:00'),
(11, 1, 1, '2026-07-24', 'closed', 'completed', 'closed', 'active', 'closed', '2026-07-24 08:00:00', '2026-07-24 12:30:00', '2026-07-24 09:55:00', '2026-07-24 10:10:00', '2026-07-24 13:00:00', 2, 3, 2, 2, '2026-07-24 07:55:00', '2026-07-24 13:00:00'),
(12, 1, 1, '2026-07-23', 'closed', 'completed', 'closed', 'active', 'closed', '2026-07-23 08:00:00', '2026-07-23 12:30:00', NULL, NULL, '2026-07-23 13:00:00', 2, 2, NULL, 2, '2026-07-23 07:55:00', '2026-07-23 13:00:00'),
(13, 1, 1, '2026-07-22', 'closed', 'completed', 'closed', 'active', 'closed', '2026-07-22 08:00:00', '2026-07-22 12:30:00', NULL, NULL, '2026-07-22 13:00:00', 2, 3, NULL, 1, '2026-07-22 07:55:00', '2026-07-22 13:00:00'),
(14, 1, 1, '2026-07-21', 'closed', 'completed', 'closed', 'active', 'closed', '2026-07-21 08:00:00', '2026-07-21 12:30:00', NULL, NULL, '2026-07-21 13:00:00', 2, 2, NULL, 2, '2026-07-21 07:55:00', '2026-07-21 13:00:00'),
(15, 1, 1, '2026-07-20', 'closed', 'completed', 'closed', 'active', 'closed', '2026-07-20 08:00:00', '2026-07-20 12:30:00', NULL, NULL, '2026-07-20 13:00:00', 2, 3, NULL, 2, '2026-07-20 07:55:00', '2026-07-20 13:00:00'),
(16, 1, 1, '2026-07-19', 'closed', 'completed', 'closed', 'active', 'closed', '2026-07-19 08:00:00', '2026-07-19 12:30:00', '2026-07-19 09:55:00', '2026-07-19 10:10:00', '2026-07-19 13:00:00', 2, 2, 2, 1, '2026-07-19 07:55:00', '2026-07-19 13:00:00'),
(17, 1, 2, '2026-08-03', 'open', 'active', NULL, NULL, 'open', '2026-08-03 08:30:00', NULL, NULL, NULL, NULL, 2, NULL, NULL, NULL, '2026-08-03 08:25:00', '2026-08-03 17:22:09'),
(18, 1, 2, '2026-08-02', 'closed', 'completed', 'closed', 'active', 'closed', '2026-08-02 08:30:00', '2026-08-02 12:00:00', NULL, NULL, '2026-08-02 12:10:00', 2, 2, NULL, 5, '2026-08-02 08:25:00', '2026-08-02 12:10:00'),
(19, 1, 1, '2026-08-04', 'open', 'active', NULL, NULL, 'open', '2026-08-04 15:50:59', NULL, NULL, NULL, NULL, 1, NULL, NULL, NULL, '2026-08-04 15:50:59', '2026-08-04 15:50:59');

-- --------------------------------------------------------

--
-- Table structure for table `queue_entries`
--

CREATE TABLE `queue_entries` (
  `id` bigint UNSIGNED NOT NULL,
  `queue_id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `patient_id` bigint UNSIGNED DEFAULT NULL,
  `display_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `source` enum('secretary','doctor','qr','link') COLLATE utf8mb4_unicode_ci NOT NULL,
  `public_link_id` bigint UNSIGNED DEFAULT NULL,
  `status` enum('waiting','called','done','no_show','canceled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'waiting',
  `status_before_completion` enum('waiting','called') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `canceled_by_completion` tinyint(1) NOT NULL DEFAULT '0',
  `position_number` int DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `called_at` datetime DEFAULT NULL,
  `done_at` datetime DEFAULT NULL,
  `canceled_at` datetime DEFAULT NULL,
  `cancellation_reason` enum('patient_request','registration_error','doctor_unavailable','end_of_day','other') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `no_show_at` datetime DEFAULT NULL,
  `last_rejoined_at` datetime DEFAULT NULL COMMENT 'Dernière heure de réintégration après une absence',
  `created_by_user_id` bigint UNSIGNED DEFAULT NULL,
  `updated_by_user_id` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `queue_entries`
--

INSERT INTO `queue_entries` (`id`, `queue_id`, `clinic_id`, `patient_id`, `display_name`, `phone`, `birth_date`, `source`, `public_link_id`, `status`, `status_before_completion`, `canceled_by_completion`, `position_number`, `created_at`, `called_at`, `done_at`, `canceled_at`, `cancellation_reason`, `no_show_at`, `last_rejoined_at`, `created_by_user_id`, `updated_by_user_id`) VALUES
(1, 1, 1, 1, 'Amine Benali', '+213551000001', '1991-03-12', 'secretary', NULL, 'waiting', NULL, 0, 1, '2026-08-03 08:05:00', NULL, NULL, NULL, NULL, NULL, NULL, 2, 2),
(2, 1, 1, 2, 'Sara Khelifi', '+213551000002', '1988-07-25', 'secretary', NULL, 'done', NULL, 0, 2, '2026-08-03 08:10:00', NULL, '2026-08-03 08:45:00', NULL, NULL, NULL, NULL, 2, 2),
(3, 1, 1, 3, 'Nadia Alloune', '+213551000003', '1994-01-18', 'doctor', NULL, 'no_show', NULL, 0, 3, '2026-08-03 08:15:00', NULL, NULL, NULL, NULL, '2026-08-03 09:00:00', NULL, 1, 1),
(4, 1, 1, 4, 'Karim Touati', '+213551000004', '1985-11-03', 'secretary', NULL, 'waiting', NULL, 0, 4, '2026-08-03 08:20:00', NULL, NULL, NULL, NULL, NULL, NULL, 2, 2),
(5, 1, 1, 5, 'Yasmine Bensaid', '+213551000005', '1997-05-09', 'secretary', NULL, 'done', NULL, 0, 5, '2026-08-03 08:30:00', NULL, '2026-08-03 09:15:00', NULL, NULL, NULL, NULL, 2, 2),
(6, 1, 1, 6, 'Walid Merabet', '+213551000006', '1990-08-21', 'secretary', NULL, 'canceled', NULL, 0, 6, '2026-08-03 08:40:00', NULL, NULL, '2026-08-03 09:10:00', 'patient_request', NULL, NULL, 2, 2),
(7, 1, 1, 7, 'Lina Rahmani', '+213551000007', '1996-12-14', 'doctor', NULL, 'waiting', NULL, 0, 7, '2026-08-03 08:50:00', NULL, NULL, NULL, NULL, NULL, NULL, 1, 1),
(8, 1, 1, 8, 'Riad Cherif', '+213551000008', '1983-04-30', 'secretary', NULL, 'waiting', NULL, 0, 8, '2026-08-03 09:00:00', NULL, NULL, NULL, NULL, NULL, NULL, 3, 3),
(9, 1, 1, 9, 'Meriem Hamdi', '+213551000009', '1992-10-06', 'secretary', NULL, 'done', NULL, 0, 9, '2026-08-03 09:10:00', NULL, '2026-08-03 10:00:00', NULL, NULL, NULL, NULL, 3, 3),
(10, 1, 1, 10, 'Sofiane Meziane', '+213551000010', '1989-02-27', 'link', 1, 'waiting', NULL, 0, 10, '2026-08-03 09:20:00', NULL, NULL, NULL, NULL, NULL, NULL, 2, 2),
(11, 1, 1, 11, 'Samira Bouzid', '+213661000011', '1978-06-16', 'qr', 1, 'waiting', NULL, 0, 11, '2026-08-03 09:30:00', NULL, NULL, NULL, NULL, NULL, NULL, 2, 2),
(12, 1, 1, 12, 'Mourad Saadi', '+213661000012', '1969-09-04', 'secretary', NULL, 'waiting', NULL, 0, 12, '2026-08-03 09:40:00', NULL, NULL, NULL, NULL, NULL, NULL, 2, 2),
(13, 1, 1, 13, 'Ines Belkacem', '+213661000013', '2001-11-22', 'secretary', NULL, 'waiting', NULL, 0, 13, '2026-08-03 08:25:00', NULL, NULL, NULL, NULL, '2026-08-03 09:05:00', '2026-08-03 10:30:00', 2, 2),
(14, 2, 1, 4, 'Karim Touati', '+213551000004', '1985-11-03', 'secretary', NULL, 'done', NULL, 0, 1, '2026-08-02 08:00:00', NULL, '2026-08-02 08:35:00', NULL, NULL, NULL, NULL, 2, 2),
(15, 2, 1, 5, 'Yasmine Bensaid', '+213551000005', '1997-05-09', 'doctor', NULL, 'done', NULL, 0, 2, '2026-08-02 08:12:00', NULL, '2026-08-02 08:47:00', NULL, NULL, NULL, NULL, 1, 3),
(16, 2, 1, 6, 'Walid Merabet', '+213551000006', '1990-08-21', 'secretary', NULL, 'no_show', NULL, 0, 3, '2026-08-02 08:24:00', NULL, NULL, NULL, NULL, '2026-08-02 08:54:00', NULL, 2, 2),
(17, 2, 1, 7, 'Lina Rahmani', '+213551000007', '1996-12-14', 'qr', 1, 'done', NULL, 0, 4, '2026-08-02 08:36:00', NULL, '2026-08-02 09:11:00', NULL, NULL, NULL, NULL, 3, 3),
(18, 2, 1, 8, 'Riad Cherif', '+213551000008', '1983-04-30', 'secretary', NULL, 'canceled', NULL, 0, 5, '2026-08-02 08:48:00', NULL, NULL, '2026-08-02 09:03:00', 'patient_request', NULL, NULL, 2, 2),
(19, 2, 1, 9, 'Meriem Hamdi', '+213551000009', '1992-10-06', 'link', 1, 'done', NULL, 0, 6, '2026-08-02 09:00:00', NULL, '2026-08-02 09:35:00', NULL, NULL, NULL, NULL, 3, 3),
(20, 2, 1, 10, 'Sofiane Meziane', '+213551000010', '1989-02-27', 'secretary', NULL, 'done', NULL, 0, 7, '2026-08-02 09:12:00', NULL, '2026-08-02 09:47:00', NULL, NULL, NULL, NULL, 2, 2),
(21, 2, 1, 11, 'Samira Bouzid', '+213661000011', '1978-06-16', 'doctor', NULL, 'no_show', NULL, 0, 8, '2026-08-02 09:24:00', NULL, NULL, NULL, NULL, '2026-08-02 09:54:00', NULL, 1, 3),
(22, 3, 1, 7, 'Lina Rahmani', '+213551000007', '1996-12-14', 'secretary', NULL, 'done', NULL, 0, 1, '2026-08-01 08:00:00', NULL, '2026-08-01 08:35:00', NULL, NULL, NULL, NULL, 2, 2),
(23, 3, 1, 8, 'Riad Cherif', '+213551000008', '1983-04-30', 'doctor', NULL, 'done', NULL, 0, 2, '2026-08-01 08:12:00', NULL, '2026-08-01 08:47:00', NULL, NULL, NULL, NULL, 1, 3),
(24, 3, 1, 9, 'Meriem Hamdi', '+213551000009', '1992-10-06', 'secretary', NULL, 'no_show', NULL, 0, 3, '2026-08-01 08:24:00', NULL, NULL, NULL, NULL, '2026-08-01 08:54:00', NULL, 2, 2),
(25, 3, 1, 10, 'Sofiane Meziane', '+213551000010', '1989-02-27', 'qr', 1, 'done', NULL, 0, 4, '2026-08-01 08:36:00', NULL, '2026-08-01 09:11:00', NULL, NULL, NULL, NULL, 3, 3),
(26, 3, 1, 11, 'Samira Bouzid', '+213661000011', '1978-06-16', 'secretary', NULL, 'canceled', NULL, 0, 5, '2026-08-01 08:48:00', NULL, NULL, '2026-08-01 09:03:00', 'registration_error', NULL, NULL, 2, 2),
(27, 3, 1, 12, 'Mourad Saadi', '+213661000012', '1969-09-04', 'link', 1, 'done', NULL, 0, 6, '2026-08-01 09:00:00', NULL, '2026-08-01 09:35:00', NULL, NULL, NULL, NULL, 3, 3),
(28, 3, 1, 13, 'Ines Belkacem', '+213661000013', '2001-11-22', 'secretary', NULL, 'done', NULL, 0, 7, '2026-08-01 09:12:00', NULL, '2026-08-01 09:47:00', NULL, NULL, NULL, NULL, 2, 2),
(29, 3, 1, 14, 'Abdelkader Boudiaf', '+213661000014', '1958-01-30', 'doctor', NULL, 'no_show', NULL, 0, 8, '2026-08-01 09:24:00', NULL, NULL, NULL, NULL, '2026-08-01 09:54:00', NULL, 1, 3),
(30, 4, 1, 10, 'Sofiane Meziane', '+213551000010', '1989-02-27', 'secretary', NULL, 'done', NULL, 0, 1, '2026-07-31 08:00:00', NULL, '2026-07-31 08:35:00', NULL, NULL, NULL, NULL, 2, 2),
(31, 4, 1, 11, 'Samira Bouzid', '+213661000011', '1978-06-16', 'doctor', NULL, 'done', NULL, 0, 2, '2026-07-31 08:12:00', NULL, '2026-07-31 08:47:00', NULL, NULL, NULL, NULL, 1, 3),
(32, 4, 1, 12, 'Mourad Saadi', '+213661000012', '1969-09-04', 'secretary', NULL, 'no_show', NULL, 0, 3, '2026-07-31 08:24:00', NULL, NULL, NULL, NULL, '2026-07-31 08:54:00', NULL, 2, 2),
(33, 4, 1, 13, 'Ines Belkacem', '+213661000013', '2001-11-22', 'qr', 1, 'done', NULL, 0, 4, '2026-07-31 08:36:00', NULL, '2026-07-31 09:11:00', NULL, NULL, NULL, NULL, 3, 3),
(34, 4, 1, 14, 'Abdelkader Boudiaf', '+213661000014', '1958-01-30', 'secretary', NULL, 'canceled', NULL, 0, 5, '2026-07-31 08:48:00', NULL, NULL, '2026-07-31 09:03:00', 'patient_request', NULL, NULL, 2, 2),
(35, 4, 1, 15, 'Lila Mansouri', '+213661000015', '1981-04-08', 'link', 1, 'done', NULL, 0, 6, '2026-07-31 09:00:00', NULL, '2026-07-31 09:35:00', NULL, NULL, NULL, NULL, 3, 3),
(36, 4, 1, 16, 'Nabil Ait Ali', '+213661000016', '1975-03-19', 'secretary', NULL, 'done', NULL, 0, 7, '2026-07-31 09:12:00', NULL, '2026-07-31 09:47:00', NULL, NULL, NULL, NULL, 2, 2),
(37, 4, 1, 17, 'Chahrazad Drikeche', '+213771000017', '1993-08-11', 'doctor', NULL, 'no_show', NULL, 0, 8, '2026-07-31 09:24:00', NULL, NULL, NULL, NULL, '2026-07-31 09:54:00', NULL, 1, 3),
(38, 5, 1, 13, 'Ines Belkacem', '+213661000013', '2001-11-22', 'secretary', NULL, 'done', NULL, 0, 1, '2026-07-30 08:00:00', NULL, '2026-07-30 08:35:00', NULL, NULL, NULL, NULL, 2, 2),
(39, 5, 1, 14, 'Abdelkader Boudiaf', '+213661000014', '1958-01-30', 'doctor', NULL, 'done', NULL, 0, 2, '2026-07-30 08:12:00', NULL, '2026-07-30 08:47:00', NULL, NULL, NULL, NULL, 1, 3),
(40, 5, 1, 15, 'Lila Mansouri', '+213661000015', '1981-04-08', 'secretary', NULL, 'no_show', NULL, 0, 3, '2026-07-30 08:24:00', NULL, NULL, NULL, NULL, '2026-07-30 08:54:00', NULL, 2, 2),
(41, 5, 1, 16, 'Nabil Ait Ali', '+213661000016', '1975-03-19', 'qr', 1, 'done', NULL, 0, 4, '2026-07-30 08:36:00', NULL, '2026-07-30 09:11:00', NULL, NULL, NULL, NULL, 3, 3),
(42, 5, 1, 17, 'Chahrazad Drikeche', '+213771000017', '1993-08-11', 'secretary', NULL, 'canceled', NULL, 0, 5, '2026-07-30 08:48:00', NULL, NULL, '2026-07-30 09:03:00', 'registration_error', NULL, NULL, 2, 2),
(43, 5, 1, 18, 'Hocine Lamri', '+213771000018', '1987-02-05', 'link', 1, 'done', NULL, 0, 6, '2026-07-30 09:00:00', NULL, '2026-07-30 09:35:00', NULL, NULL, NULL, NULL, 3, 3),
(44, 5, 1, 19, 'Baya Ziani', '+213771000019', '1965-12-02', 'secretary', NULL, 'done', NULL, 0, 7, '2026-07-30 09:12:00', NULL, '2026-07-30 09:47:00', NULL, NULL, NULL, NULL, 2, 2),
(45, 5, 1, 20, 'Samir Hadj', '+213771000020', '1999-07-27', 'doctor', NULL, 'no_show', NULL, 0, 8, '2026-07-30 09:24:00', NULL, NULL, NULL, NULL, '2026-07-30 09:54:00', NULL, 1, 3),
(46, 6, 1, 16, 'Nabil Ait Ali', '+213661000016', '1975-03-19', 'secretary', NULL, 'done', NULL, 0, 1, '2026-07-29 08:00:00', NULL, '2026-07-29 08:35:00', NULL, NULL, NULL, NULL, 2, 2),
(47, 6, 1, 17, 'Chahrazad Drikeche', '+213771000017', '1993-08-11', 'doctor', NULL, 'done', NULL, 0, 2, '2026-07-29 08:12:00', NULL, '2026-07-29 08:47:00', NULL, NULL, NULL, NULL, 1, 3),
(48, 6, 1, 18, 'Hocine Lamri', '+213771000018', '1987-02-05', 'secretary', NULL, 'no_show', NULL, 0, 3, '2026-07-29 08:24:00', NULL, NULL, NULL, NULL, '2026-07-29 08:54:00', NULL, 2, 2),
(49, 6, 1, 19, 'Baya Ziani', '+213771000019', '1965-12-02', 'qr', 1, 'done', NULL, 0, 4, '2026-07-29 08:36:00', NULL, '2026-07-29 09:11:00', NULL, NULL, NULL, NULL, 3, 3),
(50, 6, 1, 20, 'Samir Hadj', '+213771000020', '1999-07-27', 'secretary', NULL, 'canceled', NULL, 0, 5, '2026-07-29 08:48:00', NULL, NULL, '2026-07-29 09:03:00', 'patient_request', NULL, NULL, 2, 2),
(51, 6, 1, 21, 'Amina Ouali', '+213551000021', '1986-10-13', 'link', 1, 'done', NULL, 0, 6, '2026-07-29 09:00:00', NULL, '2026-07-29 09:35:00', NULL, NULL, NULL, NULL, 3, 3),
(52, 6, 1, 22, 'Farid Meziane', '+213551000022', '1972-05-15', 'secretary', NULL, 'done', NULL, 0, 7, '2026-07-29 09:12:00', NULL, '2026-07-29 09:47:00', NULL, NULL, NULL, NULL, 2, 2),
(53, 6, 1, 23, 'Kahina Ait Ahmed', '+213661000023', '1995-09-09', 'doctor', NULL, 'no_show', NULL, 0, 8, '2026-07-29 09:24:00', NULL, NULL, NULL, NULL, '2026-07-29 09:54:00', NULL, 1, 3),
(54, 7, 1, 19, 'Baya Ziani', '+213771000019', '1965-12-02', 'secretary', NULL, 'done', NULL, 0, 1, '2026-07-28 08:00:00', NULL, '2026-07-28 08:35:00', NULL, NULL, NULL, NULL, 2, 2),
(55, 7, 1, 20, 'Samir Hadj', '+213771000020', '1999-07-27', 'doctor', NULL, 'done', NULL, 0, 2, '2026-07-28 08:12:00', NULL, '2026-07-28 08:47:00', NULL, NULL, NULL, NULL, 1, 3),
(56, 7, 1, 21, 'Amina Ouali', '+213551000021', '1986-10-13', 'secretary', NULL, 'no_show', NULL, 0, 3, '2026-07-28 08:24:00', NULL, NULL, NULL, NULL, '2026-07-28 08:54:00', NULL, 2, 2),
(57, 7, 1, 22, 'Farid Meziane', '+213551000022', '1972-05-15', 'qr', 1, 'done', NULL, 0, 4, '2026-07-28 08:36:00', NULL, '2026-07-28 09:11:00', NULL, NULL, NULL, NULL, 3, 3),
(58, 7, 1, 23, 'Kahina Ait Ahmed', '+213661000023', '1995-09-09', 'secretary', NULL, 'canceled', NULL, 0, 5, '2026-07-28 08:48:00', NULL, NULL, '2026-07-28 09:03:00', 'registration_error', NULL, NULL, 2, 2),
(59, 7, 1, 24, 'Youcef Brahimi', '+213771000024', '1980-01-21', 'link', 1, 'done', NULL, 0, 6, '2026-07-28 09:00:00', NULL, '2026-07-28 09:35:00', NULL, NULL, NULL, NULL, 3, 3),
(60, 7, 1, 1, 'Amine Benali', '+213551000001', '1991-03-12', 'secretary', NULL, 'done', NULL, 0, 7, '2026-07-28 09:12:00', NULL, '2026-07-28 09:47:00', NULL, NULL, NULL, NULL, 2, 2),
(61, 7, 1, 2, 'Sara Khelifi', '+213551000002', '1988-07-25', 'doctor', NULL, 'no_show', NULL, 0, 8, '2026-07-28 09:24:00', NULL, NULL, NULL, NULL, '2026-07-28 09:54:00', NULL, 1, 3),
(62, 8, 1, 22, 'Farid Meziane', '+213551000022', '1972-05-15', 'secretary', NULL, 'done', NULL, 0, 1, '2026-07-27 08:00:00', NULL, '2026-07-27 08:35:00', NULL, NULL, NULL, NULL, 2, 2),
(63, 8, 1, 23, 'Kahina Ait Ahmed', '+213661000023', '1995-09-09', 'doctor', NULL, 'done', NULL, 0, 2, '2026-07-27 08:12:00', NULL, '2026-07-27 08:47:00', NULL, NULL, NULL, NULL, 1, 3),
(64, 8, 1, 24, 'Youcef Brahimi', '+213771000024', '1980-01-21', 'secretary', NULL, 'no_show', NULL, 0, 3, '2026-07-27 08:24:00', NULL, NULL, NULL, NULL, '2026-07-27 08:54:00', NULL, 2, 2),
(65, 8, 1, 1, 'Amine Benali', '+213551000001', '1991-03-12', 'qr', 1, 'done', NULL, 0, 4, '2026-07-27 08:36:00', NULL, '2026-07-27 09:11:00', NULL, NULL, NULL, NULL, 3, 3),
(66, 8, 1, 2, 'Sara Khelifi', '+213551000002', '1988-07-25', 'secretary', NULL, 'canceled', NULL, 0, 5, '2026-07-27 08:48:00', NULL, NULL, '2026-07-27 09:03:00', 'patient_request', NULL, NULL, 2, 2),
(67, 8, 1, 3, 'Nadia Alloune', '+213551000003', '1994-01-18', 'link', 1, 'done', NULL, 0, 6, '2026-07-27 09:00:00', NULL, '2026-07-27 09:35:00', NULL, NULL, NULL, NULL, 3, 3),
(68, 8, 1, 4, 'Karim Touati', '+213551000004', '1985-11-03', 'secretary', NULL, 'waiting', NULL, 0, 7, '2026-07-27 09:12:00', NULL, NULL, NULL, NULL, NULL, NULL, 2, 2),
(69, 8, 1, 5, 'Yasmine Bensaid', '+213551000005', '1997-05-09', 'doctor', NULL, 'waiting', NULL, 0, 8, '2026-07-27 09:24:00', NULL, NULL, NULL, NULL, NULL, NULL, 1, 3),
(70, 9, 1, 1, 'Amine Benali', '+213551000001', '1991-03-12', 'secretary', NULL, 'done', NULL, 0, 1, '2026-07-26 08:00:00', NULL, '2026-07-26 08:35:00', NULL, NULL, NULL, NULL, 2, 2),
(71, 9, 1, 2, 'Sara Khelifi', '+213551000002', '1988-07-25', 'doctor', NULL, 'done', NULL, 0, 2, '2026-07-26 08:12:00', NULL, '2026-07-26 08:47:00', NULL, NULL, NULL, NULL, 1, 3),
(72, 9, 1, 3, 'Nadia Alloune', '+213551000003', '1994-01-18', 'secretary', NULL, 'no_show', NULL, 0, 3, '2026-07-26 08:24:00', NULL, NULL, NULL, NULL, '2026-07-26 08:54:00', NULL, 2, 2),
(73, 9, 1, 4, 'Karim Touati', '+213551000004', '1985-11-03', 'qr', 1, 'done', NULL, 0, 4, '2026-07-26 08:36:00', NULL, '2026-07-26 09:11:00', NULL, NULL, NULL, NULL, 3, 3),
(74, 9, 1, 5, 'Yasmine Bensaid', '+213551000005', '1997-05-09', 'secretary', NULL, 'canceled', NULL, 0, 5, '2026-07-26 08:48:00', NULL, NULL, '2026-07-26 09:03:00', 'registration_error', NULL, NULL, 2, 2),
(75, 9, 1, 6, 'Walid Merabet', '+213551000006', '1990-08-21', 'link', 1, 'done', NULL, 0, 6, '2026-07-26 09:00:00', NULL, '2026-07-26 09:35:00', NULL, NULL, NULL, NULL, 3, 3),
(76, 9, 1, 7, 'Lina Rahmani', '+213551000007', '1996-12-14', 'secretary', NULL, 'done', NULL, 0, 7, '2026-07-26 09:12:00', NULL, '2026-07-26 09:47:00', NULL, NULL, NULL, NULL, 2, 2),
(77, 9, 1, 8, 'Riad Cherif', '+213551000008', '1983-04-30', 'doctor', NULL, 'no_show', NULL, 0, 8, '2026-07-26 09:24:00', NULL, NULL, NULL, NULL, '2026-07-26 09:54:00', NULL, 1, 3),
(78, 10, 1, 4, 'Karim Touati', '+213551000004', '1985-11-03', 'secretary', NULL, 'done', NULL, 0, 1, '2026-07-25 08:00:00', NULL, '2026-07-25 08:35:00', NULL, NULL, NULL, NULL, 2, 2),
(79, 10, 1, 5, 'Yasmine Bensaid', '+213551000005', '1997-05-09', 'doctor', NULL, 'done', NULL, 0, 2, '2026-07-25 08:12:00', NULL, '2026-07-25 08:47:00', NULL, NULL, NULL, NULL, 1, 3),
(80, 10, 1, 6, 'Walid Merabet', '+213551000006', '1990-08-21', 'secretary', NULL, 'no_show', NULL, 0, 3, '2026-07-25 08:24:00', NULL, NULL, NULL, NULL, '2026-07-25 08:54:00', NULL, 2, 2),
(81, 10, 1, 7, 'Lina Rahmani', '+213551000007', '1996-12-14', 'qr', 1, 'done', NULL, 0, 4, '2026-07-25 08:36:00', NULL, '2026-07-25 09:11:00', NULL, NULL, NULL, NULL, 3, 3),
(82, 10, 1, 8, 'Riad Cherif', '+213551000008', '1983-04-30', 'secretary', NULL, 'canceled', NULL, 0, 5, '2026-07-25 08:48:00', NULL, NULL, '2026-07-25 09:03:00', 'patient_request', NULL, NULL, 2, 2),
(83, 10, 1, 9, 'Meriem Hamdi', '+213551000009', '1992-10-06', 'link', 1, 'done', NULL, 0, 6, '2026-07-25 09:00:00', NULL, '2026-07-25 09:35:00', NULL, NULL, NULL, NULL, 3, 3),
(84, 10, 1, 10, 'Sofiane Meziane', '+213551000010', '1989-02-27', 'secretary', NULL, 'done', NULL, 0, 7, '2026-07-25 09:12:00', NULL, '2026-07-25 09:47:00', NULL, NULL, NULL, NULL, 2, 2),
(85, 10, 1, 11, 'Samira Bouzid', '+213661000011', '1978-06-16', 'doctor', NULL, 'no_show', NULL, 0, 8, '2026-07-25 09:24:00', NULL, NULL, NULL, NULL, '2026-07-25 09:54:00', NULL, 1, 3),
(86, 11, 1, 7, 'Lina Rahmani', '+213551000007', '1996-12-14', 'secretary', NULL, 'done', NULL, 0, 1, '2026-07-24 08:00:00', NULL, '2026-07-24 08:35:00', NULL, NULL, NULL, NULL, 2, 2),
(87, 11, 1, 8, 'Riad Cherif', '+213551000008', '1983-04-30', 'doctor', NULL, 'done', NULL, 0, 2, '2026-07-24 08:12:00', NULL, '2026-07-24 08:47:00', NULL, NULL, NULL, NULL, 1, 3),
(88, 11, 1, 9, 'Meriem Hamdi', '+213551000009', '1992-10-06', 'secretary', NULL, 'no_show', NULL, 0, 3, '2026-07-24 08:24:00', NULL, NULL, NULL, NULL, '2026-07-24 08:54:00', NULL, 2, 2),
(89, 11, 1, 10, 'Sofiane Meziane', '+213551000010', '1989-02-27', 'qr', 1, 'done', NULL, 0, 4, '2026-07-24 08:36:00', NULL, '2026-07-24 09:11:00', NULL, NULL, NULL, NULL, 3, 3),
(90, 11, 1, 11, 'Samira Bouzid', '+213661000011', '1978-06-16', 'secretary', NULL, 'canceled', NULL, 0, 5, '2026-07-24 08:48:00', NULL, NULL, '2026-07-24 09:03:00', 'registration_error', NULL, NULL, 2, 2),
(91, 11, 1, 12, 'Mourad Saadi', '+213661000012', '1969-09-04', 'link', 1, 'done', NULL, 0, 6, '2026-07-24 09:00:00', NULL, '2026-07-24 09:35:00', NULL, NULL, NULL, NULL, 3, 3),
(92, 11, 1, 13, 'Ines Belkacem', '+213661000013', '2001-11-22', 'secretary', NULL, 'done', NULL, 0, 7, '2026-07-24 09:12:00', NULL, '2026-07-24 09:47:00', NULL, NULL, NULL, NULL, 2, 2),
(93, 11, 1, 14, 'Abdelkader Boudiaf', '+213661000014', '1958-01-30', 'doctor', NULL, 'no_show', NULL, 0, 8, '2026-07-24 09:24:00', NULL, NULL, NULL, NULL, '2026-07-24 09:54:00', NULL, 1, 3),
(94, 12, 1, 10, 'Sofiane Meziane', '+213551000010', '1989-02-27', 'secretary', NULL, 'done', NULL, 0, 1, '2026-07-23 08:00:00', NULL, '2026-07-23 08:35:00', NULL, NULL, NULL, NULL, 2, 2),
(95, 12, 1, 11, 'Samira Bouzid', '+213661000011', '1978-06-16', 'doctor', NULL, 'done', NULL, 0, 2, '2026-07-23 08:12:00', NULL, '2026-07-23 08:47:00', NULL, NULL, NULL, NULL, 1, 3),
(96, 12, 1, 12, 'Mourad Saadi', '+213661000012', '1969-09-04', 'secretary', NULL, 'no_show', NULL, 0, 3, '2026-07-23 08:24:00', NULL, NULL, NULL, NULL, '2026-07-23 08:54:00', NULL, 2, 2),
(97, 12, 1, 13, 'Ines Belkacem', '+213661000013', '2001-11-22', 'qr', 1, 'done', NULL, 0, 4, '2026-07-23 08:36:00', NULL, '2026-07-23 09:11:00', NULL, NULL, NULL, NULL, 3, 3),
(98, 12, 1, 14, 'Abdelkader Boudiaf', '+213661000014', '1958-01-30', 'secretary', NULL, 'canceled', NULL, 0, 5, '2026-07-23 08:48:00', NULL, NULL, '2026-07-23 09:03:00', 'patient_request', NULL, NULL, 2, 2),
(99, 12, 1, 15, 'Lila Mansouri', '+213661000015', '1981-04-08', 'link', 1, 'done', NULL, 0, 6, '2026-07-23 09:00:00', NULL, '2026-07-23 09:35:00', NULL, NULL, NULL, NULL, 3, 3),
(100, 12, 1, 16, 'Nabil Ait Ali', '+213661000016', '1975-03-19', 'secretary', NULL, 'done', NULL, 0, 7, '2026-07-23 09:12:00', NULL, '2026-07-23 09:47:00', NULL, NULL, NULL, NULL, 2, 2),
(101, 12, 1, 17, 'Chahrazad Drikeche', '+213771000017', '1993-08-11', 'doctor', NULL, 'no_show', NULL, 0, 8, '2026-07-23 09:24:00', NULL, NULL, NULL, NULL, '2026-07-23 09:54:00', NULL, 1, 3),
(102, 13, 1, 13, 'Ines Belkacem', '+213661000013', '2001-11-22', 'secretary', NULL, 'done', NULL, 0, 1, '2026-07-22 08:00:00', NULL, '2026-07-22 08:35:00', NULL, NULL, NULL, NULL, 2, 2),
(103, 13, 1, 14, 'Abdelkader Boudiaf', '+213661000014', '1958-01-30', 'doctor', NULL, 'done', NULL, 0, 2, '2026-07-22 08:12:00', NULL, '2026-07-22 08:47:00', NULL, NULL, NULL, NULL, 1, 3),
(104, 13, 1, 15, 'Lila Mansouri', '+213661000015', '1981-04-08', 'secretary', NULL, 'no_show', NULL, 0, 3, '2026-07-22 08:24:00', NULL, NULL, NULL, NULL, '2026-07-22 08:54:00', NULL, 2, 2),
(105, 13, 1, 16, 'Nabil Ait Ali', '+213661000016', '1975-03-19', 'qr', 1, 'done', NULL, 0, 4, '2026-07-22 08:36:00', NULL, '2026-07-22 09:11:00', NULL, NULL, NULL, NULL, 3, 3),
(106, 13, 1, 17, 'Chahrazad Drikeche', '+213771000017', '1993-08-11', 'secretary', NULL, 'canceled', NULL, 0, 5, '2026-07-22 08:48:00', NULL, NULL, '2026-07-22 09:03:00', 'registration_error', NULL, NULL, 2, 2),
(107, 13, 1, 18, 'Hocine Lamri', '+213771000018', '1987-02-05', 'link', 1, 'done', NULL, 0, 6, '2026-07-22 09:00:00', NULL, '2026-07-22 09:35:00', NULL, NULL, NULL, NULL, 3, 3),
(108, 13, 1, 19, 'Baya Ziani', '+213771000019', '1965-12-02', 'secretary', NULL, 'done', NULL, 0, 7, '2026-07-22 09:12:00', NULL, '2026-07-22 09:47:00', NULL, NULL, NULL, NULL, 2, 2),
(109, 13, 1, 20, 'Samir Hadj', '+213771000020', '1999-07-27', 'doctor', NULL, 'no_show', NULL, 0, 8, '2026-07-22 09:24:00', NULL, NULL, NULL, NULL, '2026-07-22 09:54:00', NULL, 1, 3),
(110, 14, 1, 16, 'Nabil Ait Ali', '+213661000016', '1975-03-19', 'secretary', NULL, 'done', NULL, 0, 1, '2026-07-21 08:00:00', NULL, '2026-07-21 08:35:00', NULL, NULL, NULL, NULL, 2, 2),
(111, 14, 1, 17, 'Chahrazad Drikeche', '+213771000017', '1993-08-11', 'doctor', NULL, 'done', NULL, 0, 2, '2026-07-21 08:12:00', NULL, '2026-07-21 08:47:00', NULL, NULL, NULL, NULL, 1, 3),
(112, 14, 1, 18, 'Hocine Lamri', '+213771000018', '1987-02-05', 'secretary', NULL, 'no_show', NULL, 0, 3, '2026-07-21 08:24:00', NULL, NULL, NULL, NULL, '2026-07-21 08:54:00', NULL, 2, 2),
(113, 14, 1, 19, 'Baya Ziani', '+213771000019', '1965-12-02', 'qr', 1, 'done', NULL, 0, 4, '2026-07-21 08:36:00', NULL, '2026-07-21 09:11:00', NULL, NULL, NULL, NULL, 3, 3),
(114, 14, 1, 20, 'Samir Hadj', '+213771000020', '1999-07-27', 'secretary', NULL, 'canceled', NULL, 0, 5, '2026-07-21 08:48:00', NULL, NULL, '2026-07-21 09:03:00', 'patient_request', NULL, NULL, 2, 2),
(115, 14, 1, 21, 'Amina Ouali', '+213551000021', '1986-10-13', 'link', 1, 'done', NULL, 0, 6, '2026-07-21 09:00:00', NULL, '2026-07-21 09:35:00', NULL, NULL, NULL, NULL, 3, 3),
(116, 14, 1, 22, 'Farid Meziane', '+213551000022', '1972-05-15', 'secretary', NULL, 'done', NULL, 0, 7, '2026-07-21 09:12:00', NULL, '2026-07-21 09:47:00', NULL, NULL, NULL, NULL, 2, 2),
(117, 14, 1, 23, 'Kahina Ait Ahmed', '+213661000023', '1995-09-09', 'doctor', NULL, 'no_show', NULL, 0, 8, '2026-07-21 09:24:00', NULL, NULL, NULL, NULL, '2026-07-21 09:54:00', NULL, 1, 3),
(118, 15, 1, 19, 'Baya Ziani', '+213771000019', '1965-12-02', 'secretary', NULL, 'done', NULL, 0, 1, '2026-07-20 08:00:00', NULL, '2026-07-20 08:35:00', NULL, NULL, NULL, NULL, 2, 2),
(119, 15, 1, 20, 'Samir Hadj', '+213771000020', '1999-07-27', 'doctor', NULL, 'done', NULL, 0, 2, '2026-07-20 08:12:00', NULL, '2026-07-20 08:47:00', NULL, NULL, NULL, NULL, 1, 3),
(120, 15, 1, 21, 'Amina Ouali', '+213551000021', '1986-10-13', 'secretary', NULL, 'no_show', NULL, 0, 3, '2026-07-20 08:24:00', NULL, NULL, NULL, NULL, '2026-07-20 08:54:00', NULL, 2, 2),
(121, 15, 1, 22, 'Farid Meziane', '+213551000022', '1972-05-15', 'qr', 1, 'done', NULL, 0, 4, '2026-07-20 08:36:00', NULL, '2026-07-20 09:11:00', NULL, NULL, NULL, NULL, 3, 3),
(122, 15, 1, 23, 'Kahina Ait Ahmed', '+213661000023', '1995-09-09', 'secretary', NULL, 'canceled', NULL, 0, 5, '2026-07-20 08:48:00', NULL, NULL, '2026-07-20 09:03:00', 'registration_error', NULL, NULL, 2, 2),
(123, 15, 1, 24, 'Youcef Brahimi', '+213771000024', '1980-01-21', 'link', 1, 'done', NULL, 0, 6, '2026-07-20 09:00:00', NULL, '2026-07-20 09:35:00', NULL, NULL, NULL, NULL, 3, 3),
(124, 15, 1, 1, 'Amine Benali', '+213551000001', '1991-03-12', 'secretary', NULL, 'done', NULL, 0, 7, '2026-07-20 09:12:00', NULL, '2026-07-20 09:47:00', NULL, NULL, NULL, NULL, 2, 2),
(125, 15, 1, 2, 'Sara Khelifi', '+213551000002', '1988-07-25', 'doctor', NULL, 'no_show', NULL, 0, 8, '2026-07-20 09:24:00', NULL, NULL, NULL, NULL, '2026-07-20 09:54:00', NULL, 1, 3),
(126, 16, 1, 22, 'Farid Meziane', '+213551000022', '1972-05-15', 'secretary', NULL, 'done', NULL, 0, 1, '2026-07-19 08:00:00', NULL, '2026-07-19 08:35:00', NULL, NULL, NULL, NULL, 2, 2),
(127, 16, 1, 23, 'Kahina Ait Ahmed', '+213661000023', '1995-09-09', 'doctor', NULL, 'done', NULL, 0, 2, '2026-07-19 08:12:00', NULL, '2026-07-19 08:47:00', NULL, NULL, NULL, NULL, 1, 3),
(128, 16, 1, 24, 'Youcef Brahimi', '+213771000024', '1980-01-21', 'secretary', NULL, 'no_show', NULL, 0, 3, '2026-07-19 08:24:00', NULL, NULL, NULL, NULL, '2026-07-19 08:54:00', NULL, 2, 2),
(129, 16, 1, 1, 'Amine Benali', '+213551000001', '1991-03-12', 'qr', 1, 'done', NULL, 0, 4, '2026-07-19 08:36:00', NULL, '2026-07-19 09:11:00', NULL, NULL, NULL, NULL, 3, 3),
(130, 16, 1, 2, 'Sara Khelifi', '+213551000002', '1988-07-25', 'secretary', NULL, 'canceled', NULL, 0, 5, '2026-07-19 08:48:00', NULL, NULL, '2026-07-19 09:03:00', 'patient_request', NULL, NULL, 2, 2),
(131, 16, 1, 3, 'Nadia Alloune', '+213551000003', '1994-01-18', 'link', 1, 'done', NULL, 0, 6, '2026-07-19 09:00:00', NULL, '2026-07-19 09:35:00', NULL, NULL, NULL, NULL, 3, 3),
(132, 16, 1, 4, 'Karim Touati', '+213551000004', '1985-11-03', 'secretary', NULL, 'done', NULL, 0, 7, '2026-07-19 09:12:00', NULL, '2026-07-19 09:47:00', NULL, NULL, NULL, NULL, 2, 2),
(133, 16, 1, 5, 'Yasmine Bensaid', '+213551000005', '1997-05-09', 'doctor', NULL, 'no_show', NULL, 0, 8, '2026-07-19 09:24:00', NULL, NULL, NULL, NULL, '2026-07-19 09:54:00', NULL, 1, 3),
(134, 17, 1, 21, 'Amina Ouali', '+213551000021', '1986-10-13', 'secretary', NULL, 'waiting', NULL, 0, 1, '2026-08-03 08:35:00', NULL, NULL, NULL, NULL, NULL, NULL, 2, 2),
(135, 17, 1, 22, 'Farid Meziane', '+213551000022', '1972-05-15', 'secretary', NULL, 'done', NULL, 0, 2, '2026-08-03 08:45:00', NULL, '2026-08-03 09:20:00', NULL, NULL, NULL, NULL, 2, 5),
(136, 17, 1, 23, 'Kahina Ait Ahmed', '+213661000023', '1995-09-09', 'doctor', NULL, 'waiting', NULL, 0, 3, '2026-08-03 08:55:00', NULL, NULL, NULL, NULL, NULL, NULL, 5, 5),
(137, 18, 1, 24, 'Youcef Brahimi', '+213771000024', '1980-01-21', 'secretary', NULL, 'done', NULL, 0, 1, '2026-08-02 08:40:00', NULL, '2026-08-02 09:15:00', NULL, NULL, NULL, NULL, 2, 5),
(138, 18, 1, 20, 'Samir Hadj', '+213771000020', '1999-07-27', 'doctor', NULL, 'no_show', NULL, 0, 2, '2026-08-02 08:55:00', NULL, NULL, NULL, NULL, '2026-08-02 09:30:00', NULL, 5, 5);

-- --------------------------------------------------------

--
-- Table structure for table `queue_entry_consents`
--

CREATE TABLE `queue_entry_consents` (
  `id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `queue_entry_id` bigint UNSIGNED NOT NULL,
  `consent_type` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `channel` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'none',
  `granted` tinyint(1) NOT NULL,
  `policy_version` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_ip_hash` char(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `consented_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revoked_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `queue_entry_consents`
--

INSERT INTO `queue_entry_consents` (`id`, `clinic_id`, `queue_entry_id`, `consent_type`, `channel`, `granted`, `policy_version`, `created_ip_hash`, `user_agent`, `consented_at`, `revoked_at`) VALUES
(1, 1, 11, 'privacy', 'none', 1, 'v1.0', 'a3f5c4d2e1b09876543210fedcba9876543210fedcba9876543210fedcba9876', 'Mozilla/5.0 MARKI demo mobile', '2026-08-03 17:02:09', NULL),
(2, 1, 11, 'notifications', 'sms', 0, 'v1.0', 'a3f5c4d2e1b09876543210fedcba9876543210fedcba9876543210fedcba9876', 'Mozilla/5.0 MARKI demo mobile', '2026-08-03 17:02:09', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` bigint UNSIGNED NOT NULL,
  `code` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `label` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `code`, `label`) VALUES
(1, 'clinic_admin', 'Administrateur du cabinet'),
(2, 'doctor', 'Médecin'),
(3, 'secretary', 'Secrétaire');

-- --------------------------------------------------------

--
-- Table structure for table `staff_doctor_access`
--

CREATE TABLE `staff_doctor_access` (
  `staff_profile_id` bigint UNSIGNED NOT NULL,
  `doctor_id` bigint UNSIGNED NOT NULL,
  `access_level` enum('queue_only','queue_and_patients','full') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'queue_only'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_doctor_access`
--

INSERT INTO `staff_doctor_access` (`staff_profile_id`, `doctor_id`, `access_level`) VALUES
(1, 1, 'full'),
(1, 2, 'full'),
(2, 1, 'queue_and_patients'),
(3, 1, 'queue_only'),
(4, 2, 'queue_only');

-- --------------------------------------------------------

--
-- Table structure for table `staff_profiles`
--

CREATE TABLE `staff_profiles` (
  `id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `job_title` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `staff_profiles`
--

INSERT INTO `staff_profiles` (`id`, `clinic_id`, `user_id`, `job_title`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'Secrétaire médicale', '2025-10-03 17:22:09', '2026-08-03 17:22:09'),
(2, 1, 3, 'Agente d’accueil', '2025-12-03 17:22:09', '2026-08-03 17:22:09'),
(3, 1, 4, 'Secrétaire de consultation', '2026-02-03 17:22:09', '2026-08-03 17:22:09'),
(4, 1, 6, 'Secrétaire en formation', '2026-08-03 17:22:09', '2026-08-03 17:22:09');

-- --------------------------------------------------------

--
-- Table structure for table `structure_activation_invitations`
--

CREATE TABLE `structure_activation_invitations` (
  `id` bigint UNSIGNED NOT NULL,
  `selector` char(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `recipient_label` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `recipient_email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `revoked_at` datetime DEFAULT NULL,
  `created_clinic_id` bigint UNSIGNED DEFAULT NULL,
  `created_user_id` bigint UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `structure_activation_invitations`
--

INSERT INTO `structure_activation_invitations` (`id`, `selector`, `token_hash`, `recipient_label`, `recipient_email`, `expires_at`, `used_at`, `revoked_at`, `created_clinic_id`, `created_user_id`, `created_at`) VALUES
(1, '4741a98412d9ac6c5a4ce5d1', '9f1fe75193e7849ef02f90e39a522f063977ed8b55bd3c4554a402cd9b37653e', NULL, NULL, '2026-08-06 17:28:52', NULL, '2026-08-03 18:08:21', NULL, NULL, '2026-08-03 17:28:52');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `email` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','disabled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `last_login_at` datetime DEFAULT NULL,
  `must_change_password` tinyint(1) NOT NULL DEFAULT '0',
  `password_changed_at` datetime DEFAULT NULL,
  `failed_login_attempts` smallint UNSIGNED NOT NULL DEFAULT '0',
  `locked_until` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `clinic_id`, `email`, `phone`, `password_hash`, `full_name`, `status`, `last_login_at`, `must_change_password`, `password_changed_at`, `failed_login_attempts`, `locked_until`, `created_at`, `updated_at`) VALUES
(1, 1, 'admin@marki.test', '+213550000001', '$2y$12$XprBurp6qaUIu5c6RJpaJetsWh7BYEZvVeGvpo9ciL7bt8doITTcy', 'Dr Karim Benali', 'active', '2026-08-04 19:56:18', 0, '2025-08-03 17:22:09', 0, NULL, '2025-08-03 17:22:09', '2026-08-04 19:56:18'),
(2, 1, 'amina@marki.test', '+213550000002', '$2y$12$XprBurp6qaUIu5c6RJpaJetsWh7BYEZvVeGvpo9ciL7bt8doITTcy', 'Amina Bensaid', 'active', '2026-08-03 18:09:15', 0, '2025-10-03 17:22:09', 0, NULL, '2025-10-03 17:22:09', '2026-08-03 18:09:15'),
(3, 1, 'nadia@marki.test', '+213550000003', '$2y$12$XprBurp6qaUIu5c6RJpaJetsWh7BYEZvVeGvpo9ciL7bt8doITTcy', 'Nadia Cherif', 'active', '2026-08-03 18:10:38', 0, '2025-12-03 17:22:09', 0, NULL, '2025-12-03 17:22:09', '2026-08-03 18:10:38'),
(4, 1, 'samira@marki.test', '+213550000004', '$2y$12$XprBurp6qaUIu5c6RJpaJetsWh7BYEZvVeGvpo9ciL7bt8doITTcy', 'Samira Kaci', 'active', '2026-08-03 18:10:51', 0, '2026-02-03 17:22:09', 0, NULL, '2026-02-03 17:22:09', '2026-08-03 18:10:51'),
(5, 1, 'leila@marki.test', '+213550000005', '$2y$12$XprBurp6qaUIu5c6RJpaJetsWh7BYEZvVeGvpo9ciL7bt8doITTcy', 'Dr Leila Mansouri', 'active', '2026-08-03 18:09:35', 0, '2025-11-03 17:22:09', 0, NULL, '2025-11-03 17:22:09', '2026-08-03 18:09:35'),
(6, 1, 'temporaire@marki.test', '+213550000006', '$2y$12$ldKYxFaIpbiPSJVuJwO6F.4jRu.yPxN4iZUEDug4hoM5pNRFfSRrG', 'Yacine Boudjemaa', 'active', NULL, 1, '2026-08-03 17:22:09', 0, NULL, '2026-08-03 17:22:09', '2026-08-03 17:22:09');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `user_id` bigint UNSIGNED NOT NULL,
  `role_id` bigint UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`user_id`, `role_id`) VALUES
(1, 1),
(1, 2),
(5, 2),
(2, 3),
(3, 3),
(4, 3),
(6, 3);

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` bigint UNSIGNED NOT NULL,
  `user_id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `selected_doctor_id` bigint UNSIGNED DEFAULT NULL,
  `selector` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `validator_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_agent_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip_hash` char(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `expires_at` datetime NOT NULL,
  `last_used_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `revoked_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `visits`
--

CREATE TABLE `visits` (
  `id` bigint UNSIGNED NOT NULL,
  `clinic_id` bigint UNSIGNED NOT NULL,
  `doctor_id` bigint UNSIGNED NOT NULL,
  `patient_id` bigint UNSIGNED DEFAULT NULL,
  `queue_entry_id` bigint UNSIGNED DEFAULT NULL,
  `appointment_id` bigint UNSIGNED DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `ended_at` datetime DEFAULT NULL,
  `status` enum('in_progress','done','canceled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'in_progress',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `created_by_user_id` bigint UNSIGNED DEFAULT NULL,
  `started_by_user_id` bigint UNSIGNED DEFAULT NULL,
  `completed_by_user_id` bigint UNSIGNED DEFAULT NULL,
  `canceled_by_user_id` bigint UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `visits`
--

INSERT INTO `visits` (`id`, `clinic_id`, `doctor_id`, `patient_id`, `queue_entry_id`, `appointment_id`, `started_at`, `ended_at`, `status`, `created_at`, `updated_at`, `created_by_user_id`, `started_by_user_id`, `completed_by_user_id`, `canceled_by_user_id`) VALUES
(1, 1, 1, 2, 2, NULL, NULL, '2026-08-03 08:45:00', 'done', '2026-08-03 08:45:00', '2026-08-03 08:45:00', 2, NULL, 2, NULL),
(2, 1, 1, 5, 5, NULL, NULL, '2026-08-03 09:15:00', 'done', '2026-08-03 09:15:00', '2026-08-03 09:15:00', 2, NULL, 2, NULL),
(3, 1, 1, 9, 9, NULL, NULL, '2026-08-03 10:00:00', 'done', '2026-08-03 10:00:00', '2026-08-03 10:00:00', 3, NULL, 3, NULL),
(4, 1, 1, 4, 14, NULL, NULL, '2026-08-02 08:35:00', 'done', '2026-08-02 08:35:00', '2026-08-02 08:35:00', 2, NULL, 2, NULL),
(5, 1, 1, 5, 15, NULL, NULL, '2026-08-02 08:47:00', 'done', '2026-08-02 08:47:00', '2026-08-02 08:47:00', 1, NULL, 3, NULL),
(6, 1, 1, 7, 17, NULL, NULL, '2026-08-02 09:11:00', 'done', '2026-08-02 09:11:00', '2026-08-02 09:11:00', 3, NULL, 3, NULL),
(7, 1, 1, 9, 19, NULL, NULL, '2026-08-02 09:35:00', 'done', '2026-08-02 09:35:00', '2026-08-02 09:35:00', 3, NULL, 3, NULL),
(8, 1, 1, 10, 20, NULL, NULL, '2026-08-02 09:47:00', 'done', '2026-08-02 09:47:00', '2026-08-02 09:47:00', 2, NULL, 2, NULL),
(9, 1, 1, 7, 22, NULL, NULL, '2026-08-01 08:35:00', 'done', '2026-08-01 08:35:00', '2026-08-01 08:35:00', 2, NULL, 2, NULL),
(10, 1, 1, 8, 23, NULL, NULL, '2026-08-01 08:47:00', 'done', '2026-08-01 08:47:00', '2026-08-01 08:47:00', 1, NULL, 3, NULL),
(11, 1, 1, 10, 25, NULL, NULL, '2026-08-01 09:11:00', 'done', '2026-08-01 09:11:00', '2026-08-01 09:11:00', 3, NULL, 3, NULL),
(12, 1, 1, 12, 27, NULL, NULL, '2026-08-01 09:35:00', 'done', '2026-08-01 09:35:00', '2026-08-01 09:35:00', 3, NULL, 3, NULL),
(13, 1, 1, 13, 28, NULL, NULL, '2026-08-01 09:47:00', 'done', '2026-08-01 09:47:00', '2026-08-01 09:47:00', 2, NULL, 2, NULL),
(14, 1, 1, 10, 30, NULL, NULL, '2026-07-31 08:35:00', 'done', '2026-07-31 08:35:00', '2026-07-31 08:35:00', 2, NULL, 2, NULL),
(15, 1, 1, 11, 31, NULL, NULL, '2026-07-31 08:47:00', 'done', '2026-07-31 08:47:00', '2026-07-31 08:47:00', 1, NULL, 3, NULL),
(16, 1, 1, 13, 33, NULL, NULL, '2026-07-31 09:11:00', 'done', '2026-07-31 09:11:00', '2026-07-31 09:11:00', 3, NULL, 3, NULL),
(17, 1, 1, 15, 35, NULL, NULL, '2026-07-31 09:35:00', 'done', '2026-07-31 09:35:00', '2026-07-31 09:35:00', 3, NULL, 3, NULL),
(18, 1, 1, 16, 36, NULL, NULL, '2026-07-31 09:47:00', 'done', '2026-07-31 09:47:00', '2026-07-31 09:47:00', 2, NULL, 2, NULL),
(19, 1, 1, 13, 38, NULL, NULL, '2026-07-30 08:35:00', 'done', '2026-07-30 08:35:00', '2026-07-30 08:35:00', 2, NULL, 2, NULL),
(20, 1, 1, 14, 39, NULL, NULL, '2026-07-30 08:47:00', 'done', '2026-07-30 08:47:00', '2026-07-30 08:47:00', 1, NULL, 3, NULL),
(21, 1, 1, 16, 41, NULL, NULL, '2026-07-30 09:11:00', 'done', '2026-07-30 09:11:00', '2026-07-30 09:11:00', 3, NULL, 3, NULL),
(22, 1, 1, 18, 43, NULL, NULL, '2026-07-30 09:35:00', 'done', '2026-07-30 09:35:00', '2026-07-30 09:35:00', 3, NULL, 3, NULL),
(23, 1, 1, 19, 44, NULL, NULL, '2026-07-30 09:47:00', 'done', '2026-07-30 09:47:00', '2026-07-30 09:47:00', 2, NULL, 2, NULL),
(24, 1, 1, 16, 46, NULL, NULL, '2026-07-29 08:35:00', 'done', '2026-07-29 08:35:00', '2026-07-29 08:35:00', 2, NULL, 2, NULL),
(25, 1, 1, 17, 47, NULL, NULL, '2026-07-29 08:47:00', 'done', '2026-07-29 08:47:00', '2026-07-29 08:47:00', 1, NULL, 3, NULL),
(26, 1, 1, 19, 49, NULL, NULL, '2026-07-29 09:11:00', 'done', '2026-07-29 09:11:00', '2026-07-29 09:11:00', 3, NULL, 3, NULL),
(27, 1, 1, 21, 51, NULL, NULL, '2026-07-29 09:35:00', 'done', '2026-07-29 09:35:00', '2026-07-29 09:35:00', 3, NULL, 3, NULL),
(28, 1, 1, 22, 52, NULL, NULL, '2026-07-29 09:47:00', 'done', '2026-07-29 09:47:00', '2026-07-29 09:47:00', 2, NULL, 2, NULL),
(29, 1, 1, 19, 54, NULL, NULL, '2026-07-28 08:35:00', 'done', '2026-07-28 08:35:00', '2026-07-28 08:35:00', 2, NULL, 2, NULL),
(30, 1, 1, 20, 55, NULL, NULL, '2026-07-28 08:47:00', 'done', '2026-07-28 08:47:00', '2026-07-28 08:47:00', 1, NULL, 3, NULL),
(31, 1, 1, 22, 57, NULL, NULL, '2026-07-28 09:11:00', 'done', '2026-07-28 09:11:00', '2026-07-28 09:11:00', 3, NULL, 3, NULL),
(32, 1, 1, 24, 59, NULL, NULL, '2026-07-28 09:35:00', 'done', '2026-07-28 09:35:00', '2026-07-28 09:35:00', 3, NULL, 3, NULL),
(33, 1, 1, 1, 60, NULL, NULL, '2026-07-28 09:47:00', 'done', '2026-07-28 09:47:00', '2026-07-28 09:47:00', 2, NULL, 2, NULL),
(34, 1, 1, 22, 62, NULL, NULL, '2026-07-27 08:35:00', 'done', '2026-07-27 08:35:00', '2026-07-27 08:35:00', 2, NULL, 2, NULL),
(35, 1, 1, 23, 63, NULL, NULL, '2026-07-27 08:47:00', 'done', '2026-07-27 08:47:00', '2026-07-27 08:47:00', 1, NULL, 3, NULL),
(36, 1, 1, 1, 65, NULL, NULL, '2026-07-27 09:11:00', 'done', '2026-07-27 09:11:00', '2026-07-27 09:11:00', 3, NULL, 3, NULL),
(37, 1, 1, 3, 67, NULL, NULL, '2026-07-27 09:35:00', 'done', '2026-07-27 09:35:00', '2026-07-27 09:35:00', 3, NULL, 3, NULL),
(38, 1, 1, 1, 70, NULL, NULL, '2026-07-26 08:35:00', 'done', '2026-07-26 08:35:00', '2026-07-26 08:35:00', 2, NULL, 2, NULL),
(39, 1, 1, 2, 71, NULL, NULL, '2026-07-26 08:47:00', 'done', '2026-07-26 08:47:00', '2026-07-26 08:47:00', 1, NULL, 3, NULL),
(40, 1, 1, 4, 73, NULL, NULL, '2026-07-26 09:11:00', 'done', '2026-07-26 09:11:00', '2026-07-26 09:11:00', 3, NULL, 3, NULL),
(41, 1, 1, 6, 75, NULL, NULL, '2026-07-26 09:35:00', 'done', '2026-07-26 09:35:00', '2026-07-26 09:35:00', 3, NULL, 3, NULL),
(42, 1, 1, 7, 76, NULL, NULL, '2026-07-26 09:47:00', 'done', '2026-07-26 09:47:00', '2026-07-26 09:47:00', 2, NULL, 2, NULL),
(43, 1, 1, 4, 78, NULL, NULL, '2026-07-25 08:35:00', 'done', '2026-07-25 08:35:00', '2026-07-25 08:35:00', 2, NULL, 2, NULL),
(44, 1, 1, 5, 79, NULL, NULL, '2026-07-25 08:47:00', 'done', '2026-07-25 08:47:00', '2026-07-25 08:47:00', 1, NULL, 3, NULL),
(45, 1, 1, 7, 81, NULL, NULL, '2026-07-25 09:11:00', 'done', '2026-07-25 09:11:00', '2026-07-25 09:11:00', 3, NULL, 3, NULL),
(46, 1, 1, 9, 83, NULL, NULL, '2026-07-25 09:35:00', 'done', '2026-07-25 09:35:00', '2026-07-25 09:35:00', 3, NULL, 3, NULL),
(47, 1, 1, 10, 84, NULL, NULL, '2026-07-25 09:47:00', 'done', '2026-07-25 09:47:00', '2026-07-25 09:47:00', 2, NULL, 2, NULL),
(48, 1, 1, 7, 86, NULL, NULL, '2026-07-24 08:35:00', 'done', '2026-07-24 08:35:00', '2026-07-24 08:35:00', 2, NULL, 2, NULL),
(49, 1, 1, 8, 87, NULL, NULL, '2026-07-24 08:47:00', 'done', '2026-07-24 08:47:00', '2026-07-24 08:47:00', 1, NULL, 3, NULL),
(50, 1, 1, 10, 89, NULL, NULL, '2026-07-24 09:11:00', 'done', '2026-07-24 09:11:00', '2026-07-24 09:11:00', 3, NULL, 3, NULL),
(51, 1, 1, 12, 91, NULL, NULL, '2026-07-24 09:35:00', 'done', '2026-07-24 09:35:00', '2026-07-24 09:35:00', 3, NULL, 3, NULL),
(52, 1, 1, 13, 92, NULL, NULL, '2026-07-24 09:47:00', 'done', '2026-07-24 09:47:00', '2026-07-24 09:47:00', 2, NULL, 2, NULL),
(53, 1, 1, 10, 94, NULL, NULL, '2026-07-23 08:35:00', 'done', '2026-07-23 08:35:00', '2026-07-23 08:35:00', 2, NULL, 2, NULL),
(54, 1, 1, 11, 95, NULL, NULL, '2026-07-23 08:47:00', 'done', '2026-07-23 08:47:00', '2026-07-23 08:47:00', 1, NULL, 3, NULL),
(55, 1, 1, 13, 97, NULL, NULL, '2026-07-23 09:11:00', 'done', '2026-07-23 09:11:00', '2026-07-23 09:11:00', 3, NULL, 3, NULL),
(56, 1, 1, 15, 99, NULL, NULL, '2026-07-23 09:35:00', 'done', '2026-07-23 09:35:00', '2026-07-23 09:35:00', 3, NULL, 3, NULL),
(57, 1, 1, 16, 100, NULL, NULL, '2026-07-23 09:47:00', 'done', '2026-07-23 09:47:00', '2026-07-23 09:47:00', 2, NULL, 2, NULL),
(58, 1, 1, 13, 102, NULL, NULL, '2026-07-22 08:35:00', 'done', '2026-07-22 08:35:00', '2026-07-22 08:35:00', 2, NULL, 2, NULL),
(59, 1, 1, 14, 103, NULL, NULL, '2026-07-22 08:47:00', 'done', '2026-07-22 08:47:00', '2026-07-22 08:47:00', 1, NULL, 3, NULL),
(60, 1, 1, 16, 105, NULL, NULL, '2026-07-22 09:11:00', 'done', '2026-07-22 09:11:00', '2026-07-22 09:11:00', 3, NULL, 3, NULL),
(61, 1, 1, 18, 107, NULL, NULL, '2026-07-22 09:35:00', 'done', '2026-07-22 09:35:00', '2026-07-22 09:35:00', 3, NULL, 3, NULL),
(62, 1, 1, 19, 108, NULL, NULL, '2026-07-22 09:47:00', 'done', '2026-07-22 09:47:00', '2026-07-22 09:47:00', 2, NULL, 2, NULL),
(63, 1, 1, 16, 110, NULL, NULL, '2026-07-21 08:35:00', 'done', '2026-07-21 08:35:00', '2026-07-21 08:35:00', 2, NULL, 2, NULL),
(64, 1, 1, 17, 111, NULL, NULL, '2026-07-21 08:47:00', 'done', '2026-07-21 08:47:00', '2026-07-21 08:47:00', 1, NULL, 3, NULL),
(65, 1, 1, 19, 113, NULL, NULL, '2026-07-21 09:11:00', 'done', '2026-07-21 09:11:00', '2026-07-21 09:11:00', 3, NULL, 3, NULL),
(66, 1, 1, 21, 115, NULL, NULL, '2026-07-21 09:35:00', 'done', '2026-07-21 09:35:00', '2026-07-21 09:35:00', 3, NULL, 3, NULL),
(67, 1, 1, 22, 116, NULL, NULL, '2026-07-21 09:47:00', 'done', '2026-07-21 09:47:00', '2026-07-21 09:47:00', 2, NULL, 2, NULL),
(68, 1, 1, 19, 118, NULL, NULL, '2026-07-20 08:35:00', 'done', '2026-07-20 08:35:00', '2026-07-20 08:35:00', 2, NULL, 2, NULL),
(69, 1, 1, 20, 119, NULL, NULL, '2026-07-20 08:47:00', 'done', '2026-07-20 08:47:00', '2026-07-20 08:47:00', 1, NULL, 3, NULL),
(70, 1, 1, 22, 121, NULL, NULL, '2026-07-20 09:11:00', 'done', '2026-07-20 09:11:00', '2026-07-20 09:11:00', 3, NULL, 3, NULL),
(71, 1, 1, 24, 123, NULL, NULL, '2026-07-20 09:35:00', 'done', '2026-07-20 09:35:00', '2026-07-20 09:35:00', 3, NULL, 3, NULL),
(72, 1, 1, 1, 124, NULL, NULL, '2026-07-20 09:47:00', 'done', '2026-07-20 09:47:00', '2026-07-20 09:47:00', 2, NULL, 2, NULL),
(73, 1, 1, 22, 126, NULL, NULL, '2026-07-19 08:35:00', 'done', '2026-07-19 08:35:00', '2026-07-19 08:35:00', 2, NULL, 2, NULL),
(74, 1, 1, 23, 127, NULL, NULL, '2026-07-19 08:47:00', 'done', '2026-07-19 08:47:00', '2026-07-19 08:47:00', 1, NULL, 3, NULL),
(75, 1, 1, 1, 129, NULL, NULL, '2026-07-19 09:11:00', 'done', '2026-07-19 09:11:00', '2026-07-19 09:11:00', 3, NULL, 3, NULL),
(76, 1, 1, 3, 131, NULL, NULL, '2026-07-19 09:35:00', 'done', '2026-07-19 09:35:00', '2026-07-19 09:35:00', 3, NULL, 3, NULL),
(77, 1, 1, 4, 132, NULL, NULL, '2026-07-19 09:47:00', 'done', '2026-07-19 09:47:00', '2026-07-19 09:47:00', 2, NULL, 2, NULL),
(78, 1, 2, 22, 135, NULL, NULL, '2026-08-03 09:20:00', 'done', '2026-08-03 09:20:00', '2026-08-03 09:20:00', 2, NULL, 5, NULL),
(79, 1, 2, 24, 137, NULL, NULL, '2026-08-02 09:15:00', 'done', '2026-08-02 09:15:00', '2026-08-02 09:15:00', 2, NULL, 5, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_al_actor` (`actor_user_id`),
  ADD KEY `ix_al_clinic_time` (`clinic_id`,`created_at`),
  ADD KEY `ix_al_entity` (`entity_type`,`entity_id`);

--
-- Indexes for table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_appt_clinic` (`clinic_id`),
  ADD KEY `fk_appt_patient` (`patient_id`),
  ADD KEY `fk_appt_created_by` (`created_by_user_id`),
  ADD KEY `ix_appt_doctor_time` (`doctor_id`,`start_at`);

--
-- Indexes for table `billing_accounts`
--
ALTER TABLE `billing_accounts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `clinic_id` (`clinic_id`);

--
-- Indexes for table `billing_events`
--
ALTER TABLE `billing_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_be_doctor` (`doctor_id`),
  ADD KEY `fk_be_visit` (`visit_id`),
  ADD KEY `ix_be_clinic_time` (`clinic_id`,`created_at`);

--
-- Indexes for table `clinics`
--
ALTER TABLE `clinics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_clinics_slug` (`slug`);

--
-- Indexes for table `doctor_profiles`
--
ALTER TABLE `doctor_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `fk_doctors_clinic` (`clinic_id`);

--
-- Indexes for table `doctor_public_registration_exceptions`
--
ALTER TABLE `doctor_public_registration_exceptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_dpre_doctor_date_slot` (`doctor_id`,`exception_date`,`slot_order`),
  ADD KEY `ix_dpre_clinic_date` (`clinic_id`,`exception_date`),
  ADD KEY `ix_dpre_doctor_date` (`doctor_id`,`exception_date`,`is_active`);

--
-- Indexes for table `doctor_public_registration_hours`
--
ALTER TABLE `doctor_public_registration_hours`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_dprh_doctor_day_slot` (`doctor_id`,`day_of_week`,`slot_order`),
  ADD KEY `ix_dprh_clinic` (`clinic_id`),
  ADD KEY `ix_dprh_doctor_active` (`doctor_id`,`is_active`);

--
-- Indexes for table `doctor_public_registration_messages`
--
ALTER TABLE `doctor_public_registration_messages`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_dprm_doctor_code` (`doctor_id`,`message_code`),
  ADD KEY `ix_dprm_clinic` (`clinic_id`);

--
-- Indexes for table `doctor_public_registration_settings`
--
ALTER TABLE `doctor_public_registration_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_dprs_doctor` (`doctor_id`),
  ADD KEY `ix_dprs_clinic` (`clinic_id`);

--
-- Indexes for table `files`
--
ALTER TABLE `files`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_files_clinic` (`clinic_id`),
  ADD KEY `fk_files_patient` (`patient_id`),
  ADD KEY `fk_files_visit` (`visit_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_inv_clinic_period` (`clinic_id`,`period_start`,`period_end`);

--
-- Indexes for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_ii_invoice` (`invoice_id`),
  ADD KEY `fk_ii_be` (`billing_event_id`);

--
-- Indexes for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `visit_id` (`visit_id`),
  ADD KEY `fk_mr_clinic` (`clinic_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_notif_clinic_time` (`clinic_id`,`created_at`);

--
-- Indexes for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_password_reset_selector` (`selector`),
  ADD KEY `ix_password_reset_user` (`user_id`,`used_at`,`expires_at`),
  ADD KEY `ix_password_reset_expiration` (`expires_at`,`used_at`),
  ADD KEY `ix_password_reset_clinic` (`clinic_id`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_patients_clinic_phone` (`clinic_id`,`phone`),
  ADD KEY `ix_patients_clinic_name` (`clinic_id`,`full_name`);

--
-- Indexes for table `patient_contacts`
--
ALTER TABLE `patient_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_patient_contacts_patient` (`patient_id`);

--
-- Indexes for table `patient_public_sessions`
--
ALTER TABLE `patient_public_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_pps_entry` (`queue_entry_id`),
  ADD UNIQUE KEY `ux_pps_token` (`session_token_hash`),
  ADD KEY `ix_pps_expiration` (`expires_at`,`revoked_at`);

--
-- Indexes for table `platform_admins`
--
ALTER TABLE `platform_admins`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_platform_admin_email` (`email`),
  ADD KEY `ix_platform_admin_status` (`status`),
  ADD KEY `ix_platform_admin_locked_until` (`locked_until`);

--
-- Indexes for table `platform_admin_activity_logs`
--
ALTER TABLE `platform_admin_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_platform_admin_log_admin` (`platform_admin_id`),
  ADD KEY `ix_platform_admin_log_action` (`action`),
  ADD KEY `ix_platform_admin_log_created_at` (`created_at`);

--
-- Indexes for table `platform_admin_sessions`
--
ALTER TABLE `platform_admin_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_platform_admin_session_selector` (`selector`),
  ADD KEY `ix_platform_admin_session_admin` (`platform_admin_id`),
  ADD KEY `ix_platform_admin_session_expiry` (`expires_at`,`revoked_at`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_rx_clinic` (`clinic_id`),
  ADD KEY `ix_rx_visit` (`visit_id`);

--
-- Indexes for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_rx_items_rx` (`prescription_id`);

--
-- Indexes for table `public_links`
--
ALTER TABLE `public_links`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_pl_doctor_type` (`doctor_id`,`type`),
  ADD UNIQUE KEY `ux_pl_public_id` (`public_id`),
  ADD UNIQUE KEY `ux_pl_token_hash` (`token_hash`),
  ADD KEY `fk_pl_clinic` (`clinic_id`),
  ADD KEY `fk_pl_deactivated_by` (`deactivated_by_user_id`),
  ADD KEY `ix_pl_doctor_active` (`doctor_id`,`is_active`),
  ADD KEY `fk_pl_created_by` (`created_by_user_id`),
  ADD KEY `fk_pl_revoked_by` (`revoked_by_user_id`);

--
-- Indexes for table `public_link_events`
--
ALTER TABLE `public_link_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `ix_ple_link_time` (`public_link_id`,`created_at`),
  ADD KEY `ix_ple_link_ip_time` (`public_link_id`,`ip_hash`,`created_at`),
  ADD KEY `ix_ple_result_time` (`result_code`,`created_at`),
  ADD KEY `fk_ple_queue` (`queue_id`),
  ADD KEY `fk_ple_queue_entry` (`queue_entry_id`);

--
-- Indexes for table `queues`
--
ALTER TABLE `queues`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_queues_doctor_date` (`doctor_id`,`queue_date`),
  ADD KEY `fk_queues_opened_by` (`opened_by_user_id`),
  ADD KEY `fk_queues_closed_by` (`closed_by_user_id`),
  ADD KEY `ix_queues_clinic_date` (`clinic_id`,`queue_date`),
  ADD KEY `fk_queues_paused_by` (`paused_by_user_id`),
  ADD KEY `fk_queues_completed_by` (`completed_by_user_id`),
  ADD KEY `ix_queues_registration_day` (`registration_status`,`day_status`,`queue_date`);

--
-- Indexes for table `queue_entries`
--
ALTER TABLE `queue_entries`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_qe_queue_position` (`queue_id`,`position_number`),
  ADD UNIQUE KEY `ux_qe_queue_patient` (`queue_id`,`patient_id`),
  ADD KEY `fk_qe_clinic` (`clinic_id`),
  ADD KEY `fk_qe_created_by` (`created_by_user_id`),
  ADD KEY `fk_qe_updated_by` (`updated_by_user_id`),
  ADD KEY `ix_qe_queue_status_created` (`queue_id`,`status`,`created_at`),
  ADD KEY `ix_qe_queue_phone` (`queue_id`,`phone`),
  ADD KEY `ix_qe_patient` (`patient_id`),
  ADD KEY `ix_qe_queue_status_position` (`queue_id`,`status`,`position_number`),
  ADD KEY `ix_qe_completion_restore` (`queue_id`,`canceled_by_completion`,`status_before_completion`),
  ADD KEY `ix_qe_public_link` (`public_link_id`);

--
-- Indexes for table `queue_entry_consents`
--
ALTER TABLE `queue_entry_consents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_qec_entry_type_channel` (`queue_entry_id`,`consent_type`,`channel`),
  ADD KEY `ix_qec_clinic_time` (`clinic_id`,`consented_at`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Indexes for table `staff_doctor_access`
--
ALTER TABLE `staff_doctor_access`
  ADD PRIMARY KEY (`staff_profile_id`,`doctor_id`),
  ADD KEY `fk_sda_doctor` (`doctor_id`);

--
-- Indexes for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD KEY `fk_staff_clinic` (`clinic_id`);

--
-- Indexes for table `structure_activation_invitations`
--
ALTER TABLE `structure_activation_invitations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_structure_invitation_selector` (`selector`),
  ADD KEY `ix_structure_invitation_state` (`used_at`,`revoked_at`,`expires_at`),
  ADD KEY `ix_structure_invitation_clinic` (`created_clinic_id`),
  ADD KEY `ix_structure_invitation_user` (`created_user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_users_clinic_email` (`clinic_id`,`email`),
  ADD UNIQUE KEY `ux_users_clinic_phone` (`clinic_id`,`phone`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`user_id`,`role_id`),
  ADD KEY `fk_user_roles_role` (`role_id`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_user_sessions_selector` (`selector`),
  ADD KEY `ix_user_sessions_user_active` (`user_id`,`revoked_at`,`expires_at`),
  ADD KEY `ix_user_sessions_expiration` (`expires_at`,`revoked_at`),
  ADD KEY `ix_user_sessions_clinic` (`clinic_id`),
  ADD KEY `ix_user_sessions_doctor` (`selected_doctor_id`);

--
-- Indexes for table `visits`
--
ALTER TABLE `visits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ux_visits_qe` (`queue_entry_id`),
  ADD UNIQUE KEY `ux_visits_appt` (`appointment_id`),
  ADD KEY `fk_visits_clinic` (`clinic_id`),
  ADD KEY `fk_visits_doctor` (`doctor_id`),
  ADD KEY `fk_visits_patient` (`patient_id`),
  ADD KEY `ix_visits_created_by_user` (`created_by_user_id`),
  ADD KEY `ix_visits_started_by_user` (`started_by_user_id`),
  ADD KEY `ix_visits_completed_by_user` (`completed_by_user_id`),
  ADD KEY `ix_visits_canceled_by_user` (`canceled_by_user_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `billing_accounts`
--
ALTER TABLE `billing_accounts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `billing_events`
--
ALTER TABLE `billing_events`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clinics`
--
ALTER TABLE `clinics`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `doctor_profiles`
--
ALTER TABLE `doctor_profiles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `doctor_public_registration_exceptions`
--
ALTER TABLE `doctor_public_registration_exceptions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `doctor_public_registration_hours`
--
ALTER TABLE `doctor_public_registration_hours`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `doctor_public_registration_messages`
--
ALTER TABLE `doctor_public_registration_messages`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=112;

--
-- AUTO_INCREMENT for table `doctor_public_registration_settings`
--
ALTER TABLE `doctor_public_registration_settings`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `files`
--
ALTER TABLE `files`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoice_items`
--
ALTER TABLE `invoice_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `medical_records`
--
ALTER TABLE `medical_records`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `patient_contacts`
--
ALTER TABLE `patient_contacts`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `patient_public_sessions`
--
ALTER TABLE `patient_public_sessions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `platform_admins`
--
ALTER TABLE `platform_admins`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `platform_admin_activity_logs`
--
ALTER TABLE `platform_admin_activity_logs`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `platform_admin_sessions`
--
ALTER TABLE `platform_admin_sessions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescription_items`
--
ALTER TABLE `prescription_items`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `public_links`
--
ALTER TABLE `public_links`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `public_link_events`
--
ALTER TABLE `public_link_events`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `queues`
--
ALTER TABLE `queues`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `queue_entries`
--
ALTER TABLE `queue_entries`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=139;

--
-- AUTO_INCREMENT for table `queue_entry_consents`
--
ALTER TABLE `queue_entry_consents`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `structure_activation_invitations`
--
ALTER TABLE `structure_activation_invitations`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `visits`
--
ALTER TABLE `visits`
  MODIFY `id` bigint UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `fk_al_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_al_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `fk_appt_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_appt_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_appt_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_appt_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `billing_accounts`
--
ALTER TABLE `billing_accounts`
  ADD CONSTRAINT `fk_ba_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `billing_events`
--
ALTER TABLE `billing_events`
  ADD CONSTRAINT `fk_be_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_be_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_be_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `doctor_profiles`
--
ALTER TABLE `doctor_profiles`
  ADD CONSTRAINT `fk_doctors_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_doctors_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `doctor_public_registration_exceptions`
--
ALTER TABLE `doctor_public_registration_exceptions`
  ADD CONSTRAINT `fk_dpre_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_dpre_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctor_public_registration_hours`
--
ALTER TABLE `doctor_public_registration_hours`
  ADD CONSTRAINT `fk_dprh_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_dprh_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctor_public_registration_messages`
--
ALTER TABLE `doctor_public_registration_messages`
  ADD CONSTRAINT `fk_dprm_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_dprm_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `doctor_public_registration_settings`
--
ALTER TABLE `doctor_public_registration_settings`
  ADD CONSTRAINT `fk_dprs_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_dprs_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `files`
--
ALTER TABLE `files`
  ADD CONSTRAINT `fk_files_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_files_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_files_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_inv_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `invoice_items`
--
ALTER TABLE `invoice_items`
  ADD CONSTRAINT `fk_ii_be` FOREIGN KEY (`billing_event_id`) REFERENCES `billing_events` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ii_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `medical_records`
--
ALTER TABLE `medical_records`
  ADD CONSTRAINT `fk_mr_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_mr_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notif_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `password_reset_tokens`
--
ALTER TABLE `password_reset_tokens`
  ADD CONSTRAINT `fk_password_reset_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_password_reset_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patients`
--
ALTER TABLE `patients`
  ADD CONSTRAINT `fk_patients_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `patient_contacts`
--
ALTER TABLE `patient_contacts`
  ADD CONSTRAINT `fk_patient_contacts_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `patient_public_sessions`
--
ALTER TABLE `patient_public_sessions`
  ADD CONSTRAINT `fk_pps_entry` FOREIGN KEY (`queue_entry_id`) REFERENCES `queue_entries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `platform_admin_activity_logs`
--
ALTER TABLE `platform_admin_activity_logs`
  ADD CONSTRAINT `fk_platform_admin_log_admin` FOREIGN KEY (`platform_admin_id`) REFERENCES `platform_admins` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `platform_admin_sessions`
--
ALTER TABLE `platform_admin_sessions`
  ADD CONSTRAINT `fk_platform_admin_session_admin` FOREIGN KEY (`platform_admin_id`) REFERENCES `platform_admins` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `fk_rx_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_rx_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prescription_items`
--
ALTER TABLE `prescription_items`
  ADD CONSTRAINT `fk_rx_items_rx` FOREIGN KEY (`prescription_id`) REFERENCES `prescriptions` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `public_links`
--
ALTER TABLE `public_links`
  ADD CONSTRAINT `fk_pl_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_pl_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pl_deactivated_by` FOREIGN KEY (`deactivated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_pl_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_pl_revoked_by` FOREIGN KEY (`revoked_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `public_link_events`
--
ALTER TABLE `public_link_events`
  ADD CONSTRAINT `fk_ple_link` FOREIGN KEY (`public_link_id`) REFERENCES `public_links` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ple_queue` FOREIGN KEY (`queue_id`) REFERENCES `queues` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_ple_queue_entry` FOREIGN KEY (`queue_entry_id`) REFERENCES `queue_entries` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `queues`
--
ALTER TABLE `queues`
  ADD CONSTRAINT `fk_queues_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_queues_closed_by` FOREIGN KEY (`closed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_queues_completed_by` FOREIGN KEY (`completed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_queues_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_queues_opened_by` FOREIGN KEY (`opened_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_queues_paused_by` FOREIGN KEY (`paused_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `queue_entries`
--
ALTER TABLE `queue_entries`
  ADD CONSTRAINT `fk_qe_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_qe_created_by` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_qe_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_qe_public_link` FOREIGN KEY (`public_link_id`) REFERENCES `public_links` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_qe_queue` FOREIGN KEY (`queue_id`) REFERENCES `queues` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_qe_updated_by` FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `queue_entry_consents`
--
ALTER TABLE `queue_entry_consents`
  ADD CONSTRAINT `fk_qec_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_qec_queue_entry` FOREIGN KEY (`queue_entry_id`) REFERENCES `queue_entries` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_doctor_access`
--
ALTER TABLE `staff_doctor_access`
  ADD CONSTRAINT `fk_sda_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sda_staff` FOREIGN KEY (`staff_profile_id`) REFERENCES `staff_profiles` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staff_profiles`
--
ALTER TABLE `staff_profiles`
  ADD CONSTRAINT `fk_staff_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_staff_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `structure_activation_invitations`
--
ALTER TABLE `structure_activation_invitations`
  ADD CONSTRAINT `fk_structure_invitation_clinic` FOREIGN KEY (`created_clinic_id`) REFERENCES `clinics` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_structure_invitation_user` FOREIGN KEY (`created_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT;

--
-- Constraints for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD CONSTRAINT `fk_user_roles_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_user_roles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `fk_user_sessions_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_user_sessions_doctor` FOREIGN KEY (`selected_doctor_id`) REFERENCES `doctor_profiles` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_user_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `visits`
--
ALTER TABLE `visits`
  ADD CONSTRAINT `fk_visits_appt` FOREIGN KEY (`appointment_id`) REFERENCES `appointments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_visits_canceled_by_user` FOREIGN KEY (`canceled_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_visits_clinic` FOREIGN KEY (`clinic_id`) REFERENCES `clinics` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_visits_completed_by_user` FOREIGN KEY (`completed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_visits_created_by_user` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_visits_doctor` FOREIGN KEY (`doctor_id`) REFERENCES `doctor_profiles` (`id`) ON DELETE RESTRICT,
  ADD CONSTRAINT `fk_visits_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_visits_qe` FOREIGN KEY (`queue_entry_id`) REFERENCES `queue_entries` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_visits_started_by_user` FOREIGN KEY (`started_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
