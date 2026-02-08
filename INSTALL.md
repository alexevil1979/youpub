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

## Настройка AI-генерации контента

Система поддерживает автогенерацию заголовков, описаний и тегов для YouTube Shorts через три движка:

### Шаблонный движок (встроенный, без ключей)
Работает из коробки. Использует regex-анализ идеи и заранее заготовленные шаблоны.

### Groq AI (LLaMA 3.3 70B)
1. Получите API-ключ на [console.groq.com](https://console.groq.com)
2. Создайте файл `local.key` в корне проекта:
```bash
echo "gsk_ВАШ_КЛЮЧ_GROQ" > local.key
```
3. Убедитесь, что `local.key` есть в `.gitignore` (уже добавлен)

### GigaChat (Сбер)
1. Зарегистрируйтесь на [developers.sber.ru](https://developers.sber.ru)
2. Создайте проект GigaChat API, получите Client ID и Client Secret
3. Создайте файл `gigachat.key` в корне проекта с ключом авторизации (Base64):
```bash
echo "ВАША_BASE64_СТРОКА" > gigachat.key
```
4. Убедитесь, что `gigachat.key` есть в `.gitignore` (уже добавлен)

### Использование AI в интерфейсе

**При создании шаблона** (Шаблоны → Создать):
- Выберите один из чекбоксов: Шаблонная / Groq AI / GigaChat
- Введите идею видео → нажмите кнопку генерации → форма заполнится автоматически

**При настройке группы** (Группы → Редактировать):
- В поле «Способ оформления контента» выберите нужный тип:
  - `0` — Шаблон (ручной)
  - `1` — Автогенерация из имени файла
  - `2` — Автогенерация из названия группы
  - `3` — Автогенерация из описания группы
  - `4` — **Groq AI** (LLaMA 3.3 70B)
  - `5` — **GigaChat AI** (Сбер)

При недоступности AI-ключа система автоматически переключается на шаблонный движок.

### Важно для деплоя
Файлы `local.key` и `gigachat.key` **не хранятся в git**. После `git pull` на сервере нужно вручную создать эти файлы с ключами:
```bash
cd /ssd/www/youpub
echo "gsk_ВАШ_КЛЮЧ" > local.key
echo "ВАША_BASE64_СТРОКА" > gigachat.key
chown www-data:www-data local.key gigachat.key
chmod 600 local.key gigachat.key
```

## Первые шаги после установки

1. **Смените пароль администратора** - обязательно!
2. **Настройте интеграции**:
   - YouTube: создайте проект в Google Cloud Console, получите OAuth credentials
   - Telegram: создайте бота через @BotFather
3. **Настройте AI-ключи** — см. раздел «Настройка AI-генерации контента» выше
4. **Настройте cron** для автоматической публикации
5. **Проверьте логи** в `storage/logs/`

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
