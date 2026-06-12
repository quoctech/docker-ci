# UI Brand Guidelines - EduGame Platform

> Hướng dẫn thiết kế thương hiệu cho giao diện quản trị.

---

## 1. Color Palette

### Primary Colors

| Tên | Hex | Dùng cho |
|-----|-----|----------|
| Primary | `#2563EB` | Button chính, link, active state |
| Primary Dark | `#1D4ED8` | Hover state, focus ring |
| Primary Light | `#DBEAFE` | Background highlight, badge |

### Semantic Colors

| Tên | Hex | Dùng cho |
|-----|-----|----------|
| Success | `#10B981` | Thông báo thành công, status active |
| Danger | `#EF4444` | Lỗi, xóa, khóa tài khoản |
| Warning | `#F59E0B` | Cảnh báo, pending state |
| Info | `#3B82F6` | Thông tin, tooltip |

### Neutral Colors

| Tên | Hex | Dùng cho |
|-----|-----|----------|
| Background | `#F8FAFC` | Nền trang chính |
| Surface | `#FFFFFF` | Card, modal, sidebar |
| Text | `#1E293B` | Văn bản chính |
| Text Muted | `#64748B` | Văn bản phụ, placeholder |
| Border | `#E2E8F0` | Viền, separator |
| Sidebar BG | `#1E293B` | Nền sidebar (tối) |
| Sidebar Text | `#CBD5E1` | Text trong sidebar |
| Sidebar Active | `#2563EB` | Highlight menu active |

---

## 2. Typography

### Font Stack

```css
font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
```

Không import Google Fonts → tối ưu tốc độ, dùng system font.

### Scale

| Cấp | Size | Weight | Dùng cho |
|-----|------|--------|----------|
| H1 | 24px | 700 | Page title |
| H2 | 20px | 600 | Section title |
| H3 | 16px | 600 | Card title |
| Body | 14px | 400 | Nội dung chính |
| Small | 12px | 400 | Caption, label phụ |

### Line Height: `1.5` (body), `1.25` (heading)

---

## 3. Spacing System

Sử dụng bội số 4px:

| Token | Giá trị | Dùng cho |
|-------|---------|----------|
| `--space-1` | 4px | Khoảng cách nhỏ nhất |
| `--space-2` | 8px | Padding nội bộ |
| `--space-3` | 12px | Gap giữa phần tử gần nhau |
| `--space-4` | 16px | Padding card, margin phần tử |
| `--space-5` | 20px | Section gap |
| `--space-6` | 24px | Card padding lớn |
| `--space-8` | 32px | Section margin |
| `--space-10` | 40px | Page padding |

---

## 4. Components

### Button

```
┌─────────────────────┐
│   [Icon] Label      │  height: 40px, padding: 0 16px
└─────────────────────┘
    border-radius: 6px
    font-weight: 500
    font-size: 14px
    transition: 0.2s ease
```

Variants:
- **Primary**: bg `#2563EB`, text `#FFF`, hover `#1D4ED8`
- **Secondary**: bg `#F1F5F9`, text `#475569`, hover `#E2E8F0`
- **Danger**: bg `#FEE2E2`, text `#DC2626`, hover `#FECACA`
- **Ghost**: bg transparent, text `#475569`, hover bg `#F1F5F9`
- **Small**: height 32px, font 12px, padding 0 12px

### Card

```
┌──────────────────────────┐
│  Card Header             │  bg: #F8FAFC, border-bottom
├──────────────────────────┤
│                          │
│  Card Body               │  padding: 24px
│                          │
└──────────────────────────┘
    border: 1px solid #E2E8F0
    border-radius: 8px
    box-shadow: 0 1px 2px rgba(0,0,0,0.05)
```

### Input

```
┌──────────────────────────┐
│  Label                   │  font-size: 13px, font-weight: 500
├──────────────────────────┤
│  ┌────────────────────┐  │
│  │ Placeholder...     │  │  height: 40px, border-radius: 6px
│  └────────────────────┘  │  border: 1px solid #E2E8F0
│  Helper text / Error     │  focus: ring 2px #2563EB
└──────────────────────────┘
```

### Toggle Switch

```
Bật:  [====●]  bg: #2563EB
Tắt:  [●====]  bg: #CBD5E1
Size: 44px x 24px, thumb: 20px
```

### Badge / Status

```
┌──────┐
│ Text │  padding: 2px 8px, border-radius: 9999px, font-size: 12px
└──────┘
```
- Active: bg `#D1FAE5`, text `#065F46`
- Locked: bg `#FEE2E2`, text `#991B1B`
- Pending: bg `#FEF3C7`, text `#92400E`
- Core: bg `#DBEAFE`, text `#1E40AF`

### Toast Notification

