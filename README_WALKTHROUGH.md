# Rwanda Cargo Link Ltd — the first week on LMS

Written for: the person sitting in front of the system for the first time.

This is a guided run through every page, in the order a real company would meet them.
You play a new Kigali haulier, **Rwanda Cargo Link Ltd**, setting the system up and
running one complete job through it — from the day the company is created to the day
the accountant closes the books on that job.

Each step says exactly what to type and exactly what should appear. Work straight down;
later steps use records you create earlier.

---

# Part 1 — What this system actually is

LMS is the office of a transport company, put on a screen.

A haulier's day has five things happening at once, and they are usually kept in five
different places — a WhatsApp group, a notebook, a spreadsheet, a pile of receipts and
the accountant's own file. LMS holds all five in one place, and, more importantly,
**connects them**, so a fact entered once is not typed again anywhere else.

### The five things it holds

| What | What that means in practice |
| --- | --- |
| **The fleet** | Which vehicles exist, what each can carry, who drives it, when it was serviced, whether its insurance is still valid. |
| **The work** | Who asked for transport, what the cargo is, which vehicle and driver were given the job, where it is now, whether it arrived on time. |
| **The warehouse** | What is in each store, what came in, what went out, what needs reordering, what was bought from whom. |
| **The money** | What was spent on fuel, allowances and repairs; what was charged to the customer; what has been paid; what is still owed. |
| **The books** | The same money again, but in double entry: a journal, a general ledger, a trial balance, a profit and loss, a balance sheet and a cash flow statement. |

### What it actually does for you

- **It refuses the mistakes that cost money.** You cannot send a vehicle that is already
  out on another trip. You cannot put chilled cargo on a truck with no cooling unit. You
  cannot load five tonnes onto a truck rated for one. You cannot dispatch a driver whose
  licence expired last month.
- **It does the follow-on work itself.** Dispatching one trip marks the vehicle and the
  driver as out, puts the shipment and the delivery into transit, closes off the request
  that asked for it, and tells the driver — five changes from one button.
- **It keeps the books without an accountant typing them.** Issue an invoice and the
  ledger debits receivables and credits revenue and VAT. Record the payment and the bank
  account moves. Approve an expense and it lands in the right cost account. By the end
  of the month the trial balance is already written.
- **It remembers who did what.** Every approval carries a name and a time; every deletion
  carries a reason; every sign-in, export and file download is in the audit trail.
- **It shows each person only their own work.** A driver signing in sees the trips
  assigned to them, not the whole company's operation.

### What is not in it yet

Route distance and GPS tracking. There is no map and no live vehicle position, so
kilometres travelled, litres per 100 km and cost per kilometre are not calculated. That
is the next phase; everything it needs is already in place.

---

# Part 2 — Before you start

**Load the database**

```text
cd C:\xampp\htdocs\logistics-mvc
C:\xampp\php\php.exe scripts\migrate.php
```

It should finish with:

```text
Loaded database/seed_accounting.sql
Database setup complete.
```

**Start Apache in XAMPP**, then open `http://localhost/logistics-mvc/`.

**About the data already in there.** The system ships with a demo company so nothing is
empty on your first look. Everything you create in this walkthrough is added alongside
it. Wherever a total would be confusing because of the demo records, this guide tells
you to search for your own reference instead.

**The accounts.** All use the password `password`.

| Role | Email | Their job in this walkthrough |
| --- | --- | --- |
| Super Admin | admin@itec.rw | Sets the company up and creates the team |
| Fleet Manager | eric@itec.rw | Registers the truck, its insurance and its service |
| Warehouse Manager | nadine@itec.rw | Buys and stores the pallets |
| Logistics Manager | aline@itec.rw | Wins the customer and runs the job |
| Driver | aline@itec.rw once linked to Olivier | Drives it and delivers it |
| Finance | emmanuel@itec.rw | Bills it, banks it and closes the books |
| Management | jeanpierre@itec.rw | Reads the result |

---

# DAY 1 — The Super Admin sets the company up

Sign in as **admin@itec.rw** / `password`.

## 1.1 The dashboard — read it before touching anything

**Expect:** a green bar welcoming you, and the page in four bands.

| Band | What it is telling you |
| --- | --- |
| Four cards at the top | Total vehicles, active trips, pending requests, money spent this month. These are counts from the database, not decoration. |
| Four charts | Fleet status, monthly spend, trips by status, system activity. Each has a **View as table** button, because a chart you cannot read the numbers off is half a chart. |
| Recent Trips and Deliveries | The last six movements, with route, vehicle, driver and status. |
| Operational attention | What needs a person today: services due, licences expiring, stock below minimum, approvals waiting. |

- **Do:** click **View as table** on the first chart.
- **Expect:** the chart is replaced by the same figures as a table. Click again to go back.
- **Do:** click the moon or sun icon at the top right.
- **Expect:** the whole interface switches to dark, and stays dark as you move around.

## 1.2 Company settings — make it your company

This comes first because everything else prints the name you set here.

- **Do:** avatar menu (top right) → **Company settings**.
- **Expect:** five groups: Accounting, Company, Finance, Operations and Security.
- **Do:** set these:

  | Setting | Value |
  | --- | --- |
  | Company name | `Rwanda Cargo Link Ltd` |
  | TIN number | `112233445` |
  | Phone | `+250 788 300 400` |
  | Email | `operations@rwandacargolink.rw` |
  | Address | `KN 3 Rd, Nyarugenge, Kigali` |
  | Currency code | `RWF` |
  | Default VAT rate (%) | `18` |
  | Invoice number prefix | `INV` |
  | Default payment terms (days) | `30` |
  | Driver licence alert window (days) | `90` |
  | Vehicle document alert window (days) | `30` |
  | Failed logins before lockout | `5` |

- **Do:** click **Save settings**.
- **Expect:** a green bar, *"21 setting(s) saved."* The form sends every setting on the
  page, not only the ones you touched, so the count is the page total.
- **Do:** look at the footer at the bottom of any page.
- **Expect:** it now reads **Rwanda Cargo Link Ltd · Logistics Management System**.
- **Do:** put the word `abc` into **Default VAT rate (%)** and save.
- **Expect:** a red bar, *"tax rate must be a number."* The bad value is not saved.
  Put `18` back.

## 1.3 Users — create the team

- **Do:** Administration → **Users**.
- **Expect:** the seven seeded accounts, with role, department, last sign-in and privilege.
- **Do:** click **Add user** and fill in:

  | Field | Value |
  | --- | --- |
  | Full name | `Claudine Mukamana` |
  | Email | `claudine@rwandacargolink.rw` |
  | Phone | `+250 788 300 411` |
  | Role | `Logistics Manager` |
  | Department | `Operations` |
  | Job title | `Dispatch Officer` |
  | Status | `Active` |
  | Privilege | `Standard` |
  | Force password change at next login | **on** |

- **Expect:** a green bar *"User created."*, and under it an **amber bar showing a
  one-time password** such as *"One-time password for claudine@rwandacargolink.rw:
  MusanzeCargo5705 …"*. **Write it down now** — it is never shown again and is not
  stored anywhere in readable form.

**Now prove the rules work:**

- **Do:** click **Add user** again and use the same email `claudine@rwandacargolink.rw`.
- **Expect:** the form returns with a red box: *"Email "claudine@rwandacargolink.rw" is
  already used by another record."*
- **Do:** click **Add user** and press **Create user** with everything blank.
- **Expect:** *"Full name is required. Email is required. Role is required. Status is
  required."*
