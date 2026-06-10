<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $fields = [
            'report_type',
            'category_code',
            'chronology',
            'incident_time',
            'incident_location',
            'location_type',
            'respondent_name',
            'respondent_campus_status',
            'respondent_relation',
            'respondent_details',
            'witness_info',
            'reporter_phone',
        ];

        $normalized = [];

        foreach ($fields as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $normalized[$field] = trim(strip_tags($value));
            }
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'report_type' => ['required', 'string', Rule::in(['open', 'confidential', 'anonymous'])],
            'category_code' => [
                'required',
                'string',
                Rule::exists('report_categories', 'code')->where('is_active', true),
            ],
            'chronology' => ['required', 'string', 'min:50', 'max:10000'],
            'incident_date' => ['required', 'date', 'before_or_equal:today'],
            'incident_time' => ['nullable', 'date_format:H:i'],
            'incident_location' => ['required', 'string', 'max:500'],
            'location_type' => [
                'nullable',
                'string',
                Rule::exists('location_types', 'code')->where('is_active', true),
            ],
            'respondent_name' => ['nullable', 'string', 'max:255'],
            'respondent_campus_status' => [
                'nullable',
                'string',
                Rule::exists('campus_statuses', 'code')->where('is_active', true),
            ],
            'respondent_relation' => [
                'nullable',
                'string',
                Rule::exists('relations', 'code')->where('is_active', true),
            ],
            'respondent_details' => ['nullable', 'string', 'max:2000'],
            'witness_info' => ['nullable', 'string', 'max:2000'],
            'reporter_phone' => [
                'nullable',
                'string',
                'max:30',
                Rule::prohibitedIf(fn (): bool => $this->input('report_type') !== 'confidential'),
            ],
        ];
    }
}
