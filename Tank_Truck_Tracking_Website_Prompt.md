# Prompt: Tank Truck Installation Tracking Website

Copy everything below into your AI coding tool (e.g. Claude Code) or hand it to a developer.

---

## Project Summary

Build a fast, single-page web app to track GPS/MDVR/Door Sensor installation progress
across a fleet of tank trucks owned by multiple hauler companies. Replaces a manual
Excel workflow (truck master list + separate Omnitraq log + separate MDVR/Howen log).

## Tech Stack

- **Frontend:** Plain HTML + CSS + vanilla JavaScript (no framework). Use the
  `fetch` API for AJAX calls to PHP endpoints that return JSON. Keep JS modular
  (one file per page/feature: `trucks.js`, `filters.js`, `technicians.js`, etc.)
  and skip large third-party libraries — a small hand-written table/pagination
  helper is enough for this dataset size (~75-200 rows).
- **Backend:** PHP 8.x, no framework required — plain PHP with a small routing
  layer (or Slim if you want more structure) is enough for this app's size.
  Use PDO with the `sqlsrv`/`pdo_sqlsrv` driver to talk to MSSQL — always with
  prepared statements, never string-concatenated SQL.
- **Database:** Microsoft SQL Server. Add non-clustered indexes on
  `plate_number`, `me_no`, `location`, `technician_id`, and each install
  `status` column, since those are the primary filters. Use `NVARCHAR` (not
  numeric types) for IMEI/ICCID/SIM fields to avoid precision loss.
- **Auth:** two separate login areas/endpoints — Admin and Technician. See the
  **Authentication** section below.
- **Performance targets:** initial load < 2s, filter/search interactions <
  300ms. Achieve this with:
  - Server-side pagination and filtering in PHP/SQL — never pull the full
    table to the browser. Use `ORDER BY ... OFFSET/FETCH` in MSSQL, ~25-50
    rows per page.
  - Proper indexes so filter queries hit an index seek, not a table scan.
  - PHP OPcache enabled in production so scripts aren't recompiled per request.
  - Persistent/pooled DB connections (sqlsrv driver pools by default) instead
    of a fresh connection per request.
  - Debounced search input on the client (~300ms after typing stops before
    firing the AJAX request).
  - Small reference lookups (haulers, technicians, locations) fetched once and
    cached in a JS object for the session instead of re-queried per filter
    change.
  - Minified/combined CSS and JS, gzip/br compression enabled on the web
    server (IIS/Apache/Nginx).
  - Bottom line: this stack is not slower than a JS-framework app at this
    dataset size — the bottleneck is always query pattern, not PHP vs. Node.

## Data Model / Database Schema

All tables use MSSQL types below. Use `INT IDENTITY(1,1) PRIMARY KEY` for IDs
unless noted otherwise.

### `haulers` (Company)
| column | type | notes |
|---|---|---|
| id | INT IDENTITY PK | |
| name | NVARCHAR(200), UNIQUE, NOT NULL | e.g. "F.M. Castillo Sons Trucking and Services Corporation" |
| region | NVARCHAR(100) | e.g. "SOUTH LUZON" |
| created_at | DATETIME2, default GETDATE() | |

