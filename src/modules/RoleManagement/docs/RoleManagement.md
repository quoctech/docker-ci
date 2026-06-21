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

## Quy trình xóa vai trò (DELETE /roles/:uuid)

> **Quan trọng**: Khi xóa role, hệ thống **TỰ ĐỘNG** gỡ bỏ role khỏi tất cả user đang được gán trước.

Quy trình thực hiện trong `AdminRoleController::delete()`:

1. Tìm role theo UUID (404 nếu không có hoặc đã bị soft-delete)
2. `RoleRepository::removeAllUsersFromRole()` → xóa tất cả record trong `user_applied_roles` có `role_id` này
3. Invalidate cache permission (`perm:user:{uuid}`) của tất cả user bị gỡ
4. `RoleRepository::deactivate()` → set `roles.is_active = 0`
5. Ghi audit log:
   - `role_unapplied_by_role_delete` (1 record cho mỗi user bị gỡ)
   - `role_deleted` (1 record tổng kết với `affected_user_count`)

Response mẫu:

```json
{
  "status": "success",
  "message": "Đã xóa vai trò và gỡ bỏ vai trò khỏi 3 người dùng.",
  "data": { "affected_user_count": 3 }
}
```

**Lý do gỡ user trước**:
- Tránh để user "treo" trên role đã xóa (permission = UNION qua JOIN với `is_active=1` nên đã tự mất, nhưng record `user_applied_roles` vẫn còn)
- Nếu role được revive sau, user sẽ không tự động nhận lại role cũ — đảm bảo admin phải chủ động apply lại
- Audit log đầy đủ để truy vết ai đã bị gỡ khỏi role nào

## API Endpoints

Tất cả yêu cầu JWT + filter `module_check:role-management`.

| Method | URL | Mô tả |
|--------|-----|-------|
| GET | /api/role-management/roles | Danh sách vai trò |
| POST | /api/role-management/roles | Tạo mới hoặc revive role soft-deleted (cùng slug) |
| GET | /api/role-management/roles/:uuid | Chi tiết role + permissions |
| PUT | /api/role-management/roles/:uuid | Cập nhật tên/mô tả |
| DELETE | /api/role-management/roles/:uuid | **Xóa role + tự động gỡ khỏi user** |
| GET | /api/role-management/roles/:uuid/users | Danh sách user đang được gán role |
| GET | /api/role-management/roles/:uuid/modules | Quyền module của vai trò |
| PUT | /api/role-management/roles/:uuid/modules | Cập nhật quyền module (bump version + DEL cache) |
| POST | /api/role-management/roles/:uuid/apply-to-user | Áp dụng cho user (multi-role OK) |
| DELETE | /api/role-management/user-applied-roles | Bỏ áp dụng role cho 1 user (giữ role) |

## Ghi chú kỹ thuật

- Module `auth`, `system-admin`, `system-log` không được phân quyền qua role (chỉ super_admin)
- 1 user có thể gán nhiều role → quyền cuối = UNION của tất cả role
- Soft-delete chỉ cần `is_active = 0`; tạo lại cùng slug sẽ revive row cũ (giữ UUID)
- KHÔNG force logout user khi cấp/thu quyền (chỉ DEL cache) → UX mượt
- **Ngoại lệ**: khi đổi `users.role` (super_admin → workspace_admin) thì CẦN force logout vì JWT có chứa `role` field — xem `UserManagementController::updateRole()`
- Mọi thay đổi đều ghi audit log với before/after JSON để truy vết
- Check quyền runtime: JOIN `user_applied_roles` → `roles` (is_active=1) → `role_module_permissions`
- Cache qua Redis: `perm:user:{uuid}` — fallback về DB khi cache miss

## Lịch sử sửa lỗi

### 2026-06-21 — Fix UUID rỗng + Catch-all route gây hiểu nhầm

**Triệu chứng**: Khi bấm xóa role "Super Admin", nhận thông báo `Thiếu UUID vai trò. Cú pháp: DELETE /api/role-management/roles/{uuid}`.

**Nguyên nhân gốc** (gồm 2 phần):

1. **UUID rỗng trong DB**: Role "Super Admin" (id=22) trong bảng `roles` có cột `uuid = ''` — do dữ liệu cũ hoặc bug khi insert. Khi API `GET /roles` trả về `uuid: ""`, frontend build URL thành `/api/role-management/roles/` (UUID rỗng).

2. **Catch-all route gây hiểu nhầm**: Trong `Routes.php` có 2 closure routes `delete('roles')` và `put('roles')` trả về message "Thiếu UUID vai trò" — match đúng với URL có UUID rỗng → hiển thị thông báo kỹ thuật gây hoang mang.

**Cách sửa**:

1. **Migration `2026-06-21-000030_FixEmptyRoleUuids`**: Quét và sinh lại UUID v4 cho mọi row có `uuid=''` hoặc NULL trong bảng `roles`. Idempotent.

2. **Bỏ catch-all routes** trong `app/Config/Routes.php`: Khi UUID rỗng, giờ trả về 404 chuẩn của CodeIgniter thay vì 400 với message gây hiểu nhầm.

3. **Sửa `AdminRoleController::delete()`**: Trước khi soft-delete role, **gỡ bỏ tất cả user_applied_roles** của role đó. Ghi audit log riêng cho hành động gỡ user.

4. **Thêm endpoint `GET /api/role-management/roles/:uuid/users`**: Hiển thị danh sách user đang được gán role (dùng cho cảnh báo trước khi xóa).

5. **Cải thiện UX trong `role-management.js`**: Modal confirm xóa sẽ gọi API trên trước, hiển thị số user sẽ bị ảnh hưởng và tên 3 user đầu tiên để admin xác nhận.