- **Do:** put `claudine-at-work` in Email and save.
- **Expect:** *"Email is not a valid email address."* Click **Cancel**.

**Test the one-time password:**

- **Do:** sign out. Sign in as `claudine@rwandacargolink.rw` with the one-time password.
- **Expect:** you land straight on **Change password** with an amber warning. Try to open
  `http://localhost/logistics-mvc/vehicles` in the address bar — you are pushed back.
  Nothing else in the system is reachable.
- **Do:** current = the one-time password, new `CargoLink2026` twice, save.
- **Expect:** *"Your password was changed."* and the full system opens.
- **Do:** sign back in as admin.

**Now edit and then remove her:**

- **Do:** Users → open **Claudine Mukamana** → **Edit**. Change Job title to
  `Senior Dispatch Officer`, save.
- **Expect:** *"User updated."* and the record page shows the new title.
- **Do:** on the list, click the red bin on her row.
- **Expect:** a dialog demanding a reason. Press **Delete record** with it empty — the
  box turns red and nothing happens.
- **Do:** type `Created while learning the system` and confirm.
- **Expect:** *"User removed. It stays in the audit trail."* She is gone from the list —
  but the row still exists in the database with a `deleted_at` stamp, which is why
  Part 12 can still find her history.

## 1.4 Role permissions — decide who may do what

- **Do:** Administration → **Role permissions** → **Driver** tab.
- **Expect:** Driver holds Dashboard (view), Trips (view, edit), Shipments (view),
  Deliveries (view, edit, approve), Fuel (view, create). Everything else is unticked —
  that is why a driver has such a short sidebar.
- **Do:** click **Super Admin**.
- **Expect:** everything ticked and greyed out, with a note that Super Admin cannot be
  restricted so an installation can never lock itself out.
- **Do:** click **Warehouse Manager**, tick **Customers → view**, save.
- **Expect:** *"Permissions for Warehouse Manager were saved."*
- **Do:** sign in as `nadine@itec.rw`.
- **Expect:** a **Commercial → Customers** entry now appears in her sidebar, which was
  not there before. Open it: she can read the list but there is no **Add customer**
  button and no edit or delete icons, because only *view* was granted.
- **Do:** sign back in as admin and untick it again.

---

# DAY 1 (afternoon) — The Fleet Manager builds the fleet

Sign out, sign in as **eric@itec.rw** / `password`.

**Expect:** a shorter sidebar — Dashboard, Fleet management (Vehicles, Drivers,
Maintenance, Vehicle documents), Transport → Trips, Finance → Fuel management, Reports.
No Commercial, no Warehouse, no Administration.

## 2.1 Register the first truck

- **Do:** Fleet management → **Vehicles** → **Register vehicle**:

  | Field | Value |
  | --- | --- |
  | Plate number | `RAE 145C` |
  | Vehicle type | `Refrigerated truck` |
  | Make | `Isuzu` |
  | Model | `Forward FVR` |
  | Year of manufacture | `2022` |
  | Chassis number | `IS-FVR-145C` |
  | Payload capacity | `4000` |
  | Volume capacity | `24` |
  | Fuel type | `Diesel` |
  | **Cold chain unit fitted** | **on** |
  | Ownership | `Owned` |
  | Acquired on | `2026-03-15` |
  | Assigned driver | leave blank for now |
  | Status | `Available` |
  | Odometer reading | `18500` |
  | Next service due | 25 days from today |
  | Notes | `Main cold-chain unit for the Rubavu run.` |

- **Expect:** *"Vehicle created."* and a record page headed **RAE 145C** with a green
  **Available** badge and a strip reading Odometer `18,500 km`, Payload `4,000 kg`,
  Driver `Unassigned`, Next service.
- **Notice** the form was in four labelled sections with a sentence under each heading,
  not one long column of boxes. Every field carries its unit (`kg`, `km`, `m3`).

## 2.2 Register a second truck, and prove the plate is unique

- **Do:** **Register vehicle** again: plate `RAE 260D`, type `Box truck`, make `Hino`,
  model `500 Series`, payload `5000`, volume `30`, **Cold chain unit fitted OFF**,
  status `Available`, odometer `41200`.
- **Expect:** created. You now have two trucks: one chilled, one not. Part 5 uses the
  difference.
- **Do:** try to register a third with plate `RAE 145C`.
- **Expect:** *"Plate number "RAE 145C" is already used by another record."*

## 2.3 Insurance — and watch the status set itself

- **Do:** Fleet management → **Vehicle documents** → **Add document**:

  | Field | Value |
  | --- | --- |
  | Reference | leave blank |
  | Vehicle | `RAE 145C` |
  | Document type | `Insurance` |
  | Document number | `RAD-2026-55012` |
  | Issuer / insurer | `Radiant Insurance` |
  | Issued on | `2026-03-20` |
  | Expires on | **20 days from today** |
  | Cost | `940000` |
  | Status | `Valid` |

- **Expect:** created — but the badge does **not** say Valid as you typed. It reads
  **Expiring**, because the system recalculated it from the expiry date against your
  30-day alert window.
- **Do:** open the Vehicle documents list.
- **Expect:** the **Expires** column shows the date followed by an amber badge like
  `20d`, counting down.
- **Do:** **Edit** it and set **Expires on** to a date last month, save.
- **Expect:** the status becomes **Expired** and the list badge turns red,
  e.g. `30d late`. Set it back to 20 days ahead.
- **Do:** add a second document — Vehicle `RAE 145C`, type `Inspection`,
  number `INS-2026-8890`, expires **10 months from today**, cost `45000`.
- **Expect:** this one stays **Valid**, because it is outside the alert window.
- **Do:** open **RAE 145C**.
- **Expect:** its **Compliance documents** panel lists both.

## 2.4 The driver

- **Do:** Fleet management → **Drivers** → **Add driver**:

  | Field | Value |
  | --- | --- |
  | Full name | `Olivier Kwizera` |
  | National ID | `1199080077889900` |
  | Date of birth | `1990-07-14` |
  | Phone | `+250 788 610 220` |
  | Address | `Kicukiro, Kigali` |
  | Licence number | `RWA-DL-2201` |
  | Licence class | `C` |
  | Licence expiry | **2027-06-30** |
  | Hired on | `2026-04-01` |
  | Linked login account | `Aline Mukamana` |
  | Status | `Available` |
  | Emergency contact | `Jeanne Kwizera` |
  | Emergency phone | `+250 788 610 300` |

  > Linking the login is what lets a driver sign in and see only their own work.
  >
  > **One login, one driver.** `samuel@itec.rw` is already linked to the seeded driver
  > Samuel Niyonzima, so choosing it here is refused with *"Linked login account
  > "Samuel Niyonzima" is already used by another record."* Try it once to see the
  > message name the exact field, then pick `Aline Mukamana`, which is free.
  >
  > Part 6 signs in as `aline@itec.rw` because that is now Olivier's login.

- **Expect:** *"Driver created."*, with a strip showing Licence, Expires, Phone and Login.
- **Do:** try to add another driver with licence `RWA-DL-2201`.
- **Expect:** *"Licence number RWA-DL-2201 is already registered to another driver."*
- **Do:** open **RAE 145C** → **Edit**, set **Assigned driver** to `Olivier Kwizera`, save.
- **Expect:** the vehicle record now shows Driver `Olivier Kwizera`, and the Drivers list
  shows `RAE 145C` in Olivier's **Vehicle** column. One edit, both sides updated.

## 2.5 A service, costed properly

