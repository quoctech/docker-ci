<?php

namespace Modules\SystemLog\Repositories;

use Modules\SystemLog\Models\SystemLogModel;

class SystemLogRepository
{
    private SystemLogModel $model;

    public function __construct()
    {
        $this->model = new SystemLogModel();
    }

    public function insert(array $data): void
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        if (isset($data['context']) && is_array($data['context'])) {
            $data['context'] = json_encode($data['context'], JSON_UNESCAPED_UNICODE);
        }
        $this->model->insert($data);
    }

    public function getList(array $filters, int $page, int $perPage): array
    {
        return $this->model->getList($filters, $page, $perPage);
    }

    public function getById(int $id): ?object
    {
        $row = $this->model->find($id);
        if ($row && $row->context) {
            $row->context = json_decode($row->context, true);
        }
        return $row;
    }

    public function markSeen(int $id): void
    {
        $this->model->update($id, ['seen' => 1]);
    }

    public function markAllSeen(): void
    {
        $this->model->markAllSeen();
    }

    public function delete(int $id): void
    {
        $this->model->delete($id);
    }

    public function clearAll(): void
    {
        $this->model->clearAll();
    }

    public function getUnseenCount(): int
    {
        return $this->model->getUnseenCount();
    }
}
