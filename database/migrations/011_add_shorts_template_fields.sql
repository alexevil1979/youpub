-- Миграция: Добавление полей для оптимизации YouTube Shorts
-- Версия: 11.0
-- Дата: 2026-01-24
-- Добавляет новые поля для создания уникальных шаблонов Shorts

-- Проверяем и добавляем колонку hook_type
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'publication_templates' AND COLUMN_NAME = 'hook_type');
SET @sql = IF(@column_exists = 0, 'ALTER TABLE `publication_templates` ADD COLUMN `hook_type` enum(\'emotional\',\'intriguing\',\'atmospheric\',\'visual\',\'educational\') DEFAULT NULL COMMENT \'Тип контента (триггер)\' AFTER `variants`', 'SELECT \'hook_type already exists\' as status');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Проверяем и добавляем колонку focus_points
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'publication_templates' AND COLUMN_NAME = 'focus_points');
SET @sql = IF(@column_exists = 0, 'ALTER TABLE `publication_templates` ADD COLUMN `focus_points` text COMMENT \'JSON: массив фокусов видео (голос, неон, атмосфера и т.д.)\' AFTER `hook_type`', 'SELECT \'focus_points already exists\' as status');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Проверяем и добавляем колонку title_variants
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'publication_templates' AND COLUMN_NAME = 'title_variants');
SET @sql = IF(@column_exists = 0, 'ALTER TABLE `publication_templates` ADD COLUMN `title_variants` text COMMENT \'JSON: массив вариантов названий для A/B тестирования\' AFTER `focus_points`', 'SELECT \'title_variants already exists\' as status');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Проверяем и добавляем колонку description_variants
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'publication_templates' AND COLUMN_NAME = 'description_variants');
SET @sql = IF(@column_exists = 0, 'ALTER TABLE `publication_templates` ADD COLUMN `description_variants` text COMMENT \'JSON: объект с вариантами описаний по типам триггеров\' AFTER `title_variants`', 'SELECT \'description_variants already exists\' as status');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Проверяем и добавляем колонку emoji_groups
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'publication_templates' AND COLUMN_NAME = 'emoji_groups');
SET @sql = IF(@column_exists = 0, 'ALTER TABLE `publication_templates` ADD COLUMN `emoji_groups` text COMMENT \'JSON: объект с группами emoji по типам контента\' AFTER `description_variants`', 'SELECT \'emoji_groups already exists\' as status');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Проверяем и добавляем колонку base_tags
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'publication_templates' AND COLUMN_NAME = 'base_tags');
SET @sql = IF(@column_exists = 0, 'ALTER TABLE `publication_templates` ADD COLUMN `base_tags` text COMMENT \'Основные теги (всегда присутствуют)\' AFTER `emoji_groups`', 'SELECT \'base_tags already exists\' as status');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Проверяем и добавляем колонку tag_variants
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'publication_templates' AND COLUMN_NAME = 'tag_variants');
SET @sql = IF(@column_exists = 0, 'ALTER TABLE `publication_templates` ADD COLUMN `tag_variants` text COMMENT \'JSON: массив вариантов ротации тегов\' AFTER `base_tags`', 'SELECT \'tag_variants already exists\' as status');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Проверяем и добавляем колонку questions
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'publication_templates' AND COLUMN_NAME = 'questions');
SET @sql = IF(@column_exists = 0, 'ALTER TABLE `publication_templates` ADD COLUMN `questions` text COMMENT \'JSON: массив вопросов для вовлечённости\' AFTER `tag_variants`', 'SELECT \'questions already exists\' as status');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Проверяем и добавляем колонку pinned_comments
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'publication_templates' AND COLUMN_NAME = 'pinned_comments');
SET @sql = IF(@column_exists = 0, 'ALTER TABLE `publication_templates` ADD COLUMN `pinned_comments` text COMMENT \'JSON: массив вариантов закрепленных комментариев\' AFTER `questions`', 'SELECT \'pinned_comments already exists\' as status');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Проверяем и добавляем колонку cta_types
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'publication_templates' AND COLUMN_NAME = 'cta_types');
SET @sql = IF(@column_exists = 0, 'ALTER TABLE `publication_templates` ADD COLUMN `cta_types` text COMMENT \'JSON: массив типов CTA (call to action)\' AFTER `pinned_comments`', 'SELECT \'cta_types already exists\' as status');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Проверяем и добавляем колонку enable_ab_testing
SET @column_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'publication_templates' AND COLUMN_NAME = 'enable_ab_testing');
SET @sql = IF(@column_exists = 0, 'ALTER TABLE `publication_templates` ADD COLUMN `enable_ab_testing` tinyint(1) DEFAULT 1 COMMENT \'Включить A/B тестирование названий\' AFTER `cta_types`', 'SELECT \'enable_ab_testing already exists\' as status');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;