- **Do:** Fleet management → **Maintenance** → **Create work order**:

  | Field | Value |
  | --- | --- |
  | Work order reference | leave blank |
  | Vehicle | `RAE 145C` |
  | Service | `Pre-season cold-chain service` |
  | Maintenance type | `Preventive` |
  | Priority | `Normal` |
  | Service provider | `Kigali Auto Care` |
  | Estimated cost | `120000` |
  | Odometer at service | `18500` |
  | Status | `In progress` |
  | Due date | today |

- **Expect:** created, with Estimated `RWF 120,000` and Actual **Not costed**.
- **Do:** open Vehicles → **RAE 145C**.
- **Expect:** its status changed by itself from Available to **Maintenance**, because a
  work order went in progress. Nobody typed that.
- **Do:** back on the work order's record page, scroll to the card headed
  **Parts and labour** and fill three rows:

  | Type | Description | Part number | Qty | Unit cost |
  | --- | --- | --- | --- | --- |
  | Consumable | `Engine oil 15W40` | `OIL-15W40` | `15` | `3200` |
  | Part | `Cold-chain gas top-up` | `GAS-404A` | `1` | `22000` |
  | Labour | `Technician, 1 day` | | `1` | `15000` |

  Click **Save parts and labour**.
- **Expect:** each row shows its own line total.
- **Do:** click **Approve cost**.
- **Expect:** *"Work order … completed at RWF 85,000. RWF 35,000 under the estimate."*
  That 85,000 is 15 × 3,200 + 22,000 + 15,000 — worked out from your lines, not typed.
- **Do:** open **RAE 145C**.
- **Expect:** its status has returned to **Available** on its own.

> **Remember RWF 85,000.** In Part 9 you will find it in the ledger, debited to
> *Vehicle maintenance and repairs*, without anyone posting it.

---

# DAY 2 — The Warehouse Manager stocks up

Sign out, sign in as **nadine@itec.rw** / `password`.

## 3.1 A supplier

- **Do:** Warehouse → **Suppliers** → **Add supplier**:

  | Field | Value |
  | --- | --- |
  | Supplier code | leave blank |
  | Supplier name | `Kigali Pallet Works` |
  | Category | `Packaging` |
  | TIN number | `998877665` |
  | Contact person | `Innocent Habyarimana` |
  | Phone | `+250 788 220 118` |
  | Email | `sales@kigalipallets.rw` |
  | Address | `Gikondo Industrial Park` |
  | Payment terms | `30` |
  | Rating | `4 - Good` |
  | Status | `Active` |

- **Expect:** created, with a four-star rating showing in the list.

## 3.2 A stock item

- **Do:** Warehouse → **Inventory** → **Add item**:

  | Field | Value |
  | --- | --- |
  | SKU | leave blank |
  | Item name | `Euro pallet 1200x800` |
  | Category | `Packaging` |
  | Warehouse | `Kigali Central Warehouse` |
  | Quantity on hand | `0` |
  | Unit of measure | `Unit` |
  | Minimum level | `40` |
  | Reorder quantity | `100` |
  | Status | `Out of stock` |
  | Unit cost | `12500` |

- **Expect:** created as **SKU-2026-0001** (generated because you left it blank), a red
  **Out Of Stock** badge, and On hand `0 Unit`, Minimum `40`, Stock value `RWF 0`.
- **Expect:** the **Stock movements** panel says nothing has moved yet.

## 3.3 Buy them — a purchase request with real lines

> **Where line editors live.** Trip stops, purchase lines, maintenance parts and
> invoice lines are all edited on the record's **own page**, below its field sections —
> never on the Edit form. A line belongs to a record that already exists, so it needs
> something to hang from: create the record first, then add its lines. The Edit form
> says so in its right-hand panel.

- **Do:** Warehouse → **Procurement** → **New purchase request**:

  | Field | Value |
  | --- | --- |
  | Request reference | leave blank |
  | Description | `First pallet order for the Rubavu run` |
  | Category | `Packaging` |
  | Requested by | `Nadine Tuyisenge` |
  | Supplier | `Kigali Pallet Works` |
  | Deliver to warehouse | `Kigali Central Warehouse` |
  | Expected date | 5 days from today |
  | Amount | `0` |
  | Status | `Draft` |

- **Expect:** created as **PR-2026-0001**.
- **Do:** on the record page that just opened, scroll to the card headed
  **Requested items** and fill the first row only: Item `Euro pallet 1200x800`,
  Qty `120`, Unit `Unit`, Unit price `12500`, Stock item `Euro pallet 1200x800`.
  Click **Save requested items**.
- **Expect:** *"Requested items saved."* and the **Amount** on the record has changed by
  itself from `RWF 0` to **RWF 1,500,000**. The blank rows were ignored.
- **Expect:** there is **no Approve button** on this page. Warehouse may raise a purchase
  request but not approve its own spending — Finance does that in Part 7.

## 3.4 Record what actually arrived

Suppose 120 pallets arrive but you only count 118 onto the racks.

- **Do:** Warehouse → **Stock movements** → **Record movement**:

  | Field | Value |
  | --- | --- |
  | Movement reference | leave blank |
  | Item | `Euro pallet 1200x800` |
  | Warehouse | `Kigali Central Warehouse` |
  | Movement type | `Stock in` |
  | Quantity | `118` |
  | Unit cost | `12500` |
  | Moved at | today, `09:00` |
  | Source document type | `Purchase request` |
  | Source document reference | `PR-2026-0001` |
  | Notes | `120 ordered, 118 received; 2 broken in transit` |

- **Expect:** created, with **Balance after 118**.
- **Do:** open Inventory → **Euro pallet 1200x800**.
- **Expect:** On hand is now **118**, the badge changed by itself from **Out Of Stock**
  to **In Stock**, and Stock value reads **RWF 1,475,000**.

## 3.5 Issue some out, and watch the reorder flag

- **Do:** Record another movement: same item, **Stock out**, quantity `80`,
  Source document type `Trip`, reference `Rubavu run`, moved at today `14:00`.
- **Expect:** Balance after **38**. Open the item: On hand `38`, and because 38 is under
  the minimum of 40 the badge has turned amber **Reorder** on its own.
- **Do:** click the bell.
- **Expect:** an update titled **Stock below minimum** naming the pallets.

## 3.6 The ledger will not let stock go negative

- **Do:** Record a movement: same item, **Stock out**, quantity `500`.
- **Expect:** the form returns with *"Only 38 is on hand; this movement would take the
  balance below zero."* Nothing is saved.

## 3.7 Search, filter, sort, page and export

- **Do:** on Inventory, type `pallet` in the search box, press **Apply**.
- **Expect:** only your item. The count line above the table updates.
- **Do:** set the status filter to **Reorder**.
- **Expect:** only items at or below minimum.
- **Do:** click the **On hand** heading, then click it again.
- **Expect:** the rows re-sort, with an arrow beside the heading showing the direction.
- **Do:** set the page size to `10`.
- **Expect:** pagination appears with *Page 1 of N*.
- **Do:** click **Export CSV**.
- **Expect:** the file contains **only the filtered rows**, with the same columns as the
  screen.
- **Do:** click **Clear**.

---

# DAY 2 (afternoon) — The Logistics Manager wins the customer

Sign out, sign in as **aline@itec.rw** / `password`.

## 4.1 The customer

- **Do:** Commercial → **Customers** → **Add customer**:

  | Field | Value |
  | --- | --- |
  | Customer code | leave blank |
  | Customer name | `Bralirwa Distribution Ltd` |
  | Type | `Corporate` |
  | TIN number | `100200300` |
  | Contact person | `Yvonne Ingabire` |
  | Phone | `+250 788 440 900` |
  | Email | `logistics@bralirwadist.rw` |
  | Address | `Gisenyi Depot Road` |
  | District | `Rubavu` |
  | Payment terms | `30` |
  | Credit limit | `20000000` |
  | Status | `Active` |
  | Notes | `Chilled beverages; deliveries must arrive before 14:00.` |

