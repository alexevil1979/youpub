# Инструкция по работе с Git

## Первая публикация на GitHub/GitLab

### 1. Создайте репозиторий на GitHub/GitLab

1. Зайдите на GitHub.com или GitLab.com
2. Создайте новый репозиторий (например, `youpub`)
3. **НЕ** инициализируйте его с README или .gitignore

### 2. Подключите удаленный репозиторий

```bash
cd C:\Users\1\Documents\youpub
git remote add origin https://github.com/ВАШ_USERNAME/youpub.git
# или для GitLab:
# git remote add origin https://gitlab.com/ВАШ_USERNAME/youpub.git
```

### 3. Переименуйте ветку в main (если нужно)

```bash
git branch -M main
```

### 4. Отправьте код

```bash
git push -u origin main
```

## Работа с ветками

### Создание ветки разработки

```bash
git checkout -b dev
git push -u origin dev
```

### Переключение между ветками

```bash
git checkout main    # переключиться на main
git checkout dev     # переключиться на dev
```

## Обновление на VPS

После изменений в репозитории, на VPS выполните:

```bash
cd /ssd/www/youpub
git pull origin main
composer install --no-dev --optimize-autoloader
```

Если были изменения в БД:
```bash
mysql -u youpub_user -p youpub < database/migrations/new_migration.sql
```

## Рабочий процесс

### Для разработки

1. Создайте feature-ветку:
```bash
git checkout -b feature/new-feature
```

2. Внесите изменения и закоммитьте:
```bash
git add .
git commit -m "Описание изменений"
```

3. Отправьте ветку:
```bash
git push -u origin feature/new-feature
```

4. Создайте Pull Request на GitHub/GitLab

### Для production

1. Все изменения делайте в ветке `dev`
2. После тестирования мержите в `main`
3. На VPS всегда пуллите из `main`

## Полезные команды

```bash
# Посмотреть статус
git status

# Посмотреть историю
git log --oneline

# Отменить изменения в файле
git checkout -- filename

# Посмотреть различия
git diff

# Создать тег для версии
git tag -a v1.0.0 -m "Release version 1.0.0"
git push origin v1.0.0
```
