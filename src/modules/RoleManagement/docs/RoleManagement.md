# Module: Quản lý vai trò (role-management)

## Mục đích

Cho phép tạo các vai trò (role template) với danh sách quyền module, sau đó áp dụng nhanh cho người dùng (workspace_admin).

## Luồng hoạt động

1. Admin tạo vai trò (VD: "Giáo viên Toán", "Quản lý chi nhánh")
2. Admin phân quyền module cho vai trò (đọc/ghi/sửa/xóa từng module)
3. Admin áp dụng vai trò cho người dùng → user_module_permissions của user bị ghi đè bằng quyền của role
4. Admin vẫn có thể chỉnh sửa từng quyền sau khi áp dụng

## Tables

### roles
| Cột | Kiểu | Mô tả |
|-----|------|-------|
| id | BIGINT PK | |
| uuid | VARCHAR(36) UNIQUE | |
| name | VARCHAR(100) | Tên hiển thị |
| slug | VARCHAR(100) UNIQUE | Tự sinh từ tên |
| description | VARCHAR(255) | Mô tả ngắn |
| is_active | TINYINT | Soft delete |

### role_module_permissions
| Cột | Kiểu | Mô tả |
|-----|------|-------|
| id | BIGINT PK | |
| role_id | BIGINT FK → roles.id | |
| module_slug | VARCHAR(100) | Slug của module |
| can_read | TINYINT | |
| can_write | TINYINT | |
| can_edit | TINYINT | |
| can_delete | TINYINT | |

## API Endpoints

Tất cả yêu cầu JWT + filter `module_check:role-management`.

| Method | URL | Mô tả |
|--------|-----|-------|
| GET | /api/role-management/roles | Danh sách vai trò |
| POST | /api/role-management/roles | Tạo vai trò |
| GET | /api/role-management/roles/:uuid | Chi tiết |
| PUT | /api/role-management/roles/:uuid | Cập nhật |
| DELETE | /api/role-management/roles/:uuid | Xóa (soft) |
| GET | /api/role-management/roles/:uuid/modules | Quyền module của vai trò |
| PUT | /api/role-management/roles/:uuid/modules | Cập nhật quyền module |
| POST | /api/role-management/roles/:uuid/apply-to-user | Áp dụng cho user |

## Ghi chú

- Áp dụng vai trò = gọi `UserModulePermissionRepository::setPermissions()` → ghi đè `user_module_permissions`
- Module `auth`, `system-admin`, `system-log` không được phân quyền
- User cần refresh browser để sidebar cập nhật sau khi được gán quyền
