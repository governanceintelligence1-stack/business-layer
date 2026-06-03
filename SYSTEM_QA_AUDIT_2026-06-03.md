# System QA Audit - 2026-06-03

## Scope

Tested the business-layer frontend at `http://localhost:3001` and the three service APIs configured in `.env`:

- `USER_API_URL=http://localhost:8010`
- `CLIENT_API_URL=http://localhost:8020`
- `OPERATIONS_API_URL=http://localhost:8030`

Test identity:

- User: `22222222-2222-2222-2222-222222222222`
- Organisation: `11111111-1111-1111-1111-111111111111`
- Email: `nomsa.khumalo@khumaloforensics.co.za`

## Verification Summary

- `composer test`: passed, `3 tests`, `19 assertions`.
- PHP lint passed for inspected core files: `Router`, `ApiController`, `ProductController`, `EntitlementService`, `ApiKeyService`.
- API health endpoints passed:
  - `GET /health` on user-api, client-api, operations-api.
- Business-layer app is running on `localhost:3001`.
- Other local PHP ports are not this app:
  - `8080`: forensic upload app, `/api/v1/health` hangs.
  - `8095`: Aurexx banking app.
  - `3000`, `8765`, `8799`: credit dashboard apps.

## Frontend Routes Tested

| Route | Result | Notes |
| --- | --- | --- |
| `GET /` | 200 | Home loads. |
| `GET /dashboard` | 200 | Loads in about 950 ms. |
| `GET /updates` | 200 | Loads. |
| `GET /profile` | 200 | Loads. |
| `GET /organisation` | 200 | Loads. |
| `GET /organisation/members` | 200 | Loads. |
| `GET /products` | 200 | Loads. |
| `GET /products/upload-forensic-image` | 403 | Bug: Enterprise org is denied product access. |
| `GET /products/ocr` | 403 | Bug: Enterprise org is denied product access. |
| `GET /products/transcription` | 403 | Bug: Enterprise org is denied product access. |
| `GET /products/bank-statements` | 403 | Bug: Enterprise org is denied product access. |
| `GET /products/file-comparison` | 403 | Bug: Enterprise org is denied product access. |
| `GET /plans` | 200 | Loads active plans. |
| `GET /subscriptions` | 200 | Loads current subscription. |
| `GET /subscriptions/history` | 200 | Loads. |
| `GET /subscriptions/transactions` | 200 | Loads. |
| `GET /checkout?plan=enterprise-plan-id` | 302 | Bug/compat issue: controller expects `plan_id`, not `plan`. |
| `GET /checkout/return` | 200 | Loads payment status page. |
| `GET /checkout/cancel` | 200 | Loads cancel page. |
| `GET /tokens` | 200 | Loads token dashboard. |
| `GET /tokens/history` | 200 | Loads history. |
| `GET /credits` | 301 | Legacy redirect to `/tokens`. |
| `GET /credits/history` | 301 | Legacy redirect to `/tokens/history`. |
| `GET /api-keys` | 200 | Listing loads from user-api. Create/revoke still broken, see bugs. |
| `GET /billing` | 200 | Loads invoices/payment methods. |
| `GET /billing/history` | 200 | Loads billing history. |
| `GET /api/v1/health` | 200 | Public API health works. |
| `GET /api/v1/balance/{org}` | 200 with fatal HTML | Bug: route parameter mismatch. |
| `GET /api/v1/entitlement/{org}/{product}` | 200 with fatal HTML | Bug: route parameter mismatch. |
| `GET /api/v1/products` | 401 | Requires API auth. |
| `GET /api/v1/plans` | 401 | Requires API auth. |

## API Routes Smoke-Tested

### user-api

All tested user-api reads passed:

- `GET /health`
- `GET /users/by-email?email=nomsa.khumalo@khumaloforensics.co.za`
- `GET /users/22222222-2222-2222-2222-222222222222/profile`
- `GET /organisations/11111111-1111-1111-1111-111111111111`
- `GET /organisations/11111111-1111-1111-1111-111111111111/members`
- `GET /api-keys/11111111-1111-1111-1111-111111111111`
- `POST /api-keys` created a test key successfully.

### operations-api

Core operations reads passed:

- `GET /health`
- `GET /plans/active`
- `GET /plans/slug/enterprise`
- `GET /products/active`
- `GET /subscriptions/organisation/{org}/active`
- `GET /credits/{org}`
- `GET /credits/{org}/balance`
- `GET /credits/{org}/transactions?limit=5`
- `GET /usage/{org}/trend?days=7`
- `GET /articles?limit=4`

Notes:

- `GET /credits/{org}/usage/trend?days=7` returns `[]`.
- `GET /usage/{org}/trend?days=7` returns numeric rows with ISO dates, suitable for charts.

### client-api

Core client reads and writes tested:

- `GET /health`
- `GET /invoices/organisation/{org}`
- `GET /payment-methods/organisation/{org}`
- `GET /payments/organisation/{org}`
- `GET /payment-transactions/organisation/{org}`
- `GET /payfast/logs`
- `POST /payment-methods` created a test card successfully.
- `POST /checkout/payfast` created a checkout with PayFast reference successfully.

Authorization/policy issues:

- `GET /payment-outbox-events/pending` returned `403 Route requires authorization policy`.
- The same route still returned 403 with `X-Test-User-Id`, `X-Test-Organisation-Id`, and `X-Test-Role=owner`.

## Bugs

### Critical: Product tool routes deny Enterprise users

Symptoms:

- `/products/upload-forensic-image`, `/products/ocr`, `/products/transcription`, `/products/bank-statements`, and `/products/file-comparison` return 403.

