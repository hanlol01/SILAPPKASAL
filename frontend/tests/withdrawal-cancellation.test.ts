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

test("Reporter wizard restores state from backend without Admin decision mutations", async () => {
  const wizard = await source("src/components/portal/formal-withdrawal-wizard.tsx");

  assert.match(wizard, /queryKey:\s*portalQueryKeys\.reportWithdrawal\(registrationNumber\)/);
  assert.match(wizard, /queryFn:\s*\(\)\s*=>\s*getPortalFormalWithdrawal\(registrationNumber\)/);
  assert.match(wizard, /withdrawalQuery\.data\s*\?\?\s*createMutation\.data/);
  assert.match(wizard, /activeWithdrawal\?\.status/);
  assert.match(wizard, /effectiveCapabilities\.can_view_draft/);
  assert.match(wizard, /effectiveCapabilities\.can_upload_document/);
  assert.match(wizard, /effectiveCapabilities\.can_submit/);
  assert.match(wizard, /effectiveCapabilities\.can_cancel_request/);
  assert.doesNotMatch(wizard, /approveReportWithdrawal|rejectReportWithdrawal|reviewed_by/);
  assert.doesNotMatch(wizard, /localStorage|sessionStorage|console\./);
});

test("Admin withdrawal queue is URL-synchronized, permission-aware, and explicitly non-SLA", async () => {
  const queue = await source("src/routes/dashboard.report-withdrawals.tsx");

  assert.match(queue, /validateSearch:/);
  assert.match(queue, /Route\.useSearch\(\)/);
  assert.match(queue, /Route\.useNavigate\(\)/);
  assert.match(queue, /operationsQueryKeys\.withdrawalReviews\(query\)/);
  assert.match(queue, /reports\.withdraw\.review\.own_campus/);
  assert.match(queue, /status\s*!==\s*"pending_review"\s*\|\|\s*Boolean\(search\.q\)/);
  assert.match(queue, /waitingDays/);
  assert.doesNotMatch(queue, /overdue|terlambat|SLA/);
  assert.doesNotMatch(queue, /placeholderData|keepPreviousData/);
});

test("Admin review detail is capability-gated and protects private document access", async () => {
  const detail = await source("src/routes/dashboard.report-withdrawals.$publicId.tsx");

  assert.match(detail, /item\.capabilities\.can_view_signed_document/);
  assert.match(detail, /item\.capabilities\.can_approve/);
  assert.match(detail, /item\.capabilities\.can_reject/);
  assert.match(detail, /rejectionReason\.trim\(\)\.length\s*>=\s*20/);
  assert.match(detail, /rejectionReason\.trim\(\)\.length\s*<=\s*2000/);
  assert.match(detail, /resubmission_allowed:\s*resubmissionAllowed/);
  assert.match(detail, /roleCode\s*===\s*"super_admin"/);
  assert.match(detail, /roleCode\s*===\s*"admin"\s*&&\s*item\.capabilities\.can_review/);
  assert.match(detail, /URL\.revokeObjectURL/);
  assert.match(detail, /URL\.revokeObjectURL\(preview\.url\)[\s\S]*setPreview\(null\)/);
  assert.match(detail, /previewRequestRef\.current\?\.abort\(\)/);
  assert.match(detail, /controller\.signal/);
  assert.match(detail, /controller\.signal\.aborted[\s\S]*URL\.revokeObjectURL\(url\)/);
  assert.match(detail, /<iframe[\s\S]*sandbox=""/);
  assert.match(detail, /roleCode\s*===\s*"admin"[\s\S]*item\.report_status/);
  assert.match(detail, /error\.status\s*===\s*409/);
});

