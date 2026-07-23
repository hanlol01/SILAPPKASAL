import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import { getSchema } from "@tiptap/core";

import { articleEditorExtensions } from "../src/components/content/article-editor-extensions.ts";
import {
  documentToEditorBlocks,
  editorBlocksToDocument,
  type DocumentNode,
} from "../src/lib/content-document-editor-model.ts";
import {
  ARTICLE_DOCUMENT_LIMITS,
  articleDocumentFromTiptap,
  collectLegacyImageReferences,
  countArticleWords,
  prepareArticleDocumentForTiptap,
} from "../src/lib/article-document-tiptap.ts";
import { normalizeSafeContentLink } from "../src/lib/content-document.ts";
import { contentFieldName } from "../src/lib/content-management-errors.ts";
import {
  articleCategoryEditorState,
  articleCategoryWriteFields,
} from "../src/lib/content-editor-category.ts";
import {
  clearPrivateContentQueries,
  isPrivateContentQueryKey,
} from "../src/lib/private-query-cache.ts";

const source = (path: string) => readFile(new URL(`../${path}`, import.meta.url), "utf8");

function nestedArticleDocument(maxDepth: number): DocumentNode {
  let node: DocumentNode = { type: "text", text: "Dalam batas" };
  node = { type: "paragraph", content: [node] };
  for (let depth = maxDepth - 2; depth >= 1; depth -= 1) {
    node = { type: "blockquote", content: [node] };
  }

  return { type: "doc", content: [node] };
}

function nestedLegacyInlineBlockquoteDocument(blockquoteCount: number): DocumentNode {
  let node: DocumentNode = { type: "text", text: "Legacy inline" };
  for (let index = 0; index < blockquoteCount; index += 1) {
    node = { type: "blockquote", content: [node] };
  }

  return { type: "doc", content: [node] };
}

test("legacy article JSON is normalized for Tiptap without changing supported meaning", () => {
  const legacy: DocumentNode = {
    type: "doc",
    content: [
      {
        type: "unorderedList",
        content: [
          {
            type: "listItem",
            content: [
              {
                type: "text",
                text: "Legacy item",
                marks: [{ type: "bold" }],
              },
            ],
          },
        ],
      },
      {
        type: "blockquote",
        content: [{ type: "text", text: "Legacy quote" }],
      },
      {
        type: "callout",
        attrs: { variant: "information" },
        content: [{ type: "text", text: "Legacy callout" }],
      },
      { type: "heading_2", content: [{ type: "text", text: "Legacy H2" }] },
      { type: "heading_3", content: [{ type: "text", text: "Legacy H3" }] },
      { type: "info", content: [{ type: "text", text: "Legacy info" }] },
      { type: "warning", content: [{ type: "text", text: "Legacy warning" }] },
      { type: "help", content: [{ type: "text", text: "Legacy help" }] },
      { type: "divider" },
      {
        type: "imageReference",
        attrs: {
          attachment_public_id: "11111111-1111-4111-8111-111111111111",
          alt: "Legacy illustration",
        },
      },
    ],
  };

  const result = prepareArticleDocumentForTiptap(legacy);

  assert.deepEqual(result.unsupported, []);
  assert.equal(result.document.content?.[0]?.type, "bulletList");
  assert.equal(result.document.content?.[0]?.content?.[0]?.content?.[0]?.type, "paragraph");
  assert.equal(result.document.content?.[1]?.content?.[0]?.type, "paragraph");
  assert.equal(result.document.content?.[2]?.type, "callout");
  assert.equal(result.document.content?.[2]?.content?.[0]?.type, "paragraph");
  assert.equal(result.document.content?.[2]?.content?.[0]?.content?.[0]?.text, "Legacy callout");
  assert.equal(result.document.content?.[3]?.type, "heading");
  assert.equal(result.document.content?.[3]?.attrs?.level, 2);
  assert.equal(result.document.content?.[4]?.attrs?.level, 3);
  assert.equal(result.document.content?.[5]?.type, "callout");
  assert.equal(result.document.content?.[5]?.attrs?.variant, "information");
  assert.equal(result.document.content?.[6]?.attrs?.variant, "warning");
  assert.equal(result.document.content?.[7]?.attrs?.variant, "help");
  assert.equal(result.document.content?.[8]?.type, "horizontalRule");
  assert.deepEqual(result.document.content?.[9], legacy.content?.[9]);

  const parsed = getSchema(articleEditorExtensions).nodeFromJSON(result.document);
  parsed.check();
  assert.equal(parsed.type.name, "doc");
  assert.equal(parsed.child(0).type.name, "bulletList");
  assert.equal(parsed.child(8).type.name, "horizontalRule");
  assert.equal(parsed.child(9).type.name, "imageReference");
});

test("Tiptap output is reduced to the strict backend article contract", () => {
  const serialized = articleDocumentFromTiptap({
    type: "doc",
    content: [
      {
        type: "orderedList",
        attrs: { start: 1, type: null },
        content: [
          {
            type: "listItem",
            content: [
              {
                type: "paragraph",
                content: [
                  {
                    type: "text",
                    text: "Safe",
                    marks: [
                      {
                        type: "link",
                        attrs: {
                          href: "https://example.test/path",
                          title: "Reference",
                          rel: "noopener",
                          target: "_blank",
                          class: null,
                        },
                      },
                      { type: "bold", attrs: { ignored: true } },
                      { type: "underline", attrs: { ignored: true } },
                    ],
                  },
                ],
              },
            ],
          },
        ],
      },
      { type: "horizontalRule" },
    ],
  });

  assert.deepEqual(serialized, {
    type: "doc",
    content: [
      {
        type: "orderedList",
        content: [
          {
            type: "listItem",
            content: [
              {
                type: "paragraph",
                content: [
                  {
                    type: "text",
                    text: "Safe",
                    marks: [
                      {
                        type: "link",
                        attrs: {
                          href: "https://example.test/path",
                          title: "Reference",
                        },
                      },
                      { type: "bold" },
                      { type: "underline" },
                    ],
                  },
                ],
              },
            ],
          },
        ],
      },
      { type: "horizontalRule" },
    ],
  });
});

