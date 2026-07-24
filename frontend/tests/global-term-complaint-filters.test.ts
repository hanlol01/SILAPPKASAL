import assert from "node:assert/strict";
import { readdir, readFile } from "node:fs/promises";
import test from "node:test";

const source = (path: string) => readFile(new URL(`../${path}`, import.meta.url), "utf8");

function stringValues(value: unknown): string[] {
  if (typeof value === "string") {
    return [value];
  }

  if (Array.isArray(value)) {
    return value.flatMap(stringValues);
  }

  if (value && typeof value === "object") {
    return Object.values(value).flatMap(stringValues);
  }

  return [];
}

function keyPaths(value: unknown, prefix = ""): string[] {
  if (!value || typeof value !== "object" || Array.isArray(value)) {
    return [prefix];
  }

  return Object.entries(value).flatMap(([key, child]) =>
    keyPaths(child, prefix ? `${prefix}.${key}` : key),
  );
}

test("locale copy uses complaint terminology while preserving investigation report terms", async () => {
  for (const language of ["id", "en"]) {
    const directory = new URL(`../src/locales/${language}/`, import.meta.url);
    const files = (await readdir(directory)).filter((name) => name.endsWith(".json"));

    for (const file of files) {
      const values = stringValues(JSON.parse(await readFile(new URL(file, directory), "utf8")));
      const stale = values.filter((value) => {
        if (language === "id") {
          return (
            /\b(?:laporan|pelaporan)\b/iu.test(value) &&
            !/\bLaporan Investigasi\b/u.test(value)
          );
        }

        return (
          /\b(?:reports?|reporting)\b/iu.test(value) &&
          !/\b(?:Investigation Report|Report drafting)\b/u.test(value)
        );
      });

      assert.deepEqual(stale, [], `${language}/${file} contains stale user-facing terminology`);
    }
  }
});

test("dashboard locale keys remain in Indonesian and English parity", async () => {
  const [indonesian, english] = await Promise.all([
    source("src/locales/id/dashboard.json").then(JSON.parse),
    source("src/locales/en/dashboard.json").then(JSON.parse),
  ]);

  assert.deepEqual(keyPaths(indonesian).sort(), keyPaths(english).sort());
});

test("operational filters are role-scoped and query the existing authoritative lookups", async () => {
  const [filter, reports, cases] = await Promise.all([
    source("src/components/operational-scope-filter.tsx"),
    source("src/routes/dashboard.reports.index.tsx"),
    source("src/routes/dashboard.cases.index.tsx"),
  ]);

  assert.match(filter, /lookupUsers\("satgas_ppks"\)/);
  assert.match(filter, /enabled: roleCode === "admin"/);
  assert.match(filter, /queryFn: getUniversities/);
  assert.match(filter, /enabled: roleCode === "super_admin"/);
  assert.match(filter, /if \(roleCode === "admin"\)/);
  assert.match(filter, /if \(roleCode === "super_admin"\)/);
  const adminBranch = filter.slice(
    filter.indexOf('if (roleCode === "admin")'),
    filter.indexOf('if (roleCode === "super_admin")'),
  );
  const superAdminBranch = filter.slice(filter.indexOf('if (roleCode === "super_admin")'));
  assert.match(adminBranch, /includeUnassigned/);
  assert.match(adminBranch, /value="unassigned"/);
  assert.doesNotMatch(superAdminBranch, /unassigned/);
  assert.match(filter, /return null/);

  for (const route of [reports, cases]) {
    assert.match(
      route,
      /satgas_id:[\s\S]*roleCode === "admin" && satgasId !== "all" && satgasId !== "unassigned"/,
    );
    assert.match(route, /<OperationalScopeFilter/);
    assert.match(route, /includeUnassigned/);
    assert.match(
      route,
      /satgas_id:[\s\S]*value !== "all" && value !== "unassigned" \? Number\(value\) : undefined/,
    );
    assert.match(route, /assignment_status: value === "unassigned" \? "unassigned" : undefined/);
    assert.match(route, /navigate\(\{ search: \{\}, replace: true \}\)/);
  }

  assert.match(
    reports,
    /assignment_status:\s*roleCode === "admin" && satgasId === "unassigned"/,
  );
  assert.match(
    cases,
    /assignment_status:\s*\(roleCode === "admin" \|\| roleCode === "satgas_ppks"\) && satgasId === "unassigned"/,
  );

  assert.match(
    reports,
    /roleCode === "super_admin" && universityId !== "all" \? Number\(universityId\) : undefined/,
  );
  assert.match(reports, /university_id: value !== "all" \? Number\(value\) : undefined/);
  assert.doesNotMatch(cases, /university_id:/);
  assert.match(cases, /roleCode=\{roleCode === "admin" \? roleCode : null\}/);
});

