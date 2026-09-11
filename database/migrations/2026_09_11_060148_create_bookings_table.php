<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->restrictOnDelete();
            $table->string('customer_name');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status')->default('confirmed');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['vehicle_id', 'status', 'start_date']);
        });
    }
};
