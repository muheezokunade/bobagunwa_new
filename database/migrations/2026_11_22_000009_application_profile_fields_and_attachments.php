<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Application form aligned with the association's Member Profile form
 * (22 Nov 2026): country, address, marital status and profile summary, and
 * private supporting documents. "Reason for joining" is no longer asked.
 */
return new class extends Migration
{
    public const ATTACHMENT_KINDS = ['birth_certificate', 'lg_certificate', 'first_school_certificate', 'secondary_school_certificate', 'other_certificate', 'referee_letter', 'passport_photo', 'full_photo'];

    public function up(): void
    {
        Schema::table('membership_applications', function (Blueprint $table) {
            $table->string('country', 80)->nullable()->after('city');
            $table->string('address', 255)->nullable()->after('country');
            $table->string('marital_status', 20)->nullable()->after('address');
            $table->text('profile_summary')->nullable()->after('occupation');
            $table->text('reason')->nullable()->change();
        });
        Schema::table('members', function (Blueprint $table) {
            $table->string('country', 80)->nullable()->after('city');
            $table->string('marital_status', 20)->nullable()->after('address');
            $table->text('profile_summary')->nullable()->after('occupation');
        });
        Schema::create('application_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('membership_application_id')->constrained()->restrictOnDelete();
            $table->foreignId('private_file_id')->unique()->constrained()->restrictOnDelete();
            $table->enum('kind', self::ATTACHMENT_KINDS);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['membership_application_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_attachments');
        Schema::table('members', fn (Blueprint $table) => $table->dropColumn(['country', 'marital_status', 'profile_summary']));
        Schema::table('membership_applications', fn (Blueprint $table) => $table->dropColumn(['country', 'address', 'marital_status', 'profile_summary']));
    }
};
