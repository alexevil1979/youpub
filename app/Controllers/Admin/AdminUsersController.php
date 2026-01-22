<?php

namespace App\Controllers\Admin;

use Core\Controller;
use App\Repositories\UserRepository;

/**
 * Админ контроллер пользователей
 */
class AdminUsersController extends Controller
{
    private UserRepository $userRepo;

    public function __construct()
    {
        parent::__construct();
        $this->requireAdmin();
        $this->userRepo = new UserRepository();
    }

    /**
     * Список пользователей
     */
    public function index(): void
    {
        $users = $this->userRepo->findAll([], ['created_at' => 'DESC']);
        include __DIR__ . '/../../../views/admin/users/index.php';
    }

    /**
     * Показать пользователя
     */
    public function show(int $id): void
    {
        $user = $this->userRepo->findById($id);
        if (!$user) {
            http_response_code(404);
            echo 'User not found';
            return;
        }
        include __DIR__ . '/../../../views/admin/users/show.php';
    }

    /**
     * Обновить пользователя
     */
    public function update(int $id): void
    {
        $data = $this->getRequestData();
        
        $allowedFields = ['name', 'role', 'status', 'upload_limit', 'publish_limit'];
        $updateData = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (!empty($updateData)) {
            $this->userRepo->update($id, $updateData);
            $this->success([], 'User updated successfully');
        } else {
            $this->error('No data to update', 400);
        }
    }

    /**
     * Удалить пользователя
     */
    public function delete(int $id): void
    {
        if ($id == $_SESSION['user_id']) {
            $this->error('Cannot delete yourself', 400);
            return;
        }

        $this->userRepo->delete($id);
        $this->success([], 'User deleted successfully');
    }
}
