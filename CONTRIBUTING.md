# Contributing to phpRapidAdmin

Contributions are welcome! Here's how you can help:

## Reporting Bugs

1. Check [existing issues](https://github.com/amanullah-1/phpRapidAdmin/issues) first
2. Open a new issue with:
   - PHP version
   - MySQL/MariaDB version
   - Steps to reproduce
   - Expected vs actual behavior

## Suggesting Features

Open an issue with the **feature request** label. Describe:
- What you'd like to see
- Why it would be useful
- How it should work

## Submitting Code

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

## Development Guidelines

- Keep it **single-file** — no external dependencies
- Follow existing code style (inline CSS/JS, PHP conventions)
- Use the `h()` helper for XSS prevention on all output
- Use `csrf_ok()` to verify CSRF tokens on POST operations
- Use `db_*` abstraction functions for database operations
- Test on both PDO and mysqli drivers

## Code Style

- PHP: Follow PSR-12 where practical
- CSS: Use CSS variables from `:root`
- JS: Vanilla JavaScript only (no frameworks)
- Keep the file self-contained
