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

test("Decision create and update forms cannot author a formal decision number", async () => {
  const [createAction, updateActions, types] = await Promise.all([
    source("src/components/workflow-actions/decision-create-action.tsx"),
    source("src/components/workflow-actions/workflow-action-dialogs.tsx"),
    source("src/lib/operations-types.ts"),
  ]);

  assert.doesNotMatch(createAction, /decision_number|decisionNumber/);
  const updateSection =
    updateActions.match(/export function DecisionUpdateAction[\s\S]*?export function RecoveryMonitoringAction/)?.[0]
    ?? "";
  assert.doesNotMatch(updateSection, /decision_number|decisionNumber/);

  const createPayload =
    types.match(/export interface DecisionCreatePayload[\s\S]*?\n\}/)?.[0] ?? "";
  const updatePayload =
    types.match(/export interface DecisionUpdatePayload[\s\S]*?\n\}/)?.[0] ?? "";
  const decisionResponse =
    types.match(/export interface Decision \{[\s\S]*?\n\}/)?.[0] ?? "";
  assert.doesNotMatch(createPayload, /decision_number/);
  assert.doesNotMatch(updatePayload, /decision_number/);
  assert.match(decisionResponse, /decision_number\?: string \| null/);
});

test("finalization sends status only, warns about issuance, and prevents double submit", async () => {
  const [action, api] = await Promise.all([
    source("src/components/workflow-actions/decision-status-action.tsx"),
    source("src/lib/operations-api.ts"),
  ]);

  assert.match(action, /updateDecisionStatus\(decision\.id,\s*values\)/);
  assert.match(action, /option\.name === "finalized"/);
  assert.match(action, /decisionFinalizeConfirmation/);
  assert.match(action, /finalizeDecision/);
  assert.match(action, /if \(!mutation\.isPending\) mutation\.mutate\(values\)/);
  assert.match(action, /disabled=\{mutation\.isPending \|\| options\.length === 0\}/);
  assert.doesNotMatch(action, /decision_number|decision_code|formal_decision_code/);

  const statusMutation =
    api.match(/export function updateDecisionStatus[\s\S]*?\n\}/)?.[0] ?? "";
  assert.match(statusMutation, /body:\s*JSON\.stringify\(payload\)/);
  assert.doesNotMatch(statusMutation, /decision_number|decision_code|formal_decision_code/);
});

test("Decision transition CTA is role, permission, campus, pause, and backend-option driven", async () => {
  const [detail, action] = await Promise.all([
    source("src/routes/dashboard.cases.$id.tsx"),
    source("src/components/workflow-actions/decision-status-action.tsx"),
  ]);

  assert.match(
    detail,
    /roleCode === "admin"[\s\S]*cases\.record_decision[\s\S]*same_campus_admin[\s\S]*!operationallyPaused[\s\S]*!isOperationallyTerminalCase/,
  );
  assert.match(detail, /canTransitionStatus=\{canManageDecisionActions\}/);
  assert.match(detail, /canTransitionStatus && item\.status !== "finalized"/);
  assert.match(action, /getDecisionStatusOptions\(decision\.id\)/);
  assert.match(action, /valid_transitions/);
  assert.match(action, /options\.length === 0/);
});

test("success and conflict paths synchronize all formal-decision workflow caches", async () => {
  const action = await source("src/components/workflow-actions/decision-status-action.tsx");

  for (const expected of [
    "operationsQueryKeys.decisions(decision.recommendation_id)",
    "operationsQueryKeys.recommendations(caseId)",
    "operationsQueryKeys.recommendation(decision.recommendation_id)",
    "operationsQueryKeys.recommendationStatusOptions(decision.recommendation_id)",
    "operationsQueryKeys.decision(decision.id)",
    "operationsQueryKeys.decisionStatusOptions(decision.id)",
  ]) {
    assert.ok(action.includes(expected), `Missing cache synchronization: ${expected}`);
  }

  assert.match(
    action,
    /onError:\s*\(error\)\s*=>\s*\{[\s\S]*?synchronizeWorkflowCaches\(queryClient/,
  );
  assert.match(action, /\.catch\(\(\) => undefined\)/);
});

test("issued, legacy, and null decision numbers render without client generation", async () => {
  const detail = await source("src/routes/dashboard.cases.$id.tsx");

  assert.match(
    detail,
    /item\.decision_number\?\.trim\(\) \|\| t\("dashboard:sections\.decisionNumberPending"\)/,
  );
  assert.doesNotMatch(detail, /SK\/PPKS\/|padStart|Math\.random|randomUUID/);
});

test("Reporter contracts do not project the internal formal decision number", async () => {
  const [portalApi, portalTypes] = await Promise.all([
    source("src/lib/portal-api.ts"),
    source("src/lib/portal-types.ts"),
  ]);

  assert.doesNotMatch(`${portalApi}\n${portalTypes}`, /decision_number|formal_decision_code/);
});

test("formal decision copy has Indonesian and English locale parity", async () => {
  const [indonesian, english] = await Promise.all([
    source("src/locales/id/dashboard.json").then(JSON.parse),
    source("src/locales/en/dashboard.json").then(JSON.parse),
  ]);

  assert.equal(
    indonesian.sections.decisionNumberPending,
    "Nomor keputusan belum diterbitkan",
  );
  assert.equal(english.sections.decisionNumberPending, "Decision number not issued");
  assert.match(indonesian.workflow.decisionFinalizeConfirmation, /diterbitkan/);
  assert.match(english.workflow.decisionFinalizeConfirmation, /issued/);
  assert.deepEqual(keyPaths(indonesian).sort(), keyPaths(english).sort());
});