test("overview filters are URL-synced, role-exclusive, resettable, and applied server-side", async () => {
  const [overview, apiTypes, dashboardApi, filter] = await Promise.all([
    source("src/routes/dashboard.index.tsx"),
    source("src/lib/api-types.ts"),
    source("src/lib/dashboard-api.ts"),
    source("src/components/operational-scope-filter.tsx"),
  ]);

  assert.match(overview, /validateSearch:/);
  assert.match(overview, /satgas_id: positiveInteger\(search\.satgas_id\)/);
  assert.match(overview, /assignment_status: search\.assignment_status === "unassigned"/);
  assert.match(overview, /university_id: positiveInteger\(search\.university_id\)/);
  assert.match(overview, /if \(roleCode === "admin"\)/);
  assert.match(overview, /if \(roleCode === "super_admin"\)/);
  assert.match(overview, /getDashboardSummary\(filters\)/);
  assert.match(overview, /getDashboardReports\(filters\)/);
  assert.match(overview, /placeholderData: keepPreviousData/);
  assert.match(overview, /includeUnassigned/);
  assert.match(overview, /replace: true/);
  assert.match(overview, /navigate\(\{ search: \{\}, replace: true \}\)/);
  assert.match(filter, /value="unassigned"/);
  assert.match(apiTypes, /assignment_status\?: "unassigned"/);
  assert.match(dashboardApi, /query: asQuery\(filters\)/);
});

test("analytics propagates the same role-scoped filters to every dashboard query", async () => {
  const [analytics, dashboardApi] = await Promise.all([
    source("src/routes/dashboard.analytics.tsx"),
    source("src/lib/dashboard-api.ts"),
  ]);

  assert.match(analytics, /validateSearch:/);
  assert.match(analytics, /getDashboardSummary\(filters\)/);
  assert.match(analytics, /getDashboardReports\(filters\)/);
  assert.match(analytics, /getDashboardCases\(filters\)/);
  assert.match(analytics, /getDashboardEvidence\(filters\)/);
  assert.match(analytics, /dashboardQueryKeys\.summary\(filters\)/);
  assert.match(analytics, /dashboardQueryKeys\.reports\(filters\)/);
  assert.match(analytics, /dashboardQueryKeys\.cases\(filters\)/);
  assert.match(analytics, /dashboardQueryKeys\.evidence\(filters\)/);
  assert.match(analytics, /getDashboardWorkflow\(filters\)/);
  assert.match(analytics, /dashboardQueryKeys\.workflow\(filters\)/);
  assert.match(analytics, /workflowQuery\.isLoading/);
  assert.match(analytics, /workflowQuery\.isError/);
  assert.match(analytics, /workflowQuery\.isFetching/);
  assert.match(analytics, /workflowQuery\.refetch\(\)/);
  assert.match(analytics, /includeUnassigned/);
  assert.doesNotMatch(analytics, /keepPreviousData|placeholderData:/);
  assert.match(analytics, /const isScopeLoading =/);
  assert.match(analytics, /isFetching && !summaryQuery\.data/);
  assert.match(analytics, /if \(isScopeLoading\)/);
  assert.match(dashboardApi, /summary: \(filters\?: DashboardFilters\)/);
  assert.match(dashboardApi, /reports: \(filters\?: DashboardFilters\)/);
  assert.match(dashboardApi, /cases: \(filters\?: DashboardFilters\)/);
  assert.match(dashboardApi, /workflow: \(filters\?: DashboardFilters\)/);
  assert.match(dashboardApi, /evidence: \(filters\?: DashboardFilters\)/);
});

