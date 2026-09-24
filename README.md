# LMS - Logistics Management System

LMS is a PHP-based logistics management system for planning, monitoring, and controlling transport operations. It gives an organization one workspace for vehicles, drivers, trips, delivery tracking, maintenance, fuel, inventory, procurement, expenses, reports, and role-based access.

The project is built as a separate lightweight MVC application and does not modify the existing Xode application.

The current version is fully database-backed and covers the operational depth a real
carrier needs: customers and the cargo they ship, multi-stop routes, a dispatch process
that refuses an unsafe trip, approvals with an approver on record, a stock ledger,
vehicle compliance documents, invoices that make a trip show a margin, reports that
query live data, and a permission matrix that can actually be edited.

What changed in the 2026-09-20 rebuild, and why, is written up in `README_CHANGES.md`.

## System Overview

LMS is an operational back-office system for an organization that manages transport, fleet assets, deliveries, warehouse stock, procurement and logistics costs. It is not only a landing page or reporting screen; it is a working logistics management workspace where users can sign in, see the modules allowed for their role, create and update records, follow operational status, export reports, receive updates, and keep an audit trail of important actions.

The system covers the daily logistics cycle from transport demand to delivery completion:

1. A staff member or manager creates a transport request.
2. Operations reviews priority, route and required date.
3. A vehicle and driver are assigned through the trips workflow.
4. Deliveries are tracked with status, proof files, signatures and delivered time.
5. Fleet managers follow vehicle availability, driver assignments, fuel and maintenance.
6. Warehouse users manage inventory levels and procurement requests.
7. Finance reviews fuel, expenses, procurement and cost reports.
8. Management reviews dashboards, KPIs, charts, reports and operational risks.
9. The system records notifications and audit logs so actions remain accountable.

## What The System Does

The system helps logistics teams answer daily operational questions:

- Which vehicles are available, on trip, under maintenance, or inactive?
- Which drivers are available and whose licenses need attention?
- Which transport requests are pending, approved, assigned, rejected, or cancelled?
- Which trips and deliveries are currently loading, in transit, delivered, or failed?
- Which vehicles need maintenance and what work orders are open?
- How much fuel is being purchased and by which vehicles?
- Which stock items are low, available, or out of stock?
- Which procurement requests and suppliers are active?
- Which expenses are linked to trips, vehicles, repairs, fuel, or allowances?
- Which reports can management, finance, fleet, warehouse, and operations teams review?

The goal is to make logistics activity visible, controlled, and accountable from one dashboard.

## Logistics Workflow Coverage

| Logistics process | Supported in LMS | Main modules |
| --- | --- | --- |
| User access and responsibility | Yes. Real database users, roles and per-ability permissions, editable at `/permissions`, plus row-level scoping so a driver sees only their own work. | Users, Role permissions |
| Customer relationship | Yes. Customers with type, TIN, contact, payment terms, credit limit and status, linked to every request, trip, shipment and invoice. | Customers |
| Transport demand | Yes. Requests track requester, customer, route, required date, priority, cargo, weight and packages, and carry a real approval with an approver and a reason. | Transport requests |
| Cargo and consignment | Yes. Shipments record what is moved: cargo type and description, packages, weight, volume, declared value, temperature range and a hazardous flag. | Shipments |
| Trip planning and dispatch | Yes. Trips connect the request, customer, route, multi-stop drops, planned and actual times, vehicle and driver, with guards against double booking, over-capacity loads, cold-chain on the wrong vehicle and expired licences. | Trips, Vehicles, Drivers |
| Multi-stop routing | Yes. Trip stops hold an ordered list of pickups, drops, waypoints and checkpoints, each with a contact, planned arrival and status. | Trips |
| Delivery tracking | Yes. Deliveries track recipient, planned and actual time, proof and signature files that can be opened, and on failure a coded reason, attempt number and reschedule date. | Deliveries |
| Fleet control | Yes. Vehicles track plate, make, model, year, chassis, payload and volume capacity, fuel type, ownership, cooling unit, odometer, status and next service. | Vehicles |
| Fleet compliance | Yes. Insurance, inspection, registration, road licence and permits with expiry-driven status and dashboard alerts. | Vehicle documents |
| Driver management | Yes. Drivers track contact details, national ID, licence number, class and expiry, hire date, emergency contact and their linked login. | Drivers |
| Maintenance | Yes. Work orders track type, provider, priority, estimated cost, odometer and schedule, with parts and labour lines rolling up into the actual cost. | Maintenance |
| Fuel control | Yes. Fuel records track vehicle, station, fuel type, litres, unit price, full-tank flag, current and previous odometer, driver, trip and receipt. | Fuel |
| Expense control | Yes. Expenses track category, vehicle and trip, amount, payment method, receipt, and an approval that records who decided and when. | Expenses |
| Warehouse and stock | Yes. Items track SKU, category, unit of measure, minimum and reorder level, unit cost, batch, expiry and storage temperature. | Warehouse |
| Stock movement | Yes. A full ledger of stock in, stock out, transfers, damage, returns and adjustments, with the running balance the item quantity follows. | Stock movements |
| Procurement | Yes. Purchase requests carry itemised lines, a supplier, a receiving warehouse and an expected date; receiving posts the lines into stock. | Procurement, Suppliers |
| Pricing and revenue | Yes. Rate cards per customer and route, invoices with lines, VAT and payments, and a trip margin once an invoice is linked. | Rate cards, Invoices |
| Reports and management visibility | Yes. Eight reports that query live data over a date range with totals and CSV, plus role-specific dashboards and charts. | Dashboard, Reports |
| Notifications and audit | Yes. Notifications raised by real events and markable as read, operational alerts for expiries and low stock, and a searchable audit trail. | Notifications, Audit trail |
| Mobile and integration | Partly. A JSON API serves a driver's own trips and deliveries and can close one; there is no packaged mobile app yet. | API |
| Route distance and GPS | No. Deliberately left for a later phase; see "Not Yet Built". | — |

