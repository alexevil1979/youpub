# Отладка ошибки redirect_uri_mismatch

## 🔍 Проверка 1: Какой URI отправляется в запросе

Выполните на сервере для проверки логов:

```bash
sudo tail -50 /var/log/apache2/youpub_error.log | grep -i "youtube\|redirect"
```

Или проверьте все логи:

```bash
sudo tail -100 /var/log/apache2/youpub_error.log
```

В логах должна быть строка:
```
YouTube OAuth: Redirect URI = https://you.1tlt.ru/integrations/youtube/callback
```

## 🔍 Проверка 2: Что указано в config/env.php

```bash
cd /ssd/www/youpub
grep YOUTUBE_REDIRECT_URI config/env.php
```

Должно быть:
```php
'YOUTUBE_REDIRECT_URI' => 'https://you.1tlt.ru/integrations/youtube/callback',
```

## 🔍 Проверка 3: Что указано в Google Cloud Console

1. Откройте: https://console.cloud.google.com/
2. APIs & Services → Credentials
3. Откройте ваш OAuth Client ID: `328005740534-vvj0refou59tdog0fh86n6mr60os2sh5`
4. В разделе **Authorized redirect URIs** проверьте список

**Должно быть ТОЧНО:**
```
https://you.1tlt.ru/integrations/youtube/callback
```

## ⚠️ Частые проблемы

### Проблема 1: URI не добавлен в Google Cloud Console

**Решение:**
1. В Google Cloud Console откройте OAuth Client ID
2. В разделе **Authorized redirect URIs** нажмите **+ ADD URI**
3. Добавьте: `https://you.1tlt.ru/integrations/youtube/callback`
4. Нажмите **SAVE**
5. Подождите 1-2 минуты

### Проблема 2: Неправильный URI в config/env.php

**Решение:**
```bash
cd /ssd/www/youpub
sudo nano config/env.php
```

Проверьте, что указано **ТОЧНО** так:
```php
'YOUTUBE_REDIRECT_URI' => 'https://you.1tlt.ru/integrations/youtube/callback',
```

**Проверьте:**
- ✅ Используется `https://` (не `http://`)
- ✅ Домен: `you.1tlt.ru` (не `www.you.1tlt.ru`)
- ✅ Путь: `/integrations/youtube/callback` (слеш в начале)
- ✅ Нет слеша в конце
- ✅ Нет пробелов

### Проблема 3: Используется другой проект

**Решение:**
Убедитесь, что в Google Cloud Console вы используете проект, где создан OAuth Client ID `328005740534-vvj0refou59tdog0fh86n6mr60os2sh5`.

### Проблема 4: Изменения не применились

**Решение:**
После изменения URI в Google Cloud Console подождите 1-2 минуты. Google может кэшировать настройки.

## 🧪 Тест: Проверка через URL

Попробуйте открыть в браузере (замените YOUR_CLIENT_ID):

```
https://accounts.google.com/o/oauth2/v2/auth?client_id=328005740534-vvj0refou59tdog0fh86n6mr60os2sh5.apps.googleusercontent.com&redirect_uri=https://you.1tlt.ru/integrations/youtube/callback&response_type=code&scope=https://www.googleapis.com/auth/youtube.upload+https://www.googleapis.com/auth/youtube.readonly+https://www.googleapis.com/auth/userinfo.profile&access_type=offline&prompt=consent
```

Если увидите страницу авторизации Google - значит URI правильный.
Если увидите ошибку redirect_uri_mismatch - значит URI не совпадает.

## ✅ Пошаговая проверка

1. **Проверьте config/env.php:**
   ```bash
   grep YOUTUBE_REDIRECT_URI /ssd/www/youpub/config/env.php
   ```

2. **Проверьте Google Cloud Console:**
   - APIs & Services → Credentials
   - OAuth Client ID → Authorized redirect URIs
   - Должен быть: `https://you.1tlt.ru/integrations/youtube/callback`

3. **Сравните URI:**
   - URI в config/env.php
   - URI в Google Cloud Console
   - Они должны быть **ИДЕНТИЧНЫ** (символ в символ)

4. **Если не совпадают:**
   - Обновите в Google Cloud Console
   - Или обновите в config/env.php
   - Убедитесь, что оба одинаковые

5. **Подождите 1-2 минуты** после изменений

6. **Попробуйте снова:**
   - Откройте: https://you.1tlt.ru/integrations
   - Нажмите "Подключить YouTube"

## 🔧 Быстрое решение

Если ничего не помогает, создайте новый OAuth Client ID:

1. Google Cloud Console → Credentials
2. + CREATE CREDENTIALS → OAuth client ID
3. Application type: Web application
4. Name: YouPub YouTube Integration 2
5. Authorized redirect URIs: `https://you.1tlt.ru/integrations/youtube/callback`
6. Create
7. Скопируйте новый Client ID и Secret
8. Обновите в config/env.php
