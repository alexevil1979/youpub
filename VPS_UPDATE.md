# Инструкция по обновлению на VPS

## Первоначальная установка

Если проект еще не установлен на VPS, выполните:

```bash
# Подключитесь к VPS по SSH
ssh user@your-vps-ip

# Перейдите в директорию веб-сервера
cd /var/www

# Клонируйте репозиторий
sudo git clone https://github.com/alexevil1979/youpub.git youpub
cd youpub

# Установите зависимости
sudo composer install --no-dev --optimize-autoloader

# Настройте конфигурацию
sudo cp config/env.example.php config/env.php
sudo nano config/env.php
# Заполните все необходимые параметры

# Создайте базу данных
sudo mysql -u root -p
```

В MySQL выполните:
```sql
CREATE DATABASE youpub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'youpub_user'@'localhost' IDENTIFIED BY 'qweasd333123';
GRANT ALL PRIVILEGES ON youpub.* TO 'youpub_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

```bash
# Импортируйте схему БД
sudo mysql -u youpub_user -p youpub < database/schema.sql

# Настройте права доступа
sudo chown -R www-data:www-data /var/www/youpub
sudo chmod -R 755 /var/www/youpub
sudo chmod -R 775 /var/www/youpub/storage
sudo chmod +x /var/www/youpub/cron/*.sh

# Настройте Apache (см. DEPLOY.md)
# Настройте SSL через certbot
# Настройте cron
```

## Обновление существующего проекта

После изменений в репозитории на GitHub, обновите проект на VPS:

```bash
# Подключитесь к VPS
ssh user@your-vps-ip

# Перейдите в директорию проекта
cd /var/www/youpub

# Получите последние изменения
sudo git pull origin main

# Обновите зависимости (если были изменения в composer.json)
sudo composer install --no-dev --optimize-autoloader

# Если были изменения в БД, выполните миграции
# sudo mysql -u youpub_user -p youpub < database/migrations/new_migration.sql

# Очистите кэш (если используется opcache)
sudo systemctl reload apache2
# или
sudo php -r "opcache_reset();"

# Проверьте права доступа
sudo chown -R www-data:www-data /var/www/youpub
sudo chmod -R 755 /var/www/youpub
sudo chmod -R 775 /var/www/youpub/storage
```

## Автоматическое обновление (опционально)

Можно создать скрипт для автоматического обновления:

```bash
sudo nano /usr/local/bin/youpub-update.sh
```

Содержимое:
```bash
#!/bin/bash
cd /var/www/youpub
git pull origin main
composer install --no-dev --optimize-autoloader
chown -R www-data:www-data /var/www/youpub
chmod -R 755 /var/www/youpub
chmod -R 775 /var/www/youpub/storage
systemctl reload apache2
echo "YouPub updated successfully"
```

Сделайте исполняемым:
```bash
sudo chmod +x /usr/local/bin/youpub-update.sh
```

Запуск:
```bash
sudo /usr/local/bin/youpub-update.sh
```

## Проверка после обновления

1. Проверьте работу сайта: `https://you.1tlt.ru`
2. Проверьте логи:
```bash
tail -f /var/log/apache2/youpub_error.log
tail -f /var/www/youpub/storage/logs/workers/publish_*.log
```
3. Проверьте статус workers (cron должен работать)

## Откат изменений (если что-то пошло не так)

```bash
cd /var/www/youpub
sudo git log --oneline  # посмотрите историю коммитов
sudo git reset --hard <commit-hash>  # откатитесь к нужному коммиту
sudo composer install --no-dev --optimize-autoloader
sudo systemctl reload apache2
```

## Важные замечания

1. **Всегда делайте бэкап БД перед обновлением:**
```bash
mysqldump -u youpub_user -p youpub > backup_$(date +%Y%m%d).sql
```

2. **Проверяйте изменения в config/env.php** - они не должны перезаписываться при git pull

3. **Если были изменения в структуре БД**, создайте файл миграции в `database/migrations/` и выполните его

4. **Проверяйте логи** после каждого обновления

## Репозиторий

GitHub: https://github.com/alexevil1979/youpub.git

Основная ветка: `main`
