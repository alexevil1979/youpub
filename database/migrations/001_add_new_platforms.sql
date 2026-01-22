-- Миграция: Добавление поддержки новых платформ (TikTok, Instagram, Pinterest)
-- Версия: 1.1

-- Добавление таблиц для новых интеграций
CREATE TABLE IF NOT EXISTS `tiktok_integrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `access_token` text,
  `refresh_token` text,
  `token_expires_at` datetime DEFAULT NULL,
  `open_id` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `status` enum('connected','disconnected','error') DEFAULT 'disconnected',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `tiktok_integrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `instagram_integrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `access_token` text,
  `refresh_token` text,
  `token_expires_at` datetime DEFAULT NULL,
  `instagram_account_id` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `status` enum('connected','disconnected','error') DEFAULT 'disconnected',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `instagram_integrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pinterest_integrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `access_token` text,
  `refresh_token` text,
  `token_expires_at` datetime DEFAULT NULL,
  `board_id` varchar(255) DEFAULT NULL,
  `board_name` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `status` enum('connected','disconnected','error') DEFAULT 'disconnected',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `pinterest_integrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Обновление enum в таблице schedules
ALTER TABLE `schedules` 
MODIFY COLUMN `platform` enum('youtube','telegram','tiktok','instagram','pinterest','both') NOT NULL;

-- Обновление enum в таблице publications
ALTER TABLE `publications` 
MODIFY COLUMN `platform` enum('youtube','telegram','tiktok','instagram','pinterest') NOT NULL;

-- Обновление enum в таблице statistics
ALTER TABLE `statistics` 
MODIFY COLUMN `platform` enum('youtube','telegram','tiktok','instagram','pinterest') NOT NULL;
