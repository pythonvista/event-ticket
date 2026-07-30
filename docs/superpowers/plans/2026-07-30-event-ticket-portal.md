# Event Ticket Booking Portal Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an educational PHP/MySQL event ticket booking portal (sample event: Design Week Lagos 2026) with user registration, booking, Paystack test-mode payment (with Demo Pay fallback), QR-code e-tickets, and an admin panel.

**Architecture:** Classic page-per-file procedural PHP on XAMPP. Shared includes for DB/config, layout, and auth guards. Bootstrap 5, QRCode.js, and Paystack Inline via CDN. MySQL accessed only through PDO prepared statements.

**Tech Stack:** PHP 8 (XAMPP), MySQL/MariaDB, Bootstrap 5.3 (CDN), QRCode.js (CDN), Paystack Inline JS (CDN).

## Global Constraints

- Spec: `docs/superpowers/specs/2026-07-30-event-ticket-portal-design.md` — follow it exactly.
- Project root is `F:\projects\event-ticket`, served at `http://localhost/event-ticket/` via a junction into XAMPP htdocs (created in Task 1).
- PHP CLI: `C:\xampp\php\php.exe`. MySQL CLI: `C:\xampp\mysql\bin\mysql.exe -u root` (no password).
- `BASE_URL` is `/event-ticket/`. Database name `event_ticket_db`, user `root`, empty password.
- Every SQL statement uses PDO prepared statements. Every dynamic output goes through `e()` (htmlspecialchars). Passwords only via `password_hash()`/`password_verify()`.
- Session keys: `$_SESSION['user_id']`, `$_SESSION['user_name']` for users; `$_SESSION['admin_id']`, `$_SESSION['admin_name']` for admin. Never mix them.
- Money renders with `naira()` helper: `₦` + `number_format(x, 2)`.
- Booking reference format: `sprintf('TKT-%s-%05d', date('Y'), $bookingId)`.
- Every user-facing page: `require_once __DIR__ . '/config/db.php';` first (guards use it too), sets `$pageTitle`, then includes `includes/header.php` … `includes/footer.php`.
- Admin pages live in `admin/` and include `../includes/admin_auth.php` and `../includes/admin_header.php`.
- No PHP frameworks, no Composer, no build tools. CDN links exactly as given in Task 2.
- After creating/editing any PHP file, run `& C:\xampp\php\php.exe -l <file>` — must print "No syntax errors".
- Commit at the end of every task with the message given in the task.

---

### Task 1: Environment, config, database schema + seed

**Files:**
- Create: `config/db.php`
- Create: `database.sql`
- Create: `assets/img/dwl2026.svg`, `assets/img/techfest.svg`, `assets/img/afrobeat.svg`
- Create: `.gitignore`

**Interfaces:**
- Produces: `$pdo` (PDO, ERRMODE_EXCEPTION, FETCH_ASSOC), constants `BASE_URL`, `PAYSTACK_PUBLIC_KEY`, `PAYSTACK_SECRET_KEY`, helpers `e(string): string`, `redirect(string path relative to BASE_URL): never`, `naira(float): string`, `flash(?string): ?string` (call with a message to set, call with no args to consume). Database `event_ticket_db` with tables `users`, `admin`, `events`, `bookings`, `payments`, seeded admin (`admin`/`admin123`) and 3 events.

- [ ] **Step 1: Make the project reachable by Apache and check services**

```powershell
if (-not (Test-Path C:\xampp\htdocs\event-ticket)) { New-Item -ItemType Junction -Path C:\xampp\htdocs\event-ticket -Target F:\projects\event-ticket }
& C:\xampp\xampp_start.exe 2>$null; Get-Process -Name httpd,mysqld -ErrorAction SilentlyContinue
```

If `httpd`/`mysqld` are not running, start them: `Start-Process C:\xampp\apache_start.bat` and `Start-Process C:\xampp\mysql_start.bat` (or ask the user to start Apache and MySQL in the XAMPP Control Panel). Verify with `curl.exe -s -o NUL -w "%{http_code}" http://localhost/` → expect `200` or `302`.

- [ ] **Step 2: Write `.gitignore`**

```gitignore
assets/img/evt_*
```

(Admin-uploaded event images are runtime data, not source.)

- [ ] **Step 3: Write `config/db.php`**

```php
<?php
// Central config: session, database connection, Paystack keys, small helpers.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('BASE_URL', '/event-ticket/');
// Paystack TEST keys. Leave blank to enable the "Demo Pay" fallback button.
define('PAYSTACK_PUBLIC_KEY', '');
define('PAYSTACK_SECRET_KEY', '');

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=event_ticket_db;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $ex) {
    exit('<h2 style="font-family:sans-serif;text-align:center;margin-top:20vh">Site under maintenance. Please try again later.</h2>');
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

function naira($amount): string
{
    return '₦' . number_format((float) $amount, 2);
}

function flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'] = $message;
        return null;
    }
    $current = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $current;
}
```

- [ ] **Step 4: Generate the admin password hash**

```powershell
& C:\xampp\php\php.exe -r "echo password_hash('admin123', PASSWORD_DEFAULT);"
```

Copy the printed hash (starts with `$2y$`) — it goes into `database.sql` in the next step where `__ADMIN_HASH__` appears.

- [ ] **Step 5: Write `database.sql`** (replace `__ADMIN_HASH__` with the hash from Step 4)

```sql
CREATE DATABASE IF NOT EXISTS event_ticket_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE event_ticket_db;

DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS admin;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    image VARCHAR(255) NOT NULL DEFAULT '',
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    venue VARCHAR(150) NOT NULL,
    regular_price DECIMAL(10,2) NOT NULL,
    vip_price DECIMAL(10,2) NOT NULL,
    vvip_price DECIMAL(10,2) NOT NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;

CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_ref VARCHAR(20) NULL UNIQUE,
    user_id INT NOT NULL,
    event_id INT NOT NULL,
    ticket_type ENUM('regular','vip','vvip') NOT NULL,
    quantity INT NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (event_id) REFERENCES events(id)
) ENGINE=InnoDB;

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    paystack_reference VARCHAR(100) NOT NULL,
    status ENUM('pending','success','failed') NOT NULL DEFAULT 'pending',
    paid_at DATETIME NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id)
) ENGINE=InnoDB;

INSERT INTO admin (username, password) VALUES ('admin', '__ADMIN_HASH__');

INSERT INTO events (name, description, image, event_date, event_time, venue, regular_price, vip_price, vvip_price) VALUES
('Design Week Lagos 2026', 'Africa''s biggest celebration of design — talks, exhibitions, and workshops from leading designers across the continent.', 'dwl2026.svg', '2026-10-21', '10:00:00', 'Federal Palace Hotel, Victoria Island, Lagos', 5000.00, 15000.00, 30000.00),
('Lagos Tech Fest 2026', 'A full-day festival of technology, startups, and innovation with keynotes, demos, and networking.', 'techfest.svg', '2026-11-14', '09:00:00', 'Landmark Centre, Oniru, Lagos', 3000.00, 10000.00, 20000.00),
('Afrobeat Live Concert', 'An unforgettable night of live Afrobeat performances from top Nigerian artists.', 'afrobeat.svg', '2026-12-18', '19:00:00', 'Eko Hotel & Suites, Victoria Island, Lagos', 8000.00, 20000.00, 50000.00);
```

- [ ] **Step 6: Write the three SVG event banners**

`assets/img/dwl2026.svg`:

```svg
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="400" viewBox="0 0 800 400">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1a1a2e"/><stop offset="1" stop-color="#e94560"/></linearGradient></defs>
  <rect width="800" height="400" fill="url(#g)"/>
  <text x="400" y="185" fill="#ffffff" font-family="Arial, sans-serif" font-size="46" font-weight="bold" text-anchor="middle">Design Week Lagos</text>
  <text x="400" y="245" fill="#ffd460" font-family="Arial, sans-serif" font-size="60" font-weight="bold" text-anchor="middle">2026</text>
</svg>
```

`assets/img/techfest.svg` — same structure, gradient `#0f3460 → #16c79a`, texts `Lagos Tech Fest` / `2026`.

```svg
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="400" viewBox="0 0 800 400">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#0f3460"/><stop offset="1" stop-color="#16c79a"/></linearGradient></defs>
  <rect width="800" height="400" fill="url(#g)"/>
  <text x="400" y="185" fill="#ffffff" font-family="Arial, sans-serif" font-size="46" font-weight="bold" text-anchor="middle">Lagos Tech Fest</text>
  <text x="400" y="245" fill="#ffd460" font-family="Arial, sans-serif" font-size="60" font-weight="bold" text-anchor="middle">2026</text>
</svg>
```

`assets/img/afrobeat.svg` — gradient `#2d132c → #f0a500`, texts `Afrobeat Live` / `Concert`.

```svg
<svg xmlns="http://www.w3.org/2000/svg" width="800" height="400" viewBox="0 0 800 400">
  <defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#2d132c"/><stop offset="1" stop-color="#f0a500"/></linearGradient></defs>
  <rect width="800" height="400" fill="url(#g)"/>
  <text x="400" y="185" fill="#ffffff" font-family="Arial, sans-serif" font-size="46" font-weight="bold" text-anchor="middle">Afrobeat Live</text>
  <text x="400" y="245" fill="#ffd460" font-family="Arial, sans-serif" font-size="60" font-weight="bold" text-anchor="middle">Concert</text>
</svg>
```

- [ ] **Step 7: Import the database and verify**

```powershell
Get-Content F:\projects\event-ticket\database.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -u root
& C:\xampp\mysql\bin\mysql.exe -u root -e "USE event_ticket_db; SHOW TABLES; SELECT id, name, event_date FROM events; SELECT username FROM admin;"
```

Expected: 5 tables listed; 3 events; admin row present.

- [ ] **Step 8: Lint and verify config loads**

```powershell
& C:\xampp\php\php.exe -l F:\projects\event-ticket\config\db.php
& C:\xampp\php\php.exe -r "require 'F:/projects/event-ticket/config/db.php'; echo 'DB OK: ' . $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();"
```

Expected: "No syntax errors" and `DB OK: 3`.

- [ ] **Step 9: Commit**

```powershell
git add -A; git commit -m "feat: config, database schema and seed data"
```

---

### Task 2: Shared layout, styles, guards, Home / About / Contact

**Files:**
- Create: `includes/header.php`, `includes/footer.php`, `includes/auth.php`, `includes/admin_auth.php`
- Create: `assets/css/style.css`
- Create: `index.php`, `about.php`, `contact.php`

