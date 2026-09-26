# LMS Manual Test Plan

Written for: whoever is accepting this system — you sign in as each role, do the work
that role really does, and check the screen against what this page says you should see.

Work straight down the page. Later parts use records created in earlier parts, so do
not skip ahead. Every step says exactly what to type and exactly what to expect.

---

## Before you start

**1. Load the database**

```text
cd C:\xampp\htdocs\logistics-mvc
C:\xampp\php\php.exe scripts\migrate.php
```

Expected output, ending with:

```text
Loaded database/seed.sql
Loaded database/seed_extended.sql
Database setup complete.
```

**2. Start Apache in XAMPP** and open:

```text
http://localhost/logistics-mvc/
```

Expected: the public home page with the heading *"Every vehicle. Every delivery. One
clear view."* and a **Secure access** sign-in panel on the right.

**3. The accounts.** All seven use the password `password`.

| Role | Email |
| --- | --- |
| Super Admin | admin@itec.rw |
| Logistics Manager | aline@itec.rw |
| Fleet Manager | eric@itec.rw |
| Warehouse Manager | nadine@itec.rw |
| Driver | samuel@itec.rw |
| Finance | emmanuel@itec.rw |
| Management | jeanpierre@itec.rw |

Choosing a role in the drop-down only fills the email in for you. What actually signs
you in is the email and password.

**How to read a step:** *Do* is what you type or click. *Expect* is what must appear.
If what you see differs from *Expect*, stop and write down the step number.

---

# Part 0 — Sign-in and security

### 0.1 A wrong password is refused

- **Do:** email `admin@itec.rw`, password `wrongpass`, click **Login to dashboard**.
- **Expect:** back on the home page with a red box: *"That email and password do not
  match an active account."* You are not signed in.

### 0.2 Lockout after repeated failures

- **Do:** repeat step 0.1 four more times (five wrong attempts in total).
- **Expect:** on the fifth, the red box changes to *"Too many failed sign-ins. This
  account is locked for about 15 more minute(s)."*
- **Do:** now try the correct password `password`.
- **Expect:** still refused, with the same lockout message. The lock is real.

> To carry on immediately instead of waiting 15 minutes, run:
> ```text
> C:\xampp\mysql\bin\mysql.exe -uroot logistics_mvc -e "UPDATE users SET status='active', failed_login_count=0, locked_until=NULL WHERE email='admin@itec.rw';"
> ```

### 0.3 A good sign-in

- **Do:** email `admin@itec.rw`, password `password`.
- **Expect:** the dashboard opens. A green bar says *"Welcome back, Admin User."*
  Top right shows **Role: Super Admin**. The sidebar shows Dashboard, Fleet Management,
  Transport, Commercial, Finance, Warehouse, Reports and Administration.

### 0.4 A signed-out visitor cannot reach a page directly

- **Do:** open a private browser window and go straight to
  `http://localhost/logistics-mvc/vehicles`.
- **Expect:** you land on the home page at the sign-in panel, not on the vehicle list.

### 0.5 Forgotten password

- **Do:** on the home page click **Forgotten your password?**, enter `eric@itec.rw`,
  submit.
- **Expect:** a page saying a reset link has been created, an amber box explaining that
  no mail transport is configured, and the link itself shown on screen.
- **Do:** click **Open the reset page**, enter `Kigali2026` twice, submit.
- **Expect:** red text *"The new password must be at least 10 characters long."*
- **Do:** enter `KigaliFresh2026` twice, submit.
- **Expect:** back at sign-in with a green message *"Your password was changed."*
- **Do:** sign in as `eric@itec.rw` with `KigaliFresh2026`.
- **Expect:** the Fleet Manager dashboard opens.
- **Do:** open the same reset link again.
- **Expect:** *"This reset link is invalid or has already been used."* One use only.
- **Do:** sign out, then change Eric's password back for the rest of this plan: sign in
  as Eric, go to **Change password** in the avatar menu, current `KigaliFresh2026`,
  new `password`… **Expect:** it is refused (*"That password is too easy to guess."*).
  Leave it as `KigaliFresh2026` and use that for Eric from Part 4 onwards.

---

# Part 1 — Super Admin

Sign in as `admin@itec.rw` / `password`.

### 1.1 Dashboard

- **Expect:** four KPI cards (Total vehicles, Active trips, Pending requests, Monthly
  expenses), four charts, a *Recent Trips and Deliveries* table and an *Operational
  attention* panel. Numbers are non-zero.
- **Do:** click **View as table** on any chart.
- **Expect:** the chart is replaced by a data table with the same numbers.
- **Do:** click the sun icon in the top bar.
- **Expect:** the whole interface switches to dark mode and stays dark when you move
  between pages.

### 1.2 Notifications

- **Do:** click the bell.
- **Expect:** a list of updates; unread ones have a coloured dot and a tick button.
- **Do:** click the tick on one item.
- **Expect:** the page reloads, the unread count drops by one, that item is no longer
  marked unread.
- **Do:** open the bell and click **mark all read**.
- **Expect:** a green bar *"N update(s) marked as read."* and the red badge disappears.

### 1.3 Company settings

- **Do:** avatar menu → **Company settings**. Change **Currency symbol** from `RWF` to
  `FRW` and click **Save settings**.
- **Expect:** green bar *"1 setting(s) saved."*
- **Do:** open **Finance → Logistics expenses**.
- **Expect:** every amount now reads `FRW 45,000` instead of `RWF 45,000`.
- **Do:** set it back to `RWF`.
- **Do:** in settings, put the word `abc` in **Default VAT rate (%)** and save.
- **Expect:** a red bar *"tax rate must be a number."* and the value is not saved.

