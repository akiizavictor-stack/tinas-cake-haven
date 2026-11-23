# Local Installation Guide for Windows 10 and WAMP

## 1. Install WAMP

Install WAMP Server for the matching Windows architecture. Start WAMP and wait until the tray icon indicates that Apache and MySQL/MariaDB are running.

Required:

- PHP 8.0 or newer
- MySQL/MariaDB
- Apache
- WordPress 6.5 or newer

The plugin specifically declares PHP 8.0 and WordPress 6.5 as minimums.

## 2. Place the project

Copy or maintain the project at:

```text
C:\wamp64\www\tinas-cake-haven
```

Do not rename or delete existing application directories.

## 3. Create a local database

Open `http://localhost/phpmyadmin/`, sign in with the local WAMP administrator account and create an empty database, for example `tinas_cake_haven_local`.

Use utf8-compatible collation as recommended for WordPress. No database credentials are provided in this guide.

## 4. Import a backup

No SQL dump was found in the inspected project directory.

If the owner provides an authorized backup:

1. Open the local database in phpMyAdmin.
2. Use **Import**.
3. Select the approved SQL backup.
4. Verify that WordPress tables and `<prefix>tch_*` custom tables were created.
5. Do not import unknown files or live customer data into an unsecured environment.

Database dumps are excluded from Git. Never publish customer records or credentials.

## 5. Configure WordPress safely

Existing `wp-config.php` must not be copied with live credentials. Use placeholders when documenting or creating a local copy:

```php
define( 'DB_NAME', 'DB_NAME_PLACEHOLDER' );
define( 'DB_USER', 'DB_USER_PLACEHOLDER' );
define( 'DB_PASSWORD', 'DB_PASSWORD_PLACEHOLDER' );
define( 'DB_HOST', 'localhost' );
```

Add unique WordPress salts/keys using WordPress's official salt generator or another trusted local method. Do not reuse live private keys.

The project should not be modified to replace `wp-config.php` or database options automatically.

## 6. Configure local URLs

The source dynamically recognizes a `/tinas-cake-haven` path and `localhost:8080` in its local URL fallback. Use the URL that matches the WAMP setup, for example:

```text
http://localhost/tinas-cake-haven/
```

If WAMP is configured to use port 8080, the corresponding URL may be:

```text
http://localhost:8080/
```

Do not globally replace URLs in PHP, database serialized options or WordPress configuration.

## 7. Open the site

Open the local root URL in a browser:

- Public site: local project URL
- WordPress setup: `<local-url>/wp-admin/install.php` if WordPress is not yet configured
- Dashboard: `<local-url>/wp-admin/`

Do not use a live administrator password on a local copy unless explicitly authorized.

## 8. Activate the existing site components

This guide does not instruct or recommend changing plugins/themes during setup. Confirm the existing theme and plugin activation through the local WordPress dashboard only after a backup is available. Do not install, uninstall or activate anything merely to follow this documentation.

## Common problems

| Problem | Check |
|---|---|
| Apache will not start | Port 80/443 conflicts, WAMP services and Windows firewall |
| MySQL will not start | MySQL/MariaDB service, port 3306 and local database configuration |
| 500/blank page | PHP error log, PHP version and missing WordPress files |
| Database connection error | DB name, user, password and host placeholders |
| Styles/images missing | Local URL and WordPress home/site URL values |
| `.htaccess` rewrite errors | Apache `mod_rewrite` and virtual-host or project URL |
| Plugin database missing | Existing database backup and plugin activation status |
| WAMP URL opens wrong project | Apache alias/virtual-host configuration |

## Notes

- Do not commit `wp-config.php`, SQL dumps or media backups.
- Do not edit live database records from a local copy.
- Do not expose private customer data.
- `.gitignore` only controls Git tracking; it does not remove local files or protect a manually prepared ZIP.

## Post-installation verification checklist

After completing the local setup, verify the following:

- [ ] The WAMP services are running successfully.
- [ ] The local website homepage loads without PHP errors.
- [ ] WordPress administrator dashboard is accessible.
- [ ] The Tina's Cake Haven theme is active.
- [ ] The Tina's Cake Haven Core plugin is active.
- [ ] Product pages display the available cake products and prices.
- [ ] The ordering page loads correctly.
- [ ] Order tracking and customer account pages load.
- [ ] Navigation links and contact information work.
- [ ] No live customer information or production credentials are present in the local environment.

Record any failed checks and investigate them before declaring the installation complete.

A successful page load alone does not confirm that all ordering, payment and account functions are working. Test those functions separately using safe test data.
