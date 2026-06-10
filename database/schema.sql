-- ============================================================
-- ZD Birthdays — Database Schema
-- Database: zdbd
-- Charset: utf8mb4 | Engine: InnoDB
--
-- Import via phpMyAdmin:  Import tab → choose this file → Go
-- Import via CLI:         mysql -u root < database/schema.sql
-- ============================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+02:00";
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `zdbd`
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE `zdbd`;

-- Drop tables in reverse FK dependency order (safe re-import)
DROP TABLE IF EXISTS `birthday_wishes`;
DROP TABLE IF EXISTS `employee_images`;
DROP TABLE IF EXISTS `login_attempts`;
DROP TABLE IF EXISTS `employees`;
DROP TABLE IF EXISTS `admin_users`;
DROP TABLE IF EXISTS `branches`;
DROP TABLE IF EXISTS `countdown_captions`;
DROP TABLE IF EXISTS `site_settings`;
DROP TABLE IF EXISTS `companies`;

-- ------------------------------------------------------------
-- Companies
-- ------------------------------------------------------------
CREATE TABLE `companies` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(100) NOT NULL,
  `short_code` VARCHAR(10)  NOT NULL,
  `logo_path`  VARCHAR(255)          DEFAULT NULL,
  `created_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_short_code` (`short_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Branches
-- ------------------------------------------------------------
CREATE TABLE `branches` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `company_id` INT(11)      NOT NULL,
  `name`       VARCHAR(100) NOT NULL,
  `created_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_company` (`company_id`),
  CONSTRAINT `fk_branches_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Positions (per-company job titles — drives the form dropdown)
-- ------------------------------------------------------------
CREATE TABLE `positions` (
  `id`         INT(11)      NOT NULL AUTO_INCREMENT,
  `company_id` INT(11)      NOT NULL,
  `title`      VARCHAR(100) NOT NULL,
  `tier`       INT(11)      NOT NULL DEFAULT 0,   -- hierarchy level (1 = top)
  PRIMARY KEY (`id`),
  KEY `idx_pos_company` (`company_id`),
  CONSTRAINT `fk_pos_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Admin Users
-- ------------------------------------------------------------
CREATE TABLE `admin_users` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `username`      VARCHAR(50)  NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `created_by`    INT(11)               DEFAULT NULL,
  `session_token` VARCHAR(64)           DEFAULT NULL,
  `last_login`    TIMESTAMP NULL         DEFAULT NULL,
  `created_at`    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_username` (`username`),
  KEY `idx_created_by` (`created_by`),
  CONSTRAINT `fk_admin_creator`
    FOREIGN KEY (`created_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Login Attempts  (IP-based brute-force throttling for admin login)
-- ------------------------------------------------------------
CREATE TABLE `login_attempts` (
  `id`           INT(11)     NOT NULL AUTO_INCREMENT,
  `ip`           VARCHAR(45) NOT NULL,
  `attempted_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ip_time` (`ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Employees
-- ------------------------------------------------------------
CREATE TABLE `employees` (
  `id`              INT(11)      NOT NULL AUTO_INCREMENT,
  `full_name`       VARCHAR(150) NOT NULL,
  `birthdate`       CHAR(5)      NOT NULL COMMENT 'Format: MM-DD',
  `gender`          ENUM('M','F') NOT NULL,
  `company_id`      INT(11)      NOT NULL,
  `branch_id`       INT(11)      NOT NULL,
  `position`        VARCHAR(100)          DEFAULT NULL,
  `primary_message` TEXT                  DEFAULT NULL,
  `image_count`     INT(11)      NOT NULL DEFAULT 0,
  `locked_by`       INT(11)               DEFAULT NULL,
  `locked_at`       TIMESTAMP NULL         DEFAULT NULL,
  `created_at`      TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_birthdate`  (`birthdate`),
  KEY `idx_company`    (`company_id`),
  KEY `idx_branch`     (`branch_id`),
  KEY `idx_locked_by`  (`locked_by`),
  CONSTRAINT `fk_emp_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_emp_branch`
    FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  CONSTRAINT `fk_emp_locked_by`
    FOREIGN KEY (`locked_by`) REFERENCES `admin_users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Employee Images  - 1 per employee
-- ------------------------------------------------------------
CREATE TABLE `employee_images` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `employee_id` INT(11)      NOT NULL,
  `image_path`  VARCHAR(255) NOT NULL,
  `sort_order`  TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employee` (`employee_id`),
  CONSTRAINT `fk_images_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Birthday Wishes
-- ------------------------------------------------------------
CREATE TABLE `birthday_wishes` (
  `id`          INT(11)      NOT NULL AUTO_INCREMENT,
  `employee_id` INT(11)      NOT NULL,
  `author_name` VARCHAR(100) NOT NULL,
  `message`     TEXT         NOT NULL,
  `session_id`  VARCHAR(128) NOT NULL,
  `is_active`   TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`  TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employee`   (`employee_id`),
  KEY `idx_session`    (`session_id`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_wishes_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Countdown Captions  (admin-managed, rotated on banner)
-- ------------------------------------------------------------
CREATE TABLE `countdown_captions` (
  `id`           INT(11)      NOT NULL AUTO_INCREMENT,
  `caption_text` VARCHAR(255) NOT NULL,
  `is_active`    TINYINT(1)   NOT NULL DEFAULT 1,
  `created_at`   TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Site Settings  (key-value store)
-- ------------------------------------------------------------
CREATE TABLE `site_settings` (
  `id`            INT(11)      NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT                  DEFAULT NULL,
  `updated_at`    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ============================================================
-- SEED DATA
-- ============================================================

-- Companies
INSERT INTO `companies` (`name`, `short_code`) VALUES
('Zambezi Diamond Group',       'ZDG'),
('Zambezi Diamond Limited',     'ZDL'),
('Zambezi Diamond Construction','ZDC'),
('Impact Business Solutions',   'IBS'),
('Blu Reef',                    'BR');

-- Branches
INSERT INTO `branches` (`company_id`, `name`) VALUES
-- ZD (id=1)
(1, 'Ndola'),
-- ZDL (id=2)
(2, 'Ndola'),
(2, 'Lusaka'),
(2, 'Kabwe'),
(2, 'Chingola'),
(2, 'Livingstone'),
(2, 'Kitwe'),
(2, 'Solwezi'),
-- ZDC (id=3)
(3, 'Ndola'),
-- IBS (id=4)
(4, 'Ndola'),
(4, 'Lusaka'),
-- BR (id=5)
(5, 'Ndola');

-- Positions (per-company job titles, tier = hierarchy level)
INSERT INTO `positions` (`company_id`, `title`, `tier`) VALUES
-- ZDG (id=1)
(1, 'Board Chairman', 1),
(1, 'Group Managing Director', 2),
(1, 'Finance Director', 3),
(1, 'HR Director', 4),
(1, 'Company Secretary', 4),
(1, 'Internal Auditor', 5),
(1, 'Media & I.T Officer', 6),
(1, 'Assistant Internal Auditor', 6),
(1, 'HR Officer', 7),
(1, 'Assistant Media & I.T Officer', 7),
(1, 'Driver', 7),
(1, 'Office Assistant', 8),
(1, 'General Worker', 9),
-- ZDL (id=2)
(2, 'Director', 1),
(2, 'Managing Director', 2),
(2, 'Public & Customer Relations Manager', 3),
(2, 'Sales Manager', 3),
(2, 'Finance Manager', 3),
(2, 'Debt Collections & Recons Manager', 3),
(2, 'Branch Manager', 4),
(2, 'Debt Collections & Recons Manager Assistant', 4),
(2, 'Customer Relations Officer', 5),
(2, 'Operations Officer', 5),
(2, 'Legal Officer', 5),
(2, 'Conveyance Personnel', 5),
(2, 'Debt Collections & Recons Officer', 5),
(2, 'Assistant Accountant', 6),
(2, 'Sales Consultant', 7),
(2, 'General Worker', 8),
-- ZDC (id=3)
(3, 'Director', 1),
(3, 'Managing Director', 2),
(3, 'Sales Manager', 3),
(3, 'Marketing Manager', 3),
(3, 'Finance Manager', 3),
(3, 'Construction Manager', 3),
(3, 'Project Supervisor', 4),
(3, 'Planner', 5),
(3, 'Surveyor', 5),
(3, 'Quantity Surveyor', 5),
(3, 'Purchasing Officer', 6),
(3, 'Assistant Accountant', 6),
(3, 'Sales Consultant', 7),
(3, 'Transport Foreman', 7),
(3, 'Metal Fabricator', 7),
(3, 'Stores Clerk', 8),
(3, 'General Worker', 8),
-- IBS (id=4)
(4, 'Director', 1),
(4, 'Managing Director', 2),
(4, 'Branch Manager', 3),
(4, 'Financial Accountant', 4),
(4, 'Business Development Manager (Ndola)', 4),
(4, 'Business Development & Credit Officer', 4),
(4, 'Assistant Accountant', 5),
(4, 'Cashier', 5),
(4, 'Loan Officer', 5),
(4, 'General Worker', 6),
-- BR (id=5)
(5, 'Director', 1),
(5, 'Managing Director', 2),
(5, 'Compliance Manager', 3),
(5, 'Finance Manager', 3),
(5, 'Debt & Recons Manager', 4),
(5, 'Sales & Marketing Manager', 4),
(5, 'Marketing Executive', 5),
(5, 'Sales Consultant', 6),
(5, 'Operations Officer', 6),
(5, 'General Worker', 7);

-- Default countdown captions
INSERT INTO `countdown_captions` (`caption_text`) VALUES
('Guess who\'s birthday it is tomorrow? 🎂'),
('Get your balloons and cake ready — someone\'s about to be celebrated! 🎈'),
('Big day tomorrow! Make sure you\'re ready to show some love! 🎉'),
('Someone special is turning another year fabulous tomorrow! 🥳'),
('The countdown is on — tomorrow is going to be a great day! 🎊'),
('Shhhh... someone\'s birthday is almost here! 🤫🎂'),
('Cake, candles and confetti incoming — tomorrow is the day! 🕯️'),
('Tomorrow someone in our team deserves all the birthday love! ❤️'),
('A star is about to celebrate another trip around the sun! ⭐');

-- Default site settings
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('fallback_image_male',      NULL),
('fallback_image_female',    NULL),
('no_birthday_image',        NULL),
('site_name',                'Birthdays.ZambeziDiamond'),
('wish_edit_window_seconds', '30'),
('lock_timeout_minutes',     '10');

SET FOREIGN_KEY_CHECKS = 1;
