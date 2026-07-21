import type { ApiUser, RoleCode } from "@/lib/api-types";

const PUBLISHED_CONTENT_PERMISSION = "content.read.published";

export function canReadPublishedContent(user: ApiUser | null | undefined): boolean {
  return Boolean(user?.is_active && user.permissions?.includes(PUBLISHED_CONTENT_PERMISSION));
}

export function canEnterInformationCenterPath(
  roleCode: RoleCode | null,
  pathname: string,
): boolean {
  return (
    roleCode === "reporter" &&
    (pathname === "/dashboard/information-center" ||
      pathname.startsWith("/dashboard/information-center/"))
  );
}
