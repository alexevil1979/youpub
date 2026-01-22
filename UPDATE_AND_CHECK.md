# Обновление кода и проверка логов

## 🔍 Проблема

В логах видны только OAuth запросы, но нет логов из метода `getYouTubeChannelInfo()`. Это означает, что либо:
1. Код еще не обновлен на сервере
2. Callback не выполняется успешно
3. Нужно проверить более свежие логи

## ✅ Решение

### Шаг 1: Обновите код на сервере

```bash
cd /ssd/www/youpub
sudo git pull origin main
```

### Шаг 2: Проверьте, что код обновлен

```bash
grep -A 5 "YouTube Channel Info Request" /ssd/www/youpub/app/Controllers/DashboardController.php
```

Должна быть строка с `error_log('YouTube Channel Info Request:');`

### Шаг 3: Переподключите YouTube

1. Откройте: https://you.1tlt.ru/integrations
2. Нажмите **Отключить** (если подключено)
3. Нажмите **Подключить YouTube** снова
4. Разрешите доступ к YouTube каналу

### Шаг 4: Проверьте логи после переподключения

```bash
sudo tail -100 /var/log/apache2/youpub_error.log | grep -i "youtube\|channel"
```

Теперь должны появиться строки:
- `YouTube Channel Info Request:`
- `HTTP Code:`
- `Channel ID:`
- `Channel Name:`

### Шаг 5: Если видите ошибки в логах

#### Ошибка 401 (Unauthorized)
- Токен истек или невалидный
- Решение: переподключите YouTube

#### Ошибка 403 (Forbidden)
- Недостаточно прав доступа
- Решение: убедитесь, что разрешили все запрашиваемые права

#### HTTP Code: 200, но `No channels found`
- У аккаунта нет канала YouTube
- Решение: создайте канал YouTube для вашего аккаунта

#### HTTP Code: 200, но `channel_name` пустой
- API вернул канал, но без названия
- Решение: проверьте, что у канала есть название в YouTube

## 🔍 Полная проверка

### 1. Проверьте код на сервере

```bash
cd /ssd/www/youpub
sudo git status
```

Если есть изменения, обновите:
```bash
sudo git pull origin main
```

### 2. Проверьте config/env.php

```bash
grep YOUTUBE /ssd/www/youpub/config/env.php
```

Должно быть:
```php
'YOUTUBE_CLIENT_ID' => '710928991217-hk0s8l4kksa4q8haccq20goecovnunrb.apps.googleusercontent.com',
'YOUTUBE_CLIENT_SECRET' => '...',
'YOUTUBE_REDIRECT_URI' => 'https://you.1tlt.ru/integrations/youtube/callback',
```

### 3. Проверьте базу данных

```bash
mysql -u youpub_user -p youpub_db
```

```sql
SELECT id, user_id, channel_id, channel_name, status, created_at 
FROM youtube_integrations 
ORDER BY created_at DESC 
LIMIT 1;
```

### 4. Проверьте последние логи

```bash
sudo tail -200 /var/log/apache2/youpub_error.log | tail -50
```

Ищите строки с `YouTube Channel Info Request` или ошибки.

## 📝 Быстрая диагностика

Выполните все команды последовательно:

```bash
# 1. Обновить код
cd /ssd/www/youpub && sudo git pull origin main

# 2. Проверить, что код обновлен
grep "YouTube Channel Info Request" app/Controllers/DashboardController.php

# 3. Проверить последние логи
sudo tail -100 /var/log/apache2/youpub_error.log | grep -i "youtube\|channel"

# 4. Проверить БД
mysql -u youpub_user -p youpub_db -e "SELECT channel_id, channel_name, status FROM youtube_integrations ORDER BY id DESC LIMIT 1;"
```

## ⚠️ Важно

После обновления кода **обязательно переподключите YouTube**, чтобы новый код выполнился и появились логи.
