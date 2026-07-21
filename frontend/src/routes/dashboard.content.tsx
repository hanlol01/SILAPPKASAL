import { keepPreviousData, useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { createFileRoute, Navigate } from "@tanstack/react-router";
import {
  Eye,
  FileQuestion,
  FileText,
  Filter,
  Inbox,
  Loader2,
  MessageCircleQuestion,
  Pencil,
  Plus,
  RefreshCw,
  SearchX,
  Send,
} from "lucide-react";
import { useEffect, useMemo, useState } from "react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";

import { ContentEditor } from "@/components/content/content-editor";
import { EmptyState } from "@/components/empty-state";
import { ListPagination } from "@/components/list-pagination";
import { PageBreadcrumb } from "@/components/page-breadcrumb";
import { Alert, AlertDescription } from "@/components/ui/alert";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
  SheetTrigger,
} from "@/components/ui/sheet";
import { Skeleton } from "@/components/ui/skeleton";
import { useAuth } from "@/hooks/use-auth";
import {
  contentManagementKeys,
  createContentRevision,
  getContentCategories,
  getContentSummary,
  getManagedContent,
  getManagedContentDetail,
  submitManagedContent,
  type ContentLifecycleStatus,
  type ContentType,
  type ManagedContentSummary,
} from "@/lib/content-management-api";
import { apiErrorMessage } from "@/lib/form-errors";
import { DEFAULT_PAGE_SIZE } from "@/lib/list-controls";

export const Route = createFileRoute("/dashboard/content")({ component: DashboardContentPage });

