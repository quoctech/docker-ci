# Security Rules - CodeIgniter 4

> Tài liệu này là bắt buộc cho toàn bộ team. Mọi code PHẢI tuân thủ các rule dưới đây trước khi merge.

---

## 1. SQL Injection Prevention

### Rule: KHÔNG BAO GIỜ dùng raw query với input trực tiếp

**❌ Cấm:**

```php
$db->query("SELECT * FROM users WHERE email = '$email'");
$builder->where("status = $status");
```

**✅ Bắt buộc dùng Query Binding hoặc Query Builder:**

```php
// Query Binding (prepared statements)
$db->query("SELECT * FROM users WHERE email = ?", [$email]);

// Query Builder (auto-escape)
$builder->where('email', $email);
$builder->whereIn('id', $ids);

// Named binding
$db->query("SELECT * FROM users WHERE email = :email:", ['email' => $email]);
```

### Checklist:

- [ ] Tất cả query PHẢI dùng parameter binding hoặc Query Builder
- [ ] Không dùng `$db->query()` với string concatenation
- [ ] Nếu bắt buộc dùng raw query (stored procedure...), PHẢI escape thủ công: `$db->escape($value)`
- [ ] LIKE clause dùng: `$builder->like('column', $value)` — framework tự escape `%` và `_`

---

## 2. Cross-Site Request Forgery (CSRF)

### Rule: Mọi form POST/PUT/DELETE PHẢI có CSRF token

**Cấu hình (`app/Config/Security.php`):**

```php
public string $csrfProtection = 'session'; // Dùng session-based, KHÔNG dùng cookie
public string $tokenName = 'csrf_token_name';
public string $headerName = 'X-CSRF-TOKEN';
public bool $tokenRandomize = true; // Randomize token mỗi request
public int $expires = 7200; // 2 giờ
public bool $regenerate = true; // Regenerate sau mỗi request
```

**Bật CSRF filter (`app/Config/Filters.php`):**

```php
public array $globals = [
    'before' => [
        'csrf',
    ],
];
```

**Trong View (form):**

```php
<?= csrf_field() ?>
```

**AJAX request:**

```javascript
headers: {
    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
}
```

### Checklist:

- [ ] CSRF filter bật global cho tất cả POST/PUT/PATCH/DELETE
- [ ] Mọi form HTML có `<?= csrf_field() ?>`
- [ ] AJAX requests gửi token qua header `X-CSRF-TOKEN`
- [ ] Token regenerate mỗi request (`regenerate = true`)
- [ ] API endpoints dùng JWT/token auth thì EXCLUDE khỏi CSRF (nhưng PHẢI có auth middleware thay thế)

---

## 3. Cross-Site Scripting (XSS) Protection

### Rule: Mọi output PHẢI được escape. Không trust bất kỳ input nào.

**Output Escaping:**

```php
// Trong View - BẮT BUỘC dùng esc()
<?= esc($userInput) ?>              // HTML context (default)
<?= esc($value, 'attr') ?>          // Attribute context
<?= esc($value, 'js') ?>            // JavaScript context
<?= esc($value, 'css') ?>           // CSS context
<?= esc($value, 'url') ?>           // URL context
```

**Input Filtering:**

```php
// Validation rules
$rules = [
    'name'    => 'required|max_length[100]|alpha_numeric_space',
    'email'   => 'required|valid_email',
    'content' => 'required|max_length[5000]',
];

// Nếu cho phép HTML có chọn lọc, dùng HTMLPurifier (package bên thứ 3)
// KHÔNG dùng strip_tags() — không đủ an toàn
```

**Content Security Policy (`app/Config/ContentSecurityPolicy.php`):**

```php
public bool $reportOnly = false;
public string $defaultSrc = "'self'";
public string $scriptSrc = "'self'";
public string $styleSrc = "'self' 'unsafe-inline'"; // Hạn chế inline tối đa
public string $imgSrc = "'self' data:";
public string $frameSrc = "'none'";
public string $objectSrc = "'none'";
```

### Checklist:

- [ ] KHÔNG BAO GIỜ echo trực tiếp `<?= $variable ?>` mà không qua `esc()`
- [ ] Context-aware escaping: HTML, attribute, JS, CSS, URL
- [ ] Bật Content Security Policy
- [ ] Input validation TRƯỚC khi lưu DB
- [ ] Output escaping KHI hiển thị (defense in depth)
- [ ] Không dùng `{!! !!}` hoặc tương đương để render raw HTML từ user input

---

## 4. Cryptographic Hashing

### Rule: Dùng đúng algorithm cho đúng mục đích

**Password Hashing — BẮT BUỘC dùng `password_hash()`:**

