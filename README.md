# MMS

Manufacturing Management System migrated from native PHP to Laravel while preserving the existing business flow and visual theme.

## Stack

- Laravel 13
- PHP 8.5
- Vite
- MySQL/MariaDB

## Current Migration Scope

Native Laravel modules include Administrator, Sales, Engineering, PPIC, Procurement, Warehouse Receiving, and QC Incoming. Remaining legacy modules are still available through the `legacy/` fallback while migration continues.

## Local Setup

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
npm run build
php artisan serve
```

Configure database credentials in `.env`.

## Verification

```bash
php artisan test
npm run build
```
