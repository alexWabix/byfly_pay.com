-- ByFly Travel Payment Center Database Schema
-- Version: 1.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

-- Создание базы данных
CREATE DATABASE IF NOT EXISTS `payments` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `payments`;

-- Таблица администраторов
CREATE TABLE `admins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `country_code` varchar(5) NOT NULL DEFAULT '+7',
  `country_name` varchar(50) DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `allowed_countries` JSON DEFAULT NULL COMMENT 'Страны к которым есть доступ',
  `allowed_payment_systems` JSON DEFAULT NULL COMMENT 'Платежные системы к которым есть доступ',
  `is_super_admin` tinyint(1) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `phone` (`phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица SMS кодов для авторизации
CREATE TABLE `sms_codes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `phone` varchar(20) NOT NULL,
  `code` varchar(10) NOT NULL,
  `expires_at` datetime NOT NULL,
  `is_used` tinyint(1) DEFAULT 0,
  `attempts` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `phone` (`phone`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица JWT токенов
CREATE TABLE `admin_tokens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `admin_id` int(11) NOT NULL,
  `token` varchar(500) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `admin_id` (`admin_id`),
  KEY `token` (`token`(255)),
  FOREIGN KEY (`admin_id`) REFERENCES `admins`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица источников (приложения, сайты и т.д.)
CREATE TABLE `sources` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL COMMENT 'Название источника',
  `type` enum('website','mobile_app','desktop_app','other') DEFAULT 'website',
  `description` text DEFAULT NULL,
  `api_token` varchar(64) NOT NULL COMMENT 'Токен для API',
  `webhook_url` varchar(500) DEFAULT NULL COMMENT 'URL для webhook уведомлений',
  `allowed_ips` JSON DEFAULT NULL COMMENT 'Разрешенные IP адреса',
  `is_active` tinyint(1) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `api_token` (`api_token`),
  KEY `created_by` (`created_by`),
  FOREIGN KEY (`created_by`) REFERENCES `admins`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица способов оплаты
CREATE TABLE `payment_methods` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) NOT NULL COMMENT 'Код способа оплаты',
  `name` varchar(255) NOT NULL,
  `country` varchar(50) NOT NULL COMMENT 'Страна',
  `type` enum('card','wallet','terminal','credit','installment','other') DEFAULT 'card',
  `provider` varchar(100) DEFAULT NULL COMMENT 'Провайдер (Kaspi, Click, Payme и т.д.)',
  `commission_percent` decimal(5,2) DEFAULT 0.00 COMMENT 'Процент комиссии',
  `has_credit` tinyint(1) DEFAULT 0 COMMENT 'Доступен кредит',
  `credit_commission_percent` decimal(5,2) DEFAULT 0.00 COMMENT 'Процент за кредит',
  `has_installment` tinyint(1) DEFAULT 0 COMMENT 'Доступна рассрочка',
  `installment_months` int(11) DEFAULT NULL COMMENT 'Количество месяцев рассрочки',
  `installment_commission_percent` decimal(5,2) DEFAULT 0.00 COMMENT 'Процент за рассрочку',
  `add_commission_to_amount` tinyint(1) DEFAULT 1 COMMENT 'Добавлять комиссию к сумме',
  `settings` JSON DEFAULT NULL COMMENT 'Дополнительные настройки (API ключи и т.д.)',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `country` (`country`),
  KEY `provider` (`provider`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица Kaspi терминалов
CREATE TABLE `kaspi_terminals` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) DEFAULT NULL,
  `ip_address` varchar(50) NOT NULL,
  `port` int(11) NOT NULL,
  `camera_id` int(11) DEFAULT NULL COMMENT 'ID камеры для считывания QR',
  `camera_url` varchar(500) DEFAULT NULL COMMENT 'URL для получения QR с камеры',
  `is_active` tinyint(1) DEFAULT 1,
  `is_busy` tinyint(1) DEFAULT 0 COMMENT 'Занят ли терминал',
  `last_used` datetime DEFAULT NULL,
  `last_check` datetime DEFAULT NULL,
  `status` enum('online','offline','error') DEFAULT 'offline',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip_port` (`ip_address`,`port`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица транзакций
CREATE TABLE `transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` varchar(100) NOT NULL COMMENT 'Уникальный ID транзакции',
  `source_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL COMMENT 'Сумма к оплате',
  `original_amount` decimal(12,2) NOT NULL COMMENT 'Оригинальная сумма без комиссий',
  `currency` varchar(10) DEFAULT 'KZT',
  `description` text DEFAULT NULL COMMENT 'Описание платежа',
  `customer_phone` varchar(20) DEFAULT NULL,
  `customer_email` varchar(255) DEFAULT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `payment_method_id` int(11) DEFAULT NULL,
  `payment_type` enum('full','partial') DEFAULT 'full',
  `status` enum('pending','processing','paid','partially_paid','cancelled','failed','refunded') DEFAULT 'pending',
  `paid_amount` decimal(12,2) DEFAULT 0.00 COMMENT 'Оплаченная сумма',
  `remaining_amount` decimal(12,2) DEFAULT 0.00 COMMENT 'Остаток к оплате',
  `commission_amount` decimal(12,2) DEFAULT 0.00 COMMENT 'Сумма комиссии',
  `webhook_url` varchar(500) DEFAULT NULL,
  `webhook_sent` tinyint(1) DEFAULT 0,
  `webhook_response` text DEFAULT NULL,
  `metadata` JSON DEFAULT NULL COMMENT 'Дополнительные данные от источника',
  `payment_url` varchar(500) DEFAULT NULL COMMENT 'Ссылка для оплаты',
  `qr_code` text DEFAULT NULL COMMENT 'QR код для оплаты',
  `kaspi_terminal_id` int(11) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `transaction_id` (`transaction_id`),
  KEY `source_id` (`source_id`),
  KEY `payment_method_id` (`payment_method_id`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`source_id`) REFERENCES `sources`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`kaspi_terminal_id`) REFERENCES `kaspi_terminals`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица частичных платежей