### 1.4 Role permissions

- **Do:** Administration → **Role permissions**. Click the **Driver** tab.
- **Expect:** Driver holds Dashboard (view), Trips (view, edit), Shipments (view),
  Deliveries (view, edit, approve) and Fuel (view, create). Everything else unticked.
- **Do:** click the **Super Admin** tab.
- **Expect:** every box ticked and greyed out, with a blue note explaining Super Admin
  cannot be restricted.
- **Do:** back on **Management**, tick **Vehicles → view**, save.
- **Expect:** green bar *"Permissions for Management were saved."*
- **Do:** sign out, sign in as `jeanpierre@itec.rw` / `password`.
- **Expect:** the sidebar now shows **Fleet Management → Vehicles**, which it did not
  before. Open it: the list appears but there is no **Register vehicle** button and no
  edit or delete icons, because only *view* was granted.
- **Do:** sign back in as admin and untick it again.

### 1.5 Users

- **Do:** Administration → **Users** → **Add user**. Fill in:

  | Field | Value |
  | --- | --- |
  | Full name | `Test Dispatcher` |
  | Email | `dispatcher@itec.rw` |
  | Phone | `+250 788 123 456` |
  | Role | `Logistics Manager` |
  | Department | `Operations` |
  | Job title | `Dispatcher` |
  | Status | `Active` |
  | Privilege | `Standard` |
  | Force password change at next login | leave **on** |

- **Expect:** green bar *"User created."* and, under it, an **amber bar carrying a
  one-time password** such as *"One-time password for dispatcher@itec.rw:
  MusanzeCargo5705 — give it to them directly. They must change it at first sign-in,
  and it will not be shown again."* **Write that password down now**; reloading the
  page will not show it again, and it is never stored in plain text.
- **Do:** try to add a second user with the same email `dispatcher@itec.rw`.
- **Expect:** the form comes back with a red box: *"1 thing needs fixing before this
  can be saved"* and *"Email "dispatcher@itec.rw" is already used by another record."*
- **Do:** sign out. Sign in as `dispatcher@itec.rw` with the one-time password you wrote down.
- **Expect:** you are sent straight to **Change password** with an amber warning that a
  new password is required. Try to open `http://localhost/logistics-mvc/vehicles`
  directly — you are pushed back to the password page. Nothing else is reachable.
- **Do:** current = the one-time password, new `Dispatch2026Rw` twice, save.
- **Expect:** green bar *"Your password was changed."* and the full system opens up.
- **Do:** sign back in as admin, open Users, find Test Dispatcher, click the red bin.
- **Expect:** a dialog demanding a reason. Leave it blank and press **Delete record** —
  nothing happens and the box turns red. Type `Created during acceptance testing` and
  confirm.
- **Expect:** green bar *"User removed. It stays in the audit trail."* and the row is
  gone from the list.

### 1.6 Audit trail

- **Do:** Administration → **Audit trail**.
- **Expect:** a table of every action so far, newest first: your sign-ins, the failed
  attempts from Part 0, the lockout, settings changes, the permission change, the user
  created and the user deleted **with your reason shown in the last column**.
- **Do:** type `dispatcher` in the search box and press **Apply**.
- **Expect:** only the entries about that account.
- **Do:** click **Export CSV**.
- **Expect:** a file `audit-trail-<today>.csv` downloads and opens with the columns
  When, Who, Action, Entity, Reference, Reason.

---

# Part 2 — Warehouse Manager

Sign out, sign in as `nadine@itec.rw` / `password`.

**Expect first:** the sidebar shows only Dashboard, Transport (Transport requests,
Shipments), Warehouse (Inventory, Stock movements, Procurement, Suppliers) and Reports.
There is no Commercial, no Finance, no Administration.

### 2.1 A page you are not allowed to see

- **Do:** type `http://localhost/logistics-mvc/invoices` into the address bar.
- **Expect:** you land on the dashboard with an amber bar: *"Invoices is not available
  for the Warehouse Manager role."*

### 2.2 Add a stock item

- **Do:** Warehouse → **Inventory** → **Add item**. Fill in:

  | Field | Value |
  | --- | --- |
  | SKU | *leave blank* |
  | Item name | `Insulated cold box 40L` |
  | Category | `Cold chain` |
  | Warehouse | `Kigali Central Warehouse` |
  | Quantity on hand | `0` |
  | Unit of measure | `Unit` |
  | Minimum level | `10` |
  | Reorder quantity | `25` |
  | Status | `Out of stock` |
  | Unit cost | `38000` |
  | Batch number | `CB-2026-01` |
  | Expiry date | *leave blank* |
  | Storage temperature | `2 to 8 C` |

- **Expect:** green bar *"Stock item created."* The record page opens showing
  **SKU-2026-0001** (generated for you because you left it blank), a red
  **Out Of Stock** badge, and a highlight strip reading On hand `0 Unit`,
  Minimum `10`, Stock value `RWF 0`, Warehouse `Kigali Central Warehouse`.
- **Expect:** under **Stock movements** it says *"No movement recorded; the opening
  balance was set when the item was created."*

### 2.3 Prove the form validates

- **Do:** **Add item** again and press **Create stock item** with everything blank.
- **Expect:** the form returns with a red box listing every missing field:
  *Item name is required. Warehouse is required. Quantity on hand is required.
  Minimum level is required. Status is required. Unit cost is required.*
- **Do:** fill in Item name `Bad item`, Warehouse `Huye Depot`, Quantity `abc`,
  Minimum `5`, Unit cost `1000`, Status `In stock`, and save.
