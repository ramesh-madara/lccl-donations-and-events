# Architecture

## Current state

The plugin renders the registration form, stores accepted submissions in
`{prefix}lccl_de_blood_donors`, and exposes a frontend reviewer dashboard.
Site admins CRUD those reviewer accounts from **Blood Donation Users**.

### Bootstrap

`lccl-donations-and-events.php` defines the constants, requires the feature
classes, and hooks each one's static `init()` on `init`:

```php
register_activation_hook( __FILE__, array( 'LCCL_DE_Schema', 'install' ) );
register_activation_hook( __FILE__, array( 'LCCL_DE_Roles', 'install' ) );
add_action( 'plugins_loaded', array( 'LCCL_DE_Schema', 'maybe_install' ) );
add_action( 'init', array( 'LCCL_DE_Roles', 'maybe_install' ), 5 );
add_action( 'init', array( 'LCCL_DE_Access', 'init' ) );
add_action( 'init', array( 'LCCL_DE_Admin_Users', 'init' ) );
add_action( 'init', array( 'LCCL_DE_Shortcodes', 'init' ) );
add_action( 'init', array( 'LCCL_DE_Blood_Donor_Form', 'init' ) );
add_action( 'init', array( 'LCCL_DE_Blood_Donor_Submissions', 'init' ) );
add_action( 'init', array( 'LCCL_DE_Dashboard', 'init' ) );
```

Registering on `init` rather than at file load keeps shortcode registration
predictable and lets the option filters run after the theme has loaded.

### Separation of concerns

- `includes/class-lccl-de-*.php` — schema, option data, render, POST handler
- `templates/*.php` — markup only, included from the render method
- `assets/css/*` — styles, enqueued from the render method
- `assets/js/*` — district/bank lock and required-field submit gate

Templates are included from inside a class method, so they inherit that scope.
The form template references the class by its full name
(`LCCL_DE_Blood_Donor_Form::render_options()`) rather than `self::` to keep
that dependency obvious.

## Roadmap

### 1. Data layer and submission handling

Done. See [database.md](database.md).

### 2. Reviewer access

Done. See [admin.md](admin.md).

### 3. Caching

**W3 Total Cache is active on production.** A front end page rendering private
submission data is exactly where page caching leaks data to the wrong visitor.
W3TC skips caching for logged in users by default, but that default should not
be the only thing protecting personal data.

- Explicitly exclude `/blood-donation-admin/` in W3TC settings.
- Call `nocache_headers()` when rendering it.
- Never rely on an unguessable URL.

## Open questions

- Real blood bank list, see [filters.md](filters.md).
- Whether the events side reuses this form structure or needs its own tables.
- Whether reviewers should be able to export submissions, and in what format.
- Retention policy for personal data, given the consent wording promises
  withdrawal of optional communications.
