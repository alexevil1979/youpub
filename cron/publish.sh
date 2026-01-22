#!/bin/bash
# Cron скрипт для публикации видео
# Добавьте в crontab: * * * * * /path/to/youpub/cron/publish.sh

cd "$(dirname "$0")/.."
php workers/publish_worker.php
