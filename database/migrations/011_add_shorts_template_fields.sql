-- Миграция: Добавление полей для оптимизации YouTube Shorts
-- Версия: 11.0
-- Дата: 2026-01-24
-- Добавляет новые поля для создания уникальных шаблонов Shorts

DELIMITER $$

DROP PROCEDURE IF EXISTS add_shorts_template_fields$$
CREATE PROCEDURE add_shorts_template_fields()
BEGIN
    -- Добавляем новые поля для оптимизации Shorts
    SET @table_exists = (
        SELECT COUNT(*)
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'publication_templates'
    );

    IF @table_exists > 0 THEN
        -- Добавляем новые поля по одному, проверяя их существование

        -- hook_type
        SET @column_exists = (
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'publication_templates'
            AND COLUMN_NAME = 'hook_type'
        );

        IF @column_exists = 0 THEN
            ALTER TABLE `publication_templates`
            ADD COLUMN `hook_type` enum('emotional','intriguing','atmospheric','visual','educational') DEFAULT NULL COMMENT 'Тип контента (триггер)' AFTER `variants`;
        END IF;

        -- focus_points
        SET @column_exists = (
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'publication_templates'
            AND COLUMN_NAME = 'focus_points'
        );

        IF @column_exists = 0 THEN
            ALTER TABLE `publication_templates`
            ADD COLUMN `focus_points` text COMMENT 'JSON: массив фокусов видео (голос, неон, атмосфера и т.д.)' AFTER `hook_type`;
        END IF;

        -- title_variants
        SET @column_exists = (
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'publication_templates'
            AND COLUMN_NAME = 'title_variants'
        );

        IF @column_exists = 0 THEN
            ALTER TABLE `publication_templates`
            ADD COLUMN `title_variants` text COMMENT 'JSON: массив вариантов названий для A/B тестирования' AFTER `focus_points`;
        END IF;

        -- description_variants
        SET @column_exists = (
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'publication_templates'
            AND COLUMN_NAME = 'description_variants'
        );

        IF @column_exists = 0 THEN
            ALTER TABLE `publication_templates`
            ADD COLUMN `description_variants` text COMMENT 'JSON: объект с вариантами описаний по типам триггеров' AFTER `title_variants`;
        END IF;

        -- emoji_groups
        SET @column_exists = (
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'publication_templates'
            AND COLUMN_NAME = 'emoji_groups'
        );

        IF @column_exists = 0 THEN
            ALTER TABLE `publication_templates`
            ADD COLUMN `emoji_groups` text COMMENT 'JSON: объект с группами emoji по типам контента' AFTER `description_variants`;
        END IF;

        -- base_tags
        SET @column_exists = (
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'publication_templates'
            AND COLUMN_NAME = 'base_tags'
        );

        IF @column_exists = 0 THEN
            ALTER TABLE `publication_templates`
            ADD COLUMN `base_tags` text COMMENT 'Основные теги (всегда присутствуют)' AFTER `emoji_groups`;
        END IF;

        -- tag_variants
        SET @column_exists = (
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'publication_templates'
            AND COLUMN_NAME = 'tag_variants'
        );

        IF @column_exists = 0 THEN
            ALTER TABLE `publication_templates`
            ADD COLUMN `tag_variants` text COMMENT 'JSON: массив вариантов ротации тегов' AFTER `base_tags`;
        END IF;

        -- questions
        SET @column_exists = (
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'publication_templates'
            AND COLUMN_NAME = 'questions'
        );

        IF @column_exists = 0 THEN
            ALTER TABLE `publication_templates`
            ADD COLUMN `questions` text COMMENT 'JSON: массив вопросов для вовлечённости' AFTER `tag_variants`;
        END IF;

        -- pinned_comments
        SET @column_exists = (
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'publication_templates'
            AND COLUMN_NAME = 'pinned_comments'
        );

        IF @column_exists = 0 THEN
            ALTER TABLE `publication_templates`
            ADD COLUMN `pinned_comments` text COMMENT 'JSON: массив вариантов закрепленных комментариев' AFTER `questions`;
        END IF;

        -- cta_types
        SET @column_exists = (
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'publication_templates'
            AND COLUMN_NAME = 'cta_types'
        );

        IF @column_exists = 0 THEN
            ALTER TABLE `publication_templates`
            ADD COLUMN `cta_types` text COMMENT 'JSON: массив типов CTA (call to action)' AFTER `pinned_comments`;
        END IF;

        -- enable_ab_testing
        SET @column_exists = (
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'publication_templates'
            AND COLUMN_NAME = 'enable_ab_testing'
        );

        IF @column_exists = 0 THEN
            ALTER TABLE `publication_templates`
            ADD COLUMN `enable_ab_testing` tinyint(1) DEFAULT 1 COMMENT 'Включить A/B тестирование названий' AFTER `cta_types`;
        END IF;

        SELECT 'Shorts template fields added successfully' as result;
    ELSE
        SELECT 'Table publication_templates does not exist' as result;
    END IF;
END$$

DELIMITER ;

CALL add_shorts_template_fields();
DROP PROCEDURE IF EXISTS add_shorts_template_fields;