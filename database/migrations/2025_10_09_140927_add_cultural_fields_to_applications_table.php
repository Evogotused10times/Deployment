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
    Schema::table('applications', function (Blueprint $table) {
        $table->boolean('allow_auto_assign')->nullable()->after('service_type');
        $table->string('selection_mode')->nullable()->after('allow_auto_assign');
        $table->string('family_reference')->nullable()->after('selection_mode');
        $table->string('rites')->nullable()->after('family_reference');
        $table->string('wake_pref')->nullable()->after('rites');
        $table->string('interment_window')->nullable()->after('wake_pref');
        $table->text('rites_notes')->nullable()->after('interment_window');
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            //
        });
    }
};