- **Expect:** created as **CUS-2026-0006**, with a green **Active** badge. The **Rate
  cards**, **Invoices** and **Recent trips** panels all correctly say nothing is linked
  yet.
- **Do:** put `not-an-email` in Email and save.
- **Expect:** *"Email is not a valid email address."* Put the real one back.

## 4.2 The agreed price

**First, notice what is missing.** Still signed in as Aline, open Commercial →
**Rate cards**.

- **Expect:** the list opens and she can read every rate — but there is **no Add rate
  button**, and no edit or delete icons on any row. Only the eye.

That is not a fault. In the permission set the system ships with, **pricing belongs to
Finance**: a Logistics Manager may see what was agreed with a customer, but may not
change what the company charges. Administration → **Role permissions** → the
**Logistics Manager** tab shows it — *Rate cards* has only **view** ticked.

You have two ways forward. Pick one.

### Either — let Finance set the price (what the system expects)

- **Do:** sign out and sign in as **emmanuel@itec.rw** / `password`.
- **Do:** Commercial → **Rate cards** → **Add rate**.

### Or — decide that your dispatchers do quote prices

- **Do:** as **admin@itec.rw**, open Administration → **Role permissions** →
  **Logistics Manager**, tick **Rate cards → create** and **edit**, and save.
- **Expect:** *"Permissions for Logistics Manager were saved."*
- **Do:** sign back in as **aline@itec.rw** and open Commercial → **Rate cards**.
- **Expect:** the **Add rate** button is now there, and so are the edit icons. The change
  took effect on the next page load, with no restart and no code change.

Either way, now create the rate:

- **Do:** **Add rate**:

  | Field | Value |
  | --- | --- |
  | Rate reference | leave blank |
  | Customer | `Bralirwa Distribution Ltd` |
  | Status | `Active` |
  | Origin | `Kigali` |
  | Destination | `Rubavu` |
  | Vehicle type | `Refrigerated truck` |
  | Charging basis | `Per trip` |
  | Rate amount | `520000` |
  | Minimum charge | `480000` |
  | Effective from | first day of this month |
  | Effective to | `2026-12-31` |

- **Expect:** created as **RATE-2026-0006**.
- **Do:** **Edit** it and set **Effective to** to a date before **Effective from**, save.
- **Expect:** *"The rate cannot expire before it becomes effective."* Put it back.
- **Do:** open the customer again.
- **Expect:** the **Rate cards** panel now lists it.

> **The general rule.** Wherever a button you expected is missing, it is a permission,
> not a bug. Administration → **Role permissions** shows exactly what each role holds,
> and changing a tick there changes the interface immediately.

## 4.3 The customer asks for a truck

If you took the Finance route above, sign back in as **aline@itec.rw** now.

- **Do:** Transport → **Transport requests** → **New request**:

  | Field | Value |
  | --- | --- |
  | Request reference | leave blank |
  | Requested by | `Aline Mukamana` |
  | Customer | `Bralirwa Distribution Ltd` |
  | Priority | `High` |
  | Pickup location | `Kigali Central Warehouse` |
  | Destination | `Rubavu Depot` |
  | Required date | tomorrow |
  | Cargo description | `Chilled beverages, 300 crates` |
  | Estimated weight | `2800` |
  | Packages | `300` |
  | Status | `Pending` |
  | Notes | `Customer needs it on the shelf before Saturday trading.` |

- **Expect:** created as **REQ-2026-0001** with an amber **Pending** badge, and a
  **What happens next** panel offering **Approve request** and **Reject**.
- **Do:** click **Reject**, then press confirm with the reason box empty.
- **Expect:** the box turns red; nothing is saved. Close the dialog.
- **Do:** click **Approve request**.
- **Expect:** *"Request REQ-2026-0001 approved. Plan a trip for it next."* The badge turns
  green **Approved**, and a **Decision** panel appears showing *Decided by Aline Mukamana
  on \<today\>*. The **Approve** button is gone — it cannot be approved twice — and
  **Plan trip** has appeared in its place.

---

# DAY 3 — The job runs

Still signed in as **aline@itec.rw**.

## 5.1 Book the cargo

- **Do:** Transport → **Shipments** → **Book shipment**:

  | Field | Value |
  | --- | --- |
  | Shipment reference | leave blank |
  | Customer | `Bralirwa Distribution Ltd` |
  | Source request | `REQ-2026-0001` |
  | Loaded on trip | leave blank for now |
  | Consignee | `Rubavu Depot Store` |
  | Consignee phone | `+250 788 440 901` |
  | Origin | `Kigali Central Warehouse` |
  | Destination | `Rubavu Depot` |
  | Cargo type | `Cold chain` |
  | Cargo description | `Chilled beverages, 300 crates` |
  | Packages | `300` |
  | Gross weight | `2800` |
  | Volume | `18` |
  | Declared value | `9500000` |
  | Minimum temperature | leave blank |
  | Maximum temperature | leave blank |
  | Status | `Booked` |
  | Special instructions | `Hold between 2 and 8 degrees for the whole journey.` |

- **Expect:** refused — *"Cold-chain cargo needs a temperature range."* The system will
  not accept chilled cargo with no stated temperature, because that is the whole point
  of calling it chilled.
- **Do:** set Minimum `2` and Maximum `8`, save.
- **Expect:** created as **SHP-2026-0006**, with a strip showing Cargo, Weight
  `2,800.00 kg`, Packages `300`, Temperature `2.00 to 8.00 C`.
- **Do:** **Edit**, set Minimum `10` and Maximum `4`, save.
- **Expect:** *"Maximum temperature cannot be below the minimum temperature."*
  Put 2 and 8 back.

## 5.2 Plan the trip

- **Do:** Transport → **Trips** → **Create trip**:

  | Field | Value |
  | --- | --- |
  | Trip reference | leave blank |
  | Fulfils request | `REQ-2026-0001` |
  | Customer | `Bralirwa Distribution Ltd` |
  | Trip type | `Delivery` |
  | Pickup location | `Kigali Central Warehouse` |
  | Final destination | `Rubavu Depot` |
  | Cargo summary | `300 crates chilled beverages` |
  | Planned departure | tomorrow `06:00` |
  | Planned arrival | tomorrow `04:00` |
  | Vehicle | `RAE 260D` (the box truck) |
  | Driver | `Olivier Kwizera` |
  | Status | `Requested` |

- **Expect:** refused — *"Planned arrival cannot be before planned departure."*
- **Do:** set Planned arrival to tomorrow `12:30`, save.
- **Expect:** created as **TRP-2026-0001** with an amber **Requested** badge.
- **Do:** Shipments → edit **SHP-2026-0006** → set **Loaded on trip** to `TRP-2026-0001`,
  save. Then open the trip.
- **Expect:** the **Shipments on board** panel now lists it with cargo, packages and weight.

## 5.3 The route, stop by stop

**Where this is.** Stops are edited on the trip's **record page**, not on the Edit
form. If you are still on the form, save it first.

- **Do:** open Transport → **Trips** and click **TRP-2026-0001** itself (the blue
  reference, not the pencil icon). Scroll down past the field sections — Trip, Route,
  Plan, Resources, Actual execution, Notes — until you reach the card headed
  **Stops on this route**. It sits just above **Shipments on board**.
