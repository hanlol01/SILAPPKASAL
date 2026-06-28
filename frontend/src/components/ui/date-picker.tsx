import * as React from "react";
import { format } from "date-fns";
import { enUS, id as idLocale } from "date-fns/locale";
import { CalendarIcon } from "lucide-react";
import { useTranslation } from "react-i18next";

import { cn } from "@/lib/utils";
import { Button, type ButtonProps } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";

type DatePickerProps = Omit<ButtonProps, "value" | "onChange"> & {
  value: string;
  onChange: (value: string) => void;
  disableFuture?: boolean;
  placeholder?: string;
};

const DatePicker = React.forwardRef<HTMLButtonElement, DatePickerProps>(
  ({ value, onChange, disableFuture, placeholder, disabled, className, ...props }, ref) => {
    const { i18n } = useTranslation();
    const [open, setOpen] = React.useState(false);
    const selectedDate = parseDateValue(value);
    const locale = i18n.language === "en" ? enUS : idLocale;
    const today = startOfToday();

    return (
      <Popover open={open} onOpenChange={setOpen}>
        <PopoverTrigger asChild>
          <Button
            ref={ref}
            type="button"
            variant="outline"
            disabled={disabled}
            className={cn("w-full justify-start text-left font-normal", !selectedDate && "text-muted-foreground", className)}
            {...props}
          >
            <CalendarIcon className="h-4 w-4" />
            {selectedDate ? format(selectedDate, "d MMMM yyyy", { locale }) : placeholder}
          </Button>
        </PopoverTrigger>
        <PopoverContent className="w-auto p-0" align="start">
          <Calendar
            mode="single"
            selected={selectedDate}
            onSelect={(date) => {
              if (!date) return;
              onChange(formatDateValue(date));
              setOpen(false);
            }}
            disabled={disableFuture ? { after: today } : undefined}
            locale={locale}
          />
        </PopoverContent>
      </Popover>
    );
  },
);
DatePicker.displayName = "DatePicker";

function parseDateValue(value: string) {
  const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);
  if (!match) return undefined;

  const year = Number(match[1]);
  const month = Number(match[2]);
  const day = Number(match[3]);
  const date = new Date(year, month - 1, day);

  if (date.getFullYear() !== year || date.getMonth() !== month - 1 || date.getDate() !== day) {
    return undefined;
  }

  return date;
}

function formatDateValue(date: Date) {
  return format(date, "yyyy-MM-dd");
}

function startOfToday() {
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  return today;
}

export { DatePicker };
