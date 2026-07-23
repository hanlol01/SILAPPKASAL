import type { JSONContent } from "@tiptap/core";

import { normalizeSafeContentLink } from "./content-document.ts";
import type { DocumentMark, DocumentNode } from "@/lib/content-management-api";

const ARTICLE_NODE_TYPES = new Set([
  "doc",
  "text",
  "paragraph",
  "heading",
  "heading_2",
  "heading_3",
  "orderedList",
  "unorderedList",
  "bulletList",
  "listItem",
  "blockquote",
  "callout",
  "info",
  "warning",
  "help",
  "divider",
  "horizontalRule",
  "imageReference",
]);

const ARTICLE_MARK_TYPES = new Set<DocumentMark["type"]>([
  "bold",
  "italic",
  "underline",
  "link",
]);

const INLINE_NODE_TYPES = new Set(["text"]);

export const ARTICLE_DOCUMENT_LIMITS = {
  maxPayloadBytes: 500_000,
  maxNodes: 1_000,
  maxDepth: 12,
  maxTotalText: 200_000,
  maxTextNode: 20_000,
  maxMarksPerTextNode: 4,
  maxTotalMarks: 2_000,
  maxLinkLength: 2_048,
  maxLinkTitleLength: 255,
  maxImageAltLength: 500,
} as const;

export type ArticleDocumentFailureKind = "unsupported_shape" | "resource_limit" | null;

export interface ArticleDocumentCompatibility {
  document: DocumentNode;
  unsupported: string[];
  failureKind: ArticleDocumentFailureKind;
}

class ArticleDocumentNormalizationLimitError extends Error {
  readonly diagnostic: string;

  constructor(diagnostic: string) {
    super(diagnostic);
    this.name = "ArticleDocumentNormalizationLimitError";
    this.diagnostic = diagnostic;
  }
}

interface ArticleDocumentNormalizationBudget {
  nodes: number;
}

export function prepareArticleDocumentForTiptap(
  document: DocumentNode | null,
): ArticleDocumentCompatibility {
  if (!document) {
    return {
      document: emptyArticleDocument(),
      unsupported: [],
      failureKind: null,
    };
  }

  const inspection = inspectArticleDocument(document);
  if (inspection.resourceLimits.length > 0) {
    return {
      document,
      unsupported: inspection.resourceLimits,
      failureKind: "resource_limit",
    };
  }
  if (inspection.unsupported.length > 0) {
    return {
      document,
      unsupported: inspection.unsupported,
      failureKind: "unsupported_shape",
    };
  }

  let normalized: DocumentNode;
  try {
    normalized = normalizeForTiptap(document, { nodes: 0 });
  } catch (error) {
    if (error instanceof ArticleDocumentNormalizationLimitError) {
      return {
        document,
        unsupported: [error.diagnostic],
        failureKind: "resource_limit",
      };
    }

    throw error;
  }

  const canonicalInspection = inspectArticleDocument(normalized);
  if (canonicalInspection.resourceLimits.length > 0) {
    return {
      document,
      unsupported: canonicalInspection.resourceLimits,
      failureKind: "resource_limit",
    };
  }
  if (canonicalInspection.unsupported.length > 0) {
    return {
      document,
      unsupported: canonicalInspection.unsupported,
      failureKind: "unsupported_shape",
    };
  }

  return {
    document: normalized,
    unsupported: [],
    failureKind: null,
  };
}

export function articleDocumentFromTiptap(
  content: JSONContent,
  authorizedImageReferences: ReadonlyMap<string, DocumentNode> = new Map(),
): DocumentNode {
  const document = canonicalizeNode(content, true, authorizedImageReferences);
  const inspection = inspectArticleDocument(document);
  if (inspection.resourceLimits.length > 0 || inspection.unsupported.length > 0) {
    throw new Error("The Article editor produced an invalid controlled document.");
  }

  return document;
}