test("workflow uses the same URL-synchronized role filters and never renders stale scope data", async () => {
  const workflow = await source("src/routes/dashboard.workflow.tsx");

  assert.match(workflow, /validateSearch:/);
  assert.match(workflow, /Route\.useSearch\(\)/);
  assert.match(workflow, /if \(roleCode === "admin"\)/);
  assert.match(workflow, /if \(roleCode === "super_admin"\)/);
  assert.match(workflow, /dashboardQueryKeys\.workflow\(filters\)/);
  assert.match(workflow, /getDashboardWorkflow\(filters\)/);
  assert.match(workflow, /includeUnassigned/);
  assert.match(workflow, /assignment_status: value === "unassigned" \? "unassigned" : undefined/);
  assert.match(workflow, /navigate\(\{ search: \{\}, replace: true \}\)/);
  assert.match(workflow, /workflowQuery\.isFetching/);
  assert.doesNotMatch(workflow, /keepPreviousData|placeholderData:/);
  assert.match(workflow, /const isScopeLoading =/);
  assert.match(workflow, /isFetching && !workflowQuery\.data/);
  assert.match(workflow, /if \(isScopeLoading\)/);
  assert.match(workflow, /const workflow = workflowQuery\.data/);
});

test("reports and cases synchronize searchable filters and pagination with route search params", async () => {
  const [reports, cases] = await Promise.all([
    source("src/routes/dashboard.reports.index.tsx"),
    source("src/routes/dashboard.cases.index.tsx"),
  ]);

  for (const route of [reports, cases]) {
    assert.match(route, /validateSearch:/);
    assert.match(route, /assignment_status: search\.assignment_status === "unassigned"/);
    assert.match(route, /Route\.useSearch\(\)/);
    assert.match(route, /navigate\(\{[\s\S]*replace: true/);
    assert.match(route, /isFetching/);
    assert.match(route, /onPageChange=/);
    assert.match(route, /onPageSizeChange=/);
  }

  assert.match(reports, /q: typeof search\.q === "string"/);
  assert.match(reports, /per_page: normalizePageSize/);
  assert.match(cases, /q: typeof search\.q === "string"/);
  assert.match(cases, /satgas_id: positiveInteger/);
  assert.match(cases, /assignment_status: search\.assignment_status === "unassigned"/);
});

test("filtered empty states include scope filters and show refetch activity", async () => {
  const [reports, cases, analytics] = await Promise.all([
    source("src/routes/dashboard.reports.index.tsx"),
    source("src/routes/dashboard.cases.index.tsx"),
    source("src/routes/dashboard.analytics.tsx"),
  ]);

  assert.match(reports, /filtersActive \? \(/);
  assert.match(cases, /filtersActive \?/);
  assert.match(reports, /satgasId !== "all"/);
  assert.match(cases, /satgasId !== "all"/);
  assert.match(reports, /search\.assignment_status === "unassigned"/);
  assert.match(cases, /search\.assignment_status === "unassigned"/);
  assert.match(reports, /reportsQuery\.isFetching/);
  assert.match(cases, /casesQuery\.isFetching/);
  assert.match(analytics, /dashboard:analytics\.empty\.filteredTitle/);
  assert.match(analytics, /dashboard:analytics\.empty\.filteredDesc/);
});

test("report routes remain backward-compatible technical contracts", async () => {
  const [portal, dashboard, api] = await Promise.all([
    source("src/routes/portal.reports.index.tsx"),
    source("src/routes/dashboard.reports.index.tsx"),
    source("src/lib/operations-api.ts"),
  ]);

  assert.match(portal, /createFileRoute\("\/portal\/reports\/"\)/);
  assert.match(dashboard, /createFileRoute\("\/dashboard\/reports\/"\)/);
  assert.match(api, /apiRequestEnvelope<ReportSummary\[]>\("\/reports"/);
});

test("assignment card is explicitly placed in the top-right desktop grid cell", async () => {
  const detail = await source("src/routes/dashboard.reports.$id.tsx");

  assert.match(detail, /lg:col-start-3 lg:row-start-1/);
  assert.match(detail, /dashboard:cases\.assignments/);
});