test("withdrawal review navigation and cache isolation cover both authorized monitoring roles", async () => {
  const [layout, privateCache, authProvider] = await Promise.all([
    source("src/layouts/dashboard-layout.tsx"),
    source("src/lib/private-query-cache.ts"),
    source("src/components/auth-provider.tsx"),
  ]);

  assert.match(
    layout,
    /key:\s*"reportWithdrawals"[\s\S]*roles:\s*\["admin"\][\s\S]*permission:\s*"reports\.withdraw\.review\.own_campus"/,
  );
  assert.match(
    layout,
    /key:\s*"reportWithdrawals"[\s\S]*roles:\s*\["super_admin"\][\s\S]*permission:\s*"reports\.read\.all"/,
  );
  assert.match(privateCache, /queryKey\[0\]\s*===\s*"operations"/);
  assert.match(privateCache, /queryKey\[0\]\s*===\s*"dashboard"/);
  assert.match(privateCache, /queryKey\[0\]\s*===\s*"portal"/);
  assert.match(privateCache, /cancelQueries\(\{ predicate \}\)/);
  assert.match(privateCache, /removeQueries\(\{ predicate \}\)/);
  assert.match(authProvider, /await clearPrivateContentQueries\(queryClient\)/);
  assert.match(authProvider, /queryClient\.removeQueries\(\{ queryKey: \["portal"\] \}\)/);
});

test("Reporter rejected request uses backend capability to create a fresh resubmission", async () => {
  const [wizard, api] = await Promise.all([
    source("src/components/portal/formal-withdrawal-wizard.tsx"),
    source("src/lib/portal-api.ts"),
  ]);

  assert.match(wizard, /effectiveStatus\s*===\s*"rejected"/);
  assert.match(wizard, /effectiveCapabilities\.can_resubmit/);
  assert.match(wizard, /resubmitReason\.trim\(\)\.length\s*<\s*20/);
  assert.match(wizard, /resubmitReason\.trim\(\)\.length\s*>\s*2000/);
  assert.match(api, /\/portal\/withdrawals\/\$\{encodeURIComponent\(publicId\)\}\/resubmit/);
  assert.match(api, /reason,\s*lock_version:\s*lockVersion/);
});

