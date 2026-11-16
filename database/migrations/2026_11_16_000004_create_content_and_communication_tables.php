<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Website content, communications, events and enquiries.
 * Nothing here can affect financial records or security configuration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255)->unique();
            $table->string('original_name', 190);
            $table->string('mime', 60);
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->unsignedInteger('size_bytes');
            $table->string('alt_text', 255)->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('key', 60)->unique();
            $table->string('label', 120);
            $table->unsignedBigInteger('published_revision_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('page_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('page_id')->constrained()->restrictOnDelete();
            $table->string('title', 190);
            $table->string('summary', 500)->nullable();
            $table->mediumText('body');
            $table->string('change_note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['page_id', 'id']);
        });
        Schema::table('pages', function (Blueprint $table) {
            $table->foreign('published_revision_id')->references('id')->on('page_revisions')->restrictOnDelete();
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['news', 'announcement']);
            $table->enum('audience', ['public', 'members']);
            $table->string('slug', 160)->unique();
            $table->string('title', 190);
            $table->string('excerpt', 500)->nullable();
            $table->mediumText('body');
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['type', 'status', 'audience', 'published_at']);
        });

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->foreignId('post_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->timestamp('read_at')->useCurrent();
            $table->primary(['post_id', 'member_id']);
        });

        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 160)->unique();
            $table->string('title', 190);
            $table->string('summary', 500)->nullable();
            $table->mediumText('body')->nullable();
            $table->string('location', 255)->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();
            $table->enum('audience', ['public', 'members']);
            $table->enum('status', ['draft', 'published', 'cancelled', 'archived'])->default('draft');
            $table->boolean('rsvp_enabled')->default(false);
            $table->dateTime('rsvp_closes_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->foreignId('cover_media_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'audience', 'starts_at']);
        });

        Schema::create('event_rsvps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->restrictOnDelete();
            $table->foreignId('member_id')->constrained()->restrictOnDelete();
            $table->enum('response', ['attending', 'not_attending', 'maybe']);
            $table->unsignedTinyInteger('guests')->default(0);
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'member_id']);
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 160)->unique();
            $table->string('title', 190);
            $table->string('category', 80)->nullable();
            $table->string('summary', 500);
            $table->mediumText('body')->nullable();
            $table->foreignId('media_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->integer('sort_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'sort_order']);
        });

        Schema::create('leaders', function (Blueprint $table) {
            $table->id();
            $table->string('name', 190);
            $table->string('office', 160);
            $table->enum('group', ['executive', 'council', 'chiefs', 'other']);
            $table->string('term_label', 60)->nullable();
            $table->text('bio')->nullable();
            $table->foreignId('photo_media_id')->nullable()->constrained('media')->restrictOnDelete();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->integer('sort_order')->default(0);
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['status', 'group', 'sort_order']);
        });

        Schema::create('galleries', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 160)->unique();
            $table->string('title', 190);
            $table->string('description', 500)->nullable();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('gallery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gallery_id')->constrained()->restrictOnDelete();
            $table->foreignId('media_id')->constrained('media')->restrictOnDelete();
            $table->string('caption', 255)->nullable();
            $table->integer('sort_order')->default(0);
            $table->unique(['gallery_id', 'media_id']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 190);
            $table->string('description', 500)->nullable();
            $table->foreignId('private_file_id')->constrained('private_files')->restrictOnDelete();
            $table->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->string('name', 120);
            $table->string('email', 190);
            $table->string('phone', 40)->nullable();
            $table->enum('topic', ['membership', 'events', 'partnership', 'general']);
            $table->string('subject', 190);
            $table->text('message');
            $table->enum('status', ['new', 'in_progress', 'resolved', 'closed_spam'])->default('new');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('consent_at');
            $table->char('ip_hash', 64)->nullable();
            $table->timestamps();
            $table->index(['status', 'created_at']);
        });

        Schema::create('enquiry_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enquiry_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('note');
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::table('pages', fn (Blueprint $t) => $t->dropForeign(['published_revision_id']));
        foreach (['enquiry_notes', 'enquiries', 'documents', 'gallery_items', 'galleries', 'leaders', 'activities', 'event_rsvps', 'events', 'announcement_reads', 'posts', 'page_revisions', 'pages', 'media'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
