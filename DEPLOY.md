# Putting LMS on a cPanel server

Written for whoever is doing the hosting. Follow it top to bottom; nothing here
assumes you have seen the code.

You need about twenty minutes, a cPanel login, and the ZIP file that came with
this document.

---

## What you are installing

| | |
|---|---|
| Language | PHP 8.1 or newer (8.2 recommended) |
| Database | MySQL 5.7+ or MariaDB 10.4+ |
| PHP extensions | `pdo_mysql`, `mbstring`, `curl`, `json`, `fileinfo` |
| Disk | About 30 MB, plus whatever proof-of-delivery files grow to |
| Outgoing network | HTTPS to `api.resend.com`, for email |

All of these are standard on shared cPanel hosting. If email is the only thing
that fails, it is almost always the last line: some hosts block outgoing HTTPS
from PHP and have to be asked to open it.

---

## Step 1 — Create the database

**cPanel → MySQL® Databases**

1. Under **Create New Database**, type a name, for example `lms`. cPanel will
   prefix it with your account name, so the real name becomes something like
   `myaccount_lms`. **Write the full name down.**
2. Under **MySQL Users → Add New User**, create a user, for example `lmsuser`,
   with a long password. Again the real name will be `myaccount_lmsuser`.
   **Write both down.**
3. Under **Add User To Database**, put that user on that database and tick
   **ALL PRIVILEGES**.

You now have three things you will need in step 4: database name, user name,
password.

---

## Step 2 — Import the database

**cPanel → phpMyAdmin**

1. On the left, click the database you just made. It should be empty.
2. Click **Import**.
3. **Choose File** → `database/install.sql` from the ZIP.
4. Scroll down, click **Go**.

It should finish with a green message and 52 tables on the left.

If it stops with *"MySQL server has gone away"* or a size error, the file is too
big for the host's import limit. Use **cPanel → Terminal** instead:

```
mysql -u myaccount_lmsuser -p myaccount_lms < database/install.sql
```

### What just got created

Every table, plus the things a company cannot start without: the roles and what
each one may do, the chart of accounts, the currencies, the payment methods, the
drop-down lists, the border posts, the accounting periods, and the sign-in
accounts.

**No business data.** No vehicles, no customers, no trips, no invoices. Those
are yours to enter.

---

## Step 3 — Upload the files

**cPanel → File Manager**

There are two ways to lay this out. The first is safer and is what you should do
if the host lets you.

### The safe layout (recommended)

Put the application **above** the web root, and point the domain at its `public`
folder. Nothing but `public` is then reachable from the internet — the database
password, the code and the uploaded files simply are not on a path a browser can
ask for.

1. Go to the **home directory** (`/home/myaccount`), not `public_html`.
2. **Upload** the ZIP there and **Extract** it. You get `/home/myaccount/lms`.
3. **cPanel → Domains** (or **Addon Domains**), find the domain or subdomain,
   and set its **Document Root** to:

   ```
   /home/myaccount/lms/public
   ```

4. Leave `LOGISTICS_BASE_URL` blank in step 4.

### The simple layout (if you cannot change the document root)

Some shared plans will not let you move the document root. Then:

1. Upload the ZIP into `public_html` and extract it there.
2. The `.htaccess` in the root of the project sends every request into
   `public/`, so the site works.
3. Leave `LOGISTICS_BASE_URL` blank if the app is at the domain root, or set it
   to `/lms` if you extracted into `public_html/lms`.

This works, but the code and the `.env` file are inside the web root. The
project ships with `.htaccess` files that deny access to them; that protection
is real, but it depends on Apache honouring `.htaccess`. The first layout does
not depend on anything.

### Folder permissions

One folder has to be writable, because uploads are written into it — proof of
delivery, receipts, scanned documents and profile photographs:

```
storage/uploads               755   (or 775 if your host runs PHP as a different user)
storage/uploads/avatars       same
storage/uploads/deliveries    same
storage/uploads/expenses      same
storage/uploads/fuel          same
storage/uploads/vehicle_documents  same
```

In File Manager you can set them all at once: select `storage`, **Permissions**,
tick **Recurse into subdirectories**.

Everything else can stay at `755` for folders and `644` for files.

---

## Step 4 — Configure it

In File Manager, find `.env.example` in the project root. **Copy** it, rename
the copy to `.env`, then **Edit** it.

These are the lines that matter. Everything else can stay as it is.

```ini
# From step 1. The full names, with the cPanel prefix.
LOGISTICS_DB_HOST=localhost
LOGISTICS_DB_NAME=myaccount_lms
LOGISTICS_DB_USER=myaccount_lmsuser
LOGISTICS_DB_PASS=the password you wrote down

# Blank if the app is at the domain root.
# /lms if you extracted into public_html/lms.
LOGISTICS_BASE_URL=

# The full public address. Links inside emails need this: an email cannot
# work out where it came from. No trailing slash.
APP_URL=https://lms.yourdomain.com

# Never 1 on a server the public can reach. A full error page tells a
# stranger your database name and your folder layout.
APP_DEBUG=0

# Email. See step 6.
RESEND_API_KEY=
SMTP_FROM_EMAIL=lms@yourdomain.com
SMTP_FROM_NAME=Your Company

# Leave blank so mail goes to the real recipient.
MAIL_REDIRECT_ALL_TO=
```

**Save.**

---

## Step 5 — Check it before anyone signs in

**cPanel → Terminal**, then:

```
cd ~/lms
php scripts/doctor.php
```

It reads the database and compares it against what the code expects. You want
the last line to read:

```
Nothing is missing. This database matches the code.
```

If it names something missing, it prints the one command that fixes it.

