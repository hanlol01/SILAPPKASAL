import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const source = (path: string) => readFile(new URL(`../${path}`, import.meta.url), "utf8");

function keyPaths(value: unknown, prefix = ""): string[] {
  if (!value || typeof value !== "object" || Array.isArray(value)) return [prefix];

  return Object.entries(value).flatMap(([key, child]) =>
    keyPaths(child, prefix ? `${prefix}.${key}` : key),
  );
}

test("Case Minutes use server-owned public IDs and opaque lock tokens", async () => {
  const [api, types] = await Promise.all([
    source("src/lib/operations-api.ts"),
    source("src/lib/operations-types.ts"),
  ]);

  assert.match(api, /`\/cases\/\$\{caseId\}\/minutes`/);
  assert.match(api, /`\/case-minutes\/\$\{encodeURIComponent\(publicId\)\}`/);
  assert.match(api, /lock_version:\s*lockVersion/);
  assert.match(types, /export interface CaseMinuteInternal[\s\S]*lock_version: string/);
  assert.doesNotMatch(
    types.match(/export interface CaseMinuteDraftPayload[\s\S]*?\n\}/)?.[0] ?? "",
    /public_id|case_id|version|status|created_by|finalized_by/,
  );
});

test("Case Minute UI is capability driven and retains immutable history", async () => {
  const [component, detail] = await Promise.all([
    source("src/components/workflow-actions/case-minute.tsx"),
    source("src/routes/dashboard.cases.$id.tsx"),
  ]);

  assert.match(detail, /<CaseMinuteCard caseId=\{c\.id\} language=\{i18n\.language\} \/>/);
  assert.match(component, /minutesQuery\.data\?\.capabilities\.create === true/);
  assert.match(component, /draft\?\.capabilities\.update === true/);
  assert.match(component, /minute\.capabilities\.create_revision/);
  assert.match(component, /draft\?\.capabilities\.finalize/);
  assert.match(component, /caseMinuteFinalizeConfirmation/);
  assert.match(component, /createCaseMinuteRevision\(publicId\)/);
  assert.match(component, /finalizeCaseMinute\(minute\.public_id, minute\.lock_version\)/);
});

test("Super Admin metadata UI cannot render internal or anonymized narratives", async () => {
  const [component, types] = await Promise.all([
    source("src/components/workflow-actions/case-minute.tsx"),
    source("src/lib/operations-types.ts"),
  ]);

  const metadata = types.match(/export interface CaseMinuteMetadata[\s\S]*?\n\}/)?.[0] ?? "";
  assert.doesNotMatch(
    metadata,
    /internal_summary|anonymized_summary|outcome|follow_up|creator|updater|finalizer|capabilities|lock_version|supersedes/,
  );
  assert.match(component, /minute\.projection === "internal"/);
  assert.match(component, /caseMinuteMetadataOnly/);
});

test("Case Minute client keeps mutation authority and sensitive fields server-owned", async () => {
  const [api, component, types] = await Promise.all([
    source("src/lib/operations-api.ts"),
    source("src/components/workflow-actions/case-minute.tsx"),
    source("src/lib/operations-types.ts"),
  ]);

  assert.match(api, /createCaseMinute\(caseId: string \| number, payload: CaseMinuteDraftPayload\)[\s\S]*?method: "POST"/);
  assert.match(api, /updateCaseMinute\(publicId: string, payload: CaseMinuteUpdatePayload\)[\s\S]*?method: "PATCH"/);
  assert.match(api, /finalizeCaseMinute\(publicId: string, lockVersion: string\)[\s\S]*?lock_version: lockVersion/);
  assert.match(component, /synchronizeWorkflowCaches\(queryClient/);
  assert.match(component, /error instanceof ApiError && error\.status === 409/);
  assert.match(component, /void refresh\(\)/);
  assert.match(component, /draft\?\.capabilities\.finalize/);
  assert.match(component, /disabled=\{finalizeMutation\.isPending\}/);
  assert.match(component, /minute\.projection === "internal"/);
  assert.doesNotMatch(component, /upload|download|pdf|public URL|dangerouslySetInnerHTML/i);
  assert.doesNotMatch(
    types.match(/export interface CaseMinuteDraftPayload[\s\S]*?\n\}/)?.[0] ?? "",
    /creator|updater|finalizer|capabilities|lock_version|supersedes/,
  );
});

test("Case Minute copy has Indonesian and English locale parity", async () => {
  const [indonesian, english] = await Promise.all([
    source("src/locales/id/dashboard.json").then(JSON.parse),
    source("src/locales/en/dashboard.json").then(JSON.parse),
  ]);

  assert.match(indonesian.workflow.caseMinuteAnonymizationNotice, /identitas/i);
  assert.match(english.workflow.caseMinuteAnonymizationNotice, /identifiers/i);
  assert.deepEqual(keyPaths(indonesian).sort(), keyPaths(english).sort());
});