```php
// Hash password
$hashedPassword = password_hash($password, PASSWORD_ARGON2ID, [
    'memory_cost' => 65536,  // 64MB
    'time_cost'   => 4,
    'threads'     => 3,
]);

// Fallback nếu server không hỗ trợ Argon2id
$hashedPassword = password_hash($password, PASSWORD_BCRYPT, [
    'cost' => 12, // Minimum 12
]);

// Verify
if (password_verify($inputPassword, $storedHash)) {
    // OK
}

// Rehash nếu cần (khi upgrade algorithm)
if (password_needs_rehash($storedHash, PASSWORD_ARGON2ID)) {
    $newHash = password_hash($inputPassword, PASSWORD_ARGON2ID);
    // Update DB
}
```

**❌ CẤM tuyệt đối:**

```php
md5($password);
sha1($password);
sha256($password);
hash('sha256', $password); // Cho password
```

**Token Generation (reset password, API key, remember me):**

```php
// PHẢI dùng cryptographically secure random
$token = bin2hex(random_bytes(32)); // 64 chars hex

// HOẶC dùng CI4 Encryption
$encrypter = service('encrypter');
$encrypted = $encrypter->encrypt($data);
```

**Data Integrity (checksum, HMAC):**

```php
// HMAC cho verify data integrity
$signature = hash_hmac('sha256', $payload, $secretKey);

// Timing-safe comparison
if (hash_equals($expectedSignature, $providedSignature)) {
    // Valid
}
```

### Checklist:

- [ ] Password: Argon2id (ưu tiên) hoặc Bcrypt cost >= 12
- [ ] KHÔNG dùng MD5/SHA cho password
- [ ] Token: `random_bytes()` hoặc `bin2hex(random_bytes(32))`
- [ ] So sánh hash/token dùng `hash_equals()` (timing-safe)
- [ ] Encryption key PHẢI lưu trong `.env`, KHÔNG hardcode

---

## 5. Authentication & Session Security

### Rule: Session phải được bảo vệ chống hijacking

**Session Config (`app/Config/Session.php`):**

```php
public string $driver = 'CodeIgniter\Session\Handlers\DatabaseHandler'; // KHÔNG dùng FileHandler trên prod
public string $cookieName = '__ci_session';
public int $expiration = 7200;
public bool $matchIP = true;         // Bind session theo IP
public int $timeToUpdate = 300;       // Regenerate ID mỗi 5 phút
```

**Cookie Config (`app/Config/Cookie.php`):**

```php
public bool $secure = true;     // Chỉ gửi qua HTTPS
public bool $httponly = true;    // Không cho JS truy cập
public string $samesite = 'Strict'; // Hoặc 'Lax'
```

### Checklist:

- [ ] Session ID regenerate định kỳ
- [ ] Cookie: Secure + HttpOnly + SameSite
- [ ] Session lưu database (không dùng file trên prod)
- [ ] Logout PHẢI destroy session hoàn toàn: `session()->destroy()`
- [ ] Brute force protection: rate limiting trên login

---

## 6. Input Validation & Sanitization

### Rule: Validate TRƯỚC khi xử lý. Reject mặc định, allow theo whitelist.

```php
$rules = [
    'username' => 'required|alpha_numeric|min_length[3]|max_length[30]',
    'email'    => 'required|valid_email|max_length[254]',
    'age'      => 'required|integer|greater_than[0]|less_than[150]',
    'file'     => 'uploaded[file]|max_size[file,2048]|mime_in[file,image/png,image/jpeg]',
];

if (! $this->validate($rules)) {
    return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
}

// Lấy data đã validate
$username = $this->request->getPost('username');
```

### Checklist:

- [ ] KHÔNG dùng `$_GET`, `$_POST`, `$_REQUEST` trực tiếp — dùng `$this->request->getGet()`, `getPost()`
- [ ] Mọi input PHẢI có validation rule
- [ ] File upload: check MIME type, size, extension
- [ ] Whitelist approach: chỉ cho phép ký tự/format đã biết
- [ ] Reject sớm, fail fast

---

## 7. HTTP Security Headers

### Rule: Response PHẢI có đầy đủ security headers

**Tạo filter `app/Filters/SecurityHeaders.php`:**

```php
namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class SecurityHeaders implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null) {}

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('X-Frame-Options', 'DENY');
        $response->setHeader('X-XSS-Protection', '0'); // Disable legacy XSS auditor
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->setHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');

        return $response;
    }
}
```

### Checklist:

- [ ] X-Content-Type-Options: nosniff
- [ ] X-Frame-Options: DENY (hoặc SAMEORIGIN nếu cần iframe)
- [ ] Strict-Transport-Security (HSTS) trên production
- [ ] Referrer-Policy
- [ ] Permissions-Policy
- [ ] Loại bỏ header `X-Powered-By` (config trong php.ini: `expose_php = Off`)

