<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MasterDataResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = [
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];

        foreach ([
            'examples',
            'legal_basis',
            'workflow_stage',
            'stage_name',
            'is_terminal',
            'responsible_role',
            'valid_transitions',
        ] as $field) {
            if (array_key_exists($field, $this->getAttributes())) {
                $data[$field] = $this->{$field};
            }
        }

        return $data;
    }
}
