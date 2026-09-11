<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Booking extends Model
{
    protected $fillable = [
        'vehicle_id',
        'customer_name',
        'start_date',
        'end_date',
        'status',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'cancelled_at' => 'datetime',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * Confirmed bookings that overlap the requested range, inclusive of both ends.
     * A vehicle returned on a date is not available again until the next day.
     */
    public function scopeOverlapping(Builder $query, string $start, string $end): Builder
    {
        return $query
            ->where('status', 'confirmed')
            ->where('start_date', '<=', $end)
            ->where('end_date', '>=', $start);
    }
}
