<?php

use App\Support\IntegrityTriggers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Installs the database-level safeguards described in
 * docs/05-database-integrity-safeguards.md. They protect the ledger even
 * from direct SQL that bypasses the application.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (IntegrityTriggers::definitions() as $name => $sql) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
            DB::unprepared($sql);
        }
    }

    public function down(): void
    {
        foreach (array_keys(IntegrityTriggers::definitions()) as $name) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$name}");
        }
    }
};
