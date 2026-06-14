# VortexEngine — Module Core 03: Subscription Engine

## Tổng quan

VortexEngine là engine quản lý gói đăng ký (subscription) dành cho học viên trên nền tảng BladeEngine. Module kiểm soát quyền truy cập vào nội dung bài học thông qua cơ chế phân quyền theo trạng thái gói.

**Trạng thái subscription:**

| Trạng thái | Ý nghĩa |
|---|---|
| `TRIAL` | Dùng thử — mặc định khi học viên đăng ký, chưa mua gói |
| `VIP` | Đã kích hoạt gói trả phí, còn trong hạn |
| `EXPIRED` | Gói đã hết hạn |

---

## Cơ sở dữ liệu

### Bảng `packages`

Lưu danh sách các gói học có thể bán.

| Cột | Kiểu | Mô tả |
|---|---|---|
| `id` | INT UNSIGNED PK | Auto increment |
| `package_key` | VARCHAR(50) UNIQUE | Định danh gói: `1_MONTH`, `3_MONTHS`, `6_MONTHS`, `1_YEAR` |
| `name` | VARCHAR(100) | Tên gói hiển thị |
| `description` | TEXT | Mô tả gói |
| `price` | INT UNSIGNED | Giá VND |
| `days_to_add` | INT UNSIGNED | Số ngày gia hạn khi kích hoạt |
| `is_active` | TINYINT(1) | 1 = đang bán, 0 = ngừng bán |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

**Gói mặc định (seeded):**

| package_key | days_to_add | price |
|---|---|---|
| `1_MONTH` | 30 | 500,000 |
| `3_MONTHS` | 90 | 1,200,000 |
| `6_MONTHS` | 180 | 2,000,000 |
| `1_YEAR` | 365 | 3,500,000 |

### Bảng `student_subscriptions`

Lưu lịch sử và trạng thái gói học của từng học viên.

| Cột | Kiểu | Mô tả |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `student_id` | INT UNSIGNED | FK → `students.id` (module Students) |
| `parent_id` | INT UNSIGNED NULL | FK → user id phụ huynh (nếu mua hộ) |
| `package_key` | VARCHAR(50) | Gói đã kích hoạt |
| `status` | VARCHAR(20) | `TRIAL` / `VIP` / `EXPIRED` |
| `start_date` | DATETIME NULL | Ngày bắt đầu tính hạn |
| `expired_date` | DATETIME NULL INDEX | Ngày hết hạn |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

---

## Redis Cache

**Key pattern:** `sub:student:{student_id}` (HASH)

**Fields:**
- `status` — `TRIAL` / `VIP` / `EXPIRED`
- `expired_date` — chuỗi datetime hoặc rỗng nếu TRIAL

**TTL:** 300 giây (5 phút)

**Hằng số:**
```php
REDIS_PREFIX_SUBSCRIPTION = 'sub:student:'
SUBSCRIPTION_CACHE_TTL    = 300
```

**Luồng đọc:**
1. Đọc Redis → nếu có: dùng ngay (check `expired_date` so với `time()`)
2. Cache miss → query DB → ghi vào Redis → dùng kết quả DB
3. Nếu phát hiện hết hạn (dù DB chưa cập nhật): update DB, xóa Redis, trả 403

---

## Filter: `subscription`

**Class:** `Modules\VortexEngine\Filters\SubscriptionFilter`

**Alias:** `subscription` (đăng ký trong `Config/Filters.php`)

**Áp dụng cho:** `api/lessons/*` (thêm vào route khi module Lessons được xây dựng)

**Điều kiện bypass:**
- Role `super_admin` hoặc `workspace_admin` → pass thẳng (không cần gói)

**Điều kiện block:**
- Không có `student_id` trong JWT payload → 403 `no_student_id`
- Chưa có subscription nào trong DB → 403 `TRIAL`
- Subscription EXPIRED → 403 `EXPIRED`
- Subscription VIP nhưng `expired_date` đã qua → tự động mark EXPIRED → 403

**Response khi block:**
```json
{
  "status": "error",
  "code": "EXPIRED",
  "message": "Gói học của bạn đã hết hạn. Vui lòng gia hạn để tiếp tục học."
}
```

**Lưu ý:** Filter chạy sau `auth` filter. JWT phải hợp lệ trước khi vào đây.

---

## Bảo vệ Route theo trạng thái Module

VortexEngine **chỉ hoạt động khi module được bật** trong trang Quản lý Module. Mọi route (cả web page lẫn API) đều bị chặn khi module ở trạng thái tắt.

### Cơ chế

Hệ thống dùng hai filter riêng biệt:

