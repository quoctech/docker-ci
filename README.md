# CodeIgniter 4 Docker Development Environment

Môi trường phát triển local cho CodeIgniter 4 sử dụng Docker với:
- **PHP 8.5-FPM** (OPcache + JIT enabled)
- **Nginx** (Alpine)
- **MariaDB 11**

## Cấu trúc thư mục

```
ci4/
├── docker/
│   ├── php/
│   │   ├── Dockerfile        # PHP 8.5-FPM image
│   │   ├── local.ini         # PHP config
│   │   ├── opcache.ini       # OPcache + JIT config
│   │   └── www.conf          # PHP-FPM pool config
│   ├── nginx/
│   │   └── default.conf      # Nginx vhost config
│   └── mariadb/
│       └── init/             # SQL files chạy khi init DB
├── src/                      # CodeIgniter 4 source code
├── docker-compose.yml
├── Makefile
├── .env.example
├── .gitignore
└── README.md
```

## Bắt đầu

### 1. Build containers

```bash
make build
```

### 2. Start containers

```bash
make up
```

### 3. Cài đặt CodeIgniter 4

```bash
make install
```

### 4. Cấu hình Database

Sửa file `src/.env` (được tạo sau khi install):

```ini
database.default.hostname = db
database.default.database = ci4_db
database.default.username = ci4_user
database.default.password = ci4_pass
database.default.DBDriver = MySQLi
database.default.port = 3306
```

### 5. Truy cập ứng dụng

- **App**: http://localhost:8080
- **MariaDB**: localhost:3307

## Các lệnh hữu ích

| Lệnh | Mô tả |
|-------|--------|
| `make up` | Khởi động containers |
| `make down` | Dừng containers |
| `make build` | Build lại containers |
| `make install` | Cài CodeIgniter 4 |
| `make shell` | Vào shell PHP container |
| `make db` | Vào MariaDB shell |
| `make logs` | Xem logs |
| `make restart` | Restart containers |
| `make spark migrate` | Chạy migration |

## Performance (OPcache + JIT)

OPcache được cấu hình sẵn cho local dev:
- `validate_timestamps=1` + `revalidate_freq=0`: phát hiện thay đổi file ngay lập tức
- JIT enabled (`opcache.jit=1255`): tăng tốc thực thi PHP
- 128MB JIT buffer + 128MB OPcache memory

---

## Production

### Khác biệt so với Dev

| | Development | Production |
|---|---|---|
| Code | Volume mount (live reload) | COPY vào image (immutable) |
| OPcache | validate_timestamps=1 | validate_timestamps=0 |
| JIT buffer | 128MB | 256MB |
| Errors | Display on screen | Log to file |
| PHP-FPM | `pm = dynamic`, 20 children | `pm = static`, 50 children |
| Nginx | Basic | Gzip + Security headers + Caching |
| DB port | Exposed (3306) | Không expose |
| Image | Debian-based | Alpine (nhẹ hơn) |
| Health checks | Không | Có |
| Resource limits | Không | Có |

### Deploy Production

```bash
# 1. Tạo file env
cp .env.prod.example .env.prod
# Sửa passwords trong .env.prod

# 2. Build
make prod-build

# 3. Start
make prod-up

# 4. Check status
make prod-status
```

### Lệnh Production

| Lệnh | Mô tả |
|-------|--------|
| `make prod-build` | Build production images |
| `make prod-up` | Start production |
| `make prod-down` | Stop production |
| `make prod-logs` | Xem logs production |
| `make prod-status` | Kiểm tra trạng thái |

---

## Lưu ý

- Source code nằm trong `src/`, được mount vào container qua volume (dev) hoặc COPY vào image (prod)
- MariaDB data được persist qua Docker volume `db_data`
- Đặt file `.sql` vào `docker/mariadb/init/` để tự động chạy khi tạo DB lần đầu
- Production **không expose port DB** ra ngoài — chỉ truy cập qua internal network
- Nhớ đổi password trong `.env.prod` trước khi deploy