export function emptyArticleDocument(): DocumentNode {
  return {
    type: "doc",
    content: [{ type: "paragraph" }],
  };
}

export function documentSignature(document: DocumentNode): string {
  return JSON.stringify(document);
}

export function collectAuthorizedImageReferences(
  document: DocumentNode,
): ReadonlyMap<string, DocumentNode> {
  const references = new Map<string, DocumentNode>();
  const stack: DocumentNode[] = [document];

  while (stack.length > 0) {
    const node = stack.pop();
    if (!node || typeof node !== "object" || Array.isArray(node)) continue;
    if (node.type === "imageReference") {
      references.set(imageReferenceSignature(node.attrs), {
        type: "imageReference",
        ...(node.attrs ? { attrs: { ...node.attrs } } : {}),
      });
    }
    if (!Array.isArray(node.content)) continue;
    for (let index = node.content.length - 1; index >= 0; index -= 1) {
      const child = node.content[index];
      if (child) stack.push(child);
    }
  }

  return references;
}

/**
 * Compatibility export for callers that still describe pre-REV-MEDIA-01
 * references as legacy. Authorization is now based on server-projected or
 * freshly uploaded references, never arbitrary editor JSON.
 */
export const collectLegacyImageReferences = collectAuthorizedImageReferences;

export function countArticleWords(value: string): number {
  return value.match(/[\p{L}\p{N}]+/gu)?.length ?? 0;
}

