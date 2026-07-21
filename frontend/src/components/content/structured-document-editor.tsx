import { ArrowDown, ArrowUp, Bold, Italic, Link2, Plus, Trash2 } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { useTranslation } from "react-i18next";

import { ContentDocumentPreview } from "@/components/content/content-document-preview";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import {
  documentToEditorBlocks,
  editorBlocksToDocument,
  emptyEditorBlock,
  type DocumentNode,
  type EditorBlockKind,
  type StructuredEditorBlock,
} from "@/lib/content-document-editor-model";
import { cn } from "@/lib/utils";

interface Props {
  value: DocumentNode | null;
  onChange: (document: DocumentNode) => void;
  faq?: boolean;
  disabled?: boolean;
  error?: string;
}

const articleKinds: EditorBlockKind[] = [
  "paragraph",
  "h2",
  "h3",
  "orderedList",
  "unorderedList",
  "blockquote",
  "information",
  "warning",
  "help",
  "divider",
];
const faqKinds: EditorBlockKind[] = ["paragraph", "orderedList", "unorderedList", "blockquote"];

export function StructuredDocumentEditor({
  value,
  onChange,
  faq = false,
  disabled = false,
  error,
}: Props) {
  const { t } = useTranslation("content");
  const [blocks, setBlocks] = useState<StructuredEditorBlock[]>(() =>
    documentToEditorBlocks(value),
  );
  const lastEmittedDocument = useRef<DocumentNode | null>(null);
  const kinds = faq ? faqKinds : articleKinds;

  useEffect(() => {
    if (value === lastEmittedDocument.current) return;
    setBlocks(documentToEditorBlocks(value));
  }, [value]);

  const update = (next: StructuredEditorBlock[]) => {
    setBlocks(next);
    const document = editorBlocksToDocument(next);
    lastEmittedDocument.current = document;
    onChange(document);
  };

  const add = (kind: EditorBlockKind) => update([...blocks, emptyEditorBlock(kind)]);
  const patchBlock = (index: number, patch: Partial<StructuredEditorBlock>) =>
    update(
      blocks.map((block, position) =>
        position === index ? { ...block, ...patch, modified: true } : block,
      ),
    );
  const move = (index: number, offset: number) => {
    const target = index + offset;
    if (target < 0 || target >= blocks.length) return;
    const next = [...blocks];
    [next[index], next[target]] = [next[target], next[index]];
    update(next);
  };

  return (
    <div className="space-y-3" aria-describedby={error ? "document-error" : undefined}>
      <div
        className="flex flex-wrap gap-2 rounded-lg border bg-muted/30 p-2"
        role="toolbar"
        aria-label={t("editor.addBlock")}
      >
        {kinds.map((kind) => (
          <Button
            key={kind}
            type="button"
            variant="outline"
            size="sm"
            className="min-h-11"
            disabled={disabled}
            onClick={() => add(kind)}
          >
            <Plus className="mr-1 h-4 w-4" /> {t(`editor.${kind}`)}
          </Button>
        ))}
      </div>

      {blocks.length === 0 && (
        <p className="rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
          {t("editor.empty")}
        </p>
      )}

      <div className="space-y-3">
        {blocks.map((block, index) => {
          if (block.readOnly && block.rawNode) {
            return (
              <div
                key={block.id}
                className="rounded-lg border border-dashed bg-muted/20 p-4 shadow-sm"
              >
                <p className="font-medium">{t("editor.preservedTitle")}</p>
                <p className="mb-3 text-sm text-muted-foreground">
                  {t("editor.preservedDescription")}
                </p>
                <ContentDocumentPreview document={{ type: "doc", content: [block.rawNode] }} />
              </div>
            );
          }

          return (
            <div key={block.id} className="rounded-lg border bg-card p-3 shadow-sm">
              <div className="mb-2 flex flex-wrap items-center gap-2">
                <label className="sr-only" htmlFor={`block-kind-${block.id}`}>
                  {t("editor.addBlock")}
                </label>
                <select
                  id={`block-kind-${block.id}`}
                  className="h-11 rounded-md border bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  value={block.kind}
                  disabled={disabled}
                  onChange={(event) =>
                    patchBlock(index, { kind: event.target.value as EditorBlockKind })
                  }
                >
                  {kinds.map((kind) => (
                    <option key={kind} value={kind}>
                      {t(`editor.${kind}`)}
                    </option>
                  ))}
                </select>
                {block.kind !== "divider" && !block.kind.endsWith("List") && (
                  <>
                    <Button
                      type="button"
                      size="icon"
                      variant={block.bold ? "secondary" : "ghost"}
                      className="h-11 w-11"
                      disabled={disabled}
                      aria-label={t("editor.bold")}
                      aria-pressed={block.bold}
                      onClick={() => patchBlock(index, { bold: !block.bold })}
                    >
                      <Bold className="h-4 w-4" />
                    </Button>
                    <Button
                      type="button"
                      size="icon"
                      variant={block.italic ? "secondary" : "ghost"}
                      className="h-11 w-11"
                      disabled={disabled}
                      aria-label={t("editor.italic")}
                      aria-pressed={block.italic}
                      onClick={() => patchBlock(index, { italic: !block.italic })}
                    >
                      <Italic className="h-4 w-4" />
                    </Button>
                  </>
                )}
                <div className="ml-auto flex gap-1">
                  <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="h-11 w-11"
                    disabled={disabled || index === 0}
                    aria-label={t("editor.moveUp")}
                    onClick={() => move(index, -1)}
                  >
                    <ArrowUp className="h-4 w-4" />
                  </Button>
                  <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="h-11 w-11"
                    disabled={disabled || index === blocks.length - 1}
                    aria-label={t("editor.moveDown")}
                    onClick={() => move(index, 1)}
                  >
                    <ArrowDown className="h-4 w-4" />
                  </Button>
                  <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    className="h-11 w-11 text-destructive"
                    disabled={disabled}
                    aria-label={t("editor.removeBlock")}
                    onClick={() => update(blocks.filter((_, position) => position !== index))}
                  >
                    <Trash2 className="h-4 w-4" />
                  </Button>
                </div>
              </div>
              {block.kind !== "divider" && (
                <>
                  <label className="sr-only" htmlFor={`block-text-${block.id}`}>
                    {t(`editor.${block.kind}`)}
                  </label>
                  <Textarea
                    id={`block-text-${block.id}`}
                    className={cn(
                      "min-h-24",
                      (block.kind === "h2" || block.kind === "h3") && "font-semibold",
                    )}
                    value={block.text}
                    disabled={disabled}
                    placeholder={
                      block.kind.endsWith("List") ? t("editor.listHelp") : t(`editor.${block.kind}`)
                    }
                    onChange={(event) => patchBlock(index, { text: event.target.value })}
                  />
                  {!block.kind.endsWith("List") && (
                    <div className="mt-2 flex items-center gap-2">
                      <Link2
                        className="h-4 w-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                      />
                      <label className="sr-only" htmlFor={`block-link-${block.id}`}>
                        {t("editor.link")}
                      </label>
                      <Input
                        id={`block-link-${block.id}`}
                        type="url"
                        value={block.link}
                        disabled={disabled}
                        placeholder={t("editor.linkPlaceholder")}
                        onChange={(event) => patchBlock(index, { link: event.target.value })}
                      />
                    </div>
                  )}
                </>
              )}
            </div>
          );
        })}
      </div>
      {error && (
        <p id="document-error" role="alert" className="text-sm font-medium text-destructive">
          {error}
        </p>
      )}
    </div>
  );
}
