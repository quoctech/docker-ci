<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fix các role có UUID rỗng hoặc NULL trong bảng `roles`.
 *
 * Lý do:
 *   - Migration 2026-06-21-000028_SeedDefaultRoles chạy sinh UUID cho role Super Admin.
 *   - Tuy nhiên có thể do data cũ hoặc race condition, một số row đã có `uuid = ''`.
 *   - Hậu quả: API trả về uuid rỗng → frontend gọi DELETE /api/role-management/roles/
 *     (UUID rỗng) → 404 "Thiếu UUID vai trò".
 *
 * Cách fix:
 *   - Quét tất cả role có uuid rỗng/null.
 *   - Sinh UUID v4 mới.
 *   - Update row, đảm bảo unique (rất ít khả năng trùng).
 *
 * Migration này IDEMPOTENT — chạy nhiều lần OK.
 */
class FixEmptyRoleUuids extends Migration
{
    public function up(): void
    {
        $db = \Config\Database::connect();

        $broken = $db->table('roles')
            ->select('id, name, slug')
            ->groupStart()
                ->where('uuid', '')
                ->orWhere('uuid IS NULL', null, false)
            ->groupEnd()
            ->get()
            ->getResultArray();

        if (empty($broken)) {
            return;
        }

        foreach ($broken as $row) {
            // Sinh UUID v4 mới + retry nếu trùng (rất hiếm nhưng có thể xảy ra).
            // Tối đa 5 lần retry — nếu vẫn trùng thì log và bỏ qua row này.
            $uuid     = null;
            $attempts = 0;
            while ($attempts < 5) {
                $data = random_bytes(16);
                $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
                $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
                $candidate = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));

                $existing = $db->table('roles')->where('uuid', $candidate)->countAllResults();
                if ($existing === 0) {
                    $uuid = $candidate;
                    break;
                }
                $attempts++;
            }

            if ($uuid === null) {
                log_message('error', '[FixEmptyRoleUuids] Không thể sinh UUID duy nhất cho role id=' . $row['id']);
                continue;
            }

            $db->table('roles')
                ->where('id', $row['id'])
                ->update(['uuid' => $uuid]);
        }
    }

    public function down(): void
    {
        // Không cần rollback — fix 1 chiều.
        // Nếu cần rollback, có thể set uuid='' cho các role mặc định.
    }
}