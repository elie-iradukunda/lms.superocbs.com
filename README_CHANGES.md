# What changed in the 2026-09-20 rebuild

Written for: the developers who maintain LMS.

This release closes the gaps found in the comparison against Fleetio, Odoo, Track-POD
and ERPNext. Everything listed as missing or partly working was built, with one
deliberate exception noted at the end.

---

## 1. New domain the system did not have

| Area | What exists now | Where |
| --- | --- | --- |
| **Customers** | `customers` with type, TIN, contact, district, payment terms, credit limit and status. Linked to requests, trips, shipments, rate cards and invoices. | `customers` table, `/customers` |
| **Shipments (cargo)** | `shipments` carries what is actually moved: cargo type, description, packages, weight, volume, declared value, **temperature range** and a hazardous flag. | `shipments`, `/shipments` |
| **Multi-stop routes** | `trip_stops` gives a trip an ordered list of pickups, drop-offs, waypoints and checkpoints, each with a contact, a planned arrival and its own status. | `trip_stops`, edited on the trip record page |
| **Revenue and billing** | `rate_cards`, `invoices`, `invoice_lines` and `payments`. Totals and VAT are derived from the lines, never typed. | `/rates`, `/invoices` |
| **Stock ledger** | `stock_movements` records every stock in, stock out, transfer, damage, return and adjustment with a running `balance_after`. The item quantity now *follows* the ledger. | `stock_movements`, `/movements` |
| **Vehicle compliance** | `vehicle_documents` for insurance, inspection, registration, road licence and permits, with expiry-driven status and alerts. | `/vehicle_documents` |
| **Maintenance costing** | `maintenance_parts` (parts, labour, service, consumables) plus `actual_cost` and `odometer_reading` on the work order. | Work order record page |
| **Procurement lines** | `purchase_request_lines` with quantity, unit price and received quantity; receiving posts them into stock. | Purchase request record page |
| **Delivery exceptions** | `failure_reason` (nine coded reasons), `failure_notes`, `attempt_number` and `rescheduled_at`. | `/deliveries` |
| **Plan versus actual** | `trips.planned_departure_at` and `planned_arrival_at` alongside the actual times, which is what on-time performance is measured against. | `/trips` |

## 2. Workflow that used to be manual

`app/Models/Workflow.php` holds the state machine. A transition is only allowed from
the statuses that permit it, guards run before it, and the side effects run inside one
transaction.

**Guards that now block unsafe work**

- Dispatch without a vehicle *and* a driver.
- Dispatching a vehicle that is in maintenance or inactive.
- Dispatching a driver whose licence has expired.
- **Double booking** — a vehicle or driver already out on another running trip.
- **Cold-chain cargo on a vehicle with no cooling unit.**
- **A load heavier than the vehicle's payload capacity.**
- Issuing an invoice with no lines.
- Receiving a purchase request with no receiving warehouse.
- Any rejection, cancellation or delivery failure without a reason.

**Side effects that now happen by themselves**

Dispatching a trip sets the vehicle and driver to *on trip*, stamps the dispatch time,
pushes shipments and deliveries into transit, moves the originating request to
*assigned* and notifies the driver. Completing it releases both, stamps arrival,
marks the shipments delivered and reports whether it was on time. Cancelling releases
them and returns the request to *approved*.

Approving an expense, request, purchase or work order records **who** decided and
**when**, and tells the person who submitted it. Receiving goods posts the purchase
lines into the stock ledger. Completing a work order rolls its parts and labour up
into `actual_cost` and returns the vehicle to service.

## 3. Reports are real queries

`app/Models/ReportData.php` replaces the catalogue-of-names with eight live reports:
vehicle utilization, fuel consumption, delivery performance (with on-time rate),
maintenance cost (estimate versus actual), driver performance, inventory movement,
**trip profitability** (invoice revenue against expenses plus fuel) and expense summary.

Each takes a date range, renders on screen and exports to CSV with its period and
totals. The `reports` table is now a catalogue that points at one of these keys.

## 4. Security

| Was | Now |
| --- | --- |
| No CSRF protection at all | `Core\Csrf`; every state-changing POST is checked, and the token rotates at login |
| Session id reused across login | `session_regenerate_id(true)` on login, logout and password change |
| No rate limiting; `status = 'locked'` never set | Failed attempts counted in `login_attempts`; the account locks for a configurable window and unlocks itself |
| New users hard-coded to `password`, no way to change it | One-time password with a forced change at first login, a self-service change page, and single-use reset tokens |
| Profile and Settings were `href="#"` | `/account`, `/account/password`, `/settings` |
| Role map hard-coded in `bootstrap.php` | `permissions` + `role_permissions`, edited at `/permissions`, checked per ability (view, create, edit, delete, approve) |
| **Any driver could list every trip in the company** | Row-level scoping: a driver sees only their own trips, deliveries and fuel, in the UI and the API |
| Uploads could be written but never read | `/files/{module}/{name}` with a permission check and path containment |

