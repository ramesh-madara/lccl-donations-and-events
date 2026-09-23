# Shortcodes Reference Guide

Complete reference for all shortcodes provided by the **LCCL Donations and Events** plugin (`lccl-donations-and-events`).

---

## Quick Summary

| Shortcode | Category | Purpose | Attributes |
|---|---|---|---|
| `[lccl_blood_donor_form]` | Form | Blood donation programme registration | `title`, `intro` |
| `[lccl_spectacles_registration]` | Form | Free spectacles programme for school children | `title`, `intro` |
| `[lccl_join_our_projects]` | Form | Volunteer & partner interest registration | `title`, `intro` |
| `[lccl_donation_form]` | Donation / Payment | General donation form with cause selection | `title`, `intro` |
| `[lccl_membership_fee]` | Donation / Payment | Annual membership fee calculator & payment | `title`, `intro` |
| `[lccl_project_sponsorship]` | Donation / Payment | Specific project sponsorship with cards & progress | `banner_title`, `banner_intro`, `title`, `intro` |
| `[lccl_blood_donation_admin]` | Dashboard | Blood donor reviewer login & data dashboard | *None* |
| `[lccl_our_projects_admin]` | Dashboard | Join Our Projects reviewer login & dashboard | *None* |
| `[lccl_spectacles_admin]` | Dashboard | Free Spectacles reviewer login & dashboard | *None* |
| `[lccl_hello]` | Utility | Scaffolding card to test plugin rendering | `title`, `message` |

All shortcodes are also mapped as **WPBakery Page Builder** elements under the **LCCL** category.

---

## 1. Public Registration Forms

### `[lccl_blood_donor_form]`
Renders the public registration form for the Lions Blood Donation Programme. Submissions are saved to the `{prefix}lccl_de_blood_donors` database table.

```text
[lccl_blood_donor_form]
[lccl_blood_donor_form title="Become a Blood Donor" intro="Help us save lives in your area."]
```

- **Attributes**:
  - `title` *(string)*: Form heading. Default: `"Registration Form"`.
  - `intro` *(string)*: Paragraph below title. Pass `intro=""` to hide. Default: *"Please provide the information below so we can identify a convenient blood bank or Lions blood donation campaign in your area."*
- **Key Features**:
  - Dynamic district & blood bank cascade (select district first to load matching Sri Lankan blood banks).
  - Rate limiting, honeypot spam protection, nonce verification.
  - Automatic SMS/email notification triggers upon submission.

---

### `[lccl_spectacles_registration]`
Renders the public application form to register a school child for free vision screening and spectacles. Submissions are saved to `{prefix}lccl_de_spectacles`.

```text
[lccl_spectacles_registration]
[lccl_spectacles_registration title="Free Spectacles Application" intro="Register a student in need of vision care."]
```

- **Attributes**:
  - `title` *(string)*: Form heading. Default: `"Registration Form"`.
  - `intro` *(string)*: Explanatory intro text. Default: Explains eligibility and school principal recommendation letter requirements.
- **Key Features**:
  - Collects child, guardian, school, and vision difficulty details.
  - Supports uploading school principal recommendation letters (PDF, JPG, PNG up to 10MB).
  - Provides downloadable official recommendation letter templates (.docx and .pdf).
  - Files are stored securely and served via signed tokens.

---

### `[lccl_join_our_projects]`
Renders the community partnership and volunteer signup form. Submissions are stored in `{prefix}lccl_de_project_joins`.

```text
[lccl_join_our_projects]
[lccl_join_our_projects title="Join Our Projects" intro="Lend your skills and time to our service projects."]
```

- **Attributes**:
  - `title` *(string)*: Form heading. Default: `"Registration Form"`.
  - `intro` *(string)*: Explanatory intro text. Default: Lions service mission statement.
- **Key Features**:
  - Dynamic conditional fields based on how the user wants to help (volunteering time, professional skills, financial contribution).
  - Register as an individual, company/organization, or community group.

---

## 2. Public Contribution & Payment Forms

### `[lccl_donation_form]`
Renders the public general donation form. Donors can select causes and contribution amounts.

```text
[lccl_donation_form]
[lccl_donation_form title="Make a Donation" intro="Support Lions Club of Colombo LEADS community projects."]
```

- **Attributes**:
  - `title` *(string)*: Form heading. Default: `"Make a Donation"`.
  - `intro` *(string)*: Subheading description. Default: Appreciation and guidance copy.