function inspectArticleDocument(document: DocumentNode): {
  unsupported: string[];
  resourceLimits: string[];
} {
  const unsupported = new Set<string>();
  const resourceLimits = new Set<string>();
  let serialized: string;
  try {
    serialized = JSON.stringify(document);
  } catch {
    return {
      unsupported: [],
      resourceLimits: ["document:serialization-limit"],
    };
  }
  if (phpJsonEncodedByteLength(serialized) > ARTICLE_DOCUMENT_LIMITS.maxPayloadBytes) {
    return {
      unsupported: [],
      resourceLimits: ["document:payload-limit"],
    };
  }

  const stack: Array<{
    node: DocumentNode | null | undefined;
    path: string;
    depth: number;
  }> = [{ node: document, path: "document", depth: 0 }];
  const textParts: string[] = [];
  let nodeCount = 0;
  let markCount = 0;

  while (stack.length > 0) {
    const current = stack.pop();
    if (!current) continue;
    const { node, path, depth } = current;

    if (!node || typeof node !== "object" || Array.isArray(node)) {
      unsupported.add(`${path}:invalid-node`);
      continue;
    }
    if (depth > 0) {
      nodeCount += 1;
      if (nodeCount > ARTICLE_DOCUMENT_LIMITS.maxNodes) {
        resourceLimits.add("document:node-limit");
      }
    }
    if (depth > ARTICLE_DOCUMENT_LIMITS.maxDepth) {
      resourceLimits.add("document:depth-limit");
      continue;
    }
    if (!ARTICLE_NODE_TYPES.has(node.type)) {
      unsupported.add(`${path}:${node.type || "unknown"}`);
      continue;
    }

    if (path === "document" && node.type !== "doc") {
      unsupported.add(`${path}:expected-doc`);
    } else if (path !== "document" && node.type === "doc") {
      unsupported.add(`${path}:nested-doc`);
    }

    const allowedNodeKeys =
      node.type === "text"
        ? ["type", "text", "marks"]
        : node.type === "heading" || node.type === "callout"
          ? ["type", "attrs", "content"]
          : node.type === "imageReference"
            ? ["type", "attrs"]
            : node.type === "divider" || node.type === "horizontalRule"
              ? ["type"]
              : ["type", "content"];
    if (hasUnexpectedKeys(node, allowedNodeKeys)) {
      unsupported.add(`${path}:unexpected-fields`);
    }

    if (node.type === "text") {
      if (typeof node.text !== "string") {
        unsupported.add(`${path}:text-value`);
      } else {
        textParts.push(node.text);
        if (codePointLength(node.text) > ARTICLE_DOCUMENT_LIMITS.maxTextNode) {
          resourceLimits.add("document:text-node-limit");
        }
      }
    } else if (node.type === "heading" && ![2, 3].includes(Number(node.attrs?.level))) {
      unsupported.add(`${path}:heading-level`);
    } else if (
      node.type === "callout" &&
      !["information", "warning", "help"].includes(String(node.attrs?.variant))
    ) {
      unsupported.add(`${path}:callout-variant`);
    } else if (
      node.type === "imageReference" &&
      (typeof node.attrs?.attachment_public_id !== "string" ||
        !/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/iu.test(
          node.attrs.attachment_public_id,
        ) ||
        typeof node.attrs.alt !== "string" ||
        node.attrs.alt.trim() === "" ||
        codePointLength(node.attrs.alt.trim()) > ARTICLE_DOCUMENT_LIMITS.maxImageAltLength)
    ) {
      unsupported.add(`${path}:image-reference`);
    }

    if (node.type === "heading" && hasUnexpectedKeys(node.attrs ?? {}, ["level"])) {
      unsupported.add(`${path}:heading-attributes`);
    }
    if (node.type === "callout" && hasUnexpectedKeys(node.attrs ?? {}, ["variant"])) {
      unsupported.add(`${path}:callout-attributes`);
    }
    if (
      node.type === "imageReference" &&
      hasUnexpectedKeys(node.attrs ?? {}, ["attachment_public_id", "alt"])
    ) {
      unsupported.add(`${path}:image-attributes`);
    }

    if (node.marks !== undefined && !Array.isArray(node.marks)) {
      unsupported.add(`${path}:marks`);
    }
    const marks = Array.isArray(node.marks) ? node.marks : [];
    if (marks.length > ARTICLE_DOCUMENT_LIMITS.maxMarksPerTextNode) {
      resourceLimits.add("document:marks-per-node-limit");
    }
    markCount += marks.length;
    if (markCount > ARTICLE_DOCUMENT_LIMITS.maxTotalMarks) {
      resourceLimits.add("document:mark-limit");
    }
    for (const [markIndex, mark] of marks.entries()) {
      if (!mark || typeof mark !== "object" || Array.isArray(mark)) {
        unsupported.add(`${path}.marks[${markIndex}]:invalid-mark`);
        continue;
      }
      if (!ARTICLE_MARK_TYPES.has(mark.type)) {
        unsupported.add(`${path}.marks[${markIndex}]:${mark.type}`);
        continue;
      }

      const markPath = `${path}.marks[${markIndex}]`;
      if (mark.type === "link") {
        if (
          hasUnexpectedKeys(mark, ["type", "attrs"]) ||
          hasUnexpectedKeys(mark.attrs ?? {}, ["href", "title"]) ||
          typeof mark.attrs?.href !== "string" ||
          normalizeSafeContentLink(mark.attrs.href) === null
        ) {
          unsupported.add(`${markPath}:link`);
        }
        if (
          typeof mark.attrs?.href === "string" &&
          codePointLength(mark.attrs.href) > ARTICLE_DOCUMENT_LIMITS.maxLinkLength
        ) {
          resourceLimits.add("document:link-length-limit");
        }
        if (
          typeof mark.attrs?.title === "string" &&
          codePointLength(mark.attrs.title) > ARTICLE_DOCUMENT_LIMITS.maxLinkTitleLength
        ) {
          resourceLimits.add("document:link-title-limit");
        }
      } else if (hasUnexpectedKeys(mark, ["type"])) {
        unsupported.add(`${markPath}:unexpected-fields`);
      }
    }

    if (node.content !== undefined && !Array.isArray(node.content)) {
      unsupported.add(`${path}:content`);
      continue;
    }

    const content = node.content ?? [];
    for (let index = content.length - 1; index >= 0; index -= 1) {
      stack.push({
        node: content[index],
        path: `${path}.content[${index}]`,
        depth: depth + 1,
      });
    }
  }

  const normalizedText = textParts.join(" ").replace(/\s+/gu, " ").trim();
  if (codePointLength(normalizedText) > ARTICLE_DOCUMENT_LIMITS.maxTotalText) {
    resourceLimits.add("document:text-limit");
  }

  return {
    unsupported: [...unsupported],
    resourceLimits: [...resourceLimits],
  };
}

