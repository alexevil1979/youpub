# Исправление неправильного Redirect URI

## 🔴 Проблема

В логах видно, что отправляется **неправильный** redirect URI:
```
Redirect URI: https://you.1tlt.ru/auth/youtube/callback
```

А должно быть:
```
https://you.1tlt.ru/integrations/youtube/callback
```

## ✅ Решение

### Шаг 1: Исправьте config/env.php на сервере

Выполните на сервере:

```bash
cd /ssd/www/youpub
sudo nano config/env.php
```

Найдите строку с `YOUTUBE_REDIRECT_URI` и исправьте:

**НЕПРАВИЛЬНО:**
```php
'YOUTUBE_REDIRECT_URI' => 'https://you.1tlt.ru/auth/youtube/callback',
```

**ПРАВИЛЬНО:**
```php
'YOUTUBE_REDIRECT_URI' => 'https://you.1tlt.ru/integrations/youtube/callback',
```

Сохраните файл:
- `Ctrl+O` (сохранить)
- `Enter` (подтвердить)
- `Ctrl+X` (выйти)

### Шаг 2: Проверьте, что исправлено

```bash
grep YOUTUBE_REDIRECT_URI /ssd/www/youpub/config/env.php
```

Должно показать:
```php
'YOUTUBE_REDIRECT_URI' => 'https://you.1tlt.ru/integrations/youtube/callback',
```

### Шаг 3: Обновите Google Cloud Console

1. Откройте: https://console.cloud.google.com/
2. APIs & Services → Credentials
3. Откройте ваш OAuth Client ID: `710928991217-hk0s8l4kksa4q8haccq20goecovnunrb`
4. В разделе **Authorized redirect URIs**:
   - Удалите (если есть): `https://you.1tlt.ru/auth/youtube/callback`
   - Добавьте: `https://you.1tlt.ru/integrations/youtube/callback`
5. Нажмите **SAVE**

### Шаг 4: Проверьте работу

1. Подождите 1-2 минуты
2. Откройте: https://you.1tlt.ru/integrations
3. Нажмите **Подключить YouTube**
4. Проверьте логи:

```bash
sudo tail -20 /var/log/apache2/youpub_error.log | grep -i "redirect"
```

Теперь должно быть:
```
Redirect URI: https://you.1tlt.ru/integrations/youtube/callback
```

## ⚠️ Важно

URI должны быть **ИДЕНТИЧНЫ** в двух местах:

1. **config/env.php:**
   ```php
   'YOUTUBE_REDIRECT_URI' => 'https://you.1tlt.ru/integrations/youtube/callback',
   ```

2. **Google Cloud Console:**
   ```
   https://you.1tlt.ru/integrations/youtube/callback
   ```

## 🔍 Быстрая проверка

После исправления проверьте:

```bash
# На сервере
grep YOUTUBE_REDIRECT_URI /ssd/www/youpub/config/env.php
```

Должно быть:
```
'YOUTUBE_REDIRECT_URI' => 'https://you.1tlt.ru/integrations/youtube/callback',
```

Если видите `/auth/youtube/callback` - значит не исправили!
