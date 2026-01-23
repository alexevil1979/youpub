# Остановка автоматической публикации

## Проблема
На странице `/schedules` каждую минуту появляются новые расписания со статусом "Processing". Это происходит из-за автоматического запуска worker-скриптов через cron.

## Решения

### 1. Приостановить умные расписания через интерфейс

1. Откройте страницу `/content-groups/schedules`
2. Найдите активные умные расписания
3. Нажмите кнопку "Пауза" для каждого расписания

### 2. Приостановить через базу данных

```sql
-- Приостановить все активные умные расписания
UPDATE schedules 
SET status = 'paused' 
WHERE content_group_id IS NOT NULL 
AND status = 'pending';
```

### 3. Очистить зависшие расписания 'processing'

```sql
-- Очистить все зависшие расписания 'processing' (старше 10 минут)
UPDATE schedules
SET status = 'failed',
    error_message = 'Processing timeout (10 minutes)'
WHERE status = 'processing'
AND created_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE);
```

### 4. Временно отключить cron-задачи

```bash
# Просмотреть текущие cron-задачи
crontab -l

# Отредактировать cron-задачи
crontab -e

# Закомментировать строки с worker-скриптами:
# * * * * * /ssd/www/youpub/cron/publish.sh
# * * * * * /ssd/www/youpub/cron/smart_publish.sh (если есть)
```

### 5. Удалить все зависшие расписания 'processing'

```sql
-- Удалить все расписания со статусом 'processing'
DELETE FROM schedules 
WHERE status = 'processing' 
AND content_group_id IS NOT NULL;
```

## После исправления

После применения исправлений:
1. Worker-скрипты автоматически очищают зависшие расписания
2. Проверяется наличие активных расписаний 'processing' перед созданием новых
3. Статус временных расписаний обновляется после публикации
4. Приостановленные расписания пропускаются

## Проверка

После обновления кода проверьте:
1. Откройте `/schedules` - не должно появляться новых расписаний каждую минуту
2. Проверьте логи worker-скриптов: `storage/logs/worker/smart_publish_*.log`
3. Убедитесь, что умные расписания приостановлены, если нужно
