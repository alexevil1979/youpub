# YouPub - Система автоматической публикации видео

Production-ready SaaS система для автоматической публикации видеороликов по расписанию на YouTube и Telegram с веб-админкой.

## Технологии

- **Backend**: PHP 8.1 (OOP, MVC)
- **База данных**: MySQL
- **Frontend**: HTML + CSS + JavaScript (vanilla)
- **Сервер**: Apache (VPS Linux)
- **Очереди**: Cron + PHP Workers
- **API**: REST API

## Установка

### 1. Требования

- PHP 8.1+
- MySQL 5.7+
- Apache с mod_rewrite
- Composer

### 2. Клонирование и настройка

```bash
git clone <repository-url> youpub
cd youpub
composer install
```

### 3. База данных

```bash
mysql -u root -p
CREATE DATABASE youpub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'youpub_user'@'localhost' IDENTIFIED BY 'qweasd333123';
GRANT ALL PRIVILEGES ON youpub.* TO 'youpub_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;

mysql -u youpub_user -p youpub < database/schema.sql
```

### 4. Конфигурация

Скопируйте и настройте конфигурацию:

```bash
cp config/env.example.php config/env.php
nano config/env.php
```

Обязательно измените:
- `SECRET_KEY` - случайная строка минимум 32 символа
- `JWT_SECRET` - случайная строка минимум 32 символа
- `YOUTUBE_CLIENT_ID` и `YOUTUBE_CLIENT_SECRET` - для интеграции YouTube
- `DB_*` - параметры подключения к БД

### 5. Права доступа

```bash
chmod -R 755 storage/
chmod -R 755 workers/
chmod +x cron/*.sh
```

### 6. Настройка Apache

Создайте виртуальный хост:

```apache
<VirtualHost *:80>
    ServerName you.1tlt.ru
    DocumentRoot /path/to/youpub
    
    <Directory /path/to/youpub>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/youpub_error.log
    CustomLog ${APACHE_LOG_DIR}/youpub_access.log combined
</VirtualHost>
```

### 7. Настройка SSL (Let's Encrypt)

```bash
certbot --apache -d you.1tlt.ru
```

### 8. Настройка Cron

Добавьте в crontab:

```bash
crontab -e
```

Добавьте строки:

```
# Публикация видео (каждую минуту)
* * * * * /path/to/youpub/cron/publish.sh >> /var/log/youpub_publish.log 2>&1

# Сбор статистики (каждый час)
0 * * * * /path/to/youpub/cron/stats.sh >> /var/log/youpub_stats.log 2>&1
```

## Структура проекта

```
youpub/
├── app/                    # Приложение
│   ├── Controllers/        # Контроллеры
│   ├── Services/          # Бизнес-логика
│   ├── Repositories/      # Работа с БД
│   └── Middlewares/       # Middleware
├── core/                  # Ядро системы
│   ├── Router.php
│   ├── Controller.php
│   ├── Database.php
│   └── Auth.php
├── config/                # Конфигурация
├── database/              # SQL схемы
├── routes/                # Маршруты
├── views/                 # Представления
├── workers/               # Workers для cron
├── storage/               # Хранилище файлов
│   ├── uploads/          # Загруженные видео
│   └── logs/             # Логи
└── cron/                  # Cron скрипты
```

## API Endpoints

### Авторизация
- `POST /api/auth/login` - Вход
- `POST /api/auth/register` - Регистрация

### Видео
- `GET /api/videos` - Список видео
- `POST /api/videos/upload` - Загрузка видео
- `GET /api/videos/{id}` - Получить видео
- `DELETE /api/videos/{id}` - Удалить видео

### Расписания
- `GET /api/schedules` - Список расписаний
- `POST /api/schedules` - Создать расписание
- `GET /api/schedules/{id}` - Получить расписание
- `DELETE /api/schedules/{id}` - Удалить расписание

### Статистика
- `GET /api/stats` - Получить статистику
- `GET /api/stats/export?format=json|csv` - Экспорт статистики

## Админ-панель

Доступ: `/admin`

По умолчанию создается администратор:
- Email: `admin@you.1tlt.ru`
- Пароль: `admin123` (смените после первого входа!)

## Интеграции

### YouTube

1. Создайте проект в [Google Cloud Console](https://console.cloud.google.com/)
2. Включите YouTube Data API v3
3. Создайте OAuth 2.0 credentials
4. Укажите `YOUTUBE_CLIENT_ID` и `YOUTUBE_CLIENT_SECRET` в `config/env.php`
5. Подключите аккаунт в разделе "Интеграции"

### Telegram

1. Создайте бота через [@BotFather](https://t.me/BotFather)
2. Получите токен бота
3. Добавьте бота в канал как администратора
4. Укажите токен и ID канала в разделе "Интеграции"

## Обновление на VPS

```bash
cd /path/to/youpub
git pull origin main
composer install --no-dev --optimize-autoloader
php -r "opcache_reset();" # Очистка кэша PHP
```

Если были изменения в БД:
```bash
mysql -u youpub_user -p youpub < database/migrations/new_migration.sql
```

## Правила работы AI‑ассистента для этого проекта

- **Git и фиксация изменений**
  - AI **всегда сам коммитит и пушит** все внесённые изменения в этот репозиторий, **без дополнительных вопросов**, как только код в рабочем состоянии.
  - Сообщения коммита должны кратко описывать суть изменений.
  - Конфиги с чувствительными данными (`config/env.php` и т.п.), которые игнорируются `.gitignore`, **никогда не добавляются** в git.

- **Команды для обновления кода на VPS**
  - После каждого блока изменений AI обязан в ответе давать **готовый набор команд**, который нужно выполнить на VPS для применения правок, например:
    ```bash
    cd /ssd/www/youpub
    git pull origin main
    sudo systemctl reload php8.1-fpm
    # при изменениях в БД:
    # mysql -u youpub_user -p youpub < database/migrations/016_create_app_settings_table.sql
    ```

- **Авто‑поиск путей и окружения**
  - AI **всегда сам ищет** в проекте:
    - пути до папки проекта на VPS (`/ssd/www/youpub`, `you.1tlt.ru` и т.п.),
    - настройки домена и URL (`config/env.php`, `config/env.example.php`),
    - миграции БД (`database/migrations/*.sql`),
    - структуры папок (`storage/`, `workers/`, `cron/`),
    - роуты и админ‑панель (`routes/admin.php`, `views/admin/*`).
  - При необходимости AI использует `AI_CONTEXT.md` и другие docs (`DEPLOY.md`, `CONTENT_GROUPS_*`) для понимания контекста и не задаёт лишних вопросов, если информация уже есть в репозитории.

Эти правила обязательны для всех будущих изменений кода и документации в этом проекте.

## Безопасность

- Все пароли хешируются через `password_hash()`
- CSRF защита на формах
- Rate limiting для API
- Валидация загружаемых файлов
- Разделение ролей (user/admin)

## Лицензия

Proprietary

## Поддержка

Для вопросов и поддержки обращайтесь к администратору системы.
