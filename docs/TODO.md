# ECCR Backend Assessment — Task List

Due: Sat 12 Sep. Budget 3–4 hours.
Repo: https://github.com/JoshSalway/eccr-booking-api

Commit after every task. Write decisions into README.md as you make them.

---

## 0. Setup

- [x] Laravel 13 installed, PHP 8.5
- [x] `.env` created, key generated, `QUEUE_CONNECTION=database`
- [x] Public GitHub repo
- [x] README skeleton: what this is, setup, schema, AI usage
- [x] Assessment brief committed to `docs/`

---

## 1. Migrations and models

- [x] Vehicles and bookings migrations, FK, composite indexes
- [x] Models: `$fillable`, casts, `belongsTo` / `hasMany`
- [x] `daily_rate` stored as cents, accessor returns dollars (bare `65` in JSON)
- [x] `status` is a plain string, default `confirmed`
- [x] Nullable `external_id` + `source` on vehicles for future inventory feed

---

## 2. Vehicle seeder

- [x] `VehicleSeeder` — 5 vehicles across Southport, Surfers Paradise, Coolangatta; sedan and suv
- [x] Call from `DatabaseSeeder`
- [x] `php artisan migrate:fresh --seed`

---

## 3. Availability endpoint

`GET /api/vehicles/availability?start_date=&end_date=&type=&location=`

Decide first:
- [x] Reject `start_date` in the past? Yes, customer-facing. Noted in README
- [x] `type` / `location` as free strings, no `in:` list (external inventory may add types)

Then build, in order:
- [x] `php artisan make:controller Api/VehicleController`
- [x] `php artisan make:request AvailabilityRequest`
- [x] Rules: both dates required, `Y-m-d`, `end_date` after `start_date`
- [x] `scopeOverlapping()` stub on `Booking` (body written by hand in step 4)
- [x] Controller: `if` blocks for optional `type` and `location`, skip vehicles with an overlapping booking
- [x] Return exact contract shape, including `"available": true` on every row
- [x] Route in `routes/api.php`, public

Verify:
- [x] `curl` it: missing dates, past start, end before start, bad format → 422
- [x] `curl` it after step 4: 200 with all 5 vehicles, filters narrow it

---

## 4. Overlap logic

The named core criterion. Do not write this straight into a controller.

Spike:
- [x] `php artisan tinker`, create 4 bookings by hand on one vehicle
- [x] Verify predicate: `start_date < requested_end AND end_date > requested_start`
- [x] Check all four cases: adjacent, exact, enclosed, enclosing
- [x] Confirm `whereBetween` would MISS the enclosing case (know why)

Decide:
- [x] Same-day turnaround: NOT allowed. Return day is blocked for cleaning; next pickup is the day after. Predicate is inclusive (`<=` / `>=`)
- [x] Write the reasoning into the README

Build:
- [x] Write the body of `scopeOverlapping()`, filtering on `status = confirmed` so cancelled bookings free up dates

Verify:
- [x] Availability endpoint now hides the booked vehicle for overlapping dates

---

## 5. Booking creation

`POST /api/bookings`

Build, in order:
- [x] `php artisan make:controller Api/BookingController`
- [x] `php artisan make:request StoreBookingRequest` — same date rules, `vehicle_id` `exists:vehicles,id`, `customer_name` required string
- [x] `store()`: find vehicle, `overlapping()->exists()`, then create. No transaction or lock, noted in README
- [x] Conflict → 409 with exact `vehicle_unavailable` shape (NOT in the form request, or you get 422)
- [x] Success response: `booking_id`, `status`, `vehicle_id`, `start_date`, `end_date`. Nothing else.
- [x] Route in `routes/api.php` (auth added in step 7)

Verify:
- [x] Book a vehicle → 201, book it again for the same dates → 409, unknown `vehicle_id` → 422 not a FK 500

---

## 6. Cancellation and queued job

`DELETE /api/bookings/{id}`

Decide:
- [x] Cancelling an already-cancelled booking → idempotent 200, same body, no second job. Note in README

Build, in order:
- [x] `failed_jobs` table already exists in the stock `create_jobs_table` migration, nothing to add
- [x] `php artisan make:job SendCancellationNotification`
- [x] Job: `ShouldQueue`, `#[Tries(3)]`, `#[Backoff(10)]`, log in `handle()`, `failed(Throwable $e)` logs booking id + exception
- [x] `destroy()`: set `status = cancelled`, `cancelled_at = now()`, dispatch job
- [x] Return 200 with `{"booking_id": id, "status": "cancelled"}`, not 204
- [x] Route in `routes/api.php` (auth added in step 7)

Verify:
- [x] Unknown `{id}` → 404 JSON (route model binding + `shouldRenderJsonWhen` already handle this)
- [x] Run `php artisan queue:work`, fire the DELETE, watch it process
- [x] Deliberately throw inside `handle()`, 3 attempts 10s apart, lands in `failed_jobs`, `failed()` logged, throw removed

---

## 7. Sanctum auth

- [x] `php artisan install:api` (committed)
- [x] Read ONLY the API Token section of the Sanctum docs
- [x] `HasApiTokens` trait on `User`
- [x] Seed test user in `DatabaseSeeder` with a fixed token `1|eccr-assessment-token`, echo it, documented in README
- [x] `auth:sanctum` on POST and DELETE bookings only. Availability stays public.

Verify:
- [x] POST without token → 401, with token → 201, availability with no token → 200

---

## 8. Finish the README

Weighted equal to the code. Cover:

- [x] Overlap-prevention approach, including same-day turnaround reasoning
- [x] Why availability is public and bookings are protected
- [x] Any token can cancel any booking; would scope to owner in production
- [x] Queue driver choice and why
- [x] Failed job handling (retries, `failed()`, logging)
- [x] Trade-offs and shortcuts
- [x] Verify install steps by cloning fresh and following them
- [x] AI usage notes, written last

---

## 9. Criteria check

Brief's evaluation criteria, where each is proven:

- [x] Route/controller structure and Eloquent usage → `routes/api.php`, two thin controllers, form requests, query scope
- [x] Migration and schema quality → migrations, README schema section
- [x] Overlap-prevention correctness → `Booking::scopeOverlapping()`, tinker spike, README reasoning
- [x] Auth judgement → `auth:sanctum` on POST/DELETE only, README "why availability is public"
- [x] Validation and error handling → 422 on bad input, 409 on conflict, 404 on unknown booking, 401 without token
- [x] Contract diff with `curl`: `daily_rate` bare number, dates `Y-m-d`, `booking_id` not `id`, no extra fields, exact error strings, DELETE returns a body
- [x] README quality → section 8
- [x] Queue usage → `SendCancellationNotification`, `#[Tries]`, `#[Backoff]`, `failed()`, README driver reasoning

