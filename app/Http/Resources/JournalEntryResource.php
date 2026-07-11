<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->uuid,
            'idempotency_key' => $this->idempotency_key,
            'description' => $this->description,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'currency' => $this->currency,
            'source' => $this->source,
            'lines' => $this->whenLoaded('ledgerEntries', fn () => $this->ledgerEntries->map(fn ($line) => [
                'account_uuid' => $line->account?->uuid,
                'account_name' => $line->account?->name,
                'direction' => $line->direction,
                'amount' => number_format((float) $line->amount, 2, '.', ''),
                'notes' => $line->notes,
            ])),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
