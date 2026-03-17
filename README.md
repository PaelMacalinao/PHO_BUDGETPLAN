# PHO Budgeting System — 2026 Consolidated Budget Proposal

A secure, responsive web form for **Staff** users to input data for the **2026 Consolidated Budget Proposal V3**.

## Tech Stack

| Layer    | Technology                         |
|----------|------------------------------------|
| Backend  | PHP 8+ (raw, no framework)         |
| Database | MySQL 8 via PDO prepared statements|
| Frontend | Bootstrap 5.3, vanilla JavaScript  |

## Project Structure

```
PHO_BUDGETPLAN/
├── config.php                     # DB connection, helpers, BASE_URL
├── database.sql                   # CREATE TABLE + seed data
├── index.php                      # Dashboard & proposals list
├── admin_dashboard.php            # 3-layer budget overview
├── create.php                     # 4-step proposal wizard
├── edit.php                       # Edit proposal
├── view.php                       # View proposal (read-only)
├── delete_proposal.php            # AJAX delete handler
├── reset.php                      # Factory reset
├── includes/
│   ├── header.php                 # Shared HTML head, sidebar, top bar
│   └── footer.php                 # JS libs, sidebar controller
├── master/
│   ├── account_codes.php          # CRUD for account codes
│   ├── programs.php               # CRUD for programs (PPA)
│   ├── units.php                  # CRUD for units
│   ├── fund_sources.php           # CRUD for fund sources
│   └── indicators.php             # CRUD for indicators
└── README.md
```

## Quick Start

1. **Create the database** — import `database.sql` into MySQL:
   ```sql
   mysql -u root -p < database.sql
   ```
2. **Configure credentials** — edit `config.php` if your MySQL user/password differs.
3. **Run via XAMPP** — open `http://localhost/PHO_BUDGETPLAN/` in a browser.

## Security

- CSRF token generated per-session and validated on submit.
- All database writes use **PDO prepared statements** (no string interpolation in SQL).
- User input is sanitized with `htmlspecialchars()` on output and server-side whitelist validation on dropdowns.
- Error details are logged server-side; the user sees only a generic message.