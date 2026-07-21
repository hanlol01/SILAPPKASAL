export type InformationCenterView = "articles" | "faq" | "consultation";

export interface InformationCenterSearch {
  view?: InformationCenterView;
  section?: "education" | "policy";
  category?: string;
  q?: string;
  page?: number;
  open?: string;
}

export interface ReaderCategoryContext {
  public_id: string;
  section_code?: string | null;
}

const UUID_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
const ALLOWED_KEYS = new Set(["view", "section", "category", "q", "page", "open"]);

function isPublicId(value: unknown): value is string {
  return typeof value === "string" && value.length === 36 && UUID_PATTERN.test(value);
}

export function normalizeInformationCenterSearch(
  raw: Record<string, unknown>,
): InformationCenterSearch {
  const view = raw.view === "faq" || raw.view === "consultation" ? raw.view : undefined;
  const section =
    view === undefined && (raw.section === "education" || raw.section === "policy")
      ? raw.section
      : undefined;
  const query = typeof raw.q === "string" ? raw.q.trim().slice(0, 100) : "";
  const parsedPage = Number(raw.page);

  return {
    view,
    section,
    category:
      view !== "consultation" && isPublicId(raw.category) ? raw.category.toLowerCase() : undefined,
    q: view !== "consultation" && query ? query : undefined,
    page:
      view !== "consultation" && Number.isInteger(parsedPage) && parsedPage > 1
        ? parsedPage
        : undefined,
    open: view === "faq" && isPublicId(raw.open) ? raw.open.toLowerCase() : undefined,
  };
}

export function mergeInformationCenterSearch(
  current: InformationCenterSearch,
  next: Partial<InformationCenterSearch>,
): InformationCenterSearch {
  return normalizeInformationCenterSearch({ ...current, ...next });
}

export function informationCenterSearchNeedsNormalization(
  searchString: string,
  normalized: InformationCenterSearch,
): boolean {
  const params = new URLSearchParams(
    searchString.startsWith("?") ? searchString.slice(1) : searchString,
  );
  const expected = new Map<string, string>();

  for (const [key, value] of Object.entries(normalized)) {
    if (value !== undefined) expected.set(key, String(value));
  }

  const seen = new Set<string>();
  for (const [key, value] of params.entries()) {
    if (!ALLOWED_KEYS.has(key) || seen.has(key) || expected.get(key) !== value) return true;
    seen.add(key);
  }

  return seen.size !== expected.size;
}

export function categoryBelongsToReaderContext(
  category: ReaderCategoryContext | undefined,
  view: InformationCenterView,
  section?: string,
): boolean {
  if (!category) return false;
  if (view === "faq") return category.section_code === "faq";
  if (view !== "articles") return false;
  return section
    ? category.section_code === section
    : category.section_code === "education" || category.section_code === "policy";
}
