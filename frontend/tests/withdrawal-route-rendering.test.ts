import assert from "node:assert/strict";
import path from "node:path";
import test from "node:test";
import { fileURLToPath } from "node:url";
import React from "react";
import { renderToString } from "react-dom/server";
import {
  createMemoryHistory,
  createRootRoute,
  createRoute,
  createRouter,
  Outlet,
  RouterProvider,
} from "@tanstack/react-router";
import react from "@vitejs/plugin-react";
import { createServer, type Plugin, type ViteDevServer } from "vite";

type TestState = {
  auth: {
    roleCode: "admin" | "super_admin";
    user: { permissions: string[] };
  };
  listCalls: number;
  detailCalls: number;
  listData: unknown;
  detailData: unknown;
};

type RouteModule = {
  Route: {
    options: {
      component: React.ComponentType;
      validateSearch?: (search: Record<string, unknown>) => Record<string, unknown>;
    };
  };
};

const testGlobal = globalThis as typeof globalThis & {
  __withdrawalRouteTestState: TestState;
};

const queueItem = {
  withdrawal_reference: "withdrawal-001",
  registration_number: "QUEUE-001",
  status: "pending_review",
  reporter_display_name: "Reporter Test",
  campus: { name: "Campus Test" },
  submitted_at: "2026-07-27T00:00:00Z",
  elapsed_waiting_seconds: 60,
};

const detailItem = {
  withdrawal_reference: "withdrawal-001",
  registration_number: "DETAIL-001",
  status: "pending_review",
  campus: { name: "Campus Test" },
  submitted_at: "2026-07-27T00:00:00Z",
  reviewed_at: null,
  report_status: "submitted",
  case_status: "open",
  reason: "SECRET-REPORTER-REASON",
  rejection_reason: null,
  lock_version: 1,
  attachments: [],
  capabilities: {
    can_view_signed_document: false,
    can_review: true,
    can_approve: true,
    can_reject: true,
  },
};

const virtualModules = new Map<string, string>([
  [
    "@/hooks/use-auth",
    `export const useAuth = () => globalThis.__withdrawalRouteTestState.auth;`,
  ],
  [
    "@tanstack/react-query",
    `
      export function useQuery(options) {
        if (options.enabled === false) {
          return {
            data: undefined,
            isPending: false,
            isLoading: false,
            isFetching: false,
            isError: false,
            isSuccess: false,
            refetch: async () => undefined,
          };
        }
        const data = options.queryFn();
        return {
          data,
          isPending: false,
          isLoading: false,
          isFetching: false,
          isError: false,
          isSuccess: true,
          refetch: async () => data,
        };
      }
      export const useQueryClient = () => ({
        invalidateQueries: async () => undefined,
      });
      export const useMutation = () => ({
        isPending: false,
        mutate: () => undefined,
      });
    `,
  ],
  [
    "@/lib/operations-api",
    `
      export const operationsQueryKeys = {
        withdrawalReviews: (query) => ["operations", "withdrawal-reviews", query],
        withdrawalReview: (publicId) => ["operations", "withdrawal-review", publicId],
      };
      export const getReportWithdrawalReviews = () => {
        globalThis.__withdrawalRouteTestState.listCalls += 1;
        return globalThis.__withdrawalRouteTestState.listData;
      };
      export const getReportWithdrawalReview = () => {
        globalThis.__withdrawalRouteTestState.detailCalls += 1;
        return globalThis.__withdrawalRouteTestState.detailData;
      };
      export const approveReportWithdrawal = async () => undefined;
      export const rejectReportWithdrawal = async () => undefined;
      export const previewReportWithdrawalDocument = async () => ({
        blob: new Blob(),
        contentType: "application/pdf",
      });
    `,
  ],
  [
    "react-i18next",
    `
      export const useTranslation = () => ({
        t: (key) => key,
        i18n: { language: "id" },
      });
      export const initReactI18next = {
        type: "3rdParty",
        init: () => undefined,
      };
    `,
  ],
  [
    "sonner",
    `export const toast = { success: () => undefined, error: () => undefined };`,
  ],
  [
    "@/lib/api-client",
    `
      export class ApiError extends Error {
        constructor(message, status) {
          super(message);
          this.status = status;
        }
      }
    `,
  ],
]);

function virtualModulePlugin(): Plugin {
  const prefix = "withdrawal-test:";

  return {
    name: "withdrawal-route-test-mocks",
    enforce: "pre",
    resolveId(id) {
      if (virtualModules.has(id)) {
        return `\0${prefix}${id}`;
      }

      return id.startsWith(prefix) ? `\0${id}` : undefined;
    },
    load(id) {
      const resolvedPrefix = `\0${prefix}`;
      return id.startsWith(resolvedPrefix)
        ? virtualModules.get(id.slice(resolvedPrefix.length))
        : undefined;
    },
  };
}

function resetState(roleCode: "admin" | "super_admin" = "admin") {
  testGlobal.__withdrawalRouteTestState = {
    auth: {
      roleCode,
      user: {
        permissions:
          roleCode === "admin"
            ? ["reports.withdraw.review.own_campus"]
            : ["reports.read.all"],
      },
    },
    listCalls: 0,
    detailCalls: 0,
    listData: {
      data: [queueItem],
      meta: { current_page: 1, last_page: 1, per_page: 15, total: 1 },
    },
    detailData: detailItem,
  };
}