## Main Features

**Access and security**

- Sign-in against the `users` table with PHP password hashing, a fresh session id at
  every privilege change, and failed attempts counted towards a configurable lockout.
- One-time passwords for new accounts with a forced change at first sign-in, a
  self-service change page, and single-use password reset links.
- CSRF protection on every state-changing POST.
- Permissions held in `role_permissions` and editable per module and per ability
  (view, create, edit, delete, approve) at `/permissions`.
- `users.prvg`: `1` may switch into any role from the top bar, `2` (the default) cannot.
- Row-level scoping, so a driver sees only their own trips, deliveries and fuel.

**Operations**

- Customers, shipments with cargo, weight and temperature requirements, and multi-stop
  routes with a planned arrival per stop.
- A state machine per module, with guards that refuse an unsafe dispatch: no vehicle or
  driver, a vehicle in maintenance, an expired driving licence, a double booking,
  cold-chain cargo on a vehicle with no cooling unit, or a load over capacity.
- Side effects in one transaction: dispatching a trip commits the vehicle and driver,
  moves shipments and deliveries into transit, assigns the originating request and
  notifies the driver; completing it releases everything and reports the on-time result.
- Approvals that record who decided, when, and why a record was rejected.
- Delivery exceptions with coded failure reasons, attempt numbers and rescheduling.
- A stock ledger: the item quantity is the running balance of its movements.
- Vehicle compliance documents with expiry-driven status and dashboard alerts.
- Maintenance parts and labour rolling up into the actual cost of a work order.
- Rate cards, invoices with lines and VAT, and payments, so trips can show a margin.

**Working with records**

- Records addressed by `id`, so renaming one never breaks its link.
- SQL pagination, server-side search, typed filters, sortable columns and a CSV export
  of exactly what the filters show.
- Forms split into titled sections, each field carrying a label, a required marker, a
  unit, a placeholder and help text, with a sticky save bar.
- Record pages with a status badge, a highlight strip, working attachments, a line-item
  editor, related records and a history timeline drawn from the audit trail.
- Soft delete with a required reason; the row stays for the audit trail.
- Reference codes generated automatically when the field is left blank.

**Insight**

- Role-specific dashboards whose KPI cards, charts, attention items and recent trips all
  come from the database, with a "View as table" twin for every chart.
- Eight reports that query live data over a date range, with on-screen totals and CSV.
- Notifications raised by real events, markable as read, plus operational alerts for
  expiring licences and documents, due services, low stock and overdue invoices.
- A searchable, filterable, exportable audit trail at `/audit`.
- A JSON API for a driver's phone: their trips, their deliveries, and closing one.

**Setup**

- MySQL/MariaDB schema with a migration runner; three idempotent seed files.
- Seven automated test suites, each building and dropping its own database.

## Feature Status

