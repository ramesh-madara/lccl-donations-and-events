# Architecture

## Current state

The plugin renders front end UI. Nothing is persisted yet.

### Bootstrap

`lccl-donations-and-events.php` defines the constants, requires the feature
classes, and hooks each one's static `init()` on `init`:

```php
add_action( 'init', array( 'LCCL_DE_Shortcodes', 'init' ) );
add_action( 'init', array( 'LCCL_DE_Blood_Donor_Form', 'init' ) );
```

Registering on `init` rather than at file load keeps shortcode registration
predictable and lets the option filters run after the theme has loaded.

### Separation of concerns

- `includes/class-lccl-de-*.php` — registration, option data, render entry point
- `templates/*.php` — markup only, included from the render method
- `assets/css/*` — styles, enqueued from the render method

Templates are included from inside a class method, so they inherit that scope.
The form template references the class by its full name
(`LCCL_DE_Blood_Donor_Form::render_options()`) rather than `self::` to keep
that dependency obvious.

## Roadmap

### 1. Data layer

A custom table is the better fit here rather than a custom post type. The
database already carries roughly 8,500 posts from WooCommerce and GiveWP, and
submissions are structured records that benefit from real columns and indexes.

- Create with `dbDelta()` from a registration hook.
- Store a schema version in an option and run migrations when it changes.
- Columns should follow the field names documented in
  [shortcodes.md](shortcodes.md).

### 2. Submission handling

Handle the POST on `admin_post_` / `admin_post_nopriv_` or a REST route rather
than inside the shortcode render. Rendering happens during output, which is too
late to redirect and makes nonce handling awkward.

Checklist:

- Verify `lccl_de_nonce` against the `lccl_de_blood_donor_register` action.
- Sanitize on input, escape on output.
- Validate that select values exist in their filtered option arrays.
- Confirm the blood bank belongs to the submitted district with
  `LCCL_DE_Blood_Donor_Form::is_valid_blood_bank()`. The dependency between
  those two fields is enforced in the browser, which a crafted POST can skip.
- Require `consent`.
- Add rate limiting or a honeypot; the form is public.
- Redirect after a successful POST so refresh does not resubmit.
- On failure, pass `$values` and `$errors` back into the template — both are
  already supported, though error display markup still needs writing.

### 3. Reviewer access

Requirement: reviewers read submissions on a front end page and must not reach
wp-admin or be able to change anything.

**Capabilities are the security boundary. Blocking the URL is only cosmetic.**
Every write path in WordPress gates on a capability, so a role holding nothing
but a single custom capability fails all of them inside core, before plugin
code is involved.

```php
add_role( 'lccl_submissions_manager', 'Submissions Manager', array(
	'view_lccl_submissions' => true,
) );
```

Omitting `read` matters. When it is absent, `user_can_access_admin_page()`
returns false and `wp-admin/includes/menu.php` stops the request with a 403, so
core itself locks these accounts out of the dashboard.

Trade-off: without `read` they cannot reach `profile.php`, so they cannot
change their own password or email. Plan for the lost password flow
(`wp_lostpassword_url()`) or a front end profile form.

Supporting work:

- Redirect on `admin_init` for a cleaner result than a bare 403, guarded with
  `wp_doing_ajax()` so `admin-ajax.php` keeps working.
- Filter `show_admin_bar` to false for anyone lacking `edit_posts`.
- Use `login_redirect` to send them to the submissions page after login.
- Check `current_user_can( 'view_lccl_submissions' )` before any output on that
  page, redirecting to `wp_login_url()` otherwise.

`add_role()` writes to the `wp_user_roles` option and silently does nothing if
the role already exists. Call it from the activation hook, and keep a version
option so capability changes can be detected and re-saved — otherwise existing
accounts keep the old capability set forever.

### 4. Caching

**W3 Total Cache is active on production.** A front end page rendering private
submission data is exactly where page caching leaks data to the wrong visitor.
W3TC skips caching for logged in users by default, but that default should not
be the only thing protecting personal data.

- Explicitly exclude the submissions page in W3TC settings.
- Call `nocache_headers()` when rendering it.
- Never rely on an unguessable URL.

## Open questions

- Real blood bank list, see [filters.md](filters.md).
- Whether the events side reuses this form structure or needs its own tables.
- Whether reviewers should be able to export submissions, and in what format.
- Retention policy for personal data, given the consent wording promises
  withdrawal of optional communications.
