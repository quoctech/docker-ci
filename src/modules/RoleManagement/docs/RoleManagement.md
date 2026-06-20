# Module: Quản lý vai trò (role-management)

## Mục đích

Cho phép tạo các vai trò (role template) với danh sách quyền module, sau đó áp dụng nhanh cho người dùng (workspace_admin).

## Triết lý — Single Source of Truth (Phương án A)

> Quyền của user = UNION quyền của **TẤT CẢ** role user được gán.
> Không lưu quyền trực tiếp trên user — không drift, role đổi là user đổi theo.

Trước đây hệ thống có 2 bảng phân quyền song song (`role_module_permissions` + `user_module_permissions`) gây ra:
- **Drift dữ liệu** khi role thay đổi mà user permissions không sync kịp
- **Phức tạp** khi áp dụng role (phải copy dữ liệu từ role sang user)
- **Khó debug** khi quyền bị sai giữa 2 nơi

Sau khi tối ưu:
- ✅ Chỉ có **1 nguồn dữ liệu** duy nhất: `role_module_permissions`
- ✅ User permissions được tính tự động qua JOIN khi cần
- ✅ Có cache Redis (`perm:user:{uuid}`, TTL 1 giờ)
- ✅ Multi-role: 1 user có thể gán nhiều role, quyền = UNION
- ✅ Audit log đầy đủ (`role_permission_audit_logs`)
- ✅ Soft-delete + tạo lại role: revive row cũ (giữ UUID → không phá reference)

## Soft-delete + tạo lại — Cách giải quyết

Bug gốc: `UNIQUE(slug)` chặn tạo lại role cùng slug sau khi soft-delete.

Cách giải quyết: **`RoleRepository::createOrRevive()`** — tận dụng row inactive:

```php
public function createOrRevive(array $data): array
{
    $existing = $this->findBySlugAny($data['slug']);

    if ($existing && $existing->is_active === 1) {
        throw new \RuntimeException('Slug đang được sử dụng bởi vai trò hoạt động.');
    }

    if ($existing) {
        // REVIVE: UPDATE row cũ (giữ UUID)
        $this->model->update($existing->id, [
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active'   => 1,
            'perm_version' => 1,
        ]);
        return ['role' => $this->model->find($existing->id), 'revived' => true];
    }

    // INSERT mới
    $id = $this->model->insert([...]);
    return ['role' => $this->model->find($id), 'revived' => false];
}
```

Ưu điểm:
- Không cần virtual column / partial unique index
- Không cần migration
- Giữ nguyên UUID → role_module_permissions, user_applied_roles, audit log cũ vẫn refer đúng
- Lịch sử phân quyền được bảo toàn (chỉ is_active thay đổi, không tạo row mới)

## Luồng hoạt động

```
┌─────────────────┐     ┌──────────────────┐     ┌─────────────────┐
│ Admin tạo role  │ ──▶ │ Set permission   │ ──▶ │ Apply cho user  │
│ POST /roles     │     │ PUT /modules     │     │ POST /apply-... │
│ (revive nếu cùng│     │                  │     │                 │
│  slug đã xóa)   │     │                  │     │                 │
└─────────────────┘     └──────────────────┘     └─────────────────┘
                                                         │
                                                         ▼
                                            ┌──────────────────────────┐
                                            │ INSERT user_applied_roles│
                                            │ + DEL cache user         │
                                            │ (KHÔNG force logout)     │
                                            └──────────────────────────┘
                                                         │
                                                         ▼
                                            User nhận quyền mới ở request kế tiếp qua JOIN:
                                            user_applied_roles ─▶ role_module_permissions
```

**Bước 1**: Admin tạo vai trò (VD: "Giáo viên Toán", "Quản lý chi nhánh")
- Nếu slug đã tồn tại với `is_active=0` → **REVIVE** row (giữ UUID, cập nhật name)
- Nếu slug đã tồn tại với `is_active=1` → lỗi 409
- Nếu slug mới → INSERT

**Bước 2**: Admin phân quyền module cho vai trò (can_read/write/edit/delete từng module)
- Server: ghi vào `role_module_permissions`, bump `roles.perm_version`
- Server: DEL cache permission của tất cả user đang dùng role
- **KHÔNG force logout** — user nhận perm mới ở request kế tiếp

**Bước 3**: Admin áp dụng vai trò cho user
- Server: chỉ INSERT vào `user_applied_roles` — **KHÔNG copy data**
- Server: DEL cache permission của user
- **KHÔNG force logout** — UX mượt

**Bước 4** (real-time): Khi user request tiếp theo, hệ thống JOIN `user_applied_roles` → `role_module_permissions` để tính quyền thực tế. Kết quả được cache trong Redis.

## Tables