- **Expect:** *"Quantity on hand must be a number."*
- **Do:** set SKU to `KFF-COOL-001` (one that already exists) and save.
- **Expect:** *"SKU "KFF-COOL-001" is already used by another record."*
- **Do:** click **Cancel**.

### 2.4 Receive stock through the ledger

- **Do:** Warehouse → **Stock movements** → **Record movement**. Fill in:

  | Field | Value |
  | --- | --- |
  | Movement reference | *leave blank* |
  | Item | `Insulated cold box 40L` |
  | Warehouse | `Kigali Central Warehouse` |
  | Movement type | `Stock in` |
  | Quantity | `30` |
  | Unit cost | `38000` |
  | Moved at | today, `08:00` |
  | Source document type | `Manual adjustment` |
  | Source document reference | `OPENING-2026` |
  | Notes | `Opening stock for acceptance testing` |

- **Expect:** green bar *"Movement created."* The record shows **Balance after 30**.
- **Do:** go back to Inventory and open **Insulated cold box 40L**.
- **Expect:** On hand is now **30**, the badge has changed by itself from
  **Out Of Stock** to **In Stock**, Stock value reads **RWF 1,140,000**, and the
  **Stock movements** panel lists your movement.

### 2.5 The ledger refuses to go negative

- **Do:** Record another movement: same item, **Stock out**, quantity `50`.
- **Expect:** the form returns with *"Only 30 is on hand; this movement would take the
  balance below zero."* Nothing is saved.
- **Do:** change the quantity to `22` and save.
- **Expect:** balance after **8**. Open the item: On hand `8`, and because 8 is below
  the minimum of 10 the badge has turned amber **Reorder** on its own.

### 2.6 Purchase request with real lines

- **Do:** Warehouse → **Procurement** → **New purchase request**:

  | Field | Value |
  | --- | --- |
  | Request reference | *leave blank* |
  | Description | `Replenish insulated cold boxes` |
  | Category | `Cold chain` |
  | Requested by | `Nadine Tuyisenge` |
  | Supplier | `Secure Rwanda` |
  | Deliver to warehouse | `Kigali Central Warehouse` |
  | Expected date | one week from today |
  | Amount | `0` |
  | Status | `Draft` |

- **Expect:** green bar and a record page showing **PR-2026-0001**.
- **Do:** in **Requested items**, fill the first row: Item `Insulated cold box 40L`,
  Qty `25`, Unit `Unit`, Unit price `38000`, Stock item `Insulated cold box 40L`.
  Leave the other rows blank. Click **Save requested items**.
- **Expect:** green bar *"Requested items saved."* The **Amount** on the record has
  changed by itself from `RWF 0` to **RWF 950,000**, calculated from the line.
- **Expect:** the blank rows were ignored — only one line is listed.

### 2.7 The approval is not yours to give

- **Expect:** at the top of the record you see **What happens next** with buttons
  **Approve request**, **Reject** and **Mark received**… *only if your role may approve.*
  As Warehouse Manager the panel is **not** shown: your role can create and edit
  purchase requests but not approve them. That is correct.

### 2.8 Search, filter, sort, paginate, export

- **Do:** open Warehouse → **Inventory**. Type `cold` in the search box, press **Apply**.
- **Expect:** only items with "cold" in the name, SKU, category or warehouse. The count
  line above the table updates.
- **Do:** set the status filter to **Reorder**.
- **Expect:** only items at or below their minimum, including your cold box.
- **Do:** click the **On hand** column heading.
- **Expect:** the rows re-sort and a small arrow appears next to the heading. Click it
  again to reverse.
- **Do:** set the page size to `10`.
- **Expect:** pagination buttons appear underneath with *Page 1 of N*.
- **Do:** click **Export CSV**.
- **Expect:** the download contains **only the filtered rows**, not the whole table,
  with the same columns as the screen.
- **Do:** click **Clear**.
- **Expect:** the full list returns.

---

# Part 3 — Logistics Manager

Sign out, sign in as `aline@itec.rw` / `password`.

### 3.1 A new customer

- **Do:** Commercial → **Customers** → **Add customer**:

  | Field | Value |
  | --- | --- |
  | Customer code | *leave blank* |
  | Customer name | `Muhanga Health Centre` |
  | Type | `Government` |
  | TIN number | `109876543` |
  | Contact person | `Beatrice Uwera` |
  | Phone | `+250 788 777 111` |
  | Email | `supply@muhangahc.rw` |
  | Address | `Muhanga Town` |
  | District | `Muhanga` |
  | Payment terms | `30` |
  | Credit limit | `5000000` |
  | Status | `Active` |

- **Expect:** green bar and a record page headed **CUS-2026-0006** with a green
  **Active** badge. Panels for **Rate cards**, **Invoices** and **Recent trips** all
  say nothing is linked yet — correct for a brand-new customer.
- **Do:** put `not-an-email` in the Email field and save.
- **Expect:** *"Email is not a valid email address."*

### 3.2 Approve a transport request

- **Do:** Transport → **Transport requests**. Filter Status = **Pending**.
- **Do:** open **REQ-0312**.
- **Expect:** an amber **Pending** badge, and a **What happens next** panel with
  **Approve request** and **Reject**.
- **Do:** click **Reject**.
- **Expect:** a dialog asking for a reason. Press confirm with it empty — the box turns
  red and nothing happens.
