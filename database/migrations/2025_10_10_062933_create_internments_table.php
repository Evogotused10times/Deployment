<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('interments', function (Blueprint $table) {
            $table->id();

            // Links
            $table->foreignId('plot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();

            // When
            $table->dateTime('start_at');
            $table->dateTime('end_at');

            // Status
            $table->enum('status', ['scheduled','completed','cancelled'])
                  ->default('scheduled');

            // What
            $table->string('service_type')->nullable(); // Lawn Lot / Garden Lot / Mausoleum / Ossuary
            $table->string('rites')->nullable();        // Catholic / Protestant / etc.
            $table->string('officiant_name')->nullable();
            $table->string('officiant_contact')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();

            // Helpful indexes
            $table->index(['plot_id', 'start_at', 'end_at']);
            $table->index(['status']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('interments');
    }
};
