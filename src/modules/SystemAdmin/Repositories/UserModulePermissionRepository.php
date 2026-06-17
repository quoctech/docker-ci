<?php

namespace Modules\SystemAdmin\Repositories;

class UserModulePermissionRepository
{
    private $db;

    public function __construct()
    {
        $this->db = \Config\Database::connect();
    }

    public function getByUser(string $userUuid): array
    {
        return $this->db->query(
            "SELECT module_slug FROM user_module_permissions WHERE user_uuid = ?",
            [$userUuid]
        )->getResultObject();
    }

    public function hasPermission(string $userUuid, string $moduleSlug): bool
    {
        return (bool) $this->db->query(
            "SELECT id FROM user_module_permissions WHERE user_uuid = ? AND module_slug = ? LIMIT 1",
            [$userUuid, $moduleSlug]
        )->getRowObject();
    }

    /** Replace all permissions for a user atomically */
    public function setPermissions(string $userUuid, array $moduleSlugs, string $grantedBy): void
    {
        $this->db->transStart();

        $this->db->query(
            "DELETE FROM user_module_permissions WHERE user_uuid = ?",
            [$userUuid]
        );

        foreach ($moduleSlugs as $slug) {
            $this->db->query(
                "INSERT INTO user_module_permissions (user_uuid, module_slug, granted_by, created_at) VALUES (?, ?, ?, NOW())",
                [$userUuid, $slug, $grantedBy]
            );
        }

        $this->db->transComplete();
    }
}
