# Module: Classroom

**Namespace:** `Modules\Classroom`  
**Slug:** `classroom`  
**Phiên bản:** 1.0.0  
**Ngày tạo:** 2026-06-17

## Mục đích

Module quản lý lớp học cho giáo viên và học sinh:
- Giáo viên tạo lớp, sinh mã lớp, đăng bài tập, chấm điểm.
- Học sinh nhập mã lớp để tham gia, xem bài tập, nộp bài.
- Hỗ trợ chế độ tự động duyệt (ON) hoặc giáo viên duyệt thủ công (OFF).

---

## Cấu trúc thư mục

```
modules/Classroom/
├── Controllers/
│   ├── ClassroomController.php        # CRUD lớp học (teacher)
│   ├── ClassroomMemberController.php  # Quản lý thành viên + join/leave (student)
│   ├── AssignmentController.php       # CRUD bài tập (teacher + student read)
│   ├── SubmissionController.php       # Nộp bài + chấm điểm
│   └── ClassroomPageController.php    # Render views
├── Models/
│   ├── ClassroomModel.php
│   ├── ClassroomMemberModel.php
│   ├── AssignmentModel.php
│   └── SubmissionModel.php
├── Repositories/
│   ├── ClassroomRepository.php
│   ├── ClassroomMemberRepository.php
│   ├── AssignmentRepository.php
│   └── SubmissionRepository.php
└── docs/
    └── Classroom.md
```

---

## Database

### Bảng `classrooms`

| Cột           | Kiểu          | Ghi chú                         |
|---------------|---------------|---------------------------------|
| id            | INT PK AI     |                                 |
| uuid          | VARCHAR(36)   | UNIQUE, dùng trong API/URL      |
| teacher_uuid  | VARCHAR(36)   | FK → users.uuid                 |
| name          | VARCHAR(255)  |                                 |
| description   | TEXT NULL     |                                 |
| code          | VARCHAR(12)   | UNIQUE, format: `ABC-XXXXX`     |
| subject       | VARCHAR(100)  | Môn học                         |
| grade         | TINYINT NULL  | Khối lớp 1–12                   |
| auto_approve  | TINYINT(1)    | 1=tự động, 0=thủ công           |
| is_active     | TINYINT(1)    | Soft delete                     |
| created_at    | DATETIME      |                                 |
| updated_at    | DATETIME      |                                 |

> **Lưu ý:** Bảng `users` dùng `uuid` làm primary key (không có integer `id`). Tất cả quan hệ với `users` trong module này đều dùng `*_uuid VARCHAR(36)`.

### Bảng `classroom_members`

| Cột           | Kiểu                            | Ghi chú                       |
|---------------|---------------------------------|-------------------------------|
| id            | INT PK AI                       |                               |
| classroom_id  | INT FK                          | classrooms.id                 |
| student_uuid  | VARCHAR(36)                     | FK → users.uuid               |
| status        | ENUM(pending,approved,rejected) | Trạng thái tham gia           |
| joined_at     | DATETIME NULL                   | Khi được approve              |
| created_at    | DATETIME                        |                               |
| UNIQUE        | (classroom_id, student_uuid)    |                               |

### Bảng `assignments`

| Cột          | Kiểu          | Ghi chú                    |
|--------------|---------------|----------------------------|
| id           | INT PK AI     |                            |
| uuid         | VARCHAR(36)   | UNIQUE                     |
| classroom_id | INT FK        | classrooms.id              |
| teacher_uuid | VARCHAR(36)   | FK → users.uuid            |
| title        | VARCHAR(255)  |                            |
| description  | TEXT NULL     | Nội dung đề bài            |
| due_date     | DATETIME NULL | Hạn nộp bài                |
| max_score    | SMALLINT      | Điểm tối đa, mặc định 100  |
| is_published | TINYINT(1)    | 0=nháp, 1=đã đăng          |
| created_at   | DATETIME      |                            |
| updated_at   | DATETIME      |                            |

### Bảng `assignment_submissions`

| Cột           | Kiểu                    | Ghi chú                       |
|---------------|-------------------------|-------------------------------|
| id            | INT PK AI               |                               |
| uuid          | VARCHAR(36)             | UNIQUE                        |
| assignment_id | INT FK                  | assignments.id                |
| student_uuid  | VARCHAR(36)             | FK → users.uuid               |
| content       | TEXT NULL               | Bài làm                       |
| file_url      | VARCHAR(500) NULL       | Link file đính kèm            |
| score         | INT NULL                | Điểm (0 → max_score)          |
| feedback      | TEXT NULL               | Nhận xét của giáo viên        |
| status        | ENUM(submitted,graded)  |                               |
| submitted_at  | DATETIME                |                               |
| graded_at     | DATETIME NULL           |                               |
| created_at    | DATETIME                |                               |
| UNIQUE        | (assignment_id, student_id) | Mỗi học sinh nộp 1 lần    |

