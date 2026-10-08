# Blood donation admin

Two surfaces, one identity system. Reviewers are real WordPress users.
They never receive `read` or any other core capability, so wp-admin refuses
them even if a URL is guessed.

## Who sees what

| Person | wp-admin | Frontend dashboard |
| --- | --- | --- |
| Site administrator (`manage_options`) | **Blood Donation Users** menu | Can open the same dashboard to preview |
| Program Reviewer | Redirected away | Login + read-only registration list |
| Program Commenter | Redirected away | Login + read-only registration list + edit comments |
| Everyone else | Unchanged | Login form only |

Role slugs: `lccl_blood_donation_reviewer`, `lccl_program_commenter`.  
Capabilities: `view_lccl_submissions`, `comment_lccl_submissions`.  
Version option: `lccl_de_roles_version`. Bump `LCCL_DE_Roles::VERSION` when
the capability set changes so existing accounts are updated.

Inactive reviewers have user meta `lccl_de_reviewer_disabled` = `1`. Core
login and the REST session route both reject them.

## Blood Donation Users (wp-admin)

`LCCL_DE_Admin_Users` registers a top-level menu. It lists **only** the
reviewer and commenter roles. Create / edit / deactivate / delete use `wp_insert_user()`,
`wp_update_user()`, and `wp_delete_user()`. There is a role dropdown to select between Program Reviewer and Program Commenter.

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
| PUT | `/donors/{id}/comments` | Commenter or admin. Edit the comments field. |

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

## LCCL Programs (wp-admin Dashboards)

`LCCL_DE_Admin_Programs` registers a suite of administrative tools located under **"LCCL Programs"** in the WordPress admin menu:

- **Blood Donors, Free Spectacles, Our Projects**: Links to load the respective frontend dashboards.
- **Payment Dashboard**: A unified ledger for tracking real-time status of all transactions (Donations, Sponsorships, Memberships). Uses `[lccl_payment_dashboard]`. Restricted to wp-admin users only.
- **Payment Gateway**: Configures multiple routing profiles (Donations, Member Fees, Fundraisers) for the **CBC Paycenter** integration, including endpoint, Merchant ID, and AES-256-GCM encrypted API tokens.
- **Membership Fees**: Allows finance officers to dynamically adjust the LKR/USD exchange rate, District Fees, and Club Fees. Keeps a revision history.
- **Testimonials**: Admin interface for managing the testimonials carousel. Allows adding, editing, ordering, and activating quotes and photos.
- **Fundraiser**: Admin interface to create fundraiser events, configure table types (prices and capacities), generate table inventory, and monitor bookings.
- **Notifications**: Configure recipient email addresses for admin alerts.
