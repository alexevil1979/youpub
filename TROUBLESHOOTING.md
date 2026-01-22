# Решение проблем (Troubleshooting)

## Ошибка: Invalid command 'php_value' в .htaccess

**Проблема:** 
```
[core:alert] Invalid command 'php_value', perhaps misspelled or defined by a module not included
```

**Решение:**
1. Обновите проект: `cd /ssd/www/youpub && sudo git pull origin main`
2. Директивы `php_value` уже удалены из `.htaccess`
3. Настройте PHP через конфигурацию виртуального хоста (см. DEPLOY.md раздел 11)

## Ошибка: Database connection failed - No such file or directory

**Проблема:**
```
SQLSTATE[HY000] [2002] No such file or directory
```

**Причина:** PHP пытается подключиться к MySQL через Unix socket вместо TCP/IP.

**Решение:**

### Вариант 1: Изменить DB_HOST в config/env.php

```bash
sudo nano /ssd/www/youpub/config/env.php
```

Измените:
```php
'DB_HOST' => '127.0.0.1',  // вместо 'localhost'
```

### Вариант 2: Проверить, что MySQL запущен

```bash
sudo systemctl status mysql
# или
sudo systemctl status mariadb
```

Если не запущен:
```bash
sudo systemctl start mysql
```

### Вариант 3: Проверить права доступа

```bash
mysql -u youpub_user -p youpub
```

Если не подключается, проверьте пользователя:
```sql
SELECT user, host FROM mysql.user WHERE user = 'youpub_user';
```

## Ошибка: 500 Internal Server Error

**Проверьте:**

1. **Логи Apache:**
```bash
sudo tail -f /var/log/apache2/youpub_error.log
```

2. **Логи PHP-FPM:**
```bash
sudo tail -f /var/log/php8.1-fpm.log
```

3. **Права доступа:**
```bash
sudo chown -R www-data:www-data /ssd/www/youpub
sudo chmod -R 755 /ssd/www/youpub
sudo chmod -R 775 /ssd/www/youpub/storage
```

4. **Проверьте конфигурацию:**
```bash
sudo apache2ctl configtest
```

## Ошибка: Class not found

**Проблема:** Классы не найдены, возможно проблема с autoloader.

**Решение:**

1. Переустановите зависимости:
```bash
cd /ssd/www/youpub
sudo composer install --no-dev --optimize-autoloader
```

2. Проверьте, что `vendor/autoload.php` существует:
```bash
ls -la /ssd/www/youpub/vendor/autoload.php
```

3. Если используете opcache, очистите кэш:
```bash
sudo systemctl reload apache2
# или
sudo php -r "opcache_reset();"
```

## Ошибка: Permission denied при загрузке файлов

**Проблема:** Недостаточно прав для записи в `storage/uploads/`

**Решение:**
```bash
sudo chown -R www-data:www-data /ssd/www/youpub/storage
sudo chmod -R 775 /ssd/www/youpub/storage
```

## Workers не работают

**Проверьте:**

1. **Cron настроен:**
```bash
sudo crontab -l
```

Должны быть строки:
```
* * * * * /ssd/www/youpub/cron/publish.sh >> /var/log/youpub_publish.log 2>&1
0 * * * * /ssd/www/youpub/cron/stats.sh >> /var/log/youpub_stats.log 2>&1
```

2. **Права на скрипты:**
```bash
sudo chmod +x /ssd/www/youpub/cron/*.sh
sudo chmod +x /ssd/www/youpub/workers/*.php
```

3. **Логи workers:**
```bash
tail -f /ssd/www/youpub/storage/logs/workers/publish_*.log
```

## SSL сертификат не работает

**Проверьте:**

1. **Сертификат установлен:**
```bash
sudo certbot certificates
```

2. **Apache конфигурация SSL:**
```bash
sudo apache2ctl -S
```

3. **Обновите сертификат:**
```bash
sudo certbot renew --dry-run
```

## Проверка работоспособности системы

### Быстрая проверка:

```bash
# 1. Проверка PHP
php -v

# 2. Проверка MySQL
mysql -u youpub_user -p youpub -e "SELECT 1"

# 3. Проверка Apache
sudo systemctl status apache2

# 4. Проверка PHP-FPM
sudo systemctl status php8.1-fpm

# 5. Проверка конфигурации Apache
sudo apache2ctl configtest

# 6. Проверка прав доступа
ls -la /ssd/www/youpub/storage
```

## Полезные команды для диагностики

```bash
# Просмотр всех логов
sudo tail -f /var/log/apache2/youpub_*.log
sudo tail -f /ssd/www/youpub/storage/logs/workers/*.log

# Проверка подключения к БД
mysql -u youpub_user -p youpub -e "SHOW TABLES;"

# Проверка PHP конфигурации
php -i | grep -i "upload_max_filesize\|post_max_size\|memory_limit"

# Проверка процессов
ps aux | grep php
ps aux | grep apache

# Проверка портов
sudo netstat -tlnp | grep -E "80|443|3306"
```

## Контакты для поддержки

Если проблема не решена, проверьте:
1. Все логи (Apache, PHP-FPM, Workers)
2. Конфигурационные файлы
3. Права доступа
4. Статус всех сервисов
