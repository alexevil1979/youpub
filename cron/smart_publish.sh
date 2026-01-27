#!/bin/bash
# Cron скрипт для smart-публикации видео из групп контента
# Добавьте в crontab (каждую минуту):
# * * * * * /path/to/youpub/cron/smart_publish.sh

cd "$(dirname "$0")/.."
LOG_FILE="/var/log/youpub/cron.log"
ERROR_FILE="/var/log/youpub/cron_errors.log"

php workers/smart_publish_worker.php >> "$LOG_FILE" 2>&1
exit_code=$?

if [ $exit_code -ne 0 ]; then
  echo "$(date): smart_publish_worker.php failed with exit code $exit_code" >> "$ERROR_FILE"
fi

