# Admin UI Rules - Quy tắc giao diện quản trị

> Tài liệu bắt buộc cho việc phát triển giao diện admin panel.

---

## 1. Stack công nghệ Frontend (Admin)

| Công nghệ | Mục đích | Ghi chú |
|-----------|----------|---------|
| CI4 View Layouts | Template engine, layout kế thừa | Dùng `$this->extend()`, `$this->section()` |
| Alpine.js 3.x | Reactivity, component nhẹ | File local `public/assets/js/alpine.min.js` |
| CSS thuần | Styling toàn bộ | Không dùng Tailwind, Bootstrap hay bất kỳ framework nào |

---

## 2. Cấu trúc thư mục

```
src/
├── app/Views/
│   ├── layouts/
│   │   └── admin.php              ← Layout chính admin
│   ├── admin/
│   │   ├── login.php              ← Trang đăng nhập
│   │   ├── dashboard.php          ← Trang tổng quan
│   │   ├── modules/
│   │   │   └── index.php          ← Quản lý module
│   │   ├── configs/
│   │   │   └── index.php          ← Cài đặt website
│   │   └── users/
│   │       └── index.php          ← Quản lý người dùng
│   └── components/
│       ├── sidebar.php            ← Sidebar navigation
│       ├── header.php             ← Top header bar
│       └── toast.php              ← Thông báo toast
├── public/assets/
│   ├── css/
│   │   └── admin.css              ← CSS chính cho admin
│   ├── js/
│   │   ├── alpine.min.js          ← Alpine.js source
│   │   └── admin.js               ← JS helper cho admin
│   └── img/
│       └── logo.svg               ← Logo hệ thống
```

---

## 3. Layout System

### Master Layout (`layouts/admin.php`)

```php
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('title') ?> - Admin</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <script defer src="/assets/js/alpine.min.js"></script>
</head>
<body>
    <!-- Sidebar + Content -->
    <?= $this->renderSection('content') ?>
</body>
</html>
```

### Trang con extend layout:

```php
<?= $this->extend('layouts/admin') ?>

<?= $this->section('title') ?>Dashboard<?= $this->endSection() ?>

<?= $this->section('content') ?>
    <div class="admin-wrapper">...</div>
<?= $this->endSection() ?>
```

---

## 4. Quy tắc CSS

### Naming Convention: BEM (Block__Element--Modifier)

```css
.sidebar {}
.sidebar__nav {}
.sidebar__nav-item {}
.sidebar__nav-item--active {}

.card {}
.card__header {}
.card__body {}

.btn {}
.btn--primary {}
.btn--danger {}
.btn--sm {}
```

### CSS Variables (theme):

```css
:root {
    /* Tham chiếu UI_BRAND_GUIDELINES.md */
    --color-primary: #2563EB;
    --color-primary-dark: #1D4ED8;
    --color-success: #10B981;
    --color-danger: #EF4444;
    --color-warning: #F59E0B;
    --color-bg: #F8FAFC;
    --color-surface: #FFFFFF;
    --color-text: #1E293B;
    --color-text-muted: #64748B;
    --color-border: #E2E8F0;
    --radius: 8px;
    --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
    --shadow-md: 0 4px 6px rgba(0,0,0,0.07);
    --transition: 0.2s ease;
}
```

### Responsive Breakpoints:

```css
/* Mobile first */
@media (min-width: 768px)  { /* Tablet */ }
@media (min-width: 1024px) { /* Desktop */ }
@media (min-width: 1280px) { /* Large desktop */ }
```

---

## 5. Quy tắc Alpine.js

### Component pattern:

```html
<div x-data="moduleManager()" x-init="loadModules()">
    <template x-for="module in modules" :key="module.id">
        <div class="module-card">
            <span x-text="module.name"></span>
            <button @click="toggle(module)" class="btn--sm">
                <span x-text="module.is_enabled ? 'Tắt' : 'Bật'"></span>
            </button>
        </div>
    </template>
</div>
```

### Quy tắc:

- Khai báo Alpine component bằng function (tách khỏi inline HTML)
- Đặt logic trong `public/assets/js/admin.js`
- Dùng `fetch()` gọi API, KHÔNG dùng axios/jQuery
- Token lấy từ `localStorage.getItem('access_token')`
- Khi token hết hạn → redirect về trang login

---

## 6. Quy tắc UX Admin

### Navigation:
- Sidebar cố định bên trái (desktop), hamburger menu (mobile)
- Active state rõ ràng (highlight menu hiện tại)
- Breadcrumb ở mỗi page

### Feedback:
- Toast notification sau mỗi action (success/error)
- Loading state cho mọi async operation
- Confirm dialog trước action nguy hiểm (xóa, khóa tài khoản)

