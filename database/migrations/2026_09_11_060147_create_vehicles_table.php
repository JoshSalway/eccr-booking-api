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
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('make');
            $table->string('model');
            $table->string('type');
            $table->string('location');
            $table->unsignedInteger('daily_rate');
            $table->string('external_id')->nullable();
            $table->string('source')->nullable();
            $table->timestamps();

            $table->index(['type', 'location']);
        });
    }
};
