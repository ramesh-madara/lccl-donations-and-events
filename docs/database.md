# Database

Blood donor registrations live in a dedicated table, not in `wp_posts`.
Submissions are structured records; a custom table keeps them out of the
WooCommerce / GiveWP post pile and makes boolean filters cheap.

## Table

Name: `{prefix}lccl_de_blood_donors`

Locally that is `4FN0Y_lccl_de_blood_donors`. Created by
`LCCL_DE_Schema::install()` on plugin activation, and again from
`plugins_loaded` if the stored schema version is behind.

Schema version is stored in the `lccl_de_schema_version` option. Bump
`LCCL_DE_Schema::VERSION` when the `CREATE TABLE` changes so `dbDelta`
runs again.

## Columns

| Column | Type | Required | Source field | Notes |
| --- | --- | --- | --- | --- |
| `id` | `bigint unsigned` PK | yes | — | Auto increment |
| `first_name` | `varchar(100)` | yes | `first_name` | |
| `last_name` | `varchar(100)` | yes | `last_name` | |
| `address` | `varchar(255)` | yes | `address` | |
| `city` | `varchar(100)` | yes | `city` | |
| `postal_code` | `varchar(20)` NULL | no | `postal_code` | |
| `email` | `varchar(191)` NULL | no | `email` | Validated only when present |
| `phone` | `varchar(30)` | yes | `phone` | |
| `district` | `varchar(64)` | yes | `district` | District **key**, e.g. `Colombo` |
| `blood_bank` | `varchar(64)` | yes | `blood_bank` | Bank **key**, e.g. `panadura` |
| `blood_bank_label` | `varchar(255)` NULL | — | snapshot | Label at submit time, so later label edits do not rewrite history |
| `donation_preference` | `varchar(32)` NULL | no | `donation_preference` | `blood-bank` / `campaign` / `either` |
| `donated_before` | `varchar(16)` NULL | no | `donated_before` | `yes` / `no` / `not-sure` |
| `contact_method` | `varchar(16)` | yes | `contact_method` | `phone` / `whatsapp` / `sms` / `email` |
| `notify_campaigns` | `tinyint(1)` | yes | `notify_campaigns` | **Boolean.** `1` if the campaign checkbox was ticked, `0` otherwise. Never NULL. |
| `consent` | `tinyint(1)` | yes | `consent` | Boolean. Always `1` on a successful insert — the row is not saved without consent. |
| `ip_address` | `varchar(45)` NULL | — | request | For rate limiting / abuse review. IPv4 or IPv6. |
| `created_at` | `datetime` | yes | — | Site local time via `current_time( 'mysql' )` |

## Why those types

`notify_campaigns` is a boolean column, not `'yes'` / `'no'` / `'1'`. A
mailing query is then:

```sql
SELECT * FROM 4FN0Y_lccl_de_blood_donors WHERE notify_campaigns = 1;
```

`blood_bank` stores the stable key. `blood_bank_label` stores the human
name as it appeared when they submitted, so a later rename of
"Panadura - Base Hospital Panadura Blood Bank" does not leave old rows
pointing at a missing label.

Email is not unique. The same person may register again; we can add a
soft-duplicate check later if needed.

## Indexes

| Index | Purpose |
| --- | --- |
| `PRIMARY KEY (id)` | Row identity |
| `KEY district` | Filter by district |
| `KEY blood_bank` | Filter by bank |
| `KEY notify_campaigns` | Campaign opt-in list |
| `KEY created_at` | Newest-first listing |
| `KEY email` | Lookup by address |

## What is not stored

- Password or WordPress user ID. Registrants are not site users.
- Raw `$_POST`. Only sanitised columns.
- Medical eligibility. The consent copy says the blood bank decides that.

## Required fields vs optional

Required at submit time (browser **and** server):

`first_name`, `last_name`, `address`, `city`, `phone`, `district`,
`blood_bank`, `contact_method`, `consent`.

The blood bank must also belong to the submitted district
(`LCCL_DE_Blood_Donor_Form::is_valid_blood_bank()`). The browser
dependency can be skipped with a crafted POST.

Optional: `postal_code`, `email`, `donation_preference`, `donated_before`,
`notify_campaigns` (defaults to `0`).

## Writes

`LCCL_DE_Blood_Donor_Submissions::handle()` runs on
`admin_post` / `admin_post_nopriv` for action `lccl_de_blood_donor_register`.

Flow: nonce → honeypot → rate limit (8 / IP / hour) → sanitise → validate →
insert → redirect back with a one-time flash token. Refresh after success
does not insert a second row.

## Inspecting locally

```sql
SHOW CREATE TABLE 4FN0Y_lccl_de_blood_donors\G
SELECT id, first_name, last_name, district, blood_bank, notify_campaigns, created_at
FROM 4FN0Y_lccl_de_blood_donors
ORDER BY id DESC
LIMIT 10;
```
