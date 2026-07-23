import type { NodeViewProps } from "@tiptap/react";
import { NodeViewWrapper } from "@tiptap/react";
import { ImageOff, Trash2 } from "lucide-react";
import { useCallback, useState } from "react";
import { useTranslation } from "react-i18next";

import { AuthenticatedContentImage } from "@/components/content/authenticated-content-image";
import { Button } from "@/components/ui/button";
import { cn } from "@/lib/utils";

export function ArticleImageNodeView({ node, editor, selected, deleteNode }: NodeViewProps) {
  const { t } = useTranslation("content");
  const [unavailable, setUnavailable] = useState(false);
  const publicId =
    typeof node.attrs.attachment_public_id === "string"
      ? node.attrs.attachment_public_id
      : "";
  const alt = typeof node.attrs.alt === "string" ? node.attrs.alt : "";
  const markUnavailable = useCallback(() => setUnavailable(true), []);

  return (
    <NodeViewWrapper
      as="figure"
      className={cn(
        "group relative my-5 overflow-hidden rounded-xl border bg-muted/30",
        selected && "ring-2 ring-primary ring-offset-2",
      )}
      data-attachment-public-id={publicId}
      data-content-image-reference="true"
    >
      <div className="relative flex min-h-48 items-center justify-center bg-gradient-to-br from-sky-950 via-primary to-cyan-700">
        {!unavailable && publicId ? (
          <AuthenticatedContentImage
            publicId={publicId}
            alt={alt}
            className="max-h-[32rem] w-full object-contain"
            onUnavailable={markUnavailable}
          />
        ) : (
          <div className="flex flex-col items-center gap-2 p-6 text-center text-sm text-white">
            <ImageOff className="h-8 w-8" aria-hidden="true" />
            <span>{t("editor.imageLoadError")}</span>
          </div>
        )}
        {editor.isEditable ? (
          <Button
            aria-label={t("editor.removeImage")}
            className="absolute right-3 top-3 h-11 w-11 bg-background/90 text-destructive opacity-100 shadow-sm sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100"
            onClick={deleteNode}
            size="icon"
            type="button"
            variant="outline"
          >
            <Trash2 className="h-4 w-4" />
          </Button>
        ) : null}
      </div>
      <figcaption className="border-t bg-background px-4 py-2 text-sm text-muted-foreground">
        {alt}
      </figcaption>
    </NodeViewWrapper>
  );
}
