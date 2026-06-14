<?php

namespace Modules\SystemLog\Models;

use CodeIgniter\Model;

class SystemLogModel extends Model
{
    protected $table      = 'system_logs';
    protected $primaryKey = 'id';
    protected $returnType = 'object';
    protected $useTimestamps = false;

    protected $allowedFields = [
        'level', 'channel', 'title', 'message',
        'context', 'user_id', 'ip_address', 'url', 'method', 'seen', 'created_at',
    ];

    protected $validationRules = [
        'level'   => 'required|in_list[debug,info,warning,error,critical]',
        'channel' => 'required|max_length[50]',
        'title'   => 'required|max_length[255]',
    ];

    public function getList(array $filters = [], int $page = 1, int $perPage = 30): array
    {
        $builder = $this->builder();

        if (!empty($filters['level'])) {
            $builder->where('level', $filters['level']);
        }
        if (!empty($filters['channel'])) {
            $builder->where('channel', $filters['channel']);
        }
        if (isset($filters['seen']) && $filters['seen'] !== '') {
            $builder->where('seen', (int) $filters['seen']);
        }
        if (!empty($filters['q'])) {
            $q = $filters['q'];
            $builder->groupStart()
                ->like('title', $q)
                ->orLike('message', $q)
            ->groupEnd();
        }

        $total   = $builder->countAllResults(false);
        $offset  = ($page - 1) * $perPage;
        $records = $builder->orderBy('id', 'DESC')->get($perPage, $offset)->getResult();

        // Decode JSON context
        foreach ($records as $r) {
            if ($r->context) {
                $r->context = json_decode($r->context, true);
            }
        }

        return [
            'records'     => $records,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    public function getUnseenCount(): int
    {
        return $this->where('seen', 0)->where('level !=', 'debug')->countAllResults();
    }

    public function markAllSeen(): void
    {
        $this->where('seen', 0)->set('seen', 1)->update();
    }

    public function clearAll(): void
    {
        $this->db->table($this->table)->truncate();
    }
}
