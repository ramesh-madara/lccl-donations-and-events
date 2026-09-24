# Payment Gateway Archive: Mastercard Payment Gateway Services (MPGS)

This archive preserves the complete implementation of the **Mastercard Payment Gateway Services (MPGS) Hosted Checkout v66** integration prior to migrating to the **Commercial Bank of Ceylon (CBC) Paycenter Web 4.0** payment gateway.

---

## 1. Archived Files Overview

| File Path | Description |
| :--- | :--- |
| `archive/includes/class-lccl-de-mpgs-client.php` | MPGS REST API v66 client (`INITIATE_CHECKOUT` session creation and `RETRIEVE_ORDER` verification calls). |
| `archive/includes/class-lccl-de-membership-form.php` | Full membership fee submission handler, fee calculation, payment initiation, callback verification, and shortcode router under MPGS. |
| `archive/includes/class-lccl-de-settings.php` | MPGS multi-profile settings, profile normalization, credential storage, and OpenSSL AES-256-GCM secret encryption at rest. |
| `archive/templates/admin-gateway.php` | WP-Admin Gateway settings UI for configuring MPGS merchant credentials, API password, and route toggles. |
| `archive/templates/membership-receipt.php` | Hosted Checkout launch page loading Mastercard `checkout.js` (`Checkout.configure` and `Checkout.showPaymentPage`). |
| `archive/templates/membership-result.php` | Callback results template displaying approval/declined receipts with receipt download and return links. |
| `archive/docs/mastercard-IPG-Implementation.md` | Detailed architectural documentation of the MPGS Hosted Checkout workflow, tables, and security practices. |
| `archive/docs/FINAL-_Migration_guide-_HCO_2_1 (3).pdf` | Official Mastercard Hosted Checkout 2.1 migration guide specification. |

---

## 2. Key Architecture & Flow (MPGS Hosted Checkout v66)

### Three-Step Checkout Workflow:
1. **Server-Side Session Initiation (`INITIATE_CHECKOUT`):**
   - Plugin computes payment total strictly server-side using current exchange rate and fee formula.
   - Database record created in `wp_lccl_de_payments` in `pending` status.
   - Server makes an authenticated HTTPS `POST` to MPGS:
     `https://cbcmpgs.gateway.mastercard.com/api/rest/version/66/merchant/{merchantId}/session`
     with HTTP Basic Auth (`merchant.{merchantId}` : `{api_password}`).
   - MPGS returns `session.id` and `successIndicator`. Both are stored in `wp_lccl_de_payments`.

2. **Client-Side Payment Interaction:**
   - User is served `templates/membership-receipt.php` loading Mastercard's Hosted Checkout JavaScript (`checkout.js`).
   - `Checkout.configure({ session: { id: sessionId } })` initializes the modal/page.
   - User enters card details and completes 3D-Secure in the Mastercard-hosted environment (Zero PCI scope for WordPress).
   - Upon completion, MPGS redirects browser to `returnUrl` appending `?resultIndicator=...`.

3. **Server-Side Payment Verification (`RETRIEVE_ORDER`):**
   - In `verify_payment()`, the returned `resultIndicator` is compared against stored `success_indicator` using `hash_equals()`.
   - Plugin makes a server-to-server `GET` request to MPGS:
     `https://cbcmpgs.gateway.mastercard.com/api/rest/version/66/merchant/{merchantId}/order/{order_ref}`
   - Plugin performs strict verification:
     - Order status is `CAPTURED` or `AUTHENTICATED`.
     - Returned currency matches payment currency (`LKR`).
     - Returned amount matches database amount.
   - Upon match, atomic SQL update sets status to `paid`, stores transaction receipt, and dispatches automated admin + customer email notifications.

---

## 3. Migration Context

- **New Gateway:** Commercial Bank of Ceylon (CBC) Paycenter Web 4.0 / Bancstac API v1.5 (`PAYMENT_INIT` and `PAYMENT_COMPLETE`).
- **Date Archived:** September 2026.
