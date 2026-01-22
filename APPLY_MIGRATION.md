# Применение миграции модуля групп контента

## Проблема

Если вы получили ошибку:
```
ERROR 1049 (42000): Unknown database 'youpub_db'
```

Это означает, что база данных с таким именем не существует.

## Решение

### Вариант 1: Использовать правильное имя базы данных

Имя базы данных в проекте: **`youpub`** (не `youpub_db`)

```bash
cd /ssd/www/youpub
mysql -u youpub_user -pqweasd333123 youpub < database/migrations/002_content_groups_module.sql
```

### Вариант 2: Создать базу данных, если её нет

Если база данных `youpub` не существует, создайте её:

```bash
sudo mysql -u root -p
```

В MySQL выполните:
```sql
CREATE DATABASE IF NOT EXISTS youpub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON youpub.* TO 'youpub_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

Затем примените миграцию:
```bash
cd /ssd/www/youpub
mysql -u youpub_user -pqweasd333123 youpub < database/migrations/002_content_groups_module.sql
```

### Вариант 3: Использовать скрипт

```bash
cd /ssd/www/youpub
chmod +x database/apply_migration.sh
./database/apply_migration.sh
```

## Проверка

После применения миграции проверьте, что таблицы созданы:

```bash
mysql -u youpub_user -pqweasd333123 youpub -e "SHOW TABLES LIKE 'content_%';"
```

Должны появиться таблицы:
- `content_groups`
- `content_group_files`
- `publication_templates`
- `group_statistics`
- `publication_logs`

Также проверьте расширение таблицы `schedules`:

```bash
mysql -u youpub_user -pqweasd333123 youpub -e "DESCRIBE schedules;" | grep content_group
```

Должно быть поле `content_group_id`.

## Примечание о безопасности

⚠️ Использование пароля в командной строке небезопасно. Для production используйте файл `.my.cnf`:

```bash
# Создайте файл ~/.my.cnf
cat > ~/.my.cnf << EOF
[client]
user=youpub_user
password=qweasd333123
EOF

chmod 600 ~/.my.cnf

# Теперь можно использовать без пароля:
mysql youpub < database/migrations/002_content_groups_module.sql
```
