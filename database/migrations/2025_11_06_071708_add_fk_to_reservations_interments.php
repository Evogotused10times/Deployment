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
        // RESERVATIONS.application_id  (create only if missing)
        if (!Schema::hasColumn('reservations', 'application_id')) {
            Schema::table('reservations', function (Blueprint $t) {
                // keep nullable to avoid breaking existing rows; you can enforce later in app rules
                $t->foreignId('application_id')
                  ->nullable()
                  ->after('id')
                  ->constrained()
                  ->cascadeOnDelete();
            });
        }

        // INTERMENTS.application_id (create only if missing)
        if (!Schema::hasColumn('interments', 'application_id')) {
            Schema::table('interments', function (Blueprint $t) {
                $t->foreignId('application_id')
                  ->nullable()
                  ->after('id')
                  ->constrained()
                  ->cascadeOnDelete();
            });
        }

        // INTERMENTS.reservation_id (create only if missing)
        if (!Schema::hasColumn('interments', 'reservation_id')) {
            Schema::table('interments', function (Blueprint $t) {
                $t->foreignId('reservation_id')
                  ->nullable()
                  ->constrained()
                  ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     * (Best-effort: drop FKs first, then columns if present)
     */
    public function down(): void
    {
        // INTERMENTS: drop FKs then columns (if exist)
        Schema::table('interments', function (Blueprint $t) {
            // Drop FKs if present (names are auto-guessed by Laravel; dropping inline is fine)
            if (Schema::hasColumn('interments', 'reservation_id')) {
                try { $t->dropForeign(['reservation_id']); } catch (\Throwable $e) {}
                try { $t->dropColumn('reservation_id'); } catch (\Throwable $e) {}
            }
            if (Schema::hasColumn('interments', 'application_id')) {
                try { $t->dropForeign(['application_id']); } catch (\Throwable $e) {}
                try { $t->dropColumn('application_id'); } catch (\Throwable $e) {}
            }
        });

        // RESERVATIONS
        Schema::table('reservations', function (Blueprint $t) {
            if (Schema::hasColumn('reservations', 'application_id')) {
                try { $t->dropForeign(['application_id']); } catch (\Throwable $e) {}
                try { $t->dropColumn('application_id'); } catch (\Throwable $e) {}
            }
        });
    }
};
