# Исправление настроек PHP для загрузки видео (PHP из исходников)

## Проблема

Текущие настройки PHP слишком малы для загрузки видео:
- `upload_max_filesize => 2M` - максимум 2MB на файл
- `post_max_size => 8M` - максимум 8MB на весь POST запрос
- `max_file_uploads => 20` - максимум 20 файлов

## Нахождение конфигурационных файлов

Когда PHP установлен из исходников, конфигурационные файлы могут находиться в разных местах.

### 1. Найдите расположение php.ini:

```bash
# Проверьте, где находится php.ini
php --ini

# Или
php -i | grep "Loaded Configuration File"

# Или
php -r "echo php_ini_loaded_file();"
```

### 2. Найдите расположение php-fpm.conf:

```bash
# Проверьте, где находится конфигурация PHP-FPM
ps aux | grep php-fpm | head -1

# Или проверьте стандартные места:
ls -la /usr/local/etc/php-fpm.conf
ls -la /usr/local/etc/php-fpm.d/
ls -la /etc/php-fpm.conf
ls -la /etc/php-fpm.d/
ls -la /usr/local/php/etc/php-fpm.conf
```

### 3. Проверьте, где PHP-FPM ищет php.ini:

```bash
# В конфигурации PHP-FPM обычно есть строка:
# php_admin_value[php_ini] = /path/to/php.ini
# или
# php_admin_flag[php_ini] = /path/to/php.ini

# Найдите эту строку в php-fpm.conf
grep -r "php_ini" /usr/local/etc/php-fpm.conf
grep -r "php_ini" /usr/local/etc/php-fpm.d/
```

## Решение

### Вариант 1: Редактирование php.ini напрямую

```bash
# Найдите php.ini (см. выше)
PHP_INI=$(php -r "echo php_ini_loaded_file();")
echo "PHP ini file: $PHP_INI"

# Создайте резервную копию
sudo cp "$PHP_INI" "$PHP_INI.backup"

# Отредактируйте файл
sudo nano "$PHP_INI"
```

Найдите и измените следующие строки:

```ini
; Максимальный размер одного загружаемого файла
upload_max_filesize = 5120M

; Максимальный размер POST запроса (должен быть >= upload_max_filesize)
post_max_size = 5120M

; Максимальное количество файлов за раз
max_file_uploads = 50

; Максимальное время выполнения скрипта
max_execution_time = 3600

; Максимальное время загрузки файла
max_input_time = 3600

; Память для скриптов
memory_limit = 512M
```

### Вариант 2: Через php-fpm.conf (если php.ini не используется)

Если PHP-FPM использует свою конфигурацию, добавьте в `php-fpm.conf` или в файл пула (обычно `www.conf`):

```bash
# Найдите файл конфигурации пула
ls -la /usr/local/etc/php-fpm.d/
# Обычно это www.conf или pool.d/www.conf

sudo nano /usr/local/etc/php-fpm.d/www.conf
```

Добавьте или измените в секции `[www]`:

```ini
[www]
php_admin_value[upload_max_filesize] = 5120M
php_admin_value[post_max_size] = 5120M
php_admin_value[max_file_uploads] = 50
php_admin_value[max_execution_time] = 3600
php_admin_value[max_input_time] = 3600
php_admin_value[memory_limit] = 512M
```

### Вариант 3: Автоматическое исправление (если известен путь к php.ini)

```bash
# Установите путь к php.ini
PHP_INI=$(php -r "echo php_ini_loaded_file();")

# Создайте резервную копию
sudo cp "$PHP_INI" "$PHP_INI.backup"

# Измените настройки
sudo sed -i 's/^upload_max_filesize = .*/upload_max_filesize = 5120M/' "$PHP_INI"
sudo sed -i 's/^post_max_size = .*/post_max_size = 5120M/' "$PHP_INI"
sudo sed -i 's/^max_file_uploads = .*/max_file_uploads = 50/' "$PHP_INI"
sudo sed -i 's/^max_execution_time = .*/max_execution_time = 3600/' "$PHP_INI"
sudo sed -i 's/^max_input_time = .*/max_input_time = 3600/' "$PHP_INI"
sudo sed -i 's/^memory_limit = .*/memory_limit = 512M/' "$PHP_INI"

# Если параметры отсутствуют, добавьте их в конец файла
if ! grep -q "^upload_max_filesize" "$PHP_INI"; then
    echo "" >> "$PHP_INI"
    echo "; Upload settings" >> "$PHP_INI"
    echo "upload_max_filesize = 5120M" >> "$PHP_INI"
    echo "post_max_size = 5120M" >> "$PHP_INI"
    echo "max_file_uploads = 50" >> "$PHP_INI"
    echo "max_execution_time = 3600" >> "$PHP_INI"
    echo "max_input_time = 3600" >> "$PHP_INI"
    echo "memory_limit = 512M" >> "$PHP_INI"
fi
```

## Перезапуск PHP-FPM

После изменения настроек перезапустите PHP-FPM:

```bash
# Найдите процесс PHP-FPM
ps aux | grep php-fpm

# Перезапустите (способ зависит от того, как установлен PHP-FPM)
sudo systemctl restart php-fpm
# или
sudo service php-fpm restart
# или
sudo killall -USR2 php-fpm
# или если используется systemd
sudo systemctl restart php8.1-fpm
```

Если PHP-FPM запущен как сервис systemd, найдите его имя:

```bash
systemctl list-units | grep php
systemctl list-units | grep fpm
```

## Проверка настроек

После перезапуска проверьте:

```bash
# Через CLI
php -i | grep -i "upload_max_filesize\|post_max_size\|max_file_uploads"

# Через веб-интерфейс (создайте временный файл)
echo "<?php phpinfo(); ?>" > /ssd/www/youpub/phpinfo.php
# Откройте в браузере: https://you.1tlt.ru/phpinfo.php
# После проверки удалите: rm /ssd/www/youpub/phpinfo.php
```

## Если настройки не применяются

1. Убедитесь, что вы редактируете правильный файл (проверьте через `php --ini`)
2. Убедитесь, что PHP-FPM перезапущен
3. Проверьте, нет ли переопределений в `.htaccess` или `.user.ini`
4. Проверьте логи PHP-FPM:
   ```bash
   tail -f /var/log/php-fpm.log
   # или
   tail -f /usr/local/var/log/php-fpm.log
   ```

## Важно

- `post_max_size` должен быть **больше или равен** `upload_max_filesize`
- Для 6 видео по 100MB каждое нужно минимум 600MB в `post_max_size`
- Рекомендуется установить оба значения в 5120M (5GB) для больших видео
