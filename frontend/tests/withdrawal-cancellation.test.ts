import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const source = (path: string) => readFile(new URL(`../${path}`, import.meta.url), "utf8");

function keyPaths(value: unknown, prefix = ""): string[] {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return [prefix];
  }

  return Object.entries(value).flatMap(([key, child]) =>
    keyPaths(child, prefix ? `${prefix}.${key}` : key),
  );
}

test("Reporter detail renders direct cancellation only from authoritative capability", async () => {
  const route = await source("src/routes/portal.reports.$registrationNumber.tsx");

  assert.match(
    route,
    /report\.withdrawal_capabilities\.can_cancel\s*&&\s*\(\s*<CancelComplaintDialog/,
  );
  assert.doesNotMatch(route, /can_request_withdrawal\s*&&/);
  assert.match(route, /registrationNumber=\{report\.registration_number\}/);
});

test("direct cancellation dialog validates reason and explicit destructive confirmation", async () => {
  const dialog = await source("src/components/portal/cancel-complaint-dialog.tsx");

  assert.match(dialog, /\.min\(20,\s*messages\.reasonMin\)/);
  assert.match(dialog, /\.max\(2000,\s*messages\.reasonMax\)/);
  assert.match(dialog, /confirmed:\s*z\.boolean\(\)\.refine/);
  assert.match(dialog, /variant="destructive"/);
  assert.match(dialog, /mutation\.isPending/);
  assert.match(dialog, /role="alert"/);
  assert.match(dialog, /<DialogTitle>/);
  assert.match(dialog, /<DialogDescription>/);
  assert.match(dialog, /<FormLabel/);
  assert.doesNotMatch(dialog, /\b(?:bg-white|text-black|bg-black|text-white)\b/);
});

test("mutation uses the existing portal route and refreshes all affected Reporter caches", async () => {
  const [api, dialog] = await Promise.all([
    source("src/lib/portal-api.ts"),
    source("src/components/portal/cancel-complaint-dialog.tsx"),
  ]);

  assert.match(
    api,
    /`\/portal\/reports\/\$\{encodeURIComponent\(registrationNumber\)\}\/cancel`/,
  );
  assert.match(api, /method:\s*"POST"/);
  assert.match(dialog, /portalQueryKeys\.report\(registrationNumber\)/);
  assert.match(dialog, /portalQueryKeys\.reportsRoot\(\)/);
  assert.match(dialog, /portalQueryKeys\.summary\(\)/);
  assert.match(dialog, /portalQueryKeys\.reportTimeline\(registrationNumber\)/);
  assert.match(dialog, /portalQueryKeys\.reportHandlingProgress\(registrationNumber\)/);
  assert.match(dialog, /portalQueryKeys\.reportEvidenceFiles\(registrationNumber\)/);
});

test("cancelled status is localized and rendered with a terminal visual", async () => {
  const [labels, badge, portalId, portalEn] = await Promise.all([
    source("src/lib/portal-labels.ts"),
    source("src/components/portal/portal-status-badge.tsx"),
    source("src/locales/id/portal.json").then(JSON.parse),
    source("src/locales/en/portal.json").then(JSON.parse),
  ]);

  assert.match(labels, /"cancelled_by_reporter"/);
  assert.match(badge, /case "cancelled_by_reporter"/);
  assert.equal(portalId.statusCodes.cancelled_by_reporter, "Dibatalkan oleh Pelapor");
  assert.equal(portalEn.statusCodes.cancelled_by_reporter, "Cancelled by Reporter");
  assert.equal(portalId.cancellation.trigger, "Batalkan Pengaduan");
  assert.equal(portalEn.cancellation.trigger, "Cancel Complaint");
});

test("REV-WITHDRAW-01A exposes no formal withdrawal action or upload UI", async () => {
  const files = await Promise.all([
    source("src/components/portal/cancel-complaint-dialog.tsx"),
    source("src/routes/portal.reports.$registrationNumber.tsx"),
  ]);
  const ui = files.join("\n");

  assert.doesNotMatch(ui, /FormalWithdrawal|formal_withdrawal/);
  assert.doesNotMatch(ui, /withdrawal.*upload|upload.*withdrawal/i);
  assert.doesNotMatch(ui, /request.*withdrawal|withdrawal.*request/i);
});

test("withdrawn Case detail is read-only while retained assignments remain visible", async () => {
  const detail = await source("src/routes/dashboard.cases.$id.tsx");

  assert.match(
    detail,
    /const isOperationallyTerminalCase = \["closed", "csts_14", "withdrawn", "csts_16"\]\.includes/,
  );
  assert.match(detail, /canManageAssignments =[\s\S]*!isOperationallyTerminalCase/);
  assert.match(detail, /canUseSatgasActions = isAssignedSatgas && !isOperationallyTerminalCase/);
  assert.match(detail, /\(c\.assignments \?\? \[\]\)\.map/);
  assert.doesNotMatch(detail, /canManageAssignments && !c\.closed_at/);
});

test("Reporter portal locale keys remain in Indonesian and English parity", async () => {
  const [indonesian, english] = await Promise.all([
    source("src/locales/id/portal.json").then(JSON.parse),
    source("src/locales/en/portal.json").then(JSON.parse),
  ]);

  assert.deepEqual(keyPaths(indonesian).sort(), keyPaths(english).sort());
});
