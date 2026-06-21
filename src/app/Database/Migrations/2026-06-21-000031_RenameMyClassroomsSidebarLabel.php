<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Đổi tên sidebar item "Lớp học của tôi" → "Danh sách lớp học" cho học sinh.
 *
 * Lý do: tên "Lớp học của tôi" khó hiểu và dễ nhầm với item teacher ở trên.
 * Học sinh vào thấy ngay "Danh sách lớp học" rõ nghĩa hơn — đó là danh sách
 * các lớp học sinh đang tham gia.
 *
 * Cập nhật:
 *   - module_sidebar_items: label "Lớp học của tôi" → "Danh sách lớp học"
 *   - Đồng thời đổi group từ "Học tập" → "Lớp học" để gộp cùng nhóm với 2 item teacher
 *   - module_sidebar_items: tăng sort_order để hiển thị sau 2 item teacher
 *   - awesome_bar_items: title "Lớp học của tôi" → "Danh sách lớp học" (nhất quán Ctrl+K)
 *
 * URL KHÔNG đổi — `/admin/my-classrooms` là endpoint backend trả về
 * danh sách lớp mà user hiện tại đang tham gia (xem ClassroomPageController::myClassrooms).
 */
class RenameMyClassroomsSidebarLabel extends Migration
{
    public function up(): void
    {
        $db = \Config\Database::connect();

        // 1. Cập nhật sidebar item "Lớp học của tôi" (URL /admin/my-classrooms, group "Học tập")
        $db->table('module_sidebar_items')
            ->where('url', '/admin/my-classrooms')
            ->where('group_label', 'Học tập')
            ->update([
                'label'       => 'Danh sách lớp học',
                'group_label' => 'Lớp học',
                // sort_order: giữ nguyên 30 — sau 2 item teacher (10, 20) là hợp lý
            ]);

        // 2. Cập nhật AwesomeBar item "Lớp học của tôi" (Ctrl+K)
        $db->table('awesome_bar_items')
            ->where('url', '/admin/my-classrooms')
            ->where('title', 'Lớp học của tôi')
            ->update([
                'title'    => 'Danh sách lớp học',
                'subtitle' => 'Xem danh sách lớp học bạn đang tham gia',
            ]);
    }

    public function down(): void
    {
        $db = \Config\Database::connect();

        $db->table('module_sidebar_items')
            ->where('url', '/admin/my-classrooms')
            ->where('group_label', 'Lớp học')
            ->update([
                'label'       => 'Lớp học của tôi',
                'group_label' => 'Học tập',
            ]);

        $db->table('awesome_bar_items')
            ->where('url', '/admin/my-classrooms')
            ->where('title', 'Danh sách lớp học')
            ->update([
                'title'    => 'Lớp học của tôi',
                'subtitle' => 'Xem lớp học, bài tập, nộp bài',
            ]);
    }
}