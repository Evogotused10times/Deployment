<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('applications', function (Blueprint $table) {
    // drop the old index by its name
    $table->dropIndex('applications_applicant_email_index');

    // re-create with a new name or type
    $table->index('applicant_email', 'apps_email_idx');
});

    }
    public function down(): void {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex(['applicant_email']);
        });
    }
};

