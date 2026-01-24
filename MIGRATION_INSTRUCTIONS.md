# 🚀 Инструкции по применению миграции Shorts

## На сервере (Linux):

```bash
# Перейти в директорию проекта
cd /ssd/www/youpub

# Скачать обновления
git pull origin main

# Выполнить миграцию
php database/migrate.php 011

# Если миграция прошла успешно, увидите:
# 🎉 Миграция 011 выполнена успешно!
```

## Проверка результата:

```bash
# Проверить новые поля в таблице
mysql -u youpub_user -pqweasd333123 youpub -e "DESCRIBE publication_templates;" | tail -15

# Должны увидеть новые поля:
# hook_type
# focus_points
# title_variants
# description_variants
# emoji_groups
# base_tags
# tag_variants
# questions
# pinned_comments
# cta_types
# enable_ab_testing
```

## Если миграция не выполняется:

### Вариант 1: Выполнить SQL вручную
```bash
# Подключиться к MySQL
mysql -u youpub_user -pqweasd333123 youpub

# Выполнить содержимое файла миграции
source database/migrations/011_add_shorts_template_fields.sql

# Проверить результат
DESCRIBE publication_templates;
exit;
```

### Вариант 2: Создать резервную копию и выполнить
```bash
# Создать бэкап
mysqldump -u youpub_user -pqweasd333123 youpub > backup_before_shorts_$(date +%Y%m%d_%H%M%S).sql

# Выполнить миграцию
mysql -u youpub_user -pqweasd333123 youpub < database/migrations/011_add_shorts_template_fields.sql

# Проверить
mysql -u youpub_user -pqweasd333123 youpub -e "DESCRIBE publication_templates;"
```

## После успешной миграции:

### 1. Перезапустить PHP-FPM
```bash
sudo systemctl restart php8.1-fpm
# или
sudo systemctl restart php-fpm
```

### 2. Очистить кэш (если есть)
```bash
# Очистить OPcache если используется
php -r "opcache_reset();"
```

### 3. Проверить новую форму
```
Открыть: https://you.1tlt.ru/content-groups/templates
Нажать: "🎯 Создать шаблон для Shorts"
Должна открыться новая форма с валидацией
```

## 🔍 Диагностика проблем:

### Проверить логи PHP:
```bash
tail -f /var/log/php8.1-fpm.log
# или
tail -f /var/log/php-fpm/error.log
```

### Проверить права на файлы:
```bash
ls -la database/migrate.php
ls -la database/migrations/011_add_shorts_template_fields.sql
```

### Проверить подключение к БД:
```bash
php -r "
$config = require 'config/env.php';
try {
    \$pdo = new PDO(
        'mysql:host='.\$config['DB_HOST'].';dbname='.\$config['DB_NAME'],
        \$config['DB_USER'],
        \$config['DB_PASS']
    );
    echo '✅ Подключение к БД успешно\n';
} catch(Exception \$e) {
    echo '❌ Ошибка подключения: '.\$e->getMessage().'\n';
}
"
```

## 🎯 Готово!

После успешного выполнения миграции новая система шаблонов для YouTube Shorts будет готова к использованию!