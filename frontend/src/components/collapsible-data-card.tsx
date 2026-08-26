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
  defaultOpen = true,
  open: controlledOpen,
  onOpenChange,
  expandLabel,
  collapseLabel,
}: {
  title: React.ReactNode;
  description?: React.ReactNode;
  icon?: React.ComponentType<{ className?: string; "aria-hidden"?: boolean }>;
  headerAction?: React.ReactNode;
  children: React.ReactNode;
  className?: string;
  contentClassName?: string;
  defaultOpen?: boolean;
  open?: boolean;
  onOpenChange?: (open: boolean) => void;
  expandLabel?: string;
  collapseLabel?: string;
}) {
  const { t } = useTranslation("dashboard");
  const [uncontrolledOpen, setUncontrolledOpen] = useState(defaultOpen);
  const open = controlledOpen ?? uncontrolledOpen;
  const setOpen = (nextOpen: boolean) => {
    if (controlledOpen === undefined) {
      setUncontrolledOpen(nextOpen);
    }

    onOpenChange?.(nextOpen);
  };
  const toggleLabel = open
    ? (collapseLabel ?? t("dashboard:common.collapseSection"))
    : (expandLabel ?? t("dashboard:common.expandSection"));

  return (
    <Card className={cn("min-w-0 overflow-hidden", className)}>
      <Collapsible open={open} onOpenChange={setOpen}>
        <CardHeader className="min-w-0">
          <div
            className={cn(
              "flex min-w-0 items-start gap-3",
              headerAction ? "flex-col sm:flex-row sm:justify-between" : "justify-between",
            )}
          >
            <div className="min-w-0 space-y-1.5">
              <CardTitle className="flex min-w-0 items-center gap-2 text-base">
                {Icon && <Icon className="h-4 w-4 shrink-0" aria-hidden={true} />}
                <span className="min-w-0 break-words [overflow-wrap:anywhere]">{title}</span>
              </CardTitle>
              {description && <CardDescription>{description}</CardDescription>}
            </div>
            <div
              className={cn(
                "flex items-center gap-2",
                headerAction ? "w-full justify-between sm:w-auto sm:shrink-0" : "shrink-0",
              )}
            >
              {headerAction}
              <CollapsibleTrigger asChild>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="h-11 w-11 shrink-0 sm:h-8 sm:w-8"
                  aria-label={toggleLabel}
                  aria-expanded={open}
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
        <CollapsibleContent forceMount className={cn(!open && "hidden")}>
          <CardContent className={cn("min-w-0", contentClassName)}>{children}</CardContent>
        </CollapsibleContent>
      </Collapsible>
    </Card>
  );
}
