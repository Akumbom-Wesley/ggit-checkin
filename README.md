# GGIT Check-in

A fast, mobile-friendly check-in app for the **GGIT Youth Conference 2026**:
*"A Seat at the Table of Innovation with GGIT"*.

Staff search for a participant by name or phone, tap **Check in**, and every other
check-in device updates within a few seconds. Walk-ins, partner/sponsor guests and
invited guests can be added on the spot, and the full attendance list downloads as Excel.

---

## Features

- **Instant search.** The whole guest list loads into the browser once, so search never waits on the network.
  - Search by first name, surname, any part of the name, phone (with or without `+237`) or email
  - Tolerates typos ("mireile" finds "Mireille")
  - Keyboard shortcuts on desktop: `/` search · `↑` `↓` choose · `Enter` check in
- **One-tap check-in** with Undo (toast button, or "Undo" on the card with a second tap to confirm)
- **Live sync between devices.** Laptops, phones and tablets all stay in step (polling every 3 s)
  - If two desks check in the same person, the second one is told "already checked in at 09:42"
  - Works through brief connection drops: the list stays searchable, and a red dot shows when offline
- **Add participant** for walk-ins, partner/sponsor guests, invited guests, or registered people missing from the list
  - Warns when the phone or email already exists, and offers to check that person in instead
  - Remembers the last partner/sponsor, so groups from the same organisation are quick to add
- **Summary page.** Arrivals overall, by category, by partner/sponsor, and the latest arrivals (auto-refreshes)
- **Data & Reports page**
  - Download an Excel attendance report (Attendance sheet + Summary sheet)
  - Import participants from `.xlsx` or `.csv` (Google Forms exports work as they are)
  - Clear all check-ins, or delete all participants (each asks for confirmation)
- **Shared staff PIN** protects the whole app
- **Responsive.** Phone (drawer menu), tablet and desktop (sidebar) layouts, in GGIT brand colours

### Participant categories

