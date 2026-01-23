# Исправление проблем с PHP-FPM и логами

## 🔴 Проблема 1: PHP-FPM сервис не найден

Если команда `sudo systemctl restart php-fpm.service` выдает ошибку:
```
Failed to restart php-fpm.service: Unit php-fpm.service not found.
```

### Решение: Найдите правильное имя сервиса

Выполните на сервере:

```bash
# Вариант 1: Проверьте все доступные сервисы PHP-FPM
systemctl list-units | grep -i php

# Вариант 2: Проверьте установленную версию PHP
php -v

# Вариант 3: Попробуйте стандартные имена сервисов
sudo systemctl status php8.1-fpm
sudo systemctl status php-fpm8.1
sudo systemctl status php8.0-fpm
sudo systemctl status php-fpm

# Вариант 4: Если PHP-FPM установлен из исходников, проверьте процесс
ps aux | grep php-fpm

# Вариант 5: Найдите конфигурационный файл
find /etc -name "php-fpm.conf" 2>/dev/null
find /usr/local -name "php-fpm.conf" 2>/dev/null
```

### После того, как найдете правильное имя сервиса:

```bash
# Примеры команд для перезапуска:
sudo systemctl restart php8.1-fpm
# или
sudo systemctl restart php-fpm8.1
# или
sudo service php8.1-fpm restart
# или (если установлен из исходников)
sudo killall -USR2 php-fpm
```

## 🔴 Проблема 2: Файл логов не существует

Если команда `tail -f /ssd/www/youpub/storage/logs/error.log` выдает ошибку:
```
tail: cannot open '/ssd/www/youpub/storage/logs/error.log' for reading: No such file or directory
```

### Решение: Создайте директорию для логов

Выполните на сервере:

```bash
# 1. Создайте директорию для логов
cd /ssd/www/youpub
sudo mkdir -p storage/logs
sudo mkdir -p storage/logs/workers

# 2. Создайте файл логов
sudo touch storage/logs/error.log
sudo touch storage/logs/workers/smart_publish_$(date +%Y-%m-%d).log

# 3. Установите правильного владельца
# Сначала узнайте, под каким пользователем работает PHP-FPM
ps aux | grep php-fpm | head -1

# Обычно это www-data или nginx
sudo chown -R www-data:www-data storage/logs
# или
sudo chown -R nginx:nginx storage/logs

# 4. Установите права доступа
sudo chmod -R 755 storage/logs
sudo chmod 644 storage/logs/error.log

# 5. Проверьте, что файл создан
ls -la storage/logs/
```

### Альтернативные места для проверки логов:

```bash
# Логи PHP (обычное место)
tail -f /var/log/php8.1-fpm.log
tail -f /var/log/php-fpm.log

# Логи Apache/Nginx
tail -f /var/log/apache2/error.log
tail -f /var/log/nginx/error.log

# Системные логи
journalctl -u php8.1-fpm -f
journalctl -u php-fpm -f

# Логи приложения (если настроены)
tail -f /ssd/www/youpub/storage/logs/error.log
```

## 🔍 Проверка конфигурации

### Проверьте, где PHP пишет логи:

```bash
# Проверьте настройки error_log в PHP
php -i | grep error_log

# Проверьте настройки в php.ini
php --ini

# Откройте файл php.ini
sudo nano $(php --ini | grep "Loaded Configuration File" | awk '{print $4}')

# Найдите строку:
# error_log = /var/log/php_errors.log
# или
# error_log = syslog
```

### Настройте логирование в config/env.php:

Убедитесь, что в файле `/ssd/www/youpub/config/env.php` есть настройки:

```php
'LOG_DIR' => __DIR__ . '/../storage/logs',
'WORKER_LOG_DIR' => __DIR__ . '/../storage/logs/workers',
```

## ✅ Быстрая проверка

После создания директории и файлов, проверьте:

```bash
# 1. Проверьте, что директория существует
ls -la /ssd/www/youpub/storage/logs/

# 2. Попробуйте записать в лог
echo "Test log entry" | sudo tee -a /ssd/www/youpub/storage/logs/error.log

# 3. Проверьте права
ls -la /ssd/www/youpub/storage/logs/error.log

# 4. Попробуйте прочитать лог
tail -f /ssd/www/youpub/storage/logs/error.log
```

## 📝 Команды для быстрого исправления

Выполните все команды подряд:

```bash
cd /ssd/www/youpub

# Создайте директории
sudo mkdir -p storage/logs storage/logs/workers storage/uploads

# Создайте файлы логов
sudo touch storage/logs/error.log
sudo touch storage/logs/workers/smart_publish_$(date +%Y-%m-%d).log

# Установите владельца (замените www-data на вашего пользователя PHP-FPM)
PHP_USER=$(ps aux | grep php-fpm | grep -v grep | head -1 | awk '{print $1}')
sudo chown -R $PHP_USER:$PHP_USER storage

# Установите права
sudo chmod -R 755 storage
sudo chmod 644 storage/logs/*.log

# Проверьте
ls -la storage/logs/
```
