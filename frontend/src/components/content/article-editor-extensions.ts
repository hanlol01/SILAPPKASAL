import { getSchema, Node, mergeAttributes, type Extensions } from "@tiptap/core";
import Link from "@tiptap/extension-link";
import StarterKit from "@tiptap/starter-kit";

const Callout = Node.create({
  name: "callout",
  group: "block",
  content: "block+",
  defining: true,

  addAttributes() {
    return {
      variant: {
        default: "information",
        parseHTML: (element) => {
          const value =
            element.getAttribute("data-callout") ?? element.getAttribute("data-content-callout");
          return value === "warning" || value === "help" ? value : "information";
        },
      },
    };
  },

  parseHTML() {
    return [
      {
        tag: "aside[data-callout]",
      },
      {
        tag: "aside[data-content-callout]",
      },
    ];
  },

  renderHTML({ HTMLAttributes }) {
    const { variant, ...attributes } = HTMLAttributes;
    return [
      "aside",
      mergeAttributes(attributes, {
        "data-content-callout": variant,
      }),
      0,
    ];
  },
});

const HorizontalRule = Node.create({
  name: "horizontalRule",
  group: "block",
  atom: true,
  selectable: false,

  parseHTML() {
    return [{ tag: "hr" }];
  },

  renderHTML() {
    return ["hr", { "data-content-divider": "true" }];
  },
});

export const ImageReference = Node.create({
  name: "imageReference",
  group: "block",
  atom: true,
  isolating: true,
  selectable: true,
  draggable: false,

  addAttributes() {
    return {
      attachment_public_id: {
        default: null,
      },
      alt: {
        default: "",
      },
    };
  },

  renderHTML({ node }) {
    const alt = typeof node.attrs.alt === "string" ? node.attrs.alt : "";
    return [
      "figure",
      {
        "data-attachment-public-id": node.attrs.attachment_public_id,
        "data-alt": alt,
        "data-content-image-reference": "true",
        contenteditable: "false",
      },
      ["figcaption", {}, alt],
    ];
  },
});

const SafeLink = Link.extend({
  addAttributes() {
    return {
      ...this.parent?.(),
      title: {
        default: null,
        parseHTML: (element) => element.getAttribute("title"),
      },
    };
  },
}).configure({
  autolink: false,
  linkOnPaste: false,
  openOnClick: false,
  enableClickSelection: true,
  protocols: ["http", "https", "mailto", "tel"],
  HTMLAttributes: {
    rel: "noopener noreferrer",
    target: "_blank",
  },
  shouldAutoLink: () => false,
});

export const articleEditorCoreExtensions: Extensions = [
  StarterKit.configure({
    code: false,
    codeBlock: false,
    hardBreak: false,
    heading: {
      levels: [2, 3],
    },
    horizontalRule: false,
    link: false,
    strike: false,
    trailingNode: false,
    underline: {},
  }),
  SafeLink,
  Callout,
  HorizontalRule,
];

export const articleEditorExtensions: Extensions = [
  ...articleEditorCoreExtensions,
  ImageReference,
];

export const articleEditorSchema = getSchema(articleEditorExtensions);
