import { useQuery } from "@tanstack/react-query";
import { useTranslation } from "react-i18next";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { lookupUsers, operationsQueryKeys } from "@/lib/operations-api";
import { campusQueryKeys, getUniversities } from "@/lib/registration-api";

interface OperationalScopeFilterProps {
  roleCode?: string | null;
  satgasId: string;
  universityId: string;
  includeUnassigned?: boolean;
  onSatgasChange: (value: string) => void;
  onUniversityChange: (value: string) => void;
}

export function OperationalScopeFilter({
  roleCode,
  satgasId,
  universityId,
  includeUnassigned = false,
  onSatgasChange,
  onUniversityChange,
}: OperationalScopeFilterProps) {
  const { t } = useTranslation(["dashboard"]);
  const satgasQuery = useQuery({
    queryKey: operationsQueryKeys.userLookup("satgas_ppks"),
    queryFn: () => lookupUsers("satgas_ppks"),
    enabled: roleCode === "admin",
  });
  const universitiesQuery = useQuery({
    queryKey: campusQueryKeys.universities(),
    queryFn: getUniversities,
    enabled: roleCode === "super_admin",
  });

  if (roleCode === "admin") {
    return (
      <Select value={satgasId} onValueChange={onSatgasChange}>
        <SelectTrigger
          className="w-full sm:w-[220px]"
          disabled={satgasQuery.isLoading}
          aria-label={t("dashboard:filters.satgas")}
        >
          <SelectValue placeholder={t("dashboard:filters.allSatgas")} />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">{t("dashboard:filters.allSatgas")}</SelectItem>
          {includeUnassigned && (
            <SelectItem value="unassigned">{t("dashboard:filters.unassigned")}</SelectItem>
          )}
          {(satgasQuery.data ?? []).map((satgas) => (
            <SelectItem key={satgas.id} value={String(satgas.id)}>
              {satgas.name}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    );
  }

  if (roleCode === "super_admin") {
    return (
      <Select value={universityId} onValueChange={onUniversityChange}>
        <SelectTrigger
          className="w-full sm:w-[240px]"
          disabled={universitiesQuery.isLoading}
          aria-label={t("dashboard:filters.campus")}
        >
          <SelectValue placeholder={t("dashboard:filters.allCampuses")} />
        </SelectTrigger>
        <SelectContent>
          <SelectItem value="all">{t("dashboard:filters.allCampuses")}</SelectItem>
          {(universitiesQuery.data ?? []).map((university) => (
            <SelectItem key={university.id} value={String(university.id)}>
              {university.name}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    );
  }

  return null;
}
