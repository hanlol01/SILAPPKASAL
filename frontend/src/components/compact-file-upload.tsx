import { FileText, FileUp, X } from "lucide-react";
import type { RefObject } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";

export function CompactFileUpload({
  inputId,
  inputRef,
  accept,
  title,
  instructions,
  chooseLabel,
  changeLabel,
  removeLabel,
  selectedLabel,
  selectedFile,
  selectedDetails,
  error,
  disabled,
  onFileChange,
  onRemove,
}: {
  inputId: string;
  inputRef: RefObject<HTMLInputElement | null>;
  accept: string;
  title: string;
  instructions: string;
  chooseLabel: string;
  changeLabel: string;
  removeLabel: string;
  selectedLabel: string;
  selectedFile: File | null;
  selectedDetails?: string;
  error?: string | null;
  disabled?: boolean;
  onFileChange: (file: File | null) => void;
  onRemove: () => void;
}) {
  return (
    <div className="min-w-0 rounded-md border border-dashed bg-muted/20 p-3 sm:p-4">
      <Input
        ref={inputRef}
        id={inputId}
        type="file"
        accept={accept}
        className="sr-only"
        disabled={disabled}
        aria-describedby={`${inputId}-help${error ? ` ${inputId}-error` : ""}`}
        aria-invalid={Boolean(error)}
        onChange={(event) => onFileChange(event.target.files?.[0] ?? null)}
      />

      <div className="flex min-w-0 flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div className="flex min-w-0 items-start gap-3">
          <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border bg-background text-muted-foreground">
            <FileUp className="h-4 w-4" aria-hidden="true" />
          </span>
          <div className="min-w-0 space-y-1">
            <p className="break-words text-sm font-medium [overflow-wrap:anywhere]">{title}</p>
            <p id={`${inputId}-help`} className="break-words text-xs text-muted-foreground [overflow-wrap:anywhere]">
              {instructions}
            </p>
          </div>
        </div>
        <Button
          type="button"
          variant="outline"
          size="sm"
          className="w-full shrink-0 sm:w-auto"
          disabled={disabled}
          onClick={() => inputRef.current?.click()}
        >
          {selectedFile ? changeLabel : chooseLabel}
        </Button>
      </div>

      {selectedFile && (
        <div className="mt-3 flex min-w-0 items-start gap-3 rounded-md border bg-background p-3" aria-live="polite">
          <FileText className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
          <div className="min-w-0 flex-1">
            <p className="text-xs text-muted-foreground">{selectedLabel}</p>
            <p className="min-w-0 break-words text-sm font-medium [overflow-wrap:anywhere] whitespace-pre-wrap">
              {selectedFile.name}
            </p>
            {selectedDetails && (
              <p className="mt-0.5 min-w-0 break-words text-xs text-muted-foreground [overflow-wrap:anywhere]">
                {selectedDetails}
              </p>
            )}
          </div>
          <Button
            type="button"
            variant="ghost"
            size="icon"
            className="h-8 w-8 shrink-0"
            disabled={disabled}
            title={removeLabel}
            onClick={onRemove}
          >
            <X className="h-4 w-4" aria-hidden="true" />
            <span className="sr-only">{removeLabel}</span>
          </Button>
        </div>
      )}

      {error && (
        <p id={`${inputId}-error`} className="mt-2 min-w-0 break-words text-xs text-destructive [overflow-wrap:anywhere]" role="alert">
          {error}
        </p>
      )}
    </div>
  );
}
