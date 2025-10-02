
# vending-machine
Full vending machine simulation: customer purchases and administrative management. Vue.js frontend + Symfony API backend. Docker + MongoDB + Redis.


## Getting Started
1. Clone the repository and copy environment variables:
   ```bash
   git clone git@github.com:CarlosEstebanCasado/vending-machine.git
   cd vending-machine
   cp .env.dist .env
   ```
2. Add the local domain entry (run with elevated permissions):
   - macOS/Linux:
     ```bash
     sudo ./scripts/setup-host-entry.sh
     ```
   - Windows (PowerShell as Administrator):
     ```powershell
     ./scripts/setup-host-entry.ps1
     ```
3. Install backend dependencies (runs inside Docker):
   ```bash
   docker compose --profile dev run --rm backend composer install
   ```
4. Install frontend dependencies (runs inside Docker):
   ```bash
   docker compose --profile dev run --rm frontend npm install
   ```
5. Launch the development stack with Docker (gateway proxy, Symfony, Vite, MongoDB, Redis):
   ```bash
   docker compose --profile dev up --build -d
   ```
6. Access the services:
   - SPA: http://vendingmachine.test
   - API: http://vendingmachine.test/api/ping

7. Stop the stack:
   ```bash
   docker compose --profile dev down
   ```

**Daily workflow**
1. Start the stack: `docker compose --profile dev up -d`
2. Check logs (optional): `docker compose logs -f gateway`
3. Stop when done: `docker compose --profile dev down`

## Use Cases
- Insert accepted coins, display current balance, cancel the operation with coin return, or complete the purchase receiving change.
- Browse the customer-visible catalog: availability, pricing, and stock/change alerts.
- Authenticate as an administrator and access a dashboard with key metrics (stock levels, coin reserves, sales summary).
- Manage the catalog as an admin: create, update, deactivate products, adjust pricing, and per-slot limits.
- Restock products, update change reserves, log manual cash deposits or withdrawals.
- Review transaction history (sales, refunds, adjustments) with filters by date, product, or user.
- Switch to service mode to temporarily disable vending, register maintenance, and synchronize critical changes.
- Run scheduled or automated tasks (reconciliations, report generation, session/log cleanup).

## Local Quality Checks
- Run backend quality checks inside Docker: `make backend-ci`
- Mirror the GitHub Actions flow with host tooling: `./scripts/run-backend-ci.sh`

## Domain Models
- `Product`: identity, name, price, status, slot metadata, inventory counts; emits restock/out-of-stock events.
- `Money` and derivatives (`Coin`, `MoneyBalance`, `CoinBundle`): value objects representing accepted denominations and safe arithmetic.
- `Inventory`/`Slot`: binds products to physical compartments, tracking max capacity and current quantity. The `restock_threshold` marks the quantity at which the slot should be considered low on product so operations can schedule a replenishment before it runs out.
- `CoinInventory`: aggregate that maintains per-denomination counts and change-dispense/restock rules.
- `VendingSession`: tracks the ongoing interaction (inserted coins, selected product, transactional state); persisted from the first user action, closed with `completed`, `cancelled`, or `timeout`, linked to the resulting `Transaction`, and purged only through retention policies.
- `Transaction`: aggregate for completed operations (sales, refunds, admin adjustments) with audit metadata.
- `AdminUser`: aggregate for authenticated console accounts with credentials, permissions, and active token/session linkage.
- `MachineState`: projection summarizing global machine status (stock, change, operational flags) for quick reads.
- `MaintenanceLog`: record of service interventions, notes, and manual reconciliations.
- `AuthToken`/`RefreshToken`: models managing authentication lifecycle and revocation.
- Adapters/DTOs (MongoDB document mappers, API payloads, event payloads) bridging the domain with infrastructure.

## Security & Resilience
- Centralize secrets in environment variables (backed by `.env.dist`) and define JWT/refresh key rotation.
- Enforce rate limiting, input validation, and strict CORS policies on public API endpoints.
- Log security-sensitive events (admin logins, configuration changes) with traceability and basic alerting.
- Handle backend/Mongo outages gracefully (timeouts, controlled retries, clear frontend messaging).
- Configure automatic expiration of vending sessions and ensure coin returns on errors.

## Internationalization
- Prepare the frontend with i18n infrastructure (base translation files, currency/number helpers).
- Avoid hardcoded strings in backend responses to ease locale/currency adjustments.
- Document how to add new locales and keep translation keys aligned across frontend and backend.

## MongoDB Schema
All monetary amounts are stored in cents (`int32`) to avoid precision issues.

### Collection: `products`
- `_id` (`ObjectId`)
- `sku` (`string`) — external unique identifier
- `name` (`string`)
- `price_cents` (`int32`)
- `status` (`string`) — `active`, `inactive`
- `recommended_slot_quantity` (`int32`) — target stock per slot when planning inventory
- `created_at` / `updated_at` (`Date`)
- Indexes: `{ sku: 1 }` unique, `{ status: 1 }`

