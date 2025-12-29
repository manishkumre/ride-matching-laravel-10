# Ride Matching System (Laravel 10)

## Features
- Passenger & Driver roles
- Real-time driver location (Redis)
- Ride request & assignment
- PostGIS distance matching
- Auto-cancel jobs
- Accept / Reject / Reassign flow

## Tech Stack
- Laravel 10
- PostgreSQL + PostGIS
- Redis
- Sanctum Auth

## Setup
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
