<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use JsonException;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class ContentDocumentService
{
    /**
     * Article and FAQ deliberately share one validator implementation but not
     * one content contract. Keep every capability difference explicit here.
     *
     * @var array{
     *     name: string,
     *     block_nodes: list<string>,
     *     marks: list<string>,
     *     allow_mailto: bool,
     *     allow_tel: bool
     * }
     */
    private const ARTICLE_POLICY = [
        'name' => 'article',
        'block_nodes' => [
            'paragraph',
            'heading',
            'orderedList',
            'bulletList',
            'blockquote',
            'callout',
            'horizontalRule',
            'imageReference',
        ],
        'marks' => ['bold', 'italic', 'underline', 'link'],
        'allow_mailto' => true,
        'allow_tel' => true,
    ];

    /**
     * @var array{
     *     name: string,
     *     block_nodes: list<string>,
     *     marks: list<string>,
     *     allow_mailto: bool,
     *     allow_tel: bool
     * }
     */
    private const FAQ_POLICY = [
        'name' => 'faq',
        'block_nodes' => ['paragraph', 'orderedList', 'bulletList', 'blockquote'],
        'marks' => ['bold', 'italic', 'link'],
        'allow_mailto' => true,
        'allow_tel' => false,
    ];

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
            ->allowElement('u')
            ->allowElement('ol')
            ->allowElement('ul')
            ->allowElement('li')
            ->allowElement('blockquote')
            ->allowElement('a', ['href', 'title'])
            ->allowElement('aside', ['data-callout'])
            ->allowElement('figure', ['data-attachment-id'])
            ->allowElement('figcaption')
            ->allowElement('hr')
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
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
        return $this->prepare($document, self::ARTICLE_POLICY);
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array{document: array<string, mixed>, html: string, text: string, reading_minutes: int}
     */
    public function prepareFaq(array $document): array
    {
        return $this->prepare($document, self::FAQ_POLICY);
    }

    public function safeUrl(string $url, bool $allowMailto = true, bool $allowTel = true): bool
    {
        $url = trim($url);

        if ($url === '' || str_starts_with($url, '//') || preg_match('/[\x00-\x1F\x7F]/u', $url) === 1) {
            return false;
        }

        $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $allowed = ['http', 'https'];
        if ($allowMailto) {
            $allowed[] = 'mailto';
        }
        if ($allowTel) {
            $allowed[] = 'tel';
        }

        if (! in_array($scheme, $allowed, true)) {
            return false;
        }

        if ($scheme === 'mailto') {
            $address = substr($url, strpos($url, ':') + 1);

            return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
        }

        if ($scheme === 'tel') {
            $number = substr($url, strpos($url, ':') + 1);

            return preg_match('/^\+?[0-9](?:[0-9(). -]{1,28}[0-9])?(?:;ext=[0-9]{1,10})?$/', $number) === 1;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array{document: array<string, mixed>, html: string, text: string, reading_minutes: int}
     */
    /**
     * @param  array{
     *     name: string,
     *     block_nodes: list<string>,
     *     marks: list<string>,
     *     allow_mailto: bool,
     *     allow_tel: bool
     * }  $policy
     */
    private function prepare(array $document, array $policy): array
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
        $document = $this->normalizeDocument($document);
        $this->assertRootContent($document['content'], $policy);

        $counter = 0;
        $markCounter = 0;
        $textParts = [];
        $html = '';

        foreach ($document['content'] as $node) {
            if (! is_array($node)) {
                throw ValidationException::withMessages(['document' => ['Every document node must be an object.']]);
            }

            $html .= $this->renderNode($node, 1, $counter, $markCounter, $textParts, $policy);
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
     * @param  array{
     *     name: string,
     *     block_nodes: list<string>,
     *     marks: list<string>,
     *     allow_mailto: bool,
     *     allow_tel: bool
     * }  $policy
     */
    private function renderNode(
        array $node,
        int $depth,
        int &$counter,
        int &$markCounter,
        array &$textParts,
        array $policy,
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
            return $this->renderText($node, $markCounter, $textParts, $policy);
        }

        if ($type !== 'listItem' && ! in_array($type, $policy['block_nodes'], true)) {
            throw ValidationException::withMessages([
                'document' => ["Unsupported {$policy['name']} document node: {$type}."],
            ]);
        }

        $allowedKeys = match ($type) {
            'paragraph', 'orderedList', 'bulletList', 'listItem', 'blockquote' => ['type', 'content'],
            'heading', 'callout' => ['type', 'attrs', 'content'],
            'imageReference' => ['type', 'attrs'],
            'horizontalRule' => ['type'],
            default => throw ValidationException::withMessages(['document' => ["Unsupported document node: {$type}."]]),
        };
        $this->assertAllowedKeys($node, $allowedKeys, "{$type} node");

        $children = $node['content'] ?? [];

        if (! is_array($children)) {
            throw ValidationException::withMessages(['document' => ['Node content must be an array.']]);
        }
        $this->assertNodeContent($type, $children, $policy);

        $inner = '';
        foreach ($children as $child) {
            if (! is_array($child)) {
                throw ValidationException::withMessages(['document' => ['Every child node must be an object.']]);
            }
            $inner .= $this->renderNode($child, $depth + 1, $counter, $markCounter, $textParts, $policy);
        }

        return match ($type) {
            'paragraph' => '<p>'.$inner.'</p>',
            'heading' => $this->renderHeading($node, $inner),
            'orderedList' => '<ol>'.$inner.'</ol>',
            'bulletList' => '<ul>'.$inner.'</ul>',
            'listItem' => '<li>'.$inner.'</li>',
            'blockquote' => '<blockquote>'.$inner.'</blockquote>',
            'callout' => $this->renderCallout($node, $inner),
            'imageReference' => $this->renderImageReference($node),
            'horizontalRule' => '<hr>',
            default => throw ValidationException::withMessages(['document' => ["Unsupported document node: {$type}."]]),
        };
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  array{
     *     name: string,
     *     block_nodes: list<string>,
     *     marks: list<string>,
     *     allow_mailto: bool,
     *     allow_tel: bool
     * }  $policy
     */
    private function renderText(array $node, int &$markCounter, array &$textParts, array $policy): string
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
            if (! in_array($mark['type'], $policy['marks'], true)) {
                throw ValidationException::withMessages([
                    'document' => ["Unsupported {$policy['name']} text mark."],
                ]);
            }

            $this->assertAllowedKeys(
                $mark,
                $mark['type'] === 'link' ? ['type', 'attrs'] : ['type'],
                'text mark',
            );

            $rendered = match ($mark['type']) {
                'bold' => '<strong>'.$rendered.'</strong>',
                'italic' => '<em>'.$rendered.'</em>',
                'underline' => '<u>'.$rendered.'</u>',
                'link' => $this->renderLinkMark($mark, $rendered, $policy),
                default => throw ValidationException::withMessages(['document' => ['Unsupported text mark.']]),
            };
        }

        return $rendered;
    }

    /** @param array<string, mixed> $node */
    private function renderHeading(array $node, string $inner): string
    {
        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        $this->assertAllowedKeys($attrs, ['level'], 'heading attributes');
        $level = $attrs['level'] ?? null;

        if (! in_array($level, [2, 3], true)) {
            throw ValidationException::withMessages(['document' => ['Article headings must use H2 or H3. H1 is reserved for the page title.']]);
        }

        return "<h{$level}>{$inner}</h{$level}>";
    }

    /** @param array<string, mixed> $node */
    private function renderCallout(array $node, string $inner): string
    {
        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        $this->assertAllowedKeys($attrs, ['variant'], 'callout attributes');
        $variant = $attrs['variant'] ?? null;

        if (! in_array($variant, ['information', 'warning', 'help'], true)) {
            throw ValidationException::withMessages(['document' => ['Unsupported callout variant.']]);
        }

        return '<aside data-callout="'.htmlspecialchars($variant, ENT_QUOTES, 'UTF-8').'">'.$inner.'</aside>';
    }

    /** @param array<string, mixed> $node */
    private function renderImageReference(array $node): string
    {
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

    /**
     * @param  array<string, mixed>  $mark
     * @param  array{
     *     name: string,
     *     block_nodes: list<string>,
     *     marks: list<string>,
     *     allow_mailto: bool,
     *     allow_tel: bool
     * }  $policy
     */
    private function renderLinkMark(array $mark, string $inner, array $policy): string
    {
        $attrs = is_array($mark['attrs'] ?? null) ? $mark['attrs'] : [];
        $this->assertAllowedKeys($attrs, ['href', 'title'], 'link attributes');
        $href = $attrs['href'] ?? null;
        $title = $attrs['title'] ?? null;

        if (! is_string($href)
            || mb_strlen($href) > self::MAX_LINK_LENGTH
            || ! $this->safeUrl($href, $policy['allow_mailto'], $policy['allow_tel'])) {
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

    /**
     * Normalize only documented legacy aliases and safe scalar values. Unknown
     * keys remain present so the strict validator rejects them deterministically.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function normalizeDocument(array $document): array
    {
        $document['content'] = array_map(
            fn (mixed $node): mixed => is_array($node) ? $this->normalizeNode($node) : $node,
            $document['content'],
        );

        return $document;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function normalizeNode(array $node): array
    {
        $type = $node['type'] ?? null;
        if (! is_string($type)) {
            return $node;
        }

        $normalizedType = match ($type) {
            'unorderedList' => 'bulletList',
            'divider' => 'horizontalRule',
            'heading_2', 'heading_3' => 'heading',
            'info', 'warning', 'help' => 'callout',
            default => $type,
        };
        $node['type'] = $normalizedType;

        if ($type === 'heading_2' || $type === 'heading_3') {
            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            $node['attrs'] = $attrs + ['level' => $type === 'heading_2' ? 2 : 3];
        }
        if (in_array($type, ['info', 'warning', 'help'], true)) {
            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
            $node['attrs'] = $attrs + ['variant' => $type === 'info' ? 'information' : $type];
        }

        if ($normalizedType === 'text' && is_array($node['marks'] ?? null)) {
            $node['marks'] = array_map(function (mixed $mark): mixed {
                if (! is_array($mark) || ($mark['type'] ?? null) !== 'link') {
                    return $mark;
                }

                if (is_array($mark['attrs'] ?? null) && is_string($mark['attrs']['href'] ?? null)) {
                    $mark['attrs']['href'] = $this->normalizeUrl($mark['attrs']['href']);
                }

                return $mark;
            }, $node['marks']);
        }

        if (is_array($node['content'] ?? null)) {
            $children = [];
            foreach ($node['content'] as $child) {
                $children[] = is_array($child) ? $this->normalizeNode($child) : $child;
            }
            if (in_array($normalizedType, ['listItem', 'blockquote', 'callout'], true)) {
                $children = $this->wrapInlineRuns($children);
            }
            $node['content'] = $children;
        }

        return $node;
    }

    private function normalizeUrl(string $url): string
    {
        $url = trim($url);
        $separator = strpos($url, ':');
        if ($separator === false) {
            return $url;
        }

        $scheme = mb_strtolower(substr($url, 0, $separator));
        $value = substr($url, $separator + 1);
        if ($scheme === 'tel'
            && preg_match('/^(\+?[0-9](?:[0-9(). -]{1,28}[0-9])?)(;ext=[0-9]{1,10})?$/', $value, $matches) === 1) {
            $value = preg_replace('/[(). -]/', '', $matches[1]).($matches[2] ?? '');
        }

        return $scheme.':'.$value;
    }

    /**
     * @param  list<mixed>  $children
     * @return list<mixed>
     */
    private function wrapInlineRuns(array $children): array
    {
        $normalized = [];
        $inline = [];
        $flush = static function () use (&$normalized, &$inline): void {
            if ($inline === []) {
                return;
            }
            $normalized[] = ['type' => 'paragraph', 'content' => $inline];
            $inline = [];
        };

        foreach ($children as $child) {
            if (is_array($child) && ($child['type'] ?? null) === 'text') {
                $inline[] = $child;

                continue;
            }

            $flush();
            $normalized[] = $child;
        }
        $flush();

        return $normalized;
    }

    /**
     * @param  list<mixed>  $children
     * @param  array{
     *     name: string,
     *     block_nodes: list<string>,
     *     marks: list<string>,
     *     allow_mailto: bool,
     *     allow_tel: bool
     * }  $policy
     */
    private function assertRootContent(array $children, array $policy): void
    {
        $this->assertChildrenTypes(
            $children,
            $policy['block_nodes'],
            'document root',
        );
    }

    /**
     * @param  list<mixed>  $children
     * @param  array{
     *     name: string,
     *     block_nodes: list<string>,
     *     marks: list<string>,
     *     allow_mailto: bool,
     *     allow_tel: bool
     * }  $policy
     */
    private function assertNodeContent(string $type, array $children, array $policy): void
    {
        match ($type) {
            'paragraph', 'heading' => $this->assertChildrenTypes($children, ['text'], "{$type} node"),
            'orderedList', 'bulletList' => $this->assertNonEmptyChildrenTypes($children, ['listItem'], "{$type} node"),
            'listItem' => $this->assertListItemContent($children, $policy),
            'blockquote', 'callout' => $this->assertNonEmptyChildrenTypes(
                $children,
                $policy['block_nodes'],
                "{$type} node",
            ),
            'horizontalRule', 'imageReference' => $children === []
                ? null
                : throw ValidationException::withMessages([
                    'document' => ["{$type} nodes cannot contain child nodes."],
                ]),
            default => null,
        };
    }

    /**
     * @param  list<mixed>  $children
     * @param  array{
     *     name: string,
     *     block_nodes: list<string>,
     *     marks: list<string>,
     *     allow_mailto: bool,
     *     allow_tel: bool
     * }  $policy
     */
    private function assertListItemContent(array $children, array $policy): void
    {
        $this->assertNonEmptyChildrenTypes(
            $children,
            $policy['block_nodes'],
            'listItem node',
        );
        if (($children[0]['type'] ?? null) !== 'paragraph') {
            throw ValidationException::withMessages([
                'document' => ['A list item must begin with a paragraph.'],
            ]);
        }
    }

    /**
     * @param  list<mixed>  $children
     * @param  list<string>  $allowed
     */
    private function assertNonEmptyChildrenTypes(array $children, array $allowed, string $context): void
    {
        if ($children === []) {
            throw ValidationException::withMessages([
                'document' => ["{$context} requires child nodes."],
            ]);
        }
        $this->assertChildrenTypes($children, $allowed, $context);
    }

    /**
     * @param  list<mixed>  $children
     * @param  list<string>  $allowed
     */
    private function assertChildrenTypes(array $children, array $allowed, string $context): void
    {
        foreach ($children as $child) {
            if (! is_array($child) || ! in_array($child['type'] ?? null, $allowed, true)) {
                throw ValidationException::withMessages([
                    'document' => ["Unsupported child node in {$context}."],
                ]);
            }
        }
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