async function createTestRouter(vite: ViteDevServer, initialEntry: string) {
  const [parentModule, childModule] = await Promise.all([
    vite.ssrLoadModule("/src/routes/dashboard.report-withdrawals.tsx") as Promise<RouteModule>,
    vite.ssrLoadModule(
      "/src/routes/dashboard.report-withdrawals.$publicId.tsx",
    ) as Promise<RouteModule>,
  ]);

  const rootRoute = createRootRoute({
    component: () => React.createElement(Outlet),
  });
  const dashboardRoute = createRoute({
    getParentRoute: () => rootRoute,
    path: "dashboard",
    component: () => React.createElement(Outlet),
  });
  const parentRoute = createRoute({
    getParentRoute: () => dashboardRoute,
    path: "report-withdrawals",
    validateSearch: parentModule.Route.options.validateSearch,
    component: parentModule.Route.options.component,
  });
  const childRoute = createRoute({
    getParentRoute: () => parentRoute,
    path: "$publicId",
    component: childModule.Route.options.component,
  });
  const routeTree = rootRoute.addChildren([
    dashboardRoute.addChildren([parentRoute.addChildren([childRoute])]),
  ]);
  const history = createMemoryHistory({ initialEntries: [initialEntry] });
  const router = createRouter({ routeTree, history });

  await router.load();

  return { router, history };
}

function renderRouter(router: Awaited<ReturnType<typeof createTestRouter>>["router"]) {
  return renderToString(React.createElement(RouterProvider, { router }));
}

test("withdrawal parent mounts index or child and isolates their queries at runtime", async (t) => {
  const frontendRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), "..");
  const vite = await createServer({
    root: frontendRoot,
    configFile: false,
    appType: "custom",
    server: { middlewareMode: true },
    resolve: {
      alias: [
        ...Array.from(virtualModules.keys(), (id) => ({
          find: id,
          replacement: `withdrawal-test:${id}`,
        })),
        { find: "@", replacement: path.join(frontendRoot, "src") },
      ],
    },
    plugins: [virtualModulePlugin(), react()],
  });
  t.after(() => vite.close());

  resetState();
  const index = await createTestRouter(
    vite,
    "/dashboard/report-withdrawals?per_page=15",
  );
  const indexHtml = renderRouter(index.router);

  assert.match(indexHtml, /QUEUE-001/);
  assert.doesNotMatch(indexHtml, /DETAIL-001/);
  assert.equal(testGlobal.__withdrawalRouteTestState.listCalls, 1);
  assert.equal(testGlobal.__withdrawalRouteTestState.detailCalls, 0);

  const detailHref = indexHtml.match(
    /href="([^"]*\/dashboard\/report-withdrawals\/withdrawal-001[^"]*)"/,
  )?.[1];
  assert.ok(detailHref, "Detail link must be rendered by the queue");

  resetState();
  await index.router.navigate({ href: detailHref });
  const clickedDetailHtml = renderRouter(index.router);

  assert.match(clickedDetailHtml, /DETAIL-001/);
  assert.doesNotMatch(clickedDetailHtml, /QUEUE-001/);
  assert.equal(testGlobal.__withdrawalRouteTestState.listCalls, 0);
  assert.equal(testGlobal.__withdrawalRouteTestState.detailCalls, 1);

  const backHref = clickedDetailHtml.match(
    /href="([^"]*\/dashboard\/report-withdrawals(?:\?[^"]*)?)"/,
  )?.[1];
  assert.ok(backHref, "Detail page must render a back link");
  assert.match(backHref, /per_page=15/);

  resetState();
  index.history.back();
  await index.router.load();
  const returnedIndexHtml = renderRouter(index.router);

  assert.match(returnedIndexHtml, /QUEUE-001/);
  assert.equal(testGlobal.__withdrawalRouteTestState.listCalls, 1);
  assert.equal(testGlobal.__withdrawalRouteTestState.detailCalls, 0);

  resetState();
  const direct = await createTestRouter(
    vite,
    "/dashboard/report-withdrawals/withdrawal-001?per_page=15",
  );
  const directHtml = renderRouter(direct.router);

  assert.match(directHtml, /DETAIL-001/);
  assert.doesNotMatch(directHtml, /QUEUE-001/);
  assert.equal(testGlobal.__withdrawalRouteTestState.listCalls, 0);
  assert.equal(testGlobal.__withdrawalRouteTestState.detailCalls, 1);

  resetState("super_admin");
  const monitoring = await createTestRouter(
    vite,
    "/dashboard/report-withdrawals/withdrawal-001?per_page=15",
  );
  const monitoringHtml = renderRouter(monitoring.router);

  assert.match(monitoringHtml, /dashboard:withdrawals\.monitoringOnly/);
  assert.doesNotMatch(monitoringHtml, /SECRET-REPORTER-REASON/);
  assert.equal(testGlobal.__withdrawalRouteTestState.listCalls, 0);
  assert.equal(testGlobal.__withdrawalRouteTestState.detailCalls, 1);
});
