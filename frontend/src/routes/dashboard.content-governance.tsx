import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Navigate } from "@tanstack/react-router";
import {
  Archive,
  CheckCircle2,
  Clock3,
  Eye,
  FileCheck2,
  Filter,
  Inbox,
  Loader2,
  Pencil,
  Plus,
  RotateCcw,
  Send,
  ShieldCheck,
  Star,
  Trash2,
  XCircle,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";

import { AuthenticatedContentAttachment } from "@/components/content/authenticated-content-attachment";
import { ContentDocumentPreview } from "@/components/content/content-document-preview";
import { ContentEditor } from "@/components/content/content-editor";
import { EmptyState } from "@/components/empty-state";
import { ListPagination } from "@/components/list-pagination";
import { PageBreadcrumb } from "@/components/page-breadcrumb";
import { QueryErrorState } from "@/components/query-state";
import { Alert, AlertDescription, AlertTitle } from "@/components/ui/alert";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from "@/components/ui/alert-dialog";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { DatePicker } from "@/components/ui/date-picker";
import { Label } from "@/components/ui/label";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from "@/components/ui/sheet";
import { Skeleton } from "@/components/ui/skeleton";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs";
import { Textarea } from "@/components/ui/textarea";
import { useAuth } from "@/hooks/use-auth";
import { ApiError } from "@/lib/api-client";
import {
  approveContent,
  archiveContent,
  contentGovernanceKeys,
  createFeaturedPlacement,
  getFeaturedCampuses,
  getFeaturedEligible,
  getFeaturedPlacements,
  getGovernanceCampuses,
  getGovernanceCategories,
  getGovernanceDetail,
  getGovernancePublished,
  getGovernanceReviews,
  publishContent,
  rejectContent,
  removeFeaturedPlacement,
  requestContentRevision,
  startContentReview,
  updateFeaturedPlacement,
  type FeaturedPayload,
  type FeaturedPlacement,
  type GovernanceContentDetail,
  type GovernanceContentSummary,
} from "@/lib/content-governance-api";
import {
  contentManagementKeys,
  createContentRevision,
  getManagedContent,
  getManagedContentDetail,
  submitManagedContent,
  type ContentLifecycleStatus,
  type ContentType,
  type ManagedContentSummary,
} from "@/lib/content-management-api";
import { apiErrorMessage } from "@/lib/form-errors";
import { DEFAULT_PAGE_SIZE } from "@/lib/list-controls";
import { cn } from "@/lib/utils";

export const Route = createFileRoute("/dashboard/content-governance")({
  component: ContentGovernancePage,
});

type GovernanceTab = "review" | "global" | "featured";

function ContentGovernancePage() {
  const { t } = useTranslation(["contentGovernance", "content", "common"]);
  const { roleCode, user } = useAuth();
  const permissions = new Set(user?.permissions ?? []);
  const canAccess =
    roleCode === "super_admin" && permissions.has("content.read.management.all");
  const [tab, setTab] = useState<GovernanceTab>("review");

  if (!canAccess) return <Navigate to="/dashboard" replace />;

  return (
    <div className="space-y-6">
      <PageBreadcrumb crumbs={[{ label: t("contentGovernance:title") }]} />
      <div>
        <h1 className="text-2xl font-semibold">{t("contentGovernance:title")}</h1>
        <p className="text-sm text-muted-foreground">{t("contentGovernance:subtitle")}</p>
      </div>
      <Tabs value={tab} onValueChange={(value) => setTab(value as GovernanceTab)}>
        <TabsList className="grid h-auto w-full grid-cols-1 gap-1 sm:grid-cols-3">
          <TabsTrigger value="review" className="min-h-11">
            {t("contentGovernance:tabs.review")}
          </TabsTrigger>
          <TabsTrigger
            value="global"
            className="min-h-11"
            disabled={!permissions.has("content.publish.global")}
          >
            {t("contentGovernance:tabs.global")}
          </TabsTrigger>
          <TabsTrigger
            value="featured"
            className="min-h-11"
            disabled={!permissions.has("content.feature.manage")}
          >
            {t("contentGovernance:tabs.featured")}
          </TabsTrigger>
        </TabsList>
        <TabsContent value="review" className="mt-6">
          <ReviewWorkspace />
        </TabsContent>
        <TabsContent value="global" className="mt-6">
          <GlobalContent />
        </TabsContent>
        <TabsContent value="featured" className="mt-6">
          <FeaturedGovernance />
        </TabsContent>
      </Tabs>
    </div>
  );
}

function ReviewWorkspace() {
  const { t } = useTranslation("contentGovernance");

  return (
    <Tabs defaultValue="pending">
      <TabsList className="grid h-auto w-full grid-cols-2 sm:w-fit">
        <TabsTrigger value="pending" className="min-h-11">
          {t("review.pendingQueue")}
        </TabsTrigger>
        <TabsTrigger value="published" className="min-h-11">
          {t("review.publishedLibrary")}
        </TabsTrigger>
      </TabsList>
      <TabsContent value="pending" className="mt-4">
        <ReviewQueue />
      </TabsContent>
      <TabsContent value="published" className="mt-4">
        <PublishedGovernance />
      </TabsContent>
    </Tabs>
  );
}

function ReviewQueue() {
  const { t, i18n } = useTranslation(["contentGovernance", "content", "common"]);
  const [search, setSearch] = useState("");
  const [status, setStatus] = useState("");
  const [scope, setScope] = useState("");
  const [contentType, setContentType] = useState("");
  const [section, setSection] = useState("");
  const [category, setCategory] = useState("");
  const [campus, setCampus] = useState("");
  const [from, setFrom] = useState("");
  const [to, setTo] = useState("");
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(DEFAULT_PAGE_SIZE);
  const [filtersOpen, setFiltersOpen] = useState(false);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  useEffect(() => setPage(1), [search, status, scope, contentType, section, category, campus, from, to, pageSize]);
  const filters = useMemo(
    () => ({
      search: search || undefined,
      lifecycle_status: status || undefined,
      scope: scope || undefined,
      content_type: contentType || undefined,
      section: section || undefined,
      category: category || undefined,
      university_code: campus || undefined,
      submitted_from: from || undefined,
      submitted_to: to || undefined,
      page,
      per_page: pageSize,
    }),
    [search, status, scope, contentType, section, category, campus, from, to, page, pageSize],
  );
  const query = useQuery({
    queryKey: contentGovernanceKeys.reviews(filters),
    queryFn: ({ signal }) => getGovernanceReviews(filters, signal),
    placeholderData: keepPreviousData,
  });
  const categoriesQuery = useQuery({
    queryKey: contentGovernanceKeys.categories(section),
    queryFn: ({ signal }) => getGovernanceCategories(section, signal),
  });
  const campusesQuery = useQuery({
    queryKey: [...contentGovernanceKeys.campuses(), "reviews"],
    queryFn: ({ signal }) => getGovernanceCampuses(signal),
  });
  const reset = () => {
    setSearch("");
    setStatus("");
    setScope("");
    setContentType("");
    setSection("");
    setCategory("");
    setCampus("");
    setFrom("");
    setTo("");
  };
  const changeScope = (value: string) => {
    setScope(value);
    if (value === "global") setCampus("");
  };
  const filterProps = {
    search,
    setSearch,
    status,
    setStatus,
    scope,
    setScope: changeScope,
    contentType,
    setContentType,
    section,
    setSection,
    category,
    setCategory,
    campus,
    setCampus,
    from,
    setFrom,
    to,
    setTo,
    categories: (categoriesQuery.data ?? []).map(({ public_id, name }) => ({ public_id, name })),
    campuses: campusesQuery.data ?? [],
    reset,
  };

  return (
    <>
      <Card>
        <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between">
          <div>
            <CardTitle>{t("contentGovernance:review.title")}</CardTitle>
            <CardDescription>{t("contentGovernance:review.description")}</CardDescription>
          </div>
          <Button variant="outline" className="min-h-11 md:hidden" onClick={() => setFiltersOpen(true)}>
            <Filter className="mr-2 h-4 w-4" /> {t("contentGovernance:review.filters")}
          </Button>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="hidden md:block">
            <ReviewFilters {...filterProps} />
          </div>
          {query.isError && (
            <QueryErrorState message={t("contentGovernance:review.loadError")} onRetry={() => query.refetch()} />
          )}
          {query.isLoading && <ReviewSkeleton />}
          {!query.isLoading && !query.isError && query.data?.data.length === 0 && (
            <EmptyState icon={Inbox} title={t("contentGovernance:review.empty")} description={t("contentGovernance:review.description")} />
          )}
          {(query.data?.data.length ?? 0) > 0 && (
            <>
              <div className="hidden rounded-lg border 2xl:block">
                <table className="w-full table-fixed text-left text-xs">
                  <thead className="bg-muted/60 text-muted-foreground">
                    <tr>
                      <th className="w-[18%] p-3">{t("content:table.content")}</th>
                      <th className="w-[8%] p-3">{t("contentGovernance:review.type")}</th>
                      <th className="w-[12%] p-3">{t("contentGovernance:review.sectionCategory")}</th>
                      <th className="w-[7%] p-3">{t("contentGovernance:review.scope")}</th>
                      <th className="w-[10%] p-3">{t("contentGovernance:review.campus")}</th>
                      <th className="w-[15%] p-3">{t("contentGovernance:review.submittedBy")}</th>
                      <th className="w-[10%] p-3">{t("contentGovernance:review.submittedAt")}</th>
                      <th className="w-[5%] p-3">{t("contentGovernance:review.version")}</th>
                      <th className="w-[8%] p-3">{t("content:table.status")}</th>
                      <th className="w-[7%] p-3 text-right">{t("content:table.actions")}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {query.data?.data.map((item) => (
                      <tr key={item.public_id} className="border-t align-top">
                        <td className="p-3">
                          <p className="break-words font-medium">{item.version.title}</p>
                        </td>
                        <td className="p-3">{t(`content:${item.content_type}`)}</td>
                        <td className="p-3">
                          <p>{item.section.label[i18n.resolvedLanguage?.startsWith("en") ? "en" : "id"]}</p>
                          <p className="break-words text-muted-foreground">{item.category_name ?? item.category?.name ?? "—"}</p>
                        </td>
                        <td className="p-3">{t(`content:${item.scope}`)}</td>
                        <td className="break-words p-3">{item.scope === "global" ? t("contentGovernance:review.everyCampus") : item.university?.name ?? "—"}</td>
                        <td className="break-words p-3">
                          <p>{item.submitted_by?.name ?? "—"}</p>
                          <p className="text-muted-foreground">{item.submitted_by?.email ?? "—"}</p>
                        </td>
                        <td className="p-3">{formatDate(item.version.submitted_at, i18n.resolvedLanguage)}</td>
                        <td className="p-3">v{item.version.version_number}</td>
                        <td className="p-3"><StatusBadge status={item.lifecycle_status} /></td>
                        <td className="p-3 text-right">
                          <Button size="icon" variant="outline" className="min-h-11 min-w-11" onClick={() => setSelectedId(item.public_id)} aria-label={t("contentGovernance:review.open")}>
                            <Eye className="h-4 w-4" />
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="space-y-3 2xl:hidden">
                {query.data?.data.map((item) => (
                  <ReviewCard key={item.public_id} item={item} onOpen={() => setSelectedId(item.public_id)} />
                ))}
              </div>
              <ListPagination
                meta={query.data?.meta}
                page={page}
                pageSize={pageSize}
                onPageChange={setPage}
                onPageSizeChange={setPageSize}
                isFetching={query.isFetching}
              />
            </>
          )}
        </CardContent>
      </Card>
      <Sheet open={filtersOpen} onOpenChange={setFiltersOpen}>
        <SheetContent className="w-full overflow-y-auto sm:max-w-md">
          <SheetHeader>
            <SheetTitle>{t("contentGovernance:review.filters")}</SheetTitle>
            <SheetDescription>{t("contentGovernance:review.description")}</SheetDescription>
          </SheetHeader>
          <div className="mt-6"><ReviewFilters {...filterProps} /></div>
        </SheetContent>
      </Sheet>
      <ReviewDetail publicId={selectedId} onClose={() => setSelectedId(null)} />
    </>
  );
}

function PublishedGovernance() {
  const { t, i18n } = useTranslation(["contentGovernance", "content"]);
  const [search, setSearch] = useState("");
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(DEFAULT_PAGE_SIZE);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  useEffect(() => setPage(1), [search, pageSize]);
  const filters = useMemo(
    () => ({ search: search || undefined, page, per_page: pageSize }),
    [search, page, pageSize],
  );
  const query = useQuery({
    queryKey: contentGovernanceKeys.published(filters),
    queryFn: ({ signal }) => getGovernancePublished(filters, signal),
    placeholderData: keepPreviousData,
  });

  return (
    <>
      <Card>
        <CardHeader>
          <CardTitle>{t("contentGovernance:review.publishedTitle")}</CardTitle>
          <CardDescription>{t("contentGovernance:review.publishedDescription")}</CardDescription>
        </CardHeader>
        <CardContent className="space-y-4">
          <Label className="block max-w-lg space-y-1">
            <span>{t("contentGovernance:review.search")}</span>
            <Input value={search} onChange={(event) => setSearch(event.target.value)} />
          </Label>
          {query.isError && (
            <QueryErrorState message={t("contentGovernance:review.loadError")} onRetry={() => query.refetch()} />
          )}
          {query.isLoading && <ReviewSkeleton />}
          {!query.isLoading && !query.isError && query.data?.data.length === 0 && (
            <EmptyState
              icon={Archive}
              title={t("contentGovernance:review.publishedEmpty")}
              description={t("contentGovernance:review.publishedDescription")}
            />
          )}
          {(query.data?.data.length ?? 0) > 0 && (
            <>
              <div className="hidden overflow-x-auto rounded-lg border md:block">
                <table className="w-full min-w-[720px] text-left text-sm">
                  <thead className="bg-muted/60 text-muted-foreground">
                    <tr>
                      <th className="p-3">{t("content:table.content")}</th>
                      <th className="p-3">{t("contentGovernance:review.origin")}</th>
                      <th className="p-3">{t("contentGovernance:review.publishedAt")}</th>
                      <th className="p-3 text-right">{t("content:table.actions")}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {query.data?.data.map((item) => (
                      <tr key={item.public_id} className="border-t align-top">
                        <td className="p-3">
                          <p className="font-medium">{item.version.title}</p>
                          <p className="text-xs text-muted-foreground">
                            {t(`content:${item.content_type}`)} · v{item.version.version_number}
                          </p>
                        </td>
                        <td className="p-3">{item.university?.name ?? t("content:global")}</td>
                        <td className="p-3">{formatDate(item.version.published_at, i18n.resolvedLanguage)}</td>
                        <td className="p-3 text-right">
                          <Button variant="outline" className="min-h-11" onClick={() => setSelectedId(item.public_id)}>
                            <Eye className="mr-2 h-4 w-4" />{t("contentGovernance:review.openGovernance")}
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="space-y-3 md:hidden">
                {query.data?.data.map((item) => (
                  <Card key={item.public_id}>
                    <CardContent className="space-y-3 p-4">
                      <div className="flex items-start justify-between gap-2">
                        <div>
                          <h3 className="font-medium">{item.version.title}</h3>
                          <p className="text-xs text-muted-foreground">{item.university?.name ?? t("content:global")}</p>
                        </div>
                        <StatusBadge status={item.lifecycle_status} />
                      </div>
                      <p className="text-sm text-muted-foreground">
                        {t("contentGovernance:review.publishedAt")}: {formatDate(item.version.published_at, i18n.resolvedLanguage)}
                      </p>
                      <Button variant="outline" className="min-h-11 w-full" onClick={() => setSelectedId(item.public_id)}>
                        <Eye className="mr-2 h-4 w-4" />{t("contentGovernance:review.openGovernance")}
                      </Button>
                    </CardContent>
                  </Card>
                ))}
              </div>
              <ListPagination
                meta={query.data?.meta}
                page={page}
                pageSize={pageSize}
                onPageChange={setPage}
                onPageSizeChange={setPageSize}
                isFetching={query.isFetching}
              />
            </>
          )}
        </CardContent>
      </Card>
      <ReviewDetail publicId={selectedId} onClose={() => setSelectedId(null)} />
    </>
  );
}

interface ReviewFilterProps {
  search: string;
  setSearch: (value: string) => void;
  status: string;
  setStatus: (value: string) => void;
  scope: string;
  setScope: (value: string) => void;
  contentType: string;
  setContentType: (value: string) => void;
  section: string;
  setSection: (value: string) => void;
  category: string;
  setCategory: (value: string) => void;
  campus: string;
  setCampus: (value: string) => void;
  from: string;
  setFrom: (value: string) => void;
  to: string;
  setTo: (value: string) => void;
  categories: Array<{ public_id: string; name: string }>;
  campuses: Array<{ code: string; name: string }>;
  reset: () => void;
}

function ReviewFilters(props: ReviewFilterProps) {
  const { t } = useTranslation(["contentGovernance", "content"]);
  return (
    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
      <Label className="space-y-1 xl:col-span-2">
        <span>{t("contentGovernance:review.search")}</span>
        <Input className="h-11" value={props.search} onChange={(e) => props.setSearch(e.target.value)} />
      </Label>
      <FilterSelect value={props.status} onChange={props.setStatus} label={t("contentGovernance:review.allStatuses")} options={[
        ["submitted", t("content:submitted")], ["in_review", t("content:in_review")], ["approved", t("content:approved")],
      ]} />
      <FilterSelect value={props.scope} onChange={props.setScope} label={t("contentGovernance:review.allScopes")} options={[
        ["campus", t("content:campus")], ["global", t("content:global")],
      ]} />
      <FilterSelect value={props.contentType} onChange={props.setContentType} label={t("contentGovernance:review.allTypes")} options={[
        ["article", t("content:article")], ["faq", t("content:faq")], ["consultation", t("content:consultation")],
      ]} />
      <FilterSelect value={props.section} onChange={(value) => { props.setSection(value); props.setCategory(""); }} label={t("contentGovernance:review.allSections")} options={[
        ["education", "Edukasi"], ["policy", "Seputar Kebijakan"], ["faq", "FAQ"], ["consultation", "Konsultasi"],
      ]} />
      <FilterSelect value={props.category} onChange={props.setCategory} label={t("contentGovernance:review.allCategories")} options={props.categories.map((item) => [item.public_id, item.name])} />
      <FilterSelect
        value={props.campus}
        onChange={props.setCampus}
        label={props.scope === "global" ? t("contentGovernance:review.everyCampus") : t("contentGovernance:review.allCampuses")}
        options={props.campuses.map((item) => [item.code, item.name])}
        disabled={props.scope === "global"}
      />
      <Label className="space-y-1">
        <span>{t("contentGovernance:review.submittedFrom")}</span>
        <DatePicker className="h-11" value={props.from} onChange={props.setFrom} placeholder={t("contentGovernance:review.submittedFrom")} />
      </Label>
      <Label className="space-y-1">
        <span>{t("contentGovernance:review.submittedTo")}</span>
        <DatePicker className="h-11" value={props.to} onChange={props.setTo} placeholder={t("contentGovernance:review.submittedTo")} />
      </Label>
      <div className="flex items-end">
        <Button type="button" variant="ghost" className="min-h-11 w-full" onClick={props.reset}>
          <RotateCcw className="mr-2 h-4 w-4" />
          {t("contentGovernance:review.reset")}
        </Button>
      </div>
    </div>
  );
}

function FilterSelect({ value, onChange, label, options, disabled = false }: { value: string; onChange: (value: string) => void; label: string; options: string[][]; disabled?: boolean }) {
  return (
    <Label className="space-y-1">
      <span>{label}</span>
      <Select value={value || "all"} onValueChange={(next) => onChange(next === "all" ? "" : next)} disabled={disabled}>
        <SelectTrigger className="min-h-11"><SelectValue /></SelectTrigger>
        <SelectContent>
          <SelectItem value="all">{label}</SelectItem>
          {options.map(([key, text]) => <SelectItem key={key} value={key}>{text}</SelectItem>)}
        </SelectContent>
      </Select>
    </Label>
  );
}

function ReviewCard({ item, onOpen }: { item: GovernanceContentSummary; onOpen: () => void }) {
  const { t, i18n } = useTranslation(["contentGovernance", "content"]);
  return (
    <Card>
      <CardContent className="space-y-3 p-4">
        <div className="flex items-start justify-between gap-2">
          <div><h3 className="font-medium">{item.version.title}</h3><p className="text-xs text-muted-foreground">{t(`content:${item.content_type}`)} · v{item.version.version_number}</p></div>
          <StatusBadge status={item.lifecycle_status} />
        </div>
        <dl className="grid gap-2 text-sm sm:grid-cols-2 lg:grid-cols-3">
          <div><dt className="text-muted-foreground">{t("contentGovernance:review.sectionCategory")}</dt><dd>{item.section.label[i18n.resolvedLanguage?.startsWith("en") ? "en" : "id"]} · {item.category_name ?? item.category?.name ?? "—"}</dd></div>
          <div><dt className="text-muted-foreground">{t("contentGovernance:review.scope")}</dt><dd>{t(`content:${item.scope}`)}</dd></div>
          <div><dt className="text-muted-foreground">{t("contentGovernance:review.campus")}</dt><dd>{item.scope === "global" ? t("contentGovernance:review.everyCampus") : item.university?.name ?? "—"}</dd></div>
          <div><dt className="text-muted-foreground">{t("contentGovernance:review.submittedBy")}</dt><dd className="break-words">{item.submitted_by?.name ?? "—"}<span className="block text-xs text-muted-foreground">{item.submitted_by?.email ?? "—"}</span></dd></div>
          <div><dt className="text-muted-foreground">{t("contentGovernance:review.submittedAt")}</dt><dd>{formatDate(item.version.submitted_at, i18n.resolvedLanguage)}</dd></div>
          <div><dt className="text-muted-foreground">{t("contentGovernance:review.version")}</dt><dd>v{item.version.version_number}</dd></div>
        </dl>
        <Button variant="outline" className="min-h-11 w-full" onClick={onOpen}><Eye className="mr-2 h-4 w-4" />{t("contentGovernance:review.open")}</Button>
      </CardContent>
    </Card>
  );
}

type DecisionMode = "revision" | "reject" | "approve" | "archive" | null;

function ReviewDetail({ publicId, onClose }: { publicId: string | null; onClose: () => void }) {
  const { t, i18n } = useTranslation(["contentGovernance", "content", "common"]);
  const queryClient = useQueryClient();
  const [mode, setMode] = useState<DecisionMode>(null);
  const [note, setNote] = useState("");
  const [publishOpen, setPublishOpen] = useState(false);
  const query = useQuery({
    queryKey: contentGovernanceKeys.detail(publicId ?? ""),
    queryFn: ({ signal }) => getGovernanceDetail(publicId!, signal),
    enabled: Boolean(publicId),
  });
  const invalidate = async () => {
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: contentGovernanceKeys.all }),
      queryClient.invalidateQueries({ queryKey: contentManagementKeys.all }),
    ]);
  };
  const action = useMutation({
    mutationFn: async ({ kind, value }: { kind: Exclude<DecisionMode, null> | "start" | "publish"; value?: string }) => {
      const item = query.data!;
      if (kind === "start") return startContentReview(item.version.public_id, item.lock_version);
      if (kind === "revision") return requestContentRevision(item.version.public_id, item.lock_version, value ?? "");
      if (kind === "reject") return rejectContent(item.version.public_id, item.lock_version, value ?? "");
      if (kind === "approve") return approveContent(item.version.public_id, item.lock_version, value);
      if (kind === "archive") return archiveContent(item.public_id, item.lock_version, value ?? "");
      return publishContent(item.version.public_id, item.lock_version);
    },
    onSuccess: async () => {
      toast.success(t("contentGovernance:review.success"));
      setMode(null);
      setPublishOpen(false);
      setNote("");
      await invalidate();
    },
    onError: (error) => {
      if (error instanceof ApiError && ["content_stale_review", "content_invalid_lifecycle_transition"].includes(error.errorCode ?? "")) {
        toast.error(t(error.errorCode === "content_stale_review" ? "contentGovernance:review.stale" : "contentGovernance:review.invalidTransition"));
        void invalidate();
        return;
      }
      toast.error(apiErrorMessage(error, t("contentGovernance:review.loadError")));
    },
  });
  const submitDecision = () => {
    if (mode !== "approve" && note.trim().length < 10) {
      toast.error(t("contentGovernance:review.noteRequired"));
      return;
    }
    if (mode) action.mutate({ kind: mode, value: note.trim() });
  };

  return (
    <>
      <Sheet open={Boolean(publicId)} onOpenChange={(open) => { if (!open) onClose(); }}>
        <SheetContent className="w-full overflow-y-auto p-0 sm:max-w-2xl lg:max-w-4xl">
          <SheetHeader className="border-b p-5">
            <SheetTitle>{t("contentGovernance:review.detailTitle")}</SheetTitle>
            <SheetDescription>{t("contentGovernance:review.detailDescription")}</SheetDescription>
          </SheetHeader>
          {query.isLoading && <div className="space-y-3 p-5"><Skeleton className="h-8 w-2/3" /><Skeleton className="h-64 w-full" /></div>}
          {query.isError && <div className="p-5"><QueryErrorState message={t("contentGovernance:review.loadError")} onRetry={() => query.refetch()} /></div>}
          {query.data && (
            <div className="space-y-6 p-5 pb-28">
              <ReviewMetadata item={query.data} />
              <Alert>
                <ShieldCheck className="h-4 w-4" />
                <AlertTitle>{query.data.scope === "campus" ? t("contentGovernance:review.readOnlyCampus") : t("contentGovernance:global.secondReview")}</AlertTitle>
              </Alert>
              <VersionPreview title={t("contentGovernance:review.currentVersion")} item={query.data} version={query.data.version} />
              {query.data.previous_published_version ? (
                <VersionPreview title={t("contentGovernance:review.previousVersion")} item={query.data} version={query.data.previous_published_version} />
              ) : <p className="text-sm text-muted-foreground">{t("contentGovernance:review.noPrevious")}</p>}
              <DecisionHistory item={query.data} />
            </div>
          )}
          {query.data && (
            <div className="fixed inset-x-0 bottom-0 z-20 border-t bg-background/95 p-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] backdrop-blur sm:absolute">
              <div className="grid grid-cols-1 gap-2 sm:flex sm:flex-wrap sm:justify-end">
                {query.data.capabilities.start_review && <Button className="min-h-11 w-full sm:w-auto" disabled={action.isPending} onClick={() => action.mutate({ kind: "start" })}><Clock3 className="mr-2 h-4 w-4" />{t("contentGovernance:review.start")}</Button>}
                {query.data.capabilities.request_revision && <Button variant="outline" className="min-h-11 w-full sm:w-auto" onClick={() => setMode("revision")}><FileCheck2 className="mr-2 h-4 w-4" />{t("contentGovernance:review.requestRevision")}</Button>}
                {query.data.capabilities.reject && <Button variant="destructive" className="min-h-11 w-full sm:w-auto" onClick={() => setMode("reject")}><XCircle className="mr-2 h-4 w-4" />{t("contentGovernance:review.reject")}</Button>}
                {query.data.capabilities.approve && <Button className="min-h-11 w-full sm:w-auto" onClick={() => setMode("approve")}><CheckCircle2 className="mr-2 h-4 w-4" />{t("contentGovernance:review.approve")}</Button>}
                {query.data.capabilities.publish && <Button className="min-h-11 w-full sm:w-auto" onClick={() => setPublishOpen(true)}><Send className="mr-2 h-4 w-4" />{t("contentGovernance:review.publish")}</Button>}
                {query.data.capabilities.archive && <Button variant="destructive" className="min-h-11 w-full sm:w-auto" onClick={() => setMode("archive")}><Archive className="mr-2 h-4 w-4" />{t("contentGovernance:review.archive")}</Button>}
              </div>
            </div>
          )}
        </SheetContent>
      </Sheet>
      <Dialog open={mode !== null} onOpenChange={(open) => { if (!open) setMode(null); }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t(`contentGovernance:review.${mode === "revision" ? "revisionTitle" : mode === "reject" ? "rejectTitle" : mode === "archive" ? "archiveTitle" : "approve"}`)}</DialogTitle>
            <DialogDescription>{t(`contentGovernance:review.${mode === "revision" ? "revisionDescription" : mode === "reject" ? "rejectDescription" : mode === "archive" ? "archiveDescription" : "approvalNote"}`)}</DialogDescription>
          </DialogHeader>
          <Label htmlFor="governance-decision-note">{mode === "approve" ? t("contentGovernance:review.approvalNote") : t("contentGovernance:review.reason")}</Label>
          <Textarea id="governance-decision-note" value={note} onChange={(e) => setNote(e.target.value)} maxLength={2000} rows={6} aria-describedby="governance-note-help" />
          <p id="governance-note-help" className="text-xs text-muted-foreground">{t("contentGovernance:review.reasonHelp")} · {note.length}/2000</p>
          <DialogFooter className="flex-col-reverse gap-2 sm:flex-row">
            <Button variant="outline" className="min-h-11" onClick={() => setMode(null)}>{t("contentGovernance:review.cancel")}</Button>
            <Button variant={mode === "reject" || mode === "archive" ? "destructive" : "default"} className="min-h-11" disabled={action.isPending} onClick={submitDecision}>
              {action.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}{t("contentGovernance:review.confirm")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
      <AlertDialog open={publishOpen} onOpenChange={setPublishOpen}>
        <AlertDialogContent>
          <AlertDialogHeader><AlertDialogTitle>{t("contentGovernance:review.publishTitle")}</AlertDialogTitle><AlertDialogDescription>{t("contentGovernance:review.publishDescription")}</AlertDialogDescription></AlertDialogHeader>
          <AlertDialogFooter><AlertDialogCancel>{t("contentGovernance:review.cancel")}</AlertDialogCancel><AlertDialogAction onClick={() => action.mutate({ kind: "publish" })}>{t("contentGovernance:review.publish")}</AlertDialogAction></AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </>
  );
}

function ReviewMetadata({ item }: { item: GovernanceContentDetail }) {
  const { t, i18n } = useTranslation(["contentGovernance", "content"]);
  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-start justify-between gap-2"><div><h2 className="text-xl font-semibold">{item.version.title}</h2><p className="text-sm text-muted-foreground">{t(`content:${item.content_type}`)} · v{item.version.version_number}</p></div><StatusBadge status={item.lifecycle_status} /></div>
      <dl className="grid gap-3 rounded-lg border p-4 text-sm sm:grid-cols-2 lg:grid-cols-4">
        <Meta label={t("contentGovernance:review.scope")} value={t(`content:${item.scope}`)} />
        <Meta label={t("contentGovernance:review.campus")} value={item.scope === "global" ? t("contentGovernance:review.everyCampus") : item.university?.name ?? "—"} />
        <Meta label={t("content:section")} value={item.section.label[i18n.resolvedLanguage?.startsWith("en") ? "en" : "id"]} />
        <Meta label={t("content:category")} value={item.category_name ?? item.category?.name ?? "—"} />
        <Meta label={t("contentGovernance:review.createdBy")} value={actorLabel(item.created_by)} />
        <Meta label={t("contentGovernance:review.createdAt")} value={formatDate(item.created_at, i18n.resolvedLanguage)} />
        <Meta label={t("contentGovernance:review.submittedBy")} value={actorLabel(item.submitted_by)} />
        <Meta label={t("contentGovernance:review.submittedAt")} value={formatDate(item.version.submitted_at, i18n.resolvedLanguage)} />
        <Meta label={t("contentGovernance:review.reviewedBy")} value={actorLabel(item.reviewed_by)} />
        <Meta label={t("contentGovernance:review.reviewedAt")} value={formatDate(item.version.reviewed_at, i18n.resolvedLanguage)} />
        <Meta label={t("contentGovernance:review.approvedBy")} value={actorLabel(item.approved_by)} />
        <Meta label={t("contentGovernance:review.approvedAt")} value={formatDate(item.version.approved_at, i18n.resolvedLanguage)} />
        <Meta label={t("contentGovernance:review.publishedBy")} value={actorLabel(item.published_by)} />
        <Meta label={t("contentGovernance:review.publishedAt")} value={formatDate(item.version.published_at, i18n.resolvedLanguage)} />
        <Meta label={t("contentGovernance:review.version")} value={`v${item.version.version_number}`} />
        <Meta label={t("contentGovernance:review.currentStatus")} value={t(`content:${item.lifecycle_status}`)} />
      </dl>
    </div>
  );
}

function Meta({ label, value }: { label: string; value: string }) { return <div><dt className="text-muted-foreground">{label}</dt><dd className="break-words font-medium">{value}</dd></div>; }

function actorLabel(actor: GovernanceContentSummary["created_by"]) {
  if (!actor) return "—";
  return `${actor.name} · ${actor.email}`;
}

function VersionPreview({ title, item, version }: { title: string; item: GovernanceContentDetail; version: GovernanceContentDetail["version"] }) {
  const { t } = useTranslation(["contentGovernance", "content"]);
  return (
    <Card>
      <CardHeader><CardTitle className="text-base">{title} · v{version.version_number}</CardTitle>{version.excerpt && <CardDescription>{version.excerpt}</CardDescription>}</CardHeader>
      <CardContent className="space-y-5">
        {version.editorial_note && <Alert><AlertTitle>{t("contentGovernance:review.editorialNote")}</AlertTitle><AlertDescription className="whitespace-pre-wrap">{version.editorial_note}</AlertDescription></Alert>}
        <div><h3 className="mb-2 font-medium">{t("contentGovernance:review.contentPreview")}</h3>
          {item.content_type === "article" && <ContentDocumentPreview document={version.article?.document ?? null} />}
          {item.content_type === "faq" && <div className="space-y-3"><p className="font-medium">{version.faq?.question}</p><ContentDocumentPreview document={version.faq?.answer_document ?? null} /></div>}
          {item.content_type === "consultation" && version.consultation && <ConsultationPreview value={version.consultation} />}
        </div>
        <div><h3 className="mb-2 font-medium">{t("contentGovernance:review.attachments")}</h3>{version.attachments.length ? <ul className="space-y-2">{version.attachments.map((file) => <li key={file.public_id}><AuthenticatedContentAttachment attachment={file} /></li>)}</ul> : <p className="text-sm text-muted-foreground">{t("contentGovernance:review.noAttachments")}</p>}</div>
      </CardContent>
    </Card>
  );
}

function ConsultationPreview({ value }: { value: NonNullable<GovernanceContentDetail["version"]["consultation"]> }) {
  return <dl className="grid gap-3 rounded-lg border p-4 text-sm sm:grid-cols-2"><Meta label="Service" value={value.service_name} /><Meta label="Email" value={value.email ?? "—"} /><Meta label="Phone" value={value.phone_display ?? "—"} /><Meta label="WhatsApp" value={value.whatsapp_display ?? "—"} /><Meta label="Hours" value={value.operating_hours ?? "—"} /><Meta label="Verified owner" value={value.verified_owner ?? "—"} /></dl>;
}

function DecisionHistory({ item }: { item: GovernanceContentDetail }) {
  const { t, i18n } = useTranslation(["contentGovernance", "content"]);
  return (
    <Card>
      <CardHeader>
        <CardTitle className="text-base">{t("contentGovernance:review.history")}</CardTitle>
      </CardHeader>
      <CardContent>
        {item.decision_history.length ? (
          <ol className="relative space-y-4 border-l pl-5">
            {item.decision_history.map((event) => (
              <li key={event.public_id}>
                <span className="absolute -left-1.5 mt-1.5 h-3 w-3 rounded-full border bg-background" />
                <div className="flex flex-wrap items-center gap-2">
                  <Badge variant="outline">
                    {t(`contentGovernance:review.timelineStates.${event.state}`, {
                      defaultValue: event.state.replaceAll("_", " "),
                    })}
                  </Badge>
                  {event.version_number && <span className="text-xs">v{event.version_number}</span>}
                  {event.from_status && event.to_status && (
                    <span className="text-xs text-muted-foreground">
                      {t(`content:${event.from_status}`)} → {t(`content:${event.to_status}`)}
                    </span>
                  )}
                </div>
                <p className="mt-1 text-sm">
                  {event.actor.name ?? t("contentGovernance:review.systemActor")}
                  {event.actor.email ? ` · ${event.actor.email}` : ""}
                  {event.actor.role ? ` · ${event.actor.role}` : ""}
                  {" · "}
                  {formatDate(event.timestamp, i18n.resolvedLanguage)}
                </p>
                {event.note && (
                  <p className="mt-2 whitespace-pre-wrap rounded-md bg-muted p-3 text-sm">
                    {event.note}
                  </p>
                )}
              </li>
            ))}
          </ol>
        ) : (
          <p className="text-sm text-muted-foreground">
            {t("contentGovernance:review.historyEmpty")}
          </p>
        )}
        {item.decision_history_truncated && (
          <p className="mt-4 text-xs text-muted-foreground">
            {t("contentGovernance:review.historyTruncated")}
          </p>
        )}
      </CardContent>
    </Card>
  );
}

function GlobalContent() {
  const { t } = useTranslation(["contentGovernance", "content"]);
  const queryClient = useQueryClient();
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(DEFAULT_PAGE_SIZE);
  const [search, setSearch] = useState("");
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [editorType, setEditorType] = useState<ContentType | null>(null);
  const [createOpen, setCreateOpen] = useState(false);
  const filters = useMemo(() => ({ search: search || undefined, page, per_page: pageSize }), [search, page, pageSize]);
  const list = useQuery({ queryKey: contentManagementKeys.list(filters), queryFn: ({ signal }) => getManagedContent(filters, signal), placeholderData: keepPreviousData });
  const detail = useQuery({ queryKey: contentManagementKeys.detail(selectedId ?? ""), queryFn: ({ signal }) => getManagedContentDetail(selectedId!, signal), enabled: Boolean(selectedId) });
  const invalidate = () => Promise.all([queryClient.invalidateQueries({ queryKey: contentManagementKeys.all }), queryClient.invalidateQueries({ queryKey: contentGovernanceKeys.all })]);
  const submit = useMutation({ mutationFn: (item: ManagedContentSummary) => submitManagedContent(item.version.public_id, item.lock_version), onSuccess: async () => { toast.success(t("content:submittedSuccess")); await invalidate(); }, onError: (error) => toast.error(apiErrorMessage(error, t("content:loadError"))) });
  const revision = useMutation({ mutationFn: (item: ManagedContentSummary) => createContentRevision(item.public_id, item.lock_version), onSuccess: async (item) => { await invalidate(); setSelectedId(item.public_id); setEditorType(item.content_type); }, onError: (error) => toast.error(apiErrorMessage(error, t("content:loadError"))) });
  useEffect(() => setPage(1), [search, pageSize]);
  if (editorType) {
    if (selectedId && detail.isLoading) return <ReviewSkeleton />;
    return <ContentEditor scope="global" contentType={editorType} detail={selectedId ? detail.data : undefined} onBack={() => { setEditorType(null); setSelectedId(null); }} onSaved={(id) => { setSelectedId(id); void queryClient.invalidateQueries({ queryKey: contentManagementKeys.detail(id) }); }} />;
  }
  return (
    <Card>
      <CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between"><div><CardTitle>{t("contentGovernance:global.title")}</CardTitle><CardDescription>{t("contentGovernance:global.description")}</CardDescription></div><Button className="min-h-11" onClick={() => setCreateOpen(true)}><Plus className="mr-2 h-4 w-4" />{t("contentGovernance:global.create")}</Button></CardHeader>
      <CardContent className="space-y-4">
        <Alert><ShieldCheck className="h-4 w-4" /><AlertTitle>{t("contentGovernance:global.helper")}</AlertTitle><AlertDescription>{t("contentGovernance:global.secondReview")}</AlertDescription></Alert>
        <Label className="block max-w-md space-y-1"><span>{t("content:filters.search")}</span><Input value={search} onChange={(e) => setSearch(e.target.value)} /></Label>
        {list.isError && <QueryErrorState message={t("content:loadError")} onRetry={() => list.refetch()} />}
        {list.isLoading && <ReviewSkeleton />}
        {!list.isLoading && list.data?.data.length === 0 && <EmptyState icon={Inbox} title={t("contentGovernance:global.empty")} description={t("contentGovernance:global.description")} />}
        <div className="grid gap-3 lg:grid-cols-2">
          {list.data?.data.map((item) => <GlobalCard key={item.public_id} item={item} pending={submit.isPending || revision.isPending} onOpen={() => { setSelectedId(item.public_id); setEditorType(item.content_type); }} onSubmit={() => submit.mutate(item)} onRevision={() => revision.mutate(item)} />)}
        </div>
        <ListPagination meta={list.data?.meta} page={page} pageSize={pageSize} onPageChange={setPage} onPageSizeChange={setPageSize} isFetching={list.isFetching} />
      </CardContent>
      <Dialog open={createOpen} onOpenChange={setCreateOpen}><DialogContent><DialogHeader><DialogTitle>{t("contentGovernance:global.create")}</DialogTitle><DialogDescription>{t("contentGovernance:global.description")}</DialogDescription></DialogHeader><div className="grid gap-3 sm:grid-cols-3">{(["article", "faq", "consultation"] as ContentType[]).map((type) => <Button key={type} variant="outline" className="min-h-20" onClick={() => { setSelectedId(null); setEditorType(type); setCreateOpen(false); }}>{t(`content:${type}`)}</Button>)}</div></DialogContent></Dialog>
    </Card>
  );
}

function GlobalCard({ item, pending, onOpen, onSubmit, onRevision }: { item: ManagedContentSummary; pending: boolean; onOpen: () => void; onSubmit: () => void; onRevision: () => void }) {
  const { t, i18n } = useTranslation(["contentGovernance", "content"]);
  const editable = item.has_editable_version && item.archived_at === null;
  const canRevision = !editable && item.published_version !== null && ["published", "rejected"].includes(item.lifecycle_status);
  return <Card><CardContent className="space-y-3 p-4"><div className="flex items-start justify-between gap-2"><div><h3 className="font-medium">{item.version.title}</h3><p className="text-xs text-muted-foreground">{t(`content:${item.content_type}`)} · v{item.version.version_number}</p></div><StatusBadge status={item.lifecycle_status} /></div><dl className="grid gap-2 text-sm sm:grid-cols-2"><Meta label={t("contentGovernance:review.createdBy")} value={actorLabel(item.created_by)} /><Meta label={t("contentGovernance:review.createdAt")} value={formatDate(item.created_at, i18n.resolvedLanguage)} /><Meta label={t("contentGovernance:review.submittedBy")} value={actorLabel(item.submitted_by)} /><Meta label={t("contentGovernance:review.submittedAt")} value={formatDate(item.version.submitted_at, i18n.resolvedLanguage)} /></dl><div className="flex flex-wrap justify-end gap-2"><Button variant="outline" className="min-h-11" onClick={onOpen}>{editable ? <Pencil className="mr-2 h-4 w-4" /> : <Eye className="mr-2 h-4 w-4" />}{t(editable ? "content:edit" : "content:view")}</Button>{editable && item.lifecycle_status === "draft" && <Button className="min-h-11" disabled={pending} onClick={onSubmit}><Send className="mr-2 h-4 w-4" />{t("content:submit")}</Button>}{canRevision && <Button className="min-h-11" disabled={pending} onClick={onRevision}><Plus className="mr-2 h-4 w-4" />{t("content:createRevision")}</Button>}</div></CardContent></Card>;
}

interface FeaturedFormState { scope: "global" | "campus"; campus: string; content: string; rank: string; active: boolean; from: string; until: string; }
const emptyFeatured: FeaturedFormState = { scope: "global", campus: "", content: "", rank: "1", active: true, from: "", until: "" };

function FeaturedGovernance() {
  const { t, i18n } = useTranslation(["contentGovernance", "content"]);
  const queryClient = useQueryClient();
  const [stateFilter, setStateFilter] = useState("");
  const [formOpen, setFormOpen] = useState(false);
  const [form, setForm] = useState<FeaturedFormState>(emptyFeatured);
  const [editing, setEditing] = useState<FeaturedPlacement | null>(null);
  const [removing, setRemoving] = useState<FeaturedPlacement | null>(null);
  const placements = useQuery({ queryKey: contentGovernanceKeys.featured({ state: stateFilter || undefined }), queryFn: ({ signal }) => getFeaturedPlacements({ state: stateFilter || undefined }, signal) });
  const campuses = useQuery({ queryKey: [...contentGovernanceKeys.campuses(), "featured"], queryFn: ({ signal }) => getFeaturedCampuses(signal) });
  const eligibleFilters = { scope: form.scope, university_code: form.scope === "campus" ? form.campus || undefined : undefined };
  const eligible = useQuery({ queryKey: contentGovernanceKeys.eligible(eligibleFilters), queryFn: ({ signal }) => getFeaturedEligible(eligibleFilters, signal), enabled: formOpen && (form.scope === "global" || Boolean(form.campus)) });
  const invalidate = () => Promise.all([queryClient.invalidateQueries({ queryKey: contentGovernanceKeys.all }), queryClient.invalidateQueries({ queryKey: ["content"] })]);
  const save = useMutation({ mutationFn: async () => {
    if (!form.content || !Number(form.rank) || (form.scope === "campus" && !form.campus)) throw new ClientFormError(t("content:validation.required"));
    if (form.from && form.until && new Date(form.from) > new Date(form.until)) throw new ClientFormError(t("contentGovernance:featured.invalidWindow"));
    const payload: FeaturedPayload = { content_public_id: form.content, scope: form.scope, university_code: form.scope === "campus" ? form.campus : null, rank: Number(form.rank), is_active: form.active, active_from: form.from ? new Date(form.from).toISOString() : null, active_until: form.until ? new Date(form.until).toISOString() : null, concurrency_token: editing?.concurrency_token };
    return editing ? updateFeaturedPlacement(editing.public_id, payload) : createFeaturedPlacement(payload);
  }, onSuccess: async () => { toast.success(t("contentGovernance:featured.saved")); setFormOpen(false); setEditing(null); setForm(emptyFeatured); await invalidate(); }, onError: (error) => { if (error instanceof ClientFormError) toast.error(error.message); else if (error instanceof ApiError && error.errorCode === "content_featured_stale") { toast.error(t("contentGovernance:featured.stale")); void invalidate(); } else if (error instanceof ApiError && error.errorCode === "content_featured_conflict") toast.error(t("contentGovernance:featured.conflict")); else toast.error(apiErrorMessage(error, t("contentGovernance:review.loadError"))); } });
  const remove = useMutation({ mutationFn: (item: FeaturedPlacement) => removeFeaturedPlacement(item.public_id, item.concurrency_token), onSuccess: async () => { toast.success(t("contentGovernance:featured.removed")); setRemoving(null); await invalidate(); }, onError: (error) => { toast.error(error instanceof ApiError && error.errorCode === "content_featured_stale" ? t("contentGovernance:featured.stale") : apiErrorMessage(error, t("contentGovernance:review.loadError"))); void invalidate(); } });
  const openCreate = () => { setEditing(null); setForm(emptyFeatured); setFormOpen(true); };
  const openEdit = (item: FeaturedPlacement) => { setEditing(item); setForm({ scope: item.scope, campus: item.university?.code ?? "", content: item.content.public_id, rank: String(item.rank), active: item.is_active, from: toLocalInput(item.active_from), until: toLocalInput(item.active_until) }); setFormOpen(true); };
  const selected = eligible.data?.find((item) => item.public_id === form.content) ?? editing?.content;
  return <>
    <Card><CardHeader className="gap-3 sm:flex-row sm:items-start sm:justify-between"><div><CardTitle>{t("contentGovernance:featured.title")}</CardTitle><CardDescription>{t("contentGovernance:featured.description")}</CardDescription></div><Button className="min-h-11" onClick={openCreate}><Plus className="mr-2 h-4 w-4" />{t("contentGovernance:featured.create")}</Button></CardHeader><CardContent className="space-y-4">
      <div className="max-w-xs"><FilterSelect value={stateFilter} onChange={setStateFilter} label={t("contentGovernance:review.allStatuses")} options={(["current", "future", "expired", "inactive"] as const).map((state) => [state, t(`contentGovernance:featured.${state}`)])} /></div>
      {placements.isError && <QueryErrorState message={t("contentGovernance:review.loadError")} onRetry={() => placements.refetch()} />}
      {placements.isLoading && <ReviewSkeleton />}
      {!placements.isLoading && placements.data?.length === 0 && <EmptyState icon={Star} title={t("contentGovernance:featured.empty")} description={t("contentGovernance:featured.description")} />}
      <div className="grid gap-3 lg:grid-cols-2">{placements.data?.map((item) => <Card key={item.public_id}><CardContent className="space-y-3 p-4"><div className="flex items-start justify-between gap-2"><div><p className="text-xs text-muted-foreground">#{item.rank} · {item.university?.name ?? t("content:global")}</p><h3 className="font-medium">{item.content.title}</h3></div><Badge variant={item.state === "current" ? "default" : "secondary"}>{t(`contentGovernance:featured.${item.state}`)}</Badge></div><p className="line-clamp-2 text-sm text-muted-foreground">{item.content.excerpt}</p><p className="text-xs text-muted-foreground">{formatDate(item.active_from, i18n.resolvedLanguage)} — {formatDate(item.active_until, i18n.resolvedLanguage)}</p><div className="flex flex-wrap justify-end gap-2"><Button variant="outline" className="min-h-11" onClick={() => openEdit(item)}><Pencil className="mr-2 h-4 w-4" />{t("contentGovernance:featured.edit")}</Button><Button variant="destructive" className="min-h-11" onClick={() => setRemoving(item)}><Trash2 className="mr-2 h-4 w-4" />{t("contentGovernance:featured.remove")}</Button></div></CardContent></Card>)}</div>
    </CardContent></Card>
    <Dialog open={formOpen} onOpenChange={(open) => { setFormOpen(open); if (!open) setEditing(null); }}><DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl"><DialogHeader><DialogTitle>{t(editing ? "contentGovernance:featured.edit" : "contentGovernance:featured.create")}</DialogTitle><DialogDescription>{t("contentGovernance:featured.description")}</DialogDescription></DialogHeader><div className="grid gap-4 sm:grid-cols-2">
      <FilterSelect value={form.scope} onChange={(value) => setForm((current) => ({ ...current, scope: value as "global" | "campus", campus: "", content: "" }))} label={t("contentGovernance:featured.scope")} options={[["global", t("content:global")], ["campus", t("content:campus")]]} />
      {form.scope === "campus" && <FilterSelect value={form.campus} onChange={(value) => setForm((current) => ({ ...current, campus: value, content: "" }))} label={t("contentGovernance:featured.selectCampus")} options={(campuses.data ?? []).map((item) => [item.code, item.name])} />}
      <Label className="space-y-1 sm:col-span-2"><span>{t("contentGovernance:featured.content")}</span><Select value={form.content || undefined} onValueChange={(value) => setForm((current) => ({ ...current, content: value }))}><SelectTrigger className="min-h-11"><SelectValue placeholder={t("contentGovernance:featured.selectContent")} /></SelectTrigger><SelectContent>{eligible.data?.map((item) => <SelectItem key={item.public_id} value={item.public_id}>{item.title}</SelectItem>)}</SelectContent></Select>{eligible.data?.length === 0 && <p className="text-xs text-muted-foreground">{t("contentGovernance:featured.eligibleEmpty")}</p>}</Label>
      <Label className="space-y-1"><span>{t("contentGovernance:featured.rank")}</span><Select value={form.rank} onValueChange={(value) => setForm((current) => ({ ...current, rank: value }))}><SelectTrigger className="min-h-11"><SelectValue /></SelectTrigger><SelectContent>{[1,2,3,4,5].map((rank) => <SelectItem key={rank} value={String(rank)}>#{rank}</SelectItem>)}</SelectContent></Select></Label>
      <Label className="flex min-h-11 items-center gap-3 self-end"><Checkbox checked={form.active} onCheckedChange={(value) => setForm((current) => ({ ...current, active: value === true }))} />{t("contentGovernance:featured.active")}</Label>
      <Label className="space-y-1"><span>{t("contentGovernance:featured.activeFrom")}</span><Input type="datetime-local" value={form.from} onChange={(e) => setForm((current) => ({ ...current, from: e.target.value }))} /></Label>
      <Label className="space-y-1"><span>{t("contentGovernance:featured.activeUntil")}</span><Input type="datetime-local" value={form.until} onChange={(e) => setForm((current) => ({ ...current, until: e.target.value }))} /></Label>
    </div>{selected && <Card><CardHeader><CardTitle className="text-base">{t("contentGovernance:featured.preview")}</CardTitle></CardHeader><CardContent><p className="font-medium">{selected.title}</p><p className="mt-1 text-sm text-muted-foreground">{selected.excerpt}</p></CardContent></Card>}<DialogFooter><Button variant="outline" className="min-h-11" onClick={() => setFormOpen(false)}>{t("contentGovernance:review.cancel")}</Button><Button className="min-h-11" disabled={save.isPending} onClick={() => save.mutate()}>{save.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}{t("contentGovernance:featured.save")}</Button></DialogFooter></DialogContent></Dialog>
    <AlertDialog open={Boolean(removing)} onOpenChange={(open) => { if (!open) setRemoving(null); }}><AlertDialogContent><AlertDialogHeader><AlertDialogTitle>{t("contentGovernance:featured.removeTitle")}</AlertDialogTitle><AlertDialogDescription>{t("contentGovernance:featured.removeDescription")}</AlertDialogDescription></AlertDialogHeader><AlertDialogFooter><AlertDialogCancel>{t("contentGovernance:review.cancel")}</AlertDialogCancel><AlertDialogAction className="bg-destructive text-destructive-foreground hover:bg-destructive/90" onClick={() => removing && remove.mutate(removing)}>{t("contentGovernance:featured.remove")}</AlertDialogAction></AlertDialogFooter></AlertDialogContent></AlertDialog>
  </>;
}

class ClientFormError extends Error {}

function StatusBadge({ status }: { status: ContentLifecycleStatus }) { const { t } = useTranslation("content"); return <Badge variant={["rejected", "revision_requested"].includes(status) ? "destructive" : ["approved", "published"].includes(status) ? "default" : "secondary"}>{t(status)}</Badge>; }
function ReviewSkeleton() { return <div className="space-y-3"><Skeleton className="h-20 w-full" /><Skeleton className="h-20 w-full" /><Skeleton className="h-20 w-full" /></div>; }
function formatDate(value: string | null, language?: string) { if (!value) return "—"; return new Intl.DateTimeFormat(language?.startsWith("en") ? "en-US" : "id-ID", { dateStyle: "medium", timeStyle: "short" }).format(new Date(value)); }
function toLocalInput(value: string | null) { if (!value) return ""; const date = new Date(value); const offset = date.getTimezoneOffset(); return new Date(date.getTime() - offset * 60000).toISOString().slice(0, 16); }
