# PhpRapidAdmin

**Lightning-fast, single-file MySQL database management for PHP.**

> **Free & Open Source** — PhpRapidAdmin is 100% free to use, modify, and distribute under the [MIT License](LICENSE). No hidden costs, no premium tiers, no paywalls.

PhpRapidAdmin is a modern, zero-dependency PHP database admin tool. Drop one file on your server and get a full-featured MySQL/MariaDB management dashboard — no frameworks, no npm, no Composer, no CDN assets.

![PHP Version](https://img.shields.io/badge/PHP-7.4%2B-blue)
![License](https://img.shields.io/badge/License-MIT-green)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)
![Version](https://img.shields.io/badge/Version-1.0-purple)

---

## Features

- **Single File** — The entire app is one `phpRapidAdmin.php` file (~2000 lines)
- **Zero Dependencies** — No Composer, no npm, no external frameworks
- **Dual Driver** — PDO (preferred) with mysqli fallback
- **Full CRUD** — Browse, insert, edit, and delete rows with auto-generated forms
- **SQL Editor** — Multi-statement execution, query templates, localStorage history
- **Import/Export** — SQL and CSV formats with optional gzip compression
- **Table Management** — Structure view, add/drop columns, rename, optimize, repair, truncate
- **Bulk Operations** — Select multiple tables for batch optimize, truncate, or drop
- **Server Monitoring** — Process list, server variables, table status
- **Dark Mode** — Auto-detects system preference with manual toggle
- **Mobile Responsive** — Collapsible sidebar, works on phones and tablets
- **Security** — Password gate, CSRF tokens, XSS prevention, httponly sessions
- **Caching** — Multi-level session cache with configurable TTL

---

## Why PhpRapidAdmin?

PhpRapidAdmin is inspired by **phpMyAdmin** (the gold standard for MySQL management) and **phpMiniAdmin** (the simplicity of a single-file tool). It takes the best of both and adds modern improvements.

| Feature | **PhpRapidAdmin** | **phpMyAdmin** | **phpMiniAdmin** |
|---|---|---|---|
| **Setup Time** | < 5 seconds | 5–15 minutes | < 5 seconds |
| **File Count** | 1 file | Hundreds of files | 1 file |
| **PDO Support** | Yes (preferred) | No | No |
| **CSRF on Every POST** | Yes | Yes | Partial |
| **Dark Mode** | Auto + Toggle | Recent versions | No |
| **Mobile Responsive** | Yes | Partial | No |
| **Row Edit Forms** | Yes | Yes | No |
| **Table Structure** | Full (add/drop/rename) | Full | No |
| **Bulk Operations** | Yes | Yes | No |
| **Server Monitoring** | Yes | Yes | No |
| **CSV + Gzip Export** | Yes | No | No |
| **Memory Usage** | ~2 MB | ~8–15 MB | ~2 MB |

> **The Bottom Line:** PhpRapidAdmin combines the simplicity of phpMiniAdmin with the power of phpMyAdmin — all in a single, modern, mobile-friendly PHP file with PDO support and dark mode. Best of both worlds.

For a detailed comparison, see [comparison.md](comparison.md).

## Requirements

- PHP 7.4 or higher
- `pdo_mysql` extension (preferred) or `mysqli` extension
- MySQL 5.7+ or MariaDB 10.2+
- A web server (Apache, Nginx, Laragon, XAMPP, etc.)

---

## Installation

### Option 1: Clone from GitHub

```bash
git clone https://github.com/amanullah-1/phpRapidAdmin.git
cp phpRapidAdmin/phpRapidAdmin.php /var/www/html/
```

### Option 2: Direct Download

1. Download [`phpRapidAdmin.php`](phpRapidAdmin.php) from this repository
2. Place it in your web server's document root (e.g., `/var/www/html/` or `C:\laragon\www\`)
3. Open it in your browser: `http://localhost/phpRapidAdmin.php`

### Option 3: composer (if you prefer)

```bash
composer require amanullah-1/phprapidadmin
```

---

## Configuration

Open `phpRapidAdmin.php` and edit the configuration variables at the top of the file:

```php
// Set a password to protect access (leave empty for local-only usage)
$NANO_PASSWORD = '';

// Pre-configure database servers for quick access
$NANO_SERVERS = [
    'localhost' => [
        'host'=>'127.0.0.1', 'port'=>3306,
        'user'=>'root', 'pass'=>'',
    ],
    'production' => [
        'host'=>'db.example.com', 'port'=>3306,
        'user'=>'admin', 'pass'=>'secret',
    ],
];

// Rows per page for browsing (default: 50)
$NANO_ROWS_PER_PAGE = 50;

// Browser tab title
$NANO_TITLE = 'PhpRapidAdmin';

// Optional: directory for server-side SQL dumps (empty = browser download)
$NANO_DUMP_DIR = '';
```

### Password Protection

- **Local access** (127.0.0.1 / ::1): No password required by default
- **Remote access**: Requires `$NANO_PASSWORD` to be set; otherwise shows a "Setup required" page
- Credentials are stored in a cookie for 30 days

### Multiple Servers

Define servers in `$NANO_SERVERS` to quickly switch between databases on different hosts from the UI dropdown.

---

## Usage

### Connecting

1. Open `phpRapidAdmin.php` in your browser
2. If no servers are pre-configured, you'll see a connection form — enter your MySQL credentials
3. Select a database from the dropdown

### Browsing Data

- Click a table name in the sidebar to browse its rows
- Use pagination controls at the bottom to navigate
- Click column headers to sort

### Running SQL

1. Click the **SQL** tab or press the SQL bar
2. Write your query (multi-statement supported with `;`)
3. Click **Run** or use keyboard shortcut `Ctrl+Enter`
4. Previous queries are saved in localStorage history

### CRUD Operations

- **Insert**: Click the **Insert** button on any table to get a form based on column types
- **Edit**: Click the edit icon on any row to modify it
- **Delete**: Click the delete icon with confirmation

### Import/Export

- **Export**: Go to a table → click Export → choose SQL or CSV format
- **Import**: Click Import → upload a `.sql` or `.sql.gz` file, or paste SQL directly

### Table Management

- View table structure, indexes, and triggers
- Add or drop columns
- Rename tables
- Bulk select tables for optimize, truncate, or drop

---

## Project Structure

```
phpRapidAdmin/
├── index.html              # Landing page / project website
├── README.md               # This file
└── phpRapidAdmin.php       # The main application (single file)
```

---

## Free & Open Source

PhpRapidAdmin is **100% free** and **open source**. There are:

- **No premium tiers**
- **No hidden fees**
- **No paywalls**
- **No feature restrictions**

You are free to:
- Use it for personal or commercial projects
- Modify the source code
- Distribute copies
- Use it in client work

Licensed under the [MIT License](LICENSE). If you find it useful, a star on GitHub or a coffee goes a long way!

---

## Contributing

Contributions are welcome! Here's how you can help:

### Reporting Bugs

1. Check [existing issues](https://github.com/amanullah-1/phpRapidAdmin/issues) first
2. Open a new issue with:
   - PHP version
   - MySQL/MariaDB version
   - Steps to reproduce
   - Expected vs actual behavior

### Suggesting Features

Open an issue with the **feature request** label. Describe:
- What you'd like to see
- Why it would be useful
- How it should work

### Submitting Code

1. **Fork** the repository
2. **Create a branch** for your feature:
   ```bash
   git checkout -b feature/amazing-feature
   ```
3. **Make your changes** in `phpRapidAdmin.php`
4. **Test** on multiple PHP versions if possible (7.4, 8.0, 8.1, 8.2, 8.3)
5. **Commit** with a clear message:
   ```bash
   git commit -m "Add: amazing feature description"
   ```
6. **Push** to your fork:
   ```bash
   git push origin feature/amazing-feature
   ```
7. Open a **Pull Request** with:
   - Description of changes
   - Screenshots (for UI changes)
   - PHP/MySQL versions tested

### Development Guidelines

- Keep it **single-file** — no external dependencies
- Follow existing code style (inline CSS/JS, PHP conventions)
- Use the `h()` helper for XSS prevention on all output
- Use `csrf_ok()` to verify CSRF tokens on POST operations
- Use `db_*` abstraction functions for database operations
- Test on both PDO and mysqli drivers

### Code Style

- PHP: Follow PSR-12 where practical
- CSS: Use CSS variables from `:root`
- JS: Vanilla JavaScript only (no frameworks)
- Keep the file self-contained

---

## Buy Me a Coffee

If PhpRapidAdmin saved you time or helped your project, consider buying me a coffee! Your support helps keep this project maintained and evolving.

<a href="https://buymeacoffee.com/amanullah" target="_blank">
  <img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me A Coffee" width="200">
</a>

---

## License

This project is licensed under the **MIT License**.

```
MIT License

Copyright (c) 2024 Aman Ullah

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
```

---

## Acknowledgments

- Inspired by [PHP Mini MySQL Admin](http://phpminiadmin.sourceforge.net) by Oleg Savchuk
- Built with PHP, vanilla CSS, and vanilla JavaScript
- No frameworks were harmed in the making of this tool

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/amanullah-1">Aman Ullah</a>
</p>
