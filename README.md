# ECCR Backend Technical Assessment

A small Laravel API for checking vehicle availability and managing rental bookings.

Built with Laravel 13, PHP 8.4+, SQLite. API only, no frontend.

## Requirements

- PHP 8.4 or higher
- Composer
- SQLite

Please see https://laravel.com/framework/docs/installation for instructions on how to set up a local machine for development.

## Setup

```bash
git clone https://github.com/JoshSalway/eccr-booking-api.git
cd eccr-booking-api
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan serve
```

The API is then available at `http://localhost:8000`.

To process queued jobs, run a worker in a second terminal:

```bash
php artisan queue:work
```

## Endpoints

| Method | Path | Auth |
| --- | --- | --- |
| GET | `/api/vehicles/availability` | Public |
| POST | `/api/bookings` | Sanctum token |
| DELETE | `/api/bookings/{id}` | Sanctum token |

## Schema

### Vehicles

| Column | Type | Key |
| --- | --- | --- |
| id | bigint unsigned | PK |
| make | string | |
| model | string | |
| type | string | |
| location | string | |
| daily_rate | int | |
| external_id | string null | |
| source | string null | |
| created_at | timestamp | |
| updated_at | timestamp | |

Index: `(type, location)`

### Bookings

| Column | Type | Key |
| --- | --- | --- |
| id | bigint unsigned | PK |
| vehicle_id | bigint unsigned | FK to vehicles.id |
| customer_name | string | |
| start_date | date | |
| end_date | date | |
| status | string, default confirmed | |
| cancelled_at | timestamp null | |
| created_at | timestamp | |
| updated_at | timestamp | |

Index: `(vehicle_id, status, start_date)`

### Relationships

One vehicle has many bookings. Each booking belongs to exactly one vehicle,
recorded in `bookings.vehicle_id`, which references `vehicles.id`. The foreign
key uses `restrictOnDelete`, so a vehicle with any bookings cannot be deleted.

Bookings are not linked to a user. Any authenticated token can act on any
booking. This is a deliberate simplification for the exercise; in production
`bookings` would carry a `user_id` and cancellation would be scoped to the
owner or an admin user etc.


## AI usage

I used Claude as a reference and a sounding board rather than a code generator.

**Where I used it:** talking through the interval-overlap approach before
implementing it, including why the common `whereBetween` pattern misses the
enclosing case; checking current Laravel 13 conventions, since I'd been working
outside the PHP ecosystem recently; and reviewing my schema decisions,
particularly the composite index column order and the money-as-integer choice.

**What I wrote myself:** all application code. The migrations, models,
controllers, form requests, the `AvailabilityService` overlap logic, the
transaction and row lock, the queued job and its failure handling, the tests,
and this README.

**What I deliberately didn't use:** Laravel Boost, which ships in the current
installation docs and gives agents version-matched Laravel context. It would
have written much of this for me, which defeats the point of the exercise.