**Interfaces:**
- Consumes: `config/db.php` helpers (`e`, `redirect`, `flash`, `naira`, `$pdo`, `BASE_URL`).
- Produces: `includes/header.php` (expects optional `$pageTitle`; renders navbar that swaps Login/Register for Dashboard/Logout when `$_SESSION['user_id']` set; displays and consumes `flash()`); `includes/footer.php`; `includes/auth.php` (redirects to `login.php` when no `$_SESSION['user_id']`); `includes/admin_auth.php` (redirects to `admin/login.php` when no `$_SESSION['admin_id']`). CSS classes: `.hero`, `.event-card`, `.ticket`, `.no-print`.

- [ ] **Step 1: Write `includes/header.php`**

```php
<?php require_once __DIR__ . '/../config/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Home') ?> — EventTicket</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark no-print">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>index.php">🎟 EventTicket</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="mainNav">
      <ul class="navbar-nav ms-auto align-items-lg-center">
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>events.php">Events</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>about.php">About</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>contact.php">Contact</a></li>
        <?php if (!empty($_SESSION['user_id'])): ?>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>logout.php">Logout</a></li>
        <?php else: ?>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>login.php">Login</a></li>
        <li class="nav-item ms-lg-2"><a class="btn btn-warning btn-sm px-3" href="<?= BASE_URL ?>register.php">Register</a></li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>
<main class="flex-grow-1">
<?php if ($flashMessage = flash()): ?>
<div class="container mt-3 no-print"><div class="alert alert-info alert-dismissible fade show"><?= e($flashMessage) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>
<?php endif; ?>
```

- [ ] **Step 2: Write `includes/footer.php`**

```php
</main>
<footer class="bg-dark text-light text-center py-3 mt-auto no-print">
  <div class="container small">&copy; <?= date('Y') ?> EventTicket — Online Event Ticket Booking Portal (Educational Project)</div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
```

- [ ] **Step 3: Write the guards**

`includes/auth.php`:

```php
<?php
// Guard: only logged-in users may view this page.
require_once __DIR__ . '/../config/db.php';
if (empty($_SESSION['user_id'])) {
    flash('Please log in to continue.');
    redirect('login.php');
}
```

`includes/admin_auth.php`:

```php
<?php
// Guard: only the logged-in administrator may view this page.
require_once __DIR__ . '/../config/db.php';
if (empty($_SESSION['admin_id'])) {
    flash('Please log in as administrator.');
    redirect('admin/login.php');
}
```

- [ ] **Step 4: Write `assets/css/style.css`**

```css
.hero {
    background: linear-gradient(135deg, #1a1a2e 0%, #e94560 100%);
    color: #fff;
    padding: 6rem 0;
}
.hero .display-4 { font-weight: 700; }
.event-card img { height: 200px; object-fit: cover; }
.event-card { transition: transform .15s ease; }
.event-card:hover { transform: translateY(-4px); }

/* E-ticket */
.ticket {
    max-width: 640px;
    margin: 2rem auto;
    border: 2px dashed #1a1a2e;
    border-radius: 12px;
    background: #fff;
}
.ticket .ticket-head {
    background: #1a1a2e;
    color: #fff;
    border-radius: 10px 10px 0 0;
}

@media print {
    .no-print { display: none !important; }
    body { background: #fff !important; }
    .ticket { margin: 0 auto; border-color: #000; }
}
```

- [ ] **Step 5: Write `index.php`**

```php
<?php
require_once __DIR__ . '/config/db.php';

$stmt = $pdo->query("SELECT * FROM events WHERE status = 'active' AND event_date >= CURDATE() ORDER BY event_date ASC LIMIT 1");
$featured = $stmt->fetch() ?: null;

$pageTitle = 'Home';
require __DIR__ . '/includes/header.php';
?>
<section class="hero text-center">
  <div class="container">
    <h1 class="display-4">Book Your Event Tickets Online</h1>
    <p class="lead mb-4">Welcome to EventTicket — skip the queue and get your e-ticket in minutes.</p>
    <?php if ($featured): ?>
    <p class="fs-5">Featured: <strong><?= e($featured['name']) ?></strong> · <?= e(date('j M Y', strtotime($featured['event_date']))) ?> · <?= e($featured['venue']) ?></p>
    <a class="btn btn-warning btn-lg me-2" href="<?= BASE_URL ?>book.php?event=<?= (int) $featured['id'] ?>">Book Ticket</a>
    <?php endif; ?>
    <a class="btn btn-outline-light btn-lg" href="<?= BASE_URL ?>register.php">Register</a>
  </div>
</section>
<?php if ($featured): ?>
<section class="container my-5">
  <div class="row align-items-center g-4">
    <div class="col-md-6">
      <img src="<?= BASE_URL ?>assets/img/<?= e($featured['image']) ?>" alt="<?= e($featured['name']) ?>" class="img-fluid rounded shadow">
    </div>
    <div class="col-md-6">
      <h2><?= e($featured['name']) ?></h2>
      <p><?= e($featured['description']) ?></p>
      <ul class="list-unstyled">
        <li><strong>Date:</strong> <?= e(date('l, j F Y', strtotime($featured['event_date']))) ?></li>
        <li><strong>Time:</strong> <?= e(date('g:i A', strtotime($featured['event_time']))) ?></li>
        <li><strong>Venue:</strong> <?= e($featured['venue']) ?></li>
        <li><strong>Tickets from:</strong> <?= naira($featured['regular_price']) ?></li>
      </ul>
      <a class="btn btn-primary" href="<?= BASE_URL ?>events.php">View All Events</a>
    </div>
  </div>
</section>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 6: Write `about.php`**

```php
<?php
require_once __DIR__ . '/config/db.php';
$pageTitle = 'About';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5" style="max-width: 760px">
  <h2>About EventTicket</h2>
  <p>EventTicket is a web portal for booking event tickets online. Instead of visiting a physical ticket office, visitors can browse upcoming events, register an account, book tickets, pay securely, and receive an electronic ticket they can download or print.</p>
  <p>This portal was developed as an educational project to demonstrate a complete online booking workflow — from event discovery through payment to e-ticket generation — using PHP and MySQL.</p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 7: Write `contact.php`** (form displays a confirmation; messages are intentionally not stored — out of scope per spec)

```php
<?php
require_once __DIR__ . '/config/db.php';
$sent = ($_SERVER['REQUEST_METHOD'] === 'POST');
$pageTitle = 'Contact';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5" style="max-width: 560px">
  <h2>Contact Us</h2>
  <?php if ($sent): ?>
  <div class="alert alert-success">Thank you — your message has been received. We will get back to you soon.</div>
  <?php endif; ?>
  <form method="post" class="card card-body shadow-sm">
    <div class="mb-3"><label class="form-label">Your Name</label><input class="form-control" name="name" required></div>
    <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" required></div>
    <div class="mb-3"><label class="form-label">Message</label><textarea class="form-control" name="message" rows="4" required></textarea></div>
    <button class="btn btn-primary">Send Message</button>
  </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 8: Lint and verify in browser**

```powershell
Get-ChildItem F:\projects\event-ticket -Recurse -Filter *.php | ForEach-Object { & C:\xampp\php\php.exe -l $_.FullName }
curl.exe -s http://localhost/event-ticket/index.php | Select-String "Book Your Event Tickets Online","Design Week Lagos"
curl.exe -s http://localhost/event-ticket/about.php | Select-String "About EventTicket"
```

Expected: no syntax errors; both pages contain the expected text; home page shows the featured event.

- [ ] **Step 9: Commit**

```powershell
git add -A; git commit -m "feat: shared layout, guards, home, about and contact pages"
```

---

### Task 3: User registration

**Files:**
- Create: `register.php`

**Interfaces:**
- Consumes: `$pdo`, `e()`, `flash()`, `redirect()`, layout includes.
- Produces: rows in `users` with hashed passwords.

- [ ] **Step 1: Write `register.php`**

```php
<?php
require_once __DIR__ . '/config/db.php';

$errors = [];
$old = ['full_name' => '', 'email' => '', 'phone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $old['phone'] = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if ($old['full_name'] === '') { $errors[] = 'Full name is required.'; }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'A valid email address is required.'; }
    if ($old['phone'] === '') { $errors[] = 'Phone number is required.'; }
    if (strlen($password) < 6) { $errors[] = 'Password must be at least 6 characters.'; }
    if ($password !== $confirm) { $errors[] = 'Passwords do not match.'; }

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$old['email']]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (full_name, email, phone, password) VALUES (?, ?, ?, ?)');
            $stmt->execute([$old['full_name'], $old['email'], $old['phone'], password_hash($password, PASSWORD_DEFAULT)]);
            flash('Registration successful. Please log in.');
            redirect('login.php');
        }
    }
}

$pageTitle = 'Register';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5" style="max-width: 520px">
  <h2 class="mb-4">Create an Account</h2>
  <?php foreach ($errors as $error): ?>
  <div class="alert alert-danger py-2"><?= e($error) ?></div>
  <?php endforeach; ?>
  <form method="post" class="card card-body shadow-sm">
    <div class="mb-3"><label class="form-label">Full Name</label><input class="form-control" name="full_name" value="<?= e($old['full_name']) ?>" required></div>
    <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= e($old['email']) ?>" required></div>
    <div class="mb-3"><label class="form-label">Phone Number</label><input class="form-control" name="phone" value="<?= e($old['phone']) ?>" required></div>
    <div class="mb-3"><label class="form-label">Password</label><input type="password" class="form-control" name="password" required></div>
    <div class="mb-3"><label class="form-label">Confirm Password</label><input type="password" class="form-control" name="confirm_password" required></div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary">Register</button>
      <button type="reset" class="btn btn-outline-secondary">Reset</button>
    </div>
  </form>
  <p class="mt-3">Already have an account? <a href="<?= BASE_URL ?>login.php">Login</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 2: Lint, then verify the full register flow with curl**

```powershell
& C:\xampp\php\php.exe -l F:\projects\event-ticket\register.php
curl.exe -s -c "$env:TEMP\et.txt" -d "full_name=Test User&email=test@example.com&phone=08010000000&password=secret1&confirm_password=secret1" -o NUL -w "%{redirect_url}" http://localhost/event-ticket/register.php
& C:\xampp\mysql\bin\mysql.exe -u root -e "USE event_ticket_db; SELECT full_name, email FROM users WHERE email='test@example.com';"
curl.exe -s -d "full_name=Test User&email=test@example.com&phone=08010000000&password=secret1&confirm_password=secret1" http://localhost/event-ticket/register.php | Select-String "already exists"
curl.exe -s -d "full_name=T&email=bad&phone=1&password=a&confirm_password=b" http://localhost/event-ticket/register.php | Select-String "valid email","do not match"
```

