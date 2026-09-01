# TubigKo

TubigKo Water Refilling Service System is a comprehensive web &amp; mobile platform that modernizes the local water delivery industry by replacing manual processes with automated ordering, cashless payments, &amp; real-time rider tracking, &amp; digital ledger for managing borrowed containers.

## Backend setup (PHP + MySQL/MariaDB)

The original frontend-only demo now has a full working backend: real accounts,
sessions, role-based access, and every admin/customer page reads and writes
MySQL instead of hardcoded arrays.

### 1. Requirements

- PHP 8.1+ with the `pdo_mysql` extension
- MySQL 8+ or MariaDB 10.4+
- A web server (Apache/Nginx) **or** just PHP's built-in server for local use

### 2. Import the database

```bash
mysql -u root -p < database/schema.sql
```

This creates the `tubigko_db` database, all tables (with foreign keys), and
seed data: one admin account, six sample customers, a gallon catalog,
sample deliveries, payments, and notifications.

### 3. Configure the connection

`includes/db.php` reads its settings from environment variables (falling
back to sensible local defaults):

| Variable  | Default     |
|-----------|-------------|
| `DB_HOST` | `refilling.page.gd` |
| `DB_PORT` | `3306`      |
| `DB_NAME` | `tubigko_db`|
| `DB_USER` | `root`      |
| `DB_PASS` | *(empty)*   |

Set these however your environment normally sets env vars (Apache
`SetEnv`, a `.env` loader, your hosting panel, etc.), or just edit the
`define(...)` calls at the top of `includes/db.php` directly for a quick
local test.

### 4. Run it

```bash
php -S localhost:8000
```

Then open `http://localhost:8000/`.

### 5. Default accounts (from the seed data)

| Role     | Username        | Password       |
|----------|-----------------|----------------|
| Admin    | `admin`         | `Admin@123`    |
| Customer | `maria.santos`  | `Customer@123` |
| Customer | `jose.dc`       | `Customer@123` |
| Customer | `ana.reyes`     | `Customer@123` |

New customers can also self-register from the "Register" page — new
accounts are always created with the `customer` role (self-registering as
an admin is not possible).

**Change the admin password** (or create your own admin account and
disable/delete the seeded one) before using this anywhere beyond a local
demo — the seeded credentials above are intentionally simple for testing.

### What's wired up

- Sessions + `password_hash()`/`password_verify()` authentication
- Role-based access control enforced server-side on every admin/customer page
- CSRF tokens on every state-changing form
- Real CRUD for customers, gallons, deliveries, payments, and notifications
- Server-side recalculation of every order total (never trusts the browser)
- Automatic customer notifications when a delivery's status changes
- Prepared statements everywhere user input touches SQL

