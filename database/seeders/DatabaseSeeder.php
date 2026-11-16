<?php

namespace Database\Seeders;

use App\Support\ReferenceData;
use Illuminate\Database\Seeder;

/**
 * Production-safe: reference data only (permissions, roles, settings,
 * sequence). Never creates users, members, content or financial records.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        ReferenceData::sync();
    }
}
