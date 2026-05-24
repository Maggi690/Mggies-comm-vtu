# Universal VTU Pro — Backend API

Production-ready Laravel 12 backend for a Nigerian VTU (Virtual Top-Up) SaaS platform.

## Tech Stack

| Layer         | Technology                              |
|---------------|-----------------------------------------|
| Language      | PHP 8.4                                 |
| Framework     | Laravel 12                              |
| Database      | MySQL 8                                 |
| Cache/Queue   | Redis 7                                 |
| Auth          | Laravel Sanctum                         |
| Roles/Perms   | Spatie Laravel Permission               |
| Activity Logs | Spatie Laravel Activitylog              |
| Queue Workers | Supervisor + Laravel Horizon-compatible |
| API Docs      | OpenAPI 3.0 / Swagger + Postman         |

---

## Architecture

```
app/
├── Console/Commands/        # Artisan commands (reports, maintenance)
├── DTOs/                    # Data Transfer Objects (Auth, Wallet, Vtu)
├── Events/                  # Domain events (WalletCredited, AirtimePurchased)
├── Exceptions/              # Custom domain exceptions + global handler
├── Http/
│   ├── Controllers/
│   │   ├── Auth/            # Register, Login, PIN management
│   │   ├── User/            # Wallet, Airtime, Data, Cable, Electricity, Exam, Support
│   │   ├── Admin/           # Users, Providers, Transactions, Reports, Blacklist
│   │   ├── Api/V1/          # Developer API endpoints
│   │   └── Webhooks/        # Monnify, Paystack, Flutterwave, Developer
│   ├── Middleware/          # ApiKeyMiddleware, BlacklistMiddleware
│   ├── Requests/            # Form validation requests
│   └── Resources/           # API response transformers
├── Integrations/
│   ├── Monnify/             # Reserved accounts, webhook
│   ├── Paystack/            # Payments, webhook
│   ├── Flutterwave/         # Payments, webhook
│   ├── Paga/                # Virtual accounts
│   └── Providers/           # VTpass, Husmodata, Gsubz
├── Jobs/
│   ├── Vtu/                 # Airtime, Data, Cable, Electricity, Exam, Retry
│   ├── Webhook/             # DeliverWebhookJob (with retry/backoff)
│   └── Report/              # ExportReport, ExportTransactions
├── Listeners/               # Event handlers (notifications, commissions)
├── Models/                  # 25+ Eloquent models
├── Policies/                # Authorization policies
├── Providers/               # AppServiceProvider, EventServiceProvider
├── Repositories/            # Repository contracts
└── Services/
    ├── AuthService          # Registration, login, PIN management
    ├── Payment/             # PaymentService (multi-gateway)
    ├── Providers/           # ProviderRoutingService (smart failover)
    └── Wallet/              # WalletService (atomic double-entry ledger)
    └── Vtu/                 # AirtimeService, DataService, CableService, ElectricityService, ExamService
```

---

## Quick Start (Local Development)

```bash
# 1. Clone and install
git clone https://github.com/your-org/universal-vtu-pro.git
cd universal-vtu-pro
composer install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Configure .env (database, Redis, payment keys)
# DB_HOST, DB_DATABASE, REDIS_HOST, etc.

# 4. Database
php artisan migrate
php artisan db:seed

# 5. Serve
php artisan serve

# 6. Start queue workers (separate terminal)
php artisan queue:work redis --queue=vtu,notifications,webhooks,reports,default
```

---

## API Overview

### Base URL
```
https://api.universalvtupro.com/api
```

### Authentication
```
Authorization: Bearer <token>
```

### Key Endpoints

| Method | Endpoint                      | Description                  |
|--------|-------------------------------|------------------------------|
| POST   | `/auth/register`              | Register new user            |
| POST   | `/auth/login`                 | Login and get token          |
| POST   | `/auth/logout`                | Revoke token                 |
| POST   | `/user/set-pin`               | Set transaction PIN          |
| GET    | `/wallet/balance`             | Get wallet balance           |
| POST   | `/payments/initialize`        | Fund wallet via gateway      |
| POST   | `/payments/verify`            | Verify payment               |
| POST   | `/airtime/purchase`           | Buy airtime                  |
| GET    | `/airtime/status/{id}`        | Check airtime status         |
| GET    | `/data/plans`                 | List data plans              |
| POST   | `/data/purchase`              | Buy data bundle              |
| POST   | `/cable/validate`             | Verify smartcard             |
| POST   | `/cable/purchase`             | Subscribe cable TV           |
| POST   | `/electricity/validate`       | Verify meter                 |
| POST   | `/electricity/purchase`       | Buy electricity token        |
| POST   | `/exam/purchase`              | Buy exam scratch card        |
| POST   | `/support/ticket`             | Create support ticket        |
| GET    | `/api-keys`                   | List developer API keys      |
| POST   | `/api-keys`                   | Create API key pair          |

