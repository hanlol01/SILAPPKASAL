<?php

namespace App\Http\Requests;

use App\Models\BreakGlassRequest;
use App\Models\Report;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class BreakGlassStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (['reason_category', 'reason'] as $field) {
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
            'report_id' => ['required', 'integer', Rule::exists('reports', 'id')],
            'reason_category' => ['required', 'string', Rule::in(BreakGlassRequest::REASON_CATEGORIES)],
            'reason' => ['required', 'string', 'min:50', 'max:2000'],
            'acknowledgment' => ['required', 'accepted'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $reportId = (int) $this->input('report_id');
                $report = Report::query()->find($reportId);

                if (! $report || $report->report_type !== 'anonymous') {
                    $validator->errors()->add('report_id', __('validation.custom.report_id.anonymous_only'));

                    return;
                }

                $hasPendingRequest = BreakGlassRequest::query()
                    ->where('report_id', $reportId)
                    ->where('status', BreakGlassRequest::STATUS_PENDING)
                    ->exists();

                if ($hasPendingRequest) {
                    $validator->errors()->add('report_id', __('validation.custom.report_id.pending_access_exists'));
                }
            },
        ];
    }
}
