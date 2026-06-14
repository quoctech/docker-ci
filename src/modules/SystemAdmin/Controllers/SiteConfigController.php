<?php

namespace Modules\SystemAdmin\Controllers;

use App\Controllers\ApiController;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\SystemAdmin\Repositories\SiteConfigRepository;

/**
 * SiteConfigController - CRUD cấu hình website động.
 *
 * Xử lý request/response. Logic database ủy thác cho Repository.
 */
class SiteConfigController extends ApiController
{
    private SiteConfigRepository $configRepo;

    public function __construct()
    {
        $this->configRepo = new SiteConfigRepository();
    }

    /**
     * GET /api/admin/configs
     */
    public function index(): ResponseInterface
    {
        return $this->success($this->configRepo->getAllGrouped());
    }

    /**
     * GET /api/admin/configs/(:any)
     */
    public function show(string $key): ResponseInterface
    {
        $value = $this->configRepo->getValue($key);

        if ($value === null) {
            return $this->error('Cấu hình không tồn tại.', 404);
        }

        return $this->success(['key' => $key, 'value' => $value]);
    }

    /**
     * POST /api/admin/configs
     */
    public function create(): ResponseInterface
    {
        $isJson = str_contains($this->request->getHeaderLine('Content-Type'), 'application/json');
        $input  = $isJson ? (array) $this->request->getJSON(true) : $this->request->getPost();

        $rules = [
            'key'         => 'required|alpha_dash|max_length[100]|is_unique[site_configs.key]',
            'value'       => 'permit_empty',
            'group'       => 'permit_empty|alpha_dash|max_length[50]',
            'type'        => 'permit_empty|in_list[string,integer,boolean,json]',
            'description' => 'permit_empty|max_length[255]',
        ];

        if (! $this->validateData($input, $rules)) {
            return $this->error('Dữ liệu không hợp lệ.', 422, $this->validator->getErrors());
        }

        $this->configRepo->setValue(
            $input['key'],
            $input['value'] ?? '',
            $input['group'] ?? 'general',
            $input['type'] ?? 'string',
            $input['description'] ?? null
        );

        return $this->success(null, 'Đã tạo cấu hình.', 201);
    }

    /**
     * PUT /api/admin/configs/(:any)
     */
    public function update(string $key): ResponseInterface
    {
        $existing = $this->configRepo->findByKey($key);

        if (! $existing) {
            return $this->error('Cấu hình không tồn tại.', 404);
        }

        // getRawInput() parse cả form-urlencoded lẫn JSON cho PUT request
        $isJson = str_contains($this->request->getHeaderLine('Content-Type'), 'application/json');
        $input  = $isJson ? (array) $this->request->getJSON(true) : $this->request->getRawInput();
        $value  = $input['value'] ?? '';

        $this->configRepo->setValue($key, $value, $existing->group, $existing->type, $existing->description);

        return $this->success(['key' => $key, 'value' => $value], 'Đã cập nhật cấu hình.');
    }

    /**
     * DELETE /api/admin/configs/(:any)
     */
    public function delete(string $key): ResponseInterface
    {
        $existing = $this->configRepo->findByKey($key);

        if (! $existing) {
            return $this->error('Cấu hình không tồn tại.', 404);
        }

        $this->configRepo->delete($existing->id);
        $this->configRepo->invalidateCache();

        return $this->success(null, 'Đã xóa cấu hình.');
    }
}
