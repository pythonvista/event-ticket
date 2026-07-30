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
   (Or from a terminal — cmd: `C:\xampp\mysql\bin\mysql.exe -u root < database.sql`, PowerShell: `Get-Content database.sql -Raw | C:\xampp\mysql\bin\mysql.exe -u root`)
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
- Sessions are regenerated on login, but there is no further session hardening (no inactivity timeout or device tracking).