test("safe content links allow only normalized http, https, mailto, and tel values", () => {
  assert.equal(normalizeSafeContentLink(" HTTPS://example.test/path "), "https://example.test/path");
  assert.equal(normalizeSafeContentLink("mailto:editor@example.test"), "mailto:editor@example.test");
  assert.equal(normalizeSafeContentLink("tel:+62 (812) 3456-7890"), "tel:+6281234567890");
  for (const unsafe of [
    "javascript:alert(1)",
    " JaVaScRiPt:alert(1) ",
    "java\nscript:alert(1)",
    "data:text/html;base64,PHNjcmlwdD4=",
    "vbscript:msgbox(1)",
    "file:///tmp/private",
    "//example.test/path",
    "mailto:not-an-email",
    "tel:alert(1)",
    "https://",
  ]) {
    assert.equal(normalizeSafeContentLink(unsafe), null, unsafe);
  }
});

test("unknown article nodes fail closed instead of being dropped by the WYSIWYG editor", () => {
  const document: DocumentNode = {
    type: "doc",
    content: [
      { type: "paragraph", content: [{ type: "text", text: "Known" }] },
      { type: "futureSafeNode", attrs: { mode: "preserve" } },
    ],
  };

  const result = prepareArticleDocumentForTiptap(document);

  assert.deepEqual(result.document, document);
  assert.deepEqual(result.unsupported, ["document.content[1]:futureSafeNode"]);
});

test("unsafe legacy links fail closed before Tiptap can render them", () => {
  const document: DocumentNode = {
    type: "doc",
    content: [
      {
        type: "paragraph",
        content: [
          {
            type: "text",
            text: "Unsafe",
            marks: [
              {
                type: "link",
                attrs: { href: "javascript:alert(1)" },
              },
            ],
          },
        ],
      },
    ],
  };

  const result = prepareArticleDocumentForTiptap(document);

  assert.deepEqual(result.unsupported, [
    "document.content[0].content[0].marks[0]:link",
  ]);
});

