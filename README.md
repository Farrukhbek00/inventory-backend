# Inventory Backend

Simple inventory API built with Laravel and MySQL.

## What It Covers

- Providers, categories (parent-child), products
- Batch purchases into storage
- Client orders with FIFO batch assignment
- Purchase refunds and sale refunds
- Remaining quantity report by date
- Profit per batch report

## Stack

- PHP 8.3
- Laravel 13
- MySQL

## Quick Start

1. Install dependencies

```bash
composer install
```

2. Create env and app key

```bash
cp .env.example .env
php artisan key:generate
```

3. Set MySQL credentials in .env and run migrations

```bash
php artisan migrate
```

4. Run checks

```bash
php artisan test
composer analyse
```

## Main Endpoints

- POST /api/purchases
- POST /api/purchases/refunds
- GET /api/products/available
- POST /api/orders
- POST /api/orders/refunds
- GET /api/storages/remaining
- GET /api/batches/profit

## Notes

- Controllers are request/response only.
- Business logic is in services.
- Data access is in repositories.