- **Expect:** a small table with the columns Type, Location, Contact, Phone, Planned
  arrival and Status, three empty rows, an **Add row** button underneath, and a blue
  **Save stops on this route** button beside it.
- **Do:** fill three rows:

  | # | Type | Location | Contact | Phone | Planned arrival |
  | --- | --- | --- | --- | --- | --- |
  | 1 | Pickup | `Kigali Central Warehouse` | `Nadine Tuyisenge` | `+250 782 110 554` | tomorrow 06:00 |
  | 2 | Checkpoint | `Muhanga weighbridge` | | | tomorrow 08:00 |
  | 3 | Drop-off | `Rubavu Depot` | `Yvonne Ingabire` | `+250 788 440 900` | tomorrow 12:30 |

  Leave each Status on **Pending** and click **Save stops on this route**.
- **Expect:** the three stops listed in order, numbered 1, 2, 3.

## 5.4 Four refusals — this is the heart of the system

**a) Chilled cargo on the wrong truck**

- **Do:** click **Dispatch trip**. (The vehicle is RAE 260D, which has no cooling unit.)
- **Expect:** a red bar — *"RAE 260D has no cooling unit and this trip carries cold-chain
  cargo."* The trip is still **Requested**; nothing changed.

**b) Too heavy for the truck**

- **Do:** edit the trip and set **Vehicle** to `RAE 145C` (4,000 kg, chilled), save. Then
  edit **SHP-2026-0006** and set Gross weight to `6000`, save. Go back and
  **Dispatch trip**.
- **Expect:** *"The load is 6,000 kg but RAE 145C carries 4,000 kg."*
- **Do:** set the shipment weight back to `2800`.

**c) No driver**

- **Do:** edit the trip, clear the **Driver**, save, and **Dispatch trip**.
- **Expect:** *"Assign both a vehicle and a driver before dispatching."*

**d) The truck is already out**

- **Do:** put `Olivier Kwizera` back as driver and save. Now create a second trip:
  **Create trip**, pickup `Kigali`, destination `Musanze`, status `Requested`,
  vehicle `RAE 145C`, driver `Olivier Kwizera`. Save, then **Dispatch trip** on this
  second one — *after* you have dispatched the first in 5.5. Come back to this step then.

## 5.5 Dispatch — and the five things it does by itself

- **Do:** open **TRP-2026-0001** and click **Dispatch trip**.
- **Expect:** *"TRP-2026-0001 dispatched. The vehicle and driver are now marked on trip."*
  The badge turns blue **In Transit**.

**Now check the five changes you did not make:**

| Where | Expect |
| --- | --- |
| Fleet management → Vehicles → RAE 145C | Status is now **On Trip** |
| Fleet management → Drivers → Olivier Kwizera | Status is now **On Trip** |
| Transport requests → REQ-2026-0001 | Changed from Approved to **Assigned** |
| Shipments → SHP-2026-0006 | Changed from Booked to **In Transit** |
| The trip record | *Dispatched at* and *Actual departure* are now stamped |

- **Do:** now go back to the second trip from 5.4(d) and click **Dispatch trip**.
- **Expect:** *"That vehicle or driver is already out on trip TRP-2026-0001."*
  A truck cannot leave twice.
- **Do:** on that second trip click **Cancel trip**, reason `Created to test double booking`.
- **Expect:** cancelled, and its vehicle and driver released.

## 5.6 Create the delivery the driver will close

- **Do:** Transport → **Deliveries** → **Record delivery**:

  | Field | Value |
  | --- | --- |
  | Delivery reference | leave blank |
  | Trip | `TRP-2026-0001` |
  | Shipment | `SHP-2026-0006` |
  | Attempt number | `1` |
  | Recipient name | `Yvonne Ingabire` |
  | Recipient phone | `+250 788 440 900` |
  | Delivery address | `Rubavu Depot` |
  | Planned for | tomorrow `12:30` |
  | Status | `In transit` |

- **Expect:** created as **DEL-2026-0001**, with **Attachments** showing *Proof of
  delivery — Missing* and *Recipient signature — Missing*.

---

# DAY 3 (on the road) — The Driver

Sign out, sign in as **aline@itec.rw** / `password` — the login you linked to Olivier.

> While that account is linked to a driver profile it is scoped like a driver on the
> trips, deliveries and fuel pages: it sees Olivier's work, not the whole company's.

## 6.1 You see only your own work

- **Expect:** a short sidebar: Dashboard, Transport (Trips, Shipments, Deliveries),
  Finance → Fuel management. No Vehicles, no Customers, no Reports, no Administration.
- **Do:** open Transport → **Trips**.
- **Expect:** a short list with a grey badge above it: **Scoped to your own work**.
  `TRP-2026-0001` is there.
- **Do:** open `http://localhost/logistics-mvc/users` in the address bar.
- **Expect:** pushed back to the dashboard with *"Users is not available for the Driver
  role."*
- **Do:** as admin in another browser, find the id of a trip belonging to a different
  driver (it is in the address bar when you view it). Back as the driver, type that URL.
- **Expect:** redirected to the trip list with *"That record does not exist, or it is not
  visible to your role."* A driver cannot reach another driver's work by guessing a number.

## 6.2 Buy fuel on the road

- **Do:** Finance → **Fuel management** → **Record fuel**:

  | Field | Value |
  | --- | --- |
  | Reference | leave blank |
  | Vehicle | `RAE 145C` |
  | Station | `SP Muhanga` |
  | Fuel type | `Diesel` |
  | Purchased at | today `07:40` |
  | Litres | `120` |
  | Price per litre | `1620` |
  | Tank filled to full | **on** |
  | Odometer now | `18720` |
  | Driver | `Olivier Kwizera` |
  | Trip | `TRP-2026-0001` |

- **Expect:** created as **FUE-2026-0001** with Litres `120 L`, Total cost
  **RWF 194,400**, Odometer `18,720 km`.
- **Do:** as the Fleet Manager (or admin) look at Vehicles → **RAE 145C**.
- **Expect:** the vehicle odometer moved from 18,500 to **18,720** on its own.
- **Do:** back as the driver, record another fuel entry for the same vehicle with
  **Odometer now** `15000`.
- **Expect:** *"Odometer 15000 is below the previous reading of 18,720 km for this
  vehicle."* Change it to `18900` and save; open it and see **Previous odometer** filled
  in as `18,720`, greyed out and marked *calculated*.

> **Remember RWF 194,400.** Part 9 finds it debited to *Fuel* in the ledger.

## 6.3 Deliver it

- **Do:** Transport → **Deliveries** → open **DEL-2026-0001**.
- **Expect:** a **What happens next** panel with **Mark delivered** and **Record failure**.
- **Do:** click **Mark delivered**.
- **Expect:** *"DEL-2026-0001 marked delivered. Proof of delivery is still missing;
  upload it on the delivery record."* The badge turns green and *Delivered at* is stamped.
- **Do:** **Edit** the delivery, upload any image as **Proof of delivery** and another as
  **Recipient signature**, save.
- **Expect:** both appear under **Attachments** with **Open** links.
- **Do:** click **Open**.
- **Expect:** the file opens in a new tab. Copy that URL, sign out, paste it into a
  private window — you are refused. The file lives outside the web root and is only
  served to a signed-in user whose role may see deliveries.
- **Do:** try uploading a `.php` file as proof.
- **Expect:** *"Proof of delivery must be a PDF, image, Word or Excel file."*

## 6.4 A failed delivery, recorded properly

