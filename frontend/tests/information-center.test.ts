import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

import {
  createAuthenticatedPdfPreview,
  type PdfPreviewTab,
} from "../src/lib/authenticated-pdf-preview.ts";
import { carouselActionForKey } from "../src/lib/carousel-keyboard.ts";
import {
  normalizeConsultationPhone,
  normalizeConsultationWhatsApp,
  safeConsultationEmail,
  safeConsultationHttpsUrl,
} from "../src/lib/consultation-actions.ts";
import {
  categoryBelongsToReaderContext,
  informationCenterSearchNeedsNormalization,
  mergeInformationCenterSearch,
  normalizeInformationCenterSearch,
} from "../src/lib/information-center-navigation.ts";
import { clearPrivateContentQueries } from "../src/lib/private-query-cache.ts";
import { canReadPublishedContent } from "../src/lib/published-content-access.ts";

const source = (path: string) => readFile(new URL(`../${path}`, import.meta.url), "utf8");

test("published reader API uses centralized private keys and propagates AbortSignal", async () => {
  const [api, cache] = await Promise.all([
    source("src/lib/published-content-api.ts"),
    source("src/lib/private-query-cache.ts"),
  ]);

  assert.match(api, /\["published-content", identity \?\? "anonymous"\]/);
  for (const endpoint of [
    "sections",
    "categories",
    "articles",
    "faqs",
    "consultation",
    "featured",
  ]) {
    assert.ok(api.includes(`/content/${endpoint}`));
  }
  assert.match(api, /getPublishedArticle[\s\S]+encodeURIComponent\(publicId\)[\s\S]+\{ signal \}/);
  assert.match(api, /getFeaturedContent\(signal\?: AbortSignal\)/);
  assert.match(cache, /queryKey\[0\] === "published-content"/);
});

test("published-content permission controls entry points as well as the route guard", async () => {
  const [shell, dashboard, layout, access] = await Promise.all([
    source("src/routes/dashboard.information-center.tsx"),
    source("src/routes/dashboard.tsx"),
    source("src/layouts/dashboard-layout.tsx"),
    source("src/lib/published-content-access.ts"),
  ]);

  assert.match(shell, /canReadPublishedContent\(user\)/);
  assert.match(access, /content\.read\.published/);
  assert.match(access, /pathname === "\/dashboard\/information-center"/);
  assert.match(access, /pathname\.startsWith\("\/dashboard\/information-center\/"\)/);
  assert.match(dashboard, /canEnterInformationCenterPath\(roleCode, pathname\)/);
  assert.match(layout, /permission: "content\.read\.published"/);
  assert.match(layout, /"satgas_ppks", "reporter"/);
  assert.equal(canReadPublishedContent(null), false);
  assert.equal(
    canReadPublishedContent({ is_active: false, permissions: ["content.read.published"] } as never),
    false,
  );
  assert.equal(canReadPublishedContent({ is_active: true, permissions: [] } as never), false);
  assert.equal(
    canReadPublishedContent({ is_active: true, permissions: ["content.read.published"] } as never),
    true,
  );

  const [portalLayout, portalOverview] = await Promise.all([
    source("src/layouts/portal-layout.tsx"),
    source("src/routes/portal.index.tsx"),
  ]);
  assert.match(portalLayout, /canReadPublishedContent\(user\)/);
  assert.match(portalOverview, /publishedContentAccessible &&/);
});

test("reader URL state is normalized while user actions remain navigable in history", () => {
  const normalized = normalizeInformationCenterSearch({
    view: "faq",
    category: "A8098C1A-5A25-4D1C-9DCE-7BDBE3171730",
    q: "  dukungan  ",
    page: "2",
    open: "not-a-uuid",
  });
  assert.deepEqual(normalized, {
    view: "faq",
    section: undefined,
    category: "a8098c1a-5a25-4d1c-9dce-7bdbe3171730",
    q: "dukungan",
    page: 2,
    open: undefined,
  });
  assert.equal(
    informationCenterSearchNeedsNormalization(
      "?view=faq&category=A8098C1A-5A25-4D1C-9DCE-7BDBE3171730&q=%20dukungan%20&page=2&open=bad",
      normalized,
    ),
    true,
  );
  assert.equal(
    normalizeInformationCenterSearch({ view: "faq", open: "a".repeat(1_000) }).open,
    undefined,
  );

  const history = [normalizeInformationCenterSearch({})];
  let cursor = 0;
  const push = (next: Parameters<typeof mergeInformationCenterSearch>[1]) => {
    history.splice(cursor + 1);
    history.push(mergeInformationCenterSearch(history[cursor], next));
    cursor += 1;
  };
  push({ section: "education" });
  push({ q: "batasan", page: undefined });
  cursor -= 1;
  assert.equal(history[cursor].section, "education");
  assert.equal(history[cursor].q, undefined);
  cursor += 1;
  assert.equal(history[cursor].q, "batasan");

  const faqId = "a8098c1a-5a25-4d1c-9dce-7bdbe3171730";
  push({ view: "faq", section: undefined, q: undefined, open: faqId });
  assert.equal(history[cursor].open, faqId);
  push({ open: undefined });
  cursor -= 1;
  assert.equal(history[cursor].open, faqId);
  cursor += 1;
  assert.equal(history[cursor].open, undefined);

  assert.equal(
    categoryBelongsToReaderContext(
      { public_id: "category", section_code: "policy" },
      "articles",
      "education",
    ),
    false,
  );
  assert.equal(
    categoryBelongsToReaderContext({ public_id: "category", section_code: "faq" }, "faq"),
    true,
  );
});

