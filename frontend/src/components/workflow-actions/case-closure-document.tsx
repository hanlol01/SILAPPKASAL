import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Download, FileCheck2, Loader2 } from "lucide-react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { SecureFilePreviewDialog } from "@/components/secure-file-preview-dialog";
import { Button } from "@/components/ui/button";
import { CollapsibleDataCard } from "@/components/collapsible-data-card";
import {
  downloadCaseClosureDocument,
  getCaseClosureDocument,
  issueCaseClosureDocument,
  operationsQueryKeys,
  previewCaseClosureDocument,
} from "@/lib/operations-api";
import { formatDateTime } from "@/lib/format";
import { apiErrorMessage } from "@/lib/form-errors";

export function CaseClosureDocumentCard({ caseId, language }: { caseId: string | number; language: string }) {
  const { t } = useTranslation("dashboard");
  const queryClient = useQueryClient();
  const documentQuery = useQuery({
    queryKey: operationsQueryKeys.caseClosureDocument(caseId),
    queryFn: () => getCaseClosureDocument(caseId),
  });
  const issueMutation = useMutation({
    mutationFn: () => issueCaseClosureDocument(caseId),
    onSuccess: async () => {
      await queryClient.invalidateQueries({ queryKey: operationsQueryKeys.caseClosureDocument(caseId) });
      toast.success(t("workflow.closureDocumentIssued"));
    },
    onError: (error) => toast.error(apiErrorMessage(error, t("workflow.closureDocumentIssueError"))),
  });
  const document = documentQuery.data?.document;
  const capabilities = documentQuery.data?.capabilities;
  const downloadMutation = useMutation({
    mutationFn: () => downloadCaseClosureDocument(document!.public_id),
    onError: (error) => toast.error(apiErrorMessage(error, t("workflow.closureDocumentDownloadError"))),
  });

  if (documentQuery.isLoading) return null;
  if (!document && !capabilities?.manage) return null;

  return (
    <CollapsibleDataCard
      icon={FileCheck2}
      title={t("workflow.closureDocumentTitle")}
      description={t("workflow.closureDocumentDescription")}
    >
      {!document ? (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <p className="text-sm text-muted-foreground">{capabilities?.issue ? t("workflow.closureDocumentReady") : t("workflow.closureDocumentRequirements")}</p>
          <Button onClick={() => issueMutation.mutate()} disabled={!capabilities?.issue || issueMutation.isPending}>
            {issueMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <FileCheck2 className="h-4 w-4" />}
            {t("workflow.issueClosureDocument")}
          </Button>
        </div>
      ) : (
        <div className="flex min-w-0 flex-col gap-3 rounded-md border p-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="min-w-0">
            <p className="break-words text-sm font-medium [overflow-wrap:anywhere]">{document.document_number}</p>
            {document.issued_at && <p className="mt-1 text-xs text-muted-foreground">{t("workflow.closureDocumentIssuedAt", { date: formatDateTime(document.issued_at, language) })}</p>}
          </div>
          <div className="flex shrink-0 flex-wrap gap-2">
            {capabilities?.preview && (
              <SecureFilePreviewDialog
                fileKey={document.public_id}
                expectedMimeType="application/pdf"
                loadPreview={(signal) => previewCaseClosureDocument(document.public_id, signal)}
                onDownload={() => downloadMutation.mutate()}
                downloadPending={downloadMutation.isPending}
                triggerVariant="default"
                triggerSize="default"
                labels={{
                  preview: t("workflow.previewClosureDocument"), title: t("workflow.closureDocumentTitle"), description: t("workflow.closureDocumentDescription"),
                  loading: t("common.loading"), error: t("workflow.closureDocumentDownloadError"), retry: t("common.retry"), close: t("common.close"),
                  download: t("workflow.downloadClosureDocument"), downloading: t("common.loading"), imageAlt: t("workflow.closureDocumentTitle"),
                  pdfTitle: t("workflow.closureDocumentTitle"), pdfFallback: t("workflow.pdfFallback"), zoomIn: t("workflow.zoomIn"), zoomOut: t("workflow.zoomOut"), resetZoom: t("workflow.resetPreview"), fit: t("workflow.fitPreview"), controls: t("workflow.previewControls"),
                }}
              />
            )}
            {capabilities?.download && <Button onClick={() => downloadMutation.mutate()} disabled={downloadMutation.isPending}><Download className="h-4 w-4" />{t("workflow.downloadClosureDocument")}</Button>}
          </div>
        </div>
      )}
    </CollapsibleDataCard>
  );
}
