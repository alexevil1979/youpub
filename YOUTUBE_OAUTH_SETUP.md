# Правильная настройка YouTube OAuth

## ⚠️ ВАЖНО: Service Accounts ≠ OAuth Client IDs

**Service Accounts** - для сервер-к-сервер аутентификации (не нужно для YouTube OAuth)
**OAuth 2.0 Client IDs** - для пользовательской авторизации (это то, что нужно!)

## ✅ Правильный путь

### Шаг 1: Перейдите в правильный раздел

1. Откройте: https://console.cloud.google.com/
2. Выберите проект: **My Project 33122**
3. В меню слева: **APIs & Services** → **Credentials**
4. **НЕ** в раздел "Service accounts"!
5. Найдите раздел **"OAuth 2.0 Client IDs"** (выше на странице)

### Шаг 2: Создайте OAuth Client ID (если еще нет)

1. В разделе **OAuth 2.0 Client IDs** нажмите **+ CREATE CREDENTIALS**
2. Выберите **OAuth client ID**
3. Если появится запрос на настройку OAuth consent screen:
   - **User Type**: External (для тестирования)
   - **App name**: YouPub
   - **User support email**: alexevil1979@gmail.com
   - **Developer contact**: alexevil1979@gmail.com
   - Нажмите **Save and Continue** на всех шагах
   - В разделе **Test users** добавьте: alexevil1979@gmail.com
   - Нажмите **Save and Continue**

4. Вернитесь в **Credentials** → **+ CREATE CREDENTIALS** → **OAuth client ID**

5. Заполните:
   - **Application type**: Web application
   - **Name**: YouPub YouTube Integration
   - **Authorized redirect URIs**: 
     ```
     https://you.1tlt.ru/integrations/youtube/callback
     ```

6. Нажмите **Create**

### Шаг 3: Скопируйте Client ID и Secret

После создания вы увидите:
- **Your Client ID**: `123456789-abcdefg.apps.googleusercontent.com`
- **Your Client Secret**: `GOCSPX-abcdefghijklmnop`

⚠️ **Сохраните Client Secret** - он показывается только один раз!

### Шаг 4: Настройте на сервере

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

Сохраните (Ctrl+O, Enter, Ctrl+X).

### Шаг 5: Включите YouTube Data API v3

1. В меню: **APIs & Services** → **Library**
2. Найдите "YouTube Data API v3"
3. Нажмите **Enable**

### Шаг 6: Проверьте

1. Откройте: https://you.1tlt.ru/integrations
2. Нажмите **Подключить YouTube**
3. Должна открыться страница авторизации Google

## 📍 Где искать OAuth Client IDs

**Правильный путь:**
```
Google Cloud Console
  → APIs & Services
    → Credentials
      → OAuth 2.0 Client IDs  ← ВОТ ТУТ!
```

**НЕ ищите в:**
- ❌ Service accounts
- ❌ API keys
- ❌ Service account keys

## 🔍 Визуальная подсказка

На странице Credentials вы увидите несколько разделов:

1. **API Keys** (не нужно)
2. **OAuth 2.0 Client IDs** ← **ВОТ ЭТО НУЖНО!**
3. **Service Accounts** ← вы сейчас здесь (не то)
4. **Service Account Keys** (не нужно)

## ⚡ Быстрая ссылка

Прямая ссылка на OAuth Clients (замените PROJECT_ID):
```
https://console.cloud.google.com/apis/credentials?project=PROJECT_ID
```

Или через меню:
```
APIs & Services → Credentials → OAuth 2.0 Client IDs
```
