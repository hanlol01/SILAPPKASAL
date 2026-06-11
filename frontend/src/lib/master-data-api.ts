import { apiRequest } from "@/lib/api-client";
import type { MasterDataItem, MasterDataType } from "@/lib/api-types";

export const masterDataQueryKeys = {
  list: (type: MasterDataType, includeInactive = false) =>
    ["master-data", type, includeInactive] as const,
};

export function getMasterData(type: MasterDataType, includeInactive = false) {
  return apiRequest<MasterDataItem[]>(`/master/${type}`, {
    query: { include_inactive: includeInactive || undefined },
  });
}
