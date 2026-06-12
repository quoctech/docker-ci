# Product Requirement Document (PRD)

## 📌 Tổng Quan Dự Án
Là nền tảng giáo dục game hóa bám sát chương trình Sách giáo khoa (Toán, Văn, Anh) từ lớp 1 đến lớp 9. Hệ thống tối ưu hóa chi phí bằng cách quét PDF (Sách giáo khoa) SGK và cache cấu trúc game JSON thông qua AI đúng 1 lần duy nhất. 
Dự án được phát triển theo tư duy **Backend-First**, kiến trúc hệ thống dạng **Pluggable Modules** (Bật/Tắt, cài/gỡ linh hoạt thông qua Admin Master), các module nằm trong thư mục modules, chạy trên hạ tầng Docker (PHP 8.5, Nginx, Redis, MariaDB, Node.js).

---

## 🛠️ Stack Công Nghệ & Hạ Tầng Backend
*   **Web Server:** Nginx (gác cổng, điều hướng request).
*   **API Engine:** CodeIgniter 4 (PHP 8.5 FPM) - Đảm nhận logic core, CRUD, phân quyền, dòng tiền.
*   **Async/Worker Engine:** Node.js - Đảm nhận bóc tách PDF, gọi AI Gemini, sinh file ảnh, xử lý tác vụ nặng bất đồng bộ.
*   **Database:** MariaDB (Lưu trữ dữ liệu quan hệ, hỗ trợ kiểu dữ liệu JSON bản bốc).
*   **Caching & Queue:** Redis - Quản lý Session JWT, Rate Limiting, Leaderboard (Sorted Sets) và Cache trạng thái bật/tắt Module.

---

## 🔑 Phân Cấp Quyền Hệ Thống (RBAC)
1.  **Super Admin (Hệ thống):** Quyền tối cao, quản trị toàn bộ hệ thống, cấu hình website, bật/tắt module.
2.  **Workspace Admin (Giáo viên / Chủ trung tâm):** Sở hữu không gian làm việc riêng, quản lý lớp học, học sinh, xem báo cáo và ví hoa hồng.
3.  **User (Phụ huynh / Học sinh):** Đăng nhập làm bài tập, cày game, xem hạn mức gói.

---

## 📦 Danh Sách Các Module Core Của Hệ Thống

### 1. Module System Admin (Module Quản Trị Tối Cao)
*   **Module Registry (Cài/Gỡ Module):** Giao diện bật/tắt (mắt thần) các module. Trạng thái module (`is_enabled`) được lưu ở DB và đồng bộ lên **Redis Cache** để Dynamic Routing trong CI4 tự động đóng/mở API mà không gây sập app.
*   **Website Config Manager:** Quản lý toàn bộ cấu hình động của hệ thống (Meta tag, hotline, cấu hình cổng thanh toán VietQR, cấu hình hạn mức dùng thử mặc định).
*   **User & Workspace Management:** Kiểm soát, khóa/mở tài khoản của giáo viên và phụ huynh khi có dấu hiệu vi phạm hoặc gian lận.

### 2. Module Auth & Security (Xử lý riêng biệt, bảo mật cao)
*   **Cơ chế mã hóa:** Sử dụng **JWT (JSON Web Token)** lưu tại Client bằng **HttpOnly Cookie** chống tấn công XSS.
*   **Phiên làm việc (Session Control):** Quản lý Token Token Blacklist/Whitelist trên **Redis**. Khi Admin khóa tài khoản hoặc người dùng đổi mật khẩu, Redis lập tức xóa Key, hủy phiên đăng nhập ngay tại chỗ.
*   **Rate Limiting Filter:** Cấu hình chặn spam API (tối đa 1 request SMS/Đăng ký trong 5 giây, tối đa 60 request API/phút cho 1 IP) tránh bị scan lỗ hổng bằng công cụ tự động.

