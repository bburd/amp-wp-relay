# Changelog

## [1.7.0] - 2025-05-27

### 🔒 Security & Configuration
- Moved sensitive files (`.env`, `relay.json`, `cache.json`, and log) to `/opt/amp-status`.
- Added ownership and permission setup steps for `/opt/amp-status`.
- Best practice instructions to verify protected paths.

### ⚙️ Relay Script (PHP)
- Added file-based caching for safe concurrent access.
- Improved relay key validation using `hash_equals()`.
- Enhanced logging: login results, fallback checks, IP source.
- Respects `logging: false` flag to avoid disk writes.

### 🧩 WordPress Plugin
- New UI customization options:
  - Font family
  - Text color
  - Background color with alpha
  - Card border radius
- Shortcode aliasing:
  - `[amp_status alias="myalias"]`
- Admin panel now includes manual refresh button.
- Relay key and URL are now editable via Settings > AMP Server Status.

### 📘 Documentation
- Updated install guide:
  - Added missing relay script copy step (`/var/www/html`)
  - NGINX configuration notes
  - Permissions clarification
- Updated README with:
  - Security and logging updates
  - File-based caching description

---

For full installation and usage instructions, see `INSTALL.md`.