- **Do:** close the dialog, click **Approve request**.
- **Expect:** green bar *"Request REQ-0312 approved. Plan a trip for it next."* The
  badge turns green **Approved**. A new **Decision** panel appears on the right showing
  *Decided by Aline Mukamana on \<today's date and time\>*.
- **Expect:** the buttons have changed: **Approve request** is gone (it cannot be
  approved twice) and **Plan trip** has appeared.

### 3.3 Book the shipment

- **Do:** Transport → **Shipments** → **Book shipment**:

  | Field | Value |
  | --- | --- |
  | Shipment reference | *leave blank* |
  | Customer | `Muhanga Health Centre` |
  | Source request | `REQ-0312` |
  | Loaded on trip | *leave blank for now* |
  | Consignee | `Muhanga Health Centre Pharmacy` |
  | Consignee phone | `+250 788 777 111` |
  | Origin | `Kigali Central Warehouse` |
  | Destination | `Muhanga Health Centre` |
  | Cargo type | `Cold chain` |
  | Cargo description | `Vaccine cold boxes, 6 units` |
  | Packages | `6` |
  | Gross weight | `180` |
  | Volume | `0.9` |
  | Declared value | `6500000` |
  | Minimum temperature | *leave blank* |
  | Maximum temperature | *leave blank* |
  | Status | `Booked` |
  | Special instructions | `Temperature logger travels with the load.` |

- **Expect:** the form is refused with *"Cold-chain cargo needs a temperature range."*
- **Do:** set Minimum temperature `2` and Maximum temperature `8`, save.
- **Expect:** green bar and record **SHP-2026-0006**, with a highlight strip showing
  Cargo, Weight `180.00 kg`, Packages `6`, Temperature `2.00 to 8.00 C`.
- **Do:** edit it and set Minimum `10`, Maximum `4`.
- **Expect:** *"Maximum temperature cannot be below the minimum temperature."*
  Put them back to 2 and 8.

### 3.4 Create the trip

- **Do:** Transport → **Trips** → **Create trip**:

  | Field | Value |
  | --- | --- |
  | Trip reference | *leave blank* |
  | Fulfils request | `REQ-0312` |
  | Customer | `Muhanga Health Centre` |
  | Trip type | `Delivery` |
  | Pickup location | `Kigali Central Warehouse` |
  | Final destination | `Muhanga Health Centre` |
  | Cargo summary | `6 vaccine cold boxes` |
  | Planned departure | tomorrow `07:00` |
  | Planned arrival | tomorrow `05:00` |
  | Vehicle | `RAB 118K` |
  | Driver | `Marie Uwase` |
  | Status | `Requested` |

- **Expect:** refused with *"Planned arrival cannot be before planned departure."*
- **Do:** change Planned arrival to tomorrow `10:30` and save.
- **Expect:** green bar and record **TRP-2026-0001**, amber **Requested** badge.
- **Do:** go to Shipments, edit **SHP-2026-0006**, set **Loaded on trip** to
  `TRP-2026-0001`, save.
- **Expect:** open the trip again — the **Shipments on board** panel now lists
  SHP-2026-0006 with its cargo, packages and weight.

### 3.5 Add the route stops

- **Do:** on the trip record, in **Stops on this route**, fill three rows:

  | # | Type | Location | Contact | Phone | Planned arrival |
  | --- | --- | --- | --- | --- | --- |
  | 1 | Pickup | `Kigali Central Warehouse` | `Nadine Tuyisenge` | `+250 782 110 554` | tomorrow 07:00 |
  | 2 | Checkpoint | `Muhanga weighbridge` | | | tomorrow 09:00 |
  | 3 | Drop-off | `Muhanga Health Centre` | `Beatrice Uwera` | `+250 788 777 111` | tomorrow 10:30 |

  Leave Status on each as **Pending**. Click **Save stops on this route**.
- **Expect:** green bar *"Stops on this route saved."* and the three stops listed in
  order, numbered 1, 2, 3.

### 3.6 Dispatch — the four refusals

This is the heart of the system. Each attempt must be refused for its own reason.

**a) Cold chain on the wrong vehicle**

- **Do:** on **TRP-2026-0001** click **Dispatch trip** (the vehicle is RAB 118K, which
  has no cooling unit).
- **Expect:** a red bar: *"RAB 118K has no cooling unit and this trip carries
  cold-chain cargo."* The trip is still **Requested**; nothing changed.

**b) Over capacity**

- **Do:** edit **RAB 118K** (Fleet Management → Vehicles) and tick **Cold chain unit
  fitted**, save. Then edit **SHP-2026-0006** and set Gross weight to `5000`, save.
  Go back to the trip and click **Dispatch trip**.
- **Expect:** *"The load is 5,000 kg but RAB 118K carries 1,200 kg."*
- **Do:** set the shipment weight back to `180`.

**c) Double booking**

- **Do:** edit the trip and change the **Vehicle** to `RAC 482D`, save. Click
  **Dispatch trip**.
- **Expect:** *"That vehicle or driver is already out on trip TRP-0248."* RAC 482D is
  genuinely in transit on the Huye run, so it cannot leave twice.

**d) No driver**

- **Do:** edit the trip, set **Vehicle** `RAB 332M` and clear the **Driver**, save.
  Click **Dispatch trip**.
- **Expect:** *"Assign both a vehicle and a driver before dispatching."*

### 3.7 Dispatch — success, and everything it changes

- **Do:** edit the trip: Vehicle `RAB 332M` (has a cooling unit, 3,000 kg), Driver
  `Marie Uwase`. Save. Click **Dispatch trip**.
- **Expect:** green bar *"TRP-2026-0001 dispatched. The vehicle and driver are now
  marked on trip."* The badge turns blue **In Transit**.