### Developer API v1

Uses `X-API-Key` + `X-API-Secret` headers (no Bearer token).

| Method | Endpoint                | Description           |
|--------|-------------------------|-----------------------|
| POST   | `/v1/airtime`           | Purchase airtime      |
| POST   | `/v1/data`              | Purchase data         |
| POST   | `/v1/cable`             | Cable subscription    |
| POST   | `/v1/electricity`       | Buy electricity       |
| POST   | `/v1/exam`              | Buy exam pin          |
| GET    | `/v1/balance`           | Get wallet balance    |
| GET    | `/v1/data-plans`        | List data plans       |
| GET    | `/v1/transaction/{ref}` | Query transaction     |

### Admin API

Requires admin/assistant_admin role. Uses Bearer token.

| Method | Endpoint                              | Description              |
|--------|---------------------------------------|--------------------------|
| GET    | `/admin/reports/dashboard`            | Dashboard stats          |
| GET    | `/admin/users`                        | List users (+ search)    |
| POST   | `/admin/users/{id}/suspend`           | Suspend user             |
| POST   | `/admin/users/{id}/credit-wallet`     | Credit user wallet       |
| GET    | `/admin/transactions`                 | List transactions        |
| POST   | `/admin/transactions/{id}/refund`     | Refund transaction       |
| GET    | `/admin/providers`                    | List VTU providers       |
| POST   | `/admin/providers`                    | Add new provider         |
| POST   | `/admin/providers/reorder`            | Set provider priority    |
| POST   | `/admin/blacklist/ip`                 | Blacklist IP             |
| GET    | `/admin/reports/revenue`              | Revenue report           |

### Webhook Endpoints (no auth — signature-validated)

| Method | Endpoint                      | Gateway      |
|--------|-------------------------------|--------------|
| POST   | `/webhooks/monnify`           | Monnify      |
| POST   | `/webhooks/paystack`          | Paystack     |
| POST   | `/webhooks/flutterwave`       | Flutterwave  |
| POST   | `/webhooks/developer/{key}`   | Developers   |

---

## Queue Channels

| Queue          | Purpose                          | Workers |
|----------------|----------------------------------|---------|
| `vtu`          | VTU purchases + retries          | 4       |
| `notifications`| Emails and SMS                   | 2       |
| `webhooks`     | Outbound developer webhooks      | 2       |
| `reports`      | CSV/Excel/PDF exports            | 1       |
| `default`      | Commissions, misc background     | 2       |

---

## Security

- Laravel Sanctum token authentication
- Role-based access control (Spatie Permission)
- IP blacklisting middleware
- API key IP whitelisting
- Transaction PIN (4-digit, bcrypt-hashed)
- Rate limiting on all routes
- Encrypted provider API keys (Laravel Crypt)
- Webhook signature validation (HMAC)
- Soft deletes on all major models
- Activity logging on sensitive operations

---

## Running Tests

```bash
# All tests
php artisan test

# Specific suites
php artisan test --testsuite=Unit
php artisan test --testsuite=Feature

# Specific test class
php artisan test tests/Feature/Wallet/WalletTest.php

# With coverage
php artisan test --coverage --min=80

# Parallel (faster)
php artisan test --parallel
```

---

## Documentation

| Document               | Location                      |
|------------------------|-------------------------------|
| OpenAPI / Swagger      | `openapi.yaml`                |
| Postman Collection     | `postman_collection.json`     |
| Deployment Guide       | `DEPLOYMENT.md`               |
| Supervisor Config      | `supervisor.conf`             |
| Nginx Config           | `nginx.conf`                  |

---

## License

Proprietary — Universal VTU Pro © 2024
