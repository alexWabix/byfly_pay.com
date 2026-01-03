-- Миграция: Добавление системы вебхуков
-- Дата: 2025-12-26

-- Добавляем поля для вебхуков в transactions
ALTER TABLE `transactions` 
ADD COLUMN `webhook_url` VARCHAR(500) NULL COMMENT 'URL для отправки вебхуков',
ADD COLUMN `webhook_secret` VARCHAR(64) NULL COMMENT 'Секретный ключ для подписи вебхуков',
ADD COLUMN `metadata` TEXT NULL COMMENT 'Дополнительные данные (JSON): user_id, order_id и т.д.',
ADD COLUMN `webhook_sent_at` DATETIME NULL COMMENT 'Когда был отправлен последний вебхук',
ADD COLUMN `webhook_attempts` INT DEFAULT 0 COMMENT 'Количество попыток отправки вебхука',
ADD COLUMN `webhook_last_error` TEXT NULL COMMENT 'Последняя ошибка вебхука',
ADD INDEX `idx_webhook_url` (`webhook_url`(255)),
ADD INDEX `idx_webhook_sent_at` (`webhook_sent_at`);

-- Создаем таблицу для логов вебхуков
CREATE TABLE IF NOT EXISTS `webhook_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `transaction_id` INT NOT NULL,
  `event_type` VARCHAR(50) NOT NULL COMMENT 'paid, partially_paid, cancelled, refunded',
  `webhook_url` VARCHAR(500) NOT NULL,
  `request_payload` TEXT COMMENT 'Отправленные данные (JSON)',
  `response_code` INT NULL COMMENT 'HTTP код ответа',
  `response_body` TEXT NULL COMMENT 'Тело ответа',
  `is_success` TINYINT(1) DEFAULT 0 COMMENT 'Успешно ли доставлен',
  `error_message` TEXT NULL COMMENT 'Сообщение об ошибке',
  `attempt_number` INT DEFAULT 1 COMMENT 'Номер попытки',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_transaction_id` (`transaction_id`),
  INDEX `idx_event_type` (`event_type`),
  INDEX `idx_is_success` (`is_success`),
  INDEX `idx_created_at` (`created_at`),
  FOREIGN KEY (`transaction_id`) REFERENCES `transactions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci 
COMMENT='Логи отправки вебхуков';

-- Комментарий к таблице transactions
ALTER TABLE `transactions` 
COMMENT = 'Транзакции платежей с поддержкой вебхуков';