- **Now check the five things it changed on its own:**

  | Where to look | Expect |
  | --- | --- |
  | Fleet Management → Vehicles → RAB 332M | Status is now **On Trip** |
  | Fleet Management → Drivers → Marie Uwase | Status is now **On Trip** |
  | Transport requests → REQ-0312 | Status changed from Approved to **Assigned** |
  | Shipments → SHP-2026-0006 | Status changed from Booked to **In Transit** |
  | The trip record | *Dispatched at* and *Actual departure* are now filled in |

- **Expect:** you did not type any of those five changes yourself.

### 3.8 Record the delivery

- **Do:** Transport → **Deliveries** → **Record delivery**:

  | Field | Value |
  | --- | --- |
  | Delivery reference | *leave blank* |
  | Trip | `TRP-2026-0001` |
  | Shipment | `SHP-2026-0006` |
  | Attempt number | `1` |
  | Recipient name | `Beatrice Uwera` |
  | Recipient phone | `+250 788 777 111` |
  | Delivery address | `Muhanga Health Centre` |
  | Planned for | tomorrow `10:30` |
  | Status | `In transit` |

- **Expect:** green bar and record **DEL-2026-0001**. The **Attachments** panel shows
  *Proof of delivery — Missing* and *Recipient signature — Missing* in grey.

---

# Part 4 — Fleet Manager

Sign out, sign in as `eric@itec.rw` / `KigaliFresh2026` (the password you set in 0.5).

### 4.1 Register a vehicle

- **Do:** Fleet Management → **Vehicles** → **Register vehicle**:

  | Field | Value |
  | --- | --- |
  | Plate number | `RAD 220X` |
  | Vehicle type | `Refrigerated truck` |
  | Make | `Isuzu` |
  | Model | `Forward` |
  | Year of manufacture | `2023` |
  | Chassis number | `IS-FWD-220X` |
  | Payload capacity | `4500` |
  | Volume capacity | `20` |
  | Fuel type | `Diesel` |
  | Cold chain unit fitted | **on** |
  | Ownership | `Owned` |
  | Acquired on | `2026-02-10` |
  | Assigned driver | `Aurore Kayitesi` |
  | Status | `Available` |
  | Odometer reading | `4200` |
  | Next service due | 20 days from today |

- **Expect:** green bar *"Vehicle created."* and a record page headed **RAD 220X** with
  a green **Available** badge and a highlight strip: Odometer `4,200 km`, Payload
  `4,500 kg`, Driver `Aurore Kayitesi`, Next service.
- **Expect:** the form was laid out in four labelled sections — *Identification*,
  *Capacity and type*, *Assignment and status*, *Notes* — each with a hint, not one long
  column of boxes.
- **Do:** try to register another vehicle with plate `RAD 220X`.
- **Expect:** *"Plate number "RAD 220X" is already used by another record."*

### 4.2 Compliance document with an expiry alert

- **Do:** Fleet Management → **Vehicle documents** → **Add document**:

  | Field | Value |
  | --- | --- |
  | Reference | *leave blank* |
  | Vehicle | `RAD 220X` |
  | Document type | `Insurance` |
  | Document number | `SON-2026-99001` |
  | Issuer / insurer | `SONARWA General` |
  | Issued on | `2026-02-10` |
  | Expires on | **15 days from today** |
  | Cost | `820000` |
  | Status | `Valid` |

- **Expect:** green bar, and on the record the status is **not** *Valid* as you typed —
  it reads **Expiring**, because the system recalculated it from the expiry date.
- **Do:** open the Vehicle documents list.
- **Expect:** the **Expires** column shows the date followed by an amber badge like
  `15d`, counting down.
- **Do:** edit it and set **Expires on** to a date last month.
- **Expect:** the status becomes **Expired** and the list badge turns red, e.g. `30d late`.
- **Do:** open **RAD 220X** again.
- **Expect:** the **Compliance documents** panel on the vehicle lists this document.

### 4.3 Maintenance with real parts and a real cost

- **Do:** Fleet Management → **Maintenance** → **Create work order**:

  | Field | Value |
  | --- | --- |
  | Work order reference | *leave blank* |
  | Vehicle | `RAD 220X` |
  | Service | `First 5,000 km service` |
  | Maintenance type | `Preventive` |
  | Priority | `Normal` |
  | Service provider | `Fleet Workshop` |
  | Estimated cost | `150000` |
  | Odometer at service | `4200` |
  | Status | `In progress` |
  | Due date | today |

- **Expect:** green bar and record **MNT-2026-0001**. Highlight strip shows
  Estimated `RWF 150,000`, Actual **Not costed**.
- **Do:** open Vehicles → **RAD 220X**.
- **Expect:** its status changed by itself from Available to **Maintenance**, because
  the work order went in progress.
- **Do:** back on the work order, fill **Parts and labour**:

  | Type | Description | Part number | Qty | Unit cost |
  | --- | --- | --- | --- | --- |
  | Consumable | `Engine oil 15W40` | `OIL-15W40` | `18` | `3200` |
  | Part | `Oil filter` | `OF-2290` | `1` | `18000` |
  | Labour | `Service labour` | | `2` | `15000` |

  Click **Save parts and labour**.
- **Expect:** the lines are saved and each row shows its own line total.
- **Do:** click **Approve cost**.
- **Expect:** green bar *"Work order MNT-2026-0001 completed at RWF 105,600.
  RWF 44,400 under the estimate."* The figure came from your lines
  (18 × 3,200 + 18,000 + 2 × 15,000), not from anything you typed.
