<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The Giwa's welcome address is a managed page shown on the home page.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('pages')->where('key', 'welcome')->exists()) {
            DB::table('pages')->insert(['key' => 'welcome', 'label' => 'Giwa’s welcome address (home page)', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        DB::table('pages')->where('key', 'welcome')->whereNull('published_revision_id')
            ->whereNotExists(fn ($q) => $q->select(DB::raw(1))->from('page_revisions')->whereColumn('page_revisions.page_id', 'pages.id'))
            ->delete();
    }
};