---

## 8. Error Handling & Information Disclosure

### Rule: Production KHÔNG được leak thông tin hệ thống

```php
// .env production
CI_ENVIRONMENT = production

// KHÔNG hiển thị chi tiết lỗi cho user
// CI4 tự xử lý: development = show errors, production = generic message
```

### Checklist:

- [ ] Production: `CI_ENVIRONMENT = production`
- [ ] KHÔNG log sensitive data (password, token, card number)
- [ ] Custom error pages cho 404, 500
- [ ] `display_errors = Off` trong php.ini production
- [ ] `expose_php = Off`
- [ ] Xóa file `phpinfo()` nếu có

---

## 9. File Upload Security

### Rule: KHÔNG trust file từ user

```php
$file = $this->request->getFile('avatar');

$rules = [
    'avatar' => [
        'uploaded[avatar]',
        'max_size[avatar,2048]',           // 2MB max
        'mime_in[avatar,image/png,image/jpeg,image/webp]',
        'ext_in[avatar,png,jpg,jpeg,webp]',
        'is_image[avatar]',                 // Verify actual image
    ],
];

// Di chuyển file với tên random
$newName = $file->getRandomName();
$file->move(WRITEPATH . 'uploads', $newName);
```

### Checklist:

- [ ] Validate MIME type + extension + content
- [ ] Rename file (random name), KHÔNG dùng tên gốc
- [ ] Lưu NGOÀI webroot (`writable/uploads/`, KHÔNG `public/`)
- [ ] Serve file qua controller (kiểm tra permission trước khi trả file)
- [ ] Giới hạn size
- [ ] KHÔNG cho phép upload `.php`, `.phtml`, `.sh`, `.exe`

---

## 10. API Security (nếu build REST API)

### Rule: Stateless authentication, rate limiting, validate everything

```php
// JWT hoặc API key authentication
// KHÔNG dùng session cho API

// Rate limiting filter
// Dùng Throttler của CI4
$throttler = service('throttler');
if ($throttler->check(md5($request->getIPAddress()), 60, MINUTE) === false) {
    return $this->response->setStatusCode(429)->setJSON([
        'error' => 'Too many requests'
    ]);
}
```

### Checklist:

- [ ] Auth: JWT hoặc API key (KHÔNG session-based)
- [ ] Rate limiting trên tất cả endpoints
- [ ] Validate request body (JSON schema)
- [ ] Response KHÔNG chứa thông tin thừa (stack trace, DB query)
- [ ] CORS config chặt — chỉ allow domain cần thiết
- [ ] Versioning API (`/api/v1/...`)

---

## 11. Dependency Security

### Checklist:

- [ ] Chạy `composer audit` định kỳ (kiểm tra CVE)
- [ ] Lock version trong `composer.json` (dùng exact version, không dùng `^` hoặc `*`)
- [ ] Update dependencies hàng tháng
- [ ] Review package trước khi cài — check maintainer, stars, last update
- [ ] KHÔNG cài package không cần thiết

---

## 12. Database Security

### Checklist:

- [ ] DB user chỉ có quyền cần thiết (SELECT, INSERT, UPDATE, DELETE) — KHÔNG dùng root
- [ ] KHÔNG grant `DROP`, `ALTER`, `GRANT` cho app user
- [ ] Connection qua internal network (docker network), KHÔNG expose port DB ra public
- [ ] Backup mã hóa
- [ ] Sensitive data (PII) mã hóa at-rest nếu cần

---

## Tóm tắt Priority

| Priority | Rule | Impact |
|----------|------|--------|
| 🔴 Critical | SQL Injection Prevention | Data breach |
| 🔴 Critical | Password Hashing (Argon2id/Bcrypt) | Account takeover |
| 🔴 Critical | Input Validation | Multiple attack vectors |
| 🟠 High | CSRF Protection | Unauthorized actions |
| 🟠 High | XSS Prevention | Session hijacking, phishing |
| 🟠 High | Session Security | Account hijacking |
| 🟡 Medium | Security Headers | Defense in depth |
| 🟡 Medium | File Upload Security | RCE, storage abuse |
| 🟡 Medium | Error Handling | Information disclosure |
| 🟢 Standard | Dependency Audit | Supply chain attack |

---

## Enforcement

- **Code Review**: Mọi PR PHẢI được review security checklist trước khi merge
- **CI/CD**: Chạy `composer audit` + static analysis trong pipeline
- **Testing**: Viết test cho auth flow, input validation, access control
- **Monitoring**: Log failed login attempts, suspicious input patterns

---

*Last updated: 2026-06-11*
*Version: 1.0*
