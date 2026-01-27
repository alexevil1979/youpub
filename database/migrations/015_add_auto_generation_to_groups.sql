-- Добавление поля для автогенерации контента
-- 0 = не использовать (шаблон), 1 = на основе имени файла, 2 = на основе названия группы
ALTER TABLE `content_groups` 
ADD COLUMN `use_auto_generation` tinyint(1) DEFAULT 0 COMMENT 'Тип автогенерации: 0=шаблон, 1=имя файла, 2=название группы' AFTER `template_id`;
