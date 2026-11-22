<?php

use App\Support\ReferenceData;
use Illuminate\Database\Migrations\Migration;

/**
 * The Giwa's role (22 Nov 2026 demo): read-only oversight plus restoring a
 * System Administrator's lost access. The Giwa changes only with palace
 * approval, so this account is the recovery path for the administrator.
 */
return new class extends Migration
{
    public function up(): void
    {
        ReferenceData::sync();
    }

    public function down(): void
    {
        // Reference data is additive; the role stays unassigned if rolled back.
    }
};
