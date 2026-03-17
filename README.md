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
PHO_BUDGETING/
├── config/
│   └── db.php                  # PDO connection (singleton)
├── database/
│   └── schema.sql              # CREATE TABLE script
├── assets/
│   ├── css/style.css           # Custom theme
│   └── js/budget-form.js       # Auto-calc & validation
├── index.php                   # Main data-entry form
├── process.php                 # Server-side validation & INSERT
└── README.md
```

## Quick Start

1. **Create the database** — import `database/schema.sql` into MySQL:
   ```sql
   mysql -u root -p < database/schema.sql
   ```
2. **Configure credentials** — edit `config/db.php` if your MySQL user/password differs from `root` / *(empty)*.
3. **Run via XAMPP** — open `http://localhost/PHO/PHO_BUDGETING/` in a browser.

## Security

- CSRF token generated per-session and validated on submit.
- All database writes use **PDO prepared statements** (no string interpolation in SQL).
- User input is sanitized with `htmlspecialchars()` on output and server-side whitelist validation on dropdowns.
- Error details are logged server-side; the user sees only a generic message.