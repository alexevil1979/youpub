#!/bin/bash
# Cron скрипт для сбора статистики
# Добавьте в crontab: 0 * * * * /path/to/youpub/cron/stats.sh

cd "$(dirname "$0")/.."
LOG_FILE="/var/log/youpub/cron.log"
ERROR_FILE="/var/log/youpub/cron_errors.log"
php workers/stats_worker.php >> "$LOG_FILE" 2>&1
exit_code=$?
if [ $exit_code -ne 0 ]; then
  echo "$(date): stats_worker.php failed with exit code $exit_code" >> "$ERROR_FILE"
fi
