# VortexEngine — Module Core 03: Subscription Engine

## Tổng quan

VortexEngine là engine quản lý gói đăng ký (subscription) dành cho học viên trên nền tảng BladeEngine. Module kiểm soát quyền truy cập vào nội dung bài học thông qua cơ chế phân quyền theo trạng thái gói và lớp học được phép.

**Trạng thái subscription:**

| Trạng thái | Hiển thị | Ý nghĩa |
|---|---|---|
| `TRIAL` | Dùng thử | Mặc định khi học viên đăng ký, chưa mua gói |
| `VIP` | Premium | Đã kích hoạt gói trả phí, còn trong hạn |
| `EXPIRED` | Hết hạn | Gói đã hết hạn |

> DB lưu giá trị `VIP` / `TRIAL` / `EXPIRED`. Giao diện hiển thị "Premium" thay cho "VIP".

---

## Cơ sở dữ liệu

### Bảng `packages`

Lưu danh sách các gói học có thể bán.

| Cột | Kiểu | Mô tả |
|---|---|---|
| `id` | INT UNSIGNED PK | Auto increment |
| `package_key` | VARCHAR(50) UNIQUE | Định danh gói: `1_MONTH`, `3_MONTHS`, … |
| `name` | VARCHAR(100) | Tên gói hiển thị |
| `description` | TEXT NULL | Mô tả gói |
| `price` | INT UNSIGNED | Giá VND |
| `days_to_add` | INT UNSIGNED | Số ngày gia hạn khi kích hoạt |
| `is_active` | TINYINT(1) | 1 = đang bán, 0 = ngừng bán |
| `sub_type` | ENUM('VIP','TRIAL') | Loại subscription tạo ra khi kích hoạt — `VIP` (Premium) hoặc `TRIAL` (Dùng thử). Default `VIP` |
| `max_students` | TINYINT UNSIGNED | Số học sinh tối đa/subscription — 1 = đơn, >1 = gói kép |
| `allowed_grades` | VARCHAR(50) NULL | JSON array lớp được phép, VD `[1,2,5]` — `null` = tất cả lớp |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

**Gói mặc định (seeded):**

| package_key | days_to_add | price | sub_type |
|---|---|---|---|
| `1_MONTH` | 30 | 500,000 | `VIP` |
| `3_MONTHS` | 90 | 1,200,000 | `VIP` |
| `6_MONTHS` | 180 | 2,000,000 | `VIP` |
| `1_YEAR` | 365 | 3,500,000 | `VIP` |

**Ý nghĩa `sub_type`:**

Xác định loại `status` được ghi vào `student_subscriptions` khi kích hoạt gói. Admin có thể tạo "gói dùng thử" (`TRIAL`) để cấp quyền truy cập giới hạn mà không tính là gói trả phí. Logic gia hạn (cộng ngày vào `expired_date` hiện tại) chỉ áp dụng với gói `VIP`.

**Ý nghĩa `allowed_grades`:**

Trường **cốt lõi** xác định tài khoản học gói này được truy cập nội dung của lớp nào.

| allowed_grades | Ý nghĩa |
|---|---|
| `null` | Không giới hạn — học tất cả các lớp |
| `[1,2,3]` | Chỉ học nội dung lớp 1, 2, 3 |
| `[5]` | Chỉ học nội dung lớp 5 |

Khi kích hoạt subscription, giá trị này được **snapshot** vào `student_subscriptions.allowed_grades`. `SubscriptionFilter` dùng snapshot (không dùng package gốc) để kiểm tra quyền truy cập từng bài học.

---

### Bảng `student_subscriptions`

Lưu lịch sử và trạng thái gói học của từng học viên.

| Cột | Kiểu | Mô tả |
|---|---|---|
| `id` | INT UNSIGNED PK | |
| `student_id` | CHAR(36) | FK → `users.uuid` |
| `parent_id` | CHAR(36) NULL | UUID người mua hộ (phụ huynh) |
| `package_key` | VARCHAR(50) | Gói đã kích hoạt |
| `allowed_grades` | VARCHAR(50) NULL | Snapshot `allowed_grades` từ package tại thời điểm kích hoạt |
| `status` | VARCHAR(20) | `TRIAL` / `VIP` / `EXPIRED` |
| `start_date` | DATETIME NULL | Ngày bắt đầu tính hạn |
| `expired_date` | DATETIME NULL INDEX | Ngày hết hạn |
| `created_at` | DATETIME | |
| `updated_at` | DATETIME | |

---

### Bảng `users` (các cột liên quan)

| Cột | Kiểu | Mô tả |
|---|---|---|
| `grade` | TINYINT UNSIGNED NULL | Lớp học của học sinh (1–9), `null` nếu không phải học sinh |
| `organization` | VARCHAR(200) NULL | Tổ chức / Trường của giáo viên (`workspace_admin`) |