### Form:
- Label rõ ràng cho mỗi input
- Validation message hiển thị inline dưới field
- Button disabled khi đang submit (chống double-click)

### Table:
- Zebra striping (hàng chẵn/lẻ khác màu)
- Hover highlight
- Pagination ở bottom
- Empty state khi không có dữ liệu

---

## 7. Quy tắc Security cho Frontend

- Token lưu `localStorage` (access_token)
- Refresh token tự động qua HttpOnly cookie (browser xử lý)
- XSS: dùng `esc()` trong View, `x-text` trong Alpine (auto-escape)
- KHÔNG dùng `x-html` với dữ liệu từ user
- KHÔNG log token ra console trong production
- Redirect về login khi nhận 401 từ API

---

## 8. Responsive Design (Chi tiết)

### Chiến lược: Mobile-First

Code CSS base cho mobile trước, sau đó dùng `min-width` media queries mở rộng cho tablet/desktop.

```css
/* Base = Mobile */
.content { padding: 16px; }

/* Tablet */
@media (min-width: 768px)  { .content { padding: 24px; } }

/* Desktop */
@media (min-width: 1024px) { .content { padding: 32px; } }

/* Large Desktop */
@media (min-width: 1280px) { .content { padding: 32px 40px; max-width: 1200px; } }
```

### Breakpoints

| Tên | Min-width | Thiết bị | Sidebar | Grid columns |
|-----|-----------|----------|---------|--------------|
| Mobile | 0 - 767px | iPhone, Android phone | Hidden (hamburger) | 1 cột |
| Tablet | 768px - 1023px | iPad, tablet | Hidden (hamburger) | 2 cột |
| Desktop | 1024px - 1279px | Laptop, PC | Fixed visible (250px) | 2-3 cột |
| Large | >= 1280px | Monitor lớn | Fixed visible (250px) | 3-4 cột |

### Hành vi theo breakpoint

#### Mobile (< 768px)
- Sidebar ẩn, mở bằng hamburger button + overlay
- Grid: 1 cột duy nhất
- Card: padding giảm (16px)
- Module card: stack dọc (info trên, toggle dưới)
- Config item: stack dọc (label trên, input dưới full-width)
- Table: font nhỏ hơn, scroll ngang nếu cần
- Toast: full-width, anchor top
- Button/Input: min-height 44px (touch-friendly, WCAG 2.1 AA)
- Breadcrumb: ẩn
- Login card: padding giảm, full-width

#### Tablet (768px - 1023px)
- Sidebar vẫn ẩn, hamburger menu
- Grid: 2 cột
- Card: padding trung bình (20px)
- Module card: horizontal (info trái, toggle phải)
- Config item: horizontal
- Table: font bình thường
- Toast: fixed-width, top-right
- Breadcrumb: hiển thị

#### Desktop (>= 1024px)
- Sidebar cố định bên trái (250px)
- Hamburger ẩn
- Grid: 2-3 cột
- Card: padding đầy đủ (24px)
- Content padding: 32px
- Tất cả component horizontal layout

#### Large Desktop (>= 1280px)
- Content max-width 1200px (tránh text quá rộng)
- Grid: lên đến 4 cột
- Spacious layout

### Touch Targets (Accessibility)

Trên mobile, mọi phần tử tương tác PHẢI có vùng tap tối thiểu **44x44px** (WCAG 2.1 Level AA):

```css
@media (max-width: 767px) {
    .btn { min-height: 44px; }
    .form-input { height: 44px; }
    .sidebar__nav-item a { min-height: 44px; }
    .toggle { width: 48px; height: 28px; }
}
```

### Safe Area (iOS Notch)

Hỗ trợ iPhone có notch/Dynamic Island:

```css
@supports (padding: env(safe-area-inset-bottom)) {
    .sidebar { padding-bottom: env(safe-area-inset-bottom); }
    .content { padding-bottom: calc(16px + env(safe-area-inset-bottom)); }
}
```

### Quy tắc khi code responsive

1. **Mobile-first**: Viết CSS cho mobile trước, dùng `min-width` để scale up
2. **Flexible units**: Dùng `%`, `vw`, `min()`, `clamp()` khi phù hợp
3. **Không fixed width** cho content containers (trừ sidebar desktop)
4. **Test trên thiết bị thật**: Chrome DevTools không thay thế test thực
5. **Touch vs Click**: Mobile cần padding lớn hơn cho vùng tap
6. **Scroll**: Cho phép horizontal scroll trên table (mobile), KHÔNG cho body
7. **Font size**: Không nhỏ hơn 12px trên mobile
8. **Modal/Dialog**: Full-screen trên mobile, centered card trên desktop

---

*Last updated: 2026-06-11*
