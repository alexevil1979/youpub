-- Добавляем флаг "Генерировать контент при публикации" в шаблоны
ALTER TABLE `publication_templates`
ADD COLUMN `generate_on_publish` tinyint(1) DEFAULT 0 COMMENT 'Генерировать контент при публикации из имени файла (через GigaChat AI)' AFTER `enable_ab_testing`;
