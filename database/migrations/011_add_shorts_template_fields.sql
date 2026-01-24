-- Миграция: Добавление полей для оптимизации YouTube Shorts
-- Версия: 11.0
-- Дата: 2026-01-24
-- Добавляет новые поля для создания уникальных шаблонов Shorts

-- Добавляем колонку hook_type если её нет
ALTER TABLE `publication_templates`
ADD COLUMN IF NOT EXISTS `hook_type` enum('emotional','intriguing','atmospheric','visual','educational') DEFAULT NULL COMMENT 'Тип контента (триггер)' AFTER `variants`;

-- Добавляем колонку focus_points если её нет
ALTER TABLE `publication_templates`
ADD COLUMN IF NOT EXISTS `focus_points` text COMMENT 'JSON: массив фокусов видео (голос, неон, атмосфера и т.д.)' AFTER `hook_type`;

-- Добавляем колонку title_variants если её нет
ALTER TABLE `publication_templates`
ADD COLUMN IF NOT EXISTS `title_variants` text COMMENT 'JSON: массив вариантов названий для A/B тестирования' AFTER `focus_points`;

-- Добавляем колонку description_variants если её нет
ALTER TABLE `publication_templates`
ADD COLUMN IF NOT EXISTS `description_variants` text COMMENT 'JSON: объект с вариантами описаний по типам триггеров' AFTER `title_variants`;

-- Добавляем колонку emoji_groups если её нет
ALTER TABLE `publication_templates`
ADD COLUMN IF NOT EXISTS `emoji_groups` text COMMENT 'JSON: объект с группами emoji по типам контента' AFTER `description_variants`;

-- Добавляем колонку base_tags если её нет
ALTER TABLE `publication_templates`
ADD COLUMN IF NOT EXISTS `base_tags` text COMMENT 'Основные теги (всегда присутствуют)' AFTER `emoji_groups`;

-- Добавляем колонку tag_variants если её нет
ALTER TABLE `publication_templates`
ADD COLUMN IF NOT EXISTS `tag_variants` text COMMENT 'JSON: массив вариантов ротации тегов' AFTER `base_tags`;

-- Добавляем колонку questions если её нет
ALTER TABLE `publication_templates`
ADD COLUMN IF NOT EXISTS `questions` text COMMENT 'JSON: массив вопросов для вовлечённости' AFTER `tag_variants`;

-- Добавляем колонку pinned_comments если её нет
ALTER TABLE `publication_templates`
ADD COLUMN IF NOT EXISTS `pinned_comments` text COMMENT 'JSON: массив вариантов закрепленных комментариев' AFTER `questions`;

-- Добавляем колонку cta_types если её нет
ALTER TABLE `publication_templates`
ADD COLUMN IF NOT EXISTS `cta_types` text COMMENT 'JSON: массив типов CTA (call to action)' AFTER `pinned_comments`;

-- Добавляем колонку enable_ab_testing если её нет
ALTER TABLE `publication_templates`
ADD COLUMN IF NOT EXISTS `enable_ab_testing` tinyint(1) DEFAULT 1 COMMENT 'Включить A/B тестирование названий' AFTER `cta_types`;