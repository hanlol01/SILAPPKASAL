import type { QueryClient, QueryKey } from "@tanstack/react-query";

export function isPrivateContentQueryKey(queryKey: QueryKey): boolean {
  return queryKey[0] === "content-management";
}

export async function clearPrivateContentQueries(queryClient: QueryClient): Promise<void> {
  const predicate = (query: { queryKey: QueryKey }) => isPrivateContentQueryKey(query.queryKey);

  await queryClient.cancelQueries({ predicate });
  queryClient.removeQueries({ predicate });
}