function hasUnexpectedKeys(value: object, allowed: readonly string[]): boolean {
  return Object.keys(value).some((key) => !allowed.includes(key));
}

function normalizeForTiptap(
  node: DocumentNode,
  budget: ArticleDocumentNormalizationBudget,
  depth = 0,
): DocumentNode {
  claimNormalizationNode(budget, depth);

  const normalizedType =
    node.type === "unorderedList"
      ? "bulletList"
      : node.type === "divider"
        ? "horizontalRule"
        : node.type === "heading_2" || node.type === "heading_3"
          ? "heading"
          : node.type === "info" || node.type === "warning" || node.type === "help"
          ? "callout"
            : node.type;
  let content = (node.content ?? []).map((child) =>
    normalizeForTiptap(child, budget, depth + 1),
  );

  if (
    normalizedType === "listItem" ||
    normalizedType === "blockquote" ||
    normalizedType === "callout"
  ) {
    content = wrapInlineRuns(content, budget, depth);
  }

  const normalized: DocumentNode = {
    type: normalizedType,
  };

  if (node.text !== undefined) normalized.text = node.text;
  if (node.marks?.length) normalized.marks = node.marks.map(normalizeMark);
  const normalizedAttrs =
    node.type === "heading_2"
      ? { level: 2 }
      : node.type === "heading_3"
        ? { level: 3 }
        : node.type === "info"
          ? { variant: "information" }
          : node.type === "warning" || node.type === "help"
            ? { variant: node.type }
            : normalizeNodeAttrs(normalizedType, node.attrs);
  if (Object.keys(normalizedAttrs).length) normalized.attrs = normalizedAttrs;
  if (content.length) normalized.content = content;

  return normalized;
}

function claimNormalizationNode(
  budget: ArticleDocumentNormalizationBudget,
  depth: number,
): void {
  if (depth > ARTICLE_DOCUMENT_LIMITS.maxDepth) {
    throw new ArticleDocumentNormalizationLimitError("document:depth-limit");
  }
  if (depth === 0) return;

  budget.nodes += 1;
  if (budget.nodes > ARTICLE_DOCUMENT_LIMITS.maxNodes) {
    throw new ArticleDocumentNormalizationLimitError("document:node-limit");
  }
}

function wrapInlineRuns(
  content: DocumentNode[],
  budget: ArticleDocumentNormalizationBudget,
  parentDepth: number,
): DocumentNode[] {
  const result: DocumentNode[] = [];
  let inlineRun: DocumentNode[] = [];

  const flush = () => {
    if (inlineRun.length === 0) return;
    claimNormalizationNode(budget, parentDepth + 1);
    if (parentDepth + 2 > ARTICLE_DOCUMENT_LIMITS.maxDepth) {
      throw new ArticleDocumentNormalizationLimitError("document:depth-limit");
    }
    result.push({ type: "paragraph", content: inlineRun });
    inlineRun = [];
  };

  for (const child of content) {
    if (INLINE_NODE_TYPES.has(child.type)) {
      inlineRun.push(child);
    } else {
      flush();
      result.push(child);
    }
  }

  flush();

  return result;
}

