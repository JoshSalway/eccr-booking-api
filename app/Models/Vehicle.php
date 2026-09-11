<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    protected $fillable = [
        'make',
        'model',
        'type',
        'location',
        'daily_rate',
        'external_id',
        'source',
    ];

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    protected function dailyRate(): Attribute
    {
        return Attribute::make(
            get: fn (int $cents) => $cents / 100,
            set: fn (int|float $dollars) => (int) round($dollars * 100),
        );
    }
}
