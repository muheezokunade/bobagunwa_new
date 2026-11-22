<?php

use App\Support\ReferenceData;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two-step membership approval (22 Nov 2026 demo): the General Secretary
 * reviews and recommends; the chair of the screening committee, a different
 * officer, gives the final decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->enum('recommendation', ['approve', 'reject'])->nullable()->after('status');
            $table->foreignId('recommended_by')->nullable()->after('recommendation')->constrained('users')->restrictOnDelete();
            $table->timestamp('recommended_at')->nullable()->after('recommended_by');
        });
        DB::statement("ALTER TABLE application_events MODIFY type ENUM('submitted','status_changed','note','applicant_response','recommended') NOT NULL");
        ReferenceData::sync(); // new permission applications.decide and the Membership Initiator role
    }

    public function down(): void
    {
        DB::table('application_events')->where('type', 'recommended')->update(['type' => 'note']);
        DB::statement("ALTER TABLE application_events MODIFY type ENUM('submitted','status_changed','note','applicant_response') NOT NULL");
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recommended_by');
            $table->dropColumn(['recommendation', 'recommended_at']);
        });
    }
};