Expected: redirect to `login.php`; user row exists; duplicate email rejected; validation errors shown.

- [ ] **Step 3: Commit**

```powershell
git add -A; git commit -m "feat: user registration with validation and hashed passwords"
```

---

### Task 4: Login, logout, forgot password

**Files:**
- Create: `login.php`, `logout.php`, `forgot_password.php`

**Interfaces:**
- Consumes: `users` table, helpers, layout.
- Produces: `$_SESSION['user_id']` (int), `$_SESSION['user_name']` (string) — used by every guarded page.

- [ ] **Step 1: Write `login.php`**

```php
<?php
require_once __DIR__ . '/config/db.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$errors = [];
$oldEmail = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldEmail = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$oldEmail]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['full_name'];
        redirect('dashboard.php');
    }
    $errors[] = 'Invalid email or password.';
}

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5" style="max-width: 440px">
  <h2 class="mb-4">Login</h2>
  <?php foreach ($errors as $error): ?>
  <div class="alert alert-danger py-2"><?= e($error) ?></div>
  <?php endforeach; ?>
  <form method="post" class="card card-body shadow-sm">
    <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" value="<?= e($oldEmail) ?>" required></div>
    <div class="mb-3"><label class="form-label">Password</label><input type="password" class="form-control" name="password" required></div>
    <div class="d-flex justify-content-between align-items-center">
      <button class="btn btn-primary">Login</button>
      <a class="small" href="<?= BASE_URL ?>forgot_password.php">Forgot Password?</a>
    </div>
  </form>
  <p class="mt-3">No account yet? <a href="<?= BASE_URL ?>register.php">Register</a></p>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 2: Write `logout.php`**

```php
<?php
require_once __DIR__ . '/config/db.php';
unset($_SESSION['user_id'], $_SESSION['user_name']);
flash('You have been logged out.');
redirect('index.php');
```

- [ ] **Step 3: Write `forgot_password.php`**

Simplified educational flow (XAMPP has no mail server — a real system would email a reset link):

```php
<?php
// Educational simplification: a production system would email a one-time
// reset link instead of letting anyone who knows an email set a password.
require_once __DIR__ . '/config/db.php';

$errors = [];
$stage = 'email';
$email = trim($_POST['email'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        $errors[] = 'No account found with that email address.';
    } elseif (isset($_POST['password'])) {
        $password = $_POST['password'];
        $confirm = $_POST['confirm_password'] ?? '';
        if (strlen($password) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
            $stage = 'reset';
        } elseif ($password !== $confirm) {
            $errors[] = 'Passwords do not match.';
            $stage = 'reset';
        } else {
            $stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
            flash('Password updated. Please log in with your new password.');
            redirect('login.php');
        }
    } else {
        $stage = 'reset';
    }
}

$pageTitle = 'Forgot Password';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5" style="max-width: 440px">
  <h2 class="mb-4">Forgot Password</h2>
  <?php foreach ($errors as $error): ?>
  <div class="alert alert-danger py-2"><?= e($error) ?></div>
  <?php endforeach; ?>
  <?php if ($stage === 'email'): ?>
  <form method="post" class="card card-body shadow-sm">
    <div class="mb-3"><label class="form-label">Registered Email</label><input type="email" class="form-control" name="email" required></div>
    <button class="btn btn-primary">Continue</button>
  </form>
  <?php else: ?>
  <form method="post" class="card card-body shadow-sm">
    <input type="hidden" name="email" value="<?= e($email) ?>">
    <p class="text-muted small">Setting a new password for <strong><?= e($email) ?></strong></p>
    <div class="mb-3"><label class="form-label">New Password</label><input type="password" class="form-control" name="password" required></div>
    <div class="mb-3"><label class="form-label">Confirm New Password</label><input type="password" class="form-control" name="confirm_password" required></div>
    <button class="btn btn-primary">Set New Password</button>
  </form>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 4: Lint and verify login/logout with curl sessions**

```powershell
& C:\xampp\php\php.exe -l F:\projects\event-ticket\login.php; & C:\xampp\php\php.exe -l F:\projects\event-ticket\logout.php; & C:\xampp\php\php.exe -l F:\projects\event-ticket\forgot_password.php
curl.exe -s -c "$env:TEMP\et.txt" -d "email=test@example.com&password=secret1" -o NUL -w "%{redirect_url}`n" http://localhost/event-ticket/login.php
curl.exe -s -d "email=test@example.com&password=WRONG" http://localhost/event-ticket/login.php | Select-String "Invalid email or password"
```

Expected: successful login redirects to `dashboard.php` (that page itself arrives in Task 8 — only the redirect URL matters here); wrong password shows the error.

- [ ] **Step 5: Commit**

```powershell
git add -A; git commit -m "feat: login, logout and simplified forgot-password flow"
```

---

### Task 5: Events listing

**Files:**
- Create: `events.php`

**Interfaces:**
- Consumes: `events` table, layout, `naira()`.
- Produces: links to `book.php?event=<id>`.

- [ ] **Step 1: Write `events.php`**

```php
<?php
require_once __DIR__ . '/config/db.php';

$events = $pdo->query("SELECT * FROM events WHERE status = 'active' ORDER BY event_date ASC")->fetchAll();

$pageTitle = 'Events';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5">
  <h2 class="mb-4">Upcoming Events</h2>
  <?php if (!$events): ?>
  <div class="alert alert-secondary">No events available right now. Please check back soon.</div>
  <?php endif; ?>
  <div class="row g-4">
    <?php foreach ($events as $event): ?>
    <div class="col-sm-6 col-lg-4">
      <div class="card event-card h-100 shadow-sm">
        <?php $imagePath = __DIR__ . '/assets/img/' . $event['image']; ?>
        <?php if ($event['image'] !== '' && is_file($imagePath)): ?>
        <img src="<?= BASE_URL ?>assets/img/<?= e($event['image']) ?>" class="card-img-top" alt="<?= e($event['name']) ?>">
        <?php else: ?>
        <div class="card-img-top d-flex align-items-center justify-content-center bg-secondary text-white" style="height:200px"><?= e($event['name']) ?></div>
        <?php endif; ?>
        <div class="card-body d-flex flex-column">
          <h5 class="card-title"><?= e($event['name']) ?></h5>
          <ul class="list-unstyled small text-muted mb-3">
            <li><strong>Date:</strong> <?= e(date('D, j M Y', strtotime($event['event_date']))) ?></li>
            <li><strong>Time:</strong> <?= e(date('g:i A', strtotime($event['event_time']))) ?></li>
            <li><strong>Venue:</strong> <?= e($event['venue']) ?></li>
            <li><strong>Price:</strong> from <?= naira($event['regular_price']) ?></li>
          </ul>
          <a class="btn btn-primary mt-auto" href="<?= BASE_URL ?>book.php?event=<?= (int) $event['id'] ?>">Book Now</a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 2: Lint and verify**

```powershell
& C:\xampp\php\php.exe -l F:\projects\event-ticket\events.php
curl.exe -s http://localhost/event-ticket/events.php | Select-String "Design Week Lagos 2026","Lagos Tech Fest 2026","Afrobeat Live Concert","Book Now"
```

Expected: all three seeded events render with Book Now buttons.

- [ ] **Step 3: Commit**

```powershell
git add -A; git commit -m "feat: events listing page"
```

---

### Task 6: Ticket booking page

**Files:**
- Create: `book.php`

**Interfaces:**
- Consumes: `includes/auth.php`, `events` table, session user.
- Produces: `bookings` row with `status='pending'` and `booking_ref` in format `TKT-YYYY-00001`; redirects to `payment.php?booking=<booking_ref>`.

- [ ] **Step 1: Write `book.php`**