test("article cards use semantic full-card navigation and a complete no-image treatment", async () => {
  const card = await source("src/components/content/published-article-card.tsx");

  assert.match(card, /<Link[\s\S]+to="\/dashboard\/information-center\/articles\/\$articleId"/);
  assert.match(card, /focus-visible:ring-2/);
  assert.match(card, /motion-reduce:transform-none/);
  assert.match(card, /min-h-11/);
  assert.match(card, /sectionVisuals/);
  assert.match(card, /!hasSafeCover/);
  assert.doesNotMatch(card, /price|Unsplash|placeholder\.com|thiings\.co/i);
  assert.doesNotMatch(card, /<button/);
});

test("featured experience uses server order, accessible Embla controls, and isolated states", async () => {
  const featured = await source("src/components/content/featured-article-section.tsx");

  assert.match(featured, /getFeaturedContent\(signal\)/);
  assert.match(featured, /articles\.map/);
  assert.doesNotMatch(featured, /\.sort\(/);
  assert.match(featured, /CarouselPrevious/);
  assert.match(featured, /CarouselNext/);
  assert.match(featured, /featured\.empty/);
  assert.match(featured, /featured\.error/);
  assert.match(featured, /basis-\[88%\]/);
});

test("reader screens keep filters in URL and render controlled content only", async () => {
  const [landing, detail, preview] = await Promise.all([
    source("src/routes/dashboard.information-center.index.tsx"),
    source("src/routes/dashboard.information-center.articles.$articleId.tsx"),
    source("src/components/content/content-document-preview.tsx"),
  ]);

  assert.match(landing, /validateSearch/);
  assert.match(landing, /getPublishedArticles\(filters, signal\)/);
  assert.match(landing, /getPublishedFaqs\(filters, signal\)/);
  assert.match(landing, /Accordion/);
  assert.match(landing, /pageHeadingRef\.current\?\.focus/);
  assert.match(landing, /hidden flex-wrap items-end gap-3 lg:flex/);
  assert.match(landing, /min-h-11 lg:hidden/);
  assert.match(landing, /htmlFor=\{`\$\{idPrefix\}-section`\}/);
  assert.match(landing, /aria-labelledby=\{`\$\{idPrefix\}-category-label`\}/);
  assert.doesNotMatch(landing, /min-w-(48|56)/);
  assert.match(detail, /article\.body \?\? null/);
  assert.match(detail, /pageHeadingRef\.current\?\.focus/);
  assert.doesNotMatch(detail, /body_html|dangerouslySetInnerHTML/);
  assert.doesNotMatch(preview, /dangerouslySetInnerHTML/);
});

test("consultation action builders reject unsafe destinations and normalize safe values", async () => {
  const consultation = await source("src/components/content/published-consultation-card.tsx");

  assert.match(consultation, /href=\{`mailto:/);
  assert.match(consultation, /href=\{`tel:/);
  assert.match(consultation, /https:\/\/wa\.me\/\$\{whatsapp\}/);
  assert.match(consultation, /window\.confirm/);
  assert.doesNotMatch(consultation, /[?&](text|message|report|identity)=/i);
  assert.equal(safeConsultationEmail(" bantuan@example.test "), "bantuan@example.test");
  assert.equal(safeConsultationEmail("bukan-email"), null);
  assert.equal(normalizeConsultationPhone("+62 812-3456-789"), "+628123456789");
  assert.equal(normalizeConsultationWhatsApp("08123456789"), null);
  assert.equal(normalizeConsultationWhatsApp("+62 812-3456-789"), "628123456789");
  assert.equal(safeConsultationHttpsUrl("javascript:alert(1)"), null);
  assert.equal(safeConsultationHttpsUrl("https://user:secret@example.test"), null);
  assert.equal(
    safeConsultationHttpsUrl("https://example.test/janji"),
    "https://example.test/janji",
  );
});

test("authenticated PDF preview is single-flight, cleans Object URLs, and falls back when blocked", async () => {
  const [preview, attachment] = await Promise.all([
    source("src/components/secure-file-preview-dialog.tsx"),
    source("src/components/content/published-content-attachment.tsx"),
  ]);

  assert.match(preview, /createAuthenticatedPdfPreview/);
  assert.match(attachment, /if \(downloading\) return/);
  assert.match(attachment, /fetchContentAttachment\(attachment\.public_id, signal\)/);
  assert.match(attachment, /\[&_button\]:min-h-11/);

  let resolveLoad!: (value: { blob: string }) => void;
  const load = new Promise<{ blob: string }>((resolve) => {
    resolveLoad = resolve;
  });
  const revoked: string[] = [];
  const pending: boolean[] = [];
  let replaced = "";
  const tab = {
    closed: false,
    opener: {},
    document: { title: "", body: { textContent: null } },
    location: { replace: (url: string) => (replaced = url) },
    addEventListener: () => undefined,
    close() {
      this.closed = true;
    },
  } satisfies PdfPreviewTab;
  const controller = createAuthenticatedPdfPreview({
    load: () => load,
    validate: (response) => assert.equal(response.blob, "pdf"),
    openTab: () => tab,
    createObjectUrl: () => "blob:private-pdf",
    revokeObjectUrl: (url) => revoked.push(url),
    setTimer: () => 1 as never,
    clearTimer: () => undefined,
    title: () => "Private PDF",
    loadingText: () => "Loading",
    onPending: (value) => pending.push(value),
    onPopupBlocked: () => assert.fail("popup should be available"),
    onError: () => assert.fail("preview should succeed"),
  });
  const first = controller.open();
  assert.equal(await controller.open(), "ignored");
  resolveLoad({ blob: "pdf" });
  assert.equal(await first, "opened");
  assert.match(replaced, /^blob:private-pdf#/);
  assert.deepEqual(pending, [true, false]);
  controller.dispose();
  assert.deepEqual(revoked, ["blob:private-pdf"]);

  let fallbackCount = 0;
  const blocked = createAuthenticatedPdfPreview({
    load: async () => "unused",
    validate: () => undefined,
    openTab: () => null,
    createObjectUrl: () => "unused",
    revokeObjectUrl: () => undefined,
    setTimer: () => 1 as never,
    clearTimer: () => undefined,
    title: () => "",
    loadingText: () => "",
    onPending: () => undefined,
    onPopupBlocked: () => fallbackCount++,
    onError: () => undefined,
  });
  assert.equal(await blocked.open(), "fallback");
  assert.equal(fallbackCount, 1);
});

test("private query clearing cancels in-flight reader data before removal", async () => {
  const calls: string[] = [];
  let predicate: ((query: { queryKey: readonly unknown[] }) => boolean) | undefined;
  const queryClient = {
    async cancelQueries(options: typeof queryClientOptions) {
      calls.push("cancel");
      predicate = options.predicate;
    },
    removeQueries() {
      calls.push("remove");
    },
  };
  const queryClientOptions = { predicate: (_query: { queryKey: readonly unknown[] }) => true };
  await clearPrivateContentQueries(queryClient as never);
  assert.deepEqual(calls, ["cancel", "remove"]);
  assert.equal(predicate?.({ queryKey: ["published-content", 99] }), true);
  assert.equal(predicate?.({ queryKey: ["portal", 99] }), false);
});

test("carousel keyboard behavior maps only horizontal arrow keys", () => {
  assert.equal(carouselActionForKey("ArrowLeft"), "previous");
  assert.equal(carouselActionForKey("ArrowRight"), "next");
  assert.equal(carouselActionForKey("Enter"), null);
});

test("manifest remains app-shell-only and does not introduce a service worker cache", async () => {
  const [manifest, root, files] = await Promise.all([
    source("public/manifest.webmanifest"),
    source("src/routes/__root.tsx"),
    source("vite.config.ts"),
  ]);

  const parsed = JSON.parse(manifest);
  assert.equal(parsed.start_url, "/login");
  assert.equal(parsed.display, "standalone");
  assert.match(root, /manifest\.webmanifest/);
  assert.doesNotMatch(files, /VitePWA|workbox|serviceWorker/);
});

test("Indonesian and English Information Center locales remain in parity", async () => {
  const [id, en] = await Promise.all([
    source("src/locales/id/informationCenter.json").then(JSON.parse),
    source("src/locales/en/informationCenter.json").then(JSON.parse),
  ]);
  const keys = (value: unknown, prefix = ""): string[] =>
    value && typeof value === "object" && !Array.isArray(value)
      ? Object.entries(value).flatMap(([key, child]) =>
          keys(child, prefix ? `${prefix}.${key}` : key),
        )
      : [prefix];

  assert.deepEqual(keys(id).sort(), keys(en).sort());
});
