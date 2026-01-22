# Исправление ошибки загрузки видео "Failed to save file"

## 🔴 Проблема

При загрузке видео появляется ошибка:
```json
{"success":false,"message":"Failed to save file","errors":[]}
```

## ✅ Решение

### Шаг 1: Проверьте логи на сервере

```bash
sudo tail -50 /var/log/apache2/youpub_error.log | grep -i "video\|upload"
```

Ищите строки:
- `Video Upload: Upload dir =`
- `Video Upload: Failed to`
- `Video Upload: Error:`

### Шаг 2: Проверьте права доступа к директории

```bash
# Проверьте, существует ли директория storage
ls -la /ssd/www/youpub/storage/

# Если нет - создайте
sudo mkdir -p /ssd/www/youpub/storage/uploads
sudo chown -R www-data:www-data /ssd/www/youpub/storage
sudo chmod -R 755 /ssd/www/youpub/storage
```

### Шаг 3: Проверьте настройки PHP

Проверьте настройки PHP для загрузки файлов:

```bash
php -i | grep -i "upload_max_filesize\|post_max_size\|file_uploads"
```

Должно быть:
- `upload_max_filesize = 5120M` (или больше)
- `post_max_size = 5120M` (или больше)
- `file_uploads = On`

Если нужно изменить, добавьте в конфигурацию PHP-FPM или Apache:

```bash
sudo nano /etc/php/8.1/fpm/php.ini
```

Найдите и измените:
```ini
upload_max_filesize = 5120M
post_max_size = 5120M
max_execution_time = 3600
max_input_time = 3600
memory_limit = 512M
```

Перезапустите PHP-FPM:
```bash
sudo systemctl restart php8.1-fpm
```

### Шаг 4: Проверьте config/env.php

Убедитесь, что `UPLOAD_DIR` указан правильно:

```bash
grep UPLOAD_DIR /ssd/www/youpub/config/env.php
```

Должно быть:
```php
'UPLOAD_DIR' => __DIR__ . '/../storage/uploads',
```

### Шаг 5: Создайте директорию вручную

```bash
cd /ssd/www/youpub
sudo mkdir -p storage/uploads
sudo chown -R www-data:www-data storage
sudo chmod -R 755 storage
```

### Шаг 6: Проверьте права доступа

```bash
# Проверьте права
ls -la /ssd/www/youpub/storage/

# Должно быть примерно так:
# drwxr-xr-x www-data www-data storage/
# drwxr-xr-x www-data www-data storage/uploads/
```

Если права неправильные:
```bash
sudo chown -R www-data:www-data /ssd/www/youpub/storage
sudo chmod -R 755 /ssd/www/youpub/storage
```

### Шаг 7: Обновите код на сервере

```bash
cd /ssd/www/youpub
sudo git pull origin main
```

### Шаг 8: Попробуйте загрузить видео снова

1. Откройте: https://you.1tlt.ru/videos/upload
2. Выберите видео файл
3. Нажмите "Загрузить"
4. Проверьте логи:

```bash
sudo tail -20 /var/log/apache2/youpub_error.log | grep -i "video"
```

## 🔍 Частые проблемы

### Проблема 1: Директория не создается

**Ошибка в логах:** `Failed to create directory`

**Решение:**
```bash
sudo mkdir -p /ssd/www/youpub/storage/uploads
sudo chown -R www-data:www-data /ssd/www/youpub/storage
sudo chmod -R 755 /ssd/www/youpub/storage
```

### Проблема 2: Нет прав на запись

**Ошибка в логах:** `Directory not writable`

**Решение:**
```bash
sudo chown -R www-data:www-data /ssd/www/youpub/storage
sudo chmod -R 755 /ssd/www/youpub/storage
```

### Проблема 3: Файл слишком большой

**Ошибка в логах:** `File size exceeds maximum`

**Решение:** Увеличьте `upload_max_filesize` и `post_max_size` в PHP конфигурации

### Проблема 4: Временный файл не найден

**Ошибка в логах:** `Temp file exists = no`

**Решение:** Проверьте настройки `upload_tmp_dir` в PHP

## 📝 Быстрая диагностика

Выполните все команды последовательно:

```bash
# 1. Обновить код
cd /ssd/www/youpub && sudo git pull origin main

# 2. Создать директорию
sudo mkdir -p /ssd/www/youpub/storage/uploads

# 3. Установить права
sudo chown -R www-data:www-data /ssd/www/youpub/storage
sudo chmod -R 755 /ssd/www/youpub/storage

# 4. Проверить права
ls -la /ssd/www/youpub/storage/

# 5. Проверить логи после загрузки
sudo tail -50 /var/log/apache2/youpub_error.log | grep -i "video"
```

## ⚠️ Важно

- Директория `storage/uploads` должна существовать
- Права должны быть `755` для директорий
- Владелец должен быть `www-data` (или пользователь, под которым работает PHP-FPM)
- PHP настройки `upload_max_filesize` и `post_max_size` должны быть достаточными