```php
<?php
require_once __DIR__ . '/includes/auth.php';

$eventId = (int) ($_GET['event'] ?? $_POST['event_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? AND status = 'active'");
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    flash('Sorry, that event was not found.');
    redirect('events.php');
}

$prices = [
    'regular' => (float) $event['regular_price'],
    'vip' => (float) $event['vip_price'],
    'vvip' => (float) $event['vvip_price'],
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ticketType = $_POST['ticket_type'] ?? '';
    $quantity = (int) ($_POST['quantity'] ?? 0);

    if (!isset($prices[$ticketType])) { $errors[] = 'Please choose a ticket type.'; }
    if ($quantity < 1 || $quantity > 10) { $errors[] = 'Quantity must be between 1 and 10.'; }

    if (!$errors) {
        $total = $prices[$ticketType] * $quantity;
        $stmt = $pdo->prepare('INSERT INTO bookings (user_id, event_id, ticket_type, quantity, total_amount) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$_SESSION['user_id'], $eventId, $ticketType, $quantity, $total]);
        $bookingId = (int) $pdo->lastInsertId();
        $bookingRef = sprintf('TKT-%s-%05d', date('Y'), $bookingId);
        $pdo->prepare('UPDATE bookings SET booking_ref = ? WHERE id = ?')->execute([$bookingRef, $bookingId]);
        redirect('payment.php?booking=' . urlencode($bookingRef));
    }
}

$pageTitle = 'Book Ticket';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5" style="max-width: 640px">
  <h2 class="mb-1">Book Ticket</h2>
  <p class="text-muted mb-4"><?= e($event['name']) ?> · <?= e(date('D, j M Y', strtotime($event['event_date']))) ?> · <?= e($event['venue']) ?></p>
  <?php foreach ($errors as $error): ?>
  <div class="alert alert-danger py-2"><?= e($error) ?></div>
  <?php endforeach; ?>
  <form method="post" class="card card-body shadow-sm">
    <input type="hidden" name="event_id" value="<?= (int) $event['id'] ?>">
    <label class="form-label fw-bold">Ticket Type</label>
    <?php foreach ($prices as $type => $price): ?>
    <div class="form-check mb-2">
      <input class="form-check-input ticket-type" type="radio" name="ticket_type" id="type-<?= e($type) ?>" value="<?= e($type) ?>" data-price="<?= $price ?>" <?= $type === 'regular' ? 'checked' : '' ?> required>
      <label class="form-check-label d-flex justify-content-between" for="type-<?= e($type) ?>" style="width: 100%">
        <span><?= strtoupper(e($type)) ?></span><span><?= naira($price) ?></span>
      </label>
    </div>
    <?php endforeach; ?>
    <div class="my-3">
      <label class="form-label fw-bold">Number of Tickets</label>
      <input type="number" class="form-control" name="quantity" id="quantity" value="1" min="1" max="10" required>
    </div>
    <div class="alert alert-secondary d-flex justify-content-between mb-3">
      <span>Total</span><strong id="total">—</strong>
    </div>
    <button class="btn btn-primary">Continue to Payment</button>
  </form>
</div>
<script>
function updateTotal() {
  var checked = document.querySelector('.ticket-type:checked');
  var qty = parseInt(document.getElementById('quantity').value || '0', 10);
  var price = checked ? parseFloat(checked.dataset.price) : 0;
  document.getElementById('total').textContent = '₦' + (price * qty).toLocaleString('en-NG', {minimumFractionDigits: 2});
}
document.querySelectorAll('.ticket-type').forEach(function (el) { el.addEventListener('change', updateTotal); });
document.getElementById('quantity').addEventListener('input', updateTotal);
updateTotal();
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 2: Lint and verify (logged-in session from Task 4's cookie jar)**

```powershell
& C:\xampp\php\php.exe -l F:\projects\event-ticket\book.php
curl.exe -s -c "$env:TEMP\et.txt" -d "email=test@example.com&password=secret1" -o NUL http://localhost/event-ticket/login.php
curl.exe -s -b "$env:TEMP\et.txt" "http://localhost/event-ticket/book.php?event=1" | Select-String "Ticket Type","VIP","Continue to Payment"
curl.exe -s -b "$env:TEMP\et.txt" -d "event_id=1&ticket_type=vip&quantity=2" -o NUL -w "%{redirect_url}`n" http://localhost/event-ticket/book.php
& C:\xampp\mysql\bin\mysql.exe -u root -e "USE event_ticket_db; SELECT booking_ref, ticket_type, quantity, total_amount, status FROM bookings ORDER BY id DESC LIMIT 1;"
curl.exe -s "http://localhost/event-ticket/book.php?event=1" -o NUL -w "%{redirect_url}`n"
```

Expected: form renders; POST redirects to `payment.php?booking=TKT-...`; DB shows pending booking `vip × 2 = 30000.00`; logged-out access redirects to `login.php`.

- [ ] **Step 3: Commit**

```powershell
git add -A; git commit -m "feat: ticket booking page with server-side pricing"
```

---

### Task 7: Payment (Paystack test mode + Demo Pay) and verification

**Files:**
- Create: `payment.php`, `verify_payment.php`

**Interfaces:**
- Consumes: pending `bookings` row by `booking_ref` (owned by session user), `PAYSTACK_PUBLIC_KEY` / `PAYSTACK_SECRET_KEY`.
- Produces: on success — `bookings.status='confirmed'`, `payments` row (`status='success'`, `paid_at=NOW()`), redirect to `confirmation.php?booking=<ref>`. Demo payments use reference `DEMO-<ref>-<time()>`.

- [ ] **Step 1: Write `payment.php`**

```php
<?php
require_once __DIR__ . '/includes/auth.php';

