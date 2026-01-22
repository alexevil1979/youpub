# Руководство по миграции для новых платформ

## Обновление существующей установки

Если у вас уже установлена система, выполните миграцию для добавления поддержки новых платформ.

### 1. Обновите код

```bash
cd /ssd/www/youpub
sudo git pull origin main
```

### 2. Примените миграцию БД

```bash
mysql -u youpub_user -p youpub < database/migrations/001_add_new_platforms.sql
```

Или вручную через MySQL:

```sql
-- Создание таблиц для новых интеграций
SOURCE /ssd/www/youpub/database/migrations/001_add_new_platforms.sql;
```

### 3. Обновите конфигурацию

Добавьте в `config/env.php` параметры для новых платформ:

```php
// TikTok API
'TIKTOK_CLIENT_KEY' => '',
'TIKTOK_CLIENT_SECRET' => '',
'TIKTOK_REDIRECT_URI' => 'https://you.1tlt.ru/integrations/tiktok/callback',

// Instagram API
'INSTAGRAM_APP_ID' => '',
'INSTAGRAM_APP_SECRET' => '',
'INSTAGRAM_REDIRECT_URI' => 'https://you.1tlt.ru/integrations/instagram/callback',

// Pinterest API
'PINTEREST_APP_ID' => '',
'PINTEREST_APP_SECRET' => '',
'PINTEREST_REDIRECT_URI' => 'https://you.1tlt.ru/integrations/pinterest/callback',
```

### 4. Перезапустите сервисы

```bash
sudo systemctl reload apache2
```

## Новые платформы

### TikTok
- **API**: TikTok for Developers
- **Документация**: https://developers.tiktok.com/
- **Требуется**: Client Key и Client Secret

### Instagram Reels
- **API**: Instagram Graph API
- **Документация**: https://developers.facebook.com/docs/instagram-api
- **Требуется**: App ID и App Secret (через Facebook Developers)

### Pinterest
- **API**: Pinterest API v5
- **Документация**: https://developers.pinterest.com/docs/
- **Требуется**: App ID и App Secret

## Проверка

После миграции проверьте:
1. Новые платформы отображаются в разделе "Интеграции"
2. Можно выбрать новые платформы при создании расписания
3. Workers поддерживают новые платформы

## Откат миграции (если нужно)

```sql
-- Удаление таблиц
DROP TABLE IF EXISTS `pinterest_integrations`;
DROP TABLE IF EXISTS `instagram_integrations`;
DROP TABLE IF EXISTS `tiktok_integrations`;

-- Откат enum (только если нет данных)
ALTER TABLE `schedules` MODIFY COLUMN `platform` enum('youtube','telegram','both') NOT NULL;
ALTER TABLE `publications` MODIFY COLUMN `platform` enum('youtube','telegram') NOT NULL;
ALTER TABLE `statistics` MODIFY COLUMN `platform` enum('youtube','telegram') NOT NULL;
```
