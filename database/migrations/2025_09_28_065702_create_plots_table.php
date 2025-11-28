<?php

// database/migrations/2025_01_01_000002_create_plots_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('plots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained()->cascadeOnDelete();
            $table->string('lot_number')->index();  // e.g. "L-12"
            $table->string('block_level')->nullable();
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->enum('status', ['vacant','reserved','occupied'])->default('vacant');
            $table->string('occupant_name')->nullable();
            $table->string('occupant_contact')->nullable();
            $table->longText('geojson')->nullable(); // store polygon / point as GeoJSON
            $table->timestamps();
            $table->unique(['section_id','lot_number']);
        });
    }

    public function down() {
        Schema::dropIfExists('plots');
    }
};
