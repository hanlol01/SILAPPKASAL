import type * as React from "react";
import type { Control, FieldValues, Path } from "react-hook-form";

import {
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import { Input } from "@/components/ui/input";
import { PasswordInput } from "@/components/ui/password-input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";

const EMPTY_SELECT_VALUE = "__empty__";

export type SelectOption = {
  value: string;
  label: string;
  disabled?: boolean;
};

type BaseFieldProps<T extends FieldValues> = {
  control: Control<T>;
  name: Path<T>;
  label: string;
  className?: string;
};

type TextInputFieldProps<T extends FieldValues> = BaseFieldProps<T> & {
  type?: React.HTMLInputTypeAttribute;
  placeholder?: string;
  disabled?: boolean;
  readOnly?: boolean;
  transformValue?: (value: string) => string;
};

export function TextInputField<T extends FieldValues>({
  control,
  name,
  label,
  type,
  placeholder,
  disabled,
  readOnly,
  transformValue,
  className,
}: TextInputFieldProps<T>) {
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className={className}>
          <FormLabel>{label}</FormLabel>
          <FormControl>
            <Input
              {...field}
              type={type}
              placeholder={placeholder}
              disabled={disabled}
              readOnly={readOnly}
              value={field.value ?? ""}
              onChange={(event) => field.onChange(transformValue ? transformValue(event.target.value) : event.target.value)}
            />
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  );
}

export function PasswordField<T extends FieldValues>({
  control,
  name,
  label,
  placeholder,
  disabled,
  className,
}: Omit<TextInputFieldProps<T>, "type" | "readOnly">) {
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className={className}>
          <FormLabel>{label}</FormLabel>
          <FormControl>
            <PasswordInput
              {...field}
              placeholder={placeholder}
              disabled={disabled}
              value={field.value ?? ""}
            />
          </FormControl>
          <FormMessage />
        </FormItem>
      )}
    />
  );
}

type SelectFieldProps<T extends FieldValues> = BaseFieldProps<T> & {
  options: SelectOption[];
  placeholder: string;
  disabled?: boolean;
  onValueChange?: (value: string) => void;
};

export function SelectFormField<T extends FieldValues>({
  control,
  name,
  label,
  options,
  placeholder,
  disabled,
  onValueChange,
  className,
}: SelectFieldProps<T>) {
  return (
    <FormField
      control={control}
      name={name}
      render={({ field }) => (
        <FormItem className={className}>
          <FormLabel>{label}</FormLabel>
          <Select
            disabled={disabled}
            value={toSelectValue(field.value)}
            onValueChange={(value) => {
              const normalized = fromSelectValue(value);
              field.onChange(normalized);
              onValueChange?.(normalized);
            }}
          >
            <FormControl>
              <SelectTrigger>
                <SelectValue placeholder={placeholder} />
              </SelectTrigger>
            </FormControl>
            <SelectContent>
              {options.map((option, index) => (
                <SelectItem key={`${option.value || EMPTY_SELECT_VALUE}-${index}`} value={toSelectItemValue(option.value)} disabled={option.disabled}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
          <FormMessage />
        </FormItem>
      )}
    />
  );
}

type SelectInputProps = {
  value: string;
  onValueChange: (value: string) => void;
  options: SelectOption[];
  placeholder: string;
  disabled?: boolean;
  className?: string;
};

export function SelectInput({
  value,
  onValueChange,
  options,
  placeholder,
  disabled,
  className,
}: SelectInputProps) {
  return (
    <Select
      disabled={disabled}
      value={toSelectValue(value)}
      onValueChange={(nextValue) => onValueChange(fromSelectValue(nextValue))}
    >
      <SelectTrigger className={className}>
        <SelectValue placeholder={placeholder} />
      </SelectTrigger>
      <SelectContent>
        {options.map((option, index) => (
          <SelectItem key={`${option.value || EMPTY_SELECT_VALUE}-${index}`} value={toSelectItemValue(option.value)} disabled={option.disabled}>
            {option.label}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  );
}

function toSelectValue(value: unknown) {
  return value ? String(value) : undefined;
}

function toSelectItemValue(value: string) {
  return value === "" ? EMPTY_SELECT_VALUE : value;
}

function fromSelectValue(value: string) {
  return value === EMPTY_SELECT_VALUE ? "" : value;
}
