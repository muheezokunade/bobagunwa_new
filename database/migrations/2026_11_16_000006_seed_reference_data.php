<?php

use App\Support\ReferenceData;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ReferenceData::sync();
    }

    public function down(): void
    {
        // Reference data is removed with its tables.
    }
};
