<?php

namespace Tests\Feature;

use App\Contracts\ContentImageProcessor;
use App\Enums\ContentAttachmentPurpose;
use App\Enums\ContentScope;
use App\Models\ContentCategory;
use App\Models\ContentItem;
use App\Models\Role;
use App\Models\University;
use App\Models\User;
use App\Services\ContentPublicationService;
use Database\Seeders\Foundation\ContentFoundationSeeder;
use Database\Seeders\RbacSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class ContentImageFailClosedTest extends TestCase
{
    use RefreshDatabase;

    private University $campus;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RbacSeeder::class);
        $this->seed(ContentFoundationSeeder::class);
        $this->campus = University::query()->create([
            'code' => 'C1-IMAGE',
            'name' => 'Universitas Uji Gambar',
            'type' => 'universitas',
            'is_active' => true,
        ]);
    }

    #[DataProvider('imageFormats')]
    public function test_supported_image_formats_fail_closed_without_verified_processor(
        string $filename,
        string $expectedMime,
        string $base64,
    ): void {
        Storage::fake('content');
        config()->set('content.attachments.image_uploads_enabled', true);
        $processor = new RecordingUnavailableImageProcessor;
        $this->app->instance(ContentImageProcessor::class, $processor);

        $admin = $this->admin();
        $version = $this->draftArticle($admin)->currentDraftVersion;
        Sanctum::actingAs($admin, ['*']);

        $bytes = base64_decode($base64, true);
        $this->assertNotFalse($bytes);
        $file = UploadedFile::fake()->createWithContent($filename, $bytes);
        $this->assertSame($expectedMime, $file->getMimeType());

        $this->postJson('/api/v1/content-management/versions/'.$version->public_id.'/attachments', [
            'purpose' => ContentAttachmentPurpose::Cover->value,
            'file' => $file,
            'alt_text' => 'Ilustrasi uji netral',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('content_attachments', 0);
        $this->assertSame([], Storage::disk('content')->allFiles());
        $this->assertFalse($processor->reencodeCalled);
        $this->assertSame([], $processor->temporaryArtifacts);
    }

    public function test_general_pdf_attachment_remains_allowed(): void
    {
        Storage::fake('content');
        config()->set('content.attachments.image_uploads_enabled', true);
        $this->app->instance(ContentImageProcessor::class, new RecordingUnavailableImageProcessor);

        $admin = $this->admin();
        $version = $this->draftArticle($admin)->currentDraftVersion;
        Sanctum::actingAs($admin, ['*']);

        $this->postJson('/api/v1/content-management/versions/'.$version->public_id.'/attachments', [
            'purpose' => ContentAttachmentPurpose::Attachment->value,
            'file' => UploadedFile::fake()->createWithContent('dokumen-uji.pdf', "%PDF-1.4\n%%EOF"),
        ])->assertCreated();

        $this->assertDatabaseCount('content_attachments', 1);
        $this->assertCount(1, Storage::disk('content')->allFiles());
    }

    public function test_capabilities_only_advertise_runtime_supported_image_formats(): void
    {
        config()->set('content.attachments.image_uploads_enabled', true);
        $this->app->instance(
            ContentImageProcessor::class,
            new CapabilityOnlyImageProcessor(['image/png']),
        );
        Sanctum::actingAs($this->admin(), ['*']);

        $this->getJson('/api/v1/content-management/capabilities')
            ->assertOk()
            ->assertJsonPath('data.image_upload_available', true)
            ->assertJsonPath('data.image_formats', ['image/png'])
            ->assertJsonPath('data.cover_max_bytes', 5 * 1024 * 1024)
            ->assertJsonPath('data.inline_image_max_bytes', 10 * 1024 * 1024)
            ->assertJsonPath('data.alt_text_max_length', 500);

    }

    public function test_capabilities_hide_formats_when_verified_processor_is_unavailable(): void
    {
        config()->set('content.attachments.image_uploads_enabled', true);
        $this->app->instance(ContentImageProcessor::class, new RecordingUnavailableImageProcessor);
        Sanctum::actingAs($this->admin(), ['*']);

        $this->getJson('/api/v1/content-management/capabilities')
            ->assertOk()
            ->assertJsonPath('data.image_upload_available', false)
            ->assertJsonPath('data.image_formats', []);
    }

    public function test_request_and_capability_share_configured_image_source_limit(): void
    {
        Storage::fake('content');
        config()->set('content.attachments.image_uploads_enabled', true);
        config()->set('content.attachments.cover_max_bytes', 1024);
        config()->set('content.attachments.max_image_source_bytes', 1024);
        $this->app->instance(
            ContentImageProcessor::class,
            new CapabilityOnlyImageProcessor(['image/png']),
        );
        $admin = $this->admin();
        $version = $this->draftArticle($admin)->currentDraftVersion;
        Sanctum::actingAs($admin, ['*']);

        $this->getJson('/api/v1/content-management/capabilities')
            ->assertOk()
            ->assertJsonPath('data.cover_max_bytes', 1024)
            ->assertJsonPath('data.max_image_source_bytes', 1024);
        $this->postJson('/api/v1/content-management/versions/'.$version->public_id.'/attachments', [
            'purpose' => ContentAttachmentPurpose::Cover->value,
            'file' => UploadedFile::fake()->createWithContent(
                'oversized.png',
                str_repeat('x', 1025),
            ),
            'alt_text' => 'Melampaui batas konfigurasi',
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('content_attachments', 0);
    }

    /** @return array<string, array{string, string, string}> */
    public static function imageFormats(): array
    {
        $jpeg = '/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABBQJ//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPwF//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPwF//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQAGPwJ//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPyF//9oADAMBAAIAAwAAABAP/8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAwEBPxB//8QAFBEBAAAAAAAAAAAAAAAAAAAAAP/aAAgBAgEBPxB//8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxB//9k=';

        return [
            'JPG' => ['gambar-uji.jpg', 'image/jpeg', $jpeg],
            'JPEG' => ['gambar-uji.jpeg', 'image/jpeg', $jpeg],
            'PNG' => ['gambar-uji.png', 'image/png', 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Wl6ZQAAAABJRU5ErkJggg=='],
            'WebP' => ['gambar-uji.webp', 'image/webp', 'UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEAAUAmJaQAA3AA/vuUAAA='],
        ];
    }

    private function draftArticle(User $admin): ContentItem
    {
        $category = ContentCategory::query()->where('code', 'perspective_psychology')->firstOrFail();

        return app(ContentPublicationService::class)->createDraft($admin, [
            'content_type' => 'article',
            'section_code' => 'education',
            'category_public_id' => $category->public_id,
            'scope' => ContentScope::Campus->value,
            'university_id' => $this->campus->id,
            'title' => 'Artikel Uji Gambar '.Str::random(8),
            'excerpt' => 'Ringkasan netral untuk pengujian.',
            'document' => [
                'type' => 'doc',
                'content' => [[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'Isi edukasi netral.']],
                ]],
            ],
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create([
            'role_id' => Role::query()->where('code', 'admin')->value('id'),
            'university_id' => $this->campus->id,
            'is_active' => true,
            'email' => Str::uuid().'@example.test',
        ]);
    }
}

final class RecordingUnavailableImageProcessor implements ContentImageProcessor
{
    public bool $reencodeCalled = false;

    /** @var list<string> */
    public array $temporaryArtifacts = [];

    public function isAvailable(): bool
    {
        return false;
    }

    public function supportedMimeTypes(): array
    {
        return [];
    }

    public function reencode(UploadedFile $file): UploadedFile
    {
        $this->reencodeCalled = true;
        $this->temporaryArtifacts[] = 'unexpected-processor-output';

        throw new LogicException('The unavailable processor must never be called.');
    }

    public function release(UploadedFile $processed): void
    {
        $this->temporaryArtifacts = [];
    }
}

final class CapabilityOnlyImageProcessor implements ContentImageProcessor
{
    /** @param list<string> $formats */
    public function __construct(private readonly array $formats) {}

    public function isAvailable(): bool
    {
        return $this->formats !== [];
    }

    public function supportedMimeTypes(): array
    {
        return $this->formats;
    }

    public function reencode(UploadedFile $file): UploadedFile
    {
        throw new LogicException('Capability-only processor cannot re-encode images.');
    }

    public function release(UploadedFile $processed): void
    {
        // No processor-owned temporary output.
    }
}
