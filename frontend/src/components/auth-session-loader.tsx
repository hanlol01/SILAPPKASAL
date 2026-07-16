import { Loader2 } from "lucide-react";
import { useTranslation } from "react-i18next";

export function AuthSessionLoader() {
  const { t } = useTranslation(["common"]);

  return (
    <div
      className="flex min-h-screen items-center justify-center bg-background px-4 text-foreground"
      role="status"
      aria-live="polite"
    >
      <div className="flex min-w-0 flex-col items-center gap-3 text-center">
        <img src="/Logo.ico" alt="" aria-hidden="true" className="h-12 w-12 object-contain" />
        <div className="flex items-center gap-2 text-sm text-muted-foreground">
          <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
          <span>{t("common:restoringSession")}</span>
        </div>
      </div>
    </div>
  );
}
