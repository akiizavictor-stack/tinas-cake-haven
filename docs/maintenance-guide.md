# Maintenance Guide

## WordPress updates

Before updating WordPress core, themes or plugins:

1. Backup files and database.
2. Review plugin compatibility and PHP version.
3. Update in a staging environment if available.
4. Update WordPress core first or follow the hosting provider's approved sequence.
5. Test public pages and the custom Cake Orders dashboard.
6. Keep a rollback copy and restore point.

Do not update or activate plugins casually because custom functionality and live content depend on existing configuration.

## Theme and plugin maintenance

- Maintain `wp-content/themes/tinas-cake-haven/` as presentation code.
- Maintain `wp-content/plugins/tinas-cake-haven-core/` as business logic.
- Review plugin minimum requirements after WordPress/PHP upgrades.
- Re-run plugin database upgrades only when the documented plugin version changes.
- Preserve custom post types, taxonomies and table names.
- Test product, order and payment workflows after updates.

Third-party plugin updates should include Wordfence, WordPress SEO, WP Super Cache, UpdraftPlus and Header/Footer Elementor.

## Database backups

- Schedule regular full database backups.
- Store copies in an approved external location.
- Encrypt or restrict backup access.
- Include WordPress options, users and custom `tch_*` tables.
- Never store credentials or customer data in the Git repository.
- Test restoration periodically in an isolated environment.

## Security monitoring

- Review Wordfence firewall and scan status regularly.
- Confirm 2FA configuration for staff accounts.
- Remove inactive or unknown administrator accounts.
- Review failed-login and audit activity.
- Keep staff strong-password requirements.
- Review private upload directories.
- Apply PHP, WordPress and plugin security updates promptly.
- Confirm that live HTTPS and security headers remain active.

## Performance monitoring

- Keep WP Super Cache configured conservatively.
- Keep private order, account and tracking pages out of public caches.
- Monitor LiteSpeed/Apache logs and PHP resources.
- Compress and resize new images where appropriate.
- Preserve WebP images and browser caching.
- Monitor database growth for orders, payment logs and status history.
- Test homepage and product-page response time.

## Content updates

- Edit catalogue products under **Cake Products**.
- Update sizes/flavours under **Cake Orders → Sizes & flavours**.
- Update business details under **Cake Orders → Business settings**.
- Update pages and navigation through the WordPress editor.
- Update homepage slides under **Hero Slider**.
- Verify links and mobile layout after content changes.

## Troubleshooting

| Symptom | First checks |
|---|---|
| Blank page | PHP/Apache error logs, plugin conflicts, PHP version |
| Database error | Database status, credentials, custom table existence |
| CSS missing | Theme activation, file paths and cache |
| 404 permalinks | `.htaccess`, Apache rewrite module and permalink settings |
| Payment queue not updating | Staff capability, database transaction, server error log |
| Emails not received | WordPress mail configuration, spam filters and notification settings |
| Private file unavailable | Upload permission and `TCH_Uploads` authorization |
| Cache showing old content | Purge approved WordPress/page-cache entries only |

## Recommended maintenance schedule

| Interval | Activities |
|---|---|
| Daily | Review new orders/payments and backup job status |
| Weekly | Review Wordfence alerts, failed logins and server errors |
| Monthly | Apply approved updates, review plugins, clean obsolete content and test performance |
| Quarterly | Test database restore, staff accounts, 2FA and security settings |
| Annually | Review hosting, SSL renewal, PHP support and database capacity |
