-- Миграция: Модуль управления группами контента
-- Версия: 2.0
-- Дата: 2026-01-22

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
  CONSTRAINT `content_groups_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица связи групп и файлов (many-to-many)
CREATE TABLE IF NOT EXISTS `content_group_files` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `video_id` int(11) NOT NULL,
  `status` enum('new','queued','published','error','skipped') DEFAULT 'new',
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
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  KEY `is_active` (`is_active`),
  CONSTRAINT `publication_templates_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Обновление таблицы schedules для поддержки групп
-- Используем процедуру для безопасного добавления колонок
DELIMITER $$

DROP PROCEDURE IF EXISTS add_schedule_columns$$
CREATE PROCEDURE add_schedule_columns()
BEGIN
    -- Проверяем и добавляем колонки только если их нет
    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'schedules' 
                   AND COLUMN_NAME = 'content_group_id') THEN
        ALTER TABLE `schedules` ADD COLUMN `content_group_id` int(11) DEFAULT NULL AFTER `video_id`;
    END IF;

    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'schedules' 
                   AND COLUMN_NAME = 'template_id') THEN
        ALTER TABLE `schedules` ADD COLUMN `template_id` int(11) DEFAULT NULL AFTER `content_group_id`;
    END IF;

    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'schedules' 
                   AND COLUMN_NAME = 'schedule_type') THEN
        ALTER TABLE `schedules` ADD COLUMN `schedule_type` enum('fixed','interval','batch','random','wave') DEFAULT 'fixed' AFTER `platform`;
    END IF;

    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'schedules' 
                   AND COLUMN_NAME = 'interval_minutes') THEN
        ALTER TABLE `schedules` ADD COLUMN `interval_minutes` int(11) DEFAULT NULL COMMENT 'Для interval типа';
    END IF;

    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'schedules' 
                   AND COLUMN_NAME = 'batch_count') THEN
        ALTER TABLE `schedules` ADD COLUMN `batch_count` int(11) DEFAULT NULL COMMENT 'Для batch типа';
    END IF;

    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'schedules' 
                   AND COLUMN_NAME = 'batch_window_hours') THEN
        ALTER TABLE `schedules` ADD COLUMN `batch_window_hours` int(11) DEFAULT NULL COMMENT 'Для batch типа';
    END IF;

    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'schedules' 
                   AND COLUMN_NAME = 'random_window_start') THEN
        ALTER TABLE `schedules` ADD COLUMN `random_window_start` time DEFAULT NULL COMMENT 'Для random типа';
    END IF;

    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'schedules' 
                   AND COLUMN_NAME = 'random_window_end') THEN
        ALTER TABLE `schedules` ADD COLUMN `random_window_end` time DEFAULT NULL COMMENT 'Для random типа';
    END IF;

    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'schedules' 
                   AND COLUMN_NAME = 'wave_config') THEN
        ALTER TABLE `schedules` ADD COLUMN `wave_config` text COMMENT 'JSON: конфигурация для wave типа';
    END IF;

    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'schedules' 
                   AND COLUMN_NAME = 'weekdays') THEN
        ALTER TABLE `schedules` ADD COLUMN `weekdays` varchar(20) DEFAULT NULL COMMENT '1,2,3,4,5,6,7 (пн-вс)';
    END IF;

    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'schedules' 
                   AND COLUMN_NAME = 'active_hours_start') THEN
        ALTER TABLE `schedules` ADD COLUMN `active_hours_start` time DEFAULT NULL;
    END IF;

    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'schedules' 
                   AND COLUMN_NAME = 'active_hours_end') THEN
        ALTER TABLE `schedules` ADD COLUMN `active_hours_end` time DEFAULT NULL;
    END IF;

    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'schedules' 
                   AND COLUMN_NAME = 'daily_limit') THEN
        ALTER TABLE `schedules` ADD COLUMN `daily_limit` int(11) DEFAULT NULL COMMENT 'Лимит видео в день';
    END IF;

    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'schedules' 
                   AND COLUMN_NAME = 'hourly_limit') THEN
        ALTER TABLE `schedules` ADD COLUMN `hourly_limit` int(11) DEFAULT NULL COMMENT 'Лимит видео в час';
    END IF;

    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'schedules' 
                   AND COLUMN_NAME = 'delay_between_posts') THEN
        ALTER TABLE `schedules` ADD COLUMN `delay_between_posts` int(11) DEFAULT NULL COMMENT 'Задержка между публикациями (минуты)';
    END IF;

    IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
                   WHERE TABLE_SCHEMA = DATABASE() 
                   AND TABLE_NAME = 'schedules' 
                   AND COLUMN_NAME = 'skip_published') THEN
        ALTER TABLE `schedules` ADD COLUMN `skip_published` tinyint(1) DEFAULT 1 COMMENT 'Пропускать уже опубликованные из группы';
    END IF;