---

## Redis Cache

**Key pattern:** `sub:student:{uuid}` (HASH)

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

**Kiểm tra `allowed_grades` (khi module Lessons triển khai):**

```
subscription.allowed_grades = null  → pass (tất cả lớp)
subscription.allowed_grades = [1,2] → chỉ pass nếu lesson.grade ∈ [1,2]
```

Filter cần nhận `grade` của bài học từ route argument hoặc request attribute, rồi so với `allowed_grades` trong subscription.

**Response khi block:**
```json
{
  "status": "error",
  "code": "EXPIRED",
  "message": "Gói học của bạn đã hết hạn. Vui lòng gia hạn để tiếp tục học."
}
```

---

## Bảo vệ Route theo trạng thái Module

Mọi route (web page lẫn API) đều bị chặn khi module ở trạng thái tắt.

| Filter | Dùng cho | Phản hồi khi bị tắt |
|---|---|---|
| `module_check:vortex-engine` | API endpoints (`/api/admin/subscriptions/*`) | HTTP 503 JSON |
| `module_redirect:vortex-engine` | Web page (`/admin/subscriptions`) | Redirect `/admin/modules` |

```php
// Web page
$routes->get('subscriptions', '...', ['filter' => 'module_redirect:vortex-engine']);

// API group
$routes->group('subscriptions', ['filter' => 'module_check:vortex-engine'], function ($routes) {
    $routes->post('activate', '...');
    $routes->get('packages', '...');
    // ...
});
```

**Quy tắc bắt buộc cho mọi module:** áp dụng đủ cả hai filter — `module_redirect` cho web, `module_check` cho API. Không xử lý trong controller.

---

## API Endpoints

### `POST /api/admin/subscriptions/activate`

Kích hoạt hoặc gia hạn gói học cho học viên.

**Request body** (JSON hoặc form-urlencoded):

| Field | Kiểu | Bắt buộc | Mô tả |
|---|---|---|---|
| `student_id` | string (UUID) | Có | UUID học viên |
| `package_key` | string | Có | Mã gói |
| `parent_id` | string (UUID) | Không | UUID người mua hộ |

**Logic:**
- `status` tạo ra = `package.sub_type` (`VIP` hoặc `TRIAL`)
- Học viên **đang có VIP còn hạn** + gói mới cũng là `VIP` → gia hạn: cộng `days_to_add` vào `expired_date` hiện tại
- **Hết hạn / TRIAL / chưa có** hoặc kích hoạt gói `TRIAL` → tạo subscription mới, `start_date = NOW`, `expired_date = NOW + days_to_add`
- `allowed_grades` được snapshot từ package vào subscription lúc tạo mới
- Sau khi ghi DB: xóa Redis cache của học viên

---

### `PUT /api/admin/subscriptions/{id}`

Sửa thông tin một subscription cụ thể (dùng trong tab Danh sách).

**Request body** (JSON):

| Field | Kiểu | Bắt buộc | Mô tả |
|---|---|---|---|
| `package_key` | string | Không | Đổi gói |
| `status` | string | Không | `VIP` / `TRIAL` / `EXPIRED` |
| `expired_date` | string (YYYY-MM-DD) | Không | Ngày hết hạn mới, để trống = không giới hạn |

- Sau khi cập nhật DB: xóa Redis cache của học viên (`student_id` lấy từ subscription hiện tại)

---

### `GET /api/admin/subscriptions/list`

Danh sách subscription kèm thông tin học sinh, có phân trang và filter.

**Query params:**

| Param | Mô tả |
|---|---|
| `page` | Trang (default 1) |
| `per_page` | Số bản ghi/trang (max 100, default 20) |
| `search` | Tìm theo tên / email / username học sinh |
| `status` | `VIP` / `TRIAL` / `EXPIRED` |
| `grade` | Lọc theo lớp học (1–9) |

**Response `data`:**
```json
{
  "subscriptions": [...],
  "pagination": { "page": 1, "per_page": 20, "total": 120, "total_pages": 6 }
}
```

---

### `GET /api/admin/subscriptions/packages`

Danh sách gói đang hoạt động (`is_active = 1`), sắp xếp theo `days_to_add`.

---

### `GET /api/admin/subscriptions/packages/all`

Toàn bộ gói (cả tắt), dùng cho trang quản lý.

---

### `POST /api/admin/subscriptions/packages`

Tạo gói mới.

