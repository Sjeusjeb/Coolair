# CoolAir — Technician Booking Platform (PHP + MySQL, for XAMPP)

Full-stack version of the CoolAir concept with **three account types**:
customers book technicians, technicians manage their jobs, and an admin
oversees the whole platform — all backed by real MySQL tables.

## 1. Install

1. Copy the whole `coolair-system` folder into your XAMPP `htdocs` directory,
   e.g. `C:\xampp\htdocs\coolair` (Windows) or `/Applications/XAMPP/htdocs/coolair` (Mac).
2. Start **Apache** and **MySQL** from the XAMPP control panel.
3. Open **phpMyAdmin** (`http://localhost/phpmyadmin`).
   - **If this is a fresh install:** go to Import, and import `database/schema.sql`.
   - **If you already have a working `coolair` database from before** (technicians,
     bookings, etc. that you want to keep): do NOT drop it. Instead, select the
     `coolair` database, go to the **SQL** tab, and run `database/migration_v3.sql`.
     This only adds the new rating/security tables and columns — it does not
     touch your existing data.
   - **If you previously ran an even older version** (no `customers`/`admins`
     tables at all): drop the database (`DROP DATABASE coolair;`) and import
     `database/schema.sql` fresh instead.
4. Visit `http://localhost/coolair/database/seed.php` once, in your browser
   (only needed on a fresh install — skip this if you ran the migration on an
   existing database). This inserts demo services, technicians, an admin
   account, and a demo customer account.
5. Visit the site: `http://localhost/coolair/index.php`

If your MySQL uses a different user/password than the XAMPP default
(`root` / empty password), edit `config/db.php`.

## 2. Demo accounts (all created by seed.php)

| Role       | Email                  | Password    |
|------------|------------------------|-------------|
| Customer   | maria@coolair.demo     | password123 |
| Technician | juan@coolair.demo      | password123 |
| Technician | mark@coolair.demo      | password123 |
| Technician | rico@coolair.demo      | password123 |
| Technician | liza@coolair.demo      | password123 |
| Admin      | admin@coolair.demo     | admin123    |

## 3. What each role can do

**Customer** (sign up at `register.php` / log in at `login.php`)
- Browse and filter technicians, view profiles and reviews.
- See each technician's live **availability badge** (Available now / Scheduled / Offline) on both the browse list and their profile.
- Book a technician — booking is now tied to their account (`customer_id`).
- **My Bookings** — see every booking they've made and its current status.
- Track any booking's live status timeline (`track.php`).
- Once a booking is marked **Completed**, rate the technician (1–5 stars + optional comment) right from the tracking page. One rating per booking — the technician's overall rating and review count are recalculated automatically from all their reviews.

**Technician** (`technician/login.php`)
- Dashboard with **incoming requests** (Accept/Decline).
- **Active jobs** — advance status: On the way → Arrived → In progress → Completed, **or Cancel** the booking at any point before completion (customer sees this reflected instantly on their tracking page).
- **Earnings this week** and **service history**, computed from real bookings.

**Admin** (`admin/login.php`)
- **Dashboard** — platform-wide stats (technicians, customers, bookings, revenue
  this week) and a breakdown of bookings by status.
- **Manage technicians** — add a new technician (with services offered),
  toggle their verified badge, or delete one.
- **All bookings** — view every booking on the platform, filter by status,
  and cancel a booking if needed.
- **Customers** — view all registered customer accounts and how many
  bookings each has made.
- **Activity log** — see recent admin actions (technician added/verified/deleted, bookings cancelled, logins/logouts) and which accounts are currently locked out from repeated failed logins.

## 3b. Security features

- **CSRF protection** — every form (login, signup, booking, ratings, technician/admin actions) includes a per-session token that's verified on submit. A forged or replayed cross-site request is rejected.
- **Login lockout** — after 5 failed login attempts, an email is locked out from that role's login for 10 minutes (tracked per customer/technician/admin separately, in `login_throttle`).
- **Hardened sessions** — `httponly` + `SameSite=Lax` session cookies, strict session mode, and the session ID is regenerated on every successful login/signup (prevents session fixation).
- **Security headers** — `X-Content-Type-Options`, `X-Frame-Options`, and `Referrer-Policy` are sent on every page.
- **Admin activity log** — technician add/verify/delete, booking cancellations, and admin logins/logouts are recorded with a timestamp, viewable at `admin/activity_log.php`.
- **Stronger password rule** — signups and admin-created technician accounts now require at least 8 characters.

## 4. Project structure

