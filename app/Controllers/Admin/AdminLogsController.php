<?php

namespace App\Controllers\Admin;

use Core\Controller;
use Core\Database;

/**
 * Админ контроллер логов
 */
class AdminLogsController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
    }

    /**
     * Список логов
     */
    public function index(): void
    {
        $db = Database::getInstance();
        
        $type = $this->getParam('type');
        $module = $this->getParam('module');
        $limit = (int)($this->getParam('limit') ?? 100);
        
        $sql = "SELECT * FROM logs WHERE 1=1";
        $params = [];
        
        if ($type) {
            $sql .= " AND type = ?";
            $params[] = $type;
        }
        
        if ($module) {
            $sql .= " AND module = ?";
            $params[] = $module;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT ?";
        $params[] = $limit;
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll();

        $type = $this->getParam('type');
        $module = $this->getParam('module');
        
        include __DIR__ . '/../../../views/admin/logs/index.php';
    }

    /**
     * Показать лог
     */
    public function show(int $id): void
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM logs WHERE id = ?");
        $stmt->execute([$id]);
        $log = $stmt->fetch();
        
        if (!$log) {
            http_response_code(404);
            echo 'Log not found';
            return;
        }
        
        include __DIR__ . '/../../../views/admin/logs/show.php';
    }
}