CREATE TABLE `partial_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) NOT NULL,
  `payment_method_id` int(11) NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `commission_percent` decimal(5,2) DEFAULT 0.00,
  `commission_amount` decimal(12,2) DEFAULT 0.00,
  `total_amount` decimal(12,2) NOT NULL COMMENT 'Сумма с комиссией',
  `status` enum('pending','paid','failed') DEFAULT 'pending',
  `payment_details` JSON DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `transaction_id` (`transaction_id`),
  KEY `payment_method_id` (`payment_method_id`),
  FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица логов транзакций
CREATE TABLE `transaction_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL COMMENT 'Действие (init, status_check, payment, cancel и т.д.)',
  `status` varchar(50) DEFAULT NULL,
  `message` text DEFAULT NULL,
  `request_data` JSON DEFAULT NULL,
  `response_data` JSON DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `user_agent` varchar(500) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `transaction_id` (`transaction_id`),
  KEY `created_at` (`created_at`),
  FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Вставка дефолтного администратора
INSERT INTO `admins` (`phone`, `country_code`, `country_name`, `name`, `is_super_admin`, `is_active`) 
VALUES ('+77780021666', '+7', 'Казахстан', 'Super Admin', 1, 1);

-- Вставка дефолтных способов оплаты для Казахстана
INSERT INTO `payment_methods` (`code`, `name`, `country`, `type`, `provider`, `commission_percent`, `has_credit`, `credit_commission_percent`, `has_installment`, `sort_order`) VALUES
('kaspi_gold', 'Kaspi Gold', 'Казахстан', 'wallet', 'Kaspi', 1.00, 0, 0.00, 0, 1),
('kaspi_red', 'Kaspi Red', 'Казахстан', 'card', 'Kaspi', 1.00, 0, 0.00, 0, 2),
('kaspi_credit', 'Kaspi Кредит', 'Казахстан', 'credit', 'Kaspi', 0.00, 1, 14.00, 0, 3),
('kaspi_installment_12', 'Kaspi рассрочка 0-0-12', 'Казахстан', 'installment', 'Kaspi', 0.00, 0, 0.00, 1, 4),
('kaspi_installment_24', 'Kaspi рассрочка 0-0-24', 'Казахстан', 'installment', 'Kaspi', 0.00, 0, 0.00, 1, 5);

UPDATE `payment_methods` SET `installment_months` = 12, `installment_commission_percent` = 14.00 WHERE `code` = 'kaspi_installment_12';
UPDATE `payment_methods` SET `installment_months` = 24, `installment_commission_percent` = 14.00 WHERE `code` = 'kaspi_installment_24';

-- Вставка дефолтных Kaspi терминалов (используем HTTPS и порт 8080 согласно документации)
INSERT INTO `kaspi_terminals` (`name`, `ip_address`, `port`, `camera_id`, `camera_url`, `is_active`, `access_token`) VALUES
('Терминал 1', 'https://109.175.215.40', 8080, 4, 'http://109.175.215.40:3000/scan-qr/4', 1, NULL),
('Терминал 2', 'https://109.175.215.40', 8080, 3, 'http://109.175.215.40:3000/scan-qr/3', 1, NULL),
('Терминал 3', 'https://109.175.215.40', 8080, 8, 'http://109.175.215.40:3000/scan-qr/8', 1, NULL),
('Терминал 4', 'https://109.175.215.40', 8080, 7, 'http://109.175.215.40:3000/scan-qr/7', 1, NULL),
('Терминал 5', 'https://109.175.215.40', 8080, 5, 'http://109.175.215.40:3000/scan-qr/5', 1, NULL),
('Терминал 6', 'https://109.175.215.40', 8080, 1, 'http://109.175.215.40:3000/scan-qr/1', 1, NULL),
('Терминал 7', 'https://109.175.215.40', 8080, 2, 'http://109.175.215.40:3000/scan-qr/2', 1, NULL);

