<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * History & heritage (22 Nov 2026 demo): past Awujales and other historical
 * figures, each with a picture, years and a short summary on the page and
 * the full history in a pop-up. Content comes from the association.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historical_figures', function (Blueprint $table) {
            $table->id();
            $table->string('name', 190);
            $table->string('title', 160)->nullable();
            $table->string('years_label', 80)->nullable();
            $table->string('summary', 600);
            $table->mediumText('body')->nullable();
            $table->foreignId('photo_media_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->integer('sort_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_figures');
    }
};