| Field | Kiểu | Bắt buộc | Mô tả |
|---|---|---|---|
| `package_key` | string | Có | UPPER_CASE, unique |
| `name` | string | Có | |
| `days_to_add` | int | Có | |
| `price` | int | Có | VND |
| `description` | string | Không | |
| `sub_type` | string | Không | `VIP` (default) hoặc `TRIAL` |
| `max_students` | int | Không | Default 1 |
| `allowed_grades` | array\|null | Không | `[1,2,5]` hoặc `null` |

---

### `PUT /api/admin/subscriptions/packages/{key}`

Cập nhật thông tin gói. Tất cả field đều optional (chỉ gửi field cần thay đổi). Hỗ trợ `sub_type`, `max_students`, `allowed_grades`.

---

### `PUT /api/admin/subscriptions/packages/{key}/toggle`

Bật / tắt gói học (`is_active`).

---

### `GET /api/admin/users` (dùng trong student picker)

API dùng chung với module SystemAdmin, nhưng VortexEngine sử dụng thêm hai filter:

| Param | Mô tả |
|---|---|
| `role=user` | Chỉ lấy học sinh |
| `exclude_subscribed=1` | Loại trừ học sinh đang có subscription `VIP` hoặc `TRIAL` còn hạn |
| `grade` | Lọc theo lớp |
| `per_page` / `page` | Phân trang — student picker dùng 10 bản ghi/trang |

---

## Giao diện quản trị (`/admin/subscriptions`)

Ba sub-tab:

### Tab ⚡ Kích hoạt
- **Bước 1:** Chọn học viên — dropdown tìm kiếm, 10 học viên/trang, load more, tự động loại trừ học viên đã có subscription còn hạn (`exclude_subscribed=1`)
- **Bước 2:** Chọn gói học — hiển thị gói đang bán, kèm thông tin `sub_type`, `allowed_grades`, `max_students`
- **Bước 3:** Xác nhận và kích hoạt

### Tab 📦 Quản lý gói
- Xem, tạo, sửa, bật/tắt gói
- Mỗi gói card hiển thị badge loại đăng ký (Premium / Dùng thử), số học sinh, lớp được phép
- Inline edit: sửa tên, giá, số ngày, **loại đăng ký**, số HS tối đa, lớp, mô tả

### Tab 📋 Danh sách
- Bảng tất cả subscription, có filter tìm kiếm / trạng thái / lớp
- Nút ✏️ mở modal sửa: đổi gói, đổi trạng thái, chỉnh ngày hết hạn
- Trạng thái hiển thị: `VIP` → "Premium", `TRIAL` → "Dùng thử", `EXPIRED` → "Hết hạn"

---

## Luồng kiểm tra quyền truy cập bài học

```
Request học bài học (grade=X)
        │
        ▼
  auth filter (JWT hợp lệ?)
        │ pass
        ▼
  subscription filter
        ├─ admin/giáo viên → pass
        ├─ Không có subscription → 403 TRIAL
        ├─ EXPIRED / hết hạn → 403 EXPIRED
        └─ VIP còn hạn
              ├─ allowed_grades = null → pass (tất cả lớp)
              └─ allowed_grades = [1,2] → kiểm tra X ∈ [1,2]
                      ├─ có → pass
                      └─ không → 403 GRADE_NOT_ALLOWED  ← (chưa implement, cần khi có Lessons)
```

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
├── module.json
└── docs/
    └── VortexEngine.md

src/app/Database/Migrations/
├── 2026-06-14-000001_CreatePackagesTable.php
├── 2026-06-14-000002_CreateStudentSubscriptionsTable.php
├── 2026-06-14-000003_AddMetaToModules.php
├── 2026-06-14-000004_ChangeStudentIdToUuid.php
├── 2026-06-14-000005_AddGradeToUsers.php
├── 2026-06-14-000006_AddOrgToUsers.php
├── 2026-06-14-000007_AddMultiStudentToPackages.php
├── 2026-06-14-000008_AddAllowedGradesToSubscriptions.php
└── 2026-06-14-000009_AddSubTypeToPackages.php
```

---

## Migration & Seed

```bash
php spark migrate
php spark db:seed VortexEngineSeeder   # đăng ký module + seed 4 gói mặc định
```

---

## Tích hợp với module Lessons (tương lai)

```php
// Thêm filter subscription vào route group bài học
$routes->group('api/lessons', ['filter' => 'auth,subscription'], function ($routes) {
    // lesson routes
});
```

`SubscriptionFilter` cần được mở rộng để nhận `grade` của bài học và so với `subscription.allowed_grades`.

---

## Xử lý lỗi

Mọi exception trong `SubscriptionRepository::activate()` và `SubscriptionFilter` đều được:
1. Log qua `log_message('error', '[VortexEngine]...')` — CI4 built-in logging
2. Controller trả về HTTP 500 với message thân thiện
3. Filter fail-open (lỗi hệ thống không chặn học viên)
