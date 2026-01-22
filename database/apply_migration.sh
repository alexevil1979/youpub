#!/bin/bash
# Скрипт для применения миграции модуля групп контента

DB_NAME="youpub"
DB_USER="youpub_user"
DB_PASS="qweasd333123"
MIGRATION_FILE="database/migrations/002_content_groups_module.sql"

echo "Применение миграции модуля групп контента..."

# Проверяем существование базы данных
if ! mysql -u "$DB_USER" -p"$DB_PASS" -e "USE $DB_NAME" 2>/dev/null; then
    echo "База данных $DB_NAME не существует. Создаю..."
    mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -u root -p -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
    mysql -u root -p -e "FLUSH PRIVILEGES;"
    echo "База данных создана."
fi

# Применяем миграцию
echo "Применяю миграцию..."
mysql -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$MIGRATION_FILE"

if [ $? -eq 0 ]; then
    echo "✅ Миграция успешно применена!"
else
    echo "❌ Ошибка при применении миграции"
    exit 1
fi
