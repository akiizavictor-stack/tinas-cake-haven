# Deployment Guide – Tina's Cake Haven

## 1. Live Website

**Production URL:** https://tinascakehaven.noscamcoin.org/

Tina's Cake Haven is deployed as a WordPress website and is publicly accessible through the production domain.

## 2. Hosting Environment

The following information has been observed from the live website:

| Item | Verified information |
|---|---|
| Web server | LiteSpeed |
| PHP version | 8.2.36 |
| HTTPS | Enabled |
| HTTP response | 200 OK |
| HSTS | Enabled |
| Website platform | WordPress |

The exact hosting provider, cPanel account details, document root and database hostname must be confirmed through the authorized hosting dashboard.

No passwords, database credentials or hosting account secrets should be stored in this document or the public GitHub repository.

## 3. DNS and Document Root Configuration

The production domain must resolve to the authorized hosting server.

The hosting document root must contain the WordPress installation files and correctly serve the website's homepage and permalink routes.

The project uses Apache/LiteSpeed-compatible `.htaccess` configuration for:

- HTTPS redirection.
- Directory listing protection.
- Sensitive file protection.
- XML-RPC restrictions.
- Browser caching and compression.
- WordPress permalink routing.

The local WAMP installation uses a project-specific rewrite context:

`RewriteBase /tinas-cake-haven/`

This local configuration must not automatically be assumed to apply to the production domain. The live rewrite configuration must correspond to the actual hosting document root.

## 4. SSL Certificate and HTTPS

The production website uses HTTPS.

Deployment and maintenance should ensure that:

- The SSL certificate is valid for the exact production hostname.
- HTTP requests redirect to HTTPS.
- HTTPS pages do not load insecure HTTP resources.
- Security headers remain correctly configured.
- Certificate renewal is monitored through the hosting provider.

Where a reverse proxy is used, HTTPS detection must be configured correctly without trusting arbitrary forwarded headers.

## 5. Deployment Prerequisites

An authorized maintainer should have:

- Access to the hosting control panel.
- Access to the production database through an authorized method.
- A verified copy of the project source.
- A complete and recoverable backup.
- The required WordPress, PHP and database compatibility.
- Access to the domain DNS configuration where changes are necessary.

The project requires WordPress 6.5 or newer and PHP 8.0 or newer according to its custom plugin requirements.

## 6. Deployment Procedure

The following procedure describes the recommended process for deploying a project update.

1. Create a backup of the existing WordPress files and database.
2. Confirm the source-code version and review the changes being deployed.
3. Confirm that the deployment package excludes development credentials, temporary files and unnecessary backups.
4. Upload the authorized WordPress project files to the correct production document root.
5. Preserve the custom theme:

   `wp-content/themes/tinas-cake-haven/`

6. Preserve the custom plugin:

   `wp-content/plugins/tinas-cake-haven-core/`

7. Preserve existing media and uploads:

   `wp-content/uploads/`

8. Confirm that `wp-config.php` contains the correct production database configuration without exposing its contents.
9. Confirm that WordPress `home` and `siteurl` settings use the production HTTPS domain.
10. Confirm that the `.htaccess` rules are appropriate for the live hosting environment.
11. Clear only the relevant authorized caches.
12. Verify that WordPress core, theme and plugin files are functioning correctly.
13. Perform the post-deployment verification checks in Section 10.

Existing customer records, orders, payment records and business settings must be preserved.

Database migrations, URL replacement operations and destructive changes must only be performed after authorization and a verified backup.

## 7. Database Configuration

WordPress uses MySQL-compatible database configuration.

The following placeholders illustrate the required configuration values:

```text
DB_NAME=<authorized_database_name>
DB_USER=<authorized_database_user>
DB_PASSWORD=<authorized_database_password>
DB_HOST=<authorized_database_host>
```

The actual values must be obtained from the authorized hosting provider.

Security requirements:

- Never publish real database credentials.
- Never commit `wp-config.php` to the public repository.
- Use a dedicated database account.
- Grant only the permissions required by WordPress and the custom plugin.
- Restrict access to database administration tools.

