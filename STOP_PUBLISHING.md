# Экстренная остановка публикации

## Быстрая остановка

### 1. Остановить все расписания через скрипт

```bash
cd /ssd/www/youpub
php workers/emergency_stop.php
```

Этот скрипт:
- Остановит все pending расписания с группами (переведет в 'paused')
- Очистит все зависшие processing расписания
- Вернет файлы из статуса 'queued' в 'new'
- Покажет статистику

### 2. Остановить через SQL (быстро)

```sql
-- Остановить все pending расписания с группами
UPDATE schedules 
SET status = 'paused',
    error_message = 'Emergency stop'
WHERE status = 'pending'
AND content_group_id IS NOT NULL;

-- Очистить зависшие processing расписания
UPDATE schedules 
SET status = 'failed',
    error_message = 'Emergency stop - stuck cleared'
WHERE status = 'processing'
AND content_group_id IS NOT NULL;

-- Вернуть файлы из queued в new
UPDATE content_group_files 
SET status = 'new'
WHERE status = 'queued';
```

### 3. Остановить конкретное расписание

```sql
-- Остановить расписание по ID
UPDATE schedules 
SET status = 'paused'
WHERE id = 48;
```

### 4. Временно отключить cron (если нужно)

```bash
# Найти cron задачи
crontab -l | grep smart_publish

# Временно закомментировать (добавить # в начале строки)
# */1 * * * * cd /ssd/www/youpub && php workers/smart_publish_worker.php >> /dev/null 2>&1
```

## Возобновление публикации

### 1. Через веб-интерфейс
- Откройте страницу расписания
- Нажмите кнопку "Воспроизвести" (▶)

### 2. Через SQL
```sql
-- Возобновить расписание
UPDATE schedules 
SET status = 'pending'
WHERE id = 48;
```

### 3. Включить cron обратно
```bash
# Раскомментировать строку в crontab
crontab -e
```

## Проверка состояния

```sql
-- Проверить активные расписания
SELECT id, content_group_id, status, publish_at, error_message
FROM schedules
WHERE content_group_id IS NOT NULL
ORDER BY id DESC
LIMIT 10;

-- Проверить файлы в очереди
SELECT cgf.id, cgf.group_id, cgf.video_id, cgf.status, v.title
FROM content_group_files cgf
JOIN videos v ON v.id = cgf.video_id
WHERE cgf.status IN ('queued', 'new')
ORDER BY cgf.group_id, cgf.order_index;
```