- **Do:** create a second delivery for the same trip: reference blank, trip
  `TRP-2026-0001`, recipient `Rubavu Second Drop`, address `Gisenyi Market`,
  status `In transit`, attempt `1`. Save, then click **Record failure** and confirm with
  the reason box empty.
- **Expect:** the box turns red; nothing saved.
- **Do:** type `recipient absent` and confirm.
- **Expect:** *"… recorded as failed. Create a new attempt when it is rescheduled."*
  The badge turns red **Failed**, and the **Exception** section shows Failure reason
  **Recipient absent** with your note.

## 6.5 The driver's phone API

- **Do:** still signed in as the driver, open
  `http://localhost/logistics-mvc/api/my/trips`.
- **Expect:** JSON listing only this driver's trips.
- **Do:** open `http://localhost/logistics-mvc/api/modules/users`.
- **Expect:** `{"error":"This module is not available for your role."}`

---

# DAY 4 — Closing the job off

Sign out, sign in as **aline@itec.rw**.

- **Do:** Transport → **Trips** → open **TRP-2026-0001** → **Mark delivered**.
- **Expect:** *"TRP-2026-0001 completed. Vehicle and driver released."* plus a sentence
  saying whether it arrived on time against your planned arrival.
- **Expect:** RAE 145C and Olivier Kwizera are both back to **Available**, and the
  shipment is now **Delivered**. Again, all by itself.

---

# DAY 4 (afternoon) — Finance bills it

Sign out, sign in as **emmanuel@itec.rw** / `password`.

## 7.1 Approve the warehouse's purchase

- **Do:** Warehouse → **Procurement** → open **PR-2026-0001**.
- **Expect:** *this* time there **is** an action panel, because Finance holds the approve
  right that Warehouse does not.
- **Do:** click **Approve request**.
- **Expect:** *"Purchase request PR-2026-0001 approved."* and a **Decision** panel naming
  Emmanuel Safari.
- **Do:** click **Mark received**.
- **Expect:** *"PR-2026-0001 received. 1 stock line(s) posted into inventory."*
- **Do:** open Warehouse → Inventory → **Euro pallet 1200x800**.
- **Expect:** On hand has risen by the 120 on the purchase line, and a new movement
  appears in its ledger citing `PR-2026-0001`.

## 7.2 The driver's allowance

- **Do:** Finance → **Logistics expenses** → **Submit expense**:

  | Field | Value |
  | --- | --- |
  | Reference | leave blank |
  | Category | `Allowance` |
  | Amount | `60000` |
  | Expense date | today |
  | Vehicle | `RAE 145C` |
  | Trip | `TRP-2026-0001` |
  | Submitted by | `Olivier Kwizera`'s login (Aline Mukamana) |
  | Status | `Pending` |
  | Payment method | `Cash` |
  | Notes | `Night-out allowance, Rubavu run` |

- **Expect:** created as **EXP-2026-0001**, amber **Pending**.
- **Do:** click **Approve expense**.
- **Expect:** *"Expense EXP-2026-0001 approved for RWF 60,000."* Badge turns green, and
  the **Decision** panel names Emmanuel Safari with the time.
- **Do:** create a second expense of `15000`, category `Parking`, then **Reject** it with
  reason `No receipt attached`.
- **Expect:** red **Rejected**, and the Decision panel shows both the approver and
  **Rejection reason: No receipt attached**.

> **Remember RWF 60,000.** Part 9 finds it debited to *Driver allowances*.

## 7.3 Raise the invoice

- **Do:** Commercial → **Invoices** → **Raise invoice**:

  | Field | Value |
  | --- | --- |
  | Invoice number | leave blank |
  | Customer | `Bralirwa Distribution Ltd` |
  | Trip | `TRP-2026-0001` |
  | Status | `Draft` |
  | Issue date | today |
  | Due date | 30 days from today |
  | VAT rate | `18` |

- **Expect:** created as **INV-2026-0006**. Subtotal, VAT, Total and Paid are greyed out
  and marked *calculated* — you cannot type them.
- **Do:** click **Issue invoice** straight away.
- **Expect:** refused — *"Add at least one invoice line before issuing this invoice."*
- **Do:** on the invoice record page, scroll to the card headed **Invoice lines** and
  fill two rows:

  | Description | Qty | Unit price |
  | --- | --- | --- |
  | `Cold-chain transport Kigali to Rubavu` | `1` | `520000` |
  | `Loading and handling, 300 crates` | `1` | `60000` |

  Click **Save invoice lines**.
- **Expect:** the strip now reads Total **RWF 684,400**, Paid `RWF 0`, Balance
  **RWF 684,400**. That is 580,000 plus 18% VAT of 104,400, worked out for you.
- **Do:** click **Issue invoice**.
- **Expect:** *"Invoice INV-2026-0006 issued for RWF 684,400."* Badge turns **Issued**.

## 7.4 The customer pays

- **Do:** Commercial → **Payments received** → **Record payment**:

  | Field | Value |
  | --- | --- |
  | Payment reference | leave blank |
  | Against invoice | `INV-2026-0006` |
  | Amount received | `684400` |
  | Method | `Bank transfer` |
  | Bank or till reference | `BK-2026-77120` |
  | Received at | today `11:15` |
  | Recorded by | `Emmanuel Safari` |

- **Expect:** created as **PAY-2026-0003**.
- **Do:** open Commercial → **Invoices** → **INV-2026-0006**.
- **Expect:** the badge has changed by itself from **Issued** to **Paid**, Paid reads
  `RWF 684,400` and Balance `RWF 0`. The **Payments received** panel lists your payment.

## 7.5 Did the job make money?

- **Do:** Reports → **Trip profitability**, From the first of this month, To today, **Run**.
- **Expect:** a row for **TRP-2026-0001**:

  | Column | Expect |
  | --- | --- |
  | Revenue | `RWF 580,000` — the invoice **net of VAT**, because the 104,400 tax is collected for the revenue authority, not earned |
  | Fuel | `RWF 194,400` |
  | Other cost | `RWF 60,000` |
  | Total cost | `RWF 254,400` |
  | Margin | `RWF 325,600` |
  | Margin % | `56.1%` |

- **Do:** click **Export CSV**.
- **Expect:** a file whose first lines are the report name and period, then the headings,
  then the rows, then the totals.

---

# DAY 5 — Finance closes the books

Still signed in as **emmanuel@itec.rw**.

Everything you did in Parts 2 to 7 has already been written into double entry. Nobody
posted a journal. This part is about reading it.

## 8.1 The chart of accounts

- **Do:** Accounting → **Chart of accounts**.
- **Expect:** 33 accounts grouped by statement section: Cash and bank, Accounts
  receivable, Other current assets, Fixed assets, Accounts payable, Other current
  liabilities, Long term liabilities, Equity, Income, Cost of sales, Operating expenses.
- **Do:** open **5000 — Fuel**.
- **Expect:** its type, normal side, cash-flow activity, and a **Recent movements** panel
  listing entries — including your `FUE-2026-0001`.
- **Do:** click **Add account**:

  | Field | Value |
  | --- | --- |
  | Account code | `6600` |
  | Account name | `Security and guarding` |
  | Type | `Expense` |
  | Normal balance | `Debit` |
  | Statement section | `Operating expenses` |
  | Section order | `120` |
  | Cash flow activity | `Operating` |
  | Active | **on** |

- **Expect:** created, and it now appears under Operating expenses in the Trial Balance.
- **Do:** try to add another account with code `6600`.
- **Expect:** *"Account code "6600" is already used by another record."*

## 8.2 The journal — find your own entries

