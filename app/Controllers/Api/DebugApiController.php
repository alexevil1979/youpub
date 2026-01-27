<?php

namespace App\Controllers\Api;

use Core\Controller;
use Core\Database;

/**
 * Debug API для отладки: дампы БД и логи воркеров.
 *
 * ВАЖНО: сейчас эндпоинты доступны без авторизации.
 * Использовать только в защищённой среде!
 */
class DebugApiController extends Controller
{
    /**
     * Получить срез данных из выбранных таблиц.
     *
     * GET /api/debug/db-snapshot?tables=schedules,content_groups,content_group_files&limit=200
     */
    public function dbSnapshot(): void
    {
        try {
            $tablesParam = (string)$this->getParam('tables', 'schedules,content_groups,content_group_files,publications,videos');
            $limitParam  = (int)$this->getParam('limit', 200);
            $limit       = $limitParam > 0 ? $limitParam : 200;

            $tableNames = array_filter(array_map('trim', explode(',', $tablesParam)));
            if (empty($tableNames)) {
                $this->error('No tables specified', 400);
                return;
            }

            $db = Database::getInstance();
            $snapshot = [];

            foreach ($tableNames as $table) {
                // Простая защита от инъекций в имя таблицы
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                    continue;
                }

                $sql = "SELECT * FROM `{$table}` LIMIT :limit";
                $stmt = $db->prepare($sql);
                $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
                $stmt->execute();
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                $snapshot[$table] = $rows;
            }

            $this->success(['snapshot' => $snapshot], 'DB snapshot collected');
        } catch (\Throwable $e) {
            error_log('DebugApiController::dbSnapshot error: ' . $e->getMessage());
            $this->error('Failed to collect DB snapshot: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Получить хвост лога воркера.
     *
     * GET /api/debug/worker-log?type=smart_publish&date=2026-01-27&max_bytes=65535
     *
     * type: smart_publish | publish | stats
     */
    public function workerLog(): void
    {
        try {
            $type = (string)$this->getParam('type', 'smart_publish');
            $date = (string)$this->getParam('date', date('Y-m-d'));

            $map = [
                'smart_publish' => "smart_publish_{$date}.log",
                'publish'       => "publish_{$date}.log",
                'stats'         => "stats_{$date}.log",
            ];

            if (!isset($map[$type])) {
                $type = 'smart_publish';
            }

            $fileName = $map[$type];

            $logDir = rtrim($this->config['WORKER_LOG_DIR'] ?? (__DIR__ . '/../../../storage/logs/workers'), DIRECTORY_SEPARATOR);
            $path   = $logDir . DIRECTORY_SEPARATOR . $fileName;

            if (!is_file($path)) {
                $this->error('Log file not found: ' . $fileName, 404);
                return;
            }

            $size = filesize($path) ?: 0;
            $maxBytesParam = (int)$this->getParam('max_bytes', 65535);
            $maxBytes = $maxBytesParam > 0 ? $maxBytesParam : 65535;

            // Читаем только хвост файла, чтобы не тянуть мегабайты
            if ($size > $maxBytes) {
                $fh = fopen($path, 'rb');
                fseek($fh, -$maxBytes, SEEK_END);
                $content = fread($fh, $maxBytes);
                fclose($fh);
            } else {
                $content = file_get_contents($path);
            }

            $this->success([
                'file'       => $fileName,
                'size_bytes' => $size,
                'tail_bytes' => strlen((string)$content),
                'content'    => $content,
            ], 'Worker log tail');
        } catch (\Throwable $e) {
            error_log('DebugApiController::workerLog error: ' . $e->getMessage());
            $this->error('Failed to read worker log: ' . $e->getMessage(), 500);
        }
    }
}

