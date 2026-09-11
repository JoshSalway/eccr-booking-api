<?php

namespace App\Jobs;

use App\Models\Booking;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Tries(3)]
#[Backoff(10)]
class SendCancellationNotification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Booking $booking) {}

    /**
     * Simulates sending the cancellation notification. A real implementation
     * would send an email or SMS here.
     */
    public function handle(): void
    {
        Log::info('Cancellation notification sent', [
            'booking_id' => $this->booking->id,
            'customer_name' => $this->booking->customer_name,
            'vehicle_id' => $this->booking->vehicle_id,
        ]);
    }

    /**
     * Called once all retries are exhausted.
     */
    public function failed(Throwable $e): void
    {
        Log::error('Cancellation notification failed', [
            'booking_id' => $this->booking->id,
            'exception' => $e->getMessage(),
        ]);
    }
}