## 5. Data access

- **Records are addressed by `id`**, not by name. Two people called Jean Bosco no
  longer share a URL, renaming no longer breaks a link, and deleting by name can no
  longer remove the wrong row.
- **Pagination in SQL** with per-page choice, server-side search, typed filters and
  sortable columns. `find()` no longer loads the whole table.
- **Soft delete** — `deleted_at` on every operational table; the audit trail keeps
  working and reports stay consistent.
- **Indexes** on status, date and `deleted_at` columns used by the dashboards.
- **Auto references** (`TRP-2026-0007`) generated when the field is left blank.

## 6. Interface

Forms and record pages are generated from one schema (`app/Models/Schema.php`), so
each of the 19 modules gets the same treatment:

- Forms are split into titled sections with a hint, each field carrying a label, a
  required marker, a unit suffix, a placeholder and help text; a sticky save bar; a
  sidebar summarising the record; searchable dropdowns for relations.
- Record pages open with a status badge, a highlight strip of the numbers that matter
  for that module, the fields grouped as on the form, attachments that actually open,
  the line-item editor, related records (shipments on a trip, invoices for a customer,
  movements for an item) and a history timeline from the audit trail.
- Lists show typed cells (badges, money, expiry countdowns, ratings), filters, search,
  sorting, pagination and a filtered CSV export.
- Workflow buttons replace editing a status dropdown by hand, with a reason prompt
  where one is required.

Also new: `/audit` (searchable, filterable, exportable), notifications that are
*created by events* and can be marked read, and operational alerts refreshed at login
for expiring licences, expiring vehicle documents, due services, low stock and
overdue invoices.

## 7. API

`/api/health`, `/api/me` (with the permission matrix), `/api/my/trips`,
`/api/my/deliveries`, `/api/modules/{module}` and
`POST /api/deliveries/{id}/{complete|fail}` — enough for a driver's phone to see the
day's work and close a delivery. Scoped exactly like the web screens.

## 8. Tests

| File | Covers |
| --- | --- |
| `tests/migrate_fresh.php` | A fresh install from nothing; a second run changes nothing |
| `tests/smoke.php` | Accounts, permissions, every module definition, validation, CRUD, soft delete, all eight reports |
| `tests/seed_integrity.php` | Seeds are idempotent; derived totals are correct; the ledger and item balances agree; no duplicate references |
| `tests/workflow.php` | Every transition, every guard, every side effect, the stock ledger, invoice totals and trip profitability |
| `tests/security.php` | CSRF, lockout, password rules, reset tokens, driver scoping, SQL identifier allowlists, upload validation |
| `tests/http.php` | Boots a server and walks every page, form, report and API endpoint; checks CSRF refusal, permission denial and path traversal |

Run them all:

```text
C:\xampp\php\php.exe tests\migrate_fresh.php
C:\xampp\php\php.exe tests\smoke.php
C:\xampp\php\php.exe tests\seed_integrity.php
C:\xampp\php\php.exe tests\workflow.php
C:\xampp\php\php.exe tests\security.php
C:\xampp\php\php.exe tests\http.php
```

## 9. Deliberately left for later

**GPS and distance.** Route distance, live vehicle tracking, map views, litres per
100 km and cost per kilometre are not built, as agreed. Everything they need is in
place: `fuel_records` already stores `mileage` and `previous_mileage`, trips carry
planned and actual times, and the fuel report notes where the distance-based columns
will appear. Adding a `distance_km` column to `trips` and a coordinate pair to
`trip_stops` is the whole remaining change.

## 10. Upgrading an existing install

```text
C:\xampp\php\php.exe scripts\migrate.php
```

Three migrations are applied (`20260920_000005` to `20260920_000007`) and a new seed
file, `database/seed_extended.sql`, fills the new tables. Nothing existing is dropped.

**Before production:** change every seeded password, put the app behind HTTPS, use a
restricted database user rather than `root`, configure backups, and wire
`/forgot-password` to a real mail transport — it currently shows the reset link on
screen because no mailer is configured.