**If your plan has no Terminal**, open the site in a browser instead. A working
install shows the sign-in page. A blank page or a 500 means PHP could not start
— check **cPanel → Errors** or the `error_log` file, and the usual cause is a
wrong database password in `.env`.

### Close the doors the code ships with

Still in Terminal, and this is the one step not to skip:

```
php scripts/secure.php --apply
```

Every account ships with a password that is written in the source code. This
does not lock anybody out: they sign in as before and are taken straight to the
change-password page. Without it, anybody who has seen this code knows the
super-admin password to your live system.

**No Terminal?** Then sign in as each account and change its password by hand,
under **Account → Change password**. There are eight of them.

---

## Step 6 — Email

The system writes every message to an outbox first and hands it to the provider
second, so nothing is ever lost when the network hiccups.

1. Create an account at **resend.com**.
2. **Domains → Add Domain**, enter your domain, and add the DNS records it gives
   you (**cPanel → Zone Editor**). Wait for it to go green. Until it does, the
   provider will only deliver to the address that owns the Resend account.
3. **API Keys → Create API Key**. Copy it into `.env` as `RESEND_API_KEY`.
4. Set `SMTP_FROM_EMAIL` to an address on that verified domain.

### Test it

```
php scripts/mail.php --list      what is waiting
php scripts/mail.php             send it
```

---

## Step 7 — The two scheduled jobs

**cPanel → Cron Jobs**

Two jobs. Both are safe to run as often as you like.

### Daily warnings — once a day, early

```
Minute 0   Hour 6   Day *   Month *   Weekday *
```

```
cd /home/myaccount/lms && /usr/local/bin/php scripts/alerts.php
```

This is what notices that a driver's licence expires next month, that a vehicle
is due for service, that stock has fallen under its minimum and that an invoice
has gone past its due date. Without it, those warnings only appear when somebody
happens to sign in.

### Send the outbox — every ten minutes

```
Minute */10   Hour *   Day *   Month *   Weekday *
```

```
cd /home/myaccount/lms && /usr/local/bin/php scripts/mail.php
```

This drains the outbox and retries anything the network refused. A message is
never lost without it, but it may sit unsent until somebody notices.

> The path to PHP differs between hosts. **cPanel → Terminal**, then
> `which php`, gives you the right one. On many cPanel servers it is
> `/usr/local/bin/php` or `/opt/cpanel/ea-php82/root/usr/bin/php`.

---

## Step 8 — First sign-in

Open the site. You get a sign-in page — there is no public home page, which is
deliberate.

Sign in as the administrator. You will be asked to choose a new password
immediately, which is step 5 working.

Then, in this order:

1. **Administration → Settings** — and do this first, before anything is
   printed or sent. The install ships these deliberately blank so that nothing
   goes out carrying somebody else's trading name or tax number:

   | Setting | Where it appears |
   |---|---|
   | Company name | The head of every book, report and export; the browser tab; every email |
   | Address, phone, email | The line under the name on every exported page |
   | TIN number | The same line, and on invoices |
   | Logo | Exported PDFs and Excel workbooks |
   | Report colour | The heading colour of exports |

   Until the name is filled in, exports print `LMS LOGISTICS`. Nothing in the
   code names a company: every one of these is read from here.
2. **Accounting → Currencies** — which currency is the base. Everything is
   stored in the currency it was agreed in and nothing is converted, so each
   currency keeps its own set of books.
3. **Accounting → Chart of accounts** — the standard chart is already there. Add
   or rename what your accountant wants.
4. **Accounting → Payment methods** — your real bank account numbers and mobile
   money codes. These appear on invoices, so customers know where to pay.
5. **Administration → Role permissions** — check each role sees what it should.
6. **Administration → Users** — add your own people and deactivate the demo
   accounts you do not need.
7. **Settings → Lookups** — expense categories, cargo types, failure reasons.
   Every drop-down in the system is editable here; nothing is fixed in the code.

Then start entering vehicles, drivers, warehouses, customers and rate cards.

---

## If something goes wrong

| What you see | What it usually is |
|---|---|
| Blank white page | PHP fatal error. **cPanel → Errors**, or the `error_log` file in the project folder. |
| "Database connection failed" | The name, user or password in `.env`. Remember the cPanel prefix. |
| Every link 404s | `LOGISTICS_BASE_URL` does not match where the app actually sits. |
| CSS missing, page unstyled | Same cause. Or `.htaccess` is not being read — ask the host whether `AllowOverride` is on. |
| Emails never arrive | `php scripts/mail.php --list` shows why, per message. Usually the domain is not verified yet, or the host blocks outgoing HTTPS. |
| Uploads fail | `storage/uploads` is not writable. Set it to 775. |
| "unknown column" anywhere | `php scripts/doctor.php` names it and prints the fix. |

---

## Backing it up

Two things to keep, and they are not the same thing:

1. **The database** — cPanel → Backup → Download a MySQL Database Backup. This
   is the business: every trip, invoice, payment and journal entry.
2. **`storage/uploads`** — the signed delivery notes, the receipts, the scanned
   documents. These are not in the database and cannot be recreated.

Weekly is a reasonable minimum. Daily if you are invoicing from it.

---

## Updating later

1. Back up the database first.
2. Upload the new files over the old ones. **Do not overwrite `.env`** and do not
   touch `storage/uploads`.
3. Run the migrations:

   ```
   cd ~/lms && php scripts/migrate.php
   ```

   It only applies what has not been applied, so running it twice is safe.
4. `php scripts/doctor.php` to confirm.

No Terminal? Import the new files in `database/migrations/` through phpMyAdmin,
in filename order, skipping any you have already run.
