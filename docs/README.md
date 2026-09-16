# LCCL Donations and Events

Donation and event management for the Lions Club of Colombo Leads
(`colomboleads.org`).

Current version: **0.7.1**

## What it does today

The plugin registers front end shortcodes and exposes each of them as a
WPBakery page builder element.

| Shortcode | Purpose |
| --- | --- |
| `[lccl_blood_donor_form]` | Blood donation programme registration form |
| `[lccl_blood_donation_admin]` | Reviewer login and donor dashboard |
| `[lccl_hello]` | Scaffolding card used to verify the plugin renders |

The registration form stores rows in `{prefix}lccl_de_blood_donors`. Site
admins manage reviewer WordPress accounts from **Blood Donation Users**.
Reviewers use the frontend dashboard. See [admin.md](admin.md).

## Documentation

| Document | Contents |
| --- | --- |
| [shortcodes.md](shortcodes.md) | Shortcode reference, attributes, form field names |
| [wpbakery.md](wpbakery.md) | How elements are registered, how to add another |
| [styling.md](styling.md) | CSS variables, theming, the Kalium specificity rule |
| [filters.md](filters.md) | Filters for overriding dropdown option sets |
| [architecture.md](architecture.md) | File layout and the planned roadmap |
| [database.md](database.md) | Blood donor table schema and write path |
| [admin.md](admin.md) | Reviewer role, wp-admin user CRUD, dashboard REST |
| [local-development.md](local-development.md) | XAMPP environment setup and gotchas |

## File layout

```
lccl-donations-and-events/
├── lccl-donations-and-events.php          Plugin header, constants, bootstrap
├── includes/
│   ├── class-lccl-de-schema.php           Table installer
│   ├── class-lccl-de-roles.php            Reviewer role + dashboard page
│   ├── class-lccl-de-access.php           wp-admin lock
│   ├── class-lccl-de-admin-users.php      Blood Donation Users menu
│   ├── class-lccl-de-shortcodes.php       [lccl_hello]
│   ├── class-lccl-de-blood-donor-form.php [lccl_blood_donor_form]
│   ├── class-lccl-de-blood-donor-submissions.php  Validate + save
│   └── class-lccl-de-dashboard.php        [lccl_blood_donation_admin] + REST
├── templates/
│   ├── blood-donor-form.php               Public form markup
│   ├── blood-donation-admin.php           Login + dashboard shell
│   └── admin-users.php                    wp-admin user CRUD
├── assets/
│   ├── css/
│   │   ├── lccl-de.css                    Hello card styles
│   │   ├── lccl-de-blood-donor-form.css   Form styles
│   │   └── lccl-de-blood-donation-admin.css  Dashboard styles
│   └── js/
│       ├── lccl-de-blood-donor-form.js    District/bank lock + required-field gate
│       └── lccl-de-blood-donation-admin.js  AJAX login + donor list
└── docs/
```

## Constants

Defined in the main plugin file and available everywhere once loaded.

| Constant | Value |
| --- | --- |
| `LCCL_DE_VERSION` | Plugin version, also used for asset cache busting |
| `LCCL_DE_FILE` | Absolute path to the main plugin file |
| `LCCL_DE_PATH` | Plugin directory path, trailing slash |
| `LCCL_DE_URL` | Plugin directory URL, trailing slash |

## Conventions

- Text domain is `lccl-de`.
- Classes are prefixed `LCCL_DE_` and live in `includes/` as
  `class-lccl-de-*.php`.
- Each feature registers itself from a static `init()` method hooked on
  `init` from the main plugin file.
- Markup lives in `templates/`, not inside PHP classes.
- Stylesheets are enqueued from inside the shortcode callback so they only
  load on pages that actually use the element.
