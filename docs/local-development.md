# Local development environment

Notes for the local copy of `colomboleads.org` this plugin is developed
against. The site root is `M:\Dev\Work\LCCL`.

## Stack

| Component | Version |
| --- | --- |
| Local URL | `http://lccl.local` |
| Server | XAMPP, Apache 2.4.58 |
| PHP | 8.2.12 (production runs 8.4) |
| Database | MariaDB 10.4.32 (production runs 10.11) |
| WordPress | 7.1 |
| Database name | `lccl_wp5` |
| Table prefix | `4FN0Y_` |
| Theme | Kalium, child theme `kalium-child-construction` |
| Page builder | WPBakery (`js_composer`) 9.0.1 |

## Apache

A vhost in `C:\xampp\apache\conf\extra\httpd-vhosts.conf` points
`lccl.local` at the site root with `AllowOverride All`.

`lccl.local` must resolve to `127.0.0.1` in
`C:\Windows\System32\drivers\etc\hosts`. Editing that file needs
Administrator rights; `add-lccl-host.bat` in the site root does it.

## Importing the database

**Raise `max_allowed_packet` before importing.** The XAMPP default of 1MB is
far too small for the production dump, and the import fails partway through
with no obvious error — it simply stops creating tables. The first import here
produced 91 of 164 tables and silently omitted `4FN0Y_users`, which presented
as "Incorrect username or password" on a perfectly valid login.

`C:\xampp\mysql\bin\my.ini` is now set to `max_allowed_packet=256M`.

```powershell
$mysql = "C:\xampp\mysql\bin\mysql.exe"

& $mysql -u root -e "DROP DATABASE IF EXISTS lccl_wp5; CREATE DATABASE lccl_wp5 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci; GRANT ALL PRIVILEGES ON lccl_wp5.* TO 'lccl_wp5'@'%'; FLUSH PRIVILEGES;"

& $mysql -u root --max_allowed_packet=1G --default-character-set=utf8mb4 -D lccl_wp5 -e "source M:/Dev/Work/LCCL/lccl_wp5.sql"
```

PowerShell does not support `<` input redirection, so use `-e "source ..."`.

Sanity check afterwards — a complete import has **164 tables**:

```sql
SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='lccl_wp5';
SELECT COUNT(*) FROM 4FN0Y_users;
```

A re-import restores the production site URL, so reset it:

```sql
UPDATE 4FN0Y_options SET option_value='http://lccl.local'
WHERE option_name IN ('siteurl','home');
```

## Development changes to site files

These are local only and **must not reach production**.

`wp-config.php`:

```php
define( 'WP_CACHE', false );          // W3 Total Cache page cache off
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );       // writes wp-content/debug.log
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_HOME', 'http://lccl.local' );
define( 'WP_SITEURL', 'http://lccl.local' );
define( 'DISALLOW_FILE_EDIT', false );
```

Also disabled for XAMPP compatibility, since they are cPanel/LiteSpeed
specific and break locally:

- `.htaccess` — the `AddHandler application/x-httpd-ea-php82` block and the
  `session.save_path` lines
- `php.ini` and `.user.ini` — `session.save_path`

## Known differences from production

- Content still references `https://www.colomboleads.org`, so some media loads
  from the live site. Harmless for plugin work; a search-replace would be
  needed for a fully offline copy.
- The Under Construction plugin is active in the database.
- Ninja Forms and WPForms are both active, worth knowing before building
  anything form related.
- PHP and MariaDB are a minor version behind production.

## Testing the plugin

The form currently renders on a scratch page at
`http://lccl.local/blood-donor-form-test/` containing just
`[lccl_blood_donor_form]`. Submissions land in `4FN0Y_lccl_de_blood_donors`.
Delete the page whenever; recreate by adding the shortcode to any page.

The reviewer dashboard is created at
`http://lccl.local/blood-donation-admin/` (`[lccl_blood_donation_admin]`).
Site admins manage those accounts under **Blood Donation Users** in
wp-admin. Exclude that page in W3 Total Cache on production.

For a quick render check without a browser:

```powershell
cd M:\Dev\Work\LCCL
& "C:\xampp\php\php.exe" -r "require 'wp-load.php'; echo do_shortcode('[lccl_blood_donor_form]');"
```

Note that activating a plugin inside a one-off CLI script will not register its
shortcodes in that same request, because `init` has already fired by then. Run
the script a second time to test.
