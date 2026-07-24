import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";
import {
  validateWithdrawalDocumentFile,
  WITHDRAWAL_DOCUMENT_MAX_BYTES,
} from "../src/lib/withdrawal-document-file.ts";

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
    /report\.withdrawal_capabilities\.can_cancel\s*\?\s*\(\s*<CancelComplaintDialog/,
  );
  assert.match(route, /can_request_withdrawal\s*\|\|\s*[\s\S]*active_withdrawal/);
  assert.match(route, /<FormalWithdrawalWizard/);
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

test("REV-WITHDRAW-01B wizard restores state from backend and has no Admin decision UI", async () => {
  const wizard = await source("src/components/portal/formal-withdrawal-wizard.tsx");

  assert.match(wizard, /queryKey:\s*portalQueryKeys\.reportWithdrawal\(registrationNumber\)/);
  assert.match(wizard, /queryFn:\s*\(\)\s*=>\s*getPortalFormalWithdrawal\(registrationNumber\)/);
  assert.match(wizard, /withdrawalQuery\.data\s*\?\?\s*createMutation\.data/);
  assert.match(wizard, /activeWithdrawal\?\.status/);
  assert.match(wizard, /effectiveCapabilities\.can_view_draft/);
  assert.match(wizard, /effectiveCapabilities\.can_upload_document/);
  assert.match(wizard, /effectiveCapabilities\.can_submit/);
  assert.match(wizard, /effectiveCapabilities\.can_cancel_request/);
  assert.doesNotMatch(wizard, /approve|reject|reviewer/i);
  assert.doesNotMatch(wizard, /localStorage|sessionStorage|console\./);
});

