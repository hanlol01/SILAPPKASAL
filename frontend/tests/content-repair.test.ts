import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

import {
  documentToEditorBlocks,
  editorBlocksToDocument,
  type DocumentNode,
} from "../src/lib/content-document-editor-model.ts";
import { contentFieldName } from "../src/lib/content-management-errors.ts";
import {
  clearPrivateContentQueries,
  isPrivateContentQueryKey,
} from "../src/lib/private-query-cache.ts";

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
  assert.equal(
    contentFieldName("article", "consultation_cta_public_id"),
    "consultationCtaPublicId",
  );
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
  assert.match(route, /getGovernanceReviews\(filters\)/);
  assert.match(route, /submitted_from: from \|\| undefined/);
  assert.match(route, /university_code: campus \|\| undefined/);
  assert.match(api, /"\/content-governance\/reviews"/);
  assert.match(route, /readOnlyCampus/);
  assert.doesNotMatch(route, /<ContentEditor[\s\S]{0,300}scope="campus"/);
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