| Key | Label | Used for |
|---|---|---|
| `registered` | Registered | People who registered through the Google Form |
| `partner` | Partner / Sponsor | Guests sent by a partner or sponsor (with the organisation's name) |
| `invited` | Invited guest | People personally invited by GGIT |
| `walkin` | Walk-in | People registered at the door on the day |

---

## Tech stack

- **Laravel 12** (PHP 8.2+, tested on PHP 8.4 and 8.5)
- **MySQL** 8 / MariaDB
- **Blade + Alpine.js + Tailwind CSS v4**, built with Vite
- No extra Composer packages: Excel import/export uses PHP's built-in `zip` extension

---

## Requirements

- PHP 8.2+ with these extensions: `pdo_mysql`, `mbstring`, `zip`, `fileinfo`, `openssl`, `curl`
- Composer 2
- Node.js 18+ and npm (only needed to build the CSS/JS)
- MySQL or MariaDB (XAMPP's MySQL is fine locally)

---

## Local setup (Windows / XAMPP)

```bash
# 1. Get the code and install dependencies
git clone https://github.com/Akumbom-Wesley/ggit-checkin.git
cd ggit-checkin
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate
```

Create a MySQL database called `ggit_checkin` (collation `utf8mb4_unicode_ci`) in phpMyAdmin,
then edit `.env` (see [Configuration](#configuration)).

```bash
# 3. Database tables
php artisan migrate

# 4. Build the front end and run
npm run build
php artisan serve --host=0.0.0.0 --port=8000
```

Open http://127.0.0.1:8000 and log in with the `CHECKIN_PIN` from `.env`.

**Testing on a phone:** connect the phone to the same Wi-Fi, find the PC's IPv4 address (`ipconfig`),
and open `http://<that-address>:8000`. If it doesn't load, allow `php.exe` through Windows Firewall
on Private networks.

> Use `npm run build`, not `npm run dev`, when testing from phones. The Vite dev server
> only works on the machine it runs on.

### Loading participants

Either:

- **Recommended:** log in, then go to **Data & Reports → Import participants** and upload the Google Forms `.xlsx`, or
- run the seeder, which loads the cleaned list in `database/data/registrations.json`:

  ```bash
  php artisan db:seed --class=ParticipantSeeder
  ```

---

## Configuration

Key `.env` values:

```env
APP_NAME="GGIT Check-in"
APP_ENV=local                  # production on the server
APP_DEBUG=true                 # false on the server
APP_URL=http://localhost:8000
APP_TIMEZONE=Africa/Douala     # check-in times are shown in Cameroon time

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ggit_checkin
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_LIFETIME=1440          # devices stay logged in for 24 hours

CHECKIN_PIN=2026               # shared staff PIN; changing it signs every device out

# Optional
EVENT_NAME="GGIT Youth Conference 2026"
EVENT_THEME="A Seat at the Table of Innovation with GGIT"
CHECKIN_SYNC_MS=3000           # how often devices pull each other's check-ins (ms)
```

`config/app.php` must read the timezone from `.env`:

```php
'timezone' => env('APP_TIMEZONE', 'Africa/Douala'),
```

After changing `.env`, run `php artisan config:clear` (or `php artisan config:cache` in production).

---

## Using the app

### Check-in screen

1. Type part of a name or phone number in the search box.
2. Tap **Check in** next to the right person. A green bar confirms it, with an **Undo** button.
3. Not on the list? Tap **+ Add** (phone) or **Add participant** (desktop).
   - Choose **Walk-in**, **Partner / Sponsor**, **Invited guest** or **Registered (missing)**
   - Name, phone and email are required. For partner guests, also enter the organisation
   - If an amber box appears, that phone or email is already on the list. Use its **Check in** button instead of adding a duplicate

Notes such as dietary needs or "volunteer" are shown on the card. Important ones are highlighted with ⚠.

### Summary

Arrivals overall, by category and by partner/sponsor, plus the latest 10 arrivals.
The page refreshes every 20 seconds.

### Data & Reports

| Action | What it does |
|---|---|
| **Download Excel** | `GGIT-Attendance-YYYY-MM-DD-HHMM.xlsx` with an **Attendance** sheet (checked-in people first, in arrival order) and a **Summary** sheet |
| **Import participants** | Adds people from a `.xlsx` or `.csv` file (see below) |
| **Clear check-ins** | Sets everyone back to "not arrived". Participants are kept |
| **Delete all** | Removes every participant and check-in. Cannot be undone |

Every open device reloads its list automatically after an import, clear or delete.

### Import file format

The first row must be the column headers. Columns are recognised by their header text:

| Field | Recognised headers |
|---|---|
| Name (**required**) | "Full name", or "First name" + "Last name" |
| Phone | "Phone", "WhatsApp", "Mobile", "Tel", "Contact" |
| Email | "Email", "Email address" |
| Category | "Category", "Type", "Group". Values: Registered, Partner, Sponsor, Invited, Guest, VIP, Walk-in |
| Partner / Sponsor | "Partner", "Sponsor", "Organisation", "Company", "Institution" |
| Notes | "Notes", "Comments", "Remarks", "Anything we should know?" |
| Registered on | "Timestamp", "Registered", "Date" |

- Rows without a name are skipped.
- With **Skip people already on the list** ticked, rows whose phone or email already exists are skipped.
- People with **no phone and no email** can't be detected as duplicates, so importing the same file twice would add them again. To re-load a list, **Delete all first, then import**.
- A CSV template is available from the Import section.

---

## Project structure

```
app/
├── Exceptions/ImportException.php          # import errors shown to staff
├── Http/
│   ├── Controllers/
│   │   ├── AuthController.php              # PIN login / logout
│   │   ├── ParticipantController.php       # JSON API: list, sync, check-in, undo, add
│   │   ├── SummaryController.php           # Summary page
│   │   ├── DataManagementController.php    # Data & Reports: import, clear, delete
│   │   └── ExportController.php            # Excel download
│   ├── Middleware/RequirePin.php           # staff PIN guard
│   └── Requests/ImportParticipantsRequest.php
├── Models/Participant.php                   # categories, phone normalisation
├── Services/ParticipantImporter.php        # maps spreadsheet columns → participants
└── Support/
    ├── DatasetVersion.php                  # tells devices to reload after import/reset/delete
    ├── SimpleXlsx.php                      # Excel writer (no packages)
    └── SpreadsheetReader.php               # .xlsx / .csv reader (no packages)

config/checkin.php                          # PIN, event name/theme, sync interval
database/
├── data/registrations.json                 # cleaned Google Forms list (seeder source)
├── migrations/…_create_participants_table.php
└── seeders/ParticipantSeeder.php

resources/
├── css/app.css                             # Tailwind + GGIT colours + form styles
├── js/
│   ├── app.js                              # Alpine setup
│   ├── checkin.js                          # search, check-in, sync, stats
│   └── add-participant.js                  # add-participant form
└── views/
    ├── layouts/app.blade.php
    ├── components/app-header.blade.php     # shared top bar
    ├── components/app-drawer.blade.php     # phone/tablet menu
    ├── login.blade.php
    ├── checkin.blade.php
    ├── summary/index.blade.php
    ├── data/index.blade.php
    └── partials/ (stats, side-panel, add-modal, logout-button)
```

### API (used by the check-in screen; requires the staff PIN session)

| Method | URL | Purpose |
|---|---|---|
| GET | `/api/participants` | Full list + server time + dataset version |
| GET | `/api/participants/changes?since=…` | Everything changed since the last sync |
| POST | `/api/participants` | Add a participant (409 = possible duplicate, 422 = validation) |
| POST | `/api/participants/{id}/checkin` | Check in (409 = already checked in) |
| POST | `/api/participants/{id}/undo` | Undo a check-in |

---

## Deployment (Ubuntu VPS + Nginx)

The built assets in `public/build` are committed, so the server doesn't need Node.
Run `npm run build` locally before every push.

```bash
# Server packages (PHP 8.4 via the ondrej PPA)
sudo apt install -y nginx mysql-server git unzip \
  php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml \
  php8.4-zip php8.4-curl php8.4-bcmath php8.4-intl

# App
cd /var/www
sudo git clone https://github.com/YOUR-USERNAME/ggit-checkin.git
sudo chown -R $USER:www-data ggit-checkin && cd ggit-checkin
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate   # then edit .env
php artisan migrate --force
sudo chown -R www-data:www-data storage bootstrap/cache
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

Production `.env` differences:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://checkin.yourdomain.com
SESSION_SECURE_COOKIE=true
CHECKIN_PIN=a-new-pin-for-the-event
```

Nginx: point `root` to `/var/www/ggit-checkin/public`, use the standard Laravel `try_files`
block, and pass `.php` to `unix:/run/php/php8.4-fpm.sock`. Then add SSL:

```bash
sudo certbot --nginx -d checkin.yourdomain.com
```

### Updating

```bash
cd /var/www/ggit-checkin
git pull
composer install --no-dev -o
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

---

## Event-day checklist

**Night before**
- [ ] On Data & Reports: **Delete all**, then import the final list. Check the participant count
- [ ] Import or add partner/sponsor and invited guest lists
- [ ] Log in on every check-in device and add the page to the home screen
- [ ] Set screens to not sleep, charge everything, and bring power banks
- [ ] Brief staff: search by surname or the last phone digits → **Check in** → **Undo** if wrong → **+ Add** if not on the list

**Morning**
- [ ] Every device shows a green **Live** dot
- [ ] Mobile-data hotspot ready as a backup to the venue Wi-Fi
- [ ] Download the Excel once as an offline backup list

**During the event**
- [ ] Download the Excel every hour or so as a running backup

**After the event**
- [ ] Download the final Excel report
- [ ] Back up the database: `mysqldump -u ggit -p ggit_checkin > ggit-checkin-$(date +%F).sql`
- [ ] Change `CHECKIN_PIN` (signs all devices out) or take the site down

---

## Troubleshooting

| Problem | Fix |
|---|---|
| `Access denied for user 'root'@'localhost'` | Wrong `DB_USERNAME`/`DB_PASSWORD` in `.env`, then `php artisan config:clear` |
| `Class "App\…" not found` | Check the file's folder, exact file name and `namespace` line, then `composer dump-autoload` |
| `Unable to locate a class or view for component [app-header]` | The file must be at `resources/views/components/app-header.blade.php`; then `php artisan view:clear` |
| Styles missing or old | `npm run build`, then hard refresh (Ctrl+F5) |
| `npm install` fails with `EBUSY` / `EPERM` (Windows) | Close VS Code and running servers, delete `node_modules`, retry; exclude the project folder from Windows Defender |
| Phone can't reach the laptop | Same Wi-Fi, `php artisan serve --host=0.0.0.0`, allow `php.exe` in Windows Firewall |
| Excel export error | Enable `extension=zip` in `php.ini` (local) or install `php8.4-zip` (server) |
| Times are an hour off | `APP_TIMEZONE=Africa/Douala` in `.env`, and `config/app.php` reads it |
| A device keeps showing old data | Pull to refresh or reopen the page; it reloads automatically after imports/deletes |

---

## Privacy

The database and `database/data/registrations.json` contain participants' names, phone
numbers and emails. Keep the repository **private**, share the staff PIN only with
check-in staff, and delete or archive the data once the event reporting is done.

---

Built by **I-NNOVA CM**, Bamenda, for Growing Girls Initiative.