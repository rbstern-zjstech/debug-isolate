# Debug Isolate

**Debug Isolate** is a lightweight WordPress plugin that conditionally enables debug output based on the current user or IP address. It’s designed for safe, controlled debugging in production environments without exposing sensitive PHP warnings or errors to the public.

---

## Features

- Toggle WordPress debugging on/off via settings
- Restrict debug output to selected IP addresses
- Restrict debug output to selected users
- Admin UI for managing users and IPs
- Filters out invalid IPs and unselected users
- Adds an icon in the WordPress admin bar to indicate the tool is active
- Compatible with both IPv4 and IPv6

---

## Usage

1. Install and activate the plugin.
2. Navigate to **Settings → Debug Isolate**.
3. Use the toggle to enable or disable debug mode.
4. Add IP addresses or select WordPress users allowed to see debug output.
5. Save changes. Only listed IPs/users will receive PHP errors and warnings.

---

## Admin Bar Indicator

When debug mode is active for the current session, a red warning icon will appear in the admin bar, linking back to the plugin settings page.

---

## Validation

- IP addresses are validated with `FILTER_VALIDATE_IP`
- User dropdowns must have a valid selection to be saved
- Blank entries are automatically removed on save
- Invalid IPs are reported via admin notice after saving

---

## Behind the Scenes

This plugin adjusts `ini_set('display_errors', 1)` instead of redefining `WP_DEBUG`, ensuring compatibility even when `WP_DEBUG` is defined in `wp-config.php`.

---

## File Structure
debug-isolate/
├─ debug-isolate.php
├─ assets/
│ └─ admin.css
└─ README.md


---

## License

MIT — Free to use, modify, and redistribute.

---

## Author

Rich Stern
[ZJS Technology](https://zjstech.com)  
Custom WordPress development, performance optimization, and disaster recovery engineering.

