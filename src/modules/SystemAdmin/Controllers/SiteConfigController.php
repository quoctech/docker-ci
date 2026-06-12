<?php

namespace Modules\SystemAdmin\Controllers;

use App\Controllers\ApiController;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\SystemAdmin\Models\SiteConfigModel;

/**
 * SiteConfigController - CRUD cấu hình website động.
 *
 * Quản lý key-value config theo nhóm, cache Redis.
 */
class SiteConfigController extends ApiController
{
    private SiteConfigModel $configModel;

    public function __construct()
    {
        $this->configModel = new SiteConfigModel();
    }

    /**
     * GET /api/admin/configs
     * Lấy toàn bộ cấu hình, nhóm theo group.
     */
    public function index(): ResponseInterface
    {
        $grouped = $this->configModel->getAllGrouped();

        return $this->success($grouped);
    }

    /**
     * GET /api/admin/configs/(:any)
     * Lấy giá trị 1 cấu hình theo key.
     */
    public function show(string $key): ResponseInterface
    {
        $value = $this->configModel->getValue($key);

        if ($value === null) {
            return $this->error('Cấu hình không tồn tại.', 404);
        }

        return $this->success(['key' => $key, 'value' => $value]);
    }

    /**
     * POST /api/admin/configs
     * Tạo cấu hình mới.
     */
    public function create(): ResponseInterface
    {
        $rules = [
            'key'         => 'required|alpha_dash|max_length[100]|is_unique[site_configs.key]',
            'value'       => 'permit_empty',
            'group'       => 'permit_empty|alpha_dash|max_length[50]',
            'type'        => 'permit_empty|in_list[string,integer,boolean,json]',
            'description' => 'permit_empty|max_length[255]',
        ];

        if (! $this->validate($rules)) {
            return $this->error('Dữ liệu không hợp lệ.', 422, $this->validator->getErrors());
        }

        $this->configModel->setValue(
            $this->request->getVar('key'),
            $this->request->getVar('value') ?? '',
            $this->request->getVar('group') ?? 'general',
            $this->request->getVar('type') ?? 'string',
            $this->request->getVar('description')
        );

        return $this->success(null, 'Đã tạo cấu hình.', 201);
    }

    /**
     * PUT /api/admin/configs/(:any)
     * Cập nhật giá trị cấu hình.
     */
    public function update(string $key): ResponseInterface
    {
        $existing = $this->configModel->where('key', $key)->first();

        if (! $existing) {
            return $this->error('Cấu hình không tồn tại.', 404);
        }

        // CI4: đọc body từ PUT/PATCH request
        // getVar() không luôn hoạt động đúng với PUT + urlencoded
        // Dùng getRawInput() để parse body thủ công
        $input = $this->request->getRawInput();
        $value = $input['value'] ?? '';

        $this->configModel->setValue(
            $key,
            $value,
            $existing->group,
            $existing->type,
            $existing->description
        );

        return $this->success(['key' => $key, 'value' => $value], 'Đã cập nhật cấu hình.');
    }

    /**
     * DELETE /api/admin/configs/(:any)
     * Xóa cấu hình.
     */
    public function delete(string $key): ResponseInterface
    {
        $existing = $this->configModel->where('key', $key)->first();

        if (! $existing) {
            return $this->error('Cấu hình không tồn tại.', 404);
        }

        $this->configModel->delete($existing->id);
        $this->configModel->invalidateCache();

        return $this->success(null, 'Đã xóa cấu hình.');
    }
}
