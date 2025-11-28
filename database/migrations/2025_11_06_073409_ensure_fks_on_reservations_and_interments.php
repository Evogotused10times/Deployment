<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /* ---------- helpers ---------- */
    protected function fkExists(string $table, string $column): bool
    {
        $db = DB::getDatabaseName();
        return (bool) DB::selectOne(
            "SELECT 1
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1",
            [$db, $table, $column]
        );
    }

    protected function fkName(string $table, string $column): ?string
    {
        $db = DB::getDatabaseName();
        $row = DB::selectOne(
            "SELECT CONSTRAINT_NAME
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL
             LIMIT 1",
            [$db, $table, $column]
        );
        return $row->CONSTRAINT_NAME ?? null;
    }

    public function up(): void
    {
        // reservations.application_id -> applications.id
        if (Schema::hasColumn('reservations', 'application_id') && !$this->fkExists('reservations', 'application_id')) {
            Schema::table('reservations', function (Blueprint $t) {
                $t->foreign('application_id')
                  ->references('id')->on('applications')
                  ->cascadeOnDelete();
            });
        }

        // interments.application_id -> applications.id
        if (Schema::hasColumn('interments', 'application_id') && !$this->fkExists('interments', 'application_id')) {
            Schema::table('interments', function (Blueprint $t) {
                $t->foreign('application_id')
                  ->references('id')->on('applications')
                  ->cascadeOnDelete();
            });
        }

        // interments.reservation_id -> reservations.id
        if (Schema::hasColumn('interments', 'reservation_id') && !$this->fkExists('interments', 'reservation_id')) {
            Schema::table('interments', function (Blueprint $t) {
                $t->foreign('reservation_id')
                  ->references('id')->on('reservations')
                  ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            ['interments','reservation_id'],
            ['interments','application_id'],
            ['reservations','application_id'],
        ] as [$table, $col]) {
            $name = $this->fkName($table, $col);
            if ($name) {
                DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$name}`");
            }
        }
    }
};