$bookingRef = trim($_GET['booking'] ?? '');
$stmt = $pdo->prepare('SELECT b.*, e.name AS event_name, u.email AS user_email
                       FROM bookings b
                       JOIN events e ON e.id = b.event_id
                       JOIN users u ON u.id = b.user_id
                       WHERE b.booking_ref = ? AND b.user_id = ?');
$stmt->execute([$bookingRef, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    flash('Booking not found.');
    redirect('dashboard.php');
}
if ($booking['status'] === 'confirmed') {
    redirect('confirmation.php?booking=' . urlencode($bookingRef));
}

$unitPrice = $booking['total_amount'] / max(1, $booking['quantity']);
$pageTitle = 'Payment';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5" style="max-width: 560px">
  <h2 class="mb-4">Payment</h2>
  <div class="card shadow-sm">
    <div class="card-body">
      <table class="table mb-4">
        <tr><th>Event</th><td><?= e($booking['event_name']) ?></td></tr>
        <tr><th>Ticket Type</th><td><?= strtoupper(e($booking['ticket_type'])) ?></td></tr>
        <tr><th>Ticket Price</th><td><?= naira($unitPrice) ?></td></tr>
        <tr><th>Quantity</th><td><?= (int) $booking['quantity'] ?></td></tr>
        <tr class="table-active"><th>Total Amount</th><td class="fw-bold"><?= naira($booking['total_amount']) ?></td></tr>
      </table>
      <?php if (PAYSTACK_PUBLIC_KEY !== ''): ?>
      <button class="btn btn-success w-100" onclick="payNow()">Pay Now</button>
      <p class="small text-muted mt-2 mb-0">Test card: 4084 0840 8408 4081 · any future expiry · CVV 408</p>
      <?php else: ?>
      <form method="post" action="<?= BASE_URL ?>verify_payment.php">
        <input type="hidden" name="booking" value="<?= e($booking['booking_ref']) ?>">
        <input type="hidden" name="demo" value="1">
        <button class="btn btn-success w-100">Pay Now (Demo)</button>
      </form>
      <p class="small text-muted mt-2 mb-0">Paystack keys are not configured — this demo payment always succeeds.</p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php if (PAYSTACK_PUBLIC_KEY !== ''): ?>
<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
function payNow() {
  var handler = PaystackPop.setup({
    key: '<?= PAYSTACK_PUBLIC_KEY ?>',
    email: '<?= e($booking['user_email']) ?>',
    amount: <?= (int) round($booking['total_amount'] * 100) ?>, // kobo
    currency: 'NGN',
    ref: '<?= e($booking['booking_ref']) ?>-<?= time() ?>',
    callback: function (response) {
      window.location = '<?= BASE_URL ?>verify_payment.php?booking=<?= urlencode($booking['booking_ref']) ?>&reference=' + encodeURIComponent(response.reference);
    },
    onClose: function () {}
  });
  handler.openIframe();
}
</script>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 2: Write `verify_payment.php`**

```php
<?php
require_once __DIR__ . '/includes/auth.php';

$bookingRef = trim($_POST['booking'] ?? $_GET['booking'] ?? '');
$stmt = $pdo->prepare('SELECT * FROM bookings WHERE booking_ref = ? AND user_id = ?');
$stmt->execute([$bookingRef, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    flash('Booking not found.');
    redirect('dashboard.php');
}
if ($booking['status'] === 'confirmed') {
    redirect('confirmation.php?booking=' . urlencode($bookingRef));
}

$isDemo = isset($_POST['demo']) && PAYSTACK_SECRET_KEY === '';

if ($isDemo) {
    $reference = 'DEMO-' . $bookingRef . '-' . time();
    $verified = true;
} else {
    $reference = trim($_GET['reference'] ?? '');
    $verified = false;
    if ($reference !== '' && PAYSTACK_SECRET_KEY !== '') {
        // Never trust the browser: confirm the transaction with Paystack directly.
        $ch = curl_init('https://api.paystack.co/transaction/verify/' . rawurlencode($reference));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . PAYSTACK_SECRET_KEY],
        ]);
        $response = json_decode((string) curl_exec($ch), true);
        curl_close($ch);
        $verified = $response
            && ($response['data']['status'] ?? '') === 'success'
            && (int) ($response['data']['amount'] ?? 0) === (int) round($booking['total_amount'] * 100);
    }
}

if (!$verified) {
    $pdo->prepare("INSERT INTO payments (booking_id, amount, paystack_reference, status) VALUES (?, ?, ?, 'failed')")
        ->execute([$booking['id'], $booking['total_amount'], $reference !== '' ? $reference : 'NONE']);
    flash('Payment could not be verified. Please try again.');
    redirect('payment.php?booking=' . urlencode($bookingRef));
}

$pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?")->execute([$booking['id']]);
$pdo->prepare("INSERT INTO payments (booking_id, amount, paystack_reference, status, paid_at) VALUES (?, ?, ?, 'success', NOW())")
    ->execute([$booking['id'], $booking['total_amount'], $reference]);

redirect('confirmation.php?booking=' . urlencode($bookingRef));
```

- [ ] **Step 3: Lint and verify the demo-pay path end to end**

```powershell
& C:\xampp\php\php.exe -l F:\projects\event-ticket\payment.php; & C:\xampp\php\php.exe -l F:\projects\event-ticket\verify_payment.php
$ref = (& C:\xampp\mysql\bin\mysql.exe -u root -N -e "USE event_ticket_db; SELECT booking_ref FROM bookings WHERE status='pending' ORDER BY id DESC LIMIT 1;")
curl.exe -s -b "$env:TEMP\et.txt" "http://localhost/event-ticket/payment.php?booking=$ref" | Select-String "Total Amount","Pay Now"
curl.exe -s -b "$env:TEMP\et.txt" -d "booking=$ref&demo=1" -o NUL -w "%{redirect_url}`n" http://localhost/event-ticket/verify_payment.php
& C:\xampp\mysql\bin\mysql.exe -u root -e "USE event_ticket_db; SELECT b.booking_ref, b.status, p.paystack_reference, p.status AS pay_status FROM bookings b JOIN payments p ON p.booking_id=b.id WHERE b.booking_ref='$ref';"
```

Expected: payment page shows the summary and a Pay Now (Demo) button; POST redirects to `confirmation.php?booking=TKT-...`; booking is `confirmed` with a `success` payment whose reference starts `DEMO-`.

- [ ] **Step 4: Commit**

```powershell
git add -A; git commit -m "feat: payment page with Paystack test mode and demo fallback, server-side verification"
```

---

### Task 8: Confirmation, e-ticket, user dashboard

**Files:**
- Create: `confirmation.php`, `ticket.php`, `dashboard.php`

**Interfaces:**
- Consumes: confirmed `bookings` + `payments` + `events` + `users`; QRCode.js CDN `https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js`.
- Produces: printable `.ticket` layout; dashboard links `ticket.php?booking=<ref>` and retry links `payment.php?booking=<ref>` for pending bookings. Admin (`$_SESSION['admin_id']`) may also view any ticket.

- [ ] **Step 1: Write `confirmation.php`**

```php
<?php
require_once __DIR__ . '/includes/auth.php';

$bookingRef = trim($_GET['booking'] ?? '');
$stmt = $pdo->prepare('SELECT b.*, e.name AS event_name, u.full_name
                       FROM bookings b
                       JOIN events e ON e.id = b.event_id
                       JOIN users u ON u.id = b.user_id
                       WHERE b.booking_ref = ? AND b.user_id = ?');
$stmt->execute([$bookingRef, $_SESSION['user_id']]);
$booking = $stmt->fetch();

if (!$booking) {
    flash('Booking not found.');
    redirect('dashboard.php');
}

$stmt = $pdo->prepare("SELECT status FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1");
$stmt->execute([$booking['id']]);
$paymentStatus = $stmt->fetchColumn() ?: 'pending';

$pageTitle = 'Booking Confirmation';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5" style="max-width: 560px">
  <div class="card shadow-sm text-center">
    <div class="card-body p-4">
      <?php if ($booking['status'] === 'confirmed'): ?>
      <div class="display-6 text-success mb-2">✔</div>
      <h2 class="mb-4">Booking Confirmed!</h2>
      <?php else: ?>
      <h2 class="mb-4">Booking <?= e(ucfirst($booking['status'])) ?></h2>
      <?php endif; ?>
      <table class="table text-start">
        <tr><th>Booking ID</th><td><?= e($booking['booking_ref']) ?></td></tr>
        <tr><th>Customer Name</th><td><?= e($booking['full_name']) ?></td></tr>
        <tr><th>Event Name</th><td><?= e($booking['event_name']) ?></td></tr>
        <tr><th>Payment Status</th><td><span class="badge bg-<?= $paymentStatus === 'success' ? 'success' : 'warning text-dark' ?>"><?= e(ucfirst($paymentStatus)) ?></span></td></tr>
      </table>
      <?php if ($booking['status'] === 'confirmed'): ?>
      <a class="btn btn-primary" href="<?= BASE_URL ?>ticket.php?booking=<?= urlencode($booking['booking_ref']) ?>">Download E-Ticket</a>
      <?php else: ?>
      <a class="btn btn-warning" href="<?= BASE_URL ?>payment.php?booking=<?= urlencode($booking['booking_ref']) ?>">Complete Payment</a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 2: Write `ticket.php`** (owner or admin; confirmed bookings only)

```php
<?php
require_once __DIR__ . '/config/db.php';

if (empty($_SESSION['user_id']) && empty($_SESSION['admin_id'])) {
    flash('Please log in to continue.');
    redirect('login.php');
}

$bookingRef = trim($_GET['booking'] ?? '');
$sql = 'SELECT b.*, e.name AS event_name, e.event_date, e.event_time, e.venue, u.full_name
        FROM bookings b
        JOIN events e ON e.id = b.event_id
        JOIN users u ON u.id = b.user_id
        WHERE b.booking_ref = ?';
$params = [$bookingRef];
if (empty($_SESSION['admin_id'])) {
    $sql .= ' AND b.user_id = ?';
    $params[] = $_SESSION['user_id'];
}
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$booking = $stmt->fetch();

if (!$booking) {
    flash('Ticket not found.');
    redirect(empty($_SESSION['admin_id']) ? 'dashboard.php' : 'admin/bookings.php');
}
if ($booking['status'] !== 'confirmed') {
    flash('This booking has not been paid for yet.');
    redirect('confirmation.php?booking=' . urlencode($bookingRef));
}

$pageTitle = 'E-Ticket ' . $booking['booking_ref'];
require __DIR__ . '/includes/header.php';
?>
<div class="ticket shadow">
  <div class="ticket-head text-center py-3 px-4">
    <h3 class="mb-0">🎟 <?= e($booking['event_name']) ?></h3>
    <small>E-TICKET</small>
  </div>
  <div class="p-4">
    <div class="row">
      <div class="col-8">
        <table class="table table-sm table-borderless mb-0">
          <tr><th>Ticket Number</th><td><?= e($booking['booking_ref']) ?></td></tr>
          <tr><th>Customer Name</th><td><?= e($booking['full_name']) ?></td></tr>
          <tr><th>Ticket Type</th><td><?= strtoupper(e($booking['ticket_type'])) ?> × <?= (int) $booking['quantity'] ?></td></tr>
          <tr><th>Date</th><td><?= e(date('l, j F Y', strtotime($booking['event_date']))) ?></td></tr>
          <tr><th>Time</th><td><?= e(date('g:i A', strtotime($booking['event_time']))) ?></td></tr>
          <tr><th>Venue</th><td><?= e($booking['venue']) ?></td></tr>
        </table>
      </div>
      <div class="col-4 text-center">
        <div id="qrcode" class="d-inline-block"></div>
        <div class="small text-muted mt-2">Scan at entry</div>
      </div>
    </div>
  </div>
</div>
<div class="text-center mb-5 no-print">
  <button class="btn btn-primary" onclick="window.print()">Print Ticket</button>
  <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>dashboard.php">Back to Dashboard</a>
</div>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
new QRCode(document.getElementById('qrcode'), { text: '<?= e($booking['booking_ref']) ?>', width: 140, height: 140 });
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 3: Write `dashboard.php`**

```php
<?php
require_once __DIR__ . '/includes/auth.php';

$stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

$stmt = $pdo->prepare('SELECT b.*, e.name AS event_name, e.event_date
                       FROM bookings b JOIN events e ON e.id = b.event_id
                       WHERE b.user_id = ? ORDER BY b.created_at DESC');
$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/includes/header.php';
?>
<div class="container my-5">
  <div class="row g-4">
    <div class="col-md-4">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title">My Profile</h5>
          <p class="mb-1"><strong><?= e($user['full_name']) ?></strong></p>
          <p class="mb-1 text-muted"><?= e($user['email']) ?></p>
          <p class="mb-3 text-muted"><?= e($user['phone']) ?></p>
          <a class="btn btn-outline-danger btn-sm" href="<?= BASE_URL ?>logout.php">Logout</a>
        </div>
      </div>
    </div>
    <div class="col-md-8">
      <div class="card shadow-sm">
        <div class="card-body">
          <h5 class="card-title mb-3">Booking History</h5>
          <?php if (!$bookings): ?>
          <p class="text-muted">No bookings yet. <a href="<?= BASE_URL ?>events.php">Browse events</a> to get started.</p>
          <?php else: ?>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead><tr><th>Ref</th><th>Event</th><th>Tickets</th><th>Amount</th><th>Status</th><th></th></tr></thead>
              <tbody>
              <?php foreach ($bookings as $booking): ?>
                <tr>
                  <td class="small"><?= e($booking['booking_ref']) ?></td>
                  <td><?= e($booking['event_name']) ?><br><small class="text-muted"><?= e(date('j M Y', strtotime($booking['event_date']))) ?></small></td>
                  <td><?= strtoupper(e($booking['ticket_type'])) ?> × <?= (int) $booking['quantity'] ?></td>
                  <td><?= naira($booking['total_amount']) ?></td>
                  <td><span class="badge bg-<?= $booking['status'] === 'confirmed' ? 'success' : ($booking['status'] === 'pending' ? 'warning text-dark' : 'secondary') ?>"><?= e(ucfirst($booking['status'])) ?></span></td>
                  <td>
                    <?php if ($booking['status'] === 'confirmed'): ?>
                    <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>ticket.php?booking=<?= urlencode($booking['booking_ref']) ?>">E-Ticket</a>
                    <?php elseif ($booking['status'] === 'pending'): ?>
                    <a class="btn btn-sm btn-outline-success" href="<?= BASE_URL ?>payment.php?booking=<?= urlencode($booking['booking_ref']) ?>">Pay Now</a>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
```

- [ ] **Step 4: Lint and verify**

```powershell
& C:\xampp\php\php.exe -l F:\projects\event-ticket\confirmation.php; & C:\xampp\php\php.exe -l F:\projects\event-ticket\ticket.php; & C:\xampp\php\php.exe -l F:\projects\event-ticket\dashboard.php
$ref = (& C:\xampp\mysql\bin\mysql.exe -u root -N -e "USE event_ticket_db; SELECT booking_ref FROM bookings WHERE status='confirmed' ORDER BY id DESC LIMIT 1;")
curl.exe -s -b "$env:TEMP\et.txt" "http://localhost/event-ticket/confirmation.php?booking=$ref" | Select-String "Booking Confirmed","Download E-Ticket"
curl.exe -s -b "$env:TEMP\et.txt" "http://localhost/event-ticket/ticket.php?booking=$ref" | Select-String "qrcode","Print Ticket","Scan at entry"
curl.exe -s -b "$env:TEMP\et.txt" http://localhost/event-ticket/dashboard.php | Select-String "Booking History","E-Ticket"
curl.exe -s "http://localhost/event-ticket/ticket.php?booking=$ref" -o NUL -w "%{redirect_url}`n"
```

Expected: confirmation and ticket render for the owner; dashboard lists the booking; logged-out ticket access redirects to `login.php`.

- [ ] **Step 5: Commit**

```powershell
git add -A; git commit -m "feat: confirmation page, QR e-ticket and user dashboard"
```

---

### Task 9: Admin login, layout, dashboard, logout

**Files:**
- Create: `includes/admin_header.php`
- Create: `admin/login.php`, `admin/index.php`, `admin/logout.php`

**Interfaces:**
- Consumes: `admin` table, `includes/admin_auth.php`, layout footer.
- Produces: `$_SESSION['admin_id']`, `$_SESSION['admin_name']`; `includes/admin_header.php` (expects `$pageTitle`; admin navbar: Dashboard | Manage Users | Manage Events | Bookings | Payments | Reports | Logout).

- [ ] **Step 1: Write `includes/admin_header.php`**

```php
<?php require_once __DIR__ . '/../config/db.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle ?? 'Admin') ?> — EventTicket Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-primary no-print">
  <div class="container">
    <a class="navbar-brand fw-bold" href="<?= BASE_URL ?>admin/index.php">🛠 EventTicket Admin</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#adminNav" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="adminNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/index.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/users.php">Manage Users</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/events.php">Manage Events</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/bookings.php">Bookings</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/payments.php">Payments</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/reports.php">Reports</a></li>
        <li class="nav-item"><a class="nav-link" href="<?= BASE_URL ?>admin/logout.php">Logout</a></li>
      </ul>
    </div>
  </div>
</nav>
<main class="flex-grow-1">
<?php if ($flashMessage = flash()): ?>
<div class="container mt-3 no-print"><div class="alert alert-info alert-dismissible fade show"><?= e($flashMessage) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div></div>
<?php endif; ?>
```

- [ ] **Step 2: Write `admin/login.php`**

```php
<?php
require_once __DIR__ . '/../config/db.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('admin/index.php');
}

$errors = [];
$oldUsername = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldUsername = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM admin WHERE username = ?');
    $stmt->execute([$oldUsername]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_name'] = $admin['username'];
        redirect('admin/index.php');
    }
    $errors[] = 'Invalid username or password.';
}

$pageTitle = 'Admin Login';
require __DIR__ . '/../includes/header.php';
?>
<div class="container my-5" style="max-width: 420px">
  <h2 class="mb-4">Admin Login</h2>
  <?php foreach ($errors as $error): ?>
  <div class="alert alert-danger py-2"><?= e($error) ?></div>
  <?php endforeach; ?>
  <form method="post" class="card card-body shadow-sm">
    <div class="mb-3"><label class="form-label">Username</label><input class="form-control" name="username" value="<?= e($oldUsername) ?>" required></div>
    <div class="mb-3"><label class="form-label">Password</label><input type="password" class="form-control" name="password" required></div>
    <button class="btn btn-primary">Login</button>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
```

- [ ] **Step 3: Write `admin/index.php`**

```php
<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$totalUsers = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalEvents = (int) $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
$totalBookings = (int) $pdo->query('SELECT COUNT(*) FROM bookings')->fetchColumn();
$totalRevenue = (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'success'")->fetchColumn();

$recent = $pdo->query('SELECT b.booking_ref, b.status, b.total_amount, b.created_at, u.full_name, e.name AS event_name
                       FROM bookings b JOIN users u ON u.id = b.user_id JOIN events e ON e.id = b.event_id
                       ORDER BY b.created_at DESC LIMIT 5')->fetchAll();

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="container my-5">
  <h2 class="mb-4">Admin Dashboard</h2>
  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3"><div class="card text-bg-primary shadow-sm"><div class="card-body"><h6>Total Users</h6><div class="fs-3 fw-bold"><?= $totalUsers ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card text-bg-success shadow-sm"><div class="card-body"><h6>Total Events</h6><div class="fs-3 fw-bold"><?= $totalEvents ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card text-bg-warning shadow-sm"><div class="card-body"><h6>Total Bookings</h6><div class="fs-3 fw-bold"><?= $totalBookings ?></div></div></div></div>
    <div class="col-6 col-lg-3"><div class="card text-bg-dark shadow-sm"><div class="card-body"><h6>Total Revenue</h6><div class="fs-4 fw-bold"><?= naira($totalRevenue) ?></div></div></div></div>
  </div>
  <div class="card shadow-sm">
    <div class="card-body">
      <h5 class="card-title">Recent Bookings</h5>
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Ref</th><th>Customer</th><th>Event</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
          <tbody>
          <?php foreach ($recent as $row): ?>
            <tr>
              <td class="small"><?= e($row['booking_ref']) ?></td>
              <td><?= e($row['full_name']) ?></td>
              <td><?= e($row['event_name']) ?></td>
              <td><?= naira($row['total_amount']) ?></td>
              <td><span class="badge bg-<?= $row['status'] === 'confirmed' ? 'success' : 'warning text-dark' ?>"><?= e(ucfirst($row['status'])) ?></span></td>
              <td class="small text-muted"><?= e(date('j M Y H:i', strtotime($row['created_at']))) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
```

- [ ] **Step 4: Write `admin/logout.php`**

```php
<?php
require_once __DIR__ . '/../config/db.php';
unset($_SESSION['admin_id'], $_SESSION['admin_name']);
flash('Admin logged out.');
redirect('admin/login.php');
```

- [ ] **Step 5: Lint and verify**

```powershell
Get-ChildItem F:\projects\event-ticket\admin, F:\projects\event-ticket\includes -Filter *.php | ForEach-Object { & C:\xampp\php\php.exe -l $_.FullName }
curl.exe -s -c "$env:TEMP\etadmin.txt" -d "username=admin&password=admin123" -o NUL -w "%{redirect_url}`n" http://localhost/event-ticket/admin/login.php
curl.exe -s -b "$env:TEMP\etadmin.txt" http://localhost/event-ticket/admin/index.php | Select-String "Total Users","Total Revenue","Recent Bookings"
curl.exe -s http://localhost/event-ticket/admin/index.php -o NUL -w "%{redirect_url}`n"
```

Expected: admin login redirects to `admin/index.php`; dashboard shows stat cards; unauthenticated access redirects to `admin/login.php`.

- [ ] **Step 6: Commit**

```powershell
git add -A; git commit -m "feat: admin login, layout and stats dashboard"
```

---

### Task 10: Admin — manage events (CRUD + image upload)

**Files:**
- Create: `admin/events.php`

**Interfaces:**
- Consumes: `events`, `bookings` tables; admin guard/header.
- Produces: event rows; uploaded images saved to `assets/img/` as `evt_<uniqid>.<ext>`.

- [ ] **Step 1: Write `admin/events.php`**

```php
<?php
require_once __DIR__ . '/../includes/admin_auth.php';

function handle_image_upload(array $file, array &$errors): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null; // no file chosen — keep existing image
    }
    if ($file['error'] !== UPLOAD_ERR_OK) { $errors[] = 'Image upload failed.'; return null; }
    if ($file['size'] > 2 * 1024 * 1024) { $errors[] = 'Image must be 2 MB or smaller.'; return null; }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) { $errors[] = 'Only JPG and PNG images are allowed.'; return null; }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) { $errors[] = 'Invalid image file.'; return null; }
    $name = uniqid('evt_') . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], __DIR__ . '/../assets/img/' . $name)) {
        $errors[] = 'Could not save the image.';
        return null;
    }
    return $name;
}

