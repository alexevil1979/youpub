-- База данных для системы автоматической публикации видео
-- Версия: 1.0

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- Таблица пользователей
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `name` varchar(255) DEFAULT NULL,
  `role` enum('user','admin') DEFAULT 'user',
  `status` enum('active','inactive','banned') DEFAULT 'active',
  `upload_limit` int(11) DEFAULT 100,
  `publish_limit` int(11) DEFAULT 50,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица сессий
CREATE TABLE `sessions` (
  `id` varchar(128) NOT NULL,
  `user_id` int(11) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text,
  `expires_at` datetime NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица интеграций YouTube
CREATE TABLE `youtube_integrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `channel_id` varchar(255) DEFAULT NULL,
  `channel_name` varchar(255) DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL COMMENT 'Название аккаунта для идентификации',
  `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию',
  `access_token` text,
  `refresh_token` text,
  `token_expires_at` datetime DEFAULT NULL,
  `status` enum('connected','disconnected','error') DEFAULT 'disconnected',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `youtube_integrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица интеграций Telegram
CREATE TABLE `telegram_integrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `bot_token` varchar(255) DEFAULT NULL,
  `channel_id` varchar(255) DEFAULT NULL,
  `channel_username` varchar(255) DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL COMMENT 'Название аккаунта для идентификации',
  `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию',
  `status` enum('connected','disconnected','error') DEFAULT 'disconnected',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `telegram_integrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица интеграций TikTok
CREATE TABLE `tiktok_integrations` (
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

-- Таблица интеграций Instagram
CREATE TABLE `instagram_integrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `access_token` text,
  `refresh_token` text,
  `token_expires_at` datetime DEFAULT NULL,
  `instagram_account_id` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL COMMENT 'Название аккаунта для идентификации',
  `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию',
  `status` enum('connected','disconnected','error') DEFAULT 'disconnected',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `instagram_integrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица интеграций Pinterest
CREATE TABLE `pinterest_integrations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `access_token` text,
  `refresh_token` text,
  `token_expires_at` datetime DEFAULT NULL,
  `board_id` varchar(255) DEFAULT NULL,
  `board_name` varchar(255) DEFAULT NULL,
  `username` varchar(255) DEFAULT NULL,
  `account_name` varchar(255) DEFAULT NULL COMMENT 'Название аккаунта для идентификации',
  `is_default` tinyint(1) DEFAULT 0 COMMENT 'Аккаунт по умолчанию',
  `status` enum('connected','disconnected','error') DEFAULT 'disconnected',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `pinterest_integrations_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица видео
CREATE TABLE `videos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `mime_type` varchar(100) DEFAULT NULL,
  `duration` int(11) DEFAULT NULL,
  `title` varchar(500) DEFAULT NULL,
  `description` text,
  `tags` text,
  `thumbnail_path` varchar(500) DEFAULT NULL,
  `status` enum('uploaded','processing','ready','error') DEFAULT 'uploaded',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`),
  CONSTRAINT `videos_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица расписаний публикаций
CREATE TABLE `schedules` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `video_id` int(11) DEFAULT NULL COMMENT 'NULL для расписаний групп контента',
  `content_group_id` int(11) DEFAULT NULL COMMENT 'Связь с группой контента',
  `template_id` int(11) DEFAULT NULL COMMENT 'Шаблон оформления',
  `platform` enum('youtube','telegram','tiktok','instagram','pinterest','both') NOT NULL,
  `schedule_type` enum('fixed','interval','batch','random','wave') DEFAULT 'fixed' COMMENT 'Тип расписания',
  `publish_at` datetime NOT NULL,
  `timezone` varchar(50) DEFAULT 'UTC',
  `repeat_type` enum('once','daily','weekly','monthly') DEFAULT 'once',
  `repeat_until` datetime DEFAULT NULL,
  `interval_minutes` int(11) DEFAULT NULL COMMENT 'Для interval типа',
  `batch_count` int(11) DEFAULT NULL COMMENT 'Для batch типа',
  `batch_window_hours` int(11) DEFAULT NULL COMMENT 'Для batch типа',
  `random_window_start` time DEFAULT NULL COMMENT 'Для random типа',
  `random_window_end` time DEFAULT NULL COMMENT 'Для random типа',
  `weekdays` varchar(20) DEFAULT NULL COMMENT '1,2,3,4,5,6,7 (пн-вс)',
  `active_hours_start` time DEFAULT NULL,
  `active_hours_end` time DEFAULT NULL,
  `daily_limit` int(11) DEFAULT NULL,
  `hourly_limit` int(11) DEFAULT NULL,
  `delay_between_posts` int(11) DEFAULT NULL COMMENT 'Минуты',
  `skip_published` tinyint(1) DEFAULT 1,
  `daily_time_points` text COMMENT 'JSON: массив временных точек для fixed типа',
  `daily_points_start_date` date DEFAULT NULL,
  `daily_points_end_date` date DEFAULT NULL,
  `status` enum('pending','processing','published','failed','cancelled','paused') DEFAULT 'pending',
  `error_message` text,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `video_id` (`video_id`),
  KEY `content_group_id` (`content_group_id`),
  KEY `template_id` (`template_id`),
  KEY `publish_at` (`publish_at`),
  KEY `status` (`status`),
  KEY `idx_schedules_group_status` (`content_group_id`, `status`),
  CONSTRAINT `schedules_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `schedules_ibfk_2` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `schedules_ibfk_3` FOREIGN KEY (`content_group_id`) REFERENCES `content_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `schedules_ibfk_4` FOREIGN KEY (`template_id`) REFERENCES `publication_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица публикаций (история)
CREATE TABLE `publications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `schedule_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `platform` enum('youtube','telegram','tiktok','instagram','pinterest') NOT NULL,
  `platform_id` varchar(255) DEFAULT NULL,
  `platform_url` varchar(500) DEFAULT NULL,
  `status` enum('success','failed') NOT NULL,
  `error_message` text,
  `published_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `schedule_id` (`schedule_id`),
  KEY `user_id` (`user_id`),
  KEY `video_id` (`video_id`),
  KEY `platform` (`platform`),
  KEY `published_at` (`published_at`),
  CONSTRAINT `publications_ibfk_1` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `publications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `publications_ibfk_3` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица статистики
CREATE TABLE `statistics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `publication_id` int(11) NOT NULL,
  `platform` enum('youtube','telegram','tiktok','instagram','pinterest') NOT NULL,
  `views` int(11) DEFAULT 0,
  `likes` int(11) DEFAULT 0,
  `comments` int(11) DEFAULT 0,
  `shares` int(11) DEFAULT 0,
  `subscribers` int(11) DEFAULT 0,
  `collected_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `publication_id` (`publication_id`),
  KEY `collected_at` (`collected_at`),
  CONSTRAINT `statistics_ibfk_1` FOREIGN KEY (`publication_id`) REFERENCES `publications` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица логов
CREATE TABLE `logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `type` enum('info','warning','error','debug') DEFAULT 'info',
  `module` varchar(100) DEFAULT NULL,
  `message` text NOT NULL,
  `context` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `type` (`type`),
  KEY `module` (`module`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица групп контента
CREATE TABLE IF NOT EXISTS `content_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `template_id` int(11) DEFAULT NULL,
  `status` enum('active','paused','archived') DEFAULT 'active',
  `settings` text COMMENT 'JSON: настройки группы (лимиты, задержки и т.д.)',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `template_id` (`template_id`),
  KEY `status` (`status`),
  CONSTRAINT `content_groups_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `content_groups_ibfk_2` FOREIGN KEY (`template_id`) REFERENCES `publication_templates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица связи групп и файлов (many-to-many)
CREATE TABLE IF NOT EXISTS `content_group_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `status` enum('new','queued','published','error','skipped','paused') DEFAULT 'new',
  `published_at` datetime DEFAULT NULL,
  `error_message` text,
  `publication_id` int(11) DEFAULT NULL COMMENT 'Ссылка на publications.id',
  `order_index` int(11) DEFAULT 0 COMMENT 'Порядок в очереди',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_video_unique` (`group_id`, `video_id`),
  KEY `group_id` (`group_id`),
  KEY `video_id` (`video_id`),
  KEY `status` (`status`),
  KEY `publication_id` (`publication_id`),
  CONSTRAINT `content_group_files_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `content_groups` (`id`) ON DELETE CASCADE,
  CONSTRAINT `content_group_files_ibfk_2` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `content_group_files_ibfk_3` FOREIGN KEY (`publication_id`) REFERENCES `publications` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица шаблонов оформления
CREATE TABLE IF NOT EXISTS `publication_templates` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text,
  `title_template` text COMMENT 'Шаблон названия с переменными',
  `description_template` text COMMENT 'Шаблон описания с переменными',
  `tags_template` text COMMENT 'Шаблон тегов с переменными',
  `emoji_list` text COMMENT 'JSON: список emoji для рандомизации',
  `variants` text COMMENT 'JSON: варианты текста для рандомизации',

  -- Новые поля для оптимизации YouTube Shorts
  `hook_type` enum('emotional','intriguing','atmospheric','visual','educational') DEFAULT NULL COMMENT 'Тип контента (триггер)',
  `focus_points` text COMMENT 'JSON: массив фокусов видео (голос, неон, атмосфера и т.д.)',
  `title_variants` text COMMENT 'JSON: массив вариантов названий для A/B тестирования',
  `description_variants` text COMMENT 'JSON: объект с вариантами описаний по типам триггеров',
  `emoji_groups` text COMMENT 'JSON: объект с группами emoji по типам контента',
  `base_tags` text COMMENT 'Основные теги (всегда присутствуют)',
  `tag_variants` text COMMENT 'JSON: массив вариантов ротации тегов',
  `questions` text COMMENT 'JSON: массив вопросов для вовлечённости',
  `pinned_comments` text COMMENT 'JSON: массив вариантов закрепленных комментариев',
  `cta_types` text COMMENT 'JSON: массив типов CTA (call to action)',
  `enable_ab_testing` tinyint(1) DEFAULT 1 COMMENT 'Включить A/B тестирование названий',

  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `publication_templates_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Создание администратора по умолчанию
-- Пароль: admin123 (замените после первого входа!)
-- Для сброса пароля используйте: php scripts/reset_admin_password.php [новый_пароль]
-- Хеш пароля admin123: $2y$10$UpoFWZEWKbS0afOLQy3.g.3I94M2WLxq2beqUclpiLIRSY4zJy6Cm
INSERT INTO `users` (`email`, `password_hash`, `name`, `role`, `status`) VALUES
('admin@you.1tlt.ru', '$2y$10$UpoFWZEWKbS0afOLQy3.g.3I94M2WLxq2beqUclpiLIRSY4zJy6Cm', 'Administrator', 'admin', 'active');

COMMIT;
