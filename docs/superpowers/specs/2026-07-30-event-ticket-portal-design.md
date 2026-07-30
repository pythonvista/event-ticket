# Design: Online Event Ticket Booking Portal

**Date:** 2026-07-30
**Project:** Development of a Web Portal for an Online Event Ticket Booking (educational / HND project)
**Sample event:** Design Week Lagos 2026
**Stack:** HTML, CSS, Bootstrap 5, JavaScript, PHP (procedural, page-per-file), MySQL — running on XAMPP (Apache + MySQL + phpMyAdmin)

## 1. Purpose

A web application that lets people book event tickets online instead of visiting a physical ticket office. Users browse events, register, log in, book tickets, pay via Paystack (test mode), and receive a printable e-ticket with a QR code. An administrator manages events, users, bookings, and payments, and views reports.

This is an educational project: code favors clarity and explainability over production hardening, while still following core good practice (hashed passwords, prepared statements, escaped output).

## 2. Architecture

Classic page-per-file PHP application. No framework, no build step. Each page in the spec maps 1:1 to a PHP file. Shared concerns live in a few includes.

```
event-ticket/
├── config/
│   └── db.php                 # PDO MySQL connection + Paystack test keys
├── includes/
│   ├── header.php             # <head>, Bootstrap CDN, navbar (session-aware)
│   ├── footer.php             # footer + JS includes
│   ├── auth.php               # redirect to login.php if user not logged in
│   └── admin_auth.php         # redirect to admin/login.php if admin not logged in
├── assets/
│   ├── css/style.css          # custom styles on top of Bootstrap
│   └── img/                   # event images (uploads land here too)
├── database.sql               # full schema + seed data
├── index.php                  # Home
├── register.php               # user registration
├── login.php                  # user login
├── logout.php
├── forgot_password.php        # simplified reset (see §7)
├── events.php                 # all events grid
├── book.php                   # ticket type + quantity for one event
├── payment.php                # order summary + Pay Now
├── verify_payment.php         # server-side Paystack verification callback
├── confirmation.php           # booking confirmed page
├── ticket.php                 # printable e-ticket with QR code
├── dashboard.php              # user profile + booking history
├── about.php
├── contact.php
└── admin/
    ├── login.php              # admin login (separate from users)
    ├── index.php              # stats dashboard
    ├── events.php             # list + add/edit/delete events (image upload)
    ├── users.php              # view registered users
    ├── bookings.php           # view bookings, manually confirm payment
    ├── payments.php           # view payments
    ├── reports.php            # bookings & revenue per event, date filter, printable
    └── logout.php
```

Frontend libraries via CDN (internet is required for Paystack test mode anyway):

- Bootstrap 5 (CSS + JS bundle)
- QRCode.js (client-side QR generation on the e-ticket)
- Paystack Inline JS (payment popup)

## 3. Database

Database name: `event_ticket_db`. Five tables.

### users
| Column | Type | Notes |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| full_name | VARCHAR(100) | |
| email | VARCHAR(100) UNIQUE | login identifier |
| phone | VARCHAR(20) | |
| password | VARCHAR(255) | `password_hash()` |
| created_at | TIMESTAMP | default CURRENT_TIMESTAMP |

### admin
| Column | Type | Notes |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| username | VARCHAR(50) UNIQUE | |
| password | VARCHAR(255) | `password_hash()` |

### events
| Column | Type | Notes |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| name | VARCHAR(150) | |
| description | TEXT | |
| image | VARCHAR(255) | filename under assets/img/ |
| event_date | DATE | |
| event_time | TIME | |
| venue | VARCHAR(150) | |
| regular_price | DECIMAL(10,2) | |
| vip_price | DECIMAL(10,2) | |
| vvip_price | DECIMAL(10,2) | |
| status | ENUM('active','inactive') | default 'active' |

### bookings
| Column | Type | Notes |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| booking_ref | VARCHAR(20) UNIQUE | e.g. `DWL-2026-00042` |
| user_id | INT FK → users.id | |
| event_id | INT FK → events.id | |
| ticket_type | ENUM('regular','vip','vvip') | |
| quantity | INT | 1–10 |
| total_amount | DECIMAL(10,2) | |
| status | ENUM('pending','confirmed','cancelled') | default 'pending' |
| created_at | TIMESTAMP | |

### payments
| Column | Type | Notes |
|---|---|---|
| id | INT PK AUTO_INCREMENT | |
| booking_id | INT FK → bookings.id | |
| amount | DECIMAL(10,2) | |
| paystack_reference | VARCHAR(100) | 'DEMO-…' when simulated |
| status | ENUM('pending','success','failed') | |
| paid_at | DATETIME NULL | |

`database.sql` creates the database, all tables, and seeds:

- Admin account: username `admin`, password `admin123` (hashed)
- Design Week Lagos 2026 (featured event) + 2–3 additional sample events
- Ticket prices for the sample event: Regular ₦5,000 · VIP ₦15,000 · VVIP ₦30,000

## 4. User-facing pages

