import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

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

test("Information Center route is permission guarded for Reporter and Satgas readers", async () => {
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
  assert.match(detail, /article\.body \?\? null/);
  assert.match(detail, /pageHeadingRef\.current\?\.focus/);
  assert.doesNotMatch(detail, /body_html|dangerouslySetInnerHTML/);
  assert.doesNotMatch(preview, /dangerouslySetInnerHTML/);
});

test("consultation actions accept only safe destinations and do not prefill report data", async () => {
  const consultation = await source("src/components/content/published-consultation-card.tsx");

  assert.match(consultation, /url\.protocol === "https:"/);
  assert.match(consultation, /href=\{`mailto:/);
  assert.match(consultation, /href=\{`tel:/);
  assert.match(consultation, /https:\/\/wa\.me\/\$\{whatsapp\}/);
  assert.match(consultation, /window\.confirm/);
  assert.doesNotMatch(consultation, /[?&](text|message|report|identity)=/i);
});

test("authenticated PDF fallback and Object URL cleanup are deterministic", async () => {
  const [preview, attachment] = await Promise.all([
    source("src/components/secure-file-preview-dialog.tsx"),
    source("src/components/content/published-content-attachment.tsx"),
  ]);

  assert.match(preview, /if \(!previewTab\)[\s\S]+onDownload\(\)/);
  assert.match(preview, /pdfCleanupRef\.current\?\.\(\)/);
  assert.match(preview, /URL\.revokeObjectURL\(nextUrl\)/);
  assert.match(preview, /if \(pdfAbortRef\.current\) return/);
  assert.match(attachment, /if \(downloading\) return/);
  assert.match(attachment, /fetchContentAttachment\(attachment\.public_id, signal\)/);
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
