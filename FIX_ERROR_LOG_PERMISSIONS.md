# Исправление прав доступа для error.log

## 🔴 Проблема

Файл `error.log` существует, но в него ничего не пишется. Это происходит потому, что файл принадлежит `root`, а PHP-FPM работает под другим пользователем.

## ✅ Решение

Выполните на сервере:

```bash
cd /ssd/www/youpub

# 1. Узнайте пользователя PHP-FPM
PHP_USER=$(ps aux | grep php-fpm | grep -v grep | head -1 | awk '{print $1}')
echo "PHP-FPM user: $PHP_USER"

# 2. Установите правильного владельца для директории логов
sudo chown -R $PHP_USER:$PHP_USER storage/logs

# 3. Установите права доступа
sudo chmod -R 755 storage/logs
sudo chmod 664 storage/logs/error.log

# 4. Убедитесь, что файл существует и доступен для записи
sudo touch storage/logs/error.log
sudo chown $PHP_USER:$PHP_USER storage/logs/error.log
sudo chmod 664 storage/logs/error.log

# 5. Проверьте права
ls -la storage/logs/error.log

# 6. Проверьте, что PHP может писать в файл
sudo -u $PHP_USER touch storage/logs/error.log
sudo -u $PHP_USER echo "Test log entry" >> storage/logs/error.log

# 7. Проверьте содержимое файла
cat storage/logs/error.log
```

## 🔍 Проверка после исправления

```bash
# 1. Откройте страницу в браузере
# https://you.1tlt.ru/content-groups/templates
# или
# https://you.1tlt.ru/content-groups/schedules

# 2. Сразу проверьте логи
tail -f storage/logs/error.log

# Вы должны увидеть записи вида:
# TemplateController::index: START - 2026-01-23 23:50:00
# TemplateController::index: userId = 1
# TemplateController::index: Loading templates for user 1
# и т.д.
```

## 📝 Альтернативное решение (если выше не помогло)

Если права установлены правильно, но логи все еще не пишутся, проверьте настройки PHP:

```bash
# Проверьте, куда PHP пишет логи по умолчанию
php -i | grep error_log

# Проверьте настройки в php.ini
php --ini

# Найдите файл php.ini
PHP_INI=$(php --ini | grep "Loaded Configuration File" | awk '{print $4}')
echo "PHP ini file: $PHP_INI"

# Проверьте настройку error_log в php.ini
grep error_log $PHP_INI

# Если error_log не установлен или указывает на другое место,
# добавьте в php.ini или в конфигурацию PHP-FPM:
# error_log = /ssd/www/youpub/storage/logs/error.log
```

## 🛠️ Для PHP-FPM из исходников

Если PHP-FPM установлен из исходников, настройки могут быть в другом месте:

```bash
# Найдите конфигурацию PHP-FPM
find /usr/local -name "php-fpm.conf" 2>/dev/null
find /etc -name "php-fpm.conf" 2>/dev/null

# Или найдите pool конфигурацию
find /usr/local -name "www.conf" 2>/dev/null
find /etc -name "www.conf" 2>/dev/null

# Добавьте в конфигурацию pool (например, www.conf):
# php_admin_value[error_log] = /ssd/www/youpub/storage/logs/error.log
# php_admin_flag[log_errors] = on
```

## ✅ Быстрая проверка

После исправления прав выполните:

```bash
# Тестовая запись в лог
sudo -u www-data php -r "error_log('Test message from PHP');"

# Проверьте файл
cat storage/logs/error.log

# Должна быть строка: Test message from PHP
```
