# Module: Quản lý trường học (school-management)

## Cấu trúc tổ chức

```
Trung tâm (Center)
  └── Chi nhánh (Branch)
        └── Phòng học (Room)
```

- **Trung tâm**: Đơn vị cao nhất. VD: "Trung tâm Hà Nội"
- **Chi nhánh**: Thuộc một trung tâm. VD: "Cơ sở Cầu Giấy"
- **Phòng học**: Thuộc một chi nhánh. VD: "Phòng A101"

## Quy tắc nghiệp vụ

### Chi nhánh
- **Bắt buộc**: tên, địa chỉ, số điện thoại, email, người phụ trách
- Tên chi nhánh không được trùng trong cùng trung tâm (hoặc global nếu không có trung tâm)
- **Xóa chi nhánh**: Bị chặn nếu còn phòng học đang hoạt động

### Trung tâm
- **Xóa trung tâm**: Bị chặn nếu còn chi nhánh đang hoạt động

## Tables

### centers
| Cột | Kiểu | Mô tả |
|-----|------|-------|
| id | BIGINT PK | |
| uuid | VARCHAR(36) UNIQUE | |
| name | VARCHAR(100) | |
| address | TEXT | |
| phone | VARCHAR(20) | |
| email | VARCHAR(100) | |
| is_active | TINYINT | Soft delete |

### branches
| Cột | Kiểu | Mô tả |
|-----|------|-------|
| id | BIGINT PK | |
| uuid | VARCHAR(36) UNIQUE | |
| center_id | BIGINT FK → centers.id | Nullable |
| name | VARCHAR(100) | |
| address | TEXT | Bắt buộc |
| phone | VARCHAR(20) | Bắt buộc |
| email | VARCHAR(100) | Bắt buộc |
| manager | VARCHAR(100) | Người phụ trách, bắt buộc |
| is_active | TINYINT | Soft delete |

### rooms
| Cột | Kiểu | Mô tả |
|-----|------|-------|
| id | BIGINT PK | |
| uuid | VARCHAR(36) UNIQUE | |
| branch_id | BIGINT FK → branches.id | |
| name | VARCHAR(100) | |
| capacity | INT | Sức chứa |
| room_type | VARCHAR(50) | Loại phòng |
| is_active | TINYINT | Soft delete |

## API Endpoints

Tất cả yêu cầu JWT + filter `module_check:school-management`.

| Method | URL | Mô tả |
|--------|-----|-------|
| GET/POST | /api/school-management/centers | Danh sách / Tạo trung tâm |
| GET/PUT/DELETE | /api/school-management/centers/:uuid | Chi tiết / Sửa / Xóa |
| GET/POST | /api/school-management/branches | Danh sách / Tạo chi nhánh |
| GET/PUT/DELETE | /api/school-management/branches/:uuid | Chi tiết / Sửa / Xóa |
| GET/POST | /api/school-management/rooms | Danh sách / Tạo phòng |
| GET/PUT/DELETE | /api/school-management/rooms/:uuid | Chi tiết / Sửa / Xóa |

## Sidebar items

| Label | URL | Roles |
|-------|-----|-------|
| Quản lý trung tâm | /admin/school-management/centers | workspace_admin, super_admin |
| Quản lý chi nhánh | /admin/school-management/branches | workspace_admin, super_admin |
| Quản lý phòng học | /admin/school-management/rooms | workspace_admin, super_admin |
