import type { ContentType } from "./content-management-api";

const FIELD_NAMES: Record<string, string> = {
  section_code: "sectionCode",
  category_public_id: "categoryPublicId",
  consultation_cta_public_id: "consultationCtaPublicId",
  answer_document: "answerDocument",
  service_name: "serviceName",
  phone_display: "phone",
  whatsapp_display: "whatsapp",
  appointment_url: "appointmentUrl",
};

export function contentFieldName(contentType: ContentType, backendField: string): string {
  if (contentType === "faq" && backendField === "document") return "answerDocument";
  return FIELD_NAMES[backendField] ?? backendField;
}
