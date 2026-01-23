# Исправление настроек PHP для загрузки видео

## Проблема

Текущие настройки PHP слишком малы для загрузки видео:
- `upload_max_filesize => 2M` - максимум 2MB на файл
- `post_max_size => 8M` - максимум 8MB на весь POST запрос
- `max_file_uploads => 20` - максимум 20 файлов (это нормально)

## Решение

Нужно увеличить эти значения в конфигурации PHP.

### Для PHP-FPM 8.1:

```bash
sudo nano /etc/php/8.1/fpm/php.ini
```

Найдите и измените следующие строки:

```ini
; Максимальный размер одного загружаемого файла
upload_max_filesize = 5120M

; Максимальный размер POST запроса (должен быть >= upload_max_filesize)
post_max_size = 5120M

; Максимальное количество файлов за раз
max_file_uploads = 50

; Максимальное время выполнения скрипта (для больших загрузок)
max_execution_time = 3600

; Максимальное время загрузки файла
max_input_time = 3600

; Память для скриптов
memory_limit = 512M
```

### Также проверьте настройки в CLI версии (если используется):

```bash
sudo nano /etc/php/8.1/cli/php.ini
```

Измените те же параметры.

### Перезапустите PHP-FPM:

```bash
sudo systemctl restart php8.1-fpm
```

### Проверьте настройки:

```bash
php -i | grep -i "upload_max_filesize\|post_max_size\|max_file_uploads"
```

Должно быть:
```
max_file_uploads => 50 => 50
post_max_size => 5120M => 5120M
upload_max_filesize => 5120M => 5120M
```

### Альтернативный способ (через .htaccess или .user.ini):

Если нет доступа к php.ini, можно создать файл `.user.ini` в корне проекта:

```bash
cd /ssd/www/youpub
nano .user.ini
```

Добавьте:

```ini
upload_max_filesize = 5120M
post_max_size = 5120M
max_file_uploads = 50
max_execution_time = 3600
max_input_time = 3600
memory_limit = 512M
```

**Примечание**: Изменения в `.user.ini` применяются только если включена опция `user_ini.filename` в php.ini.

## Проверка после изменений

После изменения настроек проверьте:

```bash
# Проверка через CLI
php -i | grep -i "upload_max_filesize\|post_max_size\|max_file_uploads"

# Проверка через веб-интерфейс
# Создайте файл phpinfo.php в корне проекта:
echo "<?php phpinfo(); ?>" > /ssd/www/youpub/phpinfo.php
# Откройте в браузере: https://you.1tlt.ru/phpinfo.php
# Найдите раздел "Core" и проверьте значения
# После проверки удалите файл: rm /ssd/www/youpub/phpinfo.php
```

## Важно

- `post_max_size` должен быть **больше или равен** `upload_max_filesize`
- Если загружаете несколько файлов, `post_max_size` должен быть больше суммы размеров всех файлов
- Для 6 видео по 100MB каждое нужно минимум 600MB в `post_max_size`
- Рекомендуется установить `post_max_size` и `upload_max_filesize` в 5120M (5GB) для больших видео