function DashboardContentPage() {
  const { t, i18n } = useTranslation(["content", "dashboard", "common"]);
  const { roleCode, user } = useAuth();
  const queryClient = useQueryClient();
  const canAccess =
    roleCode === "admin" && user?.permissions?.includes("content.read.management.own_campus");
  const [search, setSearch] = useState("");
  const [contentType, setContentType] = useState("");
  const [status, setStatus] = useState("");
  const [category, setCategory] = useState("");
  const [page, setPage] = useState(1);
  const [pageSize, setPageSize] = useState(DEFAULT_PAGE_SIZE);
  const [createOpen, setCreateOpen] = useState(false);
  const [editorType, setEditorType] = useState<ContentType | null>(null);
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const filtersActive = Boolean(search || contentType || status || category);

  useEffect(() => setPage(1), [search, contentType, status, category, pageSize]);
  const filters = useMemo(
    () => ({
      search: search || undefined,
      content_type: contentType || undefined,
      lifecycle_status: status || undefined,
      category: category || undefined,
      page,
      per_page: pageSize,
    }),
    [search, contentType, status, category, page, pageSize],
  );
  const listQuery = useQuery({
    queryKey: contentManagementKeys.list(filters),
    queryFn: () => getManagedContent(filters),
    enabled: canAccess,
    placeholderData: keepPreviousData,
  });
  const summaryQuery = useQuery({
    queryKey: contentManagementKeys.summary(),
    queryFn: getContentSummary,
    enabled: canAccess,
  });
  const educationCategories = useQuery({
    queryKey: contentManagementKeys.categories("education"),
    queryFn: () => getContentCategories("education"),
    enabled: canAccess,
  });
  const policyCategories = useQuery({
    queryKey: contentManagementKeys.categories("policy"),
    queryFn: () => getContentCategories("policy"),
    enabled: canAccess,
  });
  const faqCategories = useQuery({
    queryKey: contentManagementKeys.categories("faq"),
    queryFn: () => getContentCategories("faq"),
    enabled: canAccess,
  });
  const categories = useMemo(
    () =>
      [
        ...(educationCategories.data ?? []),
        ...(policyCategories.data ?? []),
        ...(faqCategories.data ?? []),
      ].filter(
        (item, index, all) =>
          all.findIndex((candidate) => candidate.public_id === item.public_id) === index,
      ),
    [educationCategories.data, faqCategories.data, policyCategories.data],
  );
  const detailQuery = useQuery({
    queryKey: contentManagementKeys.detail(selectedId ?? ""),
    queryFn: () => getManagedContentDetail(selectedId!),
    enabled: canAccess && Boolean(selectedId),
  });

  const invalidate = async () =>
    Promise.all([
      queryClient.invalidateQueries({ queryKey: contentManagementKeys.lists() }),
      queryClient.invalidateQueries({ queryKey: contentManagementKeys.summary() }),
    ]);
  const submitMutation = useMutation({
    mutationFn: (item: ManagedContentSummary) => submitManagedContent(item.version.public_id),
    onSuccess: async () => {
      toast.success(t("content:submittedSuccess"));
      await invalidate();
    },
    onError: (error) => toast.error(apiErrorMessage(error, t("content:loadError"))),
  });
  const revisionMutation = useMutation({
    mutationFn: createContentRevision,
    onSuccess: async (item) => {
      toast.success(t("content:revisionCreated"));
      await invalidate();
      setSelectedId(item.public_id);
      setEditorType(item.content_type);
    },
    onError: (error) => toast.error(apiErrorMessage(error, t("content:loadError"))),
  });

  if (!canAccess) return <Navigate to="/dashboard" replace />;
  if (editorType) {
    if (selectedId && detailQuery.isLoading)
      return (
        <EditorSkeleton
          label={t("common:back")}
          onBack={() => {
            setSelectedId(null);
            setEditorType(null);
          }}
        />
      );
    if (selectedId && detailQuery.isError)
      return (
        <div className="space-y-4">
          <Button
            variant="ghost"
            onClick={() => {
              setSelectedId(null);
              setEditorType(null);
            }}
          >
            {t("content:back")}
          </Button>
          <Alert variant="destructive">
            <AlertDescription>
              {apiErrorMessage(detailQuery.error, t("content:loadError"))}
            </AlertDescription>
          </Alert>
        </div>
      );
    return (
      <ContentEditor
        contentType={editorType}
        detail={detailQuery.data}
        onBack={() => {
          setSelectedId(null);
          setEditorType(null);
        }}
        onSaved={(publicId) => {
          setSelectedId(publicId);
          void queryClient.invalidateQueries({ queryKey: contentManagementKeys.detail(publicId) });
        }}
      />
    );
  }

  const resetFilters = () => {
    setSearch("");
    setContentType("");
    setStatus("");
    setCategory("");
    setPage(1);
  };
  const openItem = (item: ManagedContentSummary) => {
    setSelectedId(item.public_id);
    setEditorType(item.content_type);
  };

  return (
    <div className="space-y-6">
      <PageBreadcrumb crumbs={[{ label: t("content:title") }]} />
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">{t("content:title")}</h1>
          <p className="text-sm text-muted-foreground">{t("content:subtitle")}</p>
        </div>
        <Button className="min-h-11" onClick={() => setCreateOpen(true)}>
          <Plus className="mr-2 h-4 w-4" />
          {t("content:create")}
        </Button>
      </div>

      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        {(
          [
            "draft",
            "submitted",
            "revision_requested",
            "rejected",
            "published",
          ] as ContentLifecycleStatus[]
        ).map((item) => (
          <Card key={item}>
            <CardHeader className="pb-2">
              <CardTitle className="text-sm font-medium text-muted-foreground">
                {t(`content:${item}`)}
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="text-3xl font-semibold">
                {summaryQuery.isLoading ? (
                  <Skeleton className="h-9 w-12" />
                ) : (
                  (summaryQuery.data?.[item] ?? 0)
                )}
              </div>
            </CardContent>
          </Card>
        ))}
      </div>

      <Card className="hidden md:block">
        <CardContent className="p-4">
          <FilterControls
            search={search}
            setSearch={setSearch}
            contentType={contentType}
            setContentType={setContentType}
            status={status}
            setStatus={setStatus}
            category={category}
            setCategory={setCategory}
            categories={categories}
            reset={resetFilters}
          />
        </CardContent>
      </Card>
      <div className="md:hidden">
        <Sheet>
          <SheetTrigger asChild>
            <Button variant="outline" className="min-h-11 w-full">
              <Filter className="mr-2 h-4 w-4" />
              {t("content:filters.allTypes")}
            </Button>
          </SheetTrigger>
          <SheetContent side="bottom" className="max-h-[85vh] overflow-y-auto">
            <SheetHeader>
              <SheetTitle>{t("content:filters.allTypes")}</SheetTitle>
              <SheetDescription>{t("content:subtitle")}</SheetDescription>
            </SheetHeader>
            <div className="mt-5">
              <FilterControls
                search={search}
                setSearch={setSearch}
                contentType={contentType}
                setContentType={setContentType}
                status={status}
                setStatus={setStatus}
                category={category}
                setCategory={setCategory}
                categories={categories}
                reset={resetFilters}
              />
            </div>
          </SheetContent>
        </Sheet>
      </div>

      {listQuery.isError && (
        <Alert variant="destructive">
          <AlertDescription className="flex items-center justify-between gap-3">
            {apiErrorMessage(listQuery.error, t("content:loadError"))}
            <Button size="sm" variant="outline" onClick={() => listQuery.refetch()}>
              <RefreshCw className="mr-2 h-4 w-4" />
              {t("common:retry")}
            </Button>
          </AlertDescription>
        </Alert>
      )}
      <Card>
        <CardContent className="p-0">
          {listQuery.isLoading ? (
            <div className="space-y-3 p-4">
              {Array.from({ length: 5 }).map((_, index) => (
                <Skeleton key={index} className="h-16 w-full" />
              ))}
            </div>
          ) : (
            <>
              <div className="hidden overflow-x-auto md:block">
                <table className="w-full text-sm">
                  <thead className="bg-muted/50 text-left">
                    <tr>
                      <th className="p-3">{t("content:table.content")}</th>
                      <th className="p-3">{t("content:table.type")}</th>
                      <th className="p-3">{t("content:table.section")}</th>
                      <th className="p-3">{t("content:table.status")}</th>
                      <th className="p-3">{t("content:table.version")}</th>
                      <th className="p-3">{t("content:table.updated")}</th>
                      <th className="p-3 text-right">{t("content:table.actions")}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {listQuery.data?.data.map((item) => (
                      <tr key={item.public_id} className="border-t align-top">
                        <td className="p-3">
                          <div className="font-medium">{item.version.title}</div>
                          <div className="mt-1 text-xs text-muted-foreground">
                            {item.category?.name ?? "—"}
                          </div>
                          {item.version.requires_editorial_review && (
                            <div className="mt-1 text-xs text-amber-700 dark:text-amber-300">
                              {t("content:editorialReview")}
                            </div>
                          )}
                        </td>
                        <td className="p-3">{t(`content:${item.content_type}`)}</td>
                        <td className="p-3">
                          {
                            item.section.label[
                              i18n.resolvedLanguage?.startsWith("en") ? "en" : "id"
                            ]
                          }
                        </td>
                        <td className="p-3">
                          <StatusBadge status={item.lifecycle_status} />
                        </td>
                        <td className="p-3">v{item.version.version_number}</td>
                        <td className="p-3">
                          {formatDate(item.updated_at, i18n.resolvedLanguage)}
                        </td>
                        <td className="p-3">
                          <ItemActions
                            item={item}
                            open={openItem}
                            submit={(value) => submitMutation.mutate(value)}
                            createRevision={(id) => revisionMutation.mutate(id)}
                            pending={submitMutation.isPending || revisionMutation.isPending}
                          />
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
              <div className="divide-y md:hidden">
                {listQuery.data?.data.map((item) => (
                  <div key={item.public_id} className="space-y-3 p-4">
                    <div className="flex items-start justify-between gap-2">
                      <div>
                        <h2 className="font-medium">{item.version.title}</h2>
                        <p className="text-xs text-muted-foreground">
                          {t(`content:${item.content_type}`)} · v{item.version.version_number}
                        </p>
                      </div>
                      <StatusBadge status={item.lifecycle_status} />
                    </div>
                    <p className="text-sm text-muted-foreground">
                      {item.category?.name ??
                        item.section.label[i18n.resolvedLanguage?.startsWith("en") ? "en" : "id"]}
                    </p>
                    <ItemActions
                      item={item}
                      open={openItem}
                      submit={(value) => submitMutation.mutate(value)}
                      createRevision={(id) => revisionMutation.mutate(id)}
                      pending={submitMutation.isPending || revisionMutation.isPending}
                    />
                  </div>
                ))}
              </div>
              {listQuery.data?.data.length === 0 &&
                (filtersActive ? (
                  <EmptyState
                    icon={SearchX}
                    title={t("content:filteredEmptyTitle")}
                    description={t("content:filteredEmptyDescription")}
                  />
                ) : (
                  <EmptyState
                    icon={Inbox}
                    title={t("content:emptyTitle")}
                    description={t("content:emptyDescription")}
                  />
                ))}
              <div className="p-4">
                <ListPagination
                  meta={listQuery.data?.meta}
                  page={page}
                  pageSize={pageSize}
                  onPageChange={setPage}
                  onPageSizeChange={setPageSize}
                  isFetching={listQuery.isFetching}
                />
              </div>
            </>
          )}
        </CardContent>
      </Card>

      <Dialog open={createOpen} onOpenChange={setCreateOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("content:createTitle")}</DialogTitle>
            <DialogDescription>{t("content:subtitle")}</DialogDescription>
          </DialogHeader>
          <div className="grid gap-3 sm:grid-cols-3">
            {(
              [
                { type: "article", icon: FileText },
                { type: "faq", icon: FileQuestion },
                { type: "consultation", icon: MessageCircleQuestion },
              ] as const
            ).map(({ type, icon: Icon }) => (
              <Button
                key={type}
                variant="outline"
                className="h-28 flex-col gap-2"
                onClick={() => {
                  setSelectedId(null);
                  setEditorType(type);
                  setCreateOpen(false);
                }}
              >
                <Icon className="h-7 w-7" />
                {t(`content:${type}`)}
              </Button>
            ))}
          </div>
        </DialogContent>
      </Dialog>
    </div>
  );
}

function FilterControls({
  search,
  setSearch,
  contentType,
  setContentType,
  status,
  setStatus,
  category,
  setCategory,
  categories,
  reset,
}: {
  search: string;
  setSearch: (v: string) => void;
  contentType: string;
  setContentType: (v: string) => void;
  status: string;
  setStatus: (v: string) => void;
  category: string;
  setCategory: (v: string) => void;
  categories: Array<{ public_id: string; name: string }>;
  reset: () => void;
}) {
  const { t } = useTranslation("content");
  return (
    <div className="grid gap-3 md:grid-cols-5">
      <Input
        className="h-11"
        value={search}
        onChange={(event) => setSearch(event.target.value)}
        placeholder={t("filters.search")}
      />
      <FilterSelect
        value={contentType}
        onChange={setContentType}
        label={t("filters.allTypes")}
        options={["article", "faq", "consultation"].map((value) => ({ value, label: t(value) }))}
      />
      <FilterSelect
        value={status}
        onChange={setStatus}
        label={t("filters.allStatuses")}
        options={[
          "draft",
          "submitted",
          "in_review",
          "revision_requested",
          "rejected",
          "approved",
          "published",
          "archived",
        ].map((value) => ({ value, label: t(value) }))}
      />
      <FilterSelect
        value={category}
        onChange={setCategory}
        label={t("filters.allCategories")}
        options={categories.map((item) => ({ value: item.public_id, label: item.name }))}
      />
      <Button variant="outline" className="min-h-11" onClick={reset}>
        {t("filters.reset")}
      </Button>
    </div>
  );
}

function FilterSelect({
  value,
  onChange,
  label,
  options,
}: {
  value: string;
  onChange: (value: string) => void;
  label: string;
  options: Array<{ value: string; label: string }>;
}) {
  return (
    <select
      className="h-11 min-w-0 rounded-md border bg-background px-3 text-sm"
      aria-label={label}
      value={value}
      onChange={(event) => onChange(event.target.value)}
    >
      <option value="">{label}</option>
      {options.map((option) => (
        <option key={option.value} value={option.value}>
          {option.label}
        </option>
      ))}
    </select>
  );
}

function StatusBadge({ status }: { status: ContentLifecycleStatus }) {
  const { t } = useTranslation("content");
  return (
    <Badge
      variant={
        ["rejected", "revision_requested"].includes(status)
          ? "destructive"
          : ["published", "approved"].includes(status)
            ? "default"
            : "secondary"
      }
    >
      {t(status)}
    </Badge>
  );
}

function ItemActions({
  item,
  open,
  submit,
  createRevision,
  pending,
}: {
  item: ManagedContentSummary;
  open: (item: ManagedContentSummary) => void;
  submit: (item: ManagedContentSummary) => void;
  createRevision: (id: string) => void;
  pending: boolean;
}) {
  const { t } = useTranslation("content");
  const editable = item.has_editable_version;
  const canCreateRevision =
    !item.has_editable_version &&
    item.published_version !== null &&
    ["published", "rejected"].includes(item.lifecycle_status);
  return (
    <div className="flex flex-wrap justify-end gap-2">
      <Button size="sm" variant="outline" className="min-h-11" onClick={() => open(item)}>
        {editable ? <Pencil className="mr-1 h-4 w-4" /> : <Eye className="mr-1 h-4 w-4" />}
        {t(editable ? "edit" : "view")}
      </Button>
      {editable && item.lifecycle_status === "draft" && (
        <Button size="sm" className="min-h-11" disabled={pending} onClick={() => submit(item)}>
          <Send className="mr-1 h-4 w-4" />
          {t("submit")}
        </Button>
      )}
      {canCreateRevision && (
        <Button
          size="sm"
          className="min-h-11"
          disabled={pending}
          onClick={() => createRevision(item.public_id)}
        >
          {pending ? (
            <Loader2 className="mr-1 h-4 w-4 animate-spin" />
          ) : (
            <Plus className="mr-1 h-4 w-4" />
          )}
          {t("createRevision")}
        </Button>
      )}
    </div>
  );
}

function EditorSkeleton({ onBack, label }: { onBack: () => void; label: string }) {
  return (
    <div className="space-y-4">
      <Button variant="ghost" onClick={onBack}>
        {label}
      </Button>
      <Skeleton className="h-10 w-72" />
      <Skeleton className="h-[32rem] w-full" />
    </div>
  );
}
function formatDate(value: string, language?: string) {
  return new Intl.DateTimeFormat(language?.startsWith("en") ? "en-US" : "id-ID", {
    dateStyle: "medium",
    timeStyle: "short",
  }).format(new Date(value));
}
