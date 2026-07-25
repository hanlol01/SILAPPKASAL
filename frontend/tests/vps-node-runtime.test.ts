import assert from "node:assert/strict";
import { readFile, stat } from "node:fs/promises";
import test from "node:test";

const source = (path: string) => readFile(new URL(`../${path}`, import.meta.url), "utf8");
const pngSignature = Buffer.from([137, 80, 78, 71, 13, 10, 26, 10]);

test("VPS build explicitly targets Nitro's Node server bundle", async () => {
  const viteConfig = await source("vite.config.ts");

  assert.match(viteConfig, /nitro:\s*\{[\s\S]*preset:\s*"node-server"/);
  assert.match(viteConfig, /serverDir:\s*"dist\/server"/);
  assert.match(viteConfig, /publicDir:\s*"dist\/client"/);
  assert.doesNotMatch(viteConfig, /nitro:\s*true|cloudflare-module|wrangler|miniflare/i);
});

test("production start runs the generated Node entry instead of Vite preview", async () => {
  const packageJson = JSON.parse(await source("package.json")) as {
    scripts: Record<string, string>;
  };

  assert.equal(packageJson.scripts.build, "vite build");
  assert.equal(packageJson.scripts.start, "node dist/server/index.mjs");
  assert.doesNotMatch(packageJson.scripts.start, /vite\s+preview|wrangler|miniflare|\bdev\b/i);
  assert.match(packageJson.scripts.preview, /vite\s+preview/);
});

test("deployment guides agree with the Node runtime and systemd contract", async () => {
  const [guide, update] = await Promise.all([
    source("../docs/deployment/DEPLOYMENT_DEMO_VPS.md"),
    source("../docs/deployment/DEPLOYMENT_UPDATE.md"),
  ]);

  for (const document of [guide, update]) {
    assert.match(document, /\bnpm ci\b/);
    assert.match(document, /\bnpm run build\b/);
    assert.match(document, /\bnpm run start\b/);
    assert.match(document, /dist\/server\/index\.mjs/);
    assert.match(document, /dist\/client/);
    assert.match(document, /node-server/);
    assert.doesNotMatch(document, /\bnpm install\b/);
  }

  assert.match(guide, /ExecStart=\/usr\/bin\/npm run start/);
  assert.match(guide, /User=ubuntu/);
  assert.match(guide, /Group=ubuntu/);
  assert.match(guide, /Environment=NODE_ENV=production/);
  assert.match(guide, /Environment=HOST=127\.0\.0\.1/);
  assert.match(guide, /Environment=PORT=3000/);
  assert.match(guide, /proxy_pass http:\/\/127\.0\.0\.1:3000/);
  assert.doesNotMatch(guide, /ExecStart=.*vite preview/);
  assert.doesNotMatch(guide, /ExecStart=.*(?:wrangler|miniflare)/i);
  assert.match(update, /not production runtime requirements/);
});

test("institutional branding assets remain local production assets", async () => {
  const assets = ["kemenag.png", "lpdp.png", "lpdp_white.png", "uniga.png"];

  for (const asset of assets) {
    const path = new URL(`../public/brand/institutional-support/${asset}`, import.meta.url);
    const [details, binary] = await Promise.all([stat(path), readFile(path)]);

    assert.ok(details.size > 0, `${asset} must not be empty`);
    assert.deepEqual(binary.subarray(0, pngSignature.length), pngSignature, `${asset} must be a PNG`);
  }
});

test("root footer remains synchronous and fail-closed for initial routes", async () => {
  const root = await source("src/routes/__root.tsx");

  assert.match(root, /const shouldRenderPublicFooter =/);
  assert.match(root, /pathname === "\/information-center"/);
  assert.match(root, /pathname\.startsWith\("\/information-center\/"\)/);
  assert.doesNotMatch(root, /useEffect|useState|window\.location|localStorage/);
});
