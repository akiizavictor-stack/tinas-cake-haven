# Deployment Guide

## Live domain

The correct public URL is:

[https://tinascakehaven.noscamcoin.org](https://tinascakehaven.noscamcoin.org)

## Hosting environment

Live response evidence:

- HTTP status: 200
- Server: LiteSpeed
- PHP: 8.2.36
- HTTPS: enabled
- HSTS: enabled
- Exact provider, control-panel account, web root and database host require hosting-dashboard confirmation.

## DNS and document root

The domain DNS must resolve to the authorized hosting server. The WordPress document root must contain the project files and serve WordPress index routing.

Apache/LiteSpeed-compatible `.htaccess` rules in the project:

- Force HTTPS for public requests.
- Deny directory listing.
- Protect sensitive files.
- Disable XML-RPC.
- Enable browser caching and compression.
- Route WordPress permalinks.

The local `RewriteBase /tinas-cake-haven/` rule is local-project evidence and must be verified before a live deployment to avoid assuming the live root is `/tinas-cake-haven/`. The live domain should use the authorized live document root.

## SSL/HTTPS

Maintain a valid certificate for the exact domain. HTTPS enforcement must account for reverse-proxy headers such as `X-Forwarded-Proto`. Do not disable security headers without an approved replacement.

## Deployment process

The deployment process should be performed by an authorized maintainer:

1. Create a full WordPress and database backup.
2. Confirm the source release and diff.
3. Place files in the live document root through the authorized hosting method.
4. Preserve `wp-content/themes/tinas-cake-haven/` and `wp-content/plugins/tinas-cake-haven-core/`.
5. Preserve `wp-content/uploads/` and private upload permissions.
6. Confirm database credentials and hostname in `wp-config.php` without displaying secrets.
7. Confirm WordPress `home` and `siteurl` options match the live HTTPS domain.
8. Clear only authorized caches/backups.
9. Verify core, theme and plugin versions.
10. Run the post-deployment checks below.

Do not use local URL search-and-replace or database migration tools unless explicitly authorized.

## Database configuration

Use placeholders and hosting-provided secret values:

```text
DB_NAME=<authorized_database_name>
DB_USER=<authorized_database_user>
DB_PASSWORD=<authorized_database_password>
DB_HOST=<authorized_database_host>
```

The database account should have only the permissions required by WordPress and the custom plugin.

## Backup and restoration

UpdraftPlus source is present, but schedule and destination are unverified.

Before deployment:

- Back up WordPress files.
- Back up MySQL/MariaDB.
- Preserve uploads and private upload evidence.
- Record plugin/theme/core versions.
- Keep database credentials outside the backup or repository.

After changes:

- Restore to a controlled staging environment first.
- Confirm custom tables and WordPress records.
- Verify private uploads and permissions.
- Document the restore test result.

## Post-deployment verification

- Open the live homepage.
- Check HTTP-to-HTTPS redirects.
- Check products and a product category.
- Check order, custom-cake, account and tracking page URLs.
- Check security headers.
- Review the browser console and server error log.
- Verify WordPress cron and scheduled backups.
- Confirm Wordfence and UpdraftPlus status.
- Confirm contact and order notification delivery.
- Do not submit real orders or payments for testing.