| Feature area | Included | Verified by |
| --- | --- | --- |
| Database setup | `scripts/migrate.php` creates or updates the database, applies every migration and loads all three seed files. | `tests/migrate_fresh.php` |
| Sign-in and lockout | Passwords verified with PHP hashing; attempts logged; the account locks and releases itself. | `tests/security.php` |
| Passwords | One-time password at first sign-in, self-service change, single-use reset tokens. | `tests/security.php` |
| CSRF | Every state-changing POST carries and checks a token. | `tests/security.php`, `tests/http.php` |
| Permissions | Read from `role_permissions` per ability; editable at `/permissions`. | `tests/smoke.php`, `tests/seed_integrity.php`, `tests/http.php` |
| Row-level scoping | A driver sees only their own trips, deliveries and fuel, in the UI and the API. | `tests/security.php`, `tests/http.php` |
| Dashboards | KPI cards, charts, attention items and recent trips all come from DB queries. | `tests/http.php` |
| CRUD modules | All 19 modules are generated from `app/Models/Schema.php` with pagination, search, filters, sorting and CSV export. | `tests/smoke.php`, `tests/http.php` |
| Validation | Required fields, option values, relations, numbers, dates, uploads, uniqueness and cross-field rules, all server-side. | `tests/smoke.php` |
| Workflow | Transitions, guards and side effects across requests, trips, deliveries, expenses, procurement, maintenance and invoices. | `tests/workflow.php` |
| Stock ledger | Movements drive the item balance; a movement cannot take stock below zero. | `tests/workflow.php`, `tests/seed_integrity.php` |
| Invoicing | Totals and VAT derived from lines; payments update the status. | `tests/workflow.php`, `tests/seed_integrity.php` |
| File uploads | Stored outside the web root and served through a permission-checked route with path containment. | `tests/security.php`, `tests/http.php` |
| Reports | Eight live queries with a date range, totals and CSV export. | `tests/smoke.php`, `tests/http.php` |
| Notifications | Raised by events, markable as read, plus operational alerts refreshed at sign-in. | `tests/workflow.php`, `tests/seed_integrity.php` |
| Audit trail | Sign-in, record changes, approvals, deletions, downloads and exports, searchable at `/audit`. | `tests/smoke.php`, `tests/http.php` |
| API | Health, identity, the driver's own work, any permitted module, and closing a delivery. | `tests/http.php` |
| Demo history | `scripts/migrate.php --demo` loads optional trend history. | Verified locally. |
| GPS and distance | Not built; deliberately left for a later phase. | See "Not Yet Built" |

## User Roles

The system includes these roles:

- Super Admin: full access to every module.
- Logistics Manager: transport, fleet, delivery, warehouse, procurement, expense, fuel, maintenance, and reports access.
- Fleet Manager: vehicles, drivers, maintenance, fuel, and reports access.
- Warehouse Manager: warehouse, procurement, transport requests, and reports access.
- Driver: assigned trips and deliveries access.
- Finance: fuel, expenses, procurement, and reports access.
- Management: dashboard and reports access.

## Seeded Database Login

Open the home page and use the login panel. The role list, email autofill, and account list come from active records in the `users` table joined to `roles`.

All seeded login users use this password:

```text
password
```

Seeded active users:

| Role | Email |
| --- | --- |
| Super Admin | admin@itec.rw |
| Logistics Manager | aline@itec.rw |
| Fleet Manager | eric@itec.rw |
| Warehouse Manager | nadine@itec.rw |
| Driver | samuel@itec.rw |
| Finance | emmanuel@itec.rw |
| Management | jeanpierre@itec.rw |

## Modules

### Dashboard

Shows role-specific KPI cards, charts, recent trips and operational attention items. Everything is computed by `app/Models/ChartData.php` and drawn by `public/assets/js/lms-charts.js`.

| Role | Charts |
| --- | --- |
| Super Admin | Fleet status, monthly expenses, trips by status, system activity |
| Logistics Manager | Trips per week, delivery completion, request pipeline, deliveries by status |
| Fleet Manager | Fleet status, fuel purchased, fuel by vehicle, maintenance cost, driver licence expiry |
| Warehouse Manager | Stock against minimum level, stock status, stock value by warehouse, purchase requests |
| Finance | Expenses by category, monthly spend, expense approvals, fuel cost by vehicle, procurement by supplier |
| Management | Fleet utilization, trips per month, logistics cost, cost per trip |
| Driver | My trips, my trips per week, my deliveries, licence validity (needs the login linked to a driver profile through `drivers.user_id`) |

