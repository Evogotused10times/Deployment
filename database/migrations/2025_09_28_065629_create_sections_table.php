<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();  // e.g. "A", "B1"
            $table->string('name')->nullable();    // e.g. "Section A"
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('sections');
    }
};
