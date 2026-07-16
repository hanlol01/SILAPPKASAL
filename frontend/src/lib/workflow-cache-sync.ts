import type { QueryClient, QueryKey } from "@tanstack/react-query";
import { operationsQueryKeys } from "@/lib/operations-api";

type WorkflowCacheSyncOptions = {
  caseId?: string | number | null;
  reportId?: string | number | null;
  exactKeys?: QueryKey[];
  includeReports?: boolean;
};

const DASHBOARD_ROOT = ["dashboard"] as const;
const MY_WORK_ROOT = ["my-work"] as const;

export async function synchronizeWorkflowCaches(
  queryClient: QueryClient,
  {
    caseId,
    reportId,
    exactKeys = [],
    includeReports = false,
  }: WorkflowCacheSyncOptions,
) {
  const detailKeys = uniqueQueryKeys([
    ...(caseId === null || caseId === undefined ? [] : [operationsQueryKeys.case(caseId)]),
    ...(reportId === null || reportId === undefined ? [] : [operationsQueryKeys.report(reportId)]),
    ...exactKeys,
  ]);

  await Promise.all(
    detailKeys.map(async (queryKey) => {
      await queryClient.invalidateQueries({ queryKey, exact: true, refetchType: "none" });
      await queryClient.refetchQueries(
        { queryKey, exact: true, type: "active" },
        { throwOnError: true },
      );
    }),
  );

  const listKeys: QueryKey[] = [
    operationsQueryKeys.casesRoot(),
    DASHBOARD_ROOT,
    MY_WORK_ROOT,
  ];

  if (includeReports || (reportId !== null && reportId !== undefined)) {
    listKeys.push(operationsQueryKeys.reportsRoot());
  }

  for (const queryKey of listKeys) {
    void queryClient
      .invalidateQueries({ queryKey, refetchType: "active" })
      .catch(() => undefined);
  }
}

function uniqueQueryKeys(keys: QueryKey[]) {
  const seen = new Set<string>();

  return keys.filter((key) => {
    const serialized = JSON.stringify(key);
    if (seen.has(serialized)) return false;
    seen.add(serialized);
    return true;
  });
}