### Collection: `inventory_slots`
- `_id` (`ObjectId`)
- `slot_code` (`string`) — physical label (e.g., "11")
- `product_id` (`ObjectId|null`) — current product reference
- `capacity` (`int32`)
- `quantity` (`int32`)
- `restock_threshold` (`int32`)
- `status` (`string`) — `available`, `reserved`, `disabled`
- `updated_at` (`Date`)
- Indexes: `{ slot_code: 1 }` unique, `{ product_id: 1 }`

### Collection: `coin_reserves`
- `_id` (`ObjectId`)
- `denomination_cents` (`int32`) — 5, 10, 25, 100
- `quantity` (`int32`)
- `reserved_quantity` (`int32`) — coins allocated for pending change
- `updated_at` (`Date`)
- Indexes: `{ denomination_cents: 1 }` unique

### Collection: `vending_sessions`
- `_id` (`ObjectId`)
- `state` (`string`) — `collecting`, `ready`, `dispensing`, `cancelled`, `timeout`
- `inserted_coins` (array of `{denomination_cents:int32, quantity:int32}`)
- `balance_cents` (`int32`)
- `selected_product_id` (`ObjectId|null`)
- `change_plan` (array of `{denomination_cents:int32, quantity:int32}`)
- `result_transaction_id` (`ObjectId|null`)
- `started_at` (`Date`)
- `updated_at` / `closed_at` (`Date|null`)
- `close_reason` (`string|null`)
- Indexes: `{ state: 1, started_at: -1 }`, optional TTL on `closed_at`

### Collection: `transactions`
- `_id` (`ObjectId`)
- `type` (`string`) — `vend`, `return`, `restock`, `adjustment`
- `session_id` (`ObjectId|null`)
- `items` (array of `{product_id:ObjectId, quantity:int32, unit_price_cents:int32}`)
- `total_paid_cents` (`int32`)
- `change_dispensed` (array of `{denomination_cents:int32, quantity:int32}`)
- `admin_user_id` (`ObjectId|null`)
- `status` (`string`) — `completed`, `failed`
- `metadata` (`object`) — additional info (tracking, notes)
- `created_at` (`Date`)
- Indexes: `{ type: 1, created_at: -1 }`, `{ session_id: 1 }`, `{ admin_user_id: 1 }`

### Collection: `admin_users`
- `_id` (`ObjectId`)
- `email` (`string`)
- `password_hash` (`string`)
- `roles` (array of `string`) — default `['admin']`
- `status` (`string`) — `active`, `suspended`
- `last_login_at` (`Date|null`)
- `created_at` / `updated_at` (`Date`)
- Indexes: `{ email: 1 }` unique, `{ status: 1 }`

### Collection: `auth_tokens`
- `_id` (`ObjectId`)
- `admin_user_id` (`ObjectId`)
- `token_hash` (`string`)
- `type` (`string`) — `access`, `refresh`
- `issued_at` (`Date`)
- `expires_at` (`Date`)
- `revoked_at` (`Date|null`)
- Indexes: `{ admin_user_id: 1 }`, `{ token_hash: 1 }` unique, `{ expires_at: 1 }`

### Collection: `maintenance_logs`
- `_id` (`ObjectId`)
- `admin_user_id` (`ObjectId`)
- `entry_type` (`string`) — `maintenance`, `reconciliation`, `note`
- `description` (`string`)
- `attachments` (array of `string`)
- `created_at` (`Date`)
- Indexes: `{ admin_user_id: 1, created_at: -1 }`

### Infra Collection: `audit_logs`
- `_id` (`ObjectId`)
- `event` (`string`) — `admin.login`, `config.update`, `cash.adjust`
- `actor_id` (`ObjectId|null`)
- `session_id` (`ObjectId|null`)
- `payload` (`object`)
- `ip_address` (`string|null`)
- `user_agent` (`string|null`)
- `created_at` (`Date`)
- Indexes: `{ event: 1, created_at: -1 }`, `{ actor_id: 1, created_at: -1 }`

## Docker Tooling
- `docker-compose.yml` launches the development stack (gateway proxy on port 80, Symfony dev server, Vite dev server, MongoDB, Redis) via `docker compose --profile dev up --build -d`. Before the first `up`, install dependencies with `docker compose --profile dev run --rm backend composer install` and `docker compose --profile dev run --rm frontend npm install`.
- `docker-compose.prod.yml` produces production images: a PHP 8.4 runtime serving the API on port `8080`, an Nginx-based SPA container, plus MongoDB and Redis. Start with `docker compose -f docker-compose.prod.yml up --build -d`.
- Backend and frontend images are multi-stage (`docker/backend/Dockerfile`, `docker/frontend/Dockerfile`) so CI can build optimized artifacts (`backend-prod`, `frontend-prod`).
- `.dockerignore` prevents host `vendor/`, `node_modules/`, and Git metadata from bloating the build context.