### Vehicles

Plate number, make, model, year and chassis; payload and volume capacity, fuel type, ownership and whether a cooling unit is fitted; assigned driver, availability, odometer and next service date. Capacity and the cooling unit are what dispatch checks before letting a load leave.

### Drivers

Personal and contact details, national ID, licence number, class and expiry, hire date, emergency contact, assigned vehicle and the login account the driver signs in with. An expired licence blocks dispatch.

### Transport Requests

Transport demand from the business or a customer: requester, customer, route, required date, priority, cargo description, weight and packages. Approve and Reject are buttons that record who decided and when; approving is what lets a trip be planned against it.

### Trips

The movement itself: the request it fulfils, the customer, the route and its intermediate stops, planned and actual departure and arrival, vehicle, driver and cargo summary. Dispatch, Mark delivered and Cancel run the guards and update everything connected to the trip.

### Deliveries

Each delivery attempt: the trip and shipment it belongs to, recipient and phone, planned and actual time, proof file and signature, and on a failure a coded reason, notes and a reschedule date. Uploaded proof opens through a permission-checked download route.

### Maintenance

Work orders with a maintenance type, provider, priority, estimated cost, odometer reading and schedule, plus a parts and labour table whose total becomes the actual cost when the job is completed. Completing a job returns the vehicle to service.

### Fuel

Fuel purchases by vehicle, station and fuel type, with litres, unit price, a full-tank flag, the current and previous odometer readings, the driver and trip it belongs to, and a receipt. A purchase moves the vehicle odometer forward.

### Expenses

Trip and fleet costs by category, attributed to a vehicle and a trip, with a payment method and a receipt. Approve and Reject record the approver and the time, and tell the person who submitted it.

### Warehouse And Inventory

Stock per warehouse: SKU, category, unit of measure, minimum and reorder levels, unit cost, batch number, expiry date and storage temperature. The quantity is the running balance of the stock ledger rather than a number anyone types over.

### Procurement

Purchase requests with itemised lines, a supplier, a receiving warehouse and an expected date. Approve records the approver; Mark received posts the line quantities into the stock ledger.

### Reports

Eight reports that query live data over a date range and export to CSV: vehicle utilization, fuel consumption, delivery performance (with the on-time rate), maintenance cost as estimate against actual, driver performance, inventory movement, trip profitability, and expense summary. The `reports` table is a catalogue of saved definitions pointing at these queries.

### Users And Permissions

Accounts with role, department, job title, status and privilege. A new account is created with a one-time password that must be changed at first sign-in. The role matrix itself is edited at `/permissions`, per module and per ability.

### Shipments

What is actually being carried: consignee, origin and destination, cargo type and description, packages, weight, volume, declared value, temperature range and a hazardous flag. Cold-chain and weight are enforced at dispatch.

### Customers, Rate Cards And Invoices

The commercial side. Customers hold payment terms and a credit limit; rate cards hold the agreed price per customer and route; invoices carry lines, VAT and payments, and totals are always derived from the lines. Linking an invoice to a trip is what makes trip profitability real.

### Vehicle Documents

Insurance, inspection, registration, road licence and permits, each with an expiry that drives its status and an alert on the fleet dashboard.

### Stock Movements

The stock ledger: every stock in, stock out, transfer, damage, return and adjustment with a quantity, a source document, the person who did it and the running balance afterwards.

### Audit Trail

Every sign-in, record change, approval, deletion, file download and export, searchable and filterable at `/audit` and exportable as CSV.

## Database

The database schema is in:

```text
database/schema.sql
```

The seed data is in:

```text
database/seed.sql
```

The schema creates these tables:

**Fleet** — vehicles, drivers, vehicle_documents, maintenance_orders, maintenance_parts, fuel_records

**Transport** — transport_requests, trips, trip_stops, shipments, deliveries

**Commercial** — customers, rate_cards, invoices, invoice_lines, payments

**Warehouse** — warehouses, inventory_items, stock_movements, suppliers, purchase_requests, purchase_request_lines

**Finance** — expenses

**Access and system** — roles, users, permissions, role_permissions, company_settings, login_attempts, password_resets, notifications, audit_logs, reports, migrations

Every operational table carries a `deleted_at` column: removing a record is a soft
delete, so the audit trail and past reports stay intact.

