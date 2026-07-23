# SwiftTrack — Courier &amp; Parcel Management System

A Laravel 12 application for running a courier operation: customers, drivers,
branches, parcel booking, driver dispatch, an immutable shipment-tracking
timeline, business reports, and a token-authenticated REST API.

Built for the **Vampior Designs — Associate Software Engineer Assessment**.

---

## Contents

- [Highlights](#highlights)
- [Tech stack](#tech-stack)
- [Requirements](#requirements)
- [Installation](#installation)
- [Demo accounts](#demo-accounts)
- [Feature tour](#feature-tour)
- [Architecture](#architecture)
- [Database schema](#database-schema)
- [Testing](#testing)
- [API](#api)
- [Configuration reference](#configuration-reference)
- [Troubleshooting](#troubleshooting)

---

## Highlights

- **Four distinct dashboards** — Super Admin, Branch Manager, Dispatcher and
  Driver each land on a different screen showing only what their job needs.
- **Branch-scoped data** — a Branch Manager physically cannot query another
  branch's parcels; scoping lives in Eloquent scopes and policies, not in the
  views.
- **An immutable tracking timeline** — every status change in the system funnels
  through one service, so the audit trail is complete by construction.
- **Enforced status transitions** — a parcel cannot skip from Pending to
  Delivered; the legal moves are declared on the enum and checked server-side.
- **Bonus features implemented** — QR code per parcel, email verification,
  signature-pad proof of delivery, delivery photos, and PDF export.

---

## Tech stack

| Layer | Choice |
|---|---|
| Framework | Laravel 12 |
| Language | PHP 8.3 |
| Database | MySQL |
| Views | Blade |
| CSS / JS | Bootstrap 5.3, Bootstrap Icons, Chart.js 4, SignaturePad — bundled with Vite |
| API auth | Laravel Sanctum |
| Exports | `maatwebsite/excel` (CSV, XLSX), `barryvdh/laravel-dompdf` (PDF) |
| QR codes | `simplesoftwareio/simple-qrcode` (SVG output — no imagick needed) |

Authentication is written from scratch (no Breeze, Jetstream or Fortify), as
the specification asks for a custom login and registration.

---

## Requirements

- PHP **8.2 or newer** (8.3 recommended)
  with `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `gd`, `zip`, `curl`, `bcmath`
- MySQL **5.7+** or MariaDB 10.3+
- Composer 2
- Node.js 18+ and npm

---

## Installation

### 1. Install dependencies

```bash
composer install
```

```bash
npm install
```

### 2. Create the environment file

```bash
cp .env.example .env
```

```bash
php artisan key:generate
```

Then edit `.env` and point it at your database:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=courier_db
DB_USERNAME=root
DB_PASSWORD=
```

### 3. Create the database

```bash
mysql -u root -p -e "CREATE DATABASE courier_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

### 4. Migrate and seed

```bash
php artisan migrate --seed
```

This builds the schema and creates twelve months of realistic demo data:
5 branches, 13 staff accounts, 16 drivers, 63 customers and roughly 800 parcels
with full tracking histories.

> Prefer to import a dump instead? `database/sql/courier_db.sql` contains the
> complete schema and demo data:
> `mysql -u root -p courier_db < database/sql/courier_db.sql`

### 5. Link storage and build assets

```bash
php artisan storage:link
```

```bash
npm run build
```

### 6. Run it

```bash
php artisan serve
```

Open <http://localhost:8000>.

During development, run `npm run dev` in a second terminal for hot reloading.

---

## Demo accounts

All demo accounts use the password **`password`**.

| Role | Email | Sees |
|---|---|---|
| Super Admin | `admin@swifttrack.lk` | Every branch, all staff, all revenue |
| Branch Manager | `manager.colombo@swifttrack.lk` | Colombo branch only |
| Dispatcher | `dispatcher.colombo@swifttrack.lk` | Colombo bookings and dispatch |
| Driver | `driver1@swifttrack.lk` | Only their own deliveries |

Managers and dispatchers also exist for Kandy, Galle, Jaffna and Kurunegala —
for example `manager.kandy@swifttrack.lk`. Drivers run `driver1@` … `driver13@`.

`former.staff@swifttrack.lk` is deactivated on purpose, so the
activate/deactivate behaviour has something to demonstrate.

**Try these to see the roles differ:**

1. Sign in as the Colombo manager and open **Parcels** — no Kandy shipments appear.
2. Sign in as a driver — the sidebar has no Customers, Branches or Reports at all.
3. Copy any tracking number and open `/track/{number}` in a private window —
   it works with no account, but names are masked and addresses withheld.

---

## Feature tour

### Authentication
Registration, login, logout, forgot password, reset password, change password,
and email verification. Login is rate limited per email **and** IP, so one
attacker cannot lock a real user out. A deactivated account is refused at the
credential check, and deactivating someone mid-session ends that session on
their next request.

### User management
Four roles. Super Admins manage everyone; Branch Managers manage only the
dispatchers and drivers in their own branch and cannot promote anyone. The last
active Super Admin cannot be demoted or deactivated — that would lock everybody
out of user administration.

### Customers
Full CRUD with auto-generated customer IDs (`CUS-2026-00042`), search across
name/mobile/email/NIC, status and city filters, pagination, soft delete with
restore, a profile page, and complete shipment history with lifetime spend.

### Drivers
CRUD with photo upload, an optional login account created in the same step,
activate/deactivate, assigned deliveries, and a performance summary (completed,
failed, rejected, success rate, average delivery time, cash collected).
A driver with an expired licence cannot be assigned parcels.

### Branches
CRUD, manager assignment, driver roster, staff list, and a filterable branch
shipments view. A branch with parcels still in transit cannot be archived.

### Parcels
Auto-generated tracking numbers (`SWT-20260722-K4F9C2` — random, not
sequential, so customers cannot guess each other's). Live delivery-charge
quoting from weight, dimensions, priority and parcel type. Photo uploads,
cancellation with a reason, a printable 100 × 150 mm shipping label (HTML and
PDF), and a QR code.

### Shipment tracking
Every parcel carries an append-only timeline recording status, location,
who acted, when, and remarks. The example flow from the specification —
Package Created → Picked Up → Received at Warehouse → Sorted → Dispatched →
Out for Delivery → Delivered — is modelled exactly, with `Sorted` and
`Dispatched` as timeline-only events that do not change the parcel's status.

Customers look parcels up by tracking number at `/track`, with no account.

### Delivery management
A dispatch board lists unassigned parcels (same-day first, then express, then
oldest) beside available drivers. Drivers accept, decline, mark in transit, then
complete or fail. Completion captures the receiver's name, NIC, location,
cash collected, a signature drawn on screen, and a doorstep photo.

Assignment is refused when the parcel is too heavy for the vehicle, the driver's
licence has expired, or they belong to another branch. Reassigning automatically
closes the previous assignment, so two drivers can never hold one parcel.

### Dashboards
Today's shipments and deliveries, pending, delivered, failed, revenue,
available drivers, drivers on delivery, top customers and top drivers, plus
monthly shipments, monthly revenue and a delivery success-rate chart.
Revenue is hidden from dispatchers and drivers.

### Reports
Daily shipments, monthly revenue, driver performance, customer shipments and
deliveries — each filterable by date range and exportable to **CSV, Excel and
PDF**.

---

## Architecture

```
app/
├── Enums/            10 backed enums; each owns its label, colour, icon
│                     and business rules (SLA days, vehicle capacity,
│                     legal status transitions)
├── Models/           Eloquent models with relationships and query scopes
├── Policies/         Per-model authorization, branch-scoped
├── Services/         Business logic — controllers stay thin
│   ├── ParcelService             booking, editing, status transitions
│   ├── DeliveryService           assignment lifecycle
│   ├── TrackingService           the audit timeline
│   ├── DriverAvailabilityService keeps driver status in step with the work
│   ├── PricingService            delivery-charge calculation
│   ├── DashboardService          widgets and chart series
│   ├── ReportService             the five business reports
│   ├── QrCodeService             per-parcel QR codes
│   └── FileUploadService         uploads, replacements, signature data URLs
├── Rules/            SriLankanMobile
├── Http/
│   ├── Controllers/  Web controllers + Api/V1/*
│   ├── Requests/     Form Request validation
│   ├── Resources/    API Resources
│   └── Middleware/   EnsureUserHasRole, EnsureUserIsActive
└── Exports/          One generic spreadsheet export for all five reports
```

### Why a service layer

The web UI and the API both need to book a parcel, and both need it to behave
identically. Putting that logic in `ParcelService` means the status rules and
the tracking timeline exist in exactly one place — the two front ends cannot
drift apart.

### Two notable decisions

**No `Gate::before` super-admin bypass.** A blanket "Super Admin may do
anything" check would silently defeat the rules that protect the system from
itself: a Super Admin could delete their own account, accept a delivery on a
driver's behalf, or edit a parcel that was already delivered. Each policy grants
Super Admins what they should have, explicitly.

**Soft-delete-aware unique indexes.** Customer NIC and mobile, and driver
vehicle and licence numbers, are unique on `(column, deleted_at)` rather than on
the column alone. A plain unique index would let an archived record reserve a
real person's phone number forever.

---

## Database schema

```
branches ──┬─< users ──< (created_by on parcels, customers, trackings)
           ├─< drivers ──< deliveries >── parcels
           ├─< customers ──< parcels
           └─< parcels ──┬─< parcel_trackings
                         └─< parcel_images
```

| Table | Purpose |
|---|---|
| `users` | Staff accounts, one of four roles, pinned to a branch |
| `branches` | Operational units; each has a manager |
| `customers` | Senders, auto-coded `CUS-YYYY-NNNNN` |
| `drivers` | Delivery drivers, optionally linked to a login account |
| `parcels` | Shipments, auto-coded `SWT-YYYYMMDD-XXXXXX` |
| `parcel_trackings` | Append-only audit timeline |
| `parcel_images` | Photos taken at booking or handling |
| `deliveries` | One row per driver assignment attempt |

`users` and `branches` reference each other, so the `branch_id` foreign key on
`users` is added in a follow-up migration once both tables exist.

Every table carries indexes matching the queries that actually run — for
example `parcels(status, created_at)` for the dashboard widgets and
`parcel_trackings(parcel_id, happened_at)` for the timeline.

---

## Testing

```bash
php artisan test
```

**99 tests, 369 assertions.**

| Suite | Covers |
|---|---|
| `SmokeTest` | Every screen renders for the role that owns it, all 15 report exports, PDF label |
| `AuthenticationTest` | Registration, login, throttling, logout, password reset, change password, email verification |
| `AuthorizationTest` | Branch isolation, role capabilities, driver restrictions, last-admin protection |
| `ParcelLifecycleTest` | Status transitions, tracking entries, driver availability, capacity and licence rules, auto-return |
| `Api/ApiSmokeTest` | Token auth, every read endpoint, booking, validation, pagination, public tracking privacy |
| `Unit/SriLankanMobileTest` | Every accepted and rejected phone format |

Tests run against **MySQL**, not in-memory SQLite, because the dashboards and
reports use MySQL-specific SQL (`DATE_FORMAT`, `FIELD`, `TIMESTAMPDIFF`) —
SQLite would happily pass tests that break in production.

Create the test database once:

```bash
mysql -u root -p -e "CREATE DATABASE courier_db_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

---

## API

A token-authenticated REST API lives under `/api/v1`.
**Full reference: [`docs/API.md`](docs/API.md).**

```bash
curl -X POST http://localhost:8000/api/v1/auth/login -H "Accept: application/json" -H "Content-Type: application/json" -d '{"email":"admin@swifttrack.lk","password":"password"}'
```

```bash
curl http://localhost:8000/api/v1/track/SWT-20260722-K4F9C2 -H "Accept: application/json"
```

The tracking endpoint needs no token — that is what a QR-code scan resolves to.

---

## Configuration reference

Business rules live in `config/courier.php` rather than being scattered through
controllers:

| Key | Default | Meaning |
|---|---|---|
| `pricing.base_charge` | 350.00 | Base delivery charge in LKR |
| `pricing.included_weight_kg` | 1.0 | Weight included before surcharges |
| `pricing.per_kg_rate` | 120.00 | Charge per kg above the allowance |
| `pricing.minimum_charge` | 350.00 | Floor for any shipment |
| `delivery.max_attempts` | 3 | Failed attempts before auto-return |
| `uploads.max_parcel_images` | 6 | Photos per parcel |
| `uploads.max_image_kb` | 4096 | Upload size limit |
| `pagination.web` / `.api` | 15 / 25 | Page sizes (`api_max` caps at 100) |
| `registration.requires_approval` | `false` | Set `true` to make new signups need admin activation |

Delivery charge is computed as:

```
(base + weight surcharge + inter-city surcharge) × priority multiplier
  + parcel-type handling fee,  floored at the minimum charge
```

Priority multipliers are Normal ×1.0, Express ×1.5, Same Day ×2.25.
Large light parcels are billed on volumetric weight (L × W × H ÷ 5000).

### Mail

`MAIL_MAILER=log` by default, so password-reset and verification links are
written to `storage/logs/laravel.log` and can be tested without an SMTP server.
Switch to `smtp` and fill the credentials in `.env` to send real mail.

---

## Troubleshooting

**`SQLSTATE[42000]: Specified key was too long`**
You are on MySQL 5.7 with an older row format. `AppServiceProvider` already
calls `Schema::defaultStringLength(191)` to handle this; if it persists, upgrade
MySQL or set `innodb_large_prefix=ON`.

**`Vite manifest not found`**
Run `npm run build` (or `npm run dev` while developing).

**Images and QR codes do not load**
Run `php artisan storage:link`.

**`Class "ZipArchive" not found` during Excel export**
Enable the `zip` extension in `php.ini`.

**Stale config after editing `.env`**

```bash
php artisan optimize:clear
```

---

## Deliverables checklist

| Requirement | Where |
|---|---|
| Complete Laravel source | This repository |
| MySQL export / migration files | `database/migrations/`, `database/sql/courier_db.sql` |
| README with setup instructions | This file |
| API documentation | [`docs/API.md`](docs/API.md) |
| Automated tests | `tests/` — 99 tests |