```
coolair-system/
├── config/
│   └── db.php                    # database connection settings
├── database/
│   ├── schema.sql                 # run first — creates all tables
│   └── seed.php                   # run once in the browser — demo data + accounts
├── includes/
│   ├── header.php                 # nav bar (role-aware: guest/customer/technician/admin)
│   └── footer.php
├── assets/
│   └── style.css                  # shared styling (CoolAir blue/amber theme)
├── index.php                      # home — browse/filter technicians
├── technician.php                 # technician profile
├── book.php                       # booking form (requires customer login)
├── track.php                      # look up a booking by reference, live status
├── register.php                   # customer sign up
├── login.php / logout.php         # customer auth
├── my-bookings.php                # customer's own booking history
├── technician/
│   ├── login.php / logout.php
│   ├── dashboard.php               # incoming requests / active jobs / earnings
│   └── actions.php                 # accept / decline / advance status
└── admin/
    ├── login.php / logout.php
    ├── dashboard.php                # platform stats
    ├── technicians.php              # list + add technician form
    ├── technician_actions.php       # add / verify toggle / delete
    ├── bookings.php                 # all bookings, filterable
    ├── booking_actions.php          # cancel a booking
    └── customers.php                # list of customer accounts
```

## 5. Database overview

- `customers` — customer accounts (name, email, hashed password, phone).
- `admins` — admin accounts.
- `technicians` — profile info, rating, starting fee, login credentials.
- `services` — Cleaning, Repair, Installation, Maintenance, Freon Refill.
- `technician_services` — which services each technician offers (many-to-many).
- `reviews` — customer reviews per technician.
- `bookings` — one row per booking, linked to a `customer_id` and `technician_id`,
  with a `status` enum
  (`pending → accepted → on_the_way → arrived → in_progress → completed`,
  or `declined` / `cancelled`).
- `booking_status_log` — a timestamped row every time a booking's status
  changes, so the tracking page can show real times per step.

## 6. Notes & natural next steps

- There's no CSRF protection or rate-limiting on the forms — fine for local/
  learning use, but add both before deploying this anywhere public.
- The `/admin/login.php` link is shown openly in the nav for demo convenience;
  in a real deployment you'd hide or separately secure the admin area.
- Not included yet: editable technician profile photos/portfolio uploads,
  email/SMS notifications, password reset flows, and admin management of the
  `services` list itself (currently fixed at seed time).

---

## Supabase migration (new)

The app now talks to **Supabase (Postgres) via its REST API (PostgREST)**
instead of a local MySQL database over PDO. This means it no longer needs
XAMPP's MySQL running at all — only PHP itself (for Apache/CLI) plus the
`curl` extension, which ships with PHP by default.

### What changed
- `config/db.php` (PDO/MySQL) is no longer used by the app — it's kept only
  for reference / rollback. Every page now requires `config/supabase.php`
  instead.
- `config/supabase.php` is a small REST client (`sb_get`, `sb_insert`,
  `sb_update`, `sb_delete`, `sb_upsert`, `sb_rpc`, `sb_count`) that replaces
  raw SQL/PDO calls throughout the app.
- `config/supabase_credentials.php` holds your project URL and
  **service_role** key. This key has full read/write access and bypasses
  Row Level Security — it must only ever live here, server-side. **Never**
  put it in JS, a `.env` committed to a public repo, or anything the browser
  loads.
- `database/schema_supabase.sql` is the Postgres equivalent of the old
  `database/schema.sql`, plus a handful of small SQL functions (RPC) for the
  few things plain REST can't do alone: dashboard totals, this-week revenue,
  bookings-by-status counts, and recomputing a technician's average rating.
- `database/seed_supabase.php` replaces `database/seed.php` — same demo
  data, inserted through the REST API instead of SQL.
- `database/add_technicians.php` and `database/add_demo_accounts.php` were
  **not** converted (they overlap with seed.php) — they still expect the old
  MySQL setup and can be ignored/deleted.

### Setup
1. In your Supabase project (`supabase.com` → your project → **SQL Editor**
   → New query), paste the contents of `database/schema_supabase.sql` and
   run it. This creates all the tables and functions.
2. Confirm `config/supabase_credentials.php` has your project's URL and
   `service_role` key (Project Settings → API). It's already filled in for
   this project.
3. Run the seed script once:
   - CLI: `php database/seed_supabase.php`
   - or browser: `http://localhost/coolair-system/database/seed_supabase.php`
4. Visit the site as before — `index.php` onward. No MySQL/XAMPP database
   needed anymore; only PHP + Apache (or `php -S localhost:8000`).

### Notes / limitations of this migration
- PostgREST requests aren't wrapped in a single SQL transaction the way the
  old `$pdo->beginTransaction()` seed script was, so a failed seed run can
  leave partial data — if that happens, clear the tables in the SQL editor
  and re-run `seed_supabase.php`.
- Row Level Security is left **off** in `schema_supabase.sql` since only
  this server-side PHP (using the service_role key) talks to the database.
  If you ever add a client that calls Supabase directly with the anon/public
  key, turn RLS on and add policies first.
- `is_verified` / `background_checked` are now real booleans (`true`/`false`)
  instead of MySQL's `0`/`1` — the PHP code already handles this correctly.

---

## Publishing / security notes

- `config/supabase_credentials.php` is **git-ignored**. Copy
  `config/supabase_credentials.example.php` to that name and fill in your own keys.
- Before deploying publicly, **delete or block** `database/seed*.php`,
  `database/add_*.php` and `debug.php`, and change every demo password
  (`password123`, `admin123`).
- Use PHP hosting (Apache/Nginx + PHP + cURL). Static hosts such as Netlify or
  GitHub Pages cannot run PHP.
