-- Добавление поля для автогенерации контента на основе имени файла
ALTER TABLE `content_groups` 
ADD COLUMN `use_auto_generation` tinyint(1) DEFAULT 0 COMMENT 'Использовать автогенерацию контента на основе имени файла вместо шаблона' AFTER `template_id`;
