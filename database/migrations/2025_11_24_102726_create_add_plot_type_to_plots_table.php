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
        Schema::table('plots', function (Blueprint $table) {
            // Basic type marker: 'lawn', 'garden', 'mausoleum', 'vault', etc.
            $table->string('plot_type')->nullable()->index()->after('status');

            // OPTIONAL: If you also want to persist service_type
            // $table->string('service_type')->nullable()->after('plot_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plots', function (Blueprint $table) {
            $table->dropColumn('plot_type');

            // OPTIONAL: if added above
            // $table->dropColumn('service_type');
        });
    }
};
