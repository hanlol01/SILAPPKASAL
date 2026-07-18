import { useState } from "react";
import { ChevronDown } from "lucide-react";
import { useTranslation } from "react-i18next";

import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "@/components/ui/collapsible";
import { cn } from "@/lib/utils";

export function CollapsibleDataCard({
  title,
  description,
  icon: Icon,
  headerAction,
  children,
  className,
  contentClassName,
}: {
  title: React.ReactNode;
  description?: React.ReactNode;
  icon?: React.ComponentType<{ className?: string; "aria-hidden"?: boolean }>;
  headerAction?: React.ReactNode;
  children: React.ReactNode;
  className?: string;
  contentClassName?: string;
}) {
  const { t } = useTranslation("dashboard");
  const [open, setOpen] = useState(true);
  const toggleLabel = open
    ? t("dashboard:common.collapseSection")
    : t("dashboard:common.expandSection");

  return (
    <Card className={cn("min-w-0 overflow-hidden", className)}>
      <Collapsible open={open} onOpenChange={setOpen}>
        <CardHeader className="min-w-0">
          <div className="flex min-w-0 items-start justify-between gap-3">
            <div className="min-w-0 space-y-1.5">
              <CardTitle className="flex min-w-0 items-center gap-2 text-base">
                {Icon && <Icon className="h-4 w-4 shrink-0" aria-hidden={true} />}
                <span className="min-w-0 break-words [overflow-wrap:anywhere]">{title}</span>
              </CardTitle>
              {description && <CardDescription>{description}</CardDescription>}
            </div>
            <div className="flex shrink-0 items-center gap-2">
              {headerAction}
              <CollapsibleTrigger asChild>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="h-8 w-8"
                  aria-label={toggleLabel}
                  title={toggleLabel}
                >
                  <ChevronDown
                    className={cn(
                      "h-4 w-4 transition-transform duration-200 motion-reduce:transition-none",
                      open && "rotate-180",
                    )}
                    aria-hidden="true"
                  />
                </Button>
              </CollapsibleTrigger>
            </div>
          </div>
        </CardHeader>
        <CollapsibleContent>
          <CardContent className={cn("min-w-0", contentClassName)}>{children}</CardContent>
        </CollapsibleContent>
      </Collapsible>
    </Card>
  );
}