### roles
| Cột | Kiểu | Mô tả |
|-----|------|-------|
| id | BIGINT PK | |
| uuid | VARCHAR(36) UNIQUE | |
| name | VARCHAR(100) | Tên hiển thị |
| slug | VARCHAR(100) | Tự sinh từ tên (giữ nguyên cả khi soft-delete) |
| description | VARCHAR(255) | Mô tả ngắn |
| is_active | TINYINT | 1 = active, 0 = soft-deleted |
| **perm_version** | **INT UNSIGNED** | **Bump khi permission thay đổi** |

### role_module_permissions
| Cột | Kiểu | Mô tả |
|-----|------|-------|
| id | BIGINT PK | |
| role_id | BIGINT FK → roles.id | |
| module_slug | VARCHAR(100) | Slug của module |
| can_read | TINYINT | Bắt buộc = 1 (nếu không thì không lưu) |
| can_write | TINYINT | |
| can_edit | TINYINT | |
| can_delete | TINYINT | |

### user_applied_roles
| Cột | Kiểu | Mô tả |
|-----|------|-------|
| id | BIGINT PK | |
| **user_uuid** | **VARCHAR(36)** | **KHÔNG UNIQUE nữa — cho phép 1 user gán N role** |
| role_id | BIGINT FK → roles.id | |
| applied_by | VARCHAR(36) | UUID admin thực hiện |
| applied_at | DATETIME | |

### role_permission_audit_logs
| Cột | Kiểu | Mô tả |
|-----|------|-------|
| id | BIGINT PK | |
| uuid | VARCHAR(36) UNIQUE | |
| action | VARCHAR(40) | `role_created` \| `role_revived` \| `role_updated` \| `role_deleted` \| `role_perm_changed` \| `role_applied` \| `role_unapplied` |
| role_uuid | VARCHAR(36) | |
| role_id | BIGINT | |
| user_uuid | VARCHAR(36) | User bị ảnh hưởng (khi áp dụng role) |
| performed_by | VARCHAR(36) | Admin thực hiện |
| before_json | TEXT | Trạng thái trước (JSON) |
| after_json | TEXT | Trạng thái sau (JSON) |
| ip_address | VARCHAR(45) | |
| created_at | DATETIME | |

### ~~user_module_permissions~~ (ĐÃ XÓA)
> Bảng này đã được drop ở migration `2026-06-20-000025_DropUserModulePermissions`.
> Quyền của user được tính động qua JOIN.

## Cache Strategy

**Redis key**: `perm:user:{user_uuid}`
**Value**: JSON map slug → {can_read, can_write, can_edit, can_delete}
**TTL**: 3600s (1 giờ)

**Invalidate khi**:
1. Role permission thay đổi → DEL cache của TẤT CẢ user đang dùng role đó
2. User được áp dụng role mới → DEL cache của user đó
3. Role bị xóa/vô hiệu → DEL cache của tất cả user đang dùng role

**KHÔNG force logout user** khi permission thay đổi — JWT vẫn còn hiệu lực, chỉ cần DEL cache là request kế tiếp sẽ tự lấy permission mới qua JOIN.

## API Endpoints

Tất cả yêu cầu JWT + filter `module_check:role-management`.

| Method | URL | Mô tả |
|--------|-----|-------|
| GET | /api/role-management/roles | Danh sách vai trò |
| POST | /api/role-management/roles | Tạo mới hoặc revive role soft-deleted (cùng slug) |
| GET | /api/role-management/roles/:uuid | Chi tiết |
| PUT | /api/role-management/roles/:uuid | Cập nhật tên/mô tả |
| DELETE | /api/role-management/roles/:uuid | Soft delete (set is_active=0) |
| GET | /api/role-management/roles/:uuid/modules | Quyền module của vai trò |
| PUT | /api/role-management/roles/:uuid/modules | Cập nhật quyền module (bump version + DEL cache) |
| POST | /api/role-management/roles/:uuid/apply-to-user | Áp dụng cho user (multi-role OK) |

## Ghi chú kỹ thuật

- Module `auth`, `system-admin`, `system-log` không được phân quyền qua role (chỉ super_admin)
- 1 user có thể gán nhiều role → quyền cuối = UNION của tất cả role
- Soft-delete chỉ cần `is_active = 0`; tạo lại cùng slug sẽ revive row cũ (giữ UUID)
- KHÔNG force logout user khi cấp/thu quyền (chỉ DEL cache) → UX mượt
- **Ngoại lệ**: khi đổi `users.role` (super_admin → workspace_admin) thì CẦN force logout vì JWT có chứa `role` field — xem `UserManagementController::updateRole()`
- Mọi thay đổi đều ghi audit log với before/after JSON để truy vết
- Check quyền runtime: JOIN `user_applied_roles` → `roles` (is_active=1) → `role_module_permissions`
- Cache qua Redis: `perm:user:{uuid}` — fallback về DB khi cache miss