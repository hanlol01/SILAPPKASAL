<?php

namespace Tests\Feature;

use App\Enums\ContentAttachmentPurpose;
use App\Enums\ContentScope;
use App\Models\AuditLog;
use App\Models\ContentAttachment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use App\Services\ContentDocumentService;
use App\Services\ContentPublicationService;
use App\Services\GdContentImageProcessor;
use Database\Seeders\Foundation\ContentFoundationSeeder;
use Database\Seeders\RbacSeeder;
use GdImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

final class ContentMediaTest extends TestCase
{
    use RefreshDatabase;

    private University $campusA;

    private University $campusB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(ContentFoundationSeeder::class);
        $this->campusA = University::query()->create([
            'code' => 'C-MEDIA-A',
            'name' => 'Universitas Media A',
            'type' => 'universitas',
            'is_active' => true,
        ]);
        $this->campusB = University::query()->create([
            'code' => 'C-MEDIA-B',
            'name' => 'Universitas Media B',
            'type' => 'universitas',
            'is_active' => true,
        ]);
    }

    public function test_gd_processor_normalizes_orientation_and_removes_exif_metadata(): void
    {
        $this->requireGd();
        $source = tempnam(sys_get_temp_dir(), 'content-media-source-');
        $this->assertIsString($source);

        $image = imagecreatetruecolor(2, 1);
        $this->assertInstanceOf(GdImage::class, $image);
        imagejpeg($image, $source, 95);
        imagedestroy($image);

        $bytes = file_get_contents($source);
        $this->assertIsString($bytes);
        $exifPayload = "Exif\x00\x00"
            .'II'
            .pack('v', 42)
            .pack('V', 8)
            .pack('v', 1)
            .pack('v', 0x0112)
            .pack('v', 3)
            .pack('V', 1)
            .pack('v', 6)
            ."\x00\x00"
            .pack('V', 0);
        $segment = "\xFF\xE1".pack('n', strlen($exifPayload) + 2).$exifPayload;
        file_put_contents($source, substr($bytes, 0, 2).$segment.substr($bytes, 2));

        $processor = new GdContentImageProcessor;
        $processed = $processor->reencode(new UploadedFile(
            $source,
            'oriented.jpg',
            'image/jpeg',
            UPLOAD_ERR_OK,
            true,
        ));
        $processedPath = $processed->getRealPath();
        $this->assertIsString($processedPath);
        $dimensions = getimagesize($processedPath);
        $this->assertIsArray($dimensions);
        $this->assertSame([1, 2], [(int) $dimensions[0], (int) $dimensions[1]]);
        $this->assertStringNotContainsString('Exif', (string) file_get_contents($processedPath));

        $processor->release($processed);
        $this->assertFileDoesNotExist($processedPath);
        @unlink($source);
    }

    public function test_gd_processor_reports_real_formats_and_preserves_png_alpha(): void
    {
        $this->requireGd();
        $processor = new GdContentImageProcessor;
        $formats = $processor->supportedMimeTypes();
        $this->assertContains('image/jpeg', $formats);
        $this->assertContains('image/png', $formats);
        $this->assertSame(function_exists('imagewebp'), in_array('image/webp', $formats, true));

        $source = tempnam(sys_get_temp_dir(), 'content-media-alpha-');
        $this->assertIsString($source);
        $image = imagecreatetruecolor(2, 2);
        $this->assertInstanceOf(GdImage::class, $image);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        $transparent = imagecolorallocatealpha($image, 10, 20, 30, 127);
        imagefill($image, 0, 0, $transparent);
        imagepng($image, $source);
        imagedestroy($image);

        $processed = $processor->reencode(new UploadedFile(
            $source,
            'alpha.png',
            'image/png',
            UPLOAD_ERR_OK,
            true,
        ));
        $decoded = imagecreatefrompng((string) $processed->getRealPath());
        $this->assertInstanceOf(GdImage::class, $decoded);
        $this->assertSame(127, (imagecolorat($decoded, 0, 0) >> 24) & 0x7F);
        imagedestroy($decoded);
        $processor->release($processed);
        @unlink($source);

        if (in_array('image/webp', $formats, true)) {
            $webpSource = tempnam(sys_get_temp_dir(), 'content-media-alpha-webp-');
            $this->assertIsString($webpSource);
            $webp = imagecreatetruecolor(2, 2);
            $this->assertInstanceOf(GdImage::class, $webp);
            imagealphablending($webp, false);
            imagesavealpha($webp, true);
            $webpTransparent = imagecolorallocatealpha($webp, 30, 20, 10, 127);
            imagefill($webp, 0, 0, $webpTransparent);
            imagewebp($webp, $webpSource, 100);
            imagedestroy($webp);
            $processedWebp = $processor->reencode(new UploadedFile(
                $webpSource,
                'alpha.webp',
                'image/webp',
                UPLOAD_ERR_OK,
                true,
            ));
            $this->assertSame('image/webp', $processedWebp->getMimeType());
            $decodedWebp = imagecreatefromwebp((string) $processedWebp->getRealPath());
            $this->assertInstanceOf(GdImage::class, $decodedWebp);
            $this->assertGreaterThanOrEqual(120, (imagecolorat($decodedWebp, 0, 0) >> 24) & 0x7F);
            imagedestroy($decodedWebp);
            $processor->release($processedWebp);
            @unlink($webpSource);
        }
    }

    public function test_cover_and_inline_media_stay_private_until_the_owning_version_is_published(): void
    {
        $this->requireGd();
        Storage::fake('content');
        config()->set('content.attachments.image_uploads_enabled', true);

        $admin = $this->user('admin', $this->campusA);
        $foreignAdmin = $this->user('admin', $this->campusB);
        $super = $this->user('super_admin');
        $reader = $this->user('reporter', $this->campusA);
        $foreignReader = $this->user('reporter', $this->campusB);
        $service = app(ContentPublicationService::class);
        $item = $service->createDraft($admin, $this->articlePayload('Media Privat'));
        $version = $item->currentDraftVersion;

        Sanctum::actingAs($foreignAdmin, ['*']);
        $this->postJson('/api/v1/content-management/versions/'.$version->public_id.'/attachments', [
            'purpose' => ContentAttachmentPurpose::Cover->value,
            'file' => UploadedFile::fake()->image('foreign-cover.png', 32, 18),
            'alt_text' => 'Tidak boleh masuk',
        ])->assertNotFound();
        $this->assertDatabaseCount('content_attachments', 0);

        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/v1/content-management/capabilities')
            ->assertOk()
            ->assertJsonPath('data.image_upload_available', true)
            ->assertJsonPath('data.cover_max_bytes', 5 * 1024 * 1024)
            ->assertJsonPath('data.inline_image_max_bytes', 10 * 1024 * 1024);

        $coverId = $this->uploadImage($version->public_id, 'cover', 'cover.png', 'Sampul aman');
        $inlineId = $this->uploadImage($version->public_id, 'inline_image', 'inline.png', 'Ilustrasi isi');
        $orphanId = $this->uploadImage($version->public_id, 'inline_image', 'orphan.png', 'Tidak digunakan');

        $this->get('/api/v1/content/attachments/'.$coverId)->assertOk();

        Sanctum::actingAs($reader, ['*']);
        $this->get('/api/v1/content/attachments/'.$coverId)->assertNotFound();
        $this->get('/api/v1/content/attachments/'.$inlineId)->assertNotFound();

        $item = $service->updateDraft($version->fresh(), $admin, [
            'lock_version' => $item->fresh()->lock_version,
            'document' => [
                'type' => 'doc',
                'content' => [
                    [
                        'type' => 'paragraph',
                        'content' => [['type' => 'text', 'text' => 'Isi dengan gambar aman.']],
                    ],
                    [
                        'type' => 'imageReference',
                        'attrs' => [
                            'attachment_public_id' => $inlineId,
                            'alt' => 'Ilustrasi isi',
                        ],
                    ],
                ],
            ],
        ]);
        $item = $service->submit(
            $item->currentDraftVersion,
            $admin,
            (int) $item->lock_version,
        );
        $item = $service->startReview(
            $item->currentDraftVersion,
            $super,
            (int) $item->lock_version,
        );
        $item = $service->approve(
            $item->currentDraftVersion,
            $super,
            (int) $item->lock_version,
        );
        $item = $service->publishApproved(
            $item->currentDraftVersion,
            $super,
            (int) $item->lock_version,
        );

        Sanctum::actingAs($reader, ['*']);
        $detail = $this->getJson('/api/v1/content/articles/slug/education/'.$item->slug)
            ->assertOk()
            ->assertJsonPath('data.cover.public_id', $coverId)
            ->assertJsonPath('data.inline_images.0.public_id', $inlineId)
            ->assertJsonCount(1, 'data.inline_images')
            ->assertJsonCount(0, 'data.attachments');
        $detail->assertJsonMissing(['public_id' => $orphanId]);
        $this->get('/api/v1/content/attachments/'.$coverId)->assertOk();
        $this->get('/api/v1/content/attachments/'.$inlineId)->assertOk();
        $this->get('/api/v1/content/attachments/'.$orphanId)->assertNotFound();

        Sanctum::actingAs($foreignReader, ['*']);
        $this->get('/api/v1/content/attachments/'.$coverId)->assertNotFound();
        $this->get('/api/v1/content/attachments/'.$inlineId)->assertNotFound();
    }

    public function test_svg_and_image_disguised_as_general_attachment_are_rejected(): void
    {
        $this->requireGd();
        Storage::fake('content');
        config()->set('content.attachments.image_uploads_enabled', true);
        $admin = $this->user('admin', $this->campusA);
        $version = app(ContentPublicationService::class)
            ->createDraft($admin, $this->articlePayload('Media Ditolak'))
            ->currentDraftVersion;
        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/v1/content-management/versions/'.$version->public_id.'/attachments', [
            'purpose' => 'inline_image',
            'file' => UploadedFile::fake()->createWithContent(
                'vector.svg',
                '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
            ),
            'alt_text' => 'SVG terlarang',
        ])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->postJson('/api/v1/content-management/versions/'.$version->public_id.'/attachments', [
            'purpose' => 'attachment',
            'file' => UploadedFile::fake()->image('bukan-pdf.png', 20, 20),
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('content_attachments', 0);
    }

    public function test_corrupt_executable_gif_and_oversized_dimension_images_are_rejected(): void
    {
        $this->requireGd();
        Storage::fake('content');
        config()->set('content.attachments.image_uploads_enabled', true);
        $admin = $this->user('admin', $this->campusA);
        $version = app(ContentPublicationService::class)
            ->createDraft($admin, $this->articlePayload('Payload Gambar Ditolak'))
            ->currentDraftVersion;
        Sanctum::actingAs($admin, ['*']);

        $files = [
            UploadedFile::fake()->createWithContent('corrupt.png', 'not-an-image'),
            UploadedFile::fake()->createWithContent('executable.png', "MZ\x90\x00payload"),
            UploadedFile::fake()->createWithContent('archive.png', "PK\x03\x04payload"),
            UploadedFile::fake()->createWithContent('document.pdf', "%PDF-1.4\n%%EOF"),
            UploadedFile::fake()->image('animated.gif', 20, 20),
            UploadedFile::fake()->image('too-wide.png', 6001, 1),
        ];
        foreach ($files as $file) {
            $this->postJson('/api/v1/content-management/versions/'.$version->public_id.'/attachments', [
                'purpose' => ContentAttachmentPurpose::InlineImage->value,
                'file' => $file,
                'alt_text' => 'Payload harus ditolak',
            ])->assertUnprocessable()->assertJsonValidationErrors('file');
        }
        $this->assertDatabaseCount('content_attachments', 0);
        $this->assertSame([], Storage::disk('content')->allFiles());
    }

    public function test_image_polyglot_trailing_payload_is_removed_by_reencoding(): void
    {
        $this->requireGd();
        Storage::fake('content');
        config()->set('content.attachments.image_uploads_enabled', true);
        $admin = $this->user('admin', $this->campusA);
        $version = app(ContentPublicationService::class)
            ->createDraft($admin, $this->articlePayload('Polyglot Gambar'))
            ->currentDraftVersion;
        Sanctum::actingAs($admin, ['*']);

        $source = tempnam(sys_get_temp_dir(), 'content-media-polyglot-');
        $this->assertIsString($source);
        $image = imagecreatetruecolor(2, 2);
        $this->assertInstanceOf(GdImage::class, $image);
        imagepng($image, $source);
        imagedestroy($image);
        $bytes = file_get_contents($source);
        $this->assertIsString($bytes);
        $marker = '<?php executable-polyglot ?>';
        $response = $this->postJson('/api/v1/content-management/versions/'.$version->public_id.'/attachments', [
            'purpose' => ContentAttachmentPurpose::Cover->value,
            'file' => UploadedFile::fake()->createWithContent('polyglot.png', $bytes.$marker),
            'alt_text' => 'Sampul hasil pemrosesan ulang',
        ])->assertCreated();
        $attachment = ContentAttachment::query()
            ->where('public_id', $response->json('data.public_id'))
            ->firstOrFail();
        $this->assertStringNotContainsString(
            $marker,
            (string) Storage::disk('content')->get($attachment->storage_path),
        );
        @unlink($source);
    }

    public function test_corrupt_executable_and_trailing_pdf_polyglots_are_rejected(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $version = app(ContentPublicationService::class)
            ->createDraft($admin, $this->articlePayload('Payload PDF Ditolak'))
            ->currentDraftVersion;
        Sanctum::actingAs($admin, ['*']);

        foreach ([
            ['executable.pdf', "MZ\x90\x00payload"],
            ['corrupt.pdf', "%PDF-1.4\nmissing-eof"],
            ['polyglot.pdf', "%PDF-1.4\n%%EOF\nMZ-executable"],
        ] as [$name, $bytes]) {
            $this->postJson('/api/v1/content-management/versions/'.$version->public_id.'/attachments', [
                'purpose' => ContentAttachmentPurpose::Attachment->value,
                'file' => UploadedFile::fake()->createWithContent($name, $bytes),
            ])->assertUnprocessable()->assertJsonValidationErrors('file');
        }
        $this->assertDatabaseCount('content_attachments', 0);
    }

    public function test_cover_can_be_removed_but_referenced_inline_media_requires_a_saved_document_change(): void
    {
        $this->requireGd();
        Storage::fake('content');
        config()->set('content.attachments.image_uploads_enabled', true);
        $admin = $this->user('admin', $this->campusA);
        $service = app(ContentPublicationService::class);
        $item = $service->createDraft($admin, $this->articlePayload('Penghapusan Media'));
        $version = $item->currentDraftVersion;
        Sanctum::actingAs($admin, ['*']);

        $coverId = $this->uploadImage($version->public_id, 'cover', 'remove-cover.png', 'Sampul');
        $inlineId = $this->uploadImage($version->public_id, 'inline_image', 'remove-inline.png', 'Isi');
        $item = $service->updateDraft($version->fresh(), $admin, [
            'lock_version' => $item->fresh()->lock_version,
            'document' => [
                'type' => 'doc',
                'content' => [[
                    'type' => 'imageReference',
                    'attrs' => [
                        'attachment_public_id' => $inlineId,
                        'alt' => 'Isi',
                    ],
                ]],
            ],
        ]);

        $this->deleteJson('/api/v1/content-management/attachments/'.$inlineId)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('attachment');
        $this->assertDatabaseHas('content_attachments', ['public_id' => $inlineId]);

        $this->deleteJson('/api/v1/content-management/attachments/'.$coverId)->assertOk();
        $this->assertDatabaseMissing('content_attachments', ['public_id' => $coverId]);
        $this->assertNull($version->articleContent()->firstOrFail()->cover_attachment_id);

        $item = $service->updateDraft($version->fresh(), $admin, [
            'lock_version' => $item->fresh()->lock_version,
            'document' => [
                'type' => 'doc',
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'Gambar telah dihapus.']],
                ]],
            ],
        ]);
        $this->assertNotNull($item->currentDraftVersion);
        $this->deleteJson('/api/v1/content-management/attachments/'.$inlineId)->assertOk();
        $this->assertDatabaseMissing('content_attachments', ['public_id' => $inlineId]);
        $this->assertSame(
            2,
            AuditLog::query()
                ->where('action', 'content.attachment_removed')
                ->count(),
        );
    }

    public function test_orphan_cleanup_is_dry_run_by_default_and_never_removes_referenced_media(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $item = app(ContentPublicationService::class)
            ->createDraft($admin, $this->articlePayload('Retensi Media'));
        $version = $item->currentDraftVersion;
        $referenced = $this->storedAttachment($version->id, $admin->id, 'referenced');
        $orphan = $this->storedAttachment($version->id, $admin->id, 'orphan');
        $version->articleContent->forceFill(['cover_attachment_id' => $referenced->id])->save();

        ContentAttachment::query()->whereKey([$referenced->id, $orphan->id])->update([
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);

        Artisan::call('content:purge-orphan-media', ['--older-than-hours' => 24]);
        $this->assertDatabaseHas('content_attachments', ['id' => $orphan->id]);
        Storage::disk('content')->assertExists($orphan->storage_path);

        Artisan::call('content:purge-orphan-media', [
            '--execute' => true,
            '--older-than-hours' => 24,
        ]);
        $this->assertDatabaseMissing('content_attachments', ['id' => $orphan->id]);
        Storage::disk('content')->assertMissing($orphan->storage_path);
        $this->assertDatabaseHas('content_attachments', ['id' => $referenced->id]);
        Storage::disk('content')->assertExists($referenced->storage_path);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'content.attachment_removed',
            'actor_id' => null,
        ]);
    }

    public function test_download_authorization_is_exact_to_active_lifecycle_version_and_scope(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $foreignAdmin = $this->user('admin', $this->campusB);
        $super = $this->user('super_admin');
        $reporter = $this->user('reporter', $this->campusA);
        $foreignReporter = $this->user('reporter', $this->campusB);
        $satgas = $this->user('satgas_ppks', $this->campusA);
        $service = app(ContentPublicationService::class);
        $item = $service->createDraft($admin, $this->articlePayload('Otorisasi Unduhan'));
        $version = $item->currentDraftVersion;
        $cover = $this->storedAttachment($version->id, $admin->id, 'selected-cover');
        $orphan = $this->storedAttachment($version->id, $admin->id, 'detached-cover');
        $version->articleContent->forceFill(['cover_attachment_id' => $cover->id])->save();

        Sanctum::actingAs($admin, ['*']);
        $this->get('/api/v1/content/attachments/'.$cover->public_id)->assertOk();
        $this->get('/api/v1/content/attachments/'.$orphan->public_id)->assertNotFound();

        Sanctum::actingAs($foreignAdmin, ['*']);
        $this->get('/api/v1/content/attachments/'.$cover->public_id)->assertNotFound();
        Sanctum::actingAs($satgas, ['*']);
        $this->get('/api/v1/content/attachments/'.$cover->public_id)->assertNotFound();
        $this->postJson('/api/v1/content-management/versions/'.$version->public_id.'/attachments', [
            'purpose' => ContentAttachmentPurpose::Attachment->value,
            'file' => UploadedFile::fake()->createWithContent('satgas.pdf', "%PDF-1.4\n%%EOF"),
        ])->assertForbidden();

        $item = $service->submit($version->fresh(), $admin, (int) $item->fresh()->lock_version);
        Sanctum::actingAs($admin, ['*']);
        $this->get('/api/v1/content/attachments/'.$cover->public_id)->assertNotFound();

        Sanctum::actingAs($super, ['*']);
        $this->get('/api/v1/content/attachments/'.$cover->public_id)->assertOk();
        $item = $service->startReview(
            $item->currentDraftVersion,
            $super,
            (int) $item->lock_version,
        );
        $this->get('/api/v1/content/attachments/'.$cover->public_id)->assertOk();
        $item = $service->approve(
            $item->currentDraftVersion,
            $super,
            (int) $item->lock_version,
        );
        $this->get('/api/v1/content/attachments/'.$cover->public_id)->assertOk();
        $item = $service->publishApproved(
            $item->currentDraftVersion,
            $super,
            (int) $item->lock_version,
        );

        Sanctum::actingAs($admin, ['*']);
        $this->get('/api/v1/content/attachments/'.$cover->public_id)->assertNotFound();
        Sanctum::actingAs($super, ['*']);
        $this->get('/api/v1/content/attachments/'.$cover->public_id)->assertOk();
        $reviewPermissionId = Permission::query()->where('code', 'content.review')->value('id');
        $this->assertNotNull($reviewPermissionId);
        $super->role->permissions()->detach($reviewPermissionId);
        $super->unsetRelation('role');
        $this->get('/api/v1/content/attachments/'.$cover->public_id)->assertNotFound();
        $super->role->permissions()->attach($reviewPermissionId);
        $super->unsetRelation('role');
        Sanctum::actingAs($reporter, ['*']);
        $this->get('/api/v1/content/attachments/'.$cover->public_id)->assertOk();
        $this->get('/api/v1/content/attachments/'.$orphan->public_id)->assertNotFound();
        Sanctum::actingAs($foreignReporter, ['*']);
        $this->get('/api/v1/content/attachments/'.$cover->public_id)->assertNotFound();

        $item = $service->archive($item->fresh(), $super, 'Retensi uji', (int) $item->fresh()->lock_version);
        $this->assertNotNull($item->archived_at);
        Sanctum::actingAs($admin, ['*']);
        $this->get('/api/v1/content/attachments/'.$cover->public_id)->assertNotFound();
        Sanctum::actingAs($reporter, ['*']);
        $this->get('/api/v1/content/attachments/'.$cover->public_id)->assertNotFound();
    }

    public function test_management_and_governance_manifests_only_project_active_references(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $super = $this->user('super_admin');
        $service = app(ContentPublicationService::class);
        $item = $service->createDraft($admin, $this->articlePayload('Manifest Aktif'));
        $version = $item->currentDraftVersion;
        $cover = $this->storedAttachment($version->id, $admin->id, 'cover-active');
        $detachedCover = $this->storedAttachment($version->id, $admin->id, 'cover-detached');
        $inline = $this->storedAttachment(
            $version->id,
            $admin->id,
            'inline-active',
            ContentAttachmentPurpose::InlineImage,
        );
        $detachedInline = $this->storedAttachment(
            $version->id,
            $admin->id,
            'inline-detached',
            ContentAttachmentPurpose::InlineImage,
        );
        $pdf = $this->storedAttachment(
            $version->id,
            $admin->id,
            'pdf-active',
            ContentAttachmentPurpose::Attachment,
        );
        $missingPdf = $this->storedAttachment(
            $version->id,
            $admin->id,
            'pdf-missing',
            ContentAttachmentPurpose::Attachment,
        );
        Storage::disk('content')->delete($missingPdf->storage_path);
        $version->articleContent->forceFill(['cover_attachment_id' => $cover->id])->save();
        $item = $service->updateDraft($version->fresh(), $admin, [
            'lock_version' => $item->fresh()->lock_version,
            'document' => [
                'type' => 'doc',
                'content' => [[
                    'type' => 'imageReference',
                    'attrs' => [
                        'attachment_public_id' => $inline->public_id,
                        'alt' => '  Ilustrasi manifest  ',
                    ],
                ]],
            ],
        ]);

        Sanctum::actingAs($admin, ['*']);
        $management = $this->getJson('/api/v1/content-management/items/'.$item->public_id)
            ->assertOk()
            ->assertJsonPath('data.version.article.cover.public_id', $cover->public_id);
        $expected = [$cover->public_id, $inline->public_id, $pdf->public_id];
        $this->assertSame(
            $expected,
            collect($management->json('data.version.attachments'))->pluck('public_id')->all(),
        );
        foreach ([$detachedCover, $detachedInline, $missingPdf] as $hidden) {
            $management->assertJsonMissing(['public_id' => $hidden->public_id]);
        }

        $missingPdf->delete();
        $item = $service->submit(
            $item->currentDraftVersion,
            $admin,
            (int) $item->lock_version,
        );
        Sanctum::actingAs($super, ['*']);
        $governance = $this->getJson('/api/v1/content-governance/items/'.$item->public_id)
            ->assertOk()
            ->assertJsonPath('data.version.public_id', $item->currentDraftVersion->public_id);
        $this->assertSame(
            $expected,
            collect($governance->json('data.version.attachments'))->pluck('public_id')->all(),
        );
    }

    public function test_image_reference_alt_is_canonical_and_required_for_new_writes(): void
    {
        $documents = app(ContentDocumentService::class);
        $publicId = (string) Str::uuid();
        $prepared = $documents->prepareArticle([
            'type' => 'doc',
            'content' => [[
                'type' => 'imageReference',
                'attrs' => [
                    'attachment_public_id' => $publicId,
                    'alt' => '  Diagram alur aman  ',
                ],
            ]],
        ]);
        $this->assertSame(
            'Diagram alur aman',
            $prepared['document']['content'][0]['attrs']['alt'],
        );

        foreach ([null, '', '   ', str_repeat('a', 501)] as $alt) {
            $document = [
                'type' => 'doc',
                'content' => [[
                    'type' => 'imageReference',
                    'attrs' => ['attachment_public_id' => $publicId],
                ]],
            ];
            if ($alt !== null) {
                $document['content'][0]['attrs']['alt'] = $alt;
            }
            $this->assertDocumentValidationFails(
                fn () => $documents->prepareArticle($document),
            );
        }
    }

    public function test_orphan_cleanup_handles_missing_binary_and_is_idempotent(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $item = app(ContentPublicationService::class)
            ->createDraft($admin, $this->articlePayload('Media Hilang'));
        $orphan = $this->storedAttachment(
            $item->currentDraftVersion->id,
            $admin->id,
            'missing-binary',
        );
        ContentAttachment::query()->whereKey($orphan->id)->update([
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);
        Storage::disk('content')->delete($orphan->storage_path);

        $this->assertSame(0, Artisan::call('content:purge-orphan-media', [
            '--execute' => true,
            '--older-than-hours' => 24,
        ]));
        $this->assertDatabaseMissing('content_attachments', ['id' => $orphan->id]);
        $this->assertSame(0, Artisan::call('content:purge-orphan-media', [
            '--execute' => true,
            '--older-than-hours' => 24,
        ]));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'content.attachment_removed',
            'actor_id' => null,
        ]);
    }

    public function test_orphan_cleanup_preserves_binary_when_database_delete_fails(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $item = app(ContentPublicationService::class)
            ->createDraft($admin, $this->articlePayload('Kegagalan Database'));
        $orphan = $this->storedAttachment(
            $item->currentDraftVersion->id,
            $admin->id,
            'database-failure',
        );
        ContentAttachment::query()->whereKey($orphan->id)->update([
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);
        ContentAttachment::deleting(function (ContentAttachment $attachment) use ($orphan): void {
            if ((int) $attachment->id === (int) $orphan->id) {
                throw new RuntimeException('Simulated database delete failure.');
            }
        });

        $result = Artisan::call('content:purge-orphan-media', [
            '--execute' => true,
            '--older-than-hours' => 24,
        ]);
        ContentAttachment::flushEventListeners();

        $this->assertSame(1, $result);
        $this->assertDatabaseHas('content_attachments', ['id' => $orphan->id]);
        Storage::disk('content')->assertExists($orphan->storage_path);
    }

    public function test_orphan_cleanup_keeps_metadata_deleted_when_storage_delete_throws_before_removal(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $item = app(ContentPublicationService::class)
            ->createDraft($admin, $this->articlePayload('Kegagalan Storage'));
        $orphan = $this->storedAttachment(
            $item->currentDraftVersion->id,
            $admin->id,
            'storage-failure',
        );
        ContentAttachment::query()->whereKey($orphan->id)->update([
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);
        $realDisk = Storage::disk('content');
        Log::spy();
        $failingDisk = new class
        {
            public function delete(string $path): bool
            {
                throw new RuntimeException('Simulated storage delete failure.');
            }
        };
        Storage::shouldReceive('disk')->with('content')->andReturn($failingDisk);

        $this->assertSame(1, Artisan::call('content:purge-orphan-media', [
            '--execute' => true,
            '--older-than-hours' => 24,
        ]));
        $this->assertDatabaseMissing('content_attachments', ['id' => $orphan->id]);
        $realDisk->assertExists($orphan->storage_path);
        $this->assertStringNotContainsString($orphan->storage_path, Artisan::output());
        Log::shouldHaveReceived('warning')->withArgs(
            function (string $message, array $context) use ($orphan): bool {
                $logged = $message.' '.json_encode($context);

                return $message === 'Content orphan media cleanup deferred after storage failure.'
                    && ! str_contains($logged, $orphan->storage_path)
                    && ! str_contains($logged, 'Simulated storage delete failure.')
                    && ! str_contains($logged, 'trace');
            },
        )->once();

        $this->assertSame(0, Artisan::call('content:purge-orphan-media', [
            '--execute' => true,
            '--older-than-hours' => 24,
        ]));
        $this->assertDatabaseMissing('content_attachments', ['id' => $orphan->id]);
        $realDisk->assertExists($orphan->storage_path);
    }

    public function test_orphan_cleanup_never_restores_metadata_when_delete_removes_binary_then_throws(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $item = app(ContentPublicationService::class)
            ->createDraft($admin, $this->articlePayload('Kegagalan Storage Ambigu'));
        $orphan = $this->storedAttachment(
            $item->currentDraftVersion->id,
            $admin->id,
            'storage-ambiguous-failure',
        );
        ContentAttachment::query()->whereKey($orphan->id)->update([
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);
        $realDisk = Storage::disk('content');
        $ambiguousDisk = new class($realDisk)
        {
            public function __construct(private readonly mixed $disk) {}

            public function delete(string $path): bool
            {
                $this->disk->delete($path);

                throw new RuntimeException('Simulated exception after physical deletion.');
            }

            public function exists(string $path): bool
            {
                throw new RuntimeException('Post-delete existence checks must not run.');
            }
        };
        Storage::shouldReceive('disk')->with('content')->andReturn($ambiguousDisk);

        $this->assertSame(1, Artisan::call('content:purge-orphan-media', [
            '--execute' => true,
            '--older-than-hours' => 24,
        ]));
        $this->assertDatabaseMissing('content_attachments', ['id' => $orphan->id]);
        $realDisk->assertMissing($orphan->storage_path);

        $this->assertSame(0, Artisan::call('content:purge-orphan-media', [
            '--execute' => true,
            '--older-than-hours' => 24,
        ]));
        $this->assertDatabaseMissing('content_attachments', ['id' => $orphan->id]);
    }

    public function test_orphan_cleanup_keeps_metadata_deleted_when_storage_delete_returns_false(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $item = app(ContentPublicationService::class)
            ->createDraft($admin, $this->articlePayload('Storage Menolak Penghapusan'));
        $orphan = $this->storedAttachment(
            $item->currentDraftVersion->id,
            $admin->id,
            'storage-false',
        );
        ContentAttachment::query()->whereKey($orphan->id)->update([
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);
        $realDisk = Storage::disk('content');
        $failingDisk = new class
        {
            public function delete(string $path): bool
            {
                return false;
            }
        };
        Storage::shouldReceive('disk')->with('content')->andReturn($failingDisk);

        $this->assertSame(1, Artisan::call('content:purge-orphan-media', [
            '--execute' => true,
            '--older-than-hours' => 24,
        ]));
        $this->assertDatabaseMissing('content_attachments', ['id' => $orphan->id]);
        $realDisk->assertExists($orphan->storage_path);

        $this->assertSame(0, Artisan::call('content:purge-orphan-media', [
            '--execute' => true,
            '--older-than-hours' => 24,
        ]));
        $this->assertDatabaseMissing('content_attachments', ['id' => $orphan->id]);
        $realDisk->assertExists($orphan->storage_path);
    }

    public function test_orphan_cleanup_rechecks_reference_immediately_before_metadata_deletion(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $item = app(ContentPublicationService::class)
            ->createDraft($admin, $this->articlePayload('Referensi Saat Recheck'));
        $version = $item->currentDraftVersion;
        $orphan = $this->storedAttachment($version->id, $admin->id, 'race-reference');
        ContentAttachment::query()->whereKey($orphan->id)->update([
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);
        $retrievals = 0;
        ContentAttachment::retrieved(
            function (ContentAttachment $attachment) use (&$retrievals, $orphan, $version): void {
                if ((int) $attachment->id !== (int) $orphan->id || ++$retrievals !== 2) {
                    return;
                }

                $version->articleContent()->update(['cover_attachment_id' => $orphan->id]);
            },
        );

        try {
            $this->assertSame(0, Artisan::call('content:purge-orphan-media', [
                '--execute' => true,
                '--older-than-hours' => 24,
            ]));
        } finally {
            ContentAttachment::flushEventListeners();
        }

        $this->assertSame(2, $retrievals);
        $this->assertDatabaseHas('content_attachments', ['id' => $orphan->id]);
        Storage::disk('content')->assertExists($orphan->storage_path);
        $this->assertSame(
            (int) $orphan->id,
            (int) $version->articleContent()->value('cover_attachment_id'),
        );
    }

    public function test_orphan_cleanup_rechecks_current_pointer_and_never_touches_historical_or_published_media(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $super = $this->user('super_admin');
        $service = app(ContentPublicationService::class);
        $item = $service
            ->createDraft($admin, $this->articlePayload('Pointer Berubah'));
        $orphan = $this->storedAttachment(
            $item->currentDraftVersion->id,
            $admin->id,
            'historical',
        );
        ContentAttachment::query()->whereKey($orphan->id)->update([
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);
        $item->forceFill(['current_draft_version_id' => null])->save();

        $published = $service->createDraft($admin, $this->articlePayload('Media Published'));
        $publishedAttachment = $this->storedAttachment(
            $published->currentDraftVersion->id,
            $admin->id,
            'published',
        );
        ContentAttachment::query()->whereKey($publishedAttachment->id)->update([
            'created_at' => now()->subDays(8),
            'updated_at' => now()->subDays(8),
        ]);
        $published = $service->submit(
            $published->currentDraftVersion,
            $admin,
            (int) $published->lock_version,
        );
        $published = $service->startReview(
            $published->currentDraftVersion,
            $super,
            (int) $published->lock_version,
        );
        $published = $service->approve(
            $published->currentDraftVersion,
            $super,
            (int) $published->lock_version,
        );
        $service->publishApproved(
            $published->currentDraftVersion,
            $super,
            (int) $published->lock_version,
        );

        $this->assertSame(0, Artisan::call('content:purge-orphan-media', [
            '--execute' => true,
            '--older-than-hours' => 24,
        ]));
        $this->assertDatabaseHas('content_attachments', ['id' => $orphan->id]);
        $this->assertDatabaseHas('content_attachments', ['id' => $publishedAttachment->id]);
        Storage::disk('content')->assertExists($orphan->storage_path);
        Storage::disk('content')->assertExists($publishedAttachment->storage_path);
    }

    public function test_cover_replacement_isolated_per_version_until_revision_is_published(): void
    {
        Storage::fake('content');
        $admin = $this->user('admin', $this->campusA);
        $super = $this->user('super_admin');
        $reporter = $this->user('reporter', $this->campusA);
        $service = app(ContentPublicationService::class);
        $item = $service->createDraft($admin, $this->articlePayload('Sampul Versioned'));
        $versionOne = $item->currentDraftVersion;
        $coverA = $this->storedAttachment($versionOne->id, $admin->id, 'cover-a');
        $versionOne->articleContent->forceFill(['cover_attachment_id' => $coverA->id])->save();
        $item = $service->submit($versionOne->fresh(), $admin, (int) $item->fresh()->lock_version);
        $item = $service->startReview($item->currentDraftVersion, $super, (int) $item->lock_version);
        $item = $service->approve($item->currentDraftVersion, $super, (int) $item->lock_version);
        $item = $service->publishApproved($item->currentDraftVersion, $super, (int) $item->lock_version);

        Sanctum::actingAs($reporter, ['*']);
        $this->getJson('/api/v1/content/articles/slug/education/'.$item->slug)
            ->assertOk()
            ->assertJsonPath('data.cover.public_id', $coverA->public_id);

        $item = $service->createRevision($item->fresh(), $admin, (int) $item->fresh()->lock_version);
        $versionTwo = $item->currentDraftVersion;
        $coverB = $this->storedAttachment($versionTwo->id, $admin->id, 'cover-b');
        $versionTwo->articleContent->forceFill(['cover_attachment_id' => $coverB->id])->save();

        $this->getJson('/api/v1/content/articles/slug/education/'.$item->slug)
            ->assertOk()
            ->assertJsonPath('data.cover.public_id', $coverA->public_id);
        $item = $service->submit($versionTwo->fresh(), $admin, (int) $item->fresh()->lock_version);
        $item = $service->startReview($item->currentDraftVersion, $super, (int) $item->lock_version);
        $item = $service->approve($item->currentDraftVersion, $super, (int) $item->lock_version);
        $item = $service->publishApproved($item->currentDraftVersion, $super, (int) $item->lock_version);

        $this->getJson('/api/v1/content/articles/slug/education/'.$item->slug)
            ->assertOk()
            ->assertJsonPath('data.cover.public_id', $coverB->public_id);
        $this->get('/api/v1/content/attachments/'.$coverA->public_id)->assertNotFound();
        $this->get('/api/v1/content/attachments/'.$coverB->public_id)->assertOk();
        Sanctum::actingAs($super, ['*']);
        $this->get('/api/v1/content/attachments/'.$coverA->public_id)->assertNotFound();
        $this->get('/api/v1/content/attachments/'.$coverB->public_id)->assertOk();
        Sanctum::actingAs($admin, ['*']);
        $this->get('/api/v1/content/attachments/'.$coverA->public_id)->assertNotFound();
    }

    private function uploadImage(string $versionPublicId, string $purpose, string $name, string $alt): string
    {
        return (string) $this->postJson('/api/v1/content-management/versions/'.$versionPublicId.'/attachments', [
            'purpose' => $purpose,
            'file' => UploadedFile::fake()->image($name, 32, 18),
            'alt_text' => $alt,
        ])->assertCreated()
            ->assertJsonMissingPath('data.storage_path')
            ->assertJsonMissingPath('data.checksum_sha256')
            ->json('data.public_id');
    }

    private function storedAttachment(
        int $versionId,
        int $uploaderId,
        string $suffix,
        ContentAttachmentPurpose $purpose = ContentAttachmentPurpose::Cover,
    ): ContentAttachment {
        $publicId = (string) Str::uuid();
        $pdf = $purpose === ContentAttachmentPurpose::Attachment;
        $extension = $pdf ? 'pdf' : 'png';
        $mime = $pdf ? 'application/pdf' : 'image/png';
        $path = 'orphan-tests/'.$publicId.'.'.$extension;
        $bytes = $pdf ? "%PDF-1.4\nprivate-{$suffix}\n%%EOF" : 'private-'.$suffix;
        Storage::disk('content')->put($path, $bytes);

        return ContentAttachment::query()->create([
            'public_id' => $publicId,
            'content_version_id' => $versionId,
            'purpose' => $purpose,
            'storage_disk' => 'content',
            'storage_path' => $path,
            'safe_filename' => 'media-'.$publicId.'.'.$extension,
            'detected_mime' => $mime,
            'extension' => $extension,
            'file_size' => strlen($bytes),
            'checksum_sha256' => hash('sha256', $bytes),
            'width' => $pdf ? null : 1,
            'height' => $pdf ? null : 1,
            'alt_text' => $pdf ? null : 'Ilustrasi aman',
            'uploader_id' => $uploaderId,
        ]);
    }

    /** @return array<string, mixed> */
    private function articlePayload(string $title): array
    {
        return [
            'content_type' => 'article',
            'section_code' => 'education',
            'category_name' => 'Keamanan Media',
            'scope' => ContentScope::Campus->value,
            'university_id' => $this->campusA->id,
            'title' => $title.' '.Str::random(6),
            'excerpt' => 'Ringkasan artikel pengujian media.',
            'document' => [
                'type' => 'doc',
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'Isi artikel pengujian media.']],
                ]],
            ],
        ];
    }

    private function user(string $roleCode, ?University $campus = null): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('code', $roleCode)->value('id'),
            'university_id' => $campus?->id,
            'is_active' => true,
            'email' => Str::uuid().'@example.test',
        ]);
    }

    private function requireGd(): void
    {
        if (! extension_loaded('gd')) {
            $this->markTestSkipped('The GD extension is required for secure Content image tests.');
        }
    }

    private function assertDocumentValidationFails(callable $callback): void
    {
        try {
            $callback();
            $this->fail('An invalid image reference was accepted.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
    }
}
