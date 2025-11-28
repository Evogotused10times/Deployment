<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();

            // Linked plot
            $table->foreignId('plot_id')
                ->constrained()
                ->cascadeOnDelete();

            // Who is reserving (application or walk-in)
            $table->string('reserved_by_name');
            $table->string('reserved_by_email')->nullable();
            $table->string('reserved_by_phone')->nullable();

            // Optional link to an application (if the reservation originated from one)
            $table->foreignId('application_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // Reservation lifecycle
            $table->enum('status', [
                'pending',
                'reserved',
                'confirmed',
                'cancelled',
                'expired'
            ])->default('reserved');

            $table->date('start_date')->nullable();
            $table->date('expires_at')->nullable(); // auto-expire unless confirmed/paid
            $table->text('notes')->nullable();

            $table->timestamps();

            // Prevent overlapping active reservations for same plot
            $table->index(['plot_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