### 3. Module Profile & Subscription (Quản lý hạn mức gói & Dòng tiền)
*   **Hạn gói (Expiration Engine):** Trường dữ liệu `expired_at` và `status` (TRIAL, VIP, EXPIRED) gắn với học sinh.
*   **Middleware gác cổng:** Filters của CI4 tự động quét tình trạng gói của học sinh trước khi cho phép lấy dữ liệu bài học. Nếu hết hạn, trả về mã lỗi `402 Payment Required` để chặn luồng học.
*   **Cổng VietQR Tự Động:** API webhook kết nối với Ngân hàng/PayOS. Khi phụ huynh chuyển khoản đúng cú pháp, CI4 tự động cộng thêm 30 ngày vào `expired_at` và kích hoạt lại tài khoản trong 0.5 giây.

### 4. Module Game Engine & AI Converter (Trái tim nội dung)
*   **PDF-to-JSON Pipeline (Node.js đảm nhận):** Bóc tách văn bản từ file PDF Sách giáo khoa gửi lên $\rightarrow$ Đẩy qua API Gemini Flash $\rightarrow$ Ép khuôn JSON Schema trả về cấu trúc bài học cố định.
*   **Cache Bài Học:** Lưu cục JSON bài học vào bảng `lessons_cache` (MariaDB). API của CI4 chỉ làm nhiệm vụ bốc cục JSON này trả về cho Client, chi phí API Gemini cho học sinh bằng 0đ.
*   **Gamification State:** Quản lý tiến trình của học sinh (Kinh nghiệm - EXP, Cấp độ - Level, Danh hiệu Avatar, Khung viền lấp lánh). Điểm số phiên chơi tương tác lưu tạm ở Redis trước khi sync về MariaDB cuối ngày.
*   **Redis Leaderboard:** Sử dụng `ZADD` và `ZREVRANGE` để tính toán bảng xếp hạng học sinh theo tuần/tháng của từng lớp học trong tích tắc.

### 5. Module Workspace & Class Management (Quản lý không gian làm việc)
*   **Kiến trúc Multi-Workspace:** Giáo viên đăng ký sẽ được cấp 1 Workspace riêng biệt.
*   **Quản lý lớp học:** Giáo viên tạo lớp, hệ thống tự sinh `Class_Code` (Ví dụ: `COHUONG2026`). Học sinh nhập mã này lúc đăng ký sẽ tự động map vào Workspace của giáo viên đó.
*   **Bảng Vàng Tuyên Dương:** Xuất dữ liệu Top học sinh chăm học trong tuần thành file ảnh trực quan để giáo viên tải về ném vào nhóm phụ huynh (Node.js hỗ trợ render ảnh từ HTML).

### 6. Module Affiliate & Commission (Tính % hoa hồng Giáo viên)
*   **Affiliate Tracker:** Ghi nhận và khóa mối quan hệ giữa học sinh và giáo viên giới thiệu dựa trên `Class_Code` hoặc mã giới thiệu riêng.
*   **Logic tính toán dòng tiền (Khi nạp tiền thành công):**
    *   *Tháng đầu tiên:* Cộng $15\% - 20\%$ giá trị gói vào ví giáo viên (Ví dụ: 110,000đ).
    *   *Các tháng tiếp theo:* Cộng đều đặn $7\% - 10\%$ giá trị gói khi phụ huynh tự động gia hạn (Ví dụ: 38,500đ - 55,000đ).
*   **Teacher Wallet Ledger:** Bảng log chi tiết dòng tiền ra vào của giáo viên để đảm bảo tính minh bạch, phục vụ đối soát rút tiền cuối tháng.

---

## 📈 Lộ Trình Triển Khai Backend-First (Sắp xếp theo thứ tự ưu tiên code)

*   **Giai đoạn 1 (Nền tảng):** Setup DB Schema $\rightarrow$ Viết Module Auth (JWT + Redis) $\rightarrow$ Viết Module System Admin & Website Config.
*   **Giai đoạn 2 (Dòng tiền):** Viết Module Profile & Subscription $\rightarrow$ Tích hợp Webhook VietQR nạp tiền tự động.
*   **Giai đoạn 3 (Nội dung):** Viết Tool Node.js bóc PDF sang JSON $\rightarrow$ Lưu cache bài học vào DB $\rightarrow$ Viết API GameEngine trả về bài học và lưu EXP.
*   **Giai đoạn 4 (Mở rộng phân phối):** Viết Module Workspace quản lý lớp cho giáo viên $\rightarrow$ Viết Module Affiliate tự động nhảy tiền hoa hồng cho các cô.