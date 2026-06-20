<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Xóa sidebar item trùng lặp cho System Log.
 *
 * Sau refactor drop `users.role`, logic sidebar đã đơn giản hóa
 * và bỏ check role cứng. Tuy nhiên phát hiện có 2 nơi hiển thị "System Log":
 *   - Hardcoded trong sidebar.php (group "Hệ thống") → URL /admin/system-logs
 *   - Data-driven trong module_sidebar_items (id=5) → URL /admin/system-logs
 *
 * → Super_admin thấy 2 lần → fix duplicate.
 *
 * Sau migration này: chỉ giữ hardcoded "System Log" trong group "Hệ thống".
 */
class RemoveDuplicateSystemLogSidebar extends Migration
{
    public function up(): void
    {
        $db = \Config\Database::connect();
        $db->table('module_sidebar_items')
            ->where('module_slug', 'system-log')
            ->where('label', 'System Log')
            ->delete();
    }

    public function down(): void
    {
        $db = \Config\Database::connect();

        $exists = $db->table('module_sidebar_items')
            ->where('module_slug', 'system-log')
            ->countAllResults();
        if ($exists > 0) {
            return;
        }

        $db->table('module_sidebar_items')->insert([
            'module_slug'   => 'system-log',
            'group_label'   => 'Tính năng',
            'label'         => 'System Log',
            'url'           => '/admin/system-logs',
            'icon'          => '📋',
            'allowed_roles' => json_encode(['workspace_admin', 'super_admin']),
            'match_exact'   => 0,
            'sort_order'    => 50,
        ]);
    }
}