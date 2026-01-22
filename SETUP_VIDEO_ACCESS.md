# Настройка доступа к видео файлам

## 🔴 Проблема

Страница просмотра видео пустая или видео не загружается.

## ✅ Решение: Настроить доступ к файлам через веб-сервер

### Шаг 1: Создайте симлинк для storage

Выполните на сервере:

```bash
cd /ssd/www/youpub
sudo ln -s /ssd/www/youpub/storage /ssd/www/youpub/public/storage
```

Или настройте Apache для доступа к storage напрямую.

### Шаг 2: Настройте Apache для доступа к storage

Добавьте в конфигурацию виртуального хоста Apache:

```bash
sudo nano /etc/apache2/sites-available/you.1tlt.ru.conf
```

Добавьте алиас для storage:

```apache
<VirtualHost *:443>
    ServerName you.1tlt.ru
    DocumentRoot /ssd/www/youpub/public
    
    # Алиас для доступа к загруженным файлам
    Alias /storage /ssd/www/youpub/storage
    
    <Directory /ssd/www/youpub/storage>
        Options -Indexes
        AllowOverride None
        Require all granted
    </Directory>
    
    # Остальная конфигурация...
</VirtualHost>
```

Перезапустите Apache:

```bash
sudo systemctl restart apache2
```

### Шаг 3: Проверьте права доступа

```bash
# Убедитесь, что Apache может читать файлы
sudo chown -R www-data:www-data /ssd/www/youpub/storage
sudo chmod -R 755 /ssd/www/youpub/storage
```

### Шаг 4: Обновите код на сервере

```bash
cd /ssd/www/youpub
sudo git pull origin main
```

### Шаг 5: Проверьте доступ к файлу

Попробуйте открыть файл напрямую в браузере:

```
https://you.1tlt.ru/storage/uploads/1/video_XXXXX.mp4
```

Если файл открывается - доступ настроен правильно.

## 🔍 Альтернативное решение: Через PHP

Если не хотите настраивать Apache, можно создать PHP скрипт для отдачи файлов:

Создайте файл `public/video.php`:

```php
<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    die('Access denied');
}

$videoId = $_GET['id'] ?? 0;
$userId = $_SESSION['user_id'];

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../app/Services/VideoService.php';

$videoService = new \App\Services\VideoService();
$video = $videoService->getVideo($videoId, $userId);

if (!$video || !file_exists($video['file_path'])) {
    http_response_code(404);
    die('Video not found');
}

header('Content-Type: ' . $video['mime_type']);
header('Content-Length: ' . filesize($video['file_path']));
readfile($video['file_path']);
```

И измените путь в `views/videos/show.php`:

```php
<source src="/video.php?id=<?= $video['id'] ?>" type="<?= htmlspecialchars($video['mime_type']) ?>">
```

## 📝 Проверка

После настройки:

1. Откройте: https://you.1tlt.ru/videos/1
2. Видео должно загружаться и воспроизводиться
3. Проверьте логи, если есть ошибки:

```bash
sudo tail -50 /var/log/apache2/youpub_error.log
```

## ⚠️ Важно

- Файлы должны быть доступны для чтения веб-серверу
- Путь к файлам должен быть правильным
- MIME-тип должен быть указан корректно
