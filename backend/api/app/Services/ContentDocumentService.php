<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use JsonException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class ContentDocumentService
{
    private const MAX_NODES = 1000;

    private const MAX_DEPTH = 12;

    private const MAX_TEXT_LENGTH = 200000;

    private const MAX_DOCUMENT_BYTES = 500000;

    private const MAX_TEXT_NODE_LENGTH = 20000;

    private const MAX_MARKS_PER_TEXT_NODE = 4;

    private const MAX_TOTAL_MARKS = 2000;

    private const MAX_LINK_LENGTH = 2048;

    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowElement('p')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('ol')
            ->allowElement('ul')
            ->allowElement('li')
            ->allowElement('blockquote')
            ->allowElement('a', ['href', 'title'])
            ->allowElement('aside', ['data-callout'])
            ->allowElement('figure', ['data-attachment-id'])
            ->allowElement('figcaption')
            ->allowElement('hr')
            ->allowLinkSchemes(['http', 'https', 'mailto'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->withMaxInputLength(500000);

        $this->sanitizer = new HtmlSanitizer($config);
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array{document: array<string, mixed>, html: string, text: string, reading_minutes: int}
     */
    public function prepareArticle(array $document): array
    {
        return $this->prepare($document, false);
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array{document: array<string, mixed>, html: string, text: string, reading_minutes: int}
     */
    public function prepareFaq(array $document): array
    {
        return $this->prepare($document, true);
    }

    public function safeUrl(string $url, bool $allowMailto = true): bool
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, '//') || preg_match('/[\x00-\x1F\x7F]/u', $url) === 1) {
            return false;
        }

        $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $allowed = $allowMailto ? ['http', 'https', 'mailto'] : ['http', 'https'];

        if (! in_array($scheme, $allowed, true)) {
            return false;
        }

        if ($scheme === 'mailto') {
            $address = substr($url, 7);

            return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array{document: array<string, mixed>, html: string, text: string, reading_minutes: int}
     */
    private function prepare(array $document, bool $faq): array
    {
        try {
            $serialized = json_encode($document, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['document' => ['The content document is not valid UTF-8 JSON.']]);
        }
        if (strlen($serialized) > self::MAX_DOCUMENT_BYTES) {
            throw ValidationException::withMessages(['document' => ['The serialized content document is too large.']]);
        }

        $this->assertAllowedKeys($document, ['type', 'content'], 'document root');
        if (($document['type'] ?? null) !== 'doc' || ! is_array($document['content'] ?? null)) {
            throw ValidationException::withMessages(['document' => ['A controlled content document is required.']]);
        }

        $counter = 0;
        $markCounter = 0;
        $textParts = [];
        $html = '';

        foreach ($document['content'] as $node) {
            if (! is_array($node)) {
                throw ValidationException::withMessages(['document' => ['Every document node must be an object.']]);
            }

            $html .= $this->renderNode($node, 1, $counter, $markCounter, $textParts, $faq);
        }

        $text = trim(preg_replace('/\s+/u', ' ', implode(' ', $textParts)) ?? '');

        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            throw ValidationException::withMessages(['document' => ['The content document is too long.']]);
        }

        $wordCount = $text === '' ? 0 : preg_match_all('/[\pL\pN]+/u', $text);

        return [
            'document' => $document,
            'html' => $this->sanitizer->sanitize($html),
            'text' => $text,
            'reading_minutes' => $wordCount > 0 ? max(1, (int) ceil($wordCount / 200)) : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $textParts
     */
    private function renderNode(
        array $node,
        int $depth,
        int &$counter,
        int &$markCounter,
        array &$textParts,
        bool $faq,
    ): string {
        $counter++;

        if ($counter > self::MAX_NODES || $depth > self::MAX_DEPTH) {
            throw ValidationException::withMessages(['document' => ['The content document is too complex.']]);
        }

        $type = $node['type'] ?? null;

        if (! is_string($type)) {
            throw ValidationException::withMessages(['document' => ['Each document node requires a type.']]);
        }

        if ($type === 'text') {
            return $this->renderText($node, $markCounter, $textParts);
        }

        $allowedKeys = match ($type) {
            'paragraph', 'orderedList', 'unorderedList', 'bulletList', 'listItem', 'blockquote' => ['type', 'content'],
            'heading', 'callout' => ['type', 'attrs', 'content'],
            'imageReference' => ['type', 'attrs'],
            'divider' => ['type'],
            default => throw ValidationException::withMessages(['document' => ["Unsupported document node: {$type}."]]),
        };
        $this->assertAllowedKeys($node, $allowedKeys, "{$type} node");

        $children = $node['content'] ?? [];

        if (! is_array($children)) {
            throw ValidationException::withMessages(['document' => ['Node content must be an array.']]);
        }

        $inner = '';
        foreach ($children as $child) {
            if (! is_array($child)) {
                throw ValidationException::withMessages(['document' => ['Every child node must be an object.']]);
            }
            $inner .= $this->renderNode($child, $depth + 1, $counter, $markCounter, $textParts, $faq);
        }

        return match ($type) {
            'paragraph' => '<p>'.$inner.'</p>',
            'heading' => $this->renderHeading($node, $inner, $faq),
            'orderedList' => '<ol>'.$inner.'</ol>',
            'unorderedList', 'bulletList' => '<ul>'.$inner.'</ul>',
            'listItem' => '<li>'.$inner.'</li>',
            'blockquote' => '<blockquote>'.$inner.'</blockquote>',
            'callout' => $this->renderCallout($node, $inner, $faq),
            'imageReference' => $this->renderImageReference($node, $faq),
            'divider' => $faq
                ? throw ValidationException::withMessages(['document' => ['FAQ answers do not support dividers.']])
                : '<hr>',
            default => throw ValidationException::withMessages(['document' => ["Unsupported document node: {$type}."]]),
        };
    }

    /** @param array<string, mixed> $node */
    private function renderText(array $node, int &$markCounter, array &$textParts): string
    {
        $this->assertAllowedKeys($node, ['type', 'text', 'marks'], 'text node');
        $text = $node['text'] ?? null;

        if (! is_string($text)) {
            throw ValidationException::withMessages(['document' => ['Text nodes require text.']]);
        }
        if (mb_strlen($text) > self::MAX_TEXT_NODE_LENGTH) {
            throw ValidationException::withMessages(['document' => ['A content text node is too long.']]);
        }

        $textParts[] = $text;
        $rendered = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $marks = $node['marks'] ?? [];

        if (! is_array($marks)) {
            throw ValidationException::withMessages(['document' => ['Text marks must be an array.']]);
        }
        if (count($marks) > self::MAX_MARKS_PER_TEXT_NODE) {
            throw ValidationException::withMessages(['document' => ['A text node contains too many marks.']]);
        }
        $markCounter += count($marks);
        if ($markCounter > self::MAX_TOTAL_MARKS) {
            throw ValidationException::withMessages(['document' => ['The content document contains too many marks.']]);
        }

        foreach ($marks as $mark) {
            if (! is_array($mark) || ! is_string($mark['type'] ?? null)) {
                throw ValidationException::withMessages(['document' => ['Each text mark requires a type.']]);
            }

            $this->assertAllowedKeys(
                $mark,
                $mark['type'] === 'link' ? ['type', 'attrs'] : ['type'],
                'text mark',
            );

            $rendered = match ($mark['type']) {
                'bold' => '<strong>'.$rendered.'</strong>',
                'italic' => '<em>'.$rendered.'</em>',
                'link' => $this->renderLinkMark($mark, $rendered),
                default => throw ValidationException::withMessages(['document' => ['Unsupported text mark.']]),
            };
        }

        return $rendered;
    }

    /** @param array<string, mixed> $node */
    private function renderHeading(array $node, string $inner, bool $faq): string
    {
        if ($faq) {
            throw ValidationException::withMessages(['document' => ['FAQ answers do not support headings.']]);
        }

        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        $this->assertAllowedKeys($attrs, ['level'], 'heading attributes');
        $level = $attrs['level'] ?? null;

        if (! in_array($level, [2, 3], true)) {
            throw ValidationException::withMessages(['document' => ['Article headings must use H2 or H3. H1 is reserved for the page title.']]);
        }

        return "<h{$level}>{$inner}</h{$level}>";
    }

    /** @param array<string, mixed> $node */
    private function renderCallout(array $node, string $inner, bool $faq): string
    {
        if ($faq) {
            throw ValidationException::withMessages(['document' => ['FAQ answers do not support callouts.']]);
        }

        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        $this->assertAllowedKeys($attrs, ['variant'], 'callout attributes');
        $variant = $attrs['variant'] ?? null;

        if (! in_array($variant, ['information', 'warning', 'help'], true)) {
            throw ValidationException::withMessages(['document' => ['Unsupported callout variant.']]);
        }

        return '<aside data-callout="'.htmlspecialchars($variant, ENT_QUOTES, 'UTF-8').'">'.$inner.'</aside>';
    }

    /** @param array<string, mixed> $node */
    private function renderImageReference(array $node, bool $faq): string
    {
        if ($faq) {
            throw ValidationException::withMessages(['document' => ['FAQ answers do not support image references.']]);
        }

        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        $this->assertAllowedKeys($attrs, ['attachment_public_id', 'alt'], 'image reference attributes');
        $publicId = $attrs['attachment_public_id'] ?? null;
        $alt = $attrs['alt'] ?? '';

        if (! is_string($publicId) || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $publicId) !== 1) {
            throw ValidationException::withMessages(['document' => ['Image references require a valid attachment public identifier.']]);
        }

        if (! is_string($alt) || mb_strlen($alt) > 500) {
            throw ValidationException::withMessages(['document' => ['Image alternative text is invalid.']]);
        }

        return '<figure data-attachment-id="'.htmlspecialchars($publicId, ENT_QUOTES, 'UTF-8').'">'
            .'<figcaption>'.htmlspecialchars($alt, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'</figcaption></figure>';
    }

    /** @param array<string, mixed> $mark */
    private function renderLinkMark(array $mark, string $inner): string
    {
        $attrs = is_array($mark['attrs'] ?? null) ? $mark['attrs'] : [];
        $this->assertAllowedKeys($attrs, ['href', 'title'], 'link attributes');
        $href = $attrs['href'] ?? null;
        $title = $attrs['title'] ?? null;

        if (! is_string($href) || mb_strlen($href) > self::MAX_LINK_LENGTH || ! $this->safeUrl($href)) {
            throw ValidationException::withMessages(['document' => ['Unsafe or malformed link protocol.']]);
        }

        if ($title !== null && (! is_string($title) || mb_strlen($title) > 255)) {
            throw ValidationException::withMessages(['document' => ['Link title is invalid.']]);
        }

        $titleAttribute = is_string($title) && $title !== ''
            ? ' title="'.htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"'
            : '';

        return '<a href="'.htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').'"'.$titleAttribute.'>'.$inner.'</a>';
    }

    /** @param array<string, mixed> $value @param list<string> $allowed */
    private function assertAllowedKeys(array $value, array $allowed, string $context): void
    {
        $unexpected = array_diff(array_keys($value), $allowed);
        if ($unexpected !== []) {
            throw ValidationException::withMessages([
                'document' => ["Unsupported field in {$context}: ".implode(', ', $unexpected).'.'],
            ]);
        }
    }
}
