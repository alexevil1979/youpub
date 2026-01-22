#!/bin/bash
# Cron скрипт для сбора статистики
# Добавьте в crontab: 0 * * * * /path/to/youpub/cron/stats.sh

cd "$(dirname "$0")/.."
php workers/stats_worker.php
