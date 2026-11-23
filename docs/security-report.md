# Security Report

## Evidence standard

This report distinguishes:

- **Implemented:** confirmed from source or live HTTP evidence
- **Partially implemented:** present but not fully verifiable
- **Unverified:** cannot be confirmed from source alone
- **Not implemented:** no source evidence found

## HTTPS and SSL

**Implemented:**

- `.htaccess` redirects HTTP to HTTPS except local `localhost` and `127.0.0.1`.
- Live HTTPS request returned HTTP 200.
- Live HSTS header was present with `max-age=31536000`.
- Live server headers included security headers.

Exact certificate renewal and hosting configuration require hosting-console confirmation.

## Administration security

**Implemented:**

- Custom staff capabilities control dashboard, order, payment, catalogue and settings actions.
- Admin-post handlers check capabilities and WordPress nonces.
- Customers are redirected from `wp-admin` unless they have staff capabilities.
- Customer toolbar is hidden.
- Staff password changes enforce at least 12 characters with upper/lowercase, digit and symbol when the plugin detects staff roles.
- Customer passwords require at least 10 characters with letters and numbers on account changes.

**Unverified:** Password policies for accounts updated outside the custom plugin and live account/MFA configuration.

## Login protection

**Implemented:**

- Custom plugin login limiter allows five failed attempts before a 15-minute lock unless Wordfence is active.
- Login limiter switches off if Wordfence is active to avoid overlap.
- Login errors are generic.
- XML-RPC is disabled by PHP filter and Apache denial.
- Author scans are redirected and author links are hidden.
- Public REST users endpoints are removed for unauthorized callers.

**Unverified:** live Wordfence brute-force and firewall configuration.

## Two-factor authentication

**Unverified.** No source setting or live dashboard state confirms 2FA. Wordfence Login Security modules exist in plugin source, but activation and MFA configuration cannot be established from source alone.

## Wordfence

**Partially implemented:**

- Wordfence plugin source is present.
- The custom plugin detects Wordfence as active when `wordfence` or `WORDFENCE_VERSION` is available.
- The custom login limiter does not overlap an active Wordfence installation.

**Unverified:** installation activation state, firewall mode, scan history, malware scan outcome and alert configuration.

## File permissions

**Unverified.** File ownership and permission values require server/Windows administration checks. Apache rules deny directory listing and sensitive files.

## Sensitive file protection

**Implemented in source:**

- `.htaccess` denies direct access to `wp-config.php`, SQL, backup, ZIP and other sensitive files.
- XML-RPC is denied.
- Private upload directories contain protection files.
- Private order/account pages disable caching.
- `.gitignore` excludes `wp-config.php`, environment files, SQL and backup files.

## Backup

**Partially implemented:** UpdraftPlus source exists under `wp-content/plugins/updraftplus/`, but backup destination, schedule and restore verification are not verifiable from source alone.

No SQL dump or backup archive was found in the inspected project directory.

## Database protection

**Implemented in source:**

- Database operations use `$wpdb->prepare` and/or placeholders.
- Custom table names are fixed through an allowlist.
- Order and payment changes use transactions.
- Separate status/payment audit tables.
- Duplicate order submission keys and transaction references are checked.
- Real database credentials are not documented here.

**Unverified:** DB server network restrictions, least-privilege account, encryption at rest and live backup encryption.

## Security headers

Live and source implementations include or generate:

- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `Referrer-Policy: strict-origin-when-cross-origin`
- `Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()`
- HSTS on HTTPS

## Upload security

**Implemented:**

- Custom upload directories are protected.
- Private evidence/reference files are served only through an authorized admin-post action.
- Public media handlers do not use unvalidated paths.

## Outstanding verification items

- Run an authorized Wordfence scan and preserve only approved summary output.
- Confirm 2FA for all staff accounts.
- Confirm server file permissions and ownership.
- Confirm backup schedule, retention and restore tests.
- Confirm hosting account security and database least privilege.
- Confirm live email settings and notification delivery.

