<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_unique_section_lot_index.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('plots', function (Blueprint $table) {
            $table->unique(['section_id', 'lot_number'], 'plots_section_lot_unique');
        });
    }
    public function down(): void {
        Schema::table('plots', function (Blueprint $table) {
            $table->dropUnique('plots_section_lot_unique');
        });
    }
};