- **Do:** open **RAD 220X**.
- **Expect:** its status has returned to **Available** by itself.

### 4.4 Fuel and the odometer

- **Do:** Finance → **Fuel management** → **Record fuel**:

  | Field | Value |
  | --- | --- |
  | Reference | *leave blank* |
  | Vehicle | `RAD 220X` |
  | Station | `SP Nyabugogo` |
  | Fuel type | `Diesel` |
  | Purchased at | today `09:30` |
  | Litres | `85` |
  | Price per litre | `1650` |
  | Tank filled to full | **on** |
  | Odometer now | `4650` |

- **Expect:** green bar and record **FUE-2026-0001**, highlight strip showing
  Litres `85 L`, Total cost `RWF 140,250`, Odometer `4,650 km`.
- **Do:** open Vehicles → **RAD 220X**.
- **Expect:** the vehicle odometer moved from 4,200 to **4,650** on its own.
- **Do:** record another fuel entry for the same vehicle with **Odometer now** `3000`.
- **Expect:** *"Odometer 3000 is below the previous reading of 4,650 km for this
  vehicle."*
- **Do:** set it to `5100` and save. Open the record.
- **Expect:** **Previous odometer** shows `4,650` filled in for you, greyed out and
  marked *calculated*.

### 4.5 Attach a receipt and open it again

- **Do:** edit **FUE-2026-0001**, use **Receipt** to upload any PDF or JPG from your
  machine, save.
- **Expect:** on the record, **Attachments** shows *Receipt* with an **Open** link.
- **Do:** click **Open**.
- **Expect:** the file opens in a new tab. This is the part that was previously
  write-only — the file is stored outside the web root and served only to a signed-in
  user whose role may see fuel records.
- **Do:** copy that file URL, sign out, and paste it into a private window.
- **Expect:** you are refused, not given the file.
- **Do:** try to upload a `.php` or `.exe` file as a receipt.
- **Expect:** *"Receipt must be a PDF, image, Word or Excel file."*

---

# Part 5 — Driver

Sign out, sign in as `samuel@itec.rw` / `password`.

### 5.1 You only see your own work

- **Expect:** the sidebar is short: Dashboard, Transport (Trips, Shipments, Deliveries)
  and Fuel management. No Vehicles, no Customers, no Reports, no Administration.
- **Do:** open Transport → **Trips**.
- **Expect:** a small list, and above the table a grey badge **Scoped to your own work**.
  Every trip listed has Samuel Niyonzima as its driver.
- **Do:** as a check, sign in as admin in another browser and note how many trips the
  full list shows. Samuel's list is much shorter.
- **Do:** back as Samuel, open **TRP-2026-0001** by typing its URL directly
  (that is Marie Uwase's trip from Part 3 — get its id from the address bar when you
  view it as admin, for example `http://localhost/logistics-mvc/trips/158`).
- **Expect:** you are redirected to the trip list with a red bar: *"That record does not
  exist, or it is not visible to your role."* A driver cannot reach another driver's
  work by guessing a number.

### 5.2 Close your own delivery

- **Do:** open Transport → **Deliveries**, open **DEL-0218** (the Huye run).
- **Expect:** a **What happens next** panel with **Mark delivered** and
  **Record failure**.
- **Do:** click **Mark delivered**.
- **Expect:** green bar *"DEL-0218 marked delivered."* — and if no proof is attached it
  adds *"Proof of delivery is still missing; upload it on the delivery record."*
  The badge turns green **Delivered** and *Delivered at* is filled with the current time.

### 5.3 Record a failure properly

- **Do:** open another of your deliveries that is still in transit, click
  **Record failure**, and confirm with the reason box empty.
- **Expect:** the box turns red; nothing is saved.
- **Do:** type `recipient absent` and confirm.
- **Expect:** green bar *"… recorded as failed. Create a new attempt when it is
  rescheduled."* The badge turns red **Failed**, and on the record the **Exception**
  section shows Failure reason **Recipient absent** and your note.

### 5.4 Upload proof of delivery

- **Do:** edit a delivered record, upload any image as **Proof of delivery** and
  another as **Recipient signature**, save.
- **Expect:** both appear in **Attachments** with **Open** links that work.

### 5.5 The driver API

- **Do:** while signed in as Samuel, open
  `http://localhost/logistics-mvc/api/my/trips` in the same browser.
- **Expect:** JSON listing only Samuel's trips, each with reference, route, status,
  planned and actual times, vehicle and a delivery count.
- **Do:** open `http://localhost/logistics-mvc/api/modules/users`.
- **Expect:** `{"error":"This module is not available for your role."}` with status 403.

---

# Part 6 — Finance

Sign out, sign in as `emmanuel@itec.rw` / `password`.

### 6.1 Approve an expense

- **Do:** Finance → **Logistics expenses**. Filter Status = **Pending**. Open
  **EXP-0440**.
- **Expect:** an amber **Pending** badge, highlight strip with the amount, and buttons
  **Approve expense** and **Reject**.
- **Do:** click **Approve expense**.
- **Expect:** green bar *"Expense EXP-0440 approved for RWF 8,000."* Badge turns green.
  A **Decision** panel appears showing *Decided by Emmanuel Safari on \<now\>*.
- **Do:** open another pending expense and click **Reject**, reason
  `No receipt attached`.
- **Expect:** red **Rejected** badge and the **Decision** panel shows both the approver
  and **Rejection reason: No receipt attached**.

### 6.2 Agree a price

