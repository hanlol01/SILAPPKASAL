<?php

namespace Tests\Feature;

use App\Services\ContentDocumentService;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ContentWysiwygEditorTest extends TestCase
{
    public function test_server_accepts_and_normalizes_the_complete_article_tiptap_allowlist(): void
    {
        $prepared = $this->documents()->prepareArticle([
            'type' => 'doc',
            'content' => [
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [
                    ['type' => 'text', 'text' => 'Judul bagian'],
                ]],
                ['type' => 'paragraph', 'content' => [
                    ['type' => 'text', 'text' => 'Format', 'marks' => [
                        ['type' => 'bold'],
                        ['type' => 'italic'],
                        ['type' => 'underline'],
                    ]],
                    ['type' => 'text', 'text' => ' telepon', 'marks' => [[
                        'type' => 'link',
                        'attrs' => ['href' => '  tel:+62 812-3456-7890  ', 'title' => 'Hubungi'],
                    ]]],
                ]],
                ['type' => 'bulletList', 'content' => [[
                    'type' => 'listItem',
                    'content' => [['type' => 'paragraph', 'content' => [
                        ['type' => 'text', 'text' => 'Butir'],
                    ]]],
                ]]],
                ['type' => 'orderedList', 'content' => [[
                    'type' => 'listItem',
                    'content' => [['type' => 'paragraph', 'content' => [
                        ['type' => 'text', 'text' => 'Langkah'],
                    ]]],
                ]]],
                ['type' => 'blockquote', 'content' => [[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'Kutipan']],
                ]]],
                ['type' => 'callout', 'attrs' => ['variant' => 'help'], 'content' => [[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'Bantuan']],
                ]]],
                ['type' => 'horizontalRule'],
                ['type' => 'imageReference', 'attrs' => [
                    'attachment_public_id' => '11111111-1111-4111-8111-111111111111',
                    'alt' => 'Ilustrasi lama',
                ]],
            ],
        ]);

        $this->assertSame(
            'tel:+6281234567890',
            $prepared['document']['content'][1]['content'][1]['marks'][0]['attrs']['href'],
        );
        $this->assertStringContainsString('<u>', $prepared['html']);
        $this->assertStringContainsString('href="tel:&#43;6281234567890"', $prepared['html']);
        $this->assertStringContainsString('<hr', $prepared['html']);
        $this->assertStringContainsString('rel="noopener noreferrer"', $prepared['html']);
    }

    public function test_article_and_faq_use_distinct_mark_and_link_policies(): void
    {
        $article = $this->documents()->prepareArticle([
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => 'Hubungi',
                    'marks' => [
                        ['type' => 'underline'],
                        ['type' => 'link', 'attrs' => ['href' => 'tel:+6281234567890']],
                    ],
                ]],
            ]],
        ]);

        $this->assertStringContainsString('<u>', $article['html']);
        $this->assertStringContainsString('href="tel:&#43;6281234567890"', $article['html']);

        $faq = $this->documents()->prepareFaq([
            'type' => 'doc',
            'content' => [
                ['type' => 'paragraph', 'content' => [
                    ['type' => 'text', 'text' => 'Format dasar', 'marks' => [
                        ['type' => 'bold'],
                        ['type' => 'italic'],
                    ]],
                    ['type' => 'text', 'text' => ' referensi', 'marks' => [[
                        'type' => 'link',
                        'attrs' => ['href' => 'https://example.test/faq'],
                    ]]],
                ]],
                ['type' => 'bulletList', 'content' => [[
                    'type' => 'listItem',
                    'content' => [['type' => 'paragraph', 'content' => [
                        ['type' => 'text', 'text' => 'Butir FAQ'],
                    ]]],
                ]]],
                ['type' => 'orderedList', 'content' => [[
                    'type' => 'listItem',
                    'content' => [['type' => 'paragraph', 'content' => [
                        ['type' => 'text', 'text' => 'Langkah FAQ'],
                    ]]],
                ]]],
                ['type' => 'blockquote', 'content' => [[
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'Kutipan FAQ']],
                ]]],
            ],
        ]);

        $this->assertStringContainsString('<strong>', $faq['html']);
        $this->assertStringContainsString('<em>', $faq['html']);
        $this->assertStringContainsString('<ul>', $faq['html']);
        $this->assertStringContainsString('<ol>', $faq['html']);
        $this->assertStringContainsString('<blockquote>', $faq['html']);
        $this->assertStringContainsString('href="https://example.test/faq"', $faq['html']);

        $this->assertFaqDocumentRejected([
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => 'Tidak boleh bergaris bawah',
                    'marks' => [['type' => 'underline']],
                ]],
            ]],
        ]);
        $this->assertFaqDocumentRejected($this->linkedDocument('tel:+6281234567890'));
    }

    public function test_server_reading_time_uses_unicode_word_tokens(): void
    {
        $text = implode(' ', array_fill(0, 196, 'kata')).' satu—dua naïve 東京 ١٢٣';
        $prepared = $this->documents()->prepareArticle([
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => $text]],
            ]],
        ]);
        $empty = $this->documents()->prepareArticle([
            'type' => 'doc',
            'content' => [['type' => 'paragraph']],
        ]);

        $this->assertSame(2, $prepared['reading_minutes']);
        $this->assertSame(0, $empty['reading_minutes']);
    }

    public function test_server_normalizes_legacy_article_aliases_without_touching_historical_rows(): void
    {
        $prepared = $this->documents()->prepareArticle([
            'type' => 'doc',
            'content' => [
                ['type' => 'heading_2', 'content' => [['type' => 'text', 'text' => 'Legacy H2']]],
                ['type' => 'heading_3', 'content' => [['type' => 'text', 'text' => 'Legacy H3']]],
                ['type' => 'unorderedList', 'content' => [[
                    'type' => 'listItem',
                    'content' => [['type' => 'text', 'text' => 'Legacy list']],
                ]]],
                ['type' => 'info', 'content' => [['type' => 'text', 'text' => 'Legacy info']]],
                ['type' => 'warning', 'content' => [['type' => 'text', 'text' => 'Legacy warning']]],
                ['type' => 'help', 'content' => [['type' => 'text', 'text' => 'Legacy help']]],
                ['type' => 'divider'],
            ],
        ]);

        $content = $prepared['document']['content'];
        $this->assertSame(['type' => 'heading', 'content' => [['type' => 'text', 'text' => 'Legacy H2']], 'attrs' => ['level' => 2]], $content[0]);
        $this->assertSame(3, $content[1]['attrs']['level']);
        $this->assertSame('bulletList', $content[2]['type']);
        $this->assertSame('paragraph', $content[2]['content'][0]['content'][0]['type']);
        $this->assertSame('information', $content[3]['attrs']['variant']);
        $this->assertSame('warning', $content[4]['attrs']['variant']);
        $this->assertSame('help', $content[5]['attrs']['variant']);
        $this->assertSame('horizontalRule', $content[6]['type']);
    }

    public function test_server_rejects_unknown_nodes_marks_h1_foreign_attributes_and_callout_variants(): void
    {
        $invalidNodes = [
            ['type' => 'heading', 'attrs' => ['level' => 1], 'content' => [['type' => 'text', 'text' => 'H1']]],
            ['type' => 'iframe', 'attrs' => ['src' => 'https://example.test']],
            ['type' => 'paragraph', 'attrs' => ['onclick' => 'alert(1)'], 'content' => []],
            ['type' => 'callout', 'attrs' => ['variant' => 'danger'], 'content' => [
                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Danger']]],
            ]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Code', 'marks' => [
                ['type' => 'code'],
            ]]]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Styled', 'marks' => [[
                'type' => 'bold',
                'attrs' => ['style' => 'color:red'],
            ]]]]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Link', 'marks' => [[
                'type' => 'link',
                'attrs' => ['href' => 'https://example.test', 'target' => '_self'],
            ]]]]],
        ];

        foreach ($invalidNodes as $node) {
            $this->assertDocumentRejected(['type' => 'doc', 'content' => [$node]]);
        }
    }

    public function test_server_rejects_dangerous_malformed_and_unknown_link_protocols(): void
    {
        foreach ([
            'javascript:alert(1)',
            ' JaVaScRiPt:alert(1) ',
            "java\nscript:alert(1)",
            'data:text/html;base64,PHNjcmlwdD4=',
            'vbscript:msgbox(1)',
            'file:///etc/passwd',
            '//example.test/path',
            'https://',
            'mailto:not-an-email',
            'tel:alert(1)',
        ] as $href) {
            $this->assertDocumentRejected($this->linkedDocument($href));
        }
    }

    public function test_server_enforces_payload_text_node_total_text_node_count_and_depth_limits(): void
    {
        $tooManyNodes = [];
        for ($index = 0; $index < 501; $index++) {
            $tooManyNodes[] = ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => 'node'],
            ]];
        }

        $tooMuchText = [];
        for ($index = 0; $index < 11; $index++) {
            $tooMuchText[] = ['type' => 'paragraph', 'content' => [
                ['type' => 'text', 'text' => str_repeat('a', 20000)],
            ]];
        }

        $tooDeep = ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'deep']]];
        for ($index = 0; $index < 13; $index++) {
            $tooDeep = [
                'type' => 'callout',
                'attrs' => ['variant' => 'information'],
                'content' => [$tooDeep],
            ];
        }

        foreach ([
            ['type' => 'doc', 'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => str_repeat('a', 500001)]],
            ]]],
            ['type' => 'doc', 'content' => [[
                'type' => 'paragraph',
                'content' => [['type' => 'text', 'text' => str_repeat('a', 20001)]],
            ]]],
            ['type' => 'doc', 'content' => $tooMuchText],
            ['type' => 'doc', 'content' => $tooManyNodes],
            ['type' => 'doc', 'content' => [$tooDeep]],
        ] as $document) {
            $this->assertDocumentRejected($document);
        }
    }

    /** @return array<string, mixed> */
    private function linkedDocument(string $href): array
    {
        return [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => 'Link',
                    'marks' => [['type' => 'link', 'attrs' => ['href' => $href]]],
                ]],
            ]],
        ];
    }

    /** @param array<string, mixed> $document */
    private function assertDocumentRejected(array $document): void
    {
        try {
            $this->documents()->prepareArticle($document);
            $this->fail('An invalid Article document was accepted.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    /** @param array<string, mixed> $document */
    private function assertFaqDocumentRejected(array $document): void
    {
        try {
            $this->documents()->prepareFaq($document);
            $this->fail('An invalid FAQ document was accepted.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }

    private function documents(): ContentDocumentService
    {
        return app(ContentDocumentService::class);
    }
}
