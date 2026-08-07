# Tank Truck Installation Tracker

A web application for tracking GPS/MDVR/Door Sensor installation progress across a fleet of tank trucks.

## Prerequisites

- **PHP 8.x** with `pdo_sqlsrv` extension enabled
- **Microsoft SQL Server** (any edition, including Express)
- **Laragon** (or Apache/Nginx + PHP)
- **Composer** (for the Excel import library)

## Setup Instructions

### 1. Configure the Database

1. Open SQL Server Management Studio (SSMS) or `sqlcmd`.
2. Create a new database:
   ```sql
   CREATE DATABASE tank_truck_tracking;
   ```
3. Run the schema script:
   ```bash
   sqlcmd -S localhost -d tank_truck_tracking -i sql/schema.sql
   ```

### 2. Configure PHP Connection

Edit `includes/config.php` with your MSSQL credentials:
```php
define('DB_SERVER', 'localhost');
define('DB_NAME',   'tank_truck_tracking');
define('DB_USER',   'sa');
define('DB_PASS',   'your_password');
```

### 3. Install Composer Dependencies

```bash
cd "c:\laragon\www\Installation Tracker"
composer install
```

This installs PhpSpreadsheet for the Excel import.

### 4. Run the Seed Import

```bash
php sql/seed_import.php
```

This will:
- Create the default admin user (`admin` / `admin123`)
- Import haulers and trucks from `Batangas Tank Truck Database.xlsx`
- Import Omnitraq/MDVR install records from `INSTALLED UNITS.xlsx`

### 5. Access the Application

- **Admin Panel**: `http://localhost/Installation%20Tracker/admin/login.php`
  - Username: `admin`
  - Password: `admin123`
  - ⚠️ **Change this password after first login!**

- **Technician Portal**: `http://localhost/Installation%20Tracker/tech/login.php`
  - Select your name from the dropdown and enter the password set by admin

## Project Structure

```
Installation Tracker/
├── admin/                    # Admin pages & API
│   ├── api/                  # JSON API endpoints
│   │   ├── assignments.php
│   │   ├── dashboard.php
│   │   ├── door_sensor.php
│   │   ├── export.php
│   │   ├── haulers.php
│   │   ├── mdvr.php
│   │   ├── omnitraq.php
│   │   ├── technicians.php
│   │   └── trucks.php
│   ├── dashboard.php
│   ├── haulers.php
│   ├── login.php
│   ├── technicians.php
│   └── trucks.php
├── tech/                     # Technician pages & API
│   ├── api/
│   │   ├── my_trucks.php
│   │   └── update_install.php
│   ├── login.php
│   └── my_trucks.php
├── assets/
│   ├── css/style.css         # Design system
│   └── js/
│       ├── app.js            # Core utilities
│       ├── dashboard.js
│       ├── filters.js
│       ├── haulers.js
│       ├── tech_portal.js
│       ├── technicians.js
│       └── trucks.js
├── includes/
│   ├── auth_check.php        # Session guards
│   ├── config.php            # Configuration
│   ├── db.php                # Database connection
│   └── functions.php         # Shared utilities
├── sql/
│   ├── schema.sql            # Database schema
│   └── seed_import.php       # Excel import script
├── composer.json
├── index.php                 # Root redirect
└── README.md
```

## Features

- **Dashboard**: Summary cards with completion percentages, filterable data table
- **Inventory Management**: Track hardware stock (Omnitraq, MDVR, Door Sensors). Deducts automatically based on technician installations.
- **Multi-Location Support**: Segregates inventory and team assignments by location (HQ + sites).
- **Stock Warnings**: Low-stock alerts (under 5) across both Admin panels and the Technician app.
- **Bulk Operations**: Bulk-add inventory and rapidly deploy standard hardware across all locations simultaneously.
- **Truck Management**: Full CRUD, install tracking (Omnitraq, MDVR, Door Sensor)
- **Hauler Management**: Add/edit/deactivate companies
- **Technician Management**: Admin sets passwords, assign to truck installs
- **Technician Portal**: View assigned trucks, update install status, view low-stock warnings
- **Filters**: Location, hauler, technician, install status (URL-bookmarkable)
- **CSV Export**: Export filtered data
- **Responsive**: Works on mobile and desktop

## Authentication

| Role          | Login URL                      | Access                                                       |
|---------------|--------------------------------|--------------------------------------------------------------|
| Admin         | `/admin/login.php`             | Full CRUD, all trucks, HQ inventory, reports                 |
| Team Leader   | `/admin/login.php`             | Site-specific CRUD, local site inventory, local reports      |
| Technician    | `/tech/login.php`              | Assigned trucks only, update install status, deduct inventory|

Technician and Team Leader passwords are set by the admin. Sessions timeout after 8 hours.