### `trucks`
| column | type | notes |
|---|---|---|
| id | INT IDENTITY PK | |
| hauler_id | INT, FK -> haulers.id | required |
| me_no | NVARCHAR(20) | Body/ME No., e.g. "BT-012" |
| plate_number | NVARCHAR(20), UNIQUE, NULLABLE | e.g. "NKR-7599" (nullable — some trucks aren't plated yet) |
| tractor_model | NVARCHAR(50) | e.g. "Hino", "Isuzu", "Scania" |
| location | NVARCHAR(100), INDEXED | e.g. "MABINI" (plant/site) — plan for more locations later |
| is_active | BIT, default 1 | soft-delete flag instead of hard delete |
| created_at / updated_at | DATETIME2 | |

### `technicians`
| column | type | notes |
|---|---|---|
| id | INT IDENTITY PK | |
| nickname | NVARCHAR(50), UNIQUE, NOT NULL, INDEXED | e.g. "BALEN", "MJHAY", "KRISTIAN" — this is also their login username |
| is_active | BIT, default 1 | so you can remove/deactivate without losing history |
| created_at | DATETIME2 | |

### `truck_assignments` (which technician is assigned to which truck/installation)
| column | type | notes |
|---|---|---|
| id | INT IDENTITY PK | |
| truck_id | INT, FK -> trucks.id | |
| technician_id | INT, FK -> technicians.id | |
| install_type | NVARCHAR(20) CHECK IN ('MDVR','OMNITRAQ','DOOR_SENSOR') | |
| assigned_date | DATE | |
| Allow multiple rows per truck (one per install type, can have different techs) |

### `omnitraq_installs`
| column | type | notes |
|---|---|---|
| id | INT IDENTITY PK | |
| truck_id | INT, FK -> trucks.id, NOT NULL | |
| omnitraq_no | NVARCHAR(50) | (from Howen sheet's "OMNITraq#" linkage) |
| imei | NVARCHAR(30) | e.g. "868933080747788" — store as text, not numeric |
| sim_iccid | NVARCHAR(30) | eSIM ICCID — store as text, not numeric (precision/leading-digit loss otherwise) |
| install_date | DATE | |
| technician_id | INT, FK -> technicians.id | |
| status | NVARCHAR(20) CHECK IN ('not_started','installed','verified'), INDEXED | |
| remarks | NVARCHAR(500) | |

### `mdvr_installs`
| column | type | notes |
|---|---|---|
| id | INT IDENTITY PK | |
| truck_id | INT, FK -> trucks.id, NOT NULL | |
| mdvr_type | NVARCHAR(10) CHECK IN ('NEW','OLD'), NOT NULL | radio button on the form: **New MDVR** (we installed the unit) vs **Old MDVR** (another company already installed the MDVR hardware — we only integrate our server and install a new SIM card). Both types capture the same full set of fields below, since we still need the existing unit's details to integrate it. |
| device_serial | NVARCHAR(30) | required for both NEW and OLD — for OLD this is the existing unit's serial (needed to integrate it into our server) |
| sim_iccid | NVARCHAR(30) | store as text — for OLD this is the *new* SIM being installed into the existing unit |
| sim_number | NVARCHAR(20) | store as text |
| install_date | DATE | |
| technician_id | INT, FK -> technicians.id | |
| integrated | BIT | whether our server integration is complete — applies to both NEW and OLD |
| visible | BIT | |
| performance_status | NVARCHAR(200) | e.g. "NOT ENOUGH DATA / RECHECK ANOTHER DATE" |
| detailed_remarks | NVARCHAR(MAX) | |
| documentation_link | NVARCHAR(500) | URL |
| status | NVARCHAR(20) CHECK IN ('not_started','installed','verified'), INDEXED | |

### `door_sensor_installs`
| column | type | notes |
|---|---|---|
| id | INT IDENTITY PK | |
| truck_id | INT, FK -> trucks.id, NOT NULL | |
| installed | BIT | radio button Installed / Not Installed |
| install_date | DATE, NULLABLE | |
| remarks | NVARCHAR(500) | |
| **NOTE:** flagged for future expansion — keep this table minimal for now, but design the schema so more fields (sensor model, IMEI, etc.) can be added later without breaking the UI. |

### `admin_users` (Admin login)
| column | type | notes |
|---|---|---|
| id | INT IDENTITY PK | |
| username | NVARCHAR(50), UNIQUE, NOT NULL | |
| password_hash | NVARCHAR(255), NOT NULL | hashed with PHP `password_hash()` (bcrypt/argon2), never store plaintext |
| is_active | BIT, default 1 | |
| created_at | DATETIME2 | |

### Derived field: `overall_completion`
A truck is "Completed" only when Omnitraq = installed AND MDVR = installed AND
Door Sensor = installed. Compute this in a view or on the fly — don't duplicate
as a stored column unless performance requires it.

---

## Authentication (two separate login endpoints)

Two distinct login areas, each with its own PHP session scope and permissions.
Do not merge them into one login form.

### 1. `/admin/login.php` — Admin login
- Username + password, checked against `admin_users` (password hashed with
  `password_hash()` / verified with `password_verify()`).
- On success, start a PHP session with `$_SESSION['role'] = 'admin'` and
  `$_SESSION['admin_id']`.
- Full CRUD access: haulers, trucks, technicians, all install records,
  assignments, and reports/filters.
- Protect every admin page/endpoint with a guard that checks
  `$_SESSION['role'] === 'admin'`, redirecting to `/admin/login.php` if not set.

### 2. `/tech/login.php` — Technician login (no password)
- Single input: pick or type their **nickname** (matched against
  `technicians.nickname` where `is_active = 1`). No password field at all.
- Best UX: render it as a dropdown/searchable list of active technician
  nicknames rather than free-text, so a tech can't "log in" as a name that
  doesn't exist or fat-finger it.
- On success, start a session with `$_SESSION['role'] = 'technician'` and
  `$_SESSION['technician_id']`.
- Scoped permissions — a technician can only:
  - View trucks assigned to them (via `truck_assignments`).
  - Update the install status/fields (MDVR form, Omnitraq form, Door Sensor
    radio button) for trucks assigned to them.
  - They **cannot**: add/remove haulers, trucks, or other technicians; see
    trucks not assigned to them; reassign technicians.
- Protect every technician page/endpoint with a guard that checks
  `$_SESSION['role'] === 'technician'`, and filter every query by
  `technician_id = $_SESSION['technician_id']`.

### Session handling notes
- Use PHP's built-in session handling (`session_start()`), `HttpOnly` and
  `Secure` cookie flags, and a reasonable session timeout (e.g. 8 hours,
  matching a work shift).
- Since technician login has no password, treat it as low-friction identity
  selection rather than a security boundary — keep it on a trusted internal
  network/VPN, and don't expose sensitive company-wide data (other haulers,
  full fleet list, admin functions) through the tech endpoint even if someone
  guesses/selects another technician's nickname. The main risk to guard
  against is a technician accidentally editing another technician's assigned
  truck, not external attackers — but still apply the scoping rules above
  strictly.
- Two separate route prefixes (`/admin/...` and `/tech/...`) with their own
  `auth_check.php` include keeps the permission logic simple and avoids
  accidentally leaking admin-only actions into the technician UI.

---

## Core Features (CRUD)

### 1. Hauler / Company management
- Add / edit / deactivate a hauler (company name + region).
- View all trucks under a hauler.

### 2. Truck management (per hauler)
- Add a truck to a hauler with: Body/ME No., Plate Number, Tractor Model, Location.
- Edit / remove a truck (soft delete preferred so install history isn't lost).
- From a truck's detail page, mark **what is installed**:
  - **MDVR** → first choose a radio button: **New MDVR** or **Old MDVR**.
    - *New MDVR* (we install the unit): full form — Device Serial, SIM
      ICCID (+ optionally SIM Number), install date, technician,
      integrated/visible flags, remarks.
    - *Old MDVR* (another company already installed the MDVR hardware — we
      only integrate our server and install a new SIM card): same full
      form as New — Device Serial (of the existing unit), new SIM
      ICCID/Number, install date, technician, integrated/visible flags,
      remarks. We still capture every field because we need the existing
      unit's details to integrate it into our system.
  - **OMNITraq** → opens a form for OMNITraq #, IMEI, SIM ICCID, install date,
    technician, remarks.
  - **Door Sensor** → simple radio button: Installed / Not Installed
    (+ small note in the UI: "Detailed fields to be added later").
- Each install type shows its own status badge (Not Started / Installed / Verified)
  on the truck card/row, and MDVR additionally shows a small "New" or "Old" tag.

### 3. Technician management
- Add / remove technicians.
- Assign a technician to a specific truck's install (per install type — MDVR,
  Omnitraq, Door Sensor can each have a different technician).
- Reassign or unassign without deleting install history.
- Removing a technician should not delete past install records — keep the
  historical name or mark records as "technician removed" rather than cascading
  deletes.

### 4. Filtering & Search (top of dashboard)
Filters, combinable (AND logic), each backed by an indexed DB query:
- **Location** (multi-select, e.g. MABINI + future sites)
- **Hauler/Company**
- **Technician**
- **Install status**: Omnitraq installed / not installed
- **Install status**: MDVR installed / not installed
- **Install status**: Door Sensor installed / not installed
- **Overall status**: Completed / In Progress / Not Started
- Free-text search by Plate Number or Body/ME No.
- Filters should update the URL query string so a filtered view can be bookmarked/shared.

### 5. Dashboard / Home view
- Summary cards: total trucks, % Omnitraq complete, % MDVR complete,
  % Door Sensor complete, % fully completed — by location and overall.
- Main data table (paginated, ~25-50 rows/page) with columns: ME No., Plate #,
  Hauler, Location, Tractor Model, Omnitraq status, MDVR status, Door Sensor
  status, Technician(s), Last Updated.
- Row click → truck detail/edit panel (slide-over or modal, not full page
  reload, to keep it snappy).

---

## UI/UX Requirements

- Clean, dashboard-style layout: left sidebar for navigation (Dashboard, Trucks,
  Haulers, Technicians, Reports), top bar with global search + filters.
- Status shown as color-coded badges (e.g. gray = not started, yellow = in
  progress, green = installed/completed).
- Forms open in a modal/side panel, not a new page — keeps the flow fast.
- Mobile-responsive, since technicians in the field may check status on phones.
- Table should support column sort and CSV export.
- Loading states/skeletons instead of blank screens while data fetches.

---

## Data Migration Notes (from the existing Excel files)

- `Batangas_Tank_Truck_Database.xlsx` → seeds `haulers` and `trucks`.
- `INSTALLED_UNITS.xlsx` "Omnitraq" sheet → seeds `omnitraq_installs` (join on
  Plate Number / Body No. to match trucks).
- `INSTALLED_UNITS.xlsx` "Howen" sheet → seeds `mdvr_installs` (join on Plate
  Number / Body No.).
- Watch for data-type issues: ICCID/IMEI/SIM numbers are long numeric strings in
  Excel that can lose precision or leading digits — import and store them as
  **text**, not numbers.
- Some trucks in the master list have no plate number yet (still pending
  registration) — the schema should allow `plate_number` to be nullable at the
  truck level, but the app should warn if you try to assign an installation to a
  truck without a plate.

---

## Non-Functional Requirements

- Fast: server-side pagination/filtering, indexed columns, cached reference
  data, debounced search — no unnecessary full-table loads on the client.
- Data integrity: foreign keys with sensible ON DELETE behavior (restrict
  deleting a hauler/technician that has active trucks/assignments — deactivate
  instead).
- Basic audit trail: `created_at`/`updated_at` on all tables; nice-to-have is a
  simple activity log (who changed what, when).
- Environment-ready: should run locally with a seed script that imports the two
  existing Excel files into the database on first setup.

---

## Deliverables Expected From the Build

1. MSSQL schema script (`schema.sql`) matching the model above, with all
   indexes.
2. PHP seed script (or a one-time `import.php`) that reads the two provided
   Excel files (via a PHP library like PhpSpreadsheet) and inserts into MSSQL.
3. PHP endpoints (JSON responses) for CRUD on haulers, trucks, technicians,
   omnitraq_installs, mdvr_installs, door_sensor_installs, truck_assignments —
   split cleanly under `/admin/api/...` and `/tech/api/...` per the auth rules
   above.
4. `/admin/login.php` and `/tech/login.php` with the session/permission logic
   described in the Authentication section.
5. Plain HTML/CSS/vanilla-JS frontend implementing the dashboard, filters, and
   CRUD forms for the admin side, and a simpler assigned-trucks view + install
   forms for the technician side.
6. Suggested folder structure:
   ```
   /public
     /admin
       login.php
       dashboard.php
       trucks.php
       haulers.php
       technicians.php
       /api
         trucks.php
         haulers.php
         technicians.php
         omnitraq.php
         mdvr.php
         door_sensor.php
     /tech
       login.php
       my_trucks.php
       /api
         update_install.php
     /assets
       /css
       /js
   /includes
     db.php          (PDO/MSSQL connection)
     auth_check.php   (session/role guards)
     functions.php
   /sql
     schema.sql
     seed_import.php
   ```
7. Basic README explaining how to set up the MSSQL database, configure the
   PHP `sqlsrv` driver, run the seed import, and create the first admin user.
