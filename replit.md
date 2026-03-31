# Gestion Import/Export

A web-based import/export shipment management system for tracking arrivages (shipments), colis (packages), clients, and payments.

## Tech Stack

- **Backend:** PHP 8.2 (built-in development server)
- **Database:** MariaDB 10.11 (local, started via startup script)
- **Frontend:** Vanilla HTML/CSS/JS with Chart.js (CDN), Font Awesome, jsPDF

## Architecture

- **Single-server PHP app** served via `php -S 0.0.0.0:5000`
- **MariaDB** started as a background process in the same workflow, using `--skip-grant-tables` (no password auth needed)
- **No build system** — pure PHP with CDN-hosted JS libraries

## Key Files

- `start.sh` — Startup script that launches MariaDB then PHP server
- `includes/config.php` — Database connection config (host: 127.0.0.1, port: 3306)
- `database.sql` — Full schema with seed data
- `index.php` — Main dashboard
- `login.php` / `logout.php` — Authentication
- `setup.php` — Initial admin user creation
- `pages/` — Feature modules (arrivages, partage, clients, paiements, rapports, etc.)
- `api/` — PHP API endpoints: `save_arrivage.php` (AJAX), `repartitions.php` (AJAX), `colis_arrivage.php`, `clients_search.php`, `colis_info.php`, `proprietaires.php`

## Database

- **Name:** `import_export`
- **Host:** 127.0.0.1:3306
- **User:** root (no password, skip-grant-tables mode)
- **Data directory:** `/home/runner/mysql-data`
- Schema auto-loaded on first startup via `start.sh`

## Running the App

The workflow "Start application" runs `bash start.sh` which:
1. Starts MariaDB with `--skip-grant-tables`
2. Waits for the socket to be ready
3. Loads `database.sql` if the database doesn't exist yet
4. Starts `php -S 0.0.0.0:5000`

## First-time Setup

After starting, visit `/setup.php` to create the initial admin account.

## Deployment

- **Target:** VM (always running, needed for persistent MariaDB process)
- **Run command:** `bash /home/runner/workspace/start.sh`

## Database Schema (key tables)

- `arrivages` — One record per day of reception; `transitaire_id` is now nullable (multiple transitaires per arrivage)
- `colis` — Each package has its own `transitaire_id`; linked to an arrivage
- `repartitions` — New table: package-to-client assignments (replaces the role of `colis_proprietaires` for the partage module)
- `colis_proprietaires` — Legacy table kept for paiements/dettes/bilan backward compat

## Features

- Dashboard with charts (daily packages, revenue)
- **Arrivages** — Create one entry per day with multiple transitaires + colis in a single AJAX form
- **Partage des colis** — Assign existing colis from arrivages to clients (individual = 1 client; mixed = multiple clients with poids validation)
- Client & Transitaire management
- Payment processing with automatic status updates
- Reports: daily balance, debt tracking, CSV/PDF export
- Role-based access (admin / gestionnaire)
