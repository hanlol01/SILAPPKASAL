import { z } from "zod";

export const PHONE_NUMBER_MAX_LENGTH = 30;
export const PHONE_NUMBER_PATTERN = /^\+?[0-9]+$/;
export const PHONE_NUMBER_HTML_PATTERN = "\\+?[0-9]+";

export type PhoneValidationMessages = {
  required: string;
  invalid: string;
};

export function requiredPhoneNumberSchema(messages: PhoneValidationMessages) {
  return z
    .string()
    .min(1, messages.required)
    .max(PHONE_NUMBER_MAX_LENGTH, messages.invalid)
    .regex(PHONE_NUMBER_PATTERN, messages.invalid);
}

export function optionalPhoneNumberSchema(message: string) {
  return z
    .string()
    .max(PHONE_NUMBER_MAX_LENGTH, message)
    .refine((value) => value.length === 0 || PHONE_NUMBER_PATTERN.test(value), message)
    .optional();
}

export function optionalPhoneNumberError(value: string, message: string): string | null {
  if (value.length === 0) return null;

  return value.length <= PHONE_NUMBER_MAX_LENGTH && PHONE_NUMBER_PATTERN.test(value)
    ? null
    : message;
}

export const phoneInputAttributes = {
  type: "tel" as const,
  inputMode: "tel" as const,
  autoComplete: "tel",
  pattern: PHONE_NUMBER_HTML_PATTERN,
  maxLength: PHONE_NUMBER_MAX_LENGTH,
};
