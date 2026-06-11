import { useEffect, useState } from "react";
import { getLocalStorageItem, setLocalStorageItem } from "@/lib/auth-storage";

const KEY = "safecampus_theme";

export function useTheme() {
  const [theme, setTheme] = useState<"light" | "dark">("light");

  useEffect(() => {
    if (typeof window === "undefined") return;
    const stored = (getLocalStorageItem(KEY) as "light" | "dark" | null) ?? "light";
    setTheme(stored);
    document.documentElement.classList.toggle("dark", stored === "dark");
  }, []);

  const toggle = () => {
    const next = theme === "dark" ? "light" : "dark";
    setTheme(next);
    document.documentElement.classList.toggle("dark", next === "dark");
    setLocalStorageItem(KEY, next);
  };

  return { theme, toggle };
}