The project uses standard WordPress database tables and additional custom tables for business operations.

## 8. Backup and Restoration

UpdraftPlus is present in the project environment. However, the actual backup schedule, destination and successful restoration status must be verified through the hosting dashboard.

Recommended backup procedure:

1. Back up the complete WordPress installation.
2. Export the production MySQL/MariaDB database.
3. Preserve uploaded media and private application files.
4. Record the WordPress, theme and plugin versions.
5. Store backups in a secure location separate from the public repository.
6. Restrict access to backup files because they may contain customer information.

### Restoration procedure

If restoration becomes necessary:

1. Identify the failure and select the appropriate verified backup.
2. Restore files and database in a controlled environment where possible.
3. Confirm database tables and relationships.
4. Verify the theme and custom plugin.
5. Check uploaded media and file permissions.
6. Verify customer accounts, orders and payment records.
7. Confirm the website works before returning it to normal operation.

A restoration procedure should be periodically tested. Do not claim a successful restoration until an actual test has been completed and documented.

## 9. Rollback Procedure

If a deployment causes serious errors:

1. Stop further deployment changes.
2. Record the observed error and affected functionality.
3. Notify the authorized website administrator.
4. Restore the previous verified application files if necessary.
5. Restore the matching database backup if database changes caused the problem.
6. Verify the homepage, products, accounts and order functionality.
7. Confirm HTTPS and security protections.
8. Document the incident and corrective action.

Never restore an older database over newer customer records without assessing the consequences.

## 10. Post-Deployment Verification

After deployment, perform the following checks:

### General website checks

- [ ] Open the production homepage.
- [ ] Confirm the website loads over HTTPS.
- [ ] Verify HTTP-to-HTTPS redirection.
- [ ] Check the main navigation and footer.
- [ ] Check the homepage hero slider.
- [ ] Verify responsive display on desktop and mobile.

### Product and ordering checks

- [ ] Open the product catalogue.
- [ ] Open product categories and individual products.
- [ ] Check the standard order form.
- [ ] Check the customized birthday-cake request form.
- [ ] Verify order pricing is calculated on the server.
- [ ] Confirm the delivery and collection information displays correctly.

### Customer account checks

- [ ] Verify customer registration and login pages.
- [ ] Check the customer account page.
- [ ] Check order history.
- [ ] Check guest order tracking.
- [ ] Confirm private customer pages are not publicly cached.

### Administrative checks

- [ ] Verify authorized staff access.
- [ ] Check order management.
- [ ] Check payment verification functionality.
- [ ] Confirm order status management.
- [ ] Check customer notification functionality.

### Security and performance checks

- [ ] Verify the SSL certificate.
- [ ] Review HTTP security headers.
- [ ] Confirm XML-RPC restrictions.
- [ ] Review Wordfence status.
- [ ] Confirm backup configuration.
- [ ] Review server error logs.
- [ ] Check caching and compression.
- [ ] Check website performance on mobile and desktop.

Testing must use authorized test accounts and controlled test data. Do not submit real payments or create unnecessary customer orders during routine verification.

## 11. Maintenance and Operational Notes

The website requires continued maintenance after deployment.

Recommended activities include:

- Monitoring SSL certificate validity.
- Updating WordPress, plugins and themes after compatibility checks.
- Maintaining secure database backups.
- Reviewing security alerts.
- Monitoring website availability.
- Testing contact and notification delivery.
- Reviewing customer order functionality.
- Monitoring disk usage and server resources.

## 12. Deployment Limitations and Outstanding Verification

The following operational details require confirmation before they can be reported as completed:

- Exact hosting provider and cPanel document root.
- Database hostname and production database account configuration.
- Successful scheduled backup execution.
- Successful database and file restoration test.
- Wordfence scan completion.
- WordPress cron execution.
- Email delivery reliability under production conditions.

These items should only be marked as verified after evidence has been collected.

---

**Project:** Tina's Cake Haven
**Course:** BIT 2103 – Internet Technologies & Advanced Web Development
**Institution:** Mountains of the Moon University
**Live website:** https://tinascakehaven.noscamcoin.org/