<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // When the application was finally decided (approve/deny)
            // $table->timestamp('decision_at')->nullable()->after('status');

            // Store privacy consent flag from the form
            $table->boolean('privacy_consent')->default(false)->after('terms');

            // Basic IA metadata
            $table->string('client_ip')->nullable()->after('privacy_consent');
            $table->string('user_agent', 500)->nullable()->after('client_ip');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['decision_at', 'privacy_consent', 'client_ip', 'user_agent']);
        });
    }
};