Cause:

- `src/Services/EntitlementService.php` calls `OPERATIONS_API_URL/entitlements/{org}/{product}`.
- `operations-api` has no `/entitlements/...` route.
- The 404/403 error payload is treated as a real entitlement response, so fallback logic never runs.

Fix:

- Either add `GET /entitlements/{org}/{product}` to operations-api, or make `EntitlementService::evaluateProductAccess()` ignore API error payloads and fall back to subscription + credits + product lookup.

### Critical: Business-layer API parameter routes fatal

Symptoms:

- `GET /api/v1/balance/{org}` returns status 200 with fatal HTML:
  `Unknown named parameter $org_id`.
- `GET /api/v1/entitlement/{org}/{product}` has the same issue.

Cause:

- `src/routes.php` uses `{org_id}` and `{product_slug}`.
- `src/Controllers/ApiController.php` expects `$orgId` and `$productSlug`.
- `src/Core/Router.php` passes named parameters directly to `call_user_func_array()`, so PHP treats snake_case names as unknown named parameters.

Fix:

- Rename route placeholders to `{orgId}` and `{productSlug}`, or change controller argument names to `$org_id` and `$product_slug`, or pass route params positionally.
- Also ensure fatal pages return 500 JSON, not 200 HTML.

### High: API key create/revoke/validate service mapping is still wrong

Symptoms:

- `/api-keys` listing loads because `ApiKeyService::getForOrganisation()` uses user-api.
- Create/revoke/validate/usage methods still point to client-api routes:
  - `POST CLIENT_API_URL/api-keys`
  - `POST CLIENT_API_URL/api-keys/{id}/revoke`
  - `POST CLIENT_API_URL/api-keys/validate`
  - `POST CLIENT_API_URL/api-keys/{id}/usage`

Cause:

- `src/Services/ApiKeyService.php` is only partially moved to user-api.
- client-api route map has no API-key routes.

Fix:

- Move all API-key service methods to user-api:
  - `POST /api-keys`
  - `PATCH /api-keys/{id}/revoke`
  - add/confirm validate and usage endpoints on user-api, or route those to operations-api if usage belongs there.

### High: Checkout route only accepts `plan_id`, not `plan`

Symptoms:

- `GET /checkout?plan=36f2a0fe-a4f2-4dd5-bb68-da6cc01ec367` returned 302.

Cause:

- `src/Controllers/CheckoutController.php` reads only `$_GET['plan_id']`.

Fix:

- Accept both `plan_id` and `plan`, or ensure every plan CTA sends `plan_id`.

### High: Client outbox pending route is not accessible

Symptoms:

- `GET /payment-outbox-events/pending` returns 403 even with test context headers.

Cause:

- `ClientAuthorizationPolicy::allowsInternalService()` includes the route, but normal/test requests do not satisfy the internal-service path in `Auth::bootstrap()`.

Fix:

- Decide how the frontend/outbox worker authenticates internal service requests and document the required header/token.
- Add this route to normal authorization if it is intended for owner/admin operators.

### Medium: Frontend API auth bypass does not apply to `/api/v1` routes

Symptoms:

- `GET /api/v1/products` and `GET /api/v1/plans` return 401 while `AUTH_BYPASS=true`.

Cause:

- `Middleware::apiAuth()` ignores `AUTH_BYPASS`.

Fix:

- If local development should use bypass, let `apiAuth()` return a test principal when `AUTH_BYPASS=true`, or document that `/api/v1` always requires `Authorization` or `X-API-Key`.

### Medium: PayFast ITN direct API endpoint trusts posted payload

Symptoms:

- `POST /checkout/payfast/itn` is exempt from client-api auth.
- Signature validation is done in the business-layer notify controller before forwarding, but the client-api route itself is public.

Risk:

- A direct caller could spoof an ITN unless client-api performs its own PayFast signature/source checks or only accepts internal calls.

Fix:

- Validate PayFast signature/source in client-api too, or require an internal service token on the forwarded API route.

### Medium: Chart data has two competing trend routes

Symptoms:

- `GET /credits/{org}/usage/trend?days=7` returns `[]`.
- `GET /usage/{org}/trend?days=7` returns populated numeric chart data.

Risk:

- Any UI path using the first endpoint will show an empty chart.

Fix:

- Standardize TokenService and docs on one trend endpoint.
- Keep chart rows shaped as `{ "date": "2026-06-01", "tokens_used": 1200 }`.

### Low: Local dev environment has production/session mismatch risk

Symptoms:

- `.env` has `APP_ENV=production` and `APP_DEBUG=true`.
- Local testing is done on `http://localhost:3001`, while `APP_URL` and PayFast URLs point to HTTPS ngrok.

Risk:

- Session cookie/security behavior can differ between localhost and ngrok.

Fix:

- Use a local `.env` profile for localhost or document that browser testing must use the ngrok URL.

## Test Data Created During Audit

- One payment method was created through `POST /payment-methods`.
- One API key was created through `POST /api-keys` on user-api.
- One PayFast checkout was created through `POST /checkout/payfast`.

These were created with audit/test labels where the target API accepted metadata.

## Recommended Next Fix Order

1. Fix `EntitlementService::evaluateProductAccess()` so Enterprise users can open product tools.
2. Fix `/api/v1/balance/{org}` and `/api/v1/entitlement/{org}/{product}` route parameter names.
3. Move all API-key write/validate/usage methods behind the correct user-api or operations-api endpoints.
4. Accept both `plan` and `plan_id` in checkout, or update all plan CTAs to use `plan_id`.
5. Decide and implement internal auth for payment outbox routes.
6. Standardize chart trend endpoint usage.
