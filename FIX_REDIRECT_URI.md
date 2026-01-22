# Быстрое решение ошибки redirect_uri_mismatch

## ⚡ Быстрое решение (3 шага)

### Шаг 1: Откройте Google Cloud Console

1. Перейдите: https://console.cloud.google.com/
2. Войдите в аккаунт Google (alexevil1979@gmail.com)
3. Выберите ваш проект (или создайте новый)

### Шаг 2: Добавьте Redirect URI

1. В меню слева: **APIs & Services** → **Credentials**
2. Найдите ваш **OAuth 2.0 Client ID** (или создайте новый)
3. Нажмите на название клиента (или кнопку редактирования)
4. В разделе **Authorized redirect URIs** нажмите **+ ADD URI**
5. Добавьте **ТОЧНО** такой URI:
   ```
   https://you.1tlt.ru/integrations/youtube/callback
   ```
6. Нажмите **SAVE**

### Шаг 3: Проверьте на сервере

На сервере выполните:

```bash
cd /ssd/www/youpub
sudo nano config/env.php
```

Убедитесь, что указано **ТОЧНО** так:

```php
'YOUTUBE_REDIRECT_URI' => 'https://you.1tlt.ru/integrations/youtube/callback',
```

Сохраните файл (Ctrl+O, Enter, Ctrl+X).

### Шаг 4: Попробуйте снова

1. Подождите **1-2 минуты** (изменения применяются не мгновенно)
2. Откройте: https://you.1tlt.ru/integrations
3. Нажмите **Подключить YouTube**

## ✅ Проверка

URI должны быть **ИДЕНТИЧНЫ** (символ в символ):

**В Google Cloud Console:**
```
https://you.1tlt.ru/integrations/youtube/callback
```

**В config/env.php:**
```php
'YOUTUBE_REDIRECT_URI' => 'https://you.1tlt.ru/integrations/youtube/callback',
```

## ⚠️ Частые ошибки

❌ **НЕПРАВИЛЬНО:**
- `http://you.1tlt.ru/...` (должен быть `https://`)
- `https://www.you.1tlt.ru/...` (не должно быть `www.`)
- `https://you.1tlt.ru/integrations/youtube/callback/` (лишний слеш в конце)
- Пробелы в начале или конце

✅ **ПРАВИЛЬНО:**
- `https://you.1tlt.ru/integrations/youtube/callback`

## 🔍 Если не помогло

1. Проверьте логи на сервере:
   ```bash
   sudo tail -20 /var/log/apache2/youpub_error.log | grep -i youtube
   ```

2. Убедитесь, что используете правильный проект в Google Cloud Console

3. Проверьте, что YouTube Data API v3 включен:
   - APIs & Services → Library → YouTube Data API v3 → должно быть "Enabled"

4. Попробуйте создать новый OAuth client:
   - Credentials → + CREATE CREDENTIALS → OAuth client ID
   - Application type: Web application
   - Authorized redirect URIs: `https://you.1tlt.ru/integrations/youtube/callback`
