<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Jobs\SendCancellationNotification;
use App\Models\Booking;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;

class BookingController extends Controller
{
    public function store(StoreBookingRequest $request): JsonResponse
    {
        $vehicle = Vehicle::find($request->input('vehicle_id'));

        if ($vehicle->bookings()->overlapping($request->input('start_date'), $request->input('end_date'))->exists()) {
            return response()->json([
                'error' => 'vehicle_unavailable',
                'message' => 'This vehicle is already booked for the requested dates',
            ], 409);
        }

        $booking = $vehicle->bookings()->create([
            'customer_name' => $request->input('customer_name'),
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'status' => 'confirmed',
        ]);

        return response()->json([
            'booking_id' => $booking->id,
            'status' => $booking->status,
            'vehicle_id' => $booking->vehicle_id,
            'start_date' => $booking->start_date->format('Y-m-d'),
            'end_date' => $booking->end_date->format('Y-m-d'),
        ], 201);
    }

    public function destroy(Booking $booking): JsonResponse
    {
        // Cancelling twice is harmless: the second call returns the same
        // response without re-sending the notification.
        if ($booking->status !== 'cancelled') {
            $booking->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
            ]);

            SendCancellationNotification::dispatch($booking);
        }

        return response()->json([
            'booking_id' => $booking->id,
            'status' => 'cancelled',
        ]);
    }
}
