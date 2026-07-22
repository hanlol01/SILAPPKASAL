<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublishedConsultationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $content = $this->publishedVersion?->consultationContent;

        return [
            'public_id' => $this->public_id,
            'service_name' => $content?->service_name,
            'description' => $content?->description,
            'service_type' => $content?->service_type,
            'email' => $content?->email,
            'phone' => $content?->phone_display,
            'whatsapp' => $content?->whatsapp_display,
            'office_address' => $content?->office_address,
            'operating_hours' => $content?->operating_hours,
            'procedure' => $content?->procedure,
            'confidentiality_info' => $content?->confidentiality_info,
            'emergency_available' => $content?->emergency_available,
            'appointment_url' => $content?->appointment_url,
            'action_label' => $content?->action_label,
            'icon_code' => $content?->icon_code,
            'display_order' => $content?->sort_order,
            'scope' => $this->scope?->value,
            'verification_date' => $content?->verification_date?->format('Y-m-d'),
        ];
    }
}
