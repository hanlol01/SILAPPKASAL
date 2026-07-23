import type { DocumentNode } from "@/lib/content-management-api";

export function documentHasText(document: DocumentNode | null): boolean {
  return Boolean(
    document?.content?.some(
      (node) =>
        node.type === "divider" || node.type === "horizontalRule" || collectText(node).trim(),
    ),
  );
}

export function documentHasUnsafeLink(document: DocumentNode | null): boolean {
  const visit = (node: DocumentNode): boolean => {
    for (const mark of node.marks ?? []) {
      if (mark.type === "link" && normalizeSafeContentLink(mark.attrs?.href ?? "") === null) {
        return true;
      }
    }
    return (node.content ?? []).some(visit);
  };
  return document ? visit(document) : false;
}

export function normalizeSafeContentLink(value: string): string | null {
  const normalized = value.trim();
  if (
    normalized.length === 0 ||
    normalized.length > 2048 ||
    normalized.startsWith("//") ||
    [...normalized].some((character) => {
      const code = character.codePointAt(0) ?? 0;
      return code < 32 || code === 127;
    })
  ) {
    return null;
  }

  const scheme = normalized.match(/^([a-z][a-z0-9+.-]*):/iu)?.[1]?.toLowerCase();
  if (!scheme || !["http", "https", "mailto", "tel"].includes(scheme)) return null;

  if (scheme === "mailto") {
    const address = normalized.slice(normalized.indexOf(":") + 1);
    return /^[^@\s:?]+@[^@\s:?]+\.[^@\s:?]+$/u.test(address)
      ? `mailto:${address}`
      : null;
  }

  if (scheme === "tel") {
    const number = normalized.slice(normalized.indexOf(":") + 1);
    const match = number.match(
      /^(\+?[0-9](?:[0-9(). -]{1,28}[0-9])?)(;ext=[0-9]{1,10})?$/u,
    );
    return match ? `tel:${match[1]?.replace(/[(). -]/gu, "")}${match[2] ?? ""}` : null;
  }

  try {
    const url = new URL(normalized);
    return url.protocol.toLowerCase() === `${scheme}:` && Boolean(url.hostname)
      ? `${scheme}:${normalized.slice(normalized.indexOf(":") + 1)}`
      : null;
  } catch {
    return null;
  }
}

function collectText(node: DocumentNode): string {
  if (node.type === "text") return node.text ?? "";
  return (node.content ?? []).map(collectText).join("");
}
