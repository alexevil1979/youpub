-- Миграция: Добавление полей для оптимизации YouTube Shorts
-- Версия: 11.0
-- Дата: 2026-01-24
-- Добавляет новые поля для создания уникальных шаблонов Shorts

-- Проверяем существование таблицы
SET @table_exists = (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'publication_templates'
);

-- Добавляем новые поля по одному, проверяя их существование
-- hook_type
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'publication_templates'
    AND COLUMN_NAME = 'hook_type'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `publication_templates` ADD COLUMN `hook_type` enum(\'emotional\',\'intriguing\',\'atmospheric\',\'visual\',\'educational\') DEFAULT NULL COMMENT \'Тип контента (триггер)\' AFTER `variants`;',
    'SELECT "Column hook_type already exists" as result;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- focus_points
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'publication_templates'
    AND COLUMN_NAME = 'focus_points'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `publication_templates` ADD COLUMN `focus_points` text COMMENT \'JSON: массив фокусов видео (голос, неон, атмосфера и т.д.)\' AFTER `hook_type`;',
    'SELECT "Column focus_points already exists" as result;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- title_variants
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'publication_templates'
    AND COLUMN_NAME = 'title_variants'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `publication_templates` ADD COLUMN `title_variants` text COMMENT \'JSON: массив вариантов названий для A/B тестирования\' AFTER `focus_points`;',
    'SELECT "Column title_variants already exists" as result;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- description_variants
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'publication_templates'
    AND COLUMN_NAME = 'description_variants'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `publication_templates` ADD COLUMN `description_variants` text COMMENT \'JSON: объект с вариантами описаний по типам триггеров\' AFTER `title_variants`;',
    'SELECT "Column description_variants already exists" as result;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- emoji_groups
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'publication_templates'
    AND COLUMN_NAME = 'emoji_groups'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `publication_templates` ADD COLUMN `emoji_groups` text COMMENT \'JSON: объект с группами emoji по типам контента\' AFTER `description_variants`;',
    'SELECT "Column emoji_groups already exists" as result;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- base_tags
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'publication_templates'
    AND COLUMN_NAME = 'base_tags'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `publication_templates` ADD COLUMN `base_tags` text COMMENT \'Основные теги (всегда присутствуют)\' AFTER `emoji_groups`;',
    'SELECT "Column base_tags already exists" as result;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- tag_variants
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'publication_templates'
    AND COLUMN_NAME = 'tag_variants'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `publication_templates` ADD COLUMN `tag_variants` text COMMENT \'JSON: массив вариантов ротации тегов\' AFTER `base_tags`;',
    'SELECT "Column tag_variants already exists" as result;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- questions
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'publication_templates'
    AND COLUMN_NAME = 'questions'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `publication_templates` ADD COLUMN `questions` text COMMENT \'JSON: массив вопросов для вовлечённости\' AFTER `tag_variants`;',
    'SELECT "Column questions already exists" as result;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- pinned_comments
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'publication_templates'
    AND COLUMN_NAME = 'pinned_comments'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `publication_templates` ADD COLUMN `pinned_comments` text COMMENT \'JSON: массив вариантов закрепленных комментариев\' AFTER `questions`;',
    'SELECT "Column pinned_comments already exists" as result;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- cta_types
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'publication_templates'
    AND COLUMN_NAME = 'cta_types'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `publication_templates` ADD COLUMN `cta_types` text COMMENT \'JSON: массив типов CTA (call to action)\' AFTER `pinned_comments`;',
    'SELECT "Column cta_types already exists" as result;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- enable_ab_testing
SET @column_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'publication_templates'
    AND COLUMN_NAME = 'enable_ab_testing'
);

SET @sql = IF(@column_exists = 0,
    'ALTER TABLE `publication_templates` ADD COLUMN `enable_ab_testing` tinyint(1) DEFAULT 1 COMMENT \'Включить A/B тестирование названий\' AFTER `cta_types`;',
    'SELECT "Column enable_ab_testing already exists" as result;'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT 'Shorts template fields migration completed successfully' as result;