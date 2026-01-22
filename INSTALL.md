# Быстрая установка YouPub

## Локальная установка (для разработки)

### 1. Клонирование

```bash
git clone <repository-url> youpub
cd youpub
```

### 2. Установка зависимостей

```bash
composer install
```

### 3. Настройка БД

Создайте базу данных:

```sql
CREATE DATABASE youpub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'youpub_user'@'localhost' IDENTIFIED BY 'qweasd333123';
GRANT ALL PRIVILEGES ON youpub.* TO 'youpub_user'@'localhost';
FLUSH PRIVILEGES;
```

Импортируйте схему:

```bash
mysql -u youpub_user -p youpub < database/schema.sql
```

### 4. Конфигурация

```bash
cp config/env.example.php config/env.php
```

Отредактируйте `config/env.php`:
- Измените `SECRET_KEY` и `JWT_SECRET` (используйте `openssl rand -hex 32`)
- Проверьте параметры БД

### 5. Права доступа

```bash
chmod -R 755 storage/
mkdir -p storage/uploads storage/logs/workers
```

### 6. Запуск

Используйте встроенный PHP сервер для разработки:

```bash
php -S localhost:8000
```

Откройте в браузере: `http://localhost:8000`

**Вход как администратор:**
- Email: `admin@you.1tlt.ru`
- Пароль: `admin123`

## Production установка

См. файл `DEPLOY.md` для полной инструкции по развертыванию на VPS.

## Первые шаги после установки

1. **Смените пароль администратора** - обязательно!
2. **Настройте интеграции**:
   - YouTube: создайте проект в Google Cloud Console, получите OAuth credentials
   - Telegram: создайте бота через @BotFather
3. **Настройте cron** для автоматической публикации
4. **Проверьте логи** в `storage/logs/`

## Решение проблем

### Ошибка подключения к БД
- Проверьте параметры в `config/env.php`
- Убедитесь, что MySQL запущен
- Проверьте права пользователя БД

### Ошибка загрузки файлов
- Проверьте права на `storage/uploads/`
- Проверьте `upload_max_filesize` в php.ini
- Проверьте `UPLOAD_MAX_SIZE` в конфиге

### Workers не работают
- Проверьте, что cron настроен правильно
- Проверьте права на файлы в `cron/` и `workers/`
- Проверьте логи в `storage/logs/workers/`
