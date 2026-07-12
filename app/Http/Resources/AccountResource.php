<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'name' => $this->name,
            'type' => $this->type,
            'type_label' => $this->type_label,
            'phone' => $this->phone,
            'address' => $this->address,
            'currency' => $this->currency,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
