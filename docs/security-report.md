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

## Security Maintenance and Recommendations

The following actions are recommended to maintain the security of Tina's Cake Haven.

### 1. Administrator account protection

- Use individual accounts for authorized staff instead of sharing one administrator account.
- Use strong, unique passwords.
- Enable two-factor authentication for all staff accounts and verify that it works.
- Remove or disable accounts belonging to former staff.
- Assign only the capabilities required for each staff member's duties.

### 2. WordPress and plugin maintenance

- Install WordPress security updates promptly after checking compatibility.
- Keep the custom theme and plugin under version control.
- Update third-party plugins from trusted sources.
- Remove unused plugins and themes only after confirming that they are not required.
- Back up the website before significant updates.

### 3. Backup and recovery

- Configure regular website and database backups.
- Store backups in a secure location separate from the hosting account.
- Restrict access to backup files.
- Define an appropriate backup retention period.
- Perform periodic restoration tests in a safe environment.

A backup should not be considered reliable until restoration has been successfully tested.

### 4. Security monitoring

- Review WordPress and hosting security alerts.
- Run authorized malware and vulnerability scans.
- Investigate unexpected administrator accounts, file changes and login activity.
- Monitor PHP, web-server and WordPress error logs.
- Document security incidents and corrective actions.

Wordfence scan results and firewall status should only be reported as verified after checking the live dashboard.

### 5. Customer data protection

- Restrict access to customer contact details, order records and payment evidence.
- Do not share customer information through public GitHub repositories.
- Avoid downloading live customer data to unsecured computers.
- Use test records when demonstrating or testing the application.
- Remove temporary files containing sensitive information when they are no longer required.

### 6. Hosting and database security

- Use strong hosting-account credentials and enable available account-level MFA.
- Use a database account with only the permissions required by the application.
- Keep database credentials outside public source-code repositories.
- Maintain valid HTTPS configuration and monitor certificate expiry.
- Confirm that sensitive files and backup archives cannot be downloaded publicly.

### 7. Security review schedule

| Frequency | Recommended activity |
|---|---|
| Weekly | Review important alerts and unexpected login activity |
| Monthly | Check WordPress, theme and plugin updates |
| Monthly | Review administrator and staff accounts |
| Monthly | Confirm backup completion |
| Quarterly | Perform an authorized security scan |
| Quarterly | Test backup restoration |
| After major updates | Recheck ordering, account and payment-related security controls |

## Security Assessment Conclusion

The project includes several security controls in its WordPress application, custom plugin and hosting configuration. However, some operational protections still require live verification, particularly two-factor authentication, Wordfence scanning, backup restoration and server file permissions.

Security should therefore be treated as an ongoing maintenance responsibility rather than a one-time installation task.