- **Do:** Commercial → **Rate cards** → **Add rate**:

  | Field | Value |
  | --- | --- |
  | Rate reference | *leave blank* |
  | Customer | `Muhanga Health Centre` |
  | Status | `Active` |
  | Origin | `Kigali` |
  | Destination | `Muhanga` |
  | Vehicle type | `Refrigerated truck` |
  | Charging basis | `Per trip` |
  | Rate amount | `310000` |
  | Minimum charge | `280000` |
  | Effective from | first of this month |
  | Effective to | `2026-12-31` |

- **Expect:** green bar and record **RATE-2026-0006**.
- **Do:** edit it and set **Effective to** to a date before **Effective from**.
- **Expect:** *"The rate cannot expire before it becomes effective."*
- **Do:** open Customers → **Muhanga Health Centre**.
- **Expect:** the **Rate cards** panel now lists it.

### 6.3 Raise an invoice and make the trip profitable

- **Do:** Commercial → **Invoices** → **Raise invoice**:

  | Field | Value |
  | --- | --- |
  | Invoice number | *leave blank* |
  | Customer | `Muhanga Health Centre` |
  | Trip | `TRP-2026-0001` |
  | Status | `Draft` |
  | Issue date | today |
  | Due date | 30 days from today |
  | VAT rate | `18` |

- **Expect:** green bar and record **INV-2026-0006**. Subtotal, VAT amount, Total and
  Amount paid are all greyed out and marked *calculated* — you cannot type them.
- **Do:** click **Issue invoice** straight away.
- **Expect:** refused: *"Add at least one invoice line before issuing this invoice."*
- **Do:** fill **Invoice lines**:

  | Description | Qty | Unit price |
  | --- | --- | --- |
  | `Cold-chain transport Kigali to Muhanga` | `1` | `310000` |
  | `Temperature monitoring` | `1` | `25000` |

  Click **Save invoice lines**.
- **Expect:** the highlight strip now reads Total **RWF 395,300**, Paid `RWF 0`,
  Balance **RWF 395,300**. That is 335,000 + 18% VAT of 60,300, worked out for you.
- **Do:** click **Issue invoice**.
- **Expect:** green bar *"Invoice INV-2026-0006 issued for RWF 395,300."* Badge turns
  blue **Issued**.

### 6.4 See the margin appear

- **Do:** Reports → **Trip profitability**. Set **From** to the first of this month and
  **To** to today, click **Run**.
- **Expect:** a row for **TRP-2026-0001** showing Revenue `RWF 395,300`, Fuel and Other
  cost from anything booked against that trip, Total cost, Margin and a Margin %.
  Before this release this report could not exist, because there was no revenue in the
  system at all.
- **Do:** click **Export CSV**.
- **Expect:** a file whose first lines are the report name and the period, then the
  column headings, then the rows, then the totals.

### 6.5 Chase an overdue invoice

- **Do:** open Commercial → **Invoices**, filter Status = **Overdue**.
- **Expect:** **INV-2026-0004** appears, and in the **Due** column the date carries a
  red badge saying how many days late it is.
- **Do:** click the bell.
- **Expect:** an update titled **Invoice overdue** naming that invoice and the amount
  outstanding.

---

# Part 7 — Management

Sign out, sign in as `jeanpierre@itec.rw` / `password`.

### 7.1 The reports gallery

- **Do:** open **Reports**.
- **Expect:** a grid of report cards, each with an icon, a name and one line saying what
  it answers, plus a table of saved report definitions with their owner and when each
  last ran.

### 7.2 Run each report

Open each in turn and set **From** to `2026-01-01` and **To** to today.

| Report | Expect |
| --- | --- |
| Vehicle utilization | One row per vehicle: trips, completed, cancelled, hours on road, completion % and share of trips. Totals above the table. |
| Fuel consumption | One row per vehicle that bought fuel: fill-ups, litres, average price per litre, total cost, last fill-up. A note says litres per 100 km will appear once route distance is added. |
| Delivery performance | One row per month: deliveries, delivered, failed, re-attempts, success rate, how many had a planned ETA, and the on-time rate. A note explains the 30-minute grace period. |
| Maintenance cost | One row per vehicle: work orders, completed, estimated, actual, and the variance in money and per cent. Your MNT-2026-0001 appears here. |
| Driver performance | One row per driver: trips, completed, deliveries, failures, completion % and on-time %. |
| Inventory movement | One row per item: in, out, adjustments, net change, closing balance, minimum and status. Your cold box shows in 30, out 22, closing 8. |
| Trip profitability | As in 6.4. |
| Expense summary | One row per category: claims, total, approved, pending, rejected and average claim. |

- **Do:** on any report, click the quick range **This month**.
- **Expect:** the dates change and the table reloads for that period.
- **Do:** pick a period with no activity, e.g. From `2020-01-01` To `2020-01-31`.
- **Expect:** a friendly empty state — *"No data in this period. Try a wider date range"* —
  not an error and not a blank page.

### 7.3 What Management cannot do

- **Do:** try `http://localhost/logistics-mvc/vehicles/create`.
- **Expect:** pushed back to the dashboard with *"Vehicles is not available for the
  Management role."*

---

# Part 8 — Cross-cutting checks

### 8.1 Every page opens

Sign in as admin and open each of these. None may show a PHP error, a blank page or a
missing style.

```text
/dashboard        /vehicles        /drivers          /vehicle_documents
/maintenance      /requests        /trips            /shipments
/deliveries       /customers       /rates            /invoices
/fuel             /expenses        /warehouse        /movements
/procurement      /suppliers       /users            /reports
/reports/catalogue                 /settings         /permissions
/audit            /account         /account/password
```

