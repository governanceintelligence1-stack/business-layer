# Database Schema Readiness Report

This report documents code updates made to align the application with the provided PostgreSQL schema (UUID + JSONB design), without creating or migrating a database.

## Scope Completed

- Updated service and controller logic to match key renamed/normalized columns in the target schema.
- Kept local JSON fallback behavior intact for payment testing while database drivers are being fixed.
- Documented remaining gaps that still need implementation before full production parity.

## Implemented Alignments

### 1) `payment_transactions` alignment

Updated `src/Services/PaymentTransactionService.php`:

- `provider_ref` -> `merchant_reference`
- `raw_payload` -> `raw_response`
- status mapping updated to schema values:
  - `paid` -> `successful`
  - `pending`, `failed`, `cancelled` kept compatible
- `idempotency_key` populated for pending creates
- `markActivated()` now keeps schema-valid status flow

Updated `src/Controllers/CheckoutController.php`:

- success status checks now include `successful`
- activation payload decoding supports `raw_response` structure

### 2) `payment_methods` alignment

Updated `src/Services/PaymentMethodService.php`:

- removed reliance on non-schema `user_id` and `cardholder_name` columns
- now sets schema-compatible values:
  - `provider`
  - `provider_customer_id`
  - `provider_payment_method_id`
  - `type`
  - `metadata` JSON for cardholder name

### 3) `api_keys` + `api_usage_logs` alignment

Updated `src/Services/ApiKeyService.php`:

- `user_id` -> `created_by`
- `key_prefix` -> `prefix`
- initialized `scopes` JSON
- removed join dependency on non-schema `api_keys.product_id`
- usage logging:
  - `credits_used` -> `credits_charged`
  - added `units`
- usage aggregation now sums `credits_charged`

### 4) `users` + `user_profiles` split alignment

Updated `src/Services/UserService.php`:

- user reads now join `user_profiles` for `first_name` / `last_name` / `display_name`
- create flow writes to `users` and inserts matching `user_profiles`
- update flow writes to `users` and upserts `user_profiles` names
- profile query now joins `user_profiles`

Updated `src/Services/OrganisationService.php`:

- members list now reads names from `user_profiles`
- default member role changed to schema-valid `viewer`

Updated `src/Controllers/AuthController.php`:

- registration status changed from non-schema `pending` to schema-valid `invited`

### 5) `billing_invoices` alignment

Updated `src/Services/BillingService.php`:

- `amount_total` -> schema fields:
  - `subtotal`
  - `tax_amount`
  - `total`
  - `amount_paid`
  - `amount_due`
- initial invoice status now schema-valid `issued`
- `markPaid()` now updates `amount_paid`, `amount_due`, and timestamps

## Findings (Not Yet Fully Accounted For)

These areas still need targeted refactors to achieve full parity with the provided schema:

1. `CreditService` has legacy assumptions for:
   - `credit_transactions.type` values (`credit`/`debit` vs schema enum values)
   - `job_reservations` columns/statuses (`reserved_credits`, `finalized_at`, `finalized`) not matching target schema (`estimated_credits`, `captured_at`, `captured`, etc.)
2. Some controller/service flows still rely on transitional payment JSON fallback behavior for local testing.
3. Existing migration files in `database/migrations/` still represent the earlier schema shape and are not yet replaced by the new canonical schema.
4. App-level constraints/validations for new enum sets (roles/statuses/source fields) are only partially enforced in PHP; DB constraints are expected to enforce the rest.

## Results

- The app codebase is now significantly closer to the target schema for critical auth/profile, billing, API key, and payment paths.
- No database was created and no migration was executed.
- Updated files pass linter checks.
- Remaining work is concentrated in credit reservation/ledger paths and migration unification.

## Recommended Next Step

After `pdo_pgsql` is fixed locally:

1. Add a new canonical migration chain (or baseline SQL) for the provided schema.
2. Refactor `CreditService` and related API reservation flows to exact `job_reservations` and `credit_transactions` enums/columns.
3. Run end-to-end tests for:
   - register/login/profile
   - checkout/pay/notify/return
   - invoice creation/payment reconciliation
   - API key usage logging

