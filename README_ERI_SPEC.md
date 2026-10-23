# ERI LMS – build plan against the Full System Specification

Branch: `eri-spec`. Source: *ERI Logistics Management System (LMS) – Full System Specification* (22 Oct 2026, 37 pages).

Decisions taken with the client owner (22 Oct 2026):

- The existing LMS is reshaped, not replaced. Code that already does the job is kept.
- Go-live is a **fresh install** (clean database, opening balances from Tally, section 14/25). The current live site keeps running until then.
- Currencies follow section 14: original amount + rate + base-currency amount in the books, FX gain/loss, group view in the MD's currency. Screens and reports still show money per currency.
- Interface in **English only** (spec item 6 not built).
- **Customer warehousing removed** (storage lots, gate passes, customer cargo in warehouses). The parts store stays.
- Nothing hard-coded: rates, routes, numbering, alert days, approver steps, WhatsApp number, MTN file layout, GPS providers and ledger accounts are all settings or master lists.

Roles (set 2026-10-23): one small fixed set, the same in every company — **Admin** (cross-company, manages users + permissions, creates companies), **Operational Manager** (all operations + drivers/HR + sister requests), **Finance Manager** (verify + accounting + payroll), **Managing Director** (final authorize + reports, scoped to their own company), **Cashier** (pays + reads the books), **Store & garage keeper** (store + job cards), **Driver**, **Declaration Manager** (clearance), plus **Supplier**/**Customer** portals. HR, a separate Mechanic, the Sister-company supervisor and the clearance desk sub-roles were folded in. Only Admin sees across companies, so a new ERI Uganda is fully separate.

Status: `todo` · `doing` · `done`

## Phase 1 – Core

| § | Area | What is built | Status |
|---|------|---------------|--------|
| 3 | Companies, departments | `companies`, `departments`; every operational table carries `company_id`; users belong to a company; MD/Admin switch company or see all | done |
| 4 | Roles | MD, OM, Finance Manager, Cashier, Storekeeper, Mechanic, HR, Driver, Sister-company supervisor, Supplier, Customer, Admin + clearance roles; default permission matrix of §21 (`scripts/permission_matrix.php`) | done |
| 5 | Vehicles and trailers | fleet (tanker/container), trailers with own documents, max load, tank capacity, container sizes, GPS provider + device, service interval (7,000 km), photos, the 9 truck statuses, admin-defined document types, alerts 30/14/3 days and 6,500/7,000 km | done |
| 6 | Drivers and HR | nationality, ID/passport, MoMo number + name or cash, employment status, default truck, login with one-time password, MoMo change approved by OM, active-driver list | done |
| 7 | Routes and trips | routes with legs, border posts, standard fuel per point, road toll, mileage, expected days; trip planner checks documents/service/load/double booking and creates F1; trip events; problems; replace driver/truck; handover F14 | done |
| 8–9 | Request forms + chain | form types F1–F15 (numbering, chain, ledger mapping configurable); Prepared → Verified (posts books) → Authorized (stamp) → Paid; send back with reason; versions; signatures; PDF with totals in words | done |
| 10 | Payments | Cashier "To pay", MoMo batch, MTN bulk file, batch number + proof, failed lines stay open, cash payments with PIN/signature and receipt, cash box, driver "Money received" | done |
| 11 | Fuel | Kigali fill in F1, Mombasa top-up F2 to ERI Kenya, litres confirmed by station, reason for non-standard quantities, consumption check at trip close | done |
| 14 | Accounting | per-company books in base currency, automatic postings, exchange rates, FX gain/loss, period lock, opening balances from Tally (checked by Finance, signed off by MD), manual journals verified by Finance and authorized by the MD before they post | done |
| 16 | Driver app | five buttons, offline queue | done |
| 19 | Notifications | in-app + email + WhatsApp (Business number in settings), user choice of WhatsApp events, reminders until handled | done |
| 20 | Dashboards | one home screen per role with counts; charts and work lists per role, for the company in view | done |
| 21–22 | Admin, audit, controls | approver settings with optional backup + timeout, delegation, two-step login for approvers, session timeout, audit log that cannot be changed (before/after, IP, device); daily database backup (`scripts/backup.php`) | done |

Daily jobs (Task Scheduler / cron): `php scripts/alerts.php` (every 15–60 min), `php scripts/mail.php`, `php scripts/backup.php` (once a day).

## Phase 2 – Operations and clearance

| § | Area | Status |
|---|------|--------|
| 12 | Service request F3 (raised by the system at the service km, or by OM/mechanic/driver) → job card + stores issue note F4 → storekeeper issues (stock down, cost on the truck) → mechanic closes with work and odometer; parts not in stock → draft F5; goods received against F5 (weighted average cost, Dr stock / Cr goods received not invoiced, cleared by F12); stock counts explained and approved; below-minimum and same-part-again alerts; tyre log → F6 and tyre life; crane log → F7 | done |
| 13 | Delivery note F8 (driver app, shortage worked out); OM checks it → a shortage above the customer's tolerance (quantity or %, per customer and cargo) raises F9 for the driver (valued at the customer's price), who can explain it in the app; Finance → MD. Customer invoice F13: Finance picks the customer and period, trips priced from the customer's rate card (per trip / ton / litre / container), the customer's deductions shown; Finance → MD posts and makes the invoice, emailed with PDF and in the portal; a trip is invoiced once | done |
| 17 | Sister-company request F11: a company raises it for its own truck and sends it to another; the receiving OM confirms it (routed to the receiving company, which also sees it), which opens a job card and stores issue note there (a tyre/part matched to the receiving store by name); the storekeeper issues, the garage closes the job, and an inter-company invoice posts both sides — receivable (1170) + income (4300) in the company that did the work, expense on the truck (6000) + payable (2310) in the company that asked, each in its own base currency | done |
| 18 | Purchase orders F5 (chain, no posting — a commitment); supplier portal: accept/decline with reason, invoice F12 against the order with scan; order → received → invoiced matched line by line with differences highlighted; F12 clears "goods received not invoiced" | done (portal + match; shared-load POs pending) |
| 6 | Payroll list per month: active drivers, salary, authorized deductions (F9), net; CSV export; closing the month marks deductions taken and clears the driver receivable (Dr payroll liabilities / Cr driver receivables) | done |
| 23 | Clearance shipment file in 7 steps, daily client update, demurrage, customs entries, permits/bonds, profit per file | todo |

## Phase 3 – GPS

| § | Area | Status |
|---|------|--------|
| 15 | Two GPS providers by API, positions every few minutes, geofences (yard, borders, Mombasa, customers), automatic Departed/Arrived/checkpoints, GPS silent alert | todo |