- **Do:** Accounting → **Journal**. Type `INV-2026-0006` in the search box, **Apply**.
- **Expect:** one entry, *Invoice INV-2026-0006 to Bralirwa Distribution Ltd*, amount
  `RWF 684,400`, source **Invoice**.
- **Do:** open it.
- **Expect:** three lines that must look exactly like this:

  | Account | Debit | Credit |
  | --- | --- | --- |
  | 1100 Accounts receivable | 684,400.00 | |
  | 4000 Freight revenue | | 580,000.00 |
  | 2100 VAT payable | | 104,400.00 |
  | **TOTAL** | **684,400.00** | **684,400.00** |

- **Expect:** the **Source** tile links back to the invoice itself. One click from a
  figure in the books to the document that caused it.
- **Do:** search the journal for each of these and check the posting:

  | Search | Expect |
  | --- | --- |
  | `PAY-2026-0003` | Dr **1020 Bank – main account** 684,400 / Cr **1100 Accounts receivable** 684,400 |
  | `FUE-2026-0001` | Dr **5000 Fuel** 194,400 / Cr **2000 Accounts payable** 194,400 |
  | `EXP-2026-0001` | Dr **5100 Driver allowances** 60,000 / Cr **1010 Petty cash** 60,000 |
  | your work order | Dr **6000 Vehicle maintenance and repairs** 85,000 / Cr **2000 Accounts payable** 85,000 |
  | `PR-2026-0001` | Dr **1200 Inventory** 1,500,000 / Cr **2000 Accounts payable** 1,500,000 |

  Those are the five numbers this walkthrough asked you to remember, now in the books
  without anyone typing a debit.

## 8.3 Write a journal entry by hand

Some things no operational document produces — an accrual, a correction, a depreciation
charge. That is what this form is for.

- **Do:** Accounting → **Journal** → **New entry**:

  - Entry date: today
  - Memo: `Depreciation charge for the month`
  - Reference: `DEP-09`
  - Lines:

    | Account | Description | Debit | Credit |
    | --- | --- | --- | --- |
    | 6400 Depreciation | `Fleet depreciation` | `1400000` | |
    | 1590 Accumulated depreciation | `Fleet` | | `1400000` |

- **Expect:** as you type, the footer shows running totals and a badge. With only the
  debit entered it reads **Out of balance by 1,400,000.00 — credits are short** in red.
  Once the credit is in it turns green: **Balanced**.
- **Expect:** typing into Debit clears Credit on that row, and the other way round —
  a line is one or the other, never both.
- **Do:** delete the credit line's amount and press **Post entry**.
- **Expect:** refused, *"The entry is out of balance by 1,400,000.00. Debits total
  1,400,000.00 and credits total 0.00."* The page will not send an entry that does not
  balance, and the ledger would refuse it again behind the page.
- **Do:** put it back and **Post entry**.
- **Expect:** *"Entry JRN-2026-nnnn posted for RWF 1,400,000."*

## 8.4 Reverse it — the right way to undo an entry

- **Do:** on that entry, click **Reverse**, reason `Charged to the wrong month`.
- **Expect:** a new mirror entry is posted and opened — the debit and credit swapped. Go
  back to the original: it is now badged **Reversed**, with a bar linking to its mirror.
- **Expect:** the original is **still there**. Nothing is ever deleted from a journal;
  that is what makes a book auditable.
- **Do:** try to reverse it again.
- **Expect:** *"That entry has already been reversed."*

## 8.5 The eight books

- **Do:** Accounting → **Accounting books**.
- **Expect:** a green bar at the top: **The ledger balances.** Under it, eight cards, and
  a table explaining which posting each operational event produces.

Open each in turn. Set **From** to the first of this year and **To** to today.

| Book | What to expect |
| --- | --- |
| **Chart of accounts** | Every account with its type, normal side and current balance, grouped by section. |
| **Journal** | Every entry with all of its lines, grouped by entry, ending in a TOTAL row where debits equal credits. |
| **General ledger** | Each account with its opening balance, every movement, and a running balance down the page, then a closing line. Use the **Account** filter to narrow to one. |
| **Trial balance** | Every balance in a Debit or a Credit column. At the foot, a TOTAL row, and under the table: *"The two columns agree, so the ledger is in balance."* |
| **Profit and loss** | Income, Cost of sales, **GROSS PROFIT**, Operating expenses, **OPERATING PROFIT**, then **NET PROFIT FOR THE PERIOD**. |
| **Balance sheet** | Current assets, Fixed assets, **TOTAL ASSETS**; then liabilities and equity, ending **TOTAL LIABILITIES AND EQUITY**. The note says *"Assets equal liabilities plus equity, so the statement balances."* |
| **Statement of cash flows** | Operating, investing and financing activities, a **NET MOVEMENT IN CASH**, then the opening and closing bank balances and **MOVEMENT PROVED**. The note confirms the explanation equals the actual change in the bank. |
| **Account statement** | Pick `1020 - Bank - main account`. Every movement on it with a running balance — your 684,400 receipt is in there. |

> **Why the profit looks odd.** The demo company shipped with many months of costs and
> very few invoices, so the overall profit and loss shows a loss. That is honest
> arithmetic on the seeded data, not a fault. Your own job made money — Part 7.5 proved
> it, trip by trip.

## 8.6 Download them

On any book:

- **Do:** click **PDF**.
- **Expect:** a real PDF downloads, named like `RWANDA_CARGO_LINK_LTD_Trial_Balance.pdf`.
  It opens with your company name at the top in the report colour, the report title, the
  period, the page number, and the basis and timestamp at the foot. The General Ledger
  and Journal come out in landscape because they are wide; the others are portrait.
- **Do:** click **Excel**.
- **Expect:** a real `.xlsx` opens in Excel. The figures are **numbers, not text**, so
  you can sum and pivot them. The headings are frozen, the columns are sized to their
  contents, and subtotals carry a rule above and grand totals a double rule.
- **Do:** click **CSV**.
- **Expect:** the same report as plain data, with the company, title and period as the
  first lines.
- **Do:** click **Print**.
- **Expect:** the print preview shows only the statement on white — no sidebar, no top
  bar, no buttons.

## 8.7 If the operations and the books ever disagree

- **Do:** Accounting → **Accounting books** → **Post outstanding documents**.
- **Expect:** either *"The ledger was already up to date; nothing needed posting."* or a
  list of what it posted. Run it twice — the second run posts nothing, because an entry
  belongs to its document and cannot be created twice.

---

# DAY 5 (afternoon) — Management reads the result

Sign out, sign in as **jeanpierre@itec.rw** / `password`.

- **Expect:** a short sidebar: Dashboard, Transport → Trips, Commercial (Customers,
  Invoices), Accounting → Accounting books, Reports, Administration → Audit trail.
  No create buttons anywhere — Management reads, it does not enter.

- **Do:** open **Reports** and run each of the eight, From `2026-01-01` To today:

  | Report | What it answers |
  | --- | --- |
  | Vehicle utilization | Which trucks are earning their keep — trips, completions, hours on road, share of all trips. Your RAE 145C is in it. |
  | Fuel consumption | Litres and money per vehicle, with the average price paid. A note says litres per 100 km arrives with route distance. |
  | Delivery performance | Deliveries, failures, re-attempts and the on-time rate per month. Your failed second drop is counted here. |
  | Maintenance cost | Estimate against actual per vehicle. Your work order shows 120,000 estimated, 85,000 actual. |
  | Driver performance | Trips, deliveries, failures and on-time rate per driver. |
  | Inventory movement | In, out, net change and closing balance per item. Your pallets are there. |
  | Trip profitability | As in Part 7.5. |
  | Expense summary | Totals per category, split into approved, pending and rejected. |