- **Key Features**:
  - Quick-preset amount buttons: **1,000**, **5,000**, **10,000**, **25,000**, **50,000 LKR**, and **CUSTOM VALUE**.
  - Cause checkboxes: Diabetes Awareness, Vision & Eye Care, Hunger Relief, Environment, Childhood Cancer, Youth, Disaster Relief, Humanitarian.
  - Real-time client-side calculation and summary display.

---

### `[lccl_membership_fee]`
Renders the annual membership dues calculator and payment form for Lions Club members.

```text
[lccl_membership_fee]
[lccl_membership_fee title="Annual Membership Fee" intro="Pay your annual membership dues online."]
```

- **Attributes**:
  - `title` *(string)*: Form heading. Default: `"Annual Membership Fee"`.
  - `intro` *(string)*: Intro text. Default: Instructions for paying membership dues.
- **Key Features**:
  - Membership type selector: **Single Member** vs. **Family Membership** (2 to 5 family members).
  - Live currency conversion (USD International dues to LKR based on configured exchange rate).
  - Itemized live calculation breakdown:
    - International Fee (Main member: USD 50, Additional family members: USD 25 each).
    - District Payment (per member).
    - Club Payment (once per family).
  - Dynamic total display in LKR.

---

### `[lccl_project_sponsorship]`
Renders an interactive split-layout page with ongoing/upcoming project progress cards on one side and a sponsorship payment form on the other.

```text
[lccl_project_sponsorship]
[lccl_project_sponsorship title="Support a Project" intro="Choose a project to fund."]
```

- **Attributes**:
  - `title` *(string)*: Form heading. Default: `"Support a Project"`.
  - `intro` *(string)*: Form intro. Default: *"Please provide the information below to support your selected project."*
  - `banner_title` *(string)*: Top full-width banner heading (optional). Default: `""`.
  - `banner_intro` *(string)*: Top full-width banner paragraph (optional). Default: `""`.
- **Key Features**:
  - Visual project cards displaying Project Value, Amount Raised, Amount Remaining, and progress bars.
  - Clicking "SUPPORT THIS PROJECT" on any card auto-selects the project in the form and smoothly scrolls to checkout.
  - Preset amount buttons and custom amount input.

---

## 3. Reviewer & Admin Dashboards

These shortcodes render single-page frontend application (SPA) portals. They handle reviewer login, session management, searching, filtering, and record management via WordPress REST API (`lccl-de/v1/*`).

> **Security Note**: Reviewer accounts have the `lccl_blood_donation_reviewer` role with the `view_lccl_submissions` capability. They are strictly blocked from `wp-admin` and access submissions only through these frontend shortcode pages.

### `[lccl_blood_donation_admin]`
Reviewer portal for managing blood donor registrations.
- **Attributes**: None.
- **Default Page**: `/blood-donation-admin/`
- **Features**: REST-driven AJAX login/logout, password reset, district filter, donor search, pagination (10/20/50), donor detail inspection modal, and CSV export.

### `[lccl_our_projects_admin]`
Reviewer portal for reviewing Join Our Projects volunteer and partner signups.
- **Attributes**: None.
- **Default Page**: `/our-projects-admin/`
- **Features**: Registration review, filter by support areas and registration type, detail viewer modal.

### `[lccl_spectacles_admin]`
Reviewer portal for reviewing child spectacles applications.
- **Attributes**: None.
- **Default Page**: `/spectacles-admin/`
- **Features**: Application list, school and district filters, school recommendation letter preview and secure download directly in the modal.

---

## 4. Developer / Test Utility

### `[lccl_hello]`
A lightweight scaffolding card used to verify that the plugin is active, styles are loading, and shortcodes render correctly.

```text
[lccl_hello]
[lccl_hello title="Smoke Test" message="Verification in progress"]
```

- **Attributes**:
  - `title` *(string)*: Card title. Default: `"Hello from LCCL"`.
  - `message` *(string)*: Body text. Default: *"The plugin is active and this UI is rendering from a shortcode."*
- **Displays**: Plugin version, shortcode tag name, and whether the viewing user is logged in.

---

## Styling & Layout Tips

1. **Full-Width Flow**: Public forms (`.lccl-bdf`) do not have a fixed `max-width`. They stretch to fit whatever column or container WPBakery places them in.
2. **Theme Specificity**: All styles are scoped under `.lccl-bdf` or `.lccl-bda` to ensure they override the Kalium theme's `input[type="..."]` selectors without requiring `!important`.
3. **No-Cache Requirement**: Pages containing dashboard shortcodes (`[lccl_*_admin]`) automatically send `nocache_headers()`. If caching plugins like W3 Total Cache are active, make sure those dashboard URLs are explicitly excluded from page caching.
