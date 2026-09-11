# ECCR Backend Technical Assessment
 
A small Laravel API for checking vehicle availability and managing rental bookings.
 
Built with Laravel 13, PHP 8.4+, SQLite. API only, no frontend.
 
## Requirements
 
- PHP 8.4 or higher
- Composer
- SQLite

Please see https://laravel.com/framework/docs/installation for instructions on how to set up local machine for development.

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
| POST | `/api/bookings` | Private protected with Sanctum Auth Token |
| DELETE | `/api/bookings/{id}` | Private protected with Sanctum Auth Token |



