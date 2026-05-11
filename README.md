# Governance Intelligence Business Layer

The Business Layer is a PHP 8 application that sits between client-facing products and core platform services. It provides:

- A web portal for organisation, product, plan, subscription, credit, API key, and billing management.
- A REST API (`/api/v1/*`) for entitlement checks, credit reservation/deduction flows, API key validation, and usage/balance queries.
- Authentication via Keycloak (OIDC) for portal login.
- PostgreSQL-backed persistence for organisations, users, products/plans, subscriptions, credits, API usage, invoices, and audit/webhook records.

## What This System Does

At a high level, the system enforces commercial access to products:

1. Users authenticate through Keycloak and manage commercial settings in the portal.
2. Client systems call API endpoints to check entitlement and reserve credits before work starts.
3. After work completes, credits are deducted (or reservations released).
4. Usage and billing records are persisted for reporting and invoicing.

Core business capabilities include:

- Organisation and member management
- Product and plan catalog access
- Subscription lifecycle actions (subscribe/cancel)
- Credit wallet operations (top-up, reserve, deduct, release, history)
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
5. Services interact with PostgreSQL through `GI\Core\DB`.

### Layer Responsibilities

- `src/Core/`
  - App bootstrap, Router, DB wrapper, Session/Auth, Middleware, JSON response helper, view rendering
- `src/Controllers/`
  - HTTP entrypoints for web pages and API endpoints
- `src/Services/`
  - Business logic for organisation, product, plan, subscription, credit, entitlement, billing, API keys, webhooks
- `views/`
  - PHP templates for portal pages and layouts
- `database/migrations/`
  - SQL schema migrations
- `database/seeds/`
  - Seed data for products/plans

## Key Routes

### Portal Routes

- `/` home page
- `/auth/*` login/register/callback/logout
- `/dashboard`
- `/organisation`
- `/products`
- `/plans`
- `/subscriptions`
- `/credits`
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

The migrations define tables for:

- `organisations`, `users`
- `products`, `plans`, `plan_products`
- `subscriptions`
- `credit_accounts`, `credit_transactions`
- `api_keys`, `api_usage_logs`
- `billing_invoices`, `billing_line_items`
- `job_reservations`
- `audit_logs`, `webhooks`

## Local Setup

### Prerequisites

- PHP 8.0+
- Composer
- PostgreSQL 13+

### 1) Install dependencies

```bash
composer install
```

### 2) Configure environment

Copy `.env.example` to `.env` and update values:

- `APP_*` settings (`APP_URL`, `APP_ENV`, etc.)
- `DB_*` PostgreSQL connection
- `KEYCLOAK_*` OIDC client configuration
- `SESSION_SECRET`

### 3) Create database schema

Run all SQL files in `database/migrations/` in numeric order, then optionally apply seeds in `database/seeds/`.

### 4) Run locally

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
