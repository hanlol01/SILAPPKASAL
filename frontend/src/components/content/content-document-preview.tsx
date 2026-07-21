import type { DocumentNode } from "@/lib/content-management-api";

export function ContentDocumentPreview({ document }: { document: DocumentNode | null }) {
  return (
    <div className="prose prose-slate max-w-none dark:prose-invert">
      {document?.content?.map((node, index) => (
        <PreviewNode key={index} node={node} />
      ))}
    </div>
  );
}

function PreviewNode({ node }: { node: DocumentNode }) {
  if (node.type === "text") {
    let content: React.ReactNode = node.text ?? "";
    for (const mark of node.marks ?? []) {
      if (mark.type === "bold") content = <strong>{content}</strong>;
      if (mark.type === "italic") content = <em>{content}</em>;
      if (mark.type === "link" && safeHref(mark.attrs?.href))
        content = (
          <a href={mark.attrs?.href} target="_blank" rel="noreferrer noopener">
            {content}
          </a>
        );
    }
    return <>{content}</>;
  }
  const children = node.content?.map((child, index) => <PreviewNode key={index} node={child} />);
  if (node.type === "paragraph") return <p>{children}</p>;
  if (node.type === "heading")
    return node.attrs?.level === 3 ? <h3>{children}</h3> : <h2>{children}</h2>;
  if (node.type === "orderedList") return <ol>{children}</ol>;
  if (node.type === "unorderedList" || node.type === "bulletList") return <ul>{children}</ul>;
  if (node.type === "listItem") return <li>{children}</li>;
  if (node.type === "blockquote") return <blockquote>{children}</blockquote>;
  if (node.type === "divider") return <hr />;
  if (node.type === "callout")
    return (
      <aside
        className="my-4 rounded-lg border-l-4 border-primary bg-muted p-4"
        data-callout={String(node.attrs?.variant ?? "information")}
      >
        {children}
      </aside>
    );
  return null;
}

function safeHref(value?: string): boolean {
  if (!value) return false;
  try {
    const url = new URL(value);
    return url.protocol === "http:" || url.protocol === "https:" || url.protocol === "mailto:";
  } catch {
    return false;
  }
}
