interface ArticleCategoryDetail {
  category_name?: string | null;
  category?: {
    public_id: string;
    name: string;
  } | null;
}

export interface ArticleCategoryEditorState {
  categoryName: string;
  categoryPublicId: string | null;
}

export function articleCategoryEditorState(
  detail?: ArticleCategoryDetail,
): ArticleCategoryEditorState {
  const hasCanonicalName =
    detail?.category_name !== null && detail?.category_name !== undefined;

  return {
    categoryName: detail?.category_name ?? detail?.category?.name ?? "",
    categoryPublicId: hasCanonicalName ? null : (detail?.category?.public_id ?? null),
  };
}

export function articleCategoryWriteFields(
  categoryName: string,
  legacyCategoryPublicId: string | null,
) {
  const canonicalName = categoryName.trim();

  return {
    category_name: canonicalName,
    category_public_id: canonicalName ? null : legacyCategoryPublicId || null,
  };
}