test("formal withdrawal API uses authenticated text, upload, submit, cancel, and download routes", async () => {
  const [api, client] = await Promise.all([
    source("src/lib/portal-api.ts"),
    source("src/lib/api-client.ts"),
  ]);

  assert.match(api, /\/portal\/reports\/\$\{encodeURIComponent\(registrationNumber\)\}\/withdrawal/);
  assert.match(api, /\/portal\/reports\/\$\{encodeURIComponent\(registrationNumber\)\}\/withdrawals/);
  assert.match(api, /\/portal\/withdrawals\/\$\{encodeURIComponent\(publicId\)\}\/draft-document/);
  assert.match(api, /apiFetchText\(/);
  assert.match(api, /apiUpload<FormalWithdrawalDetail>/);
  assert.match(api, /body\.append\("lock_version", String\(lockVersion\)\)/);
  assert.match(api, /JSON\.stringify\(\{ lock_version: lockVersion \}\)/);
  assert.match(api, /signed-document\/\$\{encodeURIComponent\(attachmentPublicId\)\}/);
  assert.match(api, /\/submit`/);
  assert.match(api, /\/cancel`/);
  assert.match(client, /export async function apiFetchText/);
  assert.match(client, /headers\.set\("Authorization", `Bearer \$\{token\}`\)/);
});

test("formal withdrawal reason, upload, and submit controls are fail-closed", async () => {
  const wizard = await source("src/components/portal/formal-withdrawal-wizard.tsx");

  assert.match(wizard, /\.min\(20,\s*messages\.min\)/);
  assert.match(wizard, /\.max\(2000,\s*messages\.max\)/);
  assert.match(wizard, /confirmed:\s*z\.boolean\(\)\.refine/);
  assert.match(wizard, /!selectedFile \|\| effectiveLockVersion === null \|\| uploadMutation\.isPending/);
  assert.match(
    wizard,
    /!effectiveCapabilities\.can_submit[\s\S]*effectiveLockVersion === null[\s\S]*submitMutation\.isPending/,
  );
  assert.match(wizard, /latestVersion/);
  assert.match(wizard, /uploadProgress/);
  assert.match(wizard, /sandbox="allow-modals allow-same-origin"/);
  assert.doesNotMatch(wizard, /sandbox="[^"]*allow-scripts/);
  assert.match(wizard, /documentFrameRef\.current\?\.contentWindow/);
  assert.match(wizard, /printWindow\.print\(\)/);
  assert.doesNotMatch(wizard, /window\.open|URL\.createObjectURL/);
  assert.match(wizard, /htmlFor=\{`withdrawal-document-/);
});

test("signed withdrawal file validation enforces type, extension, path, size, and empty limits", () => {
  assert.equal(
    validateWithdrawalDocumentFile({
      name: "signed.pdf",
      size: WITHDRAWAL_DOCUMENT_MAX_BYTES,
      type: "application/pdf",
    }),
    null,
  );
  assert.equal(
    validateWithdrawalDocumentFile({ name: "signed.jpeg", size: 1, type: "image/jpeg" }),
    null,
  );
  assert.equal(
    validateWithdrawalDocumentFile({ name: "signed.png", size: 1, type: "image/png" }),
    null,
  );
  assert.equal(
    validateWithdrawalDocumentFile({ name: "signed.svg", size: 1, type: "image/svg+xml" }),
    "invalidFormat",
  );
  assert.equal(
    validateWithdrawalDocumentFile({ name: "../signed.pdf", size: 1, type: "application/pdf" }),
    "unsafeFilename",
  );
  assert.equal(
    validateWithdrawalDocumentFile({ name: "signed.v2.pdf", size: 1, type: "application/pdf" }),
    "unsafeFilename",
  );
  assert.equal(
    validateWithdrawalDocumentFile({ name: "signed.pdf", size: 0, type: "application/pdf" }),
    "emptyFile",
  );
  assert.equal(
    validateWithdrawalDocumentFile({
      name: "signed.pdf",
      size: WITHDRAWAL_DOCUMENT_MAX_BYTES + 1,
      type: "application/pdf",
    }),
    "fileTooLarge",
  );
});

test("formal mutations invalidate every Reporter and operational cache affected by pause", async () => {
  const wizard = await source("src/components/portal/formal-withdrawal-wizard.tsx");

  for (const expected of [
    "portalQueryKeys.report(registrationNumber)",
    "portalQueryKeys.reportsRoot()",
    "portalQueryKeys.summary()",
    "portalQueryKeys.reportWithdrawal(registrationNumber)",
    "portalQueryKeys.reportTimeline(registrationNumber)",
    "portalQueryKeys.reportHandlingProgress(registrationNumber)",
    "portalQueryKeys.reportEvidenceFiles(registrationNumber)",
    'queryKey: ["dashboard"]',
    'queryKey: ["operations", "cases"]',
  ]) {
    assert.ok(wizard.includes(expected), `Missing cache invalidation: ${expected}`);
  }
});

test("formal status, timeline, DRAFT, upload, and cancellation copy has ID/EN parity", async () => {
  const [portalId, portalEn] = await Promise.all([
    source("src/locales/id/portal.json").then(JSON.parse),
    source("src/locales/en/portal.json").then(JSON.parse),
  ]);

  assert.equal(
    portalId.withdrawal.status.pending_review,
    "Menunggu Verifikasi Pencabutan",
  );
  assert.equal(
    portalEn.withdrawal.status.pending_review,
    "Pending Withdrawal Verification",
  );
  assert.match(portalId.withdrawal.documentDescription, /DRAF/);
  assert.match(portalEn.withdrawal.documentDescription, /DRAFT/);
  assert.ok(portalId.withdrawal.fileValidation.unsafeFilename);
  assert.ok(portalEn.withdrawal.fileValidation.unsafeFilename);
  assert.ok(portalId.timeline.stages.permohonan_pencabutan_dibuat);
  assert.ok(portalEn.timeline.stages.permohonan_pencabutan_dibuat);
  assert.ok(portalId.timeline.stages.pencabutan_dikirim_untuk_verifikasi);
  assert.ok(portalEn.timeline.stages.pencabutan_dikirim_untuk_verifikasi);
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