- **Home (`index.php`):** logo + navbar (Home | Events | About | Contact | Login | Register — swaps to Dashboard | Logout when logged in), hero banner featuring Design Week Lagos 2026 with welcome message, Book Ticket and Register buttons, footer.
- **Register:** full name, email, phone, password, confirm password; Register + Reset buttons. Validates required fields, email format, password match, unique email.
- **Login:** email + password; Login button + Forgot Password link. On success, session starts, redirect to dashboard.
- **Events:** responsive card grid of active events — image, name, date, time, venue, "from ₦…" price, Book Now button (redirects to login first if logged out).
- **Book (`book.php?event=ID`):** shows event summary; user picks ticket type (radio: Regular/VIP/VVIP with prices) and quantity (1–10); live total via JavaScript; Continue to Payment creates a `pending` booking and redirects.
- **Payment (`payment.php?booking=REF`):** shows event name, ticket type, unit price, quantity, total. Pay Now opens the Paystack popup (test keys). On popup success, browser hits `verify_payment.php`, which calls Paystack's verify API server-side; only a verified `success` marks the booking `confirmed` and inserts a `success` payment row. If Paystack keys are not configured in `config/db.php`, the page shows a **Demo Pay** button instead, which records a simulated successful payment (`DEMO-` reference) — the demo never blocks on missing keys.
- **Confirmation:** booking ref, customer name, event name, payment status, Download E-Ticket button.
- **E-Ticket (`ticket.php?booking=REF`):** styled ticket — event name, date, time, venue, customer name, ticket type, quantity, booking ref — plus a QR code encoding the booking ref, and a Print Ticket button (browser print dialog doubles as save-to-PDF). Only viewable by the booking's owner (or admin). Print stylesheet hides nav/footer.
- **Dashboard:** profile card (name, email, phone) and booking history table (event, date, tickets, amount, status, link to e-ticket) + Logout.
- **About / Contact:** static informational pages; contact shows a simple form that displays a "message received" confirmation (not stored — out of scope).

## 5. Admin pages

Separate session flag; `admin/` pages guarded by `admin_auth.php`.

- **Login:** username + password against `admin` table.
- **Dashboard:** stat cards — total users, total events, total bookings, total revenue (SUM of `success` payments); recent bookings list.
- **Manage Events:** table of events with Add/Edit/Delete. Form includes all event fields + image upload (jpg/png, max 2 MB, stored in `assets/img/`). Delete asks for confirmation; events with bookings are set `inactive` instead of hard-deleted so history stays intact.
- **Manage Users:** table of registered users (no editing — view only, per spec).
- **Bookings:** all bookings with filters by status; a **Confirm Payment** action on pending bookings (marks booking confirmed + inserts a manual `success` payment) — covers offline/manual confirmation.
- **Payments:** all payment records with status.
- **Reports:** bookings count and revenue per event, optional date-range filter, print button.

## 6. Security (educational level, but correct)

- Passwords hashed with `password_hash()` / verified with `password_verify()`.
- All SQL through PDO prepared statements.
- All dynamic output through `htmlspecialchars()`.
- Session-based auth; user and admin sessions use distinct keys; guards redirect unauthenticated access.
- Ticket/booking pages check ownership (booking's `user_id` must match session user, unless admin).
- Paystack transactions verified server-side with the secret key — the client's word is never trusted.
- Image uploads restricted by extension + MIME check and renamed on save.

Out of scope (documented as limitations): CSRF tokens, rate limiting, email verification, HTTPS config.

## 7. Forgot Password (simplified)

XAMPP has no mail server, so the flow is educational: user enters their registered email → if found, they may set a new password directly on the next screen. The limitation (a real system would email a reset link) is noted in code comments and documentation.

## 8. Error handling

- Form errors re-render the page with Bootstrap alert messages and preserved input values.
- DB connection failure shows a friendly "site under maintenance" message.
- Invalid IDs/refs in URLs (missing event, foreign booking) → redirect with an error notice.
- Failed/abandoned Paystack payments leave the booking `pending`; the user can retry payment from the dashboard.

## 9. Testing

Manual test checklist shipped in `README.md`:

1. Import `database.sql` via phpMyAdmin; site loads at `http://localhost/event-ticket/`.
2. Register a user; duplicate email rejected; password mismatch rejected.
3. Login/logout; guarded pages redirect when logged out.
4. Book each ticket type; totals compute correctly.
5. Pay with Paystack test card `4084 0840 8408 4081` (any future expiry, CVV `408`); booking flips to confirmed.
6. Demo Pay path works when keys are blank.
7. E-ticket renders with QR; print preview is clean; another user's ticket URL is blocked.
8. Admin: login, CRUD an event with image, confirm a pending payment, view reports.

No automated test framework — appropriate for project scope.

## 10. Setup

`README.md` covers: copy project into `C:\xampp\htdocs\event-ticket` (or point Apache at `F:\projects\event-ticket`), start Apache + MySQL, import `database.sql` in phpMyAdmin, optionally add Paystack test keys in `config/db.php`, browse to `http://localhost/event-ticket/`. Default admin login `admin` / `admin123`.
