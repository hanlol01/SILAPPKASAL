<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_sections', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('label_id', 100);
            $table->string('label_en', 100);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('content_categories', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('section_id')->constrained('content_sections')->restrictOnDelete();
            $table->string('stable_seed_key', 150)->nullable()->unique();
            $table->string('code', 100);
            $table->string('name', 150);
            $table->string('slug', 180);
            $table->text('description')->nullable();
            $table->string('icon_code', 100)->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->string('scope', 20);
            $table->string('scope_key', 80);
            $table->foreignId('university_id')->nullable()->constrained('universities')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['scope_key', 'code']);
            $table->unique(['section_id', 'scope_key', 'slug']);
            $table->index(['section_id', 'scope', 'university_id', 'is_active'], 'content_categories_reader_idx');
        });

        Schema::create('content_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('stable_seed_key', 180)->nullable()->unique();
            $table->string('content_type', 30);
            $table->foreignId('section_id')->constrained('content_sections')->restrictOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('content_categories')->restrictOnDelete();
            $table->string('slug', 200);
            $table->string('scope', 20);
            $table->string('scope_key', 80);
            $table->foreignId('university_id')->nullable()->constrained('universities')->restrictOnDelete();
            $table->foreignId('creator_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('current_draft_version_id')->nullable();
            $table->unsignedBigInteger('published_version_id')->nullable();
            $table->unsignedInteger('lock_version')->default(1);
            $table->timestamp('archived_at')->nullable();
            $table->foreignId('archived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('archive_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['content_type', 'scope_key', 'slug']);
            $table->index(['content_type', 'scope', 'university_id', 'archived_at'], 'content_items_reader_scope_idx');
            $table->index('current_draft_version_id');
            $table->index('published_version_id');
        });

        Schema::create('content_versions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('content_item_id')->constrained('content_items')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('lifecycle_status', 40);
            $table->string('title', 255);
            $table->text('excerpt')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('editor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type', 50)->default('manual');
            $table->string('seed_key', 180)->nullable()->unique();
            $table->boolean('requires_editorial_review')->default(false);
            $table->text('editorial_note')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('review_started_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('revision_requested_at')->nullable();
            $table->timestamps();

            $table->unique(['content_item_id', 'version_number']);
            $table->index(['lifecycle_status', 'published_at']);
        });

        Schema::create('article_version_contents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_version_id')->unique()->constrained('content_versions')->cascadeOnDelete();
            $table->json('document_json')->nullable();
            $table->longText('sanitized_html')->nullable();
            $table->longText('search_text')->nullable();
            $table->unsignedSmallInteger('estimated_reading_minutes')->default(0);
            $table->unsignedBigInteger('cover_attachment_id')->nullable();
            $table->string('cover_alt_text', 500)->nullable();
            $table->foreignId('consultation_cta_item_id')->nullable()->constrained('content_items')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('faq_version_contents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_version_id')->unique()->constrained('content_versions')->cascadeOnDelete();
            $table->string('question', 500);
            $table->json('answer_document_json')->nullable();
            $table->longText('sanitized_answer_html')->nullable();
            $table->longText('plain_search_text')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('consultation_version_contents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('content_version_id')->unique()->constrained('content_versions')->cascadeOnDelete();
            $table->string('service_name', 200);
            $table->text('description')->nullable();
            $table->string('email')->nullable();
            $table->string('phone_display', 40)->nullable();
            $table->string('phone_normalized', 20)->nullable();
            $table->string('whatsapp_display', 40)->nullable();
            $table->string('whatsapp_normalized', 20)->nullable();
            $table->text('office_address')->nullable();
            $table->text('operating_hours')->nullable();
            $table->boolean('emergency_available')->default(false);
            $table->string('appointment_url', 2048)->nullable();
            $table->string('action_label', 100)->nullable();
            $table->string('icon_code', 100)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->date('verification_date')->nullable();
            $table->string('verified_owner', 200)->nullable();
            $table->timestamps();
        });

        Schema::create('content_review_decisions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('content_version_id')->constrained('content_versions')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->restrictOnDelete();
            $table->string('decision_code', 50);
            $table->text('narrative_reason')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();

            $table->index(['content_version_id', 'decided_at']);
        });

        Schema::create('content_attachments', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('content_version_id')->constrained('content_versions')->cascadeOnDelete();
            $table->string('purpose', 30);
            $table->string('storage_disk', 50);
            $table->string('storage_path')->unique();
            $table->string('safe_filename');
            $table->text('original_filename')->nullable();
            $table->string('detected_mime', 100);
            $table->string('extension', 10);
            $table->unsignedBigInteger('file_size');
            $table->char('checksum_sha256', 64);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->string('alt_text', 500)->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->foreignId('uploader_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['content_version_id', 'purpose', 'display_order'], 'content_attachments_version_idx');
        });

        Schema::create('featured_content', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('scope', 20);
            $table->string('scope_key', 80);
            $table->foreignId('university_id')->nullable()->constrained('universities')->restrictOnDelete();
            $table->foreignId('content_item_id')->constrained('content_items')->cascadeOnDelete();
            $table->unsignedTinyInteger('rank');
            $table->boolean('is_active')->default(true);
            $table->timestamp('active_from')->nullable();
            $table->timestamp('active_until')->nullable();
            $table->foreignId('creator_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['scope', 'university_id', 'is_active'], 'featured_content_reader_idx');
        });

        Schema::table('content_items', function (Blueprint $table): void {
            $table->foreign('current_draft_version_id')->references('id')->on('content_versions')->nullOnDelete();
            $table->foreign('published_version_id')->references('id')->on('content_versions')->nullOnDelete();
        });

        Schema::table('article_version_contents', function (Blueprint $table): void {
            $table->foreign('cover_attachment_id')->references('id')->on('content_attachments')->nullOnDelete();
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement("CREATE UNIQUE INDEX content_versions_one_editable_draft ON content_versions (content_item_id) WHERE lifecycle_status IN ('draft', 'revision_requested')");
            DB::statement('CREATE INDEX content_items_published_pointer_idx ON content_items (published_version_id) WHERE published_version_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX featured_content_active_rank_unique ON featured_content (scope_key, rank) WHERE is_active = true');
            DB::statement('CREATE UNIQUE INDEX featured_content_active_item_unique ON featured_content (scope_key, content_item_id) WHERE is_active = true');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('article_version_contents')) {
            Schema::table('article_version_contents', function (Blueprint $table): void {
                $table->dropForeign(['cover_attachment_id']);
            });
        }

        if (Schema::hasTable('content_items')) {
            Schema::table('content_items', function (Blueprint $table): void {
                $table->dropForeign(['current_draft_version_id']);
                $table->dropForeign(['published_version_id']);
            });
        }

        Schema::dropIfExists('featured_content');
        Schema::dropIfExists('content_attachments');
        Schema::dropIfExists('content_review_decisions');
        Schema::dropIfExists('consultation_version_contents');
        Schema::dropIfExists('faq_version_contents');
        Schema::dropIfExists('article_version_contents');
        Schema::dropIfExists('content_versions');
        Schema::dropIfExists('content_items');
        Schema::dropIfExists('content_categories');
        Schema::dropIfExists('content_sections');
    }
};
