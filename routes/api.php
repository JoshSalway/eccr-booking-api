<?php

use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\VehicleController;
use Illuminate\Support\Facades\Route;

Route::get('/vehicles/availability', [VehicleController::class, 'availability']);
Route::post('/bookings', [BookingController::class, 'store'])->middleware('auth:sanctum');
Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->middleware('auth:sanctum');
