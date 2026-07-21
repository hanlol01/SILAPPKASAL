import type { DocumentNode } from "@/lib/content-management-api";

export function documentHasText(document: DocumentNode | null): boolean {
  return Boolean(
    document?.content?.some((node) => node.type === "divider" || collectText(node).trim()),
  );
}

export function documentHasUnsafeLink(document: DocumentNode | null): boolean {
  const visit = (node: DocumentNode): boolean => {
    for (const mark of node.marks ?? []) {
      if (mark.type === "link" && !isSafeLink(mark.attrs?.href ?? "")) return true;
    }
    return (node.content ?? []).some(visit);
  };
  return document ? visit(document) : false;
}

function collectText(node: DocumentNode): string {
  if (node.type === "text") return node.text ?? "";
  return (node.content ?? []).map(collectText).join("");
}

function isSafeLink(value: string): boolean {
  try {
    const url = new URL(value);
    return (
      url.protocol === "http:" ||
      url.protocol === "https:" ||
      (url.protocol === "mailto:" && /^mailto:[^@\s]+@[^@\s]+\.[^@\s]+$/i.test(value))
    );
  } catch {
    return false;
  }
}
