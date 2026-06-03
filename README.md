# Governance Intelligence Business Layer

The Business Layer is a PHP 8 API-orchestrating frontend for Governance Intelligence. It sits between users, client-facing products, PayFast, and the platform service APIs. It provides:

- A web portal for organisation, product, plan, subscription, credit, API key, and billing management.
- A REST API (`/api/v1/*`) for entitlement checks, credit reservation/deduction flows, API key validation, and usage/balance queries.
- Authentication via Keycloak (OIDC) for portal login.
- Hosted PayFast checkout orchestration and ITN forwarding.

Persistence is owned by backend services:

- `USER_API_URL`: organisations, users, profiles, membership.
- `CLIENT_API_URL`: clients, subscriptions, invoices, payment methods, payment transactions, PayFast ITN logs, API keys.
- `OPERATIONS_API_URL`: products, plans, platform products, entitlements, credit/token accounts, reservations, usage ledger.

## What This System Does

At a high level, the system enforces commercial access to products:

1. Users authenticate through Keycloak and manage commercial settings in the portal.
2. The portal reads and writes business data through the user, client, and operations APIs.
3. Client systems call API endpoints to check entitlement and reserve tokens before work starts.
4. After work completes, tokens are captured or reservations are released through the operations API.
5. PayFast checkout is initiated by this frontend, while payment records, ITN idempotency, invoice fulfillment, subscription updates, and token grants are completed by the client/operations services.

Core business capabilities include:

- Organisation and member management
- Product and plan catalog access
- Subscription lifecycle actions (subscribe/cancel)
- Token wallet operations (top-up, reserve, capture, release, history)
- API key lifecycle and usage analytics
- Billing/invoice listing and lookup

## Architecture and Request Flow

The app uses a lightweight MVC-style structure with service-layer business logic.

### Runtime Flow

1. `public/index.php` loads Composer autoload + environment variables (`vlucas/phpdotenv`).
2. `GI\Core\App` starts session state and loads `src/routes.php`.
3. `GI\Core\Router` matches request method/path and dispatches to controller actions.
4. Controllers validate input, call services, and return either:
   - Rendered views (portal pages), or
   - JSON via `GI\Core\ApiResponse` (REST API).
5. Services call backend APIs through `GI\Core\ApiClient`.
6. A small local JSON/log fallback under `storage/` is used only for PayFast visibility during development; it is not the source of truth.

### Layer Responsibilities

- `src/Core/`
  - App bootstrap, Router, DB wrapper, Session/Auth, Middleware, JSON response helper, view rendering
- `src/Controllers/`
  - HTTP entrypoints for web pages and API endpoints
- `src/Services/`
  - Thin API clients for organisation, product, plan, subscription, token, entitlement, billing, payment methods, payment transactions, API keys, and webhooks
- `views/`
  - PHP templates for portal pages and layouts
- `storage/`
  - Local development PayFast trace files and logs only

## Key Routes

### Portal Routes

- `/` home page
- `/auth/*` login/register/callback/logout
- `/dashboard`
- `/organisation`
- `/products`
- `/plans`
- `/subscriptions`
- `/tokens` (legacy `/credits` redirects here)
- `/api-keys`
- `/billing`

### API Routes (`/api/v1`)

- `GET /health`
- `POST /authorize`
- `POST /reserve`
- `POST /deduct`
- `POST /release`
- `GET /balance/{org_id}`
- `GET /entitlement/{org_id}/{product_slug}`
- `POST /apikeys/validate`
- `GET /usage/{api_key}`
- `GET /products`
- `GET /plans`

## Data Model Overview

The backend service databases define tables for:

- `organisations`, `users`
- `products`, `plans`, `plan_products`
- `subscriptions`
- `credit_accounts`, `credit_transactions`
- `api_keys`, `api_usage_logs`
- `billing_invoices`, `billing_line_items`, `payment_methods`, `payment_transactions`, `payfast_itn_logs`
- `job_reservations`
- `audit_logs`, `webhooks`

## Local Setup

### Prerequisites

- PHP 8.0+
- Composer
- Access to the user, client, and operations service APIs

### 1) Install dependencies

```bash
composer install
```

### 2) Configure environment

Copy `.env.example` to `.env` and update values:

- `APP_*` settings (`APP_URL`, `APP_ENV`, etc.)
- `USER_API_URL`
- `CLIENT_API_URL`
- `OPERATIONS_API_URL`
- `KEYCLOAK_*` OIDC client configuration
- `PAYFAST_*` checkout and ITN configuration
- `SESSION_SECRET`

### 3) Run locally

From project root:

```bash
php -S localhost:8000 -t public
```

Then open `http://localhost:8000`.

## Testing

Run:

```bash
composer test
```

## Notes

- API authentication currently accepts either:
  - `Authorization: Bearer <token>`, or
  - `X-API-Key: <key>`
- CSRF validation is available for POST form requests through middleware/session token checks.
- This frontend should not directly read or write payment, API key, entitlement, invoice, subscription, or token tables. Add backend service endpoints instead.
