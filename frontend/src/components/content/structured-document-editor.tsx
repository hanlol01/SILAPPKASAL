import { ArrowDown, ArrowUp, Bold, Italic, Link2, Plus, Trash2 } from "lucide-react";
import { useEffect, useRef, useState } from "react";
import { useTranslation } from "react-i18next";

import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Textarea } from "@/components/ui/textarea";
import type { DocumentMark, DocumentNode } from "@/lib/content-management-api";
import { cn } from "@/lib/utils";

type BlockKind =
  | "paragraph"
  | "h2"
  | "h3"
  | "orderedList"
  | "unorderedList"
  | "blockquote"
  | "information"
  | "warning"
  | "help"
  | "divider";

interface EditorBlock {
  id: string;
  kind: BlockKind;
  text: string;
  bold: boolean;
  italic: boolean;
  link: string;
}

interface Props {
  value: DocumentNode | null;
  onChange: (document: DocumentNode) => void;
  faq?: boolean;
  disabled?: boolean;
  error?: string;
}

const articleKinds: BlockKind[] = [
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
const faqKinds: BlockKind[] = ["paragraph", "orderedList", "unorderedList", "blockquote"];

export function StructuredDocumentEditor({
  value,
  onChange,
  faq = false,
  disabled = false,
  error,
}: Props) {
  const { t } = useTranslation("content");
  const [blocks, setBlocks] = useState<EditorBlock[]>(() => documentToBlocks(value));
  const lastEmittedDocument = useRef<DocumentNode | null>(null);
  const kinds = faq ? faqKinds : articleKinds;

  useEffect(() => {
    if (value === lastEmittedDocument.current) return;
    setBlocks(documentToBlocks(value));
  }, [value]);

  const update = (next: EditorBlock[]) => {
    setBlocks(next);
    const document = blocksToDocument(next);
    lastEmittedDocument.current = document;
    onChange(document);
  };

  const add = (kind: BlockKind) => update([...blocks, emptyBlock(kind)]);
  const patchBlock = (index: number, patch: Partial<EditorBlock>) =>
    update(blocks.map((block, position) => (position === index ? { ...block, ...patch } : block)));
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
        {blocks.map((block, index) => (
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
                onChange={(event) => patchBlock(index, { kind: event.target.value as BlockKind })}
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
                    <Link2 className="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
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
        ))}
      </div>
      {error && (
        <p id="document-error" role="alert" className="text-sm font-medium text-destructive">
          {error}
        </p>
      )}
    </div>
  );
}

function blocksToDocument(blocks: EditorBlock[]): DocumentNode {
  return { type: "doc", content: blocks.map(blockToNode) };
}

function blockToNode(block: EditorBlock): DocumentNode {
  if (block.kind === "divider") return { type: "divider" };
  const marks: DocumentMark[] = [];
  if (block.bold) marks.push({ type: "bold" });
  if (block.italic) marks.push({ type: "italic" });
  if (block.link.trim()) marks.push({ type: "link", attrs: { href: block.link.trim() } });
  const text = (value: string): DocumentNode => ({
    type: "text",
    text: value,
    ...(marks.length ? { marks } : {}),
  });

  if (block.kind === "orderedList" || block.kind === "unorderedList") {
    return {
      type: block.kind,
      content: block.text
        .split(/\r?\n/)
        .filter((item) => item.trim())
        .map((item) => ({ type: "listItem", content: [text(item.trim())] })),
    };
  }
  if (block.kind === "h2" || block.kind === "h3") {
    return {
      type: "heading",
      attrs: { level: block.kind === "h2" ? 2 : 3 },
      content: [text(block.text)],
    };
  }
  if (["information", "warning", "help"].includes(block.kind)) {
    return { type: "callout", attrs: { variant: block.kind }, content: [text(block.text)] };
  }
  return { type: block.kind, content: [text(block.text)] };
}

function documentToBlocks(document: DocumentNode | null): EditorBlock[] {
  if (!document?.content) return [];
  return document.content.map((node) => {
    const firstText = findFirstText(node);
    const marks = firstText?.marks ?? [];
    let kind: BlockKind = "paragraph";
    if (node.type === "heading") kind = node.attrs?.level === 3 ? "h3" : "h2";
    else if (node.type === "callout") kind = (node.attrs?.variant as BlockKind) || "information";
    else if (["orderedList", "unorderedList", "blockquote", "divider"].includes(node.type))
      kind = node.type as BlockKind;
    const text = node.type.endsWith("List")
      ? (node.content ?? []).map((item) => collectText(item)).join("\n")
      : collectText(node);
    return {
      id: crypto.randomUUID(),
      kind,
      text,
      bold: marks.some((mark) => mark.type === "bold"),
      italic: marks.some((mark) => mark.type === "italic"),
      link: marks.find((mark) => mark.type === "link")?.attrs?.href ?? "",
    };
  });
}

function emptyBlock(kind: BlockKind): EditorBlock {
  return { id: crypto.randomUUID(), kind, text: "", bold: false, italic: false, link: "" };
}

function findFirstText(node: DocumentNode): DocumentNode | undefined {
  if (node.type === "text") return node;
  for (const child of node.content ?? []) {
    const found = findFirstText(child);
    if (found) return found;
  }
}

function collectText(node: DocumentNode): string {
  if (node.type === "text") return node.text ?? "";
  return (node.content ?? []).map(collectText).join("");
}
