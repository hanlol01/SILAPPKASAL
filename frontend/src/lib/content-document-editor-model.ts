export interface DocumentMark {
  type: "bold" | "italic" | "link";
  attrs?: { href?: string; title?: string };
}

export interface DocumentNode {
  type: string;
  attrs?: Record<string, string | number | boolean | null>;
  content?: DocumentNode[];
  text?: string;
  marks?: DocumentMark[];
}

export type EditorBlockKind =
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

export interface StructuredEditorBlock {
  id: string;
  kind: EditorBlockKind;
  text: string;
  bold: boolean;
  italic: boolean;
  link: string;
  rawNode: DocumentNode | null;
  readOnly: boolean;
  modified: boolean;
}

export function documentToEditorBlocks(document: DocumentNode | null): StructuredEditorBlock[] {
  return (document?.content ?? []).map(nodeToBlock);
}

export function editorBlocksToDocument(blocks: StructuredEditorBlock[]): DocumentNode {
  return { type: "doc", content: blocks.map(blockToNode) };
}

export function emptyEditorBlock(kind: EditorBlockKind): StructuredEditorBlock {
  return {
    id: crypto.randomUUID(),
    kind,
    text: "",
    bold: false,
    italic: false,
    link: "",
    rawNode: null,
    readOnly: false,
    modified: true,
  };
}

function nodeToBlock(node: DocumentNode): StructuredEditorBlock {
  const rawNode = cloneNode(node);
  const fallback: StructuredEditorBlock = {
    id: crypto.randomUUID(),
    kind: "paragraph",
    text: collectText(node),
    bold: false,
    italic: false,
    link: "",
    rawNode,
    readOnly: true,
    modified: false,
  };

  if (node.type === "divider") {
    return { ...fallback, kind: "divider", readOnly: false };
  }
  if (node.type === "imageReference") return fallback;

  if (node.type === "orderedList" || node.type === "unorderedList" || node.type === "bulletList") {
    const listText = simpleListText(node);
    if (listText === null) return fallback;

    return {
      ...fallback,
      kind: node.type === "orderedList" ? "orderedList" : "unorderedList",
      text: listText,
      readOnly: false,
    };
  }

  const textNode = simpleTextNode(node);
  if (!textNode) return fallback;
  const marks = parseEditableMarks(textNode.marks ?? []);
  if (!marks) return fallback;

  const kind = editableKind(node);
  if (!kind) return fallback;

  return {
    ...fallback,
    kind,
    text: textNode.text ?? "",
    bold: marks.bold,
    italic: marks.italic,
    link: marks.link,
    readOnly: false,
  };
}

function blockToNode(block: StructuredEditorBlock): DocumentNode {
  if (!block.modified && block.rawNode) return cloneNode(block.rawNode);
  if (block.readOnly && block.rawNode) return cloneNode(block.rawNode);
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

function editableKind(node: DocumentNode): EditorBlockKind | null {
  if (node.type === "paragraph" || node.type === "blockquote") return node.type;
  if (node.type === "heading") {
    if (node.attrs?.level === 2) return "h2";
    if (node.attrs?.level === 3) return "h3";
    return null;
  }
  if (node.type === "callout") {
    const variant = node.attrs?.variant;
    return variant === "information" || variant === "warning" || variant === "help"
      ? variant
      : null;
  }

  return null;
}

function simpleTextNode(node: DocumentNode): DocumentNode | null {
  if (node.content?.length !== 1 || node.content[0]?.type !== "text") return null;
  return node.content[0];
}

function simpleListText(node: DocumentNode): string | null {
  const lines: string[] = [];
  for (const item of node.content ?? []) {
    if (item.type !== "listItem" || item.content?.length !== 1) return null;
    const textNode = item.content[0];
    if (textNode.type !== "text" || (textNode.marks?.length ?? 0) > 0) return null;
    lines.push(textNode.text ?? "");
  }

  return lines.join("\n");
}

function parseEditableMarks(marks: DocumentMark[]): {
  bold: boolean;
  italic: boolean;
  link: string;
} | null {
  let bold = false;
  let italic = false;
  let link = "";
  const seen = new Set<string>();

  for (const mark of marks) {
    if (seen.has(mark.type)) return null;
    seen.add(mark.type);
    if (mark.type === "bold") bold = true;
    else if (mark.type === "italic") italic = true;
    else if (mark.type === "link") {
      if (!mark.attrs?.href || mark.attrs.title) return null;
      link = mark.attrs.href;
    } else return null;
  }

  return { bold, italic, link };
}

function collectText(node: DocumentNode): string {
  if (node.type === "text") return node.text ?? "";
  return (node.content ?? []).map(collectText).join("");
}

function cloneNode(node: DocumentNode): DocumentNode {
  return structuredClone(node);
}