test("legacy Article adapter enforces backend-equivalent resource limits before Tiptap", async () => {
  const exactlyMaxDepth = prepareArticleDocumentForTiptap(nestedArticleDocument(12));
  assert.equal(exactlyMaxDepth.failureKind, null);
  assert.deepEqual(exactlyMaxDepth.unsupported, []);

  const tooDeep = prepareArticleDocumentForTiptap(nestedArticleDocument(13));
  assert.equal(tooDeep.failureKind, "resource_limit");
  assert.ok(tooDeep.unsupported.includes("document:depth-limit"));

  const exactlyMaxNodes = prepareArticleDocumentForTiptap({
    type: "doc",
    content: Array.from({ length: ARTICLE_DOCUMENT_LIMITS.maxNodes }, () => ({
      type: "paragraph",
    })),
  });
  assert.equal(exactlyMaxNodes.failureKind, null);
  assert.deepEqual(exactlyMaxNodes.unsupported, []);

  const tooManyNodes = prepareArticleDocumentForTiptap({
    type: "doc",
    content: Array.from({ length: ARTICLE_DOCUMENT_LIMITS.maxNodes + 1 }, () => ({
      type: "paragraph",
    })),
  });
  assert.equal(tooManyNodes.failureKind, "resource_limit");
  assert.ok(tooManyNodes.unsupported.includes("document:node-limit"));

  const tooMuchText = prepareArticleDocumentForTiptap({
    type: "doc",
    content: Array.from({ length: 11 }, () => ({
      type: "paragraph",
      content: [{ type: "text", text: "a".repeat(20_000) }],
    })),
  });
  assert.equal(tooMuchText.failureKind, "resource_limit");
  assert.ok(tooMuchText.unsupported.includes("document:text-limit"));

  const tooLarge = prepareArticleDocumentForTiptap({
    type: "doc",
    content: Array.from({ length: ARTICLE_DOCUMENT_LIMITS.maxNodes }, () => ({
      type: "imageReference",
      attrs: {
        attachment_public_id: "11111111-1111-4111-8111-111111111111",
        alt: "a".repeat(500),
      },
    })),
  });
  assert.equal(tooLarge.failureKind, "resource_limit");
  assert.deepEqual(tooLarge.unsupported, ["document:payload-limit"]);

  const unicodePayload = prepareArticleDocumentForTiptap({
    type: "doc",
    content: [
      {
        type: "paragraph",
        content: [{ type: "text", text: "界".repeat(90_000) }],
      },
    ],
  });
  assert.equal(unicodePayload.failureKind, "resource_limit");
  assert.deepEqual(unicodePayload.unsupported, ["document:payload-limit"]);

  const unknown = prepareArticleDocumentForTiptap({
    type: "doc",
    content: [{ type: "futureNode" }],
  });
  assert.equal(unknown.failureKind, "unsupported_shape");
  assert.deepEqual(unknown.unsupported, ["document.content[0]:futureNode"]);

  const normalLegacy = prepareArticleDocumentForTiptap({
    type: "doc",
    content: [
      { type: "heading_2", content: [{ type: "text", text: "Normal legacy" }] },
      { type: "divider" },
    ],
  });
  assert.equal(normalLegacy.failureKind, null);
  assert.equal(normalLegacy.document.content?.[0]?.type, "heading");
  assert.equal(normalLegacy.document.content?.[1]?.type, "horizontalRule");

  const [editorSource, contentEditorSource] = await Promise.all([
    source("src/components/content/article-wysiwyg-editor.tsx"),
    source("src/components/content/content-editor.tsx"),
  ]);
  const failClosedBranch = editorSource.indexOf("if (compatibility.unsupported.length > 0)");
  const editableBranch = editorSource.indexOf("<CompatibleArticleEditor");
  assert.ok(failClosedBranch >= 0 && editableBranch > failClosedBranch);
  const rejectedDocumentBranch = editorSource.slice(failClosedBranch, editableBranch);
  assert.match(rejectedDocumentBranch, /ContentDocumentPreview/);
  assert.doesNotMatch(rejectedDocumentBranch, /setContent|useEditor/);
  assert.match(
    editorSource,
    /onCompatibilityChange\?\.\(compatibility\.unsupported\.length === 0\)/,
  );
  assert.match(contentEditorSource, /disabled=\{[\s\S]+!articleDocumentCompatible/);
  assert.match(
    contentEditorSource,
    /mutationFn: async \(\) => \{\s+if \(!articleDocumentCompatible\)/,
  );
});

test("legacy normalization revalidates canonical node and depth budgets", () => {
  const manyInlineQuotes: DocumentNode = {
    type: "doc",
    content: Array.from({ length: 500 }, () => ({
      type: "blockquote",
      content: [{ type: "text", text: "Legacy quote" }],
    })),
  };
  const manyInlineQuotesSignature = JSON.stringify(manyInlineQuotes);
  const expandedNodes = prepareArticleDocumentForTiptap(manyInlineQuotes);
  assert.equal(expandedNodes.failureKind, "resource_limit");
  assert.deepEqual(expandedNodes.unsupported, ["document:node-limit"]);
  assert.equal(expandedNodes.document, manyInlineQuotes);
  assert.equal(JSON.stringify(manyInlineQuotes), manyInlineQuotesSignature);

  const nestedInlineQuotes = nestedLegacyInlineBlockquoteDocument(11);
  const nestedInlineQuotesSignature = JSON.stringify(nestedInlineQuotes);
  const expandedDepth = prepareArticleDocumentForTiptap(nestedInlineQuotes);
  assert.equal(expandedDepth.failureKind, "resource_limit");
  assert.deepEqual(expandedDepth.unsupported, ["document:depth-limit"]);
  assert.equal(expandedDepth.document, nestedInlineQuotes);
  assert.equal(JSON.stringify(nestedInlineQuotes), nestedInlineQuotesSignature);

  const safeLegacyQuote: DocumentNode = {
    type: "doc",
    content: [
      {
        type: "blockquote",
        content: [{ type: "text", text: "Masih aman setelah dibungkus" }],
      },
    ],
  };
  const normalized = prepareArticleDocumentForTiptap(safeLegacyQuote);
  assert.equal(normalized.failureKind, null);
  assert.deepEqual(normalized.unsupported, []);
  assert.equal(normalized.document.content?.[0]?.content?.[0]?.type, "paragraph");
  assert.equal(normalized.document.content?.[0]?.content?.[0]?.content?.[0]?.type, "text");
});

test("imageReference cannot be authored from pasted HTML or new Tiptap JSON", async () => {
  const schema = getSchema(articleEditorExtensions);
  assert.equal(schema.nodes.imageReference.spec.parseDOM, undefined);

  const legacy: DocumentNode = {
    type: "doc",
    content: [
      {
        type: "imageReference",
        attrs: {
          attachment_public_id: "11111111-1111-4111-8111-111111111111",
          alt: "Legacy only",
        },
      },
      {
        type: "imageReference",
        attrs: {
          attachment_public_id: "22222222-2222-4222-8222-222222222222",
        },
      },
    ],
  };
  const originalSignature = JSON.stringify(legacy);
  const prepared = prepareArticleDocumentForTiptap(legacy);
  const allowed = collectLegacyImageReferences(prepared.document);
  const preserved = articleDocumentFromTiptap(prepared.document, allowed);

  assert.deepEqual(preserved, legacy);
  assert.equal(JSON.stringify(legacy), originalSignature);
  assert.throws(
    () => articleDocumentFromTiptap(prepared.document),
    /legacy read-only content/,
  );

  const [editorSource, extensionSource, previewSource] = await Promise.all([
    source("src/components/content/article-wysiwyg-editor.tsx"),
    source("src/components/content/article-editor-extensions.ts"),
    source("src/components/content/content-document-preview.tsx"),
  ]);
  assert.doesNotMatch(
    extensionSource,
    /parseHTML\(\)\s*\{\s*return \[\{ tag: "figure\[data-attachment-public-id\]"/,
  );
  assert.match(editorSource, /handlePaste/);
  assert.match(editorSource, /clipboardContainsFile/);
  assert.match(editorSource, /collectLegacyImageReferences/);
  assert.match(previewSource, /node\.type === "imageReference"/);
});

test("Article word count matches backend Unicode tokenization", async () => {
  assert.equal(countArticleWords("satu dua\t tiga\nempat"), 4);
  assert.equal(countArticleWords("satu—dua"), 2);
  assert.equal(countArticleWords("halo, dunia! apa?"), 3);
  assert.equal(countArticleWords("Pendidikan naïve 東京 ١٢٣"), 4);
  assert.equal(countArticleWords(""), 0);
  assert.equal(countArticleWords("— ... !!!"), 0);

  const editorSource = await source("src/components/content/article-wysiwyg-editor.tsx");
  assert.match(editorSource, /countArticleWords\(toolbarState\.text\)/);
  assert.match(
    editorSource,
    /readingMinutes = wordCount === 0 \? 0 : Math\.max\(1, Math\.ceil\(wordCount \/ 200\)\)/,
  );
});

test("article authoring uses a controlled SSR-safe Tiptap editor with an allowlisted schema", async () => {
  const [contentEditor, wysiwyg, extensions, packageJson] = await Promise.all([
    source("src/components/content/content-editor.tsx"),
    source("src/components/content/article-wysiwyg-editor.tsx"),
    source("src/components/content/article-editor-extensions.ts"),
    source("package.json"),
  ]);

  assert.match(contentEditor, /<ArticleWysiwygEditor[\s\S]+value=\{state\.document\}/);
  assert.match(contentEditor, /<StructuredDocumentEditor[\s\S]+value=\{state\.answerDocument\}/);
  assert.match(wysiwyg, /immediatelyRender: false/);
  assert.match(wysiwyg, /shouldRerenderOnTransaction: false/);
  assert.match(wysiwyg, /setContent\(document as JSONContent,[\s\S]+emitUpdate: false/);
  assert.match(wysiwyg, /errorOnInvalidContent: true/);
  assert.match(wysiwyg, /prepareArticleDocumentForTiptap/);
  assert.match(extensions, /levels: \[2, 3\]/);
  for (const disabledExtension of ["code", "codeBlock", "hardBreak", "strike"]) {
    assert.match(extensions, new RegExp(`${disabledExtension}: false`));
  }
  assert.match(extensions, /underline: \{\}/);
  assert.match(extensions, /name: "horizontalRule"/);
  assert.doesNotMatch(
    extensions,
    /extension-image|extension-youtube|extension-collaboration|raw-html/i,
  );
  assert.doesNotMatch(packageJson, /reactjs-tiptap-editor/);
  assert.match(packageJson, /"@tiptap\/react": "\^3\.28\.0"/);

  const schema = getSchema(articleEditorExtensions);
  for (const node of [
    "image",
    "iframe",
    "video",
    "table",
    "codeBlock",
    "hardBreak",
  ]) {
    assert.equal(schema.nodes[node], undefined, `${node} must not be authorable`);
  }
  for (const mark of ["code", "strike"]) {
    assert.equal(schema.marks[mark], undefined, `${mark} must not be authorable`);
  }
  assert.ok(schema.nodes.horizontalRule);
  assert.ok(schema.marks.underline);
});

test("article toolbar, save state, navigation guard, and media boundaries are wired explicitly", async () => {
  const [editor, contentEditor, preview] = await Promise.all([
    source("src/components/content/article-wysiwyg-editor.tsx"),
    source("src/components/content/content-editor.tsx"),
    source("src/components/content/content-document-preview.tsx"),
  ]);

  for (const command of [
    "setParagraph",
    "setHeading({ level: 2 })",
    "setHeading({ level: 3 })",
    "toggleBold",
    "toggleItalic",
    "toggleUnderline",
    "toggleBulletList",
    "toggleOrderedList",
    "toggleBlockquote",
    "setLink",
    'insertContent({ type: "horizontalRule" })',
    "unsetAllMarks().clearNodes()",
    "undo",
    "redo",
  ]) {
    assert.ok(editor.includes(command), `Missing toolbar command ${command}`);
  }
  for (const variant of ["information", "warning", "help"]) {
    assert.ok(editor.includes(`applyCallout(editor, "${variant}")`));
  }
  assert.match(editor, /sticky top-0/);
  assert.match(editor, /overflow-x-auto/);
  assert.match(editor, /TooltipContent/);
  assert.match(editor, /aria-label=\{label\}/);
  assert.match(editor, /handlePaste/);
  assert.match(editor, /handleDrop/);
  assert.match(editor, /clipboardContainsFile/);
  assert.doesNotMatch(editor, /image upload|youtube|iframe|collaboration|dangerouslySetInnerHTML/i);

  assert.match(contentEditor, /useBlocker\(/);
  assert.match(contentEditor, /enableBeforeUnload: dirty/);
  assert.match(contentEditor, /navigationAllowedRef\.current = true;\s+setDirty\(false\)/);
  assert.match(contentEditor, /if \(dirty\) return;/);
  assert.match(contentEditor, /setSaveStatus\("dirty"\)/);
  assert.match(contentEditor, /setSaveStatus\("saved"\)/);
  assert.match(contentEditor, /setSaveStatus\("failed"\)/);
  assert.match(editor, /editor\.status\.\$\{saveStatus\}/);
  assert.match(editor, /readingMinutes/);
  assert.match(editor, /emitUpdate: false/);

  assert.doesNotMatch(preview, /dangerouslySetInnerHTML/);
  assert.match(preview, /depth > 12/);
  assert.match(preview, /state\.nodes > 1000/);
  assert.match(preview, /noopener noreferrer/);
  assert.match(preview, /node\.type === "imageReference"/);
});

test("structured content makes a lossless round trip for every supported complex shape", () => {
  const document: DocumentNode = {
    type: "doc",
    content: [
      {
        type: "paragraph",
        content: [
          { type: "text", text: "Mixed ", marks: [{ type: "bold" }] },
          {
            type: "text",
            text: "marks",
            marks: [
              { type: "italic" },
              { type: "link", attrs: { href: "https://example.test", title: "Verified" } },
            ],
          },
        ],
      },
      { type: "heading", attrs: { level: 2 }, content: [{ type: "text", text: "H2" }] },
      { type: "heading", attrs: { level: 3 }, content: [{ type: "text", text: "H3" }] },
      {
        type: "orderedList",
        content: [
          {
            type: "listItem",
            content: [
              { type: "paragraph", content: [{ type: "text", text: "Nested item" }] },
              {
                type: "unorderedList",
                content: [{ type: "listItem", content: [{ type: "text", text: "Child" }] }],
              },
            ],
          },
        ],
      },
      { type: "blockquote", content: [{ type: "text", text: "Quote" }] },
      {
        type: "callout",
        attrs: { variant: "warning" },
        content: [{ type: "text", text: "Warning" }],
      },
      { type: "divider" },
      {
        type: "imageReference",
        attrs: { attachment_public_id: "11111111-1111-4111-8111-111111111111", alt: "Safe" },
      },
      { type: "futureSafeNode", attrs: { mode: "preserve" }, content: [] },
    ],
  };

  const blocks = documentToEditorBlocks(document);
  assert.equal(blocks[0]?.readOnly, true);
  assert.equal(blocks[2]?.readOnly, false);
  assert.equal(blocks[3]?.readOnly, true);
  assert.equal(blocks[7]?.readOnly, true);
  assert.equal(blocks[8]?.readOnly, true);
  assert.deepEqual(editorBlocksToDocument(blocks), document);
});

test("editing one simple block does not mutate preserved complex siblings", () => {
  const image: DocumentNode = {
    type: "imageReference",
    attrs: { attachment_public_id: "22222222-2222-4222-8222-222222222222" },
  };
  const document: DocumentNode = {
    type: "doc",
    content: [{ type: "paragraph", content: [{ type: "text", text: "Before" }] }, image],
  };
  const blocks = documentToEditorBlocks(document);
  blocks[0] = { ...blocks[0]!, text: "After", modified: true };

  const serialized = editorBlocksToDocument(blocks);
  assert.equal(serialized.content?.[0]?.content?.[0]?.text, "After");
  assert.deepEqual(serialized.content?.[1], image);
});

test("FAQ document validation errors map to the visible answer editor", () => {
  assert.equal(contentFieldName("faq", "document"), "answerDocument");
  assert.equal(contentFieldName("faq", "answer_document"), "answerDocument");
  assert.equal(contentFieldName("article", "category_name"), "categoryName");
});

test("article section and same-section category changes clear legacy category placement and defer errors until interaction", async () => {
  const editor = await source("src/components/content/content-editor.tsx");

  assert.match(
    editor,
    /set\("sectionCode", value\);\s+set\("categoryName", ""\);\s+set\("categoryPublicId", null\);/,
  );
  assert.match(editor, /saveAttempted \|\| touchedFields\.has\("categoryName"\)/);
  assert.match(editor, /onBlur=\{onCategoryBlur\}/);
  assert.match(
    editor,
    /onValueChange=\{\(value\) => \{\s+set\("categoryName", value\);\s+set\("categoryPublicId", null\);\s+\}\}/,
  );
  assert.match(editor, /articleCategoryWriteFields\(state\.categoryName, state\.categoryPublicId\)/);
  assert.match(editor, /allowCreate=\{canManageCategories\}/);
  assert.match(
    editor,
    /permissions\.has\("content\.category\.govern"\) \|\| permissions\.has\("content\.category\.manage\.own_campus"\)/,
  );
});

test("stale article category relation is not reintroduced when unrelated metadata is edited", () => {
  const loaded = articleCategoryEditorState({
    category_name: "Kategori B",
    category: {
      public_id: "aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa",
      name: "Kategori A",
    },
  });
  const edited = { ...loaded, excerpt: "Ringkasan yang diperbarui." };
  const payload = {
    ...articleCategoryWriteFields(edited.categoryName, edited.categoryPublicId),
    excerpt: edited.excerpt,
  };

  assert.equal(loaded.categoryName, "Kategori B");
  assert.equal(loaded.categoryPublicId, null);
  assert.equal(payload.category_name, "Kategori B");
  assert.equal(payload.category_public_id, null);
  assert.equal(payload.excerpt, "Ringkasan yang diperbarui.");
});

test("legacy article category id is initialized only when canonical category name is null", () => {
  const loaded = articleCategoryEditorState({
    category_name: null,
    category: {
      public_id: "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb",
      name: "Kategori Legacy",
    },
  });

  assert.equal(loaded.categoryName, "Kategori Legacy");
  assert.equal(loaded.categoryPublicId, "bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb");
  assert.equal(
    articleCategoryWriteFields(loaded.categoryName, loaded.categoryPublicId).category_public_id,
    null,
  );
});

test("article category combobox supports search, keyboard selection, creation, and safe deactivation", async () => {
  const [combobox, api, dashboard] = await Promise.all([
    source("src/components/content/content-category-combobox.tsx"),
    source("src/lib/content-management-api.ts"),
    source("src/routes/dashboard.content.tsx"),
  ]);

  assert.match(combobox, /role="combobox"/);
  assert.match(combobox, /CommandInput/);
  assert.match(combobox, /Tambah kategori/);
  assert.match(combobox, /Trash2/);
  assert.match(combobox, /category\.can_deactivate/);
  assert.match(combobox, /Nonaktifkan kategori “\{pendingDelete\?\.name\}”/);
  assert.match(combobox, /Konten yang sudah ada tidak akan diubah/);
  assert.match(combobox, /allowCreate = false/);
  assert.match(combobox, /allowManage = false/);
  assert.match(api, /createManagedArticleCategory/);
  assert.match(api, /deactivateManagedArticleCategory/);
  assert.doesNotMatch(dashboard, /category: category \|\| undefined/);
  assert.doesNotMatch(dashboard, /Semua kategori legacy|All legacy categories/);
});

test("content and governance controls use consistent icons, field heights, and responsive filter grouping", async () => {
  const [content, governance, editor] = await Promise.all([
    source("src/routes/dashboard.content.tsx"),
    source("src/routes/dashboard.content-governance.tsx"),
    source("src/components/content/content-editor.tsx"),
  ]);

  assert.match(content, /RotateCcw/);
  assert.match(governance, /RotateCcw/);
  assert.match(governance, /md:grid-cols-2 xl:grid-cols-4/);
  assert.match(governance, /<DatePicker className="h-11"/);
  assert.match(content, /detailQuery\.isError[\s\S]*?<ArrowLeft className="mr-2 h-4 w-4"/);
  assert.match(content, /function EditorSkeleton[\s\S]*?<ArrowLeft className="mr-2 h-4 w-4"/);
  assert.match(editor, /ArrowLeft/);
  assert.match(editor, /\[&>input\]:h-11/);
});

test("private content queries are cancelled and removed without touching public queries", async () => {
  assert.equal(isPrivateContentQueryKey(["content-management", "items"]), true);
  assert.equal(isPrivateContentQueryKey(["content-governance", "reviews"]), true);
  assert.equal(isPrivateContentQueryKey(["content", "articles"]), false);
  const calls: string[] = [];
  const client = {
    async cancelQueries({ predicate }: { predicate: (query: { queryKey: unknown[] }) => boolean }) {
      assert.equal(predicate({ queryKey: ["content-management", "detail"] }), true);
      assert.equal(predicate({ queryKey: ["content-governance", "detail"] }), true);
      assert.equal(predicate({ queryKey: ["content", "articles"] }), false);
      calls.push("cancel");
    },
    removeQueries({ predicate }: { predicate: (query: { queryKey: unknown[] }) => boolean }) {
      assert.equal(predicate({ queryKey: ["content-management", "summary"] }), true);
      assert.equal(predicate({ queryKey: ["content-governance", "featured"] }), true);
      calls.push("remove");
    },
  };

  await clearPrivateContentQueries(client as never);
  assert.deepEqual(calls, ["cancel", "remove"]);
});

test("mobile authoring actions wrap safely and archived actions remain guarded", async () => {
  const editor = await readFile(
    new URL("../src/components/content/content-editor.tsx", import.meta.url),
    "utf8",
  );
  const dashboard = await readFile(
    new URL("../src/routes/dashboard.content.tsx", import.meta.url),
    "utf8",
  );
  assert.match(editor, /grid-cols-2/);
  assert.match(editor, /safe-area-inset-bottom/);
  assert.match(editor, /detail\.archived_at === null/);
  assert.match(dashboard, /item\.archived_at === null/);
  assert.match(dashboard, /aria-label=\{t\("filters\.search"\)\}/);
});

test("stale submission refetches authoritative detail without replacing dirty editor state", async () => {
  const editor = await readFile(
    new URL("../src/components/content/content-editor.tsx", import.meta.url),
    "utf8",
  );
  assert.match(editor, /if \(dirty\) return;[\s\S]+setState\(initialState/);
  assert.match(
    editor,
    /error\.errorCode === "content_stale_version"[\s\S]+errors\.stale[\s\S]+invalidate\(detail\?\.public_id\)/,
  );
  assert.doesNotMatch(
    editor,
    /error\.errorCode === "content_stale_version"[\s\S]{0,250}setDirty\(false\)/,
  );
});

test("auth lifecycle explicitly clears private management cache", async () => {
  const provider = await readFile(
    new URL("../src/components/auth-provider.tsx", import.meta.url),
    "utf8",
  );
  const apiClient = await readFile(new URL("../src/lib/api-client.ts", import.meta.url), "utf8");
  const calls = provider.match(/clearPrivateContentQueries\(queryClient\)/g) ?? [];
  assert.ok(calls.length >= 5);
  assert.match(provider, /const login[\s\S]+await clearPrivateContentQueries\(queryClient\)/);
  assert.match(provider, /const logout[\s\S]+await clearPrivateContentQueries\(queryClient\)/);
  assert.match(provider, /status !== 401[\s\S]+clearPrivateContentQueries\(queryClient\)/);
  assert.match(
    provider,
    /AUTH_SESSION_INVALIDATED_EVENT[\s\S]+clearPrivateContentQueries\(queryClient\)/,
  );
  assert.doesNotMatch(apiClient, /status === 401[\s\S]{0,80}clearAuthToken/);
  assert.match(apiClient, /status === 401[\s\S]{0,80}invalidateAuthSession/);
});

test("Indonesian and English content locale keys remain in parity", async () => {
  const [id, en] = await Promise.all([
    readFile(new URL("../src/locales/id/content.json", import.meta.url), "utf8").then(JSON.parse),
    readFile(new URL("../src/locales/en/content.json", import.meta.url), "utf8").then(JSON.parse),
  ]);
  const keys = (value: unknown, prefix = ""): string[] =>
    value && typeof value === "object" && !Array.isArray(value)
      ? Object.entries(value).flatMap(([key, child]) =>
          keys(child, prefix ? `${prefix}.${key}` : key),
        )
      : [prefix];

  assert.deepEqual(keys(id).sort(), keys(en).sort());
  assert.equal(typeof id.errors.stale, "string");
  assert.equal(typeof en.editor.preservedDescription, "string");
  assert.equal(id.validation.category, "Kategori wajib diisi.");
  assert.equal("consultationCta" in id, false);
  assert.equal("noCta" in en, false);
});

test("content governance review filters stay server-driven and campus content stays read-only", async () => {
  const route = await readFile(
    new URL("../src/routes/dashboard.content-governance.tsx", import.meta.url),
    "utf8",
  );
  const api = await readFile(
    new URL("../src/lib/content-governance-api.ts", import.meta.url),
    "utf8",
  );

  assert.match(route, /contentGovernanceKeys\.reviews\(filters\)/);
  assert.match(route, /getGovernanceReviews\(filters, signal\)/);
  assert.match(route, /submitted_from: from \|\| undefined/);
  assert.match(route, /university_code: campus \|\| undefined/);
  assert.match(route, /if \(value === "global"\) setCampus\(""\)/);
  assert.match(route, /disabled=\{props\.scope === "global"\}/);
  assert.match(api, /"\/content-governance\/reviews"/);
  assert.match(route, /readOnlyCampus/);
  assert.doesNotMatch(route, /<ContentEditor[\s\S]{0,300}scope="campus"/);
});

test("governance transparency renders attribution, actual editorial timeline, and responsive queue metadata", async () => {
  const [route, editor, api, managementApi, id, en] = await Promise.all([
    source("src/routes/dashboard.content-governance.tsx"),
    source("src/components/content/content-editor.tsx"),
    source("src/lib/content-governance-api.ts"),
    source("src/lib/content-management-api.ts"),
    readFile(new URL("../src/locales/id/contentGovernance.json", import.meta.url), "utf8").then(JSON.parse),
    readFile(new URL("../src/locales/en/contentGovernance.json", import.meta.url), "utf8").then(JSON.parse),
  ]);

  for (const field of ["created_by", "submitted_by", "reviewed_by", "approved_by", "published_by"]) {
    assert.match(api, new RegExp(`${field}: ContentActor \\| null`));
  }
  assert.match(route, /item\.submitted_by\?\.email/);
  assert.match(route, /actorLabel\(item\.created_by\)/);
  assert.match(route, /actorLabel\(item\.published_by\)/);
  assert.match(route, /item\.decision_history\.map/);
  assert.match(route, /timelineStates\.\$\{event\.state\}/);
  assert.match(route, /event\.from_status && event\.to_status/);
  assert.match(route, /event\.actor\.email/);
  assert.match(route, /event\.actor\.role/);
  assert.match(route, /decision_history_truncated/);
  assert.match(route, /whitespace-pre-wrap/);
  assert.match(api, /decision_history_truncated: boolean/);
  assert.match(managementApi, /editorial_timeline: ContentTimelineEvent\[\]/);
  assert.match(managementApi, /label: "central_team" \| "system" \| null/);
  assert.match(editor, /event\.actor\.label === "central_team"/);
  assert.match(editor, /timelineCentralTeam/);
  assert.match(editor, /editorial_timeline_truncated/);
  assert.match(editor, /whitespace-pre-wrap/);
  assert.match(route, /hidden rounded-lg border 2xl:block/);
  assert.match(route, /space-y-3 2xl:hidden/);
  assert.match(route, /<EmptyState[\s\S]+review\.empty/);
  assert.equal(id.review.submittedBy, "Diajukan oleh");
  assert.equal(id.review.everyCampus, "Semua Kampus");
  assert.equal(typeof id.review.timelineStates.submitted, "string");
  assert.equal(typeof en.review.timelineStates.published, "string");
  assert.equal(typeof id.review.historyTruncated, "string");
  assert.equal(typeof en.review.historyTruncated, "string");
});

test("governance private PDFs use authenticated Blob access with cleanup and safe feedback", async () => {
  const [route, attachment, attachmentApi, preview, id, en] = await Promise.all([
    readFile(new URL("../src/routes/dashboard.content-governance.tsx", import.meta.url), "utf8"),
    readFile(
      new URL("../src/components/content/authenticated-content-attachment.tsx", import.meta.url),
      "utf8",
    ),
    readFile(new URL("../src/lib/content-attachment-api.ts", import.meta.url), "utf8"),
    readFile(new URL("../src/components/secure-file-preview-dialog.tsx", import.meta.url), "utf8"),
    readFile(new URL("../src/locales/id/contentGovernance.json", import.meta.url), "utf8").then(JSON.parse),
    readFile(new URL("../src/locales/en/contentGovernance.json", import.meta.url), "utf8").then(JSON.parse),
  ]);

  assert.match(route, /<AuthenticatedContentAttachment attachment=\{file\}/);
  assert.doesNotMatch(route, /href=\{file\.download_url\}/);
  assert.doesNotMatch(route, /target="_blank"/);
  assert.match(attachment, /SecureFilePreviewDialog/);
  assert.match(attachment, /fetchContentAttachment\(attachment\.public_id, signal\)/);
  assert.match(attachment, /attachment\.filename/);
  assert.match(attachmentApi, /apiFetchBlob/);
  assert.doesNotMatch(attachmentApi, /token|query:/i);
  assert.match(preview, /disabled=\{pdfOpening\}/);
  assert.match(preview, /URL\.createObjectURL\(response\.blob\)/);
  assert.match(preview, /URL\.revokeObjectURL\(nextUrl\)/);
  assert.match(attachment, /review\.attachmentError/);
  assert.equal(typeof id.review.attachmentError, "string");
  assert.equal(typeof en.review.attachmentLoading, "string");
});

test("governance queries propagate TanStack AbortSignal to authenticated requests", async () => {
  const [route, api, managementApi] = await Promise.all([
    readFile(new URL("../src/routes/dashboard.content-governance.tsx", import.meta.url), "utf8"),
    readFile(new URL("../src/lib/content-governance-api.ts", import.meta.url), "utf8"),
    readFile(new URL("../src/lib/content-management-api.ts", import.meta.url), "utf8"),
  ]);

  for (const call of [
    "getGovernanceReviews(filters, signal)",
    "getGovernancePublished(filters, signal)",
    "getGovernanceDetail(publicId!, signal)",
    "getGovernanceCategories(section, signal)",
    "getGovernanceCampuses(signal)",
    "getFeaturedPlacements({ state: stateFilter || undefined }, signal)",
    "getFeaturedEligible(eligibleFilters, signal)",
    "getFeaturedCampuses(signal)",
  ]) {
    assert.ok(route.includes(call), `Missing signal propagation for ${call}`);
  }
  assert.match(api, /getGovernanceReviews[\s\S]+signal\?: AbortSignal[\s\S]+\{ query: filters, signal \}/);
  assert.match(api, /getGovernanceDetail[\s\S]+\{ signal \}/);
  assert.match(managementApi, /getManagedContent[\s\S]+signal\?: AbortSignal/);
  assert.match(route, /getManagedContent\(filters, signal\)/);
  assert.match(route, /getManagedContentDetail\(selectedId!, signal\)/);
});

test("editorial decisions preserve notes on conflict and render only server capabilities", async () => {
  const route = await readFile(
    new URL("../src/routes/dashboard.content-governance.tsx", import.meta.url),
    "utf8",
  );

  assert.match(route, /note\.trim\(\)\.length < 10/);
  assert.match(route, /content_stale_review/);
  assert.match(route, /void invalidate\(\)/);
  assert.doesNotMatch(route, /content_stale_review[\s\S]{0,250}setNote\(""\)/);
  for (const capability of [
    "start_review",
    "request_revision",
    "reject",
    "approve",
    "publish",
    "archive",
  ]) {
    assert.match(route, new RegExp(`capabilities\\.${capability}`));
  }
});

test("global authoring is explicitly global and separate from campus review", async () => {
  const route = await readFile(
    new URL("../src/routes/dashboard.content-governance.tsx", import.meta.url),
    "utf8",
  );
  const editor = await readFile(
    new URL("../src/components/content/content-editor.tsx", import.meta.url),
    "utf8",
  );

  assert.match(route, /<ContentEditor[\s\S]+scope="global"/);
  assert.match(route, /contentGovernance:global\.secondReview/);
  assert.match(route, /contentGovernance:global\.helper/);
  assert.match(editor, /scope === "global"/);
  assert.match(editor, /university_id: scope === "campus"/);
});

test("featured governance validates dates, uses concurrency token, and has safe mobile actions", async () => {
  const route = await readFile(
    new URL("../src/routes/dashboard.content-governance.tsx", import.meta.url),
    "utf8",
  );

  assert.match(route, /new Date\(form\.from\) > new Date\(form\.until\)/);
  assert.match(route, /concurrency_token: editing\?\.concurrency_token/);
  assert.match(route, /removeFeaturedPlacement\(item\.public_id, item\.concurrency_token\)/);
  assert.match(route, /grid grid-cols-1 gap-2 sm:flex sm:flex-wrap/);
  assert.match(route, /safe-area-inset-bottom/);
  assert.match(route, /min-h-11 w-full sm:w-auto/);
});

test("governance translations and stable error mappings remain complete in both locales", async () => {
  const [id, en, errors] = await Promise.all([
    readFile(new URL("../src/locales/id/contentGovernance.json", import.meta.url), "utf8").then(JSON.parse),
    readFile(new URL("../src/locales/en/contentGovernance.json", import.meta.url), "utf8").then(JSON.parse),
    readFile(new URL("../src/lib/form-errors.ts", import.meta.url), "utf8"),
  ]);
  const keys = (value: unknown, prefix = ""): string[] =>
    value && typeof value === "object" && !Array.isArray(value)
      ? Object.entries(value).flatMap(([key, child]) => keys(child, prefix ? `${prefix}.${key}` : key))
      : [prefix];

  assert.deepEqual(keys(id).sort(), keys(en).sort());
  assert.match(errors, /content_stale_review/);
  assert.match(errors, /content_invalid_lifecycle_transition/);
  assert.match(errors, /content_featured_conflict/);
});