| Filter | Dùng cho | Phản hồi khi bị tắt |
|---|---|---|
| `module_check:vortex-engine` | API endpoints (`/api/admin/subscriptions/*`) | HTTP 503 JSON |
| `module_redirect:vortex-engine` | Web page (`/admin/subscriptions`) | Redirect `/admin/modules` |

### Áp dụng trong Routes.php

```php
// Web page — redirect về trang quản lý module nếu bị tắt
$routes->get('subscriptions', '...::subscriptions', ['filter' => 'module_redirect:vortex-engine']);

// API — trả 503 JSON nếu bị tắt (nằm trong group auth:super_admin)
$routes->group('subscriptions', ['filter' => 'module_check:vortex-engine'], function ($routes) {
    $routes->post('activate', '...');
    $routes->get('packages', '...');
});
```

### Quy tắc cho module mới

Bất kỳ module nào cần kiểm soát theo trạng thái bật/tắt **PHẢI** áp dụng đủ cả hai filter:
1. `module_redirect:{slug}` trên **mọi web route** của module đó
2. `module_check:{slug}` trên **mọi API route** của module đó

Không được xử lý kiểm tra này trong controller — phải khai báo ở route level để đảm bảo không bỏ sót.

---

## API Endpoints

### `POST /api/admin/subscriptions/activate`

Kích hoạt hoặc gia hạn gói học cho học viên. Yêu cầu role `super_admin`.

**Request body** (JSON hoặc form-urlencoded):

| Field | Kiểu | Bắt buộc | Mô tả |
|---|---|---|---|
| `student_id` | int | Có | ID học viên |
| `package_key` | string | Có | Mã gói: `1_MONTH`, `3_MONTHS`, `6_MONTHS`, `1_YEAR` |
| `parent_id` | int | Không | ID phụ huynh mua hộ |

**Logic:**
- Nếu học viên **đang có VIP còn hạn**: gia hạn — cộng `days_to_add` vào `expired_date` hiện tại
- Nếu **hết hạn hoặc TRIAL hoặc chưa có**: tạo subscription mới với `start_date = NOW`, `expired_date = NOW + days_to_add`
- Sau khi ghi DB: xóa Redis cache của học viên đó

**Response thành công (200):**
```json
{
  "status": "success",
  "message": "Kích hoạt gói học thành công.",
  "data": {
    "subscription_id": 1,
    "student_id": 42,
    "package_key": "3_MONTHS",
    "status": "VIP",
    "start_date": "2026-06-14 10:00:00",
    "expired_date": "2026-09-12 10:00:00"
  }
}
```

**Response lỗi:**
```json
{ "status": "error", "message": "Gói học không tồn tại hoặc đã bị vô hiệu hoá.", "code": 404 }
```

---

### `GET /api/admin/subscriptions/packages`

Danh sách gói học đang hoạt động (`is_active = 1`), sắp xếp theo `days_to_add`.

---

## Cấu trúc module

```
src/modules/VortexEngine/
├── Controllers/
│   └── AdminSubscriptionController.php
├── Filters/
│   └── SubscriptionFilter.php
├── Models/
│   ├── PackageModel.php
│   └── StudentSubscriptionModel.php
├── Repositories/
│   └── SubscriptionRepository.php
└── docs/
    └── VortexEngine.md

src/app/
├── Config/
│   ├── Constants.php   (thêm SUB_STATUS_*, REDIS_PREFIX_SUBSCRIPTION, SUBSCRIPTION_CACHE_TTL)
│   ├── Filters.php     (thêm alias 'subscription')
│   └── Routes.php      (thêm POST api/admin/subscriptions/activate)
└── Database/
    ├── Migrations/
    │   ├── 2026-06-14-000001_CreatePackagesTable.php
    │   └── 2026-06-14-000002_CreateStudentSubscriptionsTable.php
    └── Seeds/
        └── PackagesSeeder.php
```

---

## Migration & Seed

```bash
# Chạy migration
php spark migrate

# Seed 4 gói mặc định
php spark db:seed PackagesSeeder
```

---

## Tích hợp với module Lessons (tương lai)

Khi module Lessons được xây dựng, thêm filter `subscription` vào route group:

```php
$routes->group('api/lessons', ['filter' => 'auth,subscription'], function ($routes) {
    // lesson routes
});
```

JWT payload của học viên phải chứa trường `student_id` (int) khi module Students được triển khai.

---

## Xử lý lỗi

Mọi exception trong `SubscriptionRepository::activate()` và `SubscriptionFilter` đều được:
1. Log qua `log_message('error', '[VortexEngine]...')` — CI4 built-in logging
2. Controller trả về HTTP 500 với message thân thiện
3. Filter fail-open (lỗi hệ thống không chặn học viên)

> Khi module Mắt Thần (System Log) được triển khai, thay `log_message()` bằng `SystemLogService::error()`.
