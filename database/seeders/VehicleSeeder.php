<?php

namespace Database\Seeders;

use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        $vehicles = [
            [
                'make' => 'Toyota',
                'model' => 'Corolla',
                'type' => 'sedan',
                'location' => 'Southport',
                'daily_rate' => 65,
            ],
            [
                'make' => 'Mazda',
                'model' => '3',
                'type' => 'sedan',
                'location' => 'Surfers Paradise',
                'daily_rate' => 70,
            ],
            [
                'make' => 'Toyota',
                'model' => 'RAV4',
                'type' => 'suv',
                'location' => 'Surfers Paradise',
                'daily_rate' => 95,
            ],
            [
                'make' => 'Hyundai',
                'model' => 'Tucson',
                'type' => 'suv',
                'location' => 'Coolangatta',
                'daily_rate' => 90,
            ],
            [
                'make' => 'Kia',
                'model' => 'Cerato',
                'type' => 'sedan',
                'location' => 'Coolangatta',
                'daily_rate' => 60,
            ],
        ];

        foreach ($vehicles as $vehicle) {
            Vehicle::create($vehicle);
        }
    }
}