A third seed file fills everything the 2026-09-20 migrations added:

```text
database/seed_extended.sql
```

It adds customers, rate cards, invoices with lines and payments, shipments with cargo
and temperature ranges, multi-stop routes, vehicle compliance documents, the opening
stock ledger, maintenance parts and purchase lines.

All three seed files are idempotent, so running them again never duplicates a record.
`tests/seed_integrity.php` proves it by running them twice and comparing every count.

## Current Data Implementation

Every module is described once in `app/Models/Schema.php`: its table, its list query,
and each field with a label, type, width, help text and validation rule. Forms, record
pages, list tables, validation, persistence and CSV export are all generated from that,
so a new field is added in one place instead of six.

- `app/Models/LogisticsData.php` — reading and writing, paginated and scoped by role
- `app/Models/Workflow.php` — status transitions, guards and side effects
- `app/Models/StockLedger.php` — stock movements and the item balance that follows them
- `app/Models/ReportData.php` — the eight live report queries
- `app/Models/Permission.php` — the role matrix, read from `role_permissions`
- `app/Models/Settings.php` — currency, tax, alert windows and lockout policy
- `app/Models/Notifier.php` — notifications raised by events, and operational alerts
- `app/Models/Reference.php` — generated reference codes such as `TRP-2026-0007`
- `app/Core/Csrf.php`, `app/Core/Flash.php` — request security and post-redirect messages

Uploaded proofs, signatures, receipts and scanned documents are stored under
`storage/uploads/`, outside the web root, and served through `/files/{module}/{name}`
after a permission check.

## Tech Stack

- PHP MVC structure
- MySQL/MariaDB
- PDO database foundation
- Bootstrap-based admin UI assets
- jQuery DataTables
- Select2
- XAMPP-friendly local setup
- Plain PHP smoke tests

## Project Structure

- `public/index.php` - front controller
- `routers/web.php` - web (HTML) route table
- `routers/api.php` - JSON API route table, reached as `/api/<endpoint>`
- `app/Core/Router.php` - route registration, login/role checks and dispatch
- `bootstrap.php` - configuration, session, role helpers, and autoloading
- `app/Controllers/` - controller classes
- `app/Models/` - data and database model classes
- `app/Views/` - page templates and shared layouts
- `config/config.php` - app and database configuration
- `database/schema.sql` - database schema
- `database/seed.sql` - starter data
- `database/migrations/` - incremental schema updates for existing databases
- `scripts/migrate.php` - migration runner (`--demo` also loads `database/seed_demo.sql`)
- `database/seed_demo.sql` - optional demo history (months of trips, expenses, fuel, maintenance) so dashboard trends have data
- `app/Models/ChartData.php` - dashboard KPI, chart and attention queries
- `public/assets/js/lms-charts.js` - dashboard chart rendering
- `tests/smoke.php` - automated smoke test
- `tests/migrate_fresh.php` - verifies one-command setup on a brand-new database
- `tests/seed_integrity.php` - full seed, role, notification, and workflow integrity test
- `storage/uploads/` - runtime upload location for proofs, signatures, and receipts
- `public/assets/` - CSS, JavaScript, images, fonts, and UI assets

## Run Locally

Through XAMPP, open:

```text
http://localhost/logistics-mvc/
```

The current router uses path URLs, not the old `?route=home` query format. The home page is `/`, the dashboard is `/dashboard`, and modules are paths such as `/vehicles`, `/trips`, and `/reports`.

Or run PHP's built-in server from the project root:

```text
php -S localhost:8080 -t public public/index.php
```

On this XAMPP installation, use the XAMPP PHP executable if the global `php` command points somewhere else:

```text
C:\xampp\php\php.exe -S localhost:8091 -t public public/index.php
```

Then open:

```text
http://127.0.0.1:8091/
```

## Database Setup

Default local database settings:

- Host: `127.0.0.1`
- Database: `logistics_mvc`
- User: `root`
- Password: empty

Initialize or update the database with one command:

```text
C:\xampp\php\php.exe scripts\migrate.php
```

The migration script creates the database if it does not exist, imports `database/schema.sql` when the database is empty, applies every file in `database/migrations/`, then loads `database/seed.sql`. The seed file is idempotent, so running the command again refreshes the starter data without duplicating seeded records.

To load months of demo history so the trend charts have something to show (safe to repeat):

```text
C:\xampp\php\php.exe scripts\migrate.php --demo
```

Manual setup is still possible:

