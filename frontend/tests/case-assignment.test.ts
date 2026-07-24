import assert from "node:assert/strict";
import { readFile } from "node:fs/promises";
import test from "node:test";

const source = (path: string) => readFile(new URL(`../${path}`, import.meta.url), "utf8");

function stringValues(value: unknown): string[] {
  if (typeof value === "string") return [value];
  if (Array.isArray(value)) return value.flatMap(stringValues);
  if (value && typeof value === "object") return Object.values(value).flatMap(stringValues);
  return [];
}

function keyPaths(value: unknown, prefix = ""): string[] {
  if (!value || typeof value !== "object" || Array.isArray(value)) return [prefix];

  return Object.entries(value).flatMap(([key, child]) =>
    keyPaths(child, prefix ? `${prefix}.${key}` : key),
  );
}

test("Admin assignment follows authoritative capability and sends the assignment lock token", async () => {
  const [detail, action] = await Promise.all([
    source("src/routes/dashboard.cases.$id.tsx"),
    source("src/components/workflow-actions/satgas-assignment-action.tsx"),
  ]);

  assert.match(detail, /c\.assignment_capabilities\?\.manage\.allowed === true/);
  assert.match(detail, /lockVersion=\{c\.lock_version\}/);
  assert.match(action, /lock_version:\s*mode === "assign-case" \? lockVersion : undefined/);
  assert.match(
    action,
    /onError:\s*\(error\)\s*=>\s*\{[\s\S]*?synchronizeWorkflowCaches\(queryClient,\s*\{[\s\S]*?caseId:\s*mode === "assign-case" \? targetId : undefined/,
  );
  assert.doesNotMatch(action, /lead_satgas_id|currentLeadSatgasId|selectLead/);
});

test("Satgas self-assignment is capability driven and client payload cannot carry an assignee id", async () => {
  const [list, api] = await Promise.all([
    source("src/routes/dashboard.cases.index.tsx"),
    source("src/lib/operations-api.ts"),
  ]);

  assert.match(list, /item\.assignment_capabilities\?\.self_assign\.allowed/);
  assert.match(list, /roleCode === "satgas_ppks"/);
  assert.match(list, /assignment_status:\s*value === "unassigned"/);
  assert.match(api, /`\/cases\/\$\{id\}\/self-assign`/);
  assert.match(api, /JSON\.stringify\(\{\s*lock_version:\s*lockVersion\s*\}\)/);
  assert.doesNotMatch(
    api.match(/export function selfAssignCase[\s\S]*?\n\}/)?.[0] ?? "",
    /satgas_id|assignee_id|user_id/,
  );
});

test("self-assignment refreshes workflow caches and conflicts refresh the available queue", async () => {
  const list = await source("src/routes/dashboard.cases.index.tsx");

  assert.match(list, /queryKey:\s*operationsQueryKeys\.cases\(query\)/);
  assert.doesNotMatch(list, /placeholderData\s*:/);
  assert.match(list, /synchronizeWorkflowCaches\(queryClient,\s*\{\s*caseId:\s*item\.id\s*\}\)/);
  assert.match(
    list,
    /onError:\s*\(error,\s*item\)\s*=>\s*\{[\s\S]*?synchronizeWorkflowCaches\(queryClient,\s*\{\s*caseId:\s*item\.id\s*\}\)/,
  );
  assert.match(list, /apiErrorMessage\(error,\s*t\("dashboard:cases\.selfAssignError"\)\)/);
});

test("read-only and paused cases rely on fail-closed server capabilities", async () => {
  const detail = await source("src/routes/dashboard.cases.$id.tsx");

  assert.match(detail, /c\.assignment_capabilities\?\.manage\.allowed === true/);
  assert.doesNotMatch(
    detail.match(/const canManageAssignments[\s\S]*?;/)?.[0] ?? "",
    /status|withdrawal|operationallyPaused/,
  );
});

test("report detail only offers forwarding before a Case exists", async () => {
  const reportDetail = await source("src/routes/dashboard.reports.$id.tsx");

  assert.match(reportDetail, /reportCase === null/);
  assert.match(reportDetail, /mode="forward-report"/);
  assert.doesNotMatch(reportDetail, /mode=\{assignmentMode\}|cases\.assign_satgas/);
});

test("active UI no longer exposes Ketua Satgas or lead-assignment controls", async () => {
  const [indonesian, english, action, caseDetail, reportDetail] = await Promise.all([
    source("src/locales/id/dashboard.json").then(JSON.parse),
    source("src/locales/en/dashboard.json").then(JSON.parse),
    source("src/components/workflow-actions/satgas-assignment-action.tsx"),
    source("src/routes/dashboard.cases.$id.tsx"),
    source("src/routes/dashboard.reports.$id.tsx"),
  ]);

  assert.equal(stringValues(indonesian).some((value) => /Ketua Satgas/i.test(value)), false);
  assert.equal(stringValues(english).some((value) => /Lead Satgas/i.test(value)), false);
  assert.doesNotMatch(`${action}\n${caseDetail}\n${reportDetail}`, /leadSatgas|currentLead|is_lead/);
  assert.deepEqual(keyPaths(indonesian).sort(), keyPaths(english).sort());
});
