<?php

namespace Database\Seeders\Foundation;

use App\Enums\ContentLifecycleStatus;
use App\Enums\ContentScope;
use App\Enums\ContentType;
use App\Models\ArticleVersionContent;
use App\Models\ContentCategory;
use App\Models\ContentItem;
use App\Models\ContentSection;
use App\Models\ContentVersion;
use App\Models\FaqVersionContent;
use App\Support\ContentCategoryName;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContentFoundationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $sections = collect(config('content_storyboard.sections'))
                ->mapWithKeys(function (array $definition): array {
                    $section = ContentSection::query()->firstOrCreate(
                        ['code' => $definition['code']],
                        [...$definition, 'is_active' => true],
                    );

                    return [$definition['code'] => $section];
                });

            $categories = collect(config('content_storyboard.categories'))
                ->values()
                ->mapWithKeys(function (array $definition, int $index) use ($sections): array {
                    $stableSeedKey = 'storyboard.category.'.$definition['code'];
                    $section = $sections[$definition['section']];
                    $category = ContentCategory::query()
                        ->where('stable_seed_key', $stableSeedKey)
                        ->first()
                        ?? ContentCategory::query()
                            ->where('section_id', $section->id)
                            ->where('scope_key', ContentScope::Global->value)
                            ->where('normalized_name', ContentCategoryName::normalize($definition['name']))
                            ->first();

                    if ($category === null) {
                        $category = ContentCategory::query()->create([
                            'stable_seed_key' => $stableSeedKey,
                            'public_id' => (string) Str::uuid(),
                            'section_id' => $section->id,
                            'code' => $definition['code'],
                            'name' => $definition['name'],
                            'normalized_name' => ContentCategoryName::normalize($definition['name']),
                            'slug' => Str::slug($definition['name']),
                            'description' => $definition['description'],
                            'icon_code' => $definition['icon'],
                            'display_order' => ($index + 1) * 10,
                            'scope' => ContentScope::Global,
                            'scope_key' => ContentScope::Global->value,
                            'university_id' => null,
                            'is_active' => true,
                            'creator_id' => null,
                        ]);
                    } elseif (blank($category->stable_seed_key)) {
                        $category->forceFill(['stable_seed_key' => $stableSeedKey])->save();
                    }

                    return [$definition['code'] => $category];
                });

            foreach (config('content_storyboard.articles') as [$seedKey, $title, $categoryCode]) {
                $category = $categories[$categoryCode];
                $item = ContentItem::query()->firstOrCreate(
                    ['stable_seed_key' => 'storyboard.article.'.$seedKey],
                    [
                        'public_id' => (string) Str::uuid(),
                        'content_type' => ContentType::Article,
                        'section_id' => $category->section_id,
                        'category_id' => null,
                        'category_name' => $category->name,
                        'slug' => Str::slug($title).'-'.$categoryCode,
                        'scope' => ContentScope::Global,
                        'scope_key' => ContentScope::Global->value,
                        'university_id' => null,
                        'creator_id' => null,
                    ],
                );

                if ($item->versions()->exists()) {
                    continue;
                }

                $version = ContentVersion::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'content_item_id' => $item->id,
                    'version_number' => 1,
                    'lifecycle_status' => ContentLifecycleStatus::Draft,
                    'title' => $title,
                    'category_name' => $category->name,
                    'category_id' => null,
                    'excerpt' => $seedKey === 'perspective_psychology_personal_boundaries'
                        ? 'Batasan diri membantu seseorang mengenali kenyamanan, kebutuhan, dan haknya dalam berinteraksi dengan orang lain.'
                        : null,
                    'source_type' => 'storyboard_seed',
                    'seed_key' => 'storyboard.article.'.$seedKey.'.v1',
                    'requires_editorial_review' => true,
                    'editorial_note' => 'Draf awal editorial. Isi substantif wajib disusun, diverifikasi, dan disetujui sebelum publikasi.',
                ]);
                ArticleVersionContent::query()->create([
                    'content_version_id' => $version->id,
                    'document_json' => null,
                    'sanitized_html' => null,
                    'search_text' => null,
                    'estimated_reading_minutes' => 0,
                ]);
                $item->forceFill(['current_draft_version_id' => $version->id])->save();
            }

            $faqSection = $sections['faq'];
            foreach (config('content_storyboard.faqs') as $index => $question) {
                $key = 'storyboard.faq.'.Str::slug($question, '_');
                $item = ContentItem::query()->firstOrCreate(
                    ['stable_seed_key' => $key],
                    [
                        'public_id' => (string) Str::uuid(),
                        'content_type' => ContentType::Faq,
                        'section_id' => $faqSection->id,
                        'category_id' => null,
                        'slug' => Str::slug($question),
                        'scope' => ContentScope::Global,
                        'scope_key' => ContentScope::Global->value,
                        'university_id' => null,
                        'creator_id' => null,
                    ],
                );

                if ($item->versions()->exists()) {
                    continue;
                }

                $version = ContentVersion::query()->create([
                    'public_id' => (string) Str::uuid(),
                    'content_item_id' => $item->id,
                    'version_number' => 1,
                    'lifecycle_status' => ContentLifecycleStatus::Draft,
                    'title' => $question,
                    'source_type' => 'storyboard_seed',
                    'seed_key' => $key.'.v1',
                    'requires_editorial_review' => true,
                    'editorial_note' => 'Pertanyaan rencana. Jawaban wajib diverifikasi secara editorial dan legal sebelum publikasi.',
                ]);
                FaqVersionContent::query()->create([
                    'content_version_id' => $version->id,
                    'question' => $question,
                    'answer_document_json' => null,
                    'sanitized_answer_html' => null,
                    'plain_search_text' => null,
                    'display_order' => ($index + 1) * 10,
                ]);
                $item->forceFill(['current_draft_version_id' => $version->id])->save();
            }
        });
    }
}