function canonicalizeNode(
  node: JSONContent,
  root = false,
  authorizedImageReferences: ReadonlyMap<string, DocumentNode> = new Map(),
): DocumentNode {
  const type = node.type ?? (root ? "doc" : "");
  const canonical: DocumentNode = {
    type,
  };

  if (typeof node.text === "string") canonical.text = node.text;

  const marks = (node.marks ?? [])
    .map((mark) => canonicalizeMark(mark))
    .filter((mark): mark is DocumentMark => mark !== null);
  if (marks.length) canonical.marks = marks;

  const attrs = normalizeNodeAttrs(type, node.attrs ?? undefined);
  if (Object.keys(attrs).length) canonical.attrs = attrs;
  if (type === "imageReference") {
    const authorizedReference = authorizedImageReferences.get(
      imageReferenceSignature(canonical.attrs),
    );
    if (!authorizedReference) {
      throw new Error("Image references require a server-authorized attachment.");
    }

    return {
      type: "imageReference",
      ...(authorizedReference.attrs ? { attrs: { ...authorizedReference.attrs } } : {}),
    };
  }

  const content = (node.content ?? []).map((child) =>
    canonicalizeNode(child, false, authorizedImageReferences),
  );
  if (content.length) canonical.content = content;

  return canonical;
}

function canonicalizeMark(mark: JSONContent): DocumentMark | null {
  if (mark.type === "bold" || mark.type === "italic" || mark.type === "underline") {
    return { type: mark.type };
  }

  if (mark.type !== "link" || typeof mark.attrs?.href !== "string") {
    return null;
  }

  const href = normalizeSafeContentLink(mark.attrs.href);
  if (href === null) return null;
  const attrs: NonNullable<DocumentMark["attrs"]> = {
    href,
  };
  if (typeof mark.attrs.title === "string" && mark.attrs.title !== "") {
    attrs.title = mark.attrs.title;
  }

  return {
    type: "link",
    attrs,
  };
}

function normalizeMark(mark: DocumentMark): DocumentMark {
  if (mark.type !== "link") return { type: mark.type };

  const attrs: NonNullable<DocumentMark["attrs"]> = {};
  if (typeof mark.attrs?.href === "string") {
    const href = normalizeSafeContentLink(mark.attrs.href);
    if (href !== null) attrs.href = href;
  }
  if (typeof mark.attrs?.title === "string" && mark.attrs.title !== "") {
    attrs.title = mark.attrs.title;
  }

  return {
    type: "link",
    attrs,
  };
}

function normalizeNodeAttrs(
  type: string,
  attrs?: Record<string, unknown>,
): NonNullable<DocumentNode["attrs"]> {
  if (type === "heading" && (attrs?.level === 2 || attrs?.level === 3)) {
    return { level: attrs.level };
  }

  if (
    type === "callout" &&
    (attrs?.variant === "information" || attrs?.variant === "warning" || attrs?.variant === "help")
  ) {
    return { variant: attrs.variant };
  }

  if (type === "imageReference" && typeof attrs?.attachment_public_id === "string") {
    const imageAttrs: NonNullable<DocumentNode["attrs"]> = {
      attachment_public_id: attrs.attachment_public_id,
    };
    if (typeof attrs.alt === "string") imageAttrs.alt = attrs.alt.trim();
    return imageAttrs;
  }

  return {};
}

function imageReferenceSignature(attrs?: Record<string, unknown>): string {
  return JSON.stringify([
    typeof attrs?.attachment_public_id === "string" ? attrs.attachment_public_id : null,
    typeof attrs?.alt === "string" ? attrs.alt.trim() : "",
  ]);
}

function codePointLength(value: string): number {
  return [...value].length;
}

/**
 * PHP json_encode escapes slashes and non-ASCII code points by default.
 * Count that representation so the frontend cannot admit a Unicode-heavy
 * payload that the backend rejects at the same 500,000-byte boundary.
 */
function phpJsonEncodedByteLength(serialized: string): number {
  let bytes = 0;
  for (const character of serialized) {
    const codePoint = character.codePointAt(0) ?? 0;
    if (character === "/") {
      bytes += 2;
    } else if (codePoint > 0x7f) {
      bytes += codePoint <= 0xffff ? 6 : 12;
    } else {
      bytes += 1;
    }
  }

  return bytes;
}
