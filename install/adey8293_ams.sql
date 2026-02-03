-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 03, 2026 at 06:01 PM
-- Server version: 11.4.9-MariaDB-cll-lve
-- PHP Version: 8.4.16

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `adey8293_ams`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` bigint(20) NOT NULL,
  `created_at` datetime NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(60) NOT NULL,
  `meta` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `consents`
--

CREATE TABLE `consents` (
  `id` bigint(20) NOT NULL,
  `visit_id` bigint(20) NOT NULL,
  `patient_id` bigint(20) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `consent_no` varchar(30) NOT NULL,
  `procedure_name` varchar(255) NOT NULL,
  `diagnosis` mediumtext DEFAULT NULL,
  `risks` mediumtext DEFAULT NULL,
  `benefits` mediumtext DEFAULT NULL,
  `alternatives` mediumtext DEFAULT NULL,
  `notes` mediumtext DEFAULT NULL,
  `signer_name` varchar(255) DEFAULT NULL,
  `signer_relation` varchar(255) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `patients`
--

CREATE TABLE `patients` (
  `id` bigint(20) NOT NULL,
  `mrn` varchar(30) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `dob` date DEFAULT NULL,
  `gender` enum('L','P') NOT NULL,
  `address` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `prescriptions`
--

CREATE TABLE `prescriptions` (
  `id` bigint(20) NOT NULL,
  `visit_id` bigint(20) NOT NULL,
  `rx_no` varchar(30) NOT NULL,
  `content` mediumtext NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `public_documents`
--

CREATE TABLE `public_documents` (
  `id` bigint(20) NOT NULL,
  `token` char(32) NOT NULL,
  `doc_type` varchar(40) NOT NULL,
  `doc_id` bigint(20) NOT NULL,
  `doc_no` varchar(40) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `revoked` tinyint(1) NOT NULL DEFAULT 0,
  `revoked_at` datetime DEFAULT NULL,
  `note` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `public_document_access_logs`
--

CREATE TABLE `public_document_access_logs` (
  `id` bigint(20) NOT NULL,
  `public_document_id` bigint(20) NOT NULL,
  `accessed_at` datetime NOT NULL,
  `ip` varchar(80) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referrals`
--

