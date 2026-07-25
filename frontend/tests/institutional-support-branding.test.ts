import assert from "node:assert/strict";
import { readFile, stat } from "node:fs/promises";
import test from "node:test";

const source = (path: string) => readFile(new URL(`../${path}`, import.meta.url), "utf8");
const pngSignature = Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]);

function keyPaths(value: unknown, prefix = ""): string[] {
  if (!value || typeof value !== "object" || Array.isArray(value)) return [prefix];

  return Object.entries(value).flatMap(([key, child]) =>
    keyPaths(child, prefix ? `${prefix}.${key}` : key),
  );
}

test("institutional support uses only approved local PNG assets", async () => {
  const assets = ["kemenag.png", "lpdp.png", "lpdp_white.png", "uniga.png"];

  for (const asset of assets) {
    const url = new URL(`../public/brand/institutional-support/${asset}`, import.meta.url);
    const [details, binary] = await Promise.all([stat(url), readFile(url)]);

    assert.ok(details.size > 0, `${asset} must not be empty`);
    assert.deepEqual(binary.subarray(0, pngSignature.length), pngSignature, `${asset} must be a PNG`);
    assert.match(asset, /^[a-z_]+\.png$/);
  }
});

test("institutional support component is stateless, accessible, and free of remote or testimonial UI", async () => {
  const component = await source("src/components/ui/institutional-support.tsx");

  assert.match(component, /export function InstitutionalSupport/);
  assert.match(component, /variant\?: "featured" \| "compact"/);
  assert.match(component, /tone\?: "light" \| "dark" \| "auto"/);
  assert.match(component, /Kementerian Agama Republik Indonesia/);
  assert.match(component, /Lembaga Pengelola Dana Pendidikan/);
  assert.match(component, /Universitas Garut/);
  assert.match(component, /aria-label=\{t\("institutionalSupport\.sectionLabel"\)\}/);
  assert.match(component, /object-contain/);
  assert.match(component, /lpdp_white\.png/);
  assert.match(component, /dark:hidden/);
  assert.match(component, /hidden h-auto w-auto object-contain dark:block/);
  assert.match(component, /tone === "dark"\s*\?\s*"bg-transparent"/);
  assert.match(component, /bg-muted\/40 dark:bg-transparent/);
  assert.doesNotMatch(component, /const colorLogoSurfaceClassName = tone === "dark"\s*\?\s*"bg-white/);
  assert.doesNotMatch(component, /bg-slate-950/);
  assert.match(component, /grid grid-cols-3 divide-x divide-border\/70/);
  assert.doesNotMatch(component, /grid-cols-1/);
  assert.match(component, /max-h-7 max-w-\[4\.5rem\] sm:max-h-10 sm:max-w-40/);

  assert.doesNotMatch(component, /https?:\/\/|data:image|base64|<svg|<a\b|<Link\b/);
  assert.doesNotMatch(component, /Tooltip|useState|ArrowUp|ArrowDown|hoveredImage/);
  assert.doesNotMatch(component, /testimonial|percentage|companies|employees|compensation/i);
  assert.doesNotMatch(component, /upload|pdf/i);
  assert.doesNotMatch(component, /min-w-\[/);
});

test("featured login support is separate by responsive placement without a compact duplicate", async () => {
  const login = await source("src/routes/login.tsx");
  const featuredPlacements = [...login.matchAll(/<InstitutionalSupport variant="featured" tone="(?:dark|light)" \/>/g)];

  assert.equal(featuredPlacements.length, 2);
  assert.match(login, /<InstitutionalSupport variant="featured" tone="dark" \/>/);
  assert.match(login, /<div className="mt-6 lg:hidden">[\s\S]*?<InstitutionalSupport variant="featured" tone="light" \/>/);
  assert.doesNotMatch(login, /variant="compact"/);
  assert.match(login, /login\(identifier, password, remember\)/);
});

test("shared Dashboard, Portal, and public shells each project the compact support footer", async () => {
  const [dashboard, portal, root] = await Promise.all([
    source("src/layouts/dashboard-layout.tsx"),
    source("src/layouts/portal-layout.tsx"),
    source("src/routes/__root.tsx"),
  ]);

  for (const shell of [dashboard, portal, root]) {
    assert.match(shell, /<InstitutionalSupport variant="compact" tone="auto" \/>/);
    assert.match(shell, /<footer/);
  }

  assert.match(root, /const shouldRenderPublicFooter =/);
  assert.match(root, /pathname === "\/information-center"/);
  assert.match(root, /pathname\.startsWith\("\/information-center\/"\)/);
  assert.match(root, /pathname === "\/register"/);
  assert.match(root, /pathname === "\/track"/);
  assert.match(root, /\{shouldRenderPublicFooter \? \(/);
});

test("root footer fails closed during the initial route state and excludes login synchronously", async () => {
  const root = await source("src/routes/__root.tsx");

  assert.doesNotMatch(root, /hasNestedShellFooter|const isLogin|!hasNestedShellFooter && !isLogin/);
  assert.doesNotMatch(root, /useEffect|useState|window\.location|localStorage/);
  assert.match(root, /const pathname = useRouterState\(\{ select: \(state\) => state\.location\.pathname \}\)/);
  assert.match(root, /<Outlet \/>[\s\S]*?\{shouldRenderPublicFooter \? \(/);
});

test("institutional support copy has Indonesian and English locale parity", async () => {
  const [indonesian, english] = await Promise.all([
    source("src/locales/id/common.json").then(JSON.parse),
    source("src/locales/en/common.json").then(JSON.parse),
  ]);

  assert.deepEqual(keyPaths(indonesian).sort(), keyPaths(english).sort());
  assert.equal(indonesian.institutionalSupport.badge, "Dukungan Institusi");
  assert.equal(indonesian.institutionalSupport.featuredHeading, "SILAPPKASAL dikembangkan dengan dukungan dari");
  assert.equal(indonesian.institutionalSupport.compactHeading, "Dengan dukungan dari");
  assert.equal(english.institutionalSupport.badge, "Institutional Support");
  assert.equal(english.institutionalSupport.featuredHeading, "SILAPPKASAL is developed with support from");
  assert.equal(english.institutionalSupport.compactHeading, "With support from");
});
