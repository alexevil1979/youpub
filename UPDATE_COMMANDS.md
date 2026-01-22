# Команды для обновления сайта

## Быстрое обновление (после git push)

После каждого изменения кода выполните на сервере:

```bash
# 1. Перейти в директорию проекта
cd /ssd/www/youpub

# 2. Получить последние изменения из Git
git pull origin main

# 3. Если были изменения в БД, применить миграции
# Проверьте, какие миграции нужно применить:
ls -la database/migrations/

# Применить конкретную миграцию:
mysql -u youpub_user -p youpub_db < database/migrations/006_fix_youtube_account_fields.sql

# Или применить все новые миграции (если есть скрипт):
# bash database/apply_migration.sh

# 4. Проверить права на файлы (если нужно)
sudo chown -R www-data:www-data /ssd/www/youpub
sudo chmod -R 755 /ssd/www/youpub
sudo chmod -R 775 /ssd/www/youpub/storage

# 5. Перезапустить PHP-FPM (если нужно)
sudo systemctl restart php8.1-fpm
# или
sudo service php8.1-fpm restart

# 6. Проверить логи на ошибки
tail -f /var/log/apache2/error.log
# или
tail -f /ssd/www/youpub/storage/logs/error.log
```

## Полное обновление с проверкой

```bash
# 1. Перейти в директорию проекта
cd /ssd/www/youpub

# 2. Сохранить текущие изменения (если есть локальные правки)
git stash

# 3. Получить последние изменения
git pull origin main

# 4. Проверить статус
git status

# 5. Применить миграции БД (если есть новые)
# Список миграций:
ls -lt database/migrations/ | head -10

# Применить последнюю миграцию:
mysql -u youpub_user -p youpub_db < database/migrations/006_fix_youtube_account_fields.sql

# 6. Проверить конфигурацию
php -l index.php
php -l app/Controllers/DashboardController.php

# 7. Проверить права
ls -la storage/
ls -la storage/uploads/

# 8. Перезапустить сервисы
sudo systemctl restart apache2
sudo systemctl restart php8.1-fpm

# 9. Проверить работу сайта
curl -I https://you.1tlt.ru
```

## Обновление после конкретных изменений

### После изменений в YouTube интеграции

```bash
cd /ssd/www/youpub
git pull origin main

# Применить миграцию для полей account_name и is_default
mysql -u youpub_user -p youpub_db < database/migrations/006_fix_youtube_account_fields.sql

# Проверить структуру таблицы
mysql -u youpub_user -p youpub_db -e "DESCRIBE youtube_integrations;"

# Перезапустить PHP-FPM
sudo systemctl restart php8.1-fpm
```

### После изменений в коде (без миграций)

```bash
cd /ssd/www/youpub
git pull origin main
sudo systemctl restart php8.1-fpm
```

### После изменений в конфигурации

```bash
cd /ssd/www/youpub
git pull origin main

# Проверить config/env.php (не должен быть в git, но проверить)
cat config/env.php | grep YOUTUBE

# Перезапустить все сервисы
sudo systemctl restart apache2
sudo systemctl restart php8.1-fpm
```

## Проверка после обновления

```bash
# 1. Проверить логи на ошибки
tail -n 50 /var/log/apache2/error.log | grep -i error
tail -n 50 /ssd/www/youpub/storage/logs/error.log

# 2. Проверить работу сайта
curl https://you.1tlt.ru/login

# 3. Проверить подключение к БД
mysql -u youpub_user -p youpub_db -e "SELECT COUNT(*) FROM users;"

# 4. Проверить структуру таблиц
mysql -u youpub_user -p youpub_db -e "SHOW TABLES;"
mysql -u youpub_user -p youpub_db -e "DESCRIBE youtube_integrations;"
```

## Откат изменений (если что-то пошло не так)

```bash
cd /ssd/www/youpub

# Посмотреть последние коммиты
git log --oneline -10

# Откатиться на предыдущий коммит
git reset --hard HEAD~1

# Или откатиться на конкретный коммит
git reset --hard <commit_hash>

# Принудительно обновить
git pull origin main --force
```

## Автоматический скрипт обновления

Создайте файл `/ssd/www/youpub/update.sh`:

```bash
#!/bin/bash
set -e

echo "🔄 Обновление сайта YouPub..."

cd /ssd/www/youpub

echo "📥 Получение изменений из Git..."
git pull origin main

echo "✅ Обновление завершено!"

# Если нужно применить миграции, раскомментируйте:
# echo "🗄️ Применение миграций..."
# mysql -u youpub_user -p youpub_db < database/migrations/006_fix_youtube_account_fields.sql

echo "🔄 Перезапуск PHP-FPM..."
sudo systemctl restart php8.1-fpm

echo "✅ Готово! Сайт обновлен."
```

Сделать исполняемым:
```bash
chmod +x /ssd/www/youpub/update.sh
```

Использовать:
```bash
/ssd/www/youpub/update.sh
```
