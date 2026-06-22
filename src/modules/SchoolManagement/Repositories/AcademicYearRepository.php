<?php

namespace Modules\SchoolManagement\Repositories;

use Modules\SchoolManagement\Models\AcademicYearModel;

/**
 * AcademicYearRepository - Thao tác với bảng academic_years.
 *
 * Quy tắc overlap (validateOverlap):
 *   Hai năm học A(start_a, end_a) và B(start_b, end_b) được coi là TRÙNG NGÀY
 *   trong cùng branch nếu: A.start_date < B.end_date AND B.start_date < A.end_date.
 *   (Công thức này loại trừ trường hợp A.end_date = B.start_date — chạm biên OK.)
 *
 * Soft-delete: chỉ set is_active=0. Khi cần tạo lại với cùng name+branch,
 * ưu tiên revive row cũ (giữ UUID) thay vì INSERT mới — tương tự RoleRepository.
 */
class AcademicYearRepository
{
    private AcademicYearModel $model;
    private $db;

    public function __construct()
    {
        $this->model = new AcademicYearModel();
        $this->db    = \Config\Database::connect();
    }

    private function generateUuid(): string
    {
        $hex = bin2hex(random_bytes(16));
        return sprintf('%s-%s-%s-%s-%s',
            substr($hex, 0, 8), substr($hex, 8, 4),
            substr($hex, 12, 4), substr($hex, 16, 4), substr($hex, 20)
        );
    }

    /**
     * Lấy danh sách năm học active, JOIN branch để hiển thị tên.
     */
    public function list(?int $branchId = null): array
    {
        $builder = $this->db->table('academic_years ay')
            ->select('ay.uuid, ay.name, ay.start_date, ay.end_date, ay.created_at, ay.updated_at,
                      b.uuid AS branch_uuid, b.name AS branch_name', false)
            ->join('branches b', 'b.id = ay.branch_id', 'left', false)
            ->where('ay.is_active', 1)
            ->orderBy('ay.start_date', 'DESC');

        if ($branchId !== null) {
            $builder->where('ay.branch_id', $branchId);
        }

        return $builder->get()->getResultObject();
    }

    public function findByUuid(string $uuid): ?object
    {
        $row = $this->db->table('academic_years ay')
            ->select('ay.id, ay.uuid, ay.name, ay.start_date, ay.end_date, ay.branch_id, ay.created_at, ay.updated_at,
                      b.uuid AS branch_uuid, b.name AS branch_name', false)
            ->join('branches b', 'b.id = ay.branch_id', 'left', false)
            ->where('ay.uuid', $uuid)
            ->where('ay.is_active', 1)
            ->get()
            ->getFirstRow();

        return $row ?: null;
    }

    /**
     * Kiểm tra 2 năm học có trùng ngày trong cùng branch hay không.
     *
     * @param int      $branchId    ID branch (bắt buộc)
     * @param string   $startDate   YYYY-MM-DD
     * @param string   $endDate     YYYY-MM-DD
     * @param string|null $excludeUuid  UUID cần loại trừ (khi update)
     */
    public function hasOverlap(int $branchId, string $startDate, string $endDate, ?string $excludeUuid = null): bool
    {
        $builder = $this->db->table('academic_years')
            ->where('branch_id', $branchId)
            ->where('is_active', 1)
            // Overlap: existing.start < new.end AND new.start < existing.end
            ->where('start_date <', $endDate)
            ->where('end_date >', $startDate);

        if ($excludeUuid !== null) {
            $builder->where('uuid !=', $excludeUuid);
        }

        return $builder->countAllResults() > 0;
    }

    /**
     * Lấy năm học đang trùng để trả về thông tin chi tiết cho error message.
     */
    public function findOverlapping(int $branchId, string $startDate, string $endDate, ?string $excludeUuid = null): ?object
    {
        $builder = $this->db->table('academic_years')
            ->where('branch_id', $branchId)
            ->where('is_active', 1)
            ->where('start_date <', $endDate)
            ->where('end_date >', $startDate);

        if ($excludeUuid !== null) {
            $builder->where('uuid !=', $excludeUuid);
        }

        return $builder->get()->getFirstRow() ?: null;
    }

    public function create(array $data): ?object
    {
        $uuid = $this->generateUuid();
        $id   = $this->model->insert([
            'uuid'       => $uuid,
            'branch_id'  => $data['branch_id'],
            'name'       => $data['name'],
            'start_date' => $data['start_date'],
            'end_date'   => $data['end_date'],
            'is_active'  => 1,
        ]);

        return $id ? $this->findByUuid($uuid) : null;
    }

    public function update(int $id, array $data): void
    {
        $this->model->update($id, $data);
    }

    public function deactivate(int $id): void
    {
        $this->model->update($id, ['is_active' => 0]);
    }
}
