import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { Check, Download, FileCheck2, Loader2 } from "lucide-react";
import { useState } from "react";
import { useTranslation } from "react-i18next";
import { toast } from "sonner";
import { SecureFilePreviewDialog } from "@/components/secure-file-preview-dialog";
import { Button } from "@/components/ui/button";
import { CollapsibleDataCard } from "@/components/collapsible-data-card";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group";
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
  const [signerDialogOpen, setSignerDialogOpen] = useState(false);
  const [selectedSignerId, setSelectedSignerId] = useState("");
  const documentQuery = useQuery({
    queryKey: operationsQueryKeys.caseClosureDocument(caseId),
    queryFn: () => getCaseClosureDocument(caseId),
  });
  const issueMutation = useMutation({
    mutationFn: (signerId?: number) => issueCaseClosureDocument(caseId, signerId === undefined ? undefined : { signer_id: signerId }),
    onSuccess: async () => {
      setSignerDialogOpen(false);
      setSelectedSignerId("");
      await queryClient.invalidateQueries({ queryKey: operationsQueryKeys.caseClosureDocument(caseId) });
      toast.success(t("workflow.closureDocumentIssued"));
    },
    onError: (error) => toast.error(apiErrorMessage(error, t("workflow.closureDocumentIssueError"))),
  });
  const document = documentQuery.data?.document;
  const capabilities = documentQuery.data?.capabilities;
  const signerOptions = documentQuery.data?.signer_options;
  const downloadMutation = useMutation({
    mutationFn: () => downloadCaseClosureDocument(document!.public_id),
    onError: (error) => toast.error(apiErrorMessage(error, t("workflow.closureDocumentDownloadError"))),
  });

  if (documentQuery.isLoading) return null;
  if (!document && !capabilities?.manage) return null;

  const issueDocument = () => {
    if (signerOptions?.selection_required) {
      setSelectedSignerId("");
      setSignerDialogOpen(true);
      return;
    }

    issueMutation.mutate(signerOptions?.eligible_signers[0]?.id);
  };

  return (
    <CollapsibleDataCard
      icon={FileCheck2}
      title={t("workflow.closureDocumentTitle")}
      description={t("workflow.closureDocumentDescription")}
    >
      {!document ? (
        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div className="min-w-0 text-sm text-muted-foreground">
            <p>{capabilities?.issue ? t("workflow.closureDocumentReady") : t("workflow.closureDocumentRequirements")}</p>
            {capabilities?.issue && signerOptions?.eligible_signers.length === 1 && (
              <p className="mt-1">{t("workflow.closureDocumentAutoSigner", { name: signerOptions.eligible_signers[0].name })}</p>
            )}
          </div>
          <Button onClick={issueDocument} disabled={!capabilities?.issue || issueMutation.isPending}>
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
      <Dialog open={signerDialogOpen} onOpenChange={setSignerDialogOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{t("workflow.closureDocumentSignerTitle")}</DialogTitle>
            <DialogDescription>{t("workflow.closureDocumentSignerDescription")}</DialogDescription>
          </DialogHeader>
          <RadioGroup value={selectedSignerId} onValueChange={setSelectedSignerId} className="gap-3">
            {signerOptions?.eligible_signers.map((signer) => (
              <label key={signer.id} className="flex cursor-pointer items-center gap-3 rounded-md border p-3 hover:bg-muted/50">
                <RadioGroupItem value={String(signer.id)} aria-label={signer.name} />
                <span className="min-w-0 flex-1">
                  <span className="block break-words font-medium [overflow-wrap:anywhere]">{signer.name}</span>
                  <span className="block text-sm text-muted-foreground">{t("workflow.closureDocumentSignerIdentity", { number: signer.identity_number })}</span>
                </span>
                {selectedSignerId === String(signer.id) && <Check className="h-4 w-4 text-primary" />}
              </label>
            ))}
          </RadioGroup>
          <DialogFooter>
            <Button variant="outline" onClick={() => setSignerDialogOpen(false)} disabled={issueMutation.isPending}>
              {t("common.cancel")}
            </Button>
            <Button
              onClick={() => issueMutation.mutate(Number(selectedSignerId))}
              disabled={!selectedSignerId || issueMutation.isPending}
            >
              {issueMutation.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <FileCheck2 className="h-4 w-4" />}
              {t("workflow.issueClosureDocument")}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </CollapsibleDataCard>
  );
}