```text
C:\xampp\mysql\bin\mysql.exe -u root < database/schema.sql
C:\xampp\mysql\bin\mysql.exe -u root logistics_mvc < database/seed.sql
```

If your MySQL user has a password, add `-p`:

```text
C:\xampp\mysql\bin\mysql.exe -u root -p < database/schema.sql
C:\xampp\mysql\bin\mysql.exe -u root -p logistics_mvc < database/seed.sql
```

Environment variables can override database settings:

- `LOGISTICS_DB_HOST`
- `LOGISTICS_DB_NAME`
- `LOGISTICS_DB_USER`
- `LOGISTICS_DB_PASS`

See `.env.example` for the expected values.

## Tests

Seven suites cover the system. Each builds its own throwaway database from
`schema.sql` plus every migration plus every seed file, then drops it, so they can be
run in any order and leave nothing behind.

```text
C:\xampp\php\php.exe tests\migrate_fresh.php
C:\xampp\php\php.exe tests\smoke.php
C:\xampp\php\php.exe tests\seed_integrity.php
C:\xampp\php\php.exe tests\workflow.php
C:\xampp\php\php.exe tests\security.php
C:\xampp\php\php.exe tests\http.php
```

| Suite | What it proves |
| --- | --- |
| `migrate_fresh.php` | `scripts/migrate.php` builds the whole database on a bare machine, records every migration, and changes nothing on a second run. |
| `smoke.php` | The seeded logins work, permissions load from the database, every module definition is complete and internally consistent, validation rejects bad input, records are created, updated, found by id and soft deleted, and all eight reports run. |
| `seed_integrity.php` | The seed files are idempotent, every table has data, invoice totals match their lines, the stock ledger agrees with every item balance, and no reference code is duplicated. |
| `workflow.php` | Every status transition, every guard (double booking, cold chain, payload capacity, expired licence, missing reason) and every side effect, plus the stock ledger, invoice totals and trip profitability. |
| `security.php` | CSRF tokens, login lockout and self-release, password rules, single-use reset tokens, driver row-level scoping, SQL identifier allowlists and upload validation. |
| `http.php` | Boots a web server and walks every list, create form, record page, edit form, CSV export, report and API endpoint; then checks that a POST without a CSRF token changes nothing, that a role is refused a module it lacks, that a driver cannot open another driver's trip, and that an upload path cannot escape the uploads folder. |

`tests/support.php` holds the shared harness: `test_database()` builds the throwaway
database the same way `scripts/migrate.php` builds a real one, and `TestRun` collects
assertion failures so a run reports all of them at once rather than stopping at the first.

## Main Routes

Records are addressed by their numeric `id`. A reference code is no longer part of the
URL, so renaming a record never breaks its link and two records may share a name.

**Public**

- `/` - home page and sign-in
- `/login` - sign-in POST
- `/logout` - sign out
- `/forgot-password` - request a password reset link
- `/reset-password/{token}` - set a new password

**Account and workspace**

- `/dashboard` - role-specific dashboard
- `/account` - profile (GET and POST)
- `/account/password` - change password (GET and POST)
- `/notifications/read`, `/notifications/read-all` - mark updates read (POST)
- `/files/{module}/{name}` - a private upload, checked against the module permission

**Modules** — `vehicles`, `drivers`, `vehicle_documents`, `maintenance`, `requests`,
`trips`, `shipments`, `deliveries`, `customers`, `rates`, `invoices`, `fuel`,
`expenses`, `warehouse`, `movements`, `procurement`, `suppliers`, `users`

- `/{module}` - list, with `?q=`, filters, `?sort=`, `?dir=`, `?page=`, `?per_page=`
- `/{module}/create` - create form
- `/{module}` (POST) - save a new record
- `/{module}/{id}` - record page
- `/{module}/{id}/edit` - edit form
- `/{module}/{id}` (POST) - save changes
- `/{module}/{id}/lines` (POST) - save line items (trip stops, invoice lines, parts)
- `/{module}/{id}/toggle` (POST) - switch status from the list
- `/{module}/{id}/delete` (POST) - soft delete, reason required
- `/{module}/{id}/action/{action}` (POST) - a workflow transition
- `/{module}/export/csv` - CSV of exactly what the current filters show

**Reports**

- `/reports` - the report gallery and the saved catalogue
- `/reports/view/{key}` - run a report, with `?from=` and `?to=`
- `/reports/export/{key}` - the same report as CSV
- `/reports/catalogue` - CRUD on the saved report definitions

