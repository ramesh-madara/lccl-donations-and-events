# Blood donation admin

Two surfaces, one identity system. Reviewers are real WordPress users.
They never receive `read` or any other core capability, so wp-admin refuses
them even if a URL is guessed.

## Who sees what

| Person | wp-admin | Frontend dashboard |
| --- | --- | --- |
| Site administrator (`manage_options`) | **Blood Donation Users** menu | Can open the same dashboard to preview |
| Blood Donation Reviewer | Redirected away | Login + read-only donor list |
| Everyone else | Unchanged | Login form only |

Role slug: `lccl_blood_donation_reviewer`.  
Capability: `view_lccl_submissions`.  
Version option: `lccl_de_roles_version`. Bump `LCCL_DE_Roles::VERSION` when
the capability set changes so existing accounts are updated.

Inactive reviewers have user meta `lccl_de_reviewer_disabled` = `1`. Core
login and the REST session route both reject them.

## Blood Donation Users (wp-admin)

`LCCL_DE_Admin_Users` registers a top-level menu. It lists **only** this
role. Create / edit / deactivate / delete use `wp_insert_user()`,
`wp_update_user()`, and `wp_delete_user()`. There is no role dropdown.

Fields: username (create only), email, first name, last name, password
(required on create, optional on edit), inactive checkbox.

## Frontend dashboard

Page created on role install: `/blood-donation-admin/`
(`lccl_de_admin_page_id`). Shortcode: `[lccl_blood_donation_admin]`.

First paint is login or dashboard chrome. After that, login, logout,
forgot-password, list, filters, and detail are REST calls — no full reload.

Namespace `lccl-de/v1`:

| Method | Route | Who |
| --- | --- | --- |
| POST | `/session` | Public, rate-limited. `wp_signon()`. |
| DELETE | `/session` | Reviewer or admin. `wp_logout()`. |
| GET | `/session` | Anyone. `{ logged_in, nonce, … }` |
| POST | `/session/forgot` | Public, rate-limited. `retrieve_password()`. |
| GET | `/donors` | Reviewer or admin. Search, district, notify, page. |
| GET | `/donors/{id}` | Same. One registration. IP only for admins. |

Cookie auth after login. Each response that can issue a session returns a
fresh `wp_rest` nonce. JS sends it as `X-WP-Nonce`.

Donor rows are **read-only**.

## Caching

`nocache_headers()` runs when the shortcode renders. That is not enough on
production: **exclude `/blood-donation-admin/` in W3 Total Cache**. Never
rely on an unguessable URL. W3TC's "don't cache logged-in users" default
must not be the only control.

## Lockdown

`LCCL_DE_Access`:

- `wp_loaded` redirects reviewers out of wp-admin (core would 403 them
  before `admin_init`; AJAX / REST are left alone)
- `show_admin_bar` is false for them
- `login_redirect` sends them to the dashboard if they use `wp-login.php`
- `wp_authenticate_user` blocks inactive reviewer accounts
