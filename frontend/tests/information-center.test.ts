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
  assert.match(api, /getFeaturedContent\(filters: FeaturedContentFilters = \{\}, signal\?: AbortSignal\)/);
  assert.match(api, /getPublishedArticleBySlug/);
  assert.match(api, /\/content\/articles\/slug\/\$\{section\}\/\$\{encodeURIComponent\(slug\)\}/);
  assert.match(api, /getPublishedArticleCategories/);
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

test("legacy information center route redirects to the role-appropriate reader", async () => {
  const [route, dashboardRoute] = await Promise.all([
    source("src/routes/information-center.tsx"),
    source("src/routes/dashboard.tsx"),
  ]);

  assert.match(route, /createFileRoute\("\/information-center"\)/);
  assert.match(route, /to="\/portal\/information-center"/);
  assert.match(route, /to="\/dashboard\/information-center"/);
  assert.match(route, /canReadPublishedContent/);
  assert.match(dashboardRoute, /canEnterInformationCenterPath\(roleCode, pathname\)/);
  assert.match(dashboardRoute, /to="\/portal\/information-center"/);
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

  assert.match(card, /<Link[\s\S]+to=\{portal \? detailTo : "\/dashboard\/information-center\/articles\/\$articleId"\}/);
  assert.match(card, /\/portal\/information-center\/education\/\$slug/);
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

  assert.match(featured, /getFeaturedContent\(filters, signal\)/);
  assert.match(featured, /articles\.map/);
  assert.doesNotMatch(featured, /\.sort\(/);
  assert.match(featured, /CarouselPrevious/);
  assert.match(featured, /CarouselNext/);
  assert.match(featured, /if \(query\.isError\) return null/);
  assert.match(featured, /if \(articles\.length === 0\) return null/);
  assert.match(featured, /basis-\[88%\]/);
});

test("reporter information center uses dedicated routes, breadcrumbs, isolated categories, and report CTAs", async () => {
  const [layout, home, educationRoute, policiesRoute, list, detail, breadcrumb, faq, consultation, spotlight, cta, dashboard] = await Promise.all([
    source("src/layouts/portal-layout.tsx"),
    source("src/routes/portal.information-center.index.tsx"),
    source("src/routes/portal.information-center.education.tsx"),
    source("src/routes/portal.information-center.policies.tsx"),
    source("src/components/content/reporter-article-list-page.tsx"),
    source("src/components/content/reporter-article-detail-page.tsx"),
    source("src/components/content/reporter-information-breadcrumb.tsx"),
    source("src/routes/portal.information-center.faq.tsx"),
    source("src/routes/portal.information-center.consultation.tsx"),
    source("src/components/content/education-spotlight.tsx"),
    source("src/components/content/reporter-information-cta.tsx"),
    source("src/routes/portal.index.tsx"),
  ]);
  assert.match(layout, /url: "\/portal\/information-center"/);
  for (const route of ["education", "policies", "faq", "consultation"]) assert.ok(home.includes(`/portal/information-center/${route}`));
  assert.doesNotMatch(home, /getPublishedArticles|getFeaturedContent|getPublishedFaqs/);
  assert.match(educationRoute, /<Outlet \/>/);
  assert.match(policiesRoute, /<Outlet \/>/);
  assert.match(list, /getPublishedArticleCategories/);
  assert.match(list, /article_category: category/);
  assert.match(detail, /getPublishedArticleBySlug/);
  assert.match(detail, /getPublishedArticleBySlug\(section, slug, signal\)/);
  assert.doesNotMatch(detail, /article\.section\.code !== section/);
  assert.match(detail, /section=\{\{ label: sectionTitle, to: listTo \}\}/);
  assert.match(detail, /article\.defaultCover/);
  assert.match(breadcrumb, /portal:overview/);
  assert.doesNotMatch(detail, /history\.back|consultation_cta/);
  assert.match(faq, /Accordion/);
  assert.match(faq, /filters\.faqSearchPlaceholder/);
  assert.match(consultation, /PublishedConsultationCard/);
  assert.doesNotMatch(spotlight, /require_cover: true/);
  assert.match(spotlight, /article\.cover && !coverUnavailable/);
  assert.match(spotlight, /bg-gradient-to-br/);
  assert.doesNotMatch(dashboard, /FeaturedArticleSection/);
  assert.match(cta, /to="\/portal\/reports\/new"/);
});

test("reporter cover surfaces preserve a 16:9 education fallback on image failure", async () => {
  const [detail, spotlight, authenticatedCover] = await Promise.all([
    source("src/components/content/reporter-article-detail-page.tsx"),
    source("src/components/content/education-spotlight.tsx"),
    source("src/components/content/authenticated-content-cover.tsx"),
  ]);

  assert.match(detail, /aspect-video/);
  assert.match(detail, /object-cover/);
  assert.match(detail, /onUnavailable=\{markUnavailable\}/);
  assert.match(detail, /from-sky-950 via-primary to-cyan-700/);
  assert.match(spotlight, /onUnavailable=\{markUnavailable\}/);
  assert.match(spotlight, /from-sky-950 via-blue-800 to-cyan-600/);
  assert.match(authenticatedCover, /onError=\{onUnavailable\}/);
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
  assert.equal(id.filters.faqSearchPlaceholder, "Cari pertanyaan...");
  assert.equal(en.filters.faqSearchPlaceholder, "Search questions...");
  assert.equal("consultationTitle" in id.article, false);
  assert.equal("consultationDescription" in en.article, false);
});
