import * as React from "react";
import { Clock } from "lucide-react";

import { cn } from "@/lib/utils";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover";
import { ScrollArea } from "@/components/ui/scroll-area";

export type TimePickerQuickPick = {
  label: string;
  value: string;
};

type TimePickerProps = Omit<React.ComponentPropsWithoutRef<"div">, "onChange"> & {
  value?: string | null;
  onChange: (value: string | null) => void;
  quickPicks: TimePickerQuickPick[];
  unknownLabel: string;
  hourLabel: string;
  minuteLabel: string;
  placeholder?: string;
  maxTime?: string;
  disabled?: boolean;
  name?: string;
};

const TimePicker = React.forwardRef<HTMLDivElement, TimePickerProps>(
  (
    {
      value,
      onChange,
      quickPicks,
      unknownLabel,
      hourLabel,
      minuteLabel,
      placeholder = "Contoh : 00:00",
      maxTime,
      disabled,
      className,
      onBlur,
      name,
      ...props
    },
    ref,
  ) => {
    const [open, setOpen] = React.useState(false);
    const isUnknown = value === null;
    const normalizedValue = isValidTime(value) ? value : "";
    const [selectedHour, selectedMinute] = splitTime(normalizedValue);
    const [maxHour, maxMinute] = splitTime(maxTime);
    const isDisabled = Boolean(disabled || isUnknown);

    return (
      <div ref={ref} className={cn("space-y-3", className)} onBlur={onBlur} {...props}>
        <Popover open={open} onOpenChange={setOpen}>
          <PopoverTrigger asChild>
            <Button
              type="button"
              variant="outline"
              disabled={isDisabled}
              className={cn(
                "h-9 w-full justify-start gap-2 px-3 text-left font-normal",
                !normalizedValue && "text-muted-foreground",
              )}
            >
              <span className="font-medium">{normalizedValue || placeholder}</span>
              <Clock aria-hidden="true" className="ml-auto h-4 w-4 text-foreground opacity-80" />
            </Button>
          </PopoverTrigger>
          <PopoverContent className="w-[184px] p-0" align="start">
            <div className="grid grid-cols-2 border-b bg-muted/40 px-2 py-1.5 text-xs font-medium text-muted-foreground">
              <span>{hourLabel}</span>
              <span>{minuteLabel}</span>
            </div>
            <div className="grid grid-cols-2">
              <ScrollArea className="h-72 border-r">
                <div className="p-1">
                  {HOURS.map((hour) => {
                    const isOptionDisabled = isHourAfterMax(hour, maxHour);
                    return (
                      <TimeOption
                        key={hour}
                        value={hour}
                        selected={hour === selectedHour}
                        disabled={isOptionDisabled}
                        onSelect={() => onChange(composeTime(hour, selectedMinute, maxTime))}
                      />
                    );
                  })}
                </div>
              </ScrollArea>
              <ScrollArea className="h-72">
                <div className="p-1">
                  {MINUTES.map((minute) => {
                    const isOptionDisabled = isMinuteAfterMax(selectedHour, minute, maxHour, maxMinute);
                    return (
                      <TimeOption
                        key={minute}
                        value={minute}
                        selected={minute === selectedMinute}
                        disabled={isOptionDisabled}
                        onSelect={() => onChange(composeTime(selectedHour, minute, maxTime))}
                      />
                    );
                  })}
                </div>
              </ScrollArea>
            </div>
          </PopoverContent>
        </Popover>
        <input type="hidden" name={name} value={normalizedValue} />
        <div className="flex flex-wrap gap-2">
          {quickPicks.map((item) => (
            <Button
              key={item.value}
              type="button"
              size="sm"
              variant={value === item.value ? "default" : "outline"}
              disabled={isDisabled || isTimeAfterMax(item.value, maxTime)}
              onClick={() => onChange(item.value)}
            >
              {item.label}
            </Button>
          ))}
        </div>
        <label className="flex items-center gap-2 text-sm text-muted-foreground">
          <Checkbox
            checked={isUnknown}
            disabled={disabled}
            onCheckedChange={(checked) => onChange(checked === true ? null : "")}
          />
          {unknownLabel}
        </label>
      </div>
    );
  },
);
TimePicker.displayName = "TimePicker";

function TimeOption({
  value,
  selected,
  disabled,
  onSelect,
}: {
  value: string;
  selected: boolean;
  disabled: boolean;
  onSelect: () => void;
}) {
  return (
    <button
      type="button"
      disabled={disabled}
      className={cn(
        "flex h-8 w-full items-center justify-center rounded-sm text-sm transition-colors",
        selected && "bg-primary text-primary-foreground",
        !selected && "hover:bg-accent hover:text-accent-foreground",
        disabled && "cursor-not-allowed opacity-40 hover:bg-transparent hover:text-current",
      )}
      onClick={onSelect}
    >
      {value}
    </button>
  );
}

const HOURS = Array.from({ length: 24 }, (_, index) => String(index).padStart(2, "0"));
const MINUTES = Array.from({ length: 60 }, (_, index) => String(index).padStart(2, "0"));

function splitTime(value?: string | null) {
  if (!isValidTime(value)) return ["", ""] as const;
  return value.split(":") as [string, string];
}

function isValidTime(value?: string | null): value is string {
  return typeof value === "string" && /^([01]\d|2[0-3]):[0-5]\d$/.test(value);
}

function composeTime(hour: string, minute: string, maxTime?: string) {
  const nextHour = hour || "00";
  const nextMinute = minute || "00";
  const nextValue = `${nextHour}:${nextMinute}`;

  if (!isTimeAfterMax(nextValue, maxTime)) return nextValue;
  return maxTime ?? nextValue;
}

function isTimeAfterMax(value: string, maxTime?: string) {
  return Boolean(maxTime && value > maxTime);
}

function isHourAfterMax(hour: string, maxHour: string) {
  return Boolean(maxHour && hour > maxHour);
}

function isMinuteAfterMax(hour: string, minute: string, maxHour: string, maxMinute: string) {
  const effectiveHour = hour || "00";
  return Boolean(maxHour && maxMinute && effectiveHour === maxHour && minute > maxMinute);
}

export { TimePicker };