- **Do:** pick a period with nothing in it, e.g. From `2020-01-01` To `2020-01-31`.
- **Expect:** a friendly empty state — *"No data in this period"* — not an error and not
  a blank page.
- **Do:** try `http://localhost/logistics-mvc/vehicles/create`.
- **Expect:** pushed back with *"Vehicles is not available for the Management role."*

---

# Part 12 — Editing and deleting, and what survives

Sign back in as **admin@itec.rw**.

## 12.1 Editing keeps the connections

- **Do:** Commercial → **Customers** → **Bralirwa Distribution Ltd** → **Edit**. Change
  Payment terms to `45` and Credit limit to `25000000`, save.
- **Expect:** *"Customer updated."* The trips, rate card and invoice linked to the
  customer are untouched — you changed the customer, not the history.

## 12.2 Deleting is a soft delete

- **Do:** Commercial → **Rate cards** → open your `RATE-2026-0006` → **Delete**, reason
  `Superseded by the 2027 tariff`.
- **Expect:** it disappears from the list.
- **Do:** Administration → **Audit trail**, search `RATE-2026-0006`.
- **Expect:** the whole history is still there — created, updated, deleted — with your
  reason. Hidden, not erased.
- **Do:** run **Trip profitability** again.
- **Expect:** it still works and still shows your job. Removing a rate card did not
  corrupt past reporting.

## 12.3 What cannot be deleted away

- **Do:** open your invoice **INV-2026-0006** and click **Cancel invoice**, reason
  `Testing what cancelling does`.
- **Expect:** the invoice is cancelled.
- **Do:** Accounting → **Journal**, search `INV-2026-0006`.
- **Expect:** its entry is **gone from the ledger** — cancelling an invoice takes its
  revenue back out, which is exactly right. The audit trail still records that it existed
  and who cancelled it.
- **Do:** Accounting → Books → **Trial balance**.
- **Expect:** it still says the two columns agree. Removing the entry removed both sides.

## 12.4 The audit trail proves the week

- **Do:** Administration → **Audit trail**.
- **Expect:** everything you have done this week, newest first: sign-ins, the settings
  change, the user created and removed with your reason, every record created and
  updated, every approval with its approver, every workflow transition, every file you
  opened and every export you downloaded.
- **Do:** filter **Area** to `journal`.
- **Expect:** only the accounting actions — entries posted, the reversal with its reason,
  the sync run.
- **Do:** search `Rwanda Cargo` and click **Export CSV**.
- **Expect:** a file with When, Who, Action, Entity, Reference and Reason.

---

# Part 13 — Every page, checked

Sign in as admin and open each of these once. None may show a PHP error, a blank page or
missing styling.

```text
/dashboard        /account          /account/password
/vehicles         /drivers          /vehicle_documents   /maintenance
/requests         /trips            /shipments           /deliveries
/customers        /rates            /invoices            /payments
/fuel             /expenses
/warehouse        /movements        /procurement         /suppliers
/accounts         /journal          /books
/reports          /reports/catalogue
/users            /permissions      /settings            /audit
```

For each module also open `/create`, then one record, then that record's `/edit`.

Then the eight books and the eight reports:

```text
/books/view/chart_of_accounts     /books/view/journal
/books/view/general_ledger        /books/view/trial_balance
/books/view/profit_loss           /books/view/balance_sheet
/books/view/cash_flow             /books/view/account_statement

/reports/view/vehicle_utilization /reports/view/fuel_consumption
/reports/view/delivery_performance /reports/view/maintenance_cost
/reports/view/driver_performance  /reports/view/inventory_movement
/reports/view/trip_profitability  /reports/view/expense_summary
```

Finally, press `/` anywhere and type a few letters.

**Expect:** the quick search jumps to any page you may open — and, signed in as a driver,
offers only the pages a driver may open.

---

# Acceptance checklist

| # | What you proved | ✓ |
| --- | --- | --- |
| 1 | Company settings changed what the whole system shows | ☐ |
| 2 | A new user got a one-time password and was forced to change it | ☐ |
| 3 | A duplicate email, a blank form and a bad email were all refused | ☐ |
| 4 | A permission granted and revoked took effect immediately | ☐ |
| 5 | A vehicle, its insurance and its inspection were registered | ☐ |
| 6 | The document status set itself to Expiring, then Expired | ☐ |
| 7 | A driver was created and linked to a login | ☐ |
| 8 | A work order's actual cost came from its parts and labour | ☐ |
| 9 | The vehicle went to Maintenance and back to Available by itself | ☐ |
| 10 | Stock in and stock out moved the balance; negative was refused | ☐ |
| 11 | The item flipped to In stock, then Reorder, on its own | ☐ |
| 12 | Purchase lines set the request amount; receiving posted them to stock | ☐ |
| 13 | Warehouse could not approve its own purchase; Finance could | ☐ |
| 14 | A customer and an agreed rate were created, with validation | ☐ |
| 15 | A request was approved, with the approver and time recorded | ☐ |
| 16 | Cold-chain cargo was refused without a temperature range | ☐ |
| 17 | Dispatch refused: no cooling unit | ☐ |
| 18 | Dispatch refused: over capacity | ☐ |
| 19 | Dispatch refused: no driver | ☐ |
| 20 | Dispatch refused: vehicle already out | ☐ |
| 21 | A successful dispatch changed five other records by itself | ☐ |
| 22 | The driver saw only their own work, and could not reach another's | ☐ |
| 23 | Fuel moved the odometer; a lower reading was refused | ☐ |
| 24 | Proof of delivery uploaded, opened, and refused to a signed-out visitor | ☐ |
| 25 | A failed delivery recorded a coded reason | ☐ |
| 26 | An expense was approved and another rejected, both on record | ☐ |
| 27 | An invoice was refused without lines; its totals and VAT were calculated | ☐ |
| 28 | A payment settled the invoice to Paid by itself | ☐ |
| 29 | Trip profitability showed revenue net of VAT, cost and margin | ☐ |
| 30 | The journal showed all five postings nobody typed | ☐ |
| 31 | A hand-written entry was refused until it balanced | ☐ |
| 32 | An entry was reversed, and the original stayed | ☐ |
| 33 | All eight books opened, balanced and reconciled | ☐ |
| 34 | Each book downloaded as PDF, Excel and CSV, and printed cleanly | ☐ |
| 35 | All eight reports ran, and an empty period was handled gracefully | ☐ |
| 36 | A deleted record vanished from lists but survived in the audit trail | ☐ |
| 37 | Cancelling an invoice removed its ledger entry and kept the books balanced | ☐ |
| 38 | Every page in Part 13 opened without error | ☐ |

---

# If something does not match

Write down the step number and what you saw instead. The useful places to look:

- `C:\xampp\apache\logs\error.log` for PHP errors.
- Administration → **Audit trail** — it records what the system thought happened,
  including anything the ledger could not post (filter Area to `journal`).
- The automated suites, which cover the same ground and name the assertion that broke:

```text
C:\xampp\php\php.exe tests\smoke.php
C:\xampp\php\php.exe tests\workflow.php
C:\xampp\php\php.exe tests\accounting.php
C:\xampp\php\php.exe tests\security.php
C:\xampp\php\php.exe tests\seed_integrity.php
```

# Starting again

This walkthrough leaves your records behind. To return to the seeded state:

```text
C:\xampp\mysql\bin\mysql.exe -uroot -e "DROP DATABASE logistics_mvc;"
C:\xampp\php\php.exe scripts\migrate.php
```
