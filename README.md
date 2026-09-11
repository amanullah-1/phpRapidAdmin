# phpRapidAdmin

**Lightning-fast, single-file MySQL database management for PHP.**

> **Free & Open Source** — phpRapidAdmin is 100% free to use, modify, and distribute under the [MIT License](LICENSE). No hidden costs, no premium tiers, no paywalls.

phpRapidAdmin is a modern, zero-dependency PHP database admin tool. Drop one file on your server and get a full-featured MySQL/MariaDB management dashboard — no frameworks, no npm, no Composer, no CDN assets.

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

## Why phpRapidAdmin?

phpRapidAdmin is inspired by **phpMyAdmin** (the gold standard for MySQL management) and **phpMiniAdmin** (the simplicity of a single-file tool). It takes the best of both and adds modern improvements.

| Feature | **phpRapidAdmin** | **phpMyAdmin** | **phpMiniAdmin** |
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

> **The Bottom Line:** phpRapidAdmin combines the simplicity of phpMiniAdmin with the power of phpMyAdmin — all in a single, modern, mobile-friendly PHP file with PDO support and dark mode. Best of both worlds.

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

---

## Configuration

Open `phpRapidAdmin.php` and edit the configuration variables at the top of the file:

```php
// Set a password to protect access (leave empty for local-only usage)
$RAPID_PASSWORD = '';

// Pre-configure database servers for quick access
$RAPID_SERVERS = [
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
$RAPID_ROWS_PER_PAGE = 50;

// Browser tab title
$RAPID_TITLE = 'phpRapidAdmin';

// Optional: directory for server-side SQL dumps (empty = browser download)
$RAPID_DUMP_DIR = '';
```

### Password Protection

- **Local access** (127.0.0.1 / ::1): No password required by default
- **Remote access**: Requires `$RAPID_PASSWORD` to be set; otherwise shows a "Setup required" page
- Credentials are stored in a cookie for 30 days

### Multiple Servers

Define servers in `$RAPID_SERVERS` to quickly switch between databases on different hosts from the UI dropdown.

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
├── README.md               # This file
├── CONTRIBUTING.md         # Contribution guidelines
├── LICENSE.md              # MIT License
├── index.html              # Landing page
└── phpRapidAdmin.php       # The main application (single file)
```

---

## Free & Open Source

phpRapidAdmin is **100% free** and **open source**. There are:

- **No premium tiers**
- **No hidden fees**
- **No paywalls**
- **No feature restrictions**

You are free to:
- Use it for personal or commercial projects
- Modify the source code
- Distribute copies
- Use it in client work

Licensed under the [MIT License](LICENSE.md). If you find it useful, a star on GitHub or a coffee goes a long way!

---

## Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on reporting bugs, suggesting features, and submitting code.

---

## Buy Me a Coffee

If phpRapidAdmin saved you time or helped your project, consider buying me a coffee! Your support helps keep this project maintained and evolving.

<a href="https://buymeacoffee.com/amanullah" target="_blank">
  <img src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png" alt="Buy Me A Coffee" width="200">
</a>

---

## License

This project is licensed under the **MIT License**. See [LICENSE.md](LICENSE.md) for details.

---

## Acknowledgments

- Inspired by [phpMyAdmin](https://www.phpmyadmin.net) and [phpMiniAdmin](http://phpminiadmin.sourceforge.net)
- Built with PHP, vanilla CSS, and vanilla JavaScript
- No frameworks were harmed in the making of this tool

---

<p align="center">
  Made with ❤️ by <a href="https://github.com/amanullah-1">Aman Ullah</a>
</p>
