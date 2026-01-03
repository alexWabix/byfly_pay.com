-- Миграция: Создание таблицы system_logs для логов системы

USE `payments`;

-- Таблица системных логов
CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `action` varchar(100) NOT NULL COMMENT 'Действие (terminals_check, cleanup и т.д.)',
  `status` enum('success','warning','error') DEFAULT 'success',
  `message` text DEFAULT NULL,
  `data` JSON DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `action` (`action`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;




