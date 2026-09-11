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


## Key decisions

**`daily_rate` is stored as an integer in cents.** Floating point cannot
represent most decimal fractions exactly, so money maths on floats drifts.
Storing the smallest currency unit as an integer keeps every calculation exact
and rounds once at display time. This is the convention Laravel Cashier and
Stripe both use. The accessor on `Vehicle` divides by 100 on read and
multiplies on write, so the API returns a bare number (`65`) as the contract
requires. A `decimal` column would come back from PDO as the string `"65.00"`
and break the contract without a cast anyway. In production I would replace
the hand-written accessor with a money library such as `brick/money`, which
adds a currency code, immutable arithmetic and per-currency rounding rules,
and still stores the amount as integer minor units.

**`type` and `location` are plain strings, validated as strings only.** No
enum, no `in:` list, no lookup table. The brief says inventory may come from
an external source later, and a hardcoded list would need a deploy every time
that feed added a category. An unknown type returns an empty list, which is
the correct answer. The rental industry classifies vehicles with ACRISS codes
and locations with branch codes; in production both would be foreign keys.

**Booking dates are `date` columns, not `datetime`.** The contract only uses
`YYYY-MM-DD`, and pickup and return times are a depot concern, not a booking
one.

**`status` is a plain string with a default of `confirmed`.** Two values are
enough for this exercise. An enum class would be one more file for no
behaviour.

**A `start_date` in the past is rejected.** The availability endpoint is
public and customer-facing. Nobody hires a car for last week, so a past date
is a client bug and gets a 422 with a clear message. Note that the example
dates in the brief are now in the past, so use current dates when testing.

**No automated tests.** Everything was verified by hand in tinker and with
`curl` against a running server: the seeder, every validation rule, the
optional filters, the contract shape, and all seven overlap cases including
the one `whereBetween` misses. That fits the 3 to 4 hour budget for this
exercise. In real work I would write feature tests for each endpoint and the
overlap boundary cases (adjacent, exact, enclosed, enclosing, cancelled) so
the behaviour is proven on every change, not just once.

**No transaction or row lock on booking creation.** The controller checks
for an overlap and then inserts, as two plain statements. Two requests for the
same vehicle arriving at the same instant could both pass the check. In
production I would wrap the check and insert in a transaction with a
`lockForUpdate()` on the vehicle row so concurrent requests are serialised,
or look at a database-level exclusion constraint if the platform supports it.
A better customer experience would be a short hold: reserve the vehicle for
5 to 15 minutes once someone reaches the payment step, so a second customer
is told early that it is no longer available instead of failing at the last
click. That needs a `pending` status with an expiry and a job to release
stale holds. All of this rests on assumptions about traffic and checkout flow
that the brief does not make, so I left it out rather than build on a guess.

**One overlap query per vehicle.** The availability controller loads the
matching vehicles and asks each one whether it has a clashing booking. For a
fleet of five this is fine and keeps the overlap logic in one place. For a
large fleet the same scope would move into the main query with
`whereDoesntHave`.

## Overlap prevention

The check lives in one place, a query scope on `Booking`:

```php
public function scopeOverlapping(Builder $query, string $start, string $end): Builder
{
    return $query
        ->where('status', 'confirmed')
        ->where('start_date', '<=', $end)
        ->where('end_date', '>=', $start);
}
```

Two ranges overlap when each one starts before the other ends. That single
pair of comparisons catches every case: an exact match, a booking enclosed by
the request, a booking that encloses the request, and a partial overlap at
either end. Cancelled bookings are excluded by the status filter, so
cancelling a booking frees its dates immediately.

**Why not `whereBetween`.** The intuitive version asks whether either
endpoint of an existing booking falls inside the requested range. It misses
the enclosing case entirely: a booking from the 8th to the 18th has neither
endpoint inside a request for the 10th to the 15th, so the vehicle would be
handed to two customers at once. I verified this in tinker with seven
bookings covering every case before writing the scope.

**Same-day turnaround is not allowed.** Customers return late, and a returned
car needs to be cleaned before it goes out again. So the return date is
blocked and the next hire can start no earlier than the following day. This is
why the comparisons are inclusive (`<=` and `>=`). A one-night hire from the
10th to the 11th blocks both calendar days. If the business later wanted
same-day handover, the change is two operators and nothing else. The other
option is to store pickup and return times and let the depot decide the gap,
but that is a business decision. I would clarify the exact requirements with
Matt, Head of Software Engineering, and how turnaround actually works at the
branches before changing the schema.
