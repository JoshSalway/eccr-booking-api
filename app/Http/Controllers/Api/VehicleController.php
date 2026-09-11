<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AvailabilityRequest;
use App\Models\Vehicle;
use Illuminate\Http\JsonResponse;

class VehicleController extends Controller
{
    public function availability(AvailabilityRequest $request): JsonResponse
    {
        $query = Vehicle::query();

        if ($request->filled('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->filled('location')) {
            $query->where('location', $request->input('location'));
        }

        $results = [];

        foreach ($query->get() as $vehicle) {
            if ($vehicle->bookings()->overlapping($request->input('start_date'), $request->input('end_date'))->exists()) {
                continue;
            }

            $results[] = [
                'id' => $vehicle->id,
                'make' => $vehicle->make,
                'model' => $vehicle->model,
                'type' => $vehicle->type,
                'location' => $vehicle->location,
                'daily_rate' => $vehicle->daily_rate,
                'available' => true,
            ];
        }

        return response()->json($results);
    }
}
