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

The API is then available at `http://localhost:8000`. The seeder prints the
API token at the end of `migrate --seed`; see Authentication below.

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

## Authentication

Booking creation and cancellation require a Sanctum bearer token. Availability
is public, because a customer searches before they have signed in. There is no
registration or login flow; the seeder creates one test user and one token.

The token is the same on every fresh install so it can be copied from here:

```
Authorization: Bearer 1|nib1AjgQH55Gri1Ywd3YSn1JFiSFUWDW65XTnJAN
```

Sanctum stores only the SHA-256 hash of a token, so the seeder writes the hash
of a fixed string rather than calling `createToken()`, which would generate a
random one. In production tokens are random and shown once. Any valid token
can cancel any booking, since bookings are not linked to a user; see Schema.

## Trying it out

Two terminals. In the first, set up and serve:

```bash
php artisan migrate:fresh --seed
php artisan serve
```

The last line of the seed output is the token. In the second terminal, save
the token and a booking body so you do not retype them:

```bash
T='1|nib1AjgQH55Gri1Ywd3YSn1JFiSFUWDW65XTnJAN'
B='{"vehicle_id":1,"customer_name":"Ann","start_date":"2026-10-20","end_date":"2026-10-22"}'
```

Use dates from today onwards; past dates are rejected. Then run these in order.
Add `-w '\n%{http_code}\n'` to any curl to see the status code. The codes you
will see:

| Code | Meaning | When |
| --- | --- | --- |
| 200 | OK | Availability list, cancellation |
| 201 | Created | Booking made |
| 401 | Unauthenticated | Missing or wrong token on a protected route |
| 404 | Not found | Cancelling a booking id that does not exist |
| 409 | Conflict | Vehicle already booked for those dates |
| 422 | Unprocessable | Validation failed: bad date, unknown vehicle, missing field |

**1. Availability is public.** No token needed. Returns all five vehicles with `200 OK`.

```bash
curl "http://localhost:8000/api/vehicles/availability?start_date=2026-10-20&end_date=2026-10-22"
```

**2. Booking without a token is refused.** `401 Unauthenticated`, before the controller runs.

```bash
curl -X POST http://localhost:8000/api/bookings \
  -H 'Accept: application/json' -H 'Content-Type: application/json' -d "$B"
# {"message":"Unauthenticated."}
```

**3. Booking with the token works.** `201 Created`, with the booking.

```bash
curl -X POST http://localhost:8000/api/bookings \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -H "Authorization: Bearer $T" -d "$B"
# {"booking_id":1,"status":"confirmed","vehicle_id":1,"start_date":"2026-10-20","end_date":"2026-10-22"}
```

**4. The same dates again are refused.** `409 Conflict`, the vehicle is taken.
Vehicle 1 also disappears from step 1 for those dates.

```bash
# repeat step 3
# {"error":"vehicle_unavailable","message":"This vehicle is already booked for the requested dates"}
```

**5. Cancel it.** `200 OK`. Cancelling again returns the same body, still 200.

```bash
curl -X DELETE http://localhost:8000/api/bookings/1 \
  -H 'Accept: application/json' -H "Authorization: Bearer $T"
# {"booking_id":1,"status":"cancelled"}
```

**6. The dates are free again.** Repeat step 3 and it returns `201 Created`
with a new `booking_id`.

**7. Process the notification job.** The DELETE queued a job. Start a worker
and watch it pick the job up, then check the log. The worker keeps running
until you press Ctrl+C.

```bash
php artisan queue:work
# App\Jobs\SendCancellationNotification ... DONE

tail -1 storage/logs/laravel.log
# local.INFO: Cancellation notification sent {"booking_id":1,"customer_name":"Ann","vehicle_id":1}
```

Send `Accept: application/json` on every request. Without it Laravel treats a
401 as a browser request and tries to redirect to a login page that does not
exist.

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
the one `whereBetween` misses. 

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

## Queue and the cancellation job

Cancelling dispatches `SendCancellationNotification`, which logs a line in
place of a real email or SMS.

Database driver so the job runs outside the request and a failure is visible
in `failed_jobs`; sync would hide it. A failing job retries three times ten
seconds apart, then `failed()` logs the booking id and exception.

## AI usage

I worked through this the way I would have before AI, with a task list and
one commit per task, and used Claude Code where I would previously have
been Googling: checking current Laravel 13 syntax, talking through options
before choosing one, and getting first drafts of files to work from. Each
piece was then tested in tinker and with curl and thought through against
the scope of work before it went in. The decisions in this README are mine.
AI helped with the writing, and I put as much of my own thinking into the
task as I could.