```
┌─────────────────────────────────┐
│ [✓] Message thành công          │  Position: top-right
└─────────────────────────────────┘  Auto-dismiss: 3 giây
    border-radius: 8px
    box-shadow: 0 4px 12px rgba(0,0,0,0.15)
```
- Success: left-border `#10B981`
- Error: left-border `#EF4444`
- Warning: left-border `#F59E0B`

---

## 5. Layout Grid

### Admin Layout

```
┌──────────────────────────────────────────────┐
│ [≡]  EduGame Admin        [Avatar] Admin ▼   │  Header: h=56px
├────────┬─────────────────────────────────────┤
│        │                                     │
│  Menu  │         Content Area                │
│  Item  │                                     │
│  Item  │   ┌───────────────────────────┐     │
│  Item  │   │  Card / Table / Form      │     │
│  Item  │   │                           │     │
│        │   └───────────────────────────┘     │
│        │                                     │
├────────┴─────────────────────────────────────┤
│ Footer: © 2026 EduGame Platform              │
└──────────────────────────────────────────────┘

Sidebar: width=250px (desktop), hidden (mobile)
Content: padding=32px
```

### Login Page

```
┌──────────────────────────────────────────────┐
│                                              │
│              ┌───────────────┐               │  Centered card
│              │    [Logo]     │               │  max-width: 400px
│              │               │               │  bg: white
│              │  Identifier   │               │  shadow-lg
│              │  [________]   │               │
│              │               │               │
│              │  Password     │               │
│              │  [________]   │               │
│              │               │               │
│              │  [Đăng nhập]  │               │
│              │               │               │
│              └───────────────┘               │
│                                              │
└──────────────────────────────────────────────┘
Background: gradient hoặc pattern nhẹ
```

---

## 6. Iconography

Không dùng icon library nặng. Sử dụng:
- HTML entities / Unicode symbols cho icon đơn giản
- Inline SVG cho icon quan trọng (logo, menu)

Ví dụ:
- Menu: `☰` hoặc SVG hamburger
- Check: `✓`
- Close: `✕`
- Arrow: `→` `←` `↑` `↓`
- Settings: `⚙`
- User: `👤`
- Logout: `↪`

---

## 7. Animation & Transition

- Duration: `0.2s` (micro-interaction), `0.3s` (modal/drawer)
- Easing: `ease` hoặc `cubic-bezier(0.4, 0, 0.2, 1)`
- Sử dụng: hover, focus, toggle, toast appear/disappear
- KHÔNG animation phức tạp, giữ clean và nhanh

---

## 8. Responsive Design

### Breakpoints & Behavior

| Device | Width | Sidebar | Grid | Content Padding |
|--------|-------|---------|------|-----------------|
| Mobile | 0 - 767px | Hidden + Hamburger | 1 col | 16px |
| Tablet | 768px - 1023px | Hidden + Hamburger | 2 col | 24px |
| Desktop | 1024px - 1279px | Fixed 250px | 2-3 col | 32px |
| Large | >= 1280px | Fixed 250px | 3-4 col | 32px 40px |

### Component Adaptation

| Component | Mobile | Tablet | Desktop |
|-----------|--------|--------|---------|
| Module Card | Stack vertical | Horizontal | Horizontal |
| Config Item | Stack vertical | Horizontal | Horizontal |
| Grid | 1 column | 2 columns | 3 columns |
| Table | Scroll horizontal, 12px font | Normal | Normal |
| Toast | Full width, top center | Fixed width, top-right | Fixed width, top-right |
| Modal | Full screen | Centered card 480px | Centered card 480px |
| Button | 44px height (touch) | 40px height | 40px height |
| Input | 44px height (touch) | 40px height | 40px height |
| Login Card | Full width, 20px padding | 400px max, 28px padding | 400px max, 32px padding |
| Breadcrumb | Hidden | Visible | Visible |
| Header | Compact, 16px padding | 20px padding | 24px padding |

### Typography Scale (Responsive)

| Element | Mobile | Tablet | Desktop |
|---------|--------|--------|---------|
| Page Title (H1) | 20px | 22px | 24px |
| Section Title (H2) | 18px | 19px | 20px |
| Card Title (H3) | 15px | 15px | 16px |
| Body | 14px | 14px | 14px |
| Small/Caption | 12px | 12px | 12px |

### Spacing Scale (Responsive)

| Context | Mobile | Tablet | Desktop |
|---------|--------|--------|---------|
| Content padding | 16px | 24px | 32px |
| Card padding | 16px | 20px | 24px |
| Grid gap | 16px | 20px | 24px |
| Section margin | 20px | 24px | 32px |

---

## 9. Dark Mode (Tùy chọn, phase sau)

Không triển khai dark mode ở phase hiện tại.
Nhưng thiết kế CSS variables để dễ thêm sau:

```css
[data-theme="dark"] {
    --color-bg: #0F172A;
    --color-surface: #1E293B;
    --color-text: #F1F5F9;
    --color-border: #334155;
}
```

---

*Last updated: 2026-06-11*
