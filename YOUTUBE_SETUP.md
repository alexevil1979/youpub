# Настройка YouTube API интеграции

## ⚠️ Важно

**Google Cloud CLI НЕ требуется!** Все настройки можно сделать через веб-интерфейс Google Cloud Console.

## Пошаговая инструкция

### 1. Создание проекта в Google Cloud Console

1. Перейдите на https://console.cloud.google.com/
2. Войдите в аккаунт Google
3. Создайте новый проект или выберите существующий
4. Запомните название проекта

### 2. Включение YouTube Data API v3

1. В меню слева выберите **APIs & Services** → **Library**
2. В поиске введите "YouTube Data API v3"
3. Нажмите на результат и нажмите **Enable** (Включить)
4. Дождитесь активации API

### 3. Создание OAuth 2.0 Credentials

1. Перейдите в **APIs & Services** → **Credentials**
2. Нажмите **+ CREATE CREDENTIALS** → **OAuth client ID**
3. Если появится запрос на настройку OAuth consent screen:
   - User Type: **External** (для тестирования) или **Internal** (для Google Workspace)
   - App name: **YouPub**
   - User support email: ваш email
   - Developer contact: ваш email
   - Нажмите **Save and Continue**
   - Scopes: нажмите **Save and Continue** (можно пропустить)
   - Test users: добавьте свой email, нажмите **Save and Continue**
   - Summary: нажмите **Back to Dashboard**

4. Вернитесь в **Credentials** → **+ CREATE CREDENTIALS** → **OAuth client ID**
5. Заполните форму:
   - **Application type**: Web application
   - **Name**: YouPub YouTube Integration
   - **Authorized redirect URIs**: 
     ```
     https://you.1tlt.ru/integrations/youtube/callback
     ```
     ⚠️ **ВАЖНО**: URI должен быть **ТОЧНО** таким же, включая:
     - Протокол: `https://` (не `http://`)
     - Домен: `you.1tlt.ru` (без `www.` если не используете)
     - Путь: `/integrations/youtube/callback` (слеш в начале обязателен)
   
6. Нажмите **Create**

### 4. Копирование Credentials

После создания вы увидите:
- **Client ID** (например: `123456789-abcdefghijklmnop.apps.googleusercontent.com`)
- **Client Secret** (например: `GOCSPX-abcdefghijklmnopqrstuvwxyz`)

⚠️ **Сохраните Client Secret** - он показывается только один раз!

### 5. Настройка на сервере

```bash
cd /ssd/www/youpub
sudo nano config/env.php
```

Добавьте/обновите:
```php
'YOUTUBE_CLIENT_ID' => 'ваш_client_id_здесь',
'YOUTUBE_CLIENT_SECRET' => 'ваш_client_secret_здесь',
'YOUTUBE_REDIRECT_URI' => 'https://you.1tlt.ru/integrations/youtube/callback',
```

**Проверьте:**
- ✅ URI точно совпадает с тем, что в Google Cloud Console
- ✅ Используется `https://` (не `http://`)
- ✅ Нет лишних пробелов или символов
- ✅ Путь начинается с `/`

### 6. Проверка настроек

После сохранения проверьте, что настройки правильные:

```bash
# Проверить, что файл сохранен
grep YOUTUBE config/env.php
```

Должно быть:
```
'YOUTUBE_CLIENT_ID' => 'ваш_client_id',
'YOUTUBE_CLIENT_SECRET' => 'ваш_client_secret',
'YOUTUBE_REDIRECT_URI' => 'https://you.1tlt.ru/integrations/youtube/callback',
```

### 7. Подключение YouTube

1. Откройте `https://you.1tlt.ru/integrations`
2. Нажмите **Подключить YouTube**
3. Выберите аккаунт Google
4. Разрешите доступ к YouTube каналу
5. После успешной авторизации вы вернетесь на сайт

## Решение проблем

### Ошибка: redirect_uri_mismatch

**Причина**: Redirect URI в запросе не совпадает с зарегистрированным в Google Cloud Console.

**Решение**:
1. Проверьте URI в Google Cloud Console:
   - APIs & Services → Credentials → ваш OAuth client
   - В разделе "Authorized redirect URIs" должен быть:
     ```
     https://you.1tlt.ru/integrations/youtube/callback
     ```

2. Проверьте URI в `config/env.php`:
   ```php
   'YOUTUBE_REDIRECT_URI' => 'https://you.1tlt.ru/integrations/youtube/callback',
   ```

3. Убедитесь, что:
   - ✅ Оба URI **ТОЧНО** совпадают (символ в символ)
   - ✅ Используется `https://` (не `http://`)
   - ✅ Нет лишних пробелов
   - ✅ Путь начинается с `/`

4. Если изменили URI в Google Cloud Console, подождите 1-2 минуты для применения изменений

### Ошибка: access_denied

**Причина**: Пользователь отменил авторизацию или приложение не прошло верификацию.

**Решение**: 
- Для тестирования добавьте свой email в "Test users" в OAuth consent screen
- Для production нужно пройти верификацию приложения в Google

### Ошибка: invalid_client

**Причина**: Неправильный Client ID или Client Secret.

**Решение**:
- Проверьте, что скопировали правильные значения
- Убедитесь, что нет лишних пробелов
- Проверьте, что используете правильный проект в Google Cloud Console

## Проверка работоспособности

После настройки проверьте логи:

```bash
sudo tail -f /var/log/apache2/youpub_error.log
```

При успешном подключении в БД должна появиться запись в таблице `youtube_integrations` со статусом `connected`.