For each module also open `/create`, then one record, then that record's `/edit`.

### 8.2 The quick search

- **Do:** press `/` anywhere.
- **Expect:** the top search box takes focus. Type `inv` — a drop-down lists Invoices
  and any matching report. Arrow down and press Enter to jump there.
- **Expect:** signed in as a Driver, the same search offers only pages a driver may open.

### 8.3 Soft delete keeps the history

- **Do:** as admin, delete the customer **Muhanga Health Centre** with the reason
  `End of acceptance testing`.
- **Expect:** it disappears from the customer list.
- **Do:** open Administration → **Audit trail** and search `Muhanga`.
- **Expect:** the full history is still there — created, updated, deleted — with your
  reason. The record was hidden, not erased.
- **Do:** run the **Trip profitability** report again.
- **Expect:** it still works and still shows the invoice; deleting a customer did not
  corrupt past reporting.

### 8.4 The security token

- **Do:** open any record page, press F12, and in the console run
  `document.querySelectorAll('input[name="_token"]').length`.
- **Expect:** a number greater than zero — every form that changes something carries a
  token. Pages built from an outdated copy of a form are rejected with
  *"Your session security token expired. Please try that action again."*

### 8.5 The screen on a phone

- **Do:** press F12, switch to a phone-sized viewport, and open a list, a create form
  and a record page.
- **Expect:** the sidebar collapses, tables scroll sideways rather than breaking the
  layout, form fields stack one per row, and the save bar stays reachable at the bottom.

---

## Acceptance checklist

Tick each line only when the steps above passed.

| # | Area | Passed |
| --- | --- | --- |
| 1 | Sign-in, wrong password refused, lockout after five attempts | ☐ |
| 2 | Forgotten password: link created, rules enforced, link single-use | ☐ |
| 3 | New account gets a one-time password, shown once, and must change it before anything else opens | ☐ |
| 4 | Company settings change what the whole system shows | ☐ |
| 5 | Role permissions can be granted and revoked, and take effect at once | ☐ |
| 6 | A role is refused a module it does not hold | ☐ |
| 7 | Inventory item created; SKU generated; validation messages correct | ☐ |
| 8 | Stock ledger moves the balance and refuses to go below zero | ☐ |
| 9 | Stock status flips to Reorder and Out of stock on its own | ☐ |
| 10 | Purchase request lines set the request amount | ☐ |
| 11 | Search, filter, sort, page size and CSV export all work on a list | ☐ |
| 12 | Customer created; invalid email refused | ☐ |
| 13 | Request approved, with approver and time recorded; reason required to reject | ☐ |
| 14 | Shipment refused without a temperature range for cold chain | ☐ |
| 15 | Trip refused when planned arrival precedes planned departure | ☐ |
| 16 | Multi-stop route saved in order | ☐ |
| 17 | Dispatch refused: no cooling unit | ☐ |
| 18 | Dispatch refused: load over capacity | ☐ |
| 19 | Dispatch refused: vehicle already on another trip | ☐ |
| 20 | Dispatch refused: no driver | ☐ |
| 21 | Successful dispatch changed vehicle, driver, request, shipment and delivery by itself | ☐ |
| 22 | Vehicle registered; duplicate plate refused | ☐ |
| 23 | Document status recalculated to Expiring and Expired from its date | ☐ |
| 24 | Work order actual cost came from the parts and labour lines | ☐ |
| 25 | Vehicle returned to service by itself when the job completed | ☐ |
| 26 | Fuel moved the vehicle odometer; a lower reading was refused | ☐ |
| 27 | Uploaded receipt opens for an allowed user and is refused to a signed-out one | ☐ |
| 28 | Executable upload refused | ☐ |
| 29 | Driver sees only their own trips and deliveries | ☐ |
| 30 | Driver cannot open another driver's record by its id | ☐ |
| 31 | Driver marked a delivery delivered and recorded a failure with a reason | ☐ |
| 32 | Driver API returns only that driver's work; a forbidden module returns 403 | ☐ |
| 33 | Expense approved and rejected, with approver and reason on record | ☐ |
| 34 | Rate card created; invalid validity period refused | ☐ |
| 35 | Invoice refused without lines; totals and VAT calculated from the lines | ☐ |
| 36 | Trip profitability shows revenue, cost and margin for the trip | ☐ |
| 37 | Overdue invoice flagged in the list and in the bell | ☐ |
| 38 | All eight reports run, with an empty period handled gracefully | ☐ |
| 39 | Every page in 8.1 opens with no error | ☐ |
| 40 | Notifications can be marked read; the badge goes down | ☐ |
| 41 | Deleted record is hidden but its history survives in the audit trail | ☐ |
| 42 | Audit trail searchable, filterable and exportable | ☐ |
| 43 | Layout holds together on a phone-sized screen | ☐ |

---

## If a step fails

Note the step number and what you saw instead. The useful places to look are:

- `C:\xampp\apache\logs\error.log` for PHP errors.
- Administration → **Audit trail**, which records what the system thought happened.
- The automated suites, which cover the same ground and say which assertion broke:

```text
C:\xampp\php\php.exe tests\smoke.php
C:\xampp\php\php.exe tests\workflow.php
C:\xampp\php\php.exe tests\security.php
C:\xampp\php\php.exe tests\seed_integrity.php
```

## Putting the data back

This plan leaves your test records behind. To return to a clean seeded state:

```text
C:\xampp\mysql\bin\mysql.exe -uroot -e "DROP DATABASE logistics_mvc;"
C:\xampp\php\php.exe scripts\migrate.php
```