END$$

DELIMITER ;

-- Выполняем процедуру
CALL add_schedule_columns();

-- Удаляем процедуру
DROP PROCEDURE IF EXISTS add_schedule_columns;

-- Добавляем индексы и внешние ключи (если их еще нет)
SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                     WHERE TABLE_SCHEMA = DATABASE() 
                     AND TABLE_NAME = 'schedules' 
                     AND INDEX_NAME = 'content_group_id');
SET @sql = IF(@index_exists = 0, 
    'ALTER TABLE `schedules` ADD KEY `content_group_id` (`content_group_id`)', 
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
                     WHERE TABLE_SCHEMA = DATABASE() 
                     AND TABLE_NAME = 'schedules' 
                     AND INDEX_NAME = 'template_id');
SET @sql = IF(@index_exists = 0, 
    'ALTER TABLE `schedules` ADD KEY `template_id` (`template_id`)', 
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Добавляем внешние ключи (если их еще нет)
SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                  WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'schedules' 
                  AND CONSTRAINT_NAME = 'schedules_ibfk_3');
SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE `schedules` ADD CONSTRAINT `schedules_ibfk_3` FOREIGN KEY (`content_group_id`) REFERENCES `content_groups` (`id`) ON DELETE CASCADE', 
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                  WHERE TABLE_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'schedules' 
                  AND CONSTRAINT_NAME = 'schedules_ibfk_4');
SET @sql = IF(@fk_exists = 0, 
    'ALTER TABLE `schedules` ADD CONSTRAINT `schedules_ibfk_4` FOREIGN KEY (`template_id`) REFERENCES `publication_templates` (`id`) ON DELETE SET NULL', 
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Таблица статистики публикаций по группам
CREATE TABLE IF NOT EXISTS `group_statistics` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `group_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `total_files` int(11) DEFAULT 0,
  `published_count` int(11) DEFAULT 0,
  `failed_count` int(11) DEFAULT 0,
  `queued_count` int(11) DEFAULT 0,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `group_date_unique` (`group_id`, `date`),
  KEY `group_id` (`group_id`),
  KEY `date` (`date`),
  CONSTRAINT `group_statistics_ibfk_1` FOREIGN KEY (`group_id`) REFERENCES `content_groups` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Таблица логов публикаций (расширенная)
CREATE TABLE IF NOT EXISTS `publication_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `publication_id` int(11) DEFAULT NULL,
  `schedule_id` int(11) DEFAULT NULL,
  `group_id` int(11) DEFAULT NULL,
  `video_id` int(11) NOT NULL,
  `platform` enum('youtube','telegram','tiktok','instagram','pinterest') NOT NULL,
  `action` varchar(50) NOT NULL COMMENT 'upload, update, delete, error',
  `status` enum('success','failed','pending') DEFAULT 'pending',
  `request_data` text COMMENT 'JSON: данные запроса к API',
  `response_data` text COMMENT 'JSON: ответ от API',
  `error_message` text,
  `execution_time` decimal(10,3) DEFAULT NULL COMMENT 'Время выполнения в секундах',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `publication_id` (`publication_id`),
  KEY `schedule_id` (`schedule_id`),
  KEY `group_id` (`group_id`),
  KEY `video_id` (`video_id`),
  KEY `platform` (`platform`),
  KEY `status` (`status`),
  KEY `created_at` (`created_at`),
  CONSTRAINT `publication_logs_ibfk_1` FOREIGN KEY (`publication_id`) REFERENCES `publications` (`id`) ON DELETE SET NULL,
  CONSTRAINT `publication_logs_ibfk_2` FOREIGN KEY (`schedule_id`) REFERENCES `schedules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `publication_logs_ibfk_3` FOREIGN KEY (`group_id`) REFERENCES `content_groups` (`id`) ON DELETE SET NULL,
  CONSTRAINT `publication_logs_ibfk_4` FOREIGN KEY (`video_id`) REFERENCES `videos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Индексы для оптимизации
CREATE INDEX IF NOT EXISTS `idx_schedules_group_status` ON `schedules` (`content_group_id`, `status`);
CREATE INDEX IF NOT EXISTS `idx_group_files_status` ON `content_group_files` (`group_id`, `status`);
CREATE INDEX IF NOT EXISTS `idx_group_files_order` ON `content_group_files` (`group_id`, `order_index`, `status`);