**Administration**

- `/permissions` - the role matrix (GET and POST)
- `/settings` - company settings (GET and POST)
- `/audit` - searchable audit trail, `/audit/export` for CSV

**API** (JSON; 401 unauthenticated, 403 unauthorised, 419 on a missing CSRF token)

- `/api/health`, `/api/me`
- `/api/my/trips`, `/api/my/deliveries` - the signed-in driver's own work
- `/api/modules/{module}` - one page of any module the caller may view
- `POST /api/deliveries/{id}/complete`, `POST /api/deliveries/{id}/fail`

Under Apache the root `.htaccess` forwards every request to `public/`, so `mod_rewrite`
and `AllowOverride All` are required. The base path comes from `app.base_url` in
`config/config.php`; this workspace defaults to `/logistics-mvc`. If the project folder
is different, set `LOGISTICS_BASE_URL` or update `config/config.php`.

## What Is Implemented

Operational depth:

- Customers, shipments with cargo and temperature requirements, multi-stop routes,
  rate cards, invoices with lines and payments, and a full stock movement ledger.
- Vehicle compliance documents with expiry alerts; maintenance parts and labour rolling
  up into an actual cost; purchase request lines that post into stock on receipt.
- Delivery exceptions with coded failure reasons, attempt numbers and rescheduling.
- Planned versus actual departure and arrival, which is what the on-time rate measures.

Workflow:

- A state machine per module: a status only moves where it is allowed to move.
- Guards that refuse an unsafe dispatch: no vehicle or driver, a vehicle in maintenance,
  an expired driving licence, a double booking, cold-chain cargo on a vehicle with no
  cooling unit, or a load heavier than the vehicle can carry.
- Side effects in one transaction: dispatching a trip commits the vehicle and driver,
  pushes shipments and deliveries into transit, assigns the originating request and
  notifies the driver; completing it releases everything and reports the on-time result.
- Approvals record who decided and when, and tell the person who submitted the record.

Reporting:

- Eight reports that query live data, with a date range, on-screen totals and CSV export.
- Trip profitability compares invoiced revenue against expenses and fuel for that trip.

Security and access:

- CSRF on every state-changing POST; a fresh session id at every privilege change.
- Login lockout after a configurable number of failures, releasing itself after the window.
- One-time passwords for new accounts, a self-service change page and single-use reset links.
- Permissions in the database, editable per role and per ability at `/permissions`.
- Row-level scoping: a driver sees only their own trips, deliveries and fuel.
- Private uploads served through a permission-checked route with path containment.

Data access:

- Records addressed by id, SQL pagination, server-side search, filters and sorting.
- Soft delete on every operational table, with the audit trail intact.
- Indexes on the status, date and `deleted_at` columns the dashboards read.

Interface:

- Sectioned forms with help text, units, required markers and a sticky save bar.
- Record pages with a status badge, highlight strip, working attachments, a line-item
  editor, related records and a history timeline.
- Lists with typed cells, filters, sorting, pagination and a filtered CSV export.
- A searchable audit trail, and notifications raised by real events that can be cleared.

## Not Yet Built

**GPS and distance.** Route distance, live vehicle tracking, map views, litres per
100 km and cost per kilometre are deliberately left for a later phase. The groundwork
is in place: fuel records store the current and previous odometer readings, trips carry
planned and actual times, and the fuel report says where the distance columns will go.

Other candidates: emailing notifications and reset links instead of showing them on
screen, PDF invoice output, a phone-shaped driver screen on top of the existing API,
warehouse-to-warehouse transfers as a single paired movement, and recurring
preventive-maintenance schedules.

## Production Readiness Notes

The system is ready for internal deployment after running the migrations and changing
the seeded passwords. Before exposing it publicly:

- Change every seeded password; the seeded accounts all share one.
- Wire `/forgot-password` to a real mail transport. Without a mailer it shows the reset
  link on screen so an administrator can pass it on, which is fine internally and not
  fine on the public internet.
- Use a production web server configuration rather than XAMPP, and enable HTTPS.
- Use a restricted database user instead of MySQL root.
- Turn off PHP error display and write errors to logs.
- Keep `storage/` outside the web root (it already is) and back it up with the database.
- Confirm `LOGISTICS_BASE_URL` matches the production URL.
- Review `/permissions` for the roles this organisation actually uses.
