import type { Extensions } from "@tiptap/core";
import { ReactNodeViewRenderer } from "@tiptap/react";

import { ArticleImageNodeView } from "@/components/content/article-image-node-view";
import {
  articleEditorCoreExtensions,
  ImageReference,
} from "@/components/content/article-editor-extensions";

const AuthorableImageReference = ImageReference.extend({
  addNodeView() {
    return ReactNodeViewRenderer(ArticleImageNodeView);
  },
});

export const articleEditorMediaExtensions: Extensions = [
  ...articleEditorCoreExtensions,
  AuthorableImageReference,
];
