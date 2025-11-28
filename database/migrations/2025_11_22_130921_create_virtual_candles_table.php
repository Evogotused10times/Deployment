<?php

// database/migrations/2025_01_01_000000_create_virtual_candles_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('virtual_candles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plot_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100)->nullable();      // name of the person lighting
            $table->string('message', 255)->nullable();   // short prayer / note
            $table->string('ip_hash', 64)->nullable();    // simple anti-abuse tracking
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('virtual_candles');
    }
};