test("formal withdrawal API uses authenticated text, upload, submit, cancel, and download routes", async () => {
  const [api, client] = await Promise.all([
    source("src/lib/portal-api.ts"),
    source("src/lib/api-client.ts"),
  ]);

  assert.match(api, /\/portal\/reports\/\$\{encodeURIComponent\(registrationNumber\)\}\/withdrawal/);
  assert.match(api, /\/portal\/reports\/\$\{encodeURIComponent\(registrationNumber\)\}\/withdrawals/);
  assert.match(api, /\/portal\/withdrawals\/\$\{encodeURIComponent\(publicId\)\}\/draft-document/);
  assert.match(api, /draft-document\/download/);
  assert.match(api, /draft-document\/example/);
  assert.match(api, /apiFetchText\(/);
  assert.match(api, /apiFetchBlob\(/);
  assert.match(api, /apiDownload\(/);
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
  assert.match(wizard, /downloadPortalWithdrawalDraftDocument/);
  assert.match(wizard, /getPortalWithdrawalDraftDocumentExample/);
  assert.match(wizard, /window\.open\("", "_blank"\)/);
  assert.match(wizard, /URL\.createObjectURL\(blob\)/);
  assert.match(wizard, /URL\.revokeObjectURL/);
  assert.doesNotMatch(wizard, /printWindow\.print\(\)/);
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
  const [wizard, directCancellation, reviewDetail] = await Promise.all([
    source("src/components/portal/formal-withdrawal-wizard.tsx"),
    source("src/components/portal/cancel-complaint-dialog.tsx"),
    source("src/routes/dashboard.report-withdrawals.$publicId.tsx"),
  ]);

  for (const expected of [
    "portalQueryKeys.report(registrationNumber)",
    "portalQueryKeys.reportsRoot()",
    "portalQueryKeys.summary()",
    "portalQueryKeys.reportWithdrawal(registrationNumber)",
    "portalQueryKeys.reportTimeline(registrationNumber)",
    "portalQueryKeys.reportHandlingProgress(registrationNumber)",
    "portalQueryKeys.reportEvidenceFiles(registrationNumber)",
    'queryKey: ["dashboard"]',
    'queryKey: ["operations"]',
  ]) {
    assert.ok(wizard.includes(expected), `Missing cache invalidation: ${expected}`);
  }

  assert.match(directCancellation, /queryKey: \["dashboard"\]/);
  assert.match(directCancellation, /queryKey: \["operations"\]/);
  assert.match(reviewDetail, /queryKey: \["operations"\]/);
  assert.match(reviewDetail, /queryKey: \["dashboard"\]/);
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
  assert.match(
    detail,
    /canUseSatgasActions\s*=\s*[\s\S]*?isAssignedSatgas\s*&&\s*!operationallyPaused\s*&&\s*!isOperationallyTerminalCase/,
  );
  assert.match(detail, /\(c\.assignments \?\? \[\]\)\.map/);
  assert.doesNotMatch(detail, /canManageAssignments && !c\.closed_at/);
});

test("approved withdrawal is read-only while owner document history remains available", async () => {
  const [wizard, types] = await Promise.all([
    source("src/components/portal/formal-withdrawal-wizard.tsx"),
    source("src/lib/portal-types.ts"),
  ]);

  assert.match(wizard, /effectiveAttachments\.map\(\(attachment\)/);
  assert.match(wizard, /withdrawal\.documentHistory/);
  assert.match(wizard, /effectiveCapabilities\.can_cancel_request/);
  assert.match(wizard, /effectiveCapabilities\.can_resubmit/);
  assert.match(wizard, /effectiveCapabilities\.can_upload_document/);
  assert.match(wizard, /effectiveCapabilities\.can_submit/);
  assert.match(types, /attachments:\s*WithdrawalAttachment\[\]/);
});

test("withdrawal queue separates initial loading, filtered empty, unfiltered empty, and retry states", async () => {
  const queue = await source("src/routes/dashboard.report-withdrawals.tsx");

  assert.match(queue, /withdrawalsQuery\.isPending\s*\?/);
  assert.match(queue, /withdrawalsQuery\.isError\s*\?/);
  assert.match(queue, /filteredEmptyDescription/);
  assert.match(queue, /withdrawals\.emptyDescription/);
  assert.match(queue, /<AccessDenied/);
  assert.match(queue, /onRetry=\{\(\) => withdrawalsQuery\.refetch\(\)\}/);
  assert.doesNotMatch(queue, /placeholderData|keepPreviousData/);
});

test("Admin report surfaces pending withdrawal and links queue detail without exposing a new mutation", async () => {
  const [list, detail] = await Promise.all([
    source("src/routes/dashboard.reports.index.tsx"),
    source("src/routes/dashboard.reports.$id.tsx"),
  ]);

  assert.match(list, /withdrawal_workflow\?\.status\s*===\s*"pending_review"/);
  assert.match(list, /withdrawals\.pendingBadge/);
  assert.match(detail, /to="\/dashboard\/report-withdrawals\/\$publicId"/);
  assert.doesNotMatch(detail, /approveReportWithdrawal|rejectReportWithdrawal/);
});

test("restricted role UX stays generic for Satgas and metadata-only for Super Admin", async () => {
  const [caseDetail, reviewDetail] = await Promise.all([
    source("src/routes/dashboard.cases.$id.tsx"),
    source("src/routes/dashboard.report-withdrawals.$publicId.tsx"),
  ]);

  assert.match(caseDetail, /withdrawals\.satgasPendingBanner/);
  assert.match(caseDetail, /withdrawals\.satgasWithdrawnBanner/);
  assert.match(caseDetail, /operationallyPaused\s*=\s*workflowContext\?\.facts\.operationally_paused\s*!==\s*false/);
  assert.match(caseDetail, /canUseSatgasActions\s*=\s*[\s\S]*?isAssignedSatgas\s*&&\s*!operationallyPaused\s*&&\s*!isOperationallyTerminalCase/);
  assert.match(caseDetail, /canUpdateEvidence\s*=\s*[\s\S]*?canUseSatgasActions/);
  assert.match(caseDetail, /privacy\.request_break_glass[\s\S]*?!operationallyPaused[\s\S]*?!isOperationallyTerminalCase/);
  assert.doesNotMatch(caseDetail, /withdrawal_reference|rejection_reason|signed-document/);
  assert.match(reviewDetail, /roleCode\s*===\s*"super_admin"/);
  assert.match(reviewDetail, /withdrawals\.monitoringOnly/);
  assert.match(reviewDetail, /roleCode\s*===\s*"admin"\s*&&\s*item\.reason/);
  assert.match(reviewDetail, /roleCode\s*===\s*"admin"\s*&&\s*item\.capabilities\.can_review/);
});

test("withdrawal preview has abort, object URL cleanup, and an accessible fallback", async () => {
  const detail = await source("src/routes/dashboard.report-withdrawals.$publicId.tsx");

  assert.match(detail, /new AbortController\(\)/);
  assert.match(detail, /previewRequestRef\.current\?\.abort\(\)/);
  assert.match(detail, /URL\.revokeObjectURL/);
  assert.match(detail, /WITHDRAWAL_PREVIEW_MIME_TYPES\.has\(response\.contentType\)/);
  assert.match(detail, /response\.blob\.size\s*===\s*0/);
  assert.ok(
    detail.indexOf("WITHDRAWAL_PREVIEW_MIME_TYPES.has(response.contentType)") <
      detail.indexOf("URL.createObjectURL(response.blob)"),
    "MIME validation must run before creating the object URL",
  );
  assert.match(detail, /catch \(error\) \{[\s\S]*?setPreviewFailed\(true\)/);
  assert.match(detail, /onError=\{\(\) => \{/);
  assert.match(detail, /setPreviewFailed\(true\)/);
  assert.match(detail, /role="alert"/);
  assert.match(detail, /dashboard:common\.retry/);
});

test("withdrawal dialogs and cards retain accessibility, responsive, and theme contracts", async () => {
  const [wizard, queue, detail] = await Promise.all([
    source("src/components/portal/formal-withdrawal-wizard.tsx"),
    source("src/routes/dashboard.report-withdrawals.tsx"),
    source("src/routes/dashboard.report-withdrawals.$publicId.tsx"),
  ]);

  assert.match(wizard, /max-h-\[92vh\] overflow-y-auto/);
  assert.match(wizard, /aria-current=\{item === step \? "step"/);
  assert.match(wizard, /aria-describedby=/);
  assert.match(wizard, /sm:flex-row/);
  assert.match(queue, /sm:grid-cols-/);
  assert.match(detail, /lg:grid-cols-2/);
  assert.match(detail, /break-all font-mono/);
  assert.match(wizard, /iframe[\s\S]*bg-white/);
  assert.doesNotMatch(wizard, /\b(?:bg-black|text-black)\b/);
  for (const text of [queue, detail]) {
    assert.doesNotMatch(text, /\b(?:bg-white|bg-black|text-black)\b/);
  }
});

test("withdrawal status copy separates approved requests from withdrawn complaints", async () => {
  const [portalId, portalEn, dashboardId, dashboardEn] = await Promise.all([
    source("src/locales/id/portal.json").then(JSON.parse),
    source("src/locales/en/portal.json").then(JSON.parse),
    source("src/locales/id/dashboard.json").then(JSON.parse),
    source("src/locales/en/dashboard.json").then(JSON.parse),
  ]);

  assert.equal(portalId.withdrawal.status.approved, "Pencabutan Disetujui");
  assert.equal(portalEn.withdrawal.status.approved, "Withdrawal Approved");
  assert.equal(dashboardId.withdrawals.status.approved, "Pencabutan Disetujui");
  assert.equal(dashboardEn.withdrawals.status.approved, "Withdrawal Approved");
  assert.doesNotMatch(JSON.stringify(portalId.withdrawal), /Laporan/i);
  assert.doesNotMatch(JSON.stringify(dashboardId.withdrawals), /Laporan/i);
});

test("Reporter portal locale keys remain in Indonesian and English parity", async () => {
  const [indonesian, english] = await Promise.all([
    source("src/locales/id/portal.json").then(JSON.parse),
    source("src/locales/en/portal.json").then(JSON.parse),
  ]);

  assert.deepEqual(keyPaths(indonesian).sort(), keyPaths(english).sort());
});