CREATE TABLE `referrals` (
  `id` bigint(20) NOT NULL,
  `visit_id` bigint(20) DEFAULT NULL,
  `patient_id` bigint(20) NOT NULL,
  `sender_doctor_id` int(11) NOT NULL,
  `referral_no` varchar(32) NOT NULL,
  `referred_to_doctor` varchar(120) NOT NULL,
  `referred_to_specialty` varchar(120) NOT NULL,
  `diagnosis` mediumtext NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `referral_doctors`
--

CREATE TABLE `referral_doctors` (
  `id` int(11) NOT NULL,
  `name` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `key` varchar(80) NOT NULL,
  `value` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sick_letters`
--

CREATE TABLE `sick_letters` (
  `id` bigint(20) NOT NULL,
  `visit_id` bigint(20) NOT NULL,
  `patient_id` bigint(20) NOT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `letter_no` varchar(30) NOT NULL,
  `diagnosis` mediumtext DEFAULT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `notes` mediumtext DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','dokter','perawat','sekretariat') NOT NULL DEFAULT 'dokter',
  `full_name` varchar(120) DEFAULT NULL,
  `sip` varchar(80) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `usg_images`
--

CREATE TABLE `usg_images` (
  `id` bigint(20) NOT NULL,
  `visit_id` bigint(20) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `caption` varchar(120) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `visits`
--

CREATE TABLE `visits` (
  `id` bigint(20) NOT NULL,
  `patient_id` bigint(20) NOT NULL,
  `visit_no` varchar(30) NOT NULL,
  `visit_date` datetime NOT NULL,
  `anamnesis` mediumtext DEFAULT NULL,
  `physical_exam` mediumtext DEFAULT NULL,
  `usg_report` mediumtext DEFAULT NULL,
  `therapy` mediumtext DEFAULT NULL,
  `doctor_id` int(11) DEFAULT NULL,
  `referral_doctor_id` int(11) DEFAULT NULL,
  `is_usg` tinyint(1) NOT NULL DEFAULT 0,
  `usg_type` enum('diagnostic','interventional') DEFAULT NULL,
  `signature_path` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `visit_queue`
--

CREATE TABLE `visit_queue` (
  `id` bigint(20) NOT NULL,
  `patient_id` bigint(20) NOT NULL,
  `queue_date` date NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'new',
  `handled_visit_id` bigint(20) DEFAULT NULL,
  `created_by` bigint(20) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `consents`
--
ALTER TABLE `consents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `consent_no` (`consent_no`),
  ADD KEY `visit_id` (`visit_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `patients`
--
ALTER TABLE `patients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mrn` (`mrn`);

--
-- Indexes for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rx_no` (`rx_no`),
  ADD KEY `visit_id` (`visit_id`);

--
-- Indexes for table `public_documents`
--
ALTER TABLE `public_documents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_public_documents_token` (`token`),
  ADD UNIQUE KEY `uq_public_documents_doc` (`doc_type`,`doc_id`),
  ADD KEY `idx_public_documents_doc_type` (`doc_type`),
  ADD KEY `idx_public_documents_revoked` (`revoked`);

--
-- Indexes for table `public_document_access_logs`
--
ALTER TABLE `public_document_access_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_pd_access_doc` (`public_document_id`),
  ADD KEY `idx_pd_access_time` (`accessed_at`);

--
-- Indexes for table `referrals`
--
ALTER TABLE `referrals`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `referral_no` (`referral_no`),
  ADD KEY `idx_visit_id` (`visit_id`),
  ADD KEY `idx_patient_id` (`patient_id`),
  ADD KEY `idx_sender_doctor_id` (`sender_doctor_id`);

--
-- Indexes for table `referral_doctors`
--
ALTER TABLE `referral_doctors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_referral_doctors_name` (`name`),
  ADD KEY `is_active` (`is_active`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`);

--
-- Indexes for table `sick_letters`
--
ALTER TABLE `sick_letters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `letter_no` (`letter_no`),
  ADD KEY `visit_id` (`visit_id`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `usg_images`
--
ALTER TABLE `usg_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_visit_id` (`visit_id`);

--
-- Indexes for table `visits`
--
ALTER TABLE `visits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `visit_no` (`visit_no`),
  ADD KEY `patient_id` (`patient_id`),
  ADD KEY `visit_date` (`visit_date`),
  ADD KEY `idx_visits_referral_doctor_id` (`referral_doctor_id`),
  ADD KEY `idx_visits_is_usg` (`is_usg`),
  ADD KEY `idx_visits_usg_type` (`usg_type`),
  ADD KEY `idx_visits_visit_date` (`visit_date`);

--
-- Indexes for table `visit_queue`
--
ALTER TABLE `visit_queue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_queue_date` (`queue_date`),
  ADD KEY `idx_patient_date` (`patient_id`,`queue_date`),
  ADD KEY `idx_status_date` (`status`,`queue_date`),
  ADD KEY `idx_handled_visit` (`handled_visit_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `consents`
--
ALTER TABLE `consents`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `patients`
--
ALTER TABLE `patients`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `prescriptions`
--
ALTER TABLE `prescriptions`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `public_documents`
--
ALTER TABLE `public_documents`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `public_document_access_logs`
--
ALTER TABLE `public_document_access_logs`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referrals`
--
ALTER TABLE `referrals`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `referral_doctors`
--
ALTER TABLE `referral_doctors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sick_letters`
--
ALTER TABLE `sick_letters`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `usg_images`
--
ALTER TABLE `usg_images`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `visits`
--
ALTER TABLE `visits`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `visit_queue`
--
ALTER TABLE `visit_queue`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `consents`
--
ALTER TABLE `consents`
  ADD CONSTRAINT `fk_consent_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_consent_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `prescriptions`
--
ALTER TABLE `prescriptions`
  ADD CONSTRAINT `prescriptions_ibfk_1` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `referrals`
--
ALTER TABLE `referrals`
  ADD CONSTRAINT `fk_ref_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ref_sender` FOREIGN KEY (`sender_doctor_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_ref_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sick_letters`
--
ALTER TABLE `sick_letters`
  ADD CONSTRAINT `fk_sick_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_sick_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `usg_images`
--
ALTER TABLE `usg_images`
  ADD CONSTRAINT `fk_usg_images_visit` FOREIGN KEY (`visit_id`) REFERENCES `visits` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `visits`
--
ALTER TABLE `visits`
  ADD CONSTRAINT `visits_ibfk_1` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
