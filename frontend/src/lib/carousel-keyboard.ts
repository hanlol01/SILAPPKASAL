export type CarouselKeyboardAction = "previous" | "next" | null;

export function carouselActionForKey(key: string): CarouselKeyboardAction {
  if (key === "ArrowLeft") return "previous";
  if (key === "ArrowRight") return "next";
  return null;
}
