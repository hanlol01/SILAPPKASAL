<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDecisionStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'max:50'],
            'decision_number' => ['prohibited'],
            'decision_code' => ['prohibited'],
            'formal_decision_code' => ['prohibited'],
            'decisionNumber' => ['prohibited'],
            'decisionCode' => ['prohibited'],
            'formalDecisionCode' => ['prohibited'],
            'sequence' => ['prohibited'],
            'decision_sequence' => ['prohibited'],
            'sequence_number' => ['prohibited'],
            'year' => ['prohibited'],
            'decision_year' => ['prohibited'],
            'nomor_keputusan' => ['prohibited'],
            'nomor_putusan' => ['prohibited'],
            'nomor_sk' => ['prohibited'],
            'kode_keputusan' => ['prohibited'],
            'kode_putusan' => ['prohibited'],
            'decision_no' => ['prohibited'],
        ];
    }
}