$action = $_GET['action'] ?? 'list';
$errors = [];

// Load event being edited.
$editing = null;
if ($action === 'edit') {
    $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([(int) ($_GET['id'] ?? 0)]);
    $editing = $stmt->fetch();
    if (!$editing) {
        flash('Event not found.');
        redirect('admin/events.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($action, ['add', 'edit'], true)) {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $eventDate = $_POST['event_date'] ?? '';
    $eventTime = $_POST['event_time'] ?? '';
    $venue = trim($_POST['venue'] ?? '');
    $regular = (float) ($_POST['regular_price'] ?? 0);
    $vip = (float) ($_POST['vip_price'] ?? 0);
    $vvip = (float) ($_POST['vvip_price'] ?? 0);
    $status = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';

    if ($name === '') { $errors[] = 'Event name is required.'; }
    if ($description === '') { $errors[] = 'Description is required.'; }
    if (!strtotime($eventDate)) { $errors[] = 'A valid date is required.'; }
    if (!strtotime($eventTime)) { $errors[] = 'A valid time is required.'; }
    if ($venue === '') { $errors[] = 'Venue is required.'; }
    if ($regular <= 0 || $vip <= 0 || $vvip <= 0) { $errors[] = 'All three ticket prices must be greater than zero.'; }

    $image = handle_image_upload($_FILES['image'] ?? [], $errors);

    if (!$errors) {
        if ($action === 'add') {
            $stmt = $pdo->prepare('INSERT INTO events (name, description, image, event_date, event_time, venue, regular_price, vip_price, vvip_price, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$name, $description, $image ?? '', $eventDate, $eventTime, $venue, $regular, $vip, $vvip, $status]);
            flash('Event added.');
        } else {
            $stmt = $pdo->prepare('UPDATE events SET name = ?, description = ?, image = ?, event_date = ?, event_time = ?, venue = ?, regular_price = ?, vip_price = ?, vvip_price = ?, status = ? WHERE id = ?');
            $stmt->execute([$name, $description, $image ?? $editing['image'], $eventDate, $eventTime, $venue, $regular, $vip, $vvip, $status, $editing['id']]);
            flash('Event updated.');
        }
        redirect('admin/events.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM bookings WHERE event_id = ?');
    $stmt->execute([$id]);
    if ((int) $stmt->fetchColumn() > 0) {
        // Keep booking history intact: deactivate instead of deleting.
        $pdo->prepare("UPDATE events SET status = 'inactive' WHERE id = ?")->execute([$id]);
        flash('Event has bookings, so it was set to inactive instead of deleted.');
    } else {
        $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
        flash('Event deleted.');
    }
    redirect('admin/events.php');
}

$events = $pdo->query('SELECT * FROM events ORDER BY event_date ASC')->fetchAll();

$pageTitle = 'Manage Events';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="container my-5">
  <?php if (in_array($action, ['add', 'edit'], true)): ?>
  <h2 class="mb-4"><?= $action === 'add' ? 'Add Event' : 'Edit Event' ?></h2>
  <?php foreach ($errors as $error): ?>
  <div class="alert alert-danger py-2"><?= e($error) ?></div>
  <?php endforeach; ?>
  <?php $formValue = fn(string $field) => e($_POST[$field] ?? $editing[$field] ?? ''); ?>
  <form method="post" enctype="multipart/form-data" class="card card-body shadow-sm" style="max-width: 720px">
    <div class="mb-3"><label class="form-label">Event Name</label><input class="form-control" name="name" value="<?= $formValue('name') ?>" required></div>
    <div class="mb-3"><label class="form-label">Description</label><textarea class="form-control" name="description" rows="3" required><?= $formValue('description') ?></textarea></div>
    <div class="row">
      <div class="col-md-6 mb-3"><label class="form-label">Date</label><input type="date" class="form-control" name="event_date" value="<?= $formValue('event_date') ?>" required></div>
      <div class="col-md-6 mb-3"><label class="form-label">Time</label><input type="time" class="form-control" name="event_time" value="<?= $formValue('event_time') ?>" required></div>
    </div>
    <div class="mb-3"><label class="form-label">Venue</label><input class="form-control" name="venue" value="<?= $formValue('venue') ?>" required></div>
    <div class="row">
      <div class="col-md-4 mb-3"><label class="form-label">Regular Price (₦)</label><input type="number" step="0.01" min="1" class="form-control" name="regular_price" value="<?= $formValue('regular_price') ?>" required></div>
      <div class="col-md-4 mb-3"><label class="form-label">VIP Price (₦)</label><input type="number" step="0.01" min="1" class="form-control" name="vip_price" value="<?= $formValue('vip_price') ?>" required></div>
      <div class="col-md-4 mb-3"><label class="form-label">VVIP Price (₦)</label><input type="number" step="0.01" min="1" class="form-control" name="vvip_price" value="<?= $formValue('vvip_price') ?>" required></div>
    </div>
    <div class="row">
      <div class="col-md-6 mb-3">
        <label class="form-label">Image (JPG/PNG, max 2 MB<?= $action === 'edit' ? ' — leave empty to keep current' : '' ?>)</label>
        <input type="file" class="form-control" name="image" accept=".jpg,.jpeg,.png">
      </div>
      <div class="col-md-6 mb-3">
        <label class="form-label">Status</label>
        <select class="form-select" name="status">
          <option value="active" <?= ($_POST['status'] ?? $editing['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= ($_POST['status'] ?? $editing['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </div>
    </div>
    <div class="d-flex gap-2">
      <button class="btn btn-primary"><?= $action === 'add' ? 'Add Event' : 'Save Changes' ?></button>
      <a class="btn btn-outline-secondary" href="<?= BASE_URL ?>admin/events.php">Cancel</a>
    </div>
  </form>
  <?php else: ?>
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Manage Events</h2>
    <a class="btn btn-primary" href="<?= BASE_URL ?>admin/events.php?action=add">+ Add Event</a>
  </div>
  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Name</th><th>Date</th><th>Venue</th><th>Regular</th><th>VIP</th><th>VVIP</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($events as $event): ?>
          <tr>
            <td><?= e($event['name']) ?></td>
            <td class="small"><?= e(date('j M Y', strtotime($event['event_date']))) ?> <?= e(date('g:i A', strtotime($event['event_time']))) ?></td>
            <td class="small"><?= e($event['venue']) ?></td>
            <td><?= naira($event['regular_price']) ?></td>
            <td><?= naira($event['vip_price']) ?></td>
            <td><?= naira($event['vvip_price']) ?></td>
            <td><span class="badge bg-<?= $event['status'] === 'active' ? 'success' : 'secondary' ?>"><?= e(ucfirst($event['status'])) ?></span></td>
            <td class="text-nowrap">
              <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>admin/events.php?action=edit&id=<?= (int) $event['id'] ?>">Edit</a>
              <form method="post" action="<?= BASE_URL ?>admin/events.php?action=delete" class="d-inline" onsubmit="return confirm('Delete this event?');">
                <input type="hidden" name="id" value="<?= (int) $event['id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
```

- [ ] **Step 2: Lint and verify add/edit/delete with curl**

```powershell
& C:\xampp\php\php.exe -l F:\projects\event-ticket\admin\events.php
curl.exe -s -b "$env:TEMP\etadmin.txt" http://localhost/event-ticket/admin/events.php | Select-String "Manage Events","Add Event","Design Week Lagos 2026"
curl.exe -s -b "$env:TEMP\etadmin.txt" -d "name=Test Event&description=A test&event_date=2026-09-01&event_time=12:00&venue=Test Hall&regular_price=1000&vip_price=2000&vvip_price=3000&status=active" -o NUL -w "%{redirect_url}`n" "http://localhost/event-ticket/admin/events.php?action=add"
$id = (& C:\xampp\mysql\bin\mysql.exe -u root -N -e "USE event_ticket_db; SELECT id FROM events WHERE name='Test Event';")
curl.exe -s -b "$env:TEMP\etadmin.txt" -d "id=$id" -o NUL "http://localhost/event-ticket/admin/events.php?action=delete"
& C:\xampp\mysql\bin\mysql.exe -u root -e "USE event_ticket_db; SELECT COUNT(*) AS remaining FROM events WHERE name='Test Event';"
```

Expected: list renders; add redirects back to list and the row exists; delete removes it (`remaining = 0`, since the test event has no bookings). Note: curl posts without a file field — `handle_image_upload` returns null (no file) and the event saves with an empty `image`; the events grid then shows its built-in text placeholder block, which is the expected behavior.

- [ ] **Step 3: Commit**

```powershell
git add -A; git commit -m "feat: admin event management with image upload and safe delete"
```

---

### Task 11: Admin — users, bookings (confirm payment), payments

**Files:**
- Create: `admin/users.php`, `admin/bookings.php`, `admin/payments.php`

**Interfaces:**
- Consumes: all tables; admin guard/header.
- Produces: manual payment confirmation — `bookings.status='confirmed'` + `payments` row with reference `MANUAL-<booking_ref>` and `status='success'`.

- [ ] **Step 1: Write `admin/users.php`**

```php
<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$users = $pdo->query('SELECT u.*, COUNT(b.id) AS booking_count
                      FROM users u LEFT JOIN bookings b ON b.user_id = u.id
                      GROUP BY u.id ORDER BY u.created_at DESC')->fetchAll();

$pageTitle = 'Manage Users';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="container my-5">
  <h2 class="mb-4">Registered Users</h2>
  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>#</th><th>Full Name</th><th>Email</th><th>Phone</th><th>Bookings</th><th>Registered</th></tr></thead>
        <tbody>
        <?php foreach ($users as $user): ?>
          <tr>
            <td><?= (int) $user['id'] ?></td>
            <td><?= e($user['full_name']) ?></td>
            <td><?= e($user['email']) ?></td>
            <td><?= e($user['phone']) ?></td>
            <td><?= (int) $user['booking_count'] ?></td>
            <td class="small text-muted"><?= e(date('j M Y', strtotime($user['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
```

- [ ] **Step 2: Write `admin/bookings.php`**

```php
<?php
require_once __DIR__ . '/../includes/admin_auth.php';

// Manual payment confirmation (e.g. bank transfer confirmed offline).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_booking_id'])) {
    $id = (int) $_POST['confirm_booking_id'];
    $stmt = $pdo->prepare("SELECT * FROM bookings WHERE id = ? AND status = 'pending'");
    $stmt->execute([$id]);
    if ($booking = $stmt->fetch()) {
        $pdo->prepare("UPDATE bookings SET status = 'confirmed' WHERE id = ?")->execute([$id]);
        $pdo->prepare("INSERT INTO payments (booking_id, amount, paystack_reference, status, paid_at) VALUES (?, ?, ?, 'success', NOW())")
            ->execute([$id, $booking['total_amount'], 'MANUAL-' . $booking['booking_ref']]);
        flash('Payment confirmed for ' . $booking['booking_ref'] . '.');
    }
    redirect('admin/bookings.php');
}

$statusFilter = $_GET['status'] ?? '';
$sql = 'SELECT b.*, u.full_name, e.name AS event_name
        FROM bookings b JOIN users u ON u.id = b.user_id JOIN events e ON e.id = b.event_id';
$params = [];
if (in_array($statusFilter, ['pending', 'confirmed', 'cancelled'], true)) {
    $sql .= ' WHERE b.status = ?';
    $params[] = $statusFilter;
}
$sql .= ' ORDER BY b.created_at DESC';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$pageTitle = 'Bookings';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="container my-5">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Bookings</h2>
    <div class="btn-group">
      <a class="btn btn-sm btn-outline-secondary <?= $statusFilter === '' ? 'active' : '' ?>" href="<?= BASE_URL ?>admin/bookings.php">All</a>
      <a class="btn btn-sm btn-outline-secondary <?= $statusFilter === 'pending' ? 'active' : '' ?>" href="?status=pending">Pending</a>
      <a class="btn btn-sm btn-outline-secondary <?= $statusFilter === 'confirmed' ? 'active' : '' ?>" href="?status=confirmed">Confirmed</a>
      <a class="btn btn-sm btn-outline-secondary <?= $statusFilter === 'cancelled' ? 'active' : '' ?>" href="?status=cancelled">Cancelled</a>
    </div>
  </div>
  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>Ref</th><th>Customer</th><th>Event</th><th>Tickets</th><th>Amount</th><th>Status</th><th>Date</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($bookings as $booking): ?>
          <tr>
            <td class="small"><?= e($booking['booking_ref']) ?></td>
            <td><?= e($booking['full_name']) ?></td>
            <td><?= e($booking['event_name']) ?></td>
            <td><?= strtoupper(e($booking['ticket_type'])) ?> × <?= (int) $booking['quantity'] ?></td>
            <td><?= naira($booking['total_amount']) ?></td>
            <td><span class="badge bg-<?= $booking['status'] === 'confirmed' ? 'success' : ($booking['status'] === 'pending' ? 'warning text-dark' : 'secondary') ?>"><?= e(ucfirst($booking['status'])) ?></span></td>
            <td class="small text-muted"><?= e(date('j M Y H:i', strtotime($booking['created_at']))) ?></td>
            <td class="text-nowrap">
              <?php if ($booking['status'] === 'pending'): ?>
              <form method="post" class="d-inline" onsubmit="return confirm('Confirm payment for this booking?');">
                <input type="hidden" name="confirm_booking_id" value="<?= (int) $booking['id'] ?>">
                <button class="btn btn-sm btn-success">Confirm Payment</button>
              </form>
              <?php elseif ($booking['status'] === 'confirmed'): ?>
              <a class="btn btn-sm btn-outline-primary" href="<?= BASE_URL ?>ticket.php?booking=<?= urlencode($booking['booking_ref']) ?>">Ticket</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
```

- [ ] **Step 3: Write `admin/payments.php`**

```php
<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$payments = $pdo->query('SELECT p.*, b.booking_ref, u.full_name, e.name AS event_name
                         FROM payments p
                         JOIN bookings b ON b.id = p.booking_id
                         JOIN users u ON u.id = b.user_id
                         JOIN events e ON e.id = b.event_id
                         ORDER BY p.id DESC')->fetchAll();

$pageTitle = 'Payments';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="container my-5">
  <h2 class="mb-4">Payments</h2>
  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead><tr><th>#</th><th>Booking</th><th>Customer</th><th>Event</th><th>Amount</th><th>Reference</th><th>Status</th><th>Paid At</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $payment): ?>
          <tr>
            <td><?= (int) $payment['id'] ?></td>
            <td class="small"><?= e($payment['booking_ref']) ?></td>
            <td><?= e($payment['full_name']) ?></td>
            <td><?= e($payment['event_name']) ?></td>
            <td><?= naira($payment['amount']) ?></td>
            <td class="small text-muted"><?= e($payment['paystack_reference']) ?></td>
            <td><span class="badge bg-<?= $payment['status'] === 'success' ? 'success' : ($payment['status'] === 'failed' ? 'danger' : 'warning text-dark') ?>"><?= e(ucfirst($payment['status'])) ?></span></td>
            <td class="small text-muted"><?= $payment['paid_at'] ? e(date('j M Y H:i', strtotime($payment['paid_at']))) : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
```

- [ ] **Step 4: Lint, then verify — create a fresh pending booking as the test user, confirm it as admin**

```powershell
& C:\xampp\php\php.exe -l F:\projects\event-ticket\admin\users.php; & C:\xampp\php\php.exe -l F:\projects\event-ticket\admin\bookings.php; & C:\xampp\php\php.exe -l F:\projects\event-ticket\admin\payments.php
curl.exe -s -b "$env:TEMP\et.txt" -d "event_id=2&ticket_type=regular&quantity=1" -o NUL http://localhost/event-ticket/book.php
$bid = (& C:\xampp\mysql\bin\mysql.exe -u root -N -e "USE event_ticket_db; SELECT id FROM bookings WHERE status='pending' ORDER BY id DESC LIMIT 1;")
curl.exe -s -b "$env:TEMP\etadmin.txt" http://localhost/event-ticket/admin/users.php | Select-String "test@example.com"
curl.exe -s -b "$env:TEMP\etadmin.txt" -d "confirm_booking_id=$bid" -o NUL http://localhost/event-ticket/admin/bookings.php
& C:\xampp\mysql\bin\mysql.exe -u root -e "USE event_ticket_db; SELECT b.status, p.paystack_reference FROM bookings b JOIN payments p ON p.booking_id=b.id WHERE b.id=$bid;"
curl.exe -s -b "$env:TEMP\etadmin.txt" http://localhost/event-ticket/admin/payments.php | Select-String "MANUAL-","DEMO-"
```

Expected: users page lists the test user; booking flips to `confirmed` with a `MANUAL-TKT-...` payment; payments page shows both MANUAL and DEMO references.

- [ ] **Step 5: Commit**

```powershell
git add -A; git commit -m "feat: admin users, bookings with manual payment confirmation, payments list"
```

---

### Task 12: Admin — reports

**Files:**
- Create: `admin/reports.php`

**Interfaces:**
- Consumes: `bookings`, `payments`, `events`; admin guard/header; `.no-print` CSS.
- Produces: per-event bookings count + revenue table with optional date-range filter and Print button.

- [ ] **Step 1: Write `admin/reports.php`**

```php
<?php
require_once __DIR__ . '/../includes/admin_auth.php';

$from = $_GET['from'] ?? '';
$to = $_GET['to'] ?? '';
$validFrom = $from !== '' && strtotime($from) ? $from : null;
$validTo = $to !== '' && strtotime($to) ? $to : null;

$sql = "SELECT e.name, e.event_date,
               COUNT(DISTINCT b.id) AS bookings_count,
               COALESCE(SUM(CASE WHEN b.status = 'confirmed' THEN b.quantity END), 0) AS tickets_sold,
               COALESCE((SELECT SUM(p.amount) FROM payments p JOIN bookings b2 ON b2.id = p.booking_id
                         WHERE b2.event_id = e.id AND p.status = 'success'" .
                         ($validFrom ? " AND p.paid_at >= :pfrom" : '') .
                         ($validTo ? " AND p.paid_at <= :pto" : '') . "), 0) AS revenue
        FROM events e
        LEFT JOIN bookings b ON b.event_id = e.id" .
        ($validFrom ? " AND b.created_at >= :bfrom" : '') .
        ($validTo ? " AND b.created_at <= :bto" : '') . "
        GROUP BY e.id, e.name, e.event_date
        ORDER BY e.event_date ASC";

$stmt = $pdo->prepare($sql);
if ($validFrom) { $stmt->bindValue(':pfrom', $validFrom . ' 00:00:00'); $stmt->bindValue(':bfrom', $validFrom . ' 00:00:00'); }
if ($validTo) { $stmt->bindValue(':pto', $validTo . ' 23:59:59'); $stmt->bindValue(':bto', $validTo . ' 23:59:59'); }
$stmt->execute();
$rows = $stmt->fetchAll();

$grandRevenue = array_sum(array_column($rows, 'revenue'));
$grandBookings = array_sum(array_column($rows, 'bookings_count'));

$pageTitle = 'Reports';
require __DIR__ . '/../includes/admin_header.php';
?>
<div class="container my-5">
  <div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <h2 class="mb-0">Reports</h2>
    <button class="btn btn-outline-primary" onclick="window.print()">Print Report</button>
  </div>
  <form method="get" class="row g-2 mb-4 no-print" style="max-width: 520px">
    <div class="col"><label class="form-label small">From</label><input type="date" class="form-control" name="from" value="<?= e($from) ?>"></div>
    <div class="col"><label class="form-label small">To</label><input type="date" class="form-control" name="to" value="<?= e($to) ?>"></div>
    <div class="col-auto d-flex align-items-end"><button class="btn btn-primary">Filter</button></div>
  </form>
  <h4 class="d-none d-print-block mb-3">EventTicket — Bookings &amp; Revenue Report<?= $validFrom || $validTo ? ' (' . e($from ?: '…') . ' to ' . e($to ?: '…') . ')' : '' ?></h4>
  <div class="card shadow-sm">
    <div class="card-body table-responsive">
      <table class="table align-middle mb-0">
        <thead><tr><th>Event</th><th>Event Date</th><th>Bookings</th><th>Tickets Sold (confirmed)</th><th>Revenue</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
          <tr>
            <td><?= e($row['name']) ?></td>
            <td><?= e(date('j M Y', strtotime($row['event_date']))) ?></td>
            <td><?= (int) $row['bookings_count'] ?></td>
            <td><?= (int) $row['tickets_sold'] ?></td>
            <td><?= naira($row['revenue']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="table-active fw-bold"><td colspan="2">Total</td><td><?= (int) $grandBookings ?></td><td></td><td><?= naira($grandRevenue) ?></td></tr>
        </tfoot>
      </table>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
```

- [ ] **Step 2: Lint and verify**

```powershell
& C:\xampp\php\php.exe -l F:\projects\event-ticket\admin\reports.php
curl.exe -s -b "$env:TEMP\etadmin.txt" http://localhost/event-ticket/admin/reports.php | Select-String "Reports","Design Week Lagos 2026","Total"
curl.exe -s -b "$env:TEMP\etadmin.txt" "http://localhost/event-ticket/admin/reports.php?from=2020-01-01&to=2020-01-02" | Select-String "0.00"
```

Expected: report lists every event with booking counts and revenue matching the admin dashboard total; an out-of-range date filter shows zero revenue.

- [ ] **Step 3: Commit**

```powershell
git add -A; git commit -m "feat: admin reports with date filter and print view"
```

---

### Task 13: README, cleanup, full manual test checklist

**Files:**
- Create: `README.md`
- Modify: none (cleanup of test data only)

- [ ] **Step 1: Write `README.md`**

````markdown
# EventTicket — Online Event Ticket Booking Portal

An educational web portal (HND project) for booking event tickets online.
Sample event: **Design Week Lagos 2026**.

## Stack

HTML · CSS · Bootstrap 5 · JavaScript · PHP · MySQL — developed with
Visual Studio Code, XAMPP, phpMyAdmin, and Google Chrome.

## Setup

1. Install [XAMPP](https://www.apachefriends.org/) and start **Apache** and **MySQL** from the XAMPP Control Panel.
2. Put this project at `C:\xampp\htdocs\event-ticket`
   (or create a junction: `New-Item -ItemType Junction -Path C:\xampp\htdocs\event-ticket -Target <project folder>`).
3. Import the database: open http://localhost/phpmyadmin → **Import** → choose `database.sql` → **Go**.
   (Or from a terminal: `C:\xampp\mysql\bin\mysql.exe -u root < database.sql`)
4. Optional — real payments: create a free [Paystack](https://paystack.com) account, copy your **test** keys
   into `config/db.php` (`PAYSTACK_PUBLIC_KEY` = `pk_test_...`, `PAYSTACK_SECRET_KEY` = `sk_test_...`).
   With the keys blank, the site uses a **Demo Pay** button that always succeeds.
5. Open http://localhost/event-ticket/

## Logins

| Role | URL | Credentials |
|---|---|---|
| Admin | http://localhost/event-ticket/admin/login.php | `admin` / `admin123` |
| User | http://localhost/event-ticket/login.php | register your own account |

## Paystack test card

`4084 0840 8408 4081` · any future expiry · CVV `408`

## Manual test checklist

1. Import `database.sql`; home page loads with the featured event.
2. Register a user — duplicate email rejected, password mismatch rejected.
3. Login / logout; `dashboard.php`, `book.php`, `ticket.php` redirect to login when logged out.
4. Book each ticket type; the live total and stored total match (price × quantity).
5. Pay with the Paystack test card (keys configured) — booking becomes **confirmed**.
6. With keys blank, **Demo Pay** confirms the booking with a `DEMO-` reference.
7. E-ticket shows QR code; print preview hides navigation; another user's ticket URL redirects away.
8. Admin: login, add/edit/delete an event (with image), confirm a pending payment, view users, payments and reports.

## Known limitations (educational scope)

- Forgot Password resets directly on-site (no email server in XAMPP; a real system would email a reset link).
- No CSRF tokens, rate limiting, or email verification.
- Contact form does not store or send messages.
- If Paystack verification fails with an SSL error, set `curl.cainfo` in `C:\xampp\php\php.ini` to a
  [cacert.pem](https://curl.se/docs/caextract.html) file and restart Apache.
````

- [ ] **Step 2: Remove curl-generated test data and re-verify seed state**

```powershell
& C:\xampp\mysql\bin\mysql.exe -u root -e "USE event_ticket_db; DELETE FROM payments; DELETE FROM bookings; DELETE FROM users WHERE email='test@example.com'; SELECT (SELECT COUNT(*) FROM users) AS users, (SELECT COUNT(*) FROM bookings) AS bookings, (SELECT COUNT(*) FROM payments) AS payments;"
```

Expected: `users=0, bookings=0, payments=0` (clean slate for the user's own demo).

- [ ] **Step 3: Final sweep — lint everything, smoke-test every page**

```powershell
Get-ChildItem F:\projects\event-ticket -Recurse -Filter *.php | ForEach-Object { & C:\xampp\php\php.exe -l $_.FullName } | Select-String "Errors parsing"
$pages = 'index.php','events.php','about.php','contact.php','register.php','login.php','forgot_password.php','admin/login.php'
foreach ($p in $pages) { "{0} -> {1}" -f $p, (curl.exe -s -o NUL -w "%{http_code}" "http://localhost/event-ticket/$p") }
```

Expected: no parse errors; every public page returns `200`.

- [ ] **Step 4: Commit**

```powershell
git add -A; git commit -m "docs: README with setup, logins, test checklist and limitations"
```
