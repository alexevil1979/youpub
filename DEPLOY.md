# Инструкция по развертыванию на VPS

## Подготовка сервера

### 1. Обновление системы

```bash
sudo apt update && sudo apt upgrade -y
```

### 2. Установка необходимого ПО

```bash
# PHP 8.1 и расширения
sudo apt install -y php8.1 php8.1-cli php8.1-fpm php8.1-mysql php8.1-mbstring php8.1-xml php8.1-curl php8.1-zip

# Apache
sudo apt install -y apache2 libapache2-mod-php8.1

# MySQL
sudo apt install -y mysql-server

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Git
sudo apt install -y git
```

### 3. Настройка MySQL

```bash
sudo mysql_secure_installation
```

Создайте базу данных и пользователя:

```bash
sudo mysql -u root -p
```

```sql
CREATE DATABASE youpub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'youpub_user'@'localhost' IDENTIFIED BY 'qweasd333123';
GRANT ALL PRIVILEGES ON youpub.* TO 'youpub_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### 4. Клонирование проекта

```bash
cd /ssd/www
sudo git clone https://github.com/alexevil1979/youpub.git youpub
cd youpub
sudo composer install --no-dev --optimize-autoloader
```

### 5. Настройка прав доступа

```bash
sudo chown -R www-data:www-data /ssd/www/youpub
sudo chmod -R 755 /ssd/www/youpub
sudo chmod -R 775 /ssd/www/youpub/storage
sudo chmod +x /ssd/www/youpub/cron/*.sh
```

### 6. Настройка конфигурации

```bash
cd /ssd/www/youpub
sudo cp config/env.example.php config/env.php
sudo nano config/env.php
```

Обязательно измените:
- `SECRET_KEY` - сгенерируйте случайную строку (минимум 32 символа)
- `JWT_SECRET` - сгенерируйте случайную строку (минимум 32 символа)
- Проверьте параметры БД

Генерация секретных ключей:
```bash
openssl rand -hex 32
```

### 7. Импорт базы данных

```bash
mysql -u youpub_user -p youpub < /ssd/www/youpub/database/schema.sql
```

### 8. Настройка Apache

Создайте виртуальный хост:

```bash
sudo nano /etc/apache2/sites-available/you.1tlt.ru.conf
```

Содержимое файла:

```apache
<VirtualHost *:80>
    ServerName you.1tlt.ru
    ServerAlias www.you.1tlt.ru
    DocumentRoot /ssd/www/youpub

    <Directory /ssd/www/youpub>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/youpub_error.log
    CustomLog ${APACHE_LOG_DIR}/youpub_access.log combined
</VirtualHost>
```

Активируйте сайт:

```bash
sudo a2ensite you.1tlt.ru.conf
sudo a2enmod rewrite
sudo systemctl restart apache2
```

### 9. Настройка SSL (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d you.1tlt.ru -d www.you.1tlt.ru
```

Следуйте инструкциям. Certbot автоматически настроит SSL и обновление сертификатов.

### 10. Настройка Cron

Откройте crontab:

```bash
sudo crontab -e
```

Добавьте строки:

```
# Публикация видео (каждую минуту)
* * * * * /ssd/www/youpub/cron/publish.sh >> /var/log/youpub_publish.log 2>&1

# Сбор статистики (каждый час)
0 * * * * /ssd/www/youpub/cron/stats.sh >> /var/log/youpub_stats.log 2>&1
```

### 11. Настройка PHP

#### Вариант 1: Если используется mod_php (Apache)

Отредактируйте настройки PHP:

```bash
sudo nano /etc/php/8.1/apache2/php.ini
```

Измените:
```
upload_max_filesize = 5120M
post_max_size = 5120M
max_execution_time = 3600
memory_limit = 512M
```

#### Вариант 2: Если используется PHP-FPM (рекомендуется)

Настройте через конфигурацию виртуального хоста. Обновите файл `/etc/apache2/sites-available/you.1tlt.ru.conf`:

```apache
<VirtualHost *:80>
    ServerName you.1tlt.ru
    ServerAlias www.you.1tlt.ru
    DocumentRoot /ssd/www/youpub

    <Directory /ssd/www/youpub>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Настройки PHP-FPM
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php8.1-fpm.sock|fcgi://localhost"
    </FilesMatch>

    # PHP настройки для больших файлов
    php_admin_value upload_max_filesize 5120M
    php_admin_value post_max_size 5120M
    php_admin_value max_execution_time 3600
    php_admin_value memory_limit 512M

    ErrorLog ${APACHE_LOG_DIR}/youpub_error.log
    CustomLog ${APACHE_LOG_DIR}/youpub_access.log combined
</VirtualHost>
```

Или настройте через PHP-FPM pool:

```bash
sudo nano /etc/php/8.1/fpm/pool.d/www.conf
```

Найдите и измените:
```
php_admin_value[upload_max_filesize] = 5120M
php_admin_value[post_max_size] = 5120M
php_admin_value[max_execution_time] = 3600
php_admin_value[memory_limit] = 512M
```

Перезапустите сервисы:

```bash
sudo systemctl restart apache2
sudo systemctl restart php8.1-fpm
```

## Обновление проекта

Для обновления проекта на сервере:

```bash
cd /ssd/www/youpub
sudo git pull origin main
sudo composer install --no-dev --optimize-autoloader

git reset --hard
git pull origin main

# Если были изменения в БД
mysql -u youpub_user -p youpub < database/migrations/new_migration.sql

# Очистка кэша (если используется opcache)
sudo systemctl reload apache2
```

## Проверка работоспособности

1. Откройте в браузере: `https://you.1tlt.ru`
2. Войдите как администратор:
   - Email: `admin@you.1tlt.ru`
   - Пароль: `admin123`
3. Смените пароль администратора после первого входа!

## Мониторинг

Проверка логов:

```bash
# Логи Apache
sudo tail -f /var/log/apache2/youpub_error.log

# Логи workers
tail -f /ssd/www/youpub/storage/logs/workers/publish_*.log
tail -f /ssd/www/youpub/storage/logs/workers/stats_*.log

# Логи cron
tail -f /var/log/youpub_publish.log
tail -f /var/log/youpub_stats.log
```

## Безопасность

1. **Смените пароль администратора** после первого входа
2. **Измените SECRET_KEY и JWT_SECRET** в `config/env.php`
3. **Настройте firewall** (UFW):

```bash
sudo ufw allow 22/tcp
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
```

4. **Регулярно обновляйте систему**:

```bash
sudo apt update && sudo apt upgrade -y
```

## Резервное копирование

Создайте скрипт для резервного копирования:

```bash
#!/bin/bash
# backup.sh

BACKUP_DIR="/backup/youpub"
DATE=$(date +%Y%m%d_%H%M%S)

mkdir -p $BACKUP_DIR

# Бэкап БД
mysqldump -u youpub_user -p'qweasd333123' youpub > $BACKUP_DIR/db_$DATE.sql

# Бэкап файлов
tar -czf $BACKUP_DIR/files_$DATE.tar.gz /ssd/www/youpub/storage

# Удаление старых бэкапов (старше 7 дней)
find $BACKUP_DIR -type f -mtime +7 -delete
```

Добавьте в cron для ежедневного бэкапа:

```bash
0 2 * * * /path/to/backup.sh
```

## Поддержка

При возникновении проблем проверьте:
1. Логи Apache и PHP
2. Логи workers
3. Права доступа к файлам
4. Настройки БД
5. Статус сервисов (Apache, MySQL)