---

## API Endpoints

Tất cả API cần JWT (header `Authorization: Bearer <token>`).  
Controllers tự kiểm tra role/ownership.

### Giáo viên (workspace_admin / super_admin)

| Method | URL                                                    | Controller::method              |
|--------|--------------------------------------------------------|---------------------------------|
| GET    | /api/classrooms                                        | ClassroomController::index      |
| POST   | /api/classrooms                                        | ClassroomController::create     |
| GET    | /api/classrooms/{uuid}                                 | ClassroomController::show       |
| PUT    | /api/classrooms/{uuid}                                 | ClassroomController::update     |
| DELETE | /api/classrooms/{uuid}                                 | ClassroomController::delete     |
| PUT    | /api/classrooms/{uuid}/toggle-approval                 | ClassroomController::toggleApproval |
| GET    | /api/classrooms/{uuid}/members                         | ClassroomMemberController::index |
| PUT    | /api/classrooms/{uuid}/members/{id}/approve            | ClassroomMemberController::approve |
| PUT    | /api/classrooms/{uuid}/members/{id}/reject             | ClassroomMemberController::reject |
| DELETE | /api/classrooms/{uuid}/members/{id}                    | ClassroomMemberController::remove |
| GET    | /api/classrooms/{uuid}/assignments                     | AssignmentController::index     |
| POST   | /api/classrooms/{uuid}/assignments                     | AssignmentController::create    |
| GET    | /api/assignments/{uuid}                                | AssignmentController::show      |
| PUT    | /api/assignments/{uuid}                                | AssignmentController::update    |
| DELETE | /api/assignments/{uuid}                                | AssignmentController::delete    |
| GET    | /api/assignments/{uuid}/submissions                    | SubmissionController::index     |
| PUT    | /api/submissions/{uuid}/grade                          | SubmissionController::grade     |

### Học sinh (user)

| Method | URL                                          | Controller::method                    |
|--------|----------------------------------------------|---------------------------------------|
| POST   | /api/classrooms/join                         | ClassroomMemberController::join       |
| GET    | /api/my-classrooms                           | ClassroomMemberController::myClassrooms |
| GET    | /api/my-classrooms/{uuid}                    | ClassroomMemberController::show       |
| GET    | /api/my-classrooms/{uuid}/assignments        | AssignmentController::index           |
| DELETE | /api/my-classrooms/{uuid}/leave              | ClassroomMemberController::leave      |
| POST   | /api/assignments/{uuid}/submit               | SubmissionController::submit          |
| GET    | /api/assignments/{uuid}/my-submission        | SubmissionController::mySubmission    |

---

## Web Pages (Admin)

| URL                                                      | View                                        |
|----------------------------------------------------------|---------------------------------------------|
| /admin/classrooms                                        | admin/classrooms/index.php                  |
| /admin/classrooms/{uuid}                                 | admin/classrooms/detail.php                 |
| /admin/classrooms/{cUuid}/assignments/{aUuid}            | admin/classrooms/assignment.php             |
| /admin/my-classrooms                                     | admin/my_classrooms/index.php               |
| /admin/my-classrooms/{uuid}                              | admin/my_classrooms/detail.php              |

---

## Mã lớp học

Format: `{3 ký tự đầu username giáo viên}-{5 ký tự random}`  
Ví dụ: `HOA-XBCD7`, `NGU-A12F3`

Sinh bằng `ClassroomRepository::generateCode()`:
- Prefix: 3 ký tự đầu của username (alphanumeric), padding `X` nếu thiếu.
- Suffix: `strtoupper(substr(bin2hex(random_bytes(3)), 0, 5))` — mỗi ký tự là hex (0-9, A-F).
- Loop cho đến khi code chưa tồn tại trong DB.

---

## Cơ chế duyệt thành viên

- `auto_approve = 1`: học sinh nhập mã → status = `approved` ngay lập tức.
- `auto_approve = 0`: học sinh nhập mã → status = `pending`, giáo viên vào lớp → tab "Học sinh" → duyệt từng người.
- Giáo viên có thể toggle qua `PUT /api/classrooms/{uuid}/toggle-approval`.

---

## Bảo mật

Tuân thủ `SECURITY_RULES.md`:
- SQL: parameterized queries, `$db->query("...", [$param])`, Query Builder.
- UUID: `bin2hex(random_bytes(16))`.
- Role check: mỗi controller method tự kiểm tra role và ownership.
- Views: `x-text` (không dùng `x-html`), `esc()` cho PHP output.

---

## Awesome Bar

Module đăng ký 2 mục vào Awesome Bar (qua migration):

| Label              | URL                    | Icon | Role                 |
|--------------------|------------------------|------|----------------------|
| Quản lý lớp học    | /admin/classrooms      | 🏫   | workspace_admin      |
| Lớp học của tôi    | /admin/my-classrooms   | 📚   | user                 |
