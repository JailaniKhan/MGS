<?php

namespace App\Services\Billing;

use App\Models\Order;
use App\Models\PartyPayment;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\PurchasePayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Allocate a party-level (ledger) payment onto that party's open documents.
 *
 * Oldest non-cancelled document first, same-currency documents only, never
 * paying a document past its canonical remaining balance. Any amount that
 * cannot be allocated (overpayment) is kept on account as a PartyPayment
 * so the ledger still records it and the party balance credits it.
 */
class PaymentAllocationService
{
    /**
     * @return array{allocated: string, unallocated: string, documents: array<int, array{type: string, id: int, amount: string}>}
     */
    public function allocate(string $personType, int $personId, string $amount, string $currency, ?string $notes = null): array
    {
        $documents = $personType === 'customer'
            ? $this->openDocuments(Order::class, 'person_type', 'person_id', $personType, $personId, $currency)
            : $this->openDocuments(Purchase::class, 'person_type', 'person_id', $personType, $personId, $currency);

        $left = bcadd('0.00', (string) $amount, 2);
        $allocated = '0.00';
        $applied = [];

        DB::transaction(function () use ($personType, $personId, $documents, $currency, &$left, &$allocated, &$applied, $notes) {
            foreach ($documents as $candidate) {
                if (bccomp($left, '0', 2) <= 0) {
                    break;
                }

                // Re-read the candidate under row lock so a concurrent
                // payment can't slip between our remaining-balance read and
                // the insert.
                $document = $candidate instanceof Order
                    ? Order::whereKey($candidate->id)->lockForUpdate()->first()
                    : Purchase::whereKey($candidate->id)->lockForUpdate()->first();

                if (! $document) {
                    continue;
                }

                $remaining = $document->remaining_amount;
                if (bccomp($remaining, '0', 2) <= 0) {
                    continue;
                }

                $chunk = bccomp($left, $remaining, 2) <= 0 ? $left : $remaining;

                if ($personType === 'customer') {
                    Payment::create([
                        'order_id' => $document->id,
                        'amount' => $chunk,
                        'currency' => $document->currency,
                        'notes' => $notes,
                    ]);
                } else {
                    PurchasePayment::create([
                        'purchase_id' => $document->id,
                        'amount' => $chunk,
                        'currency' => $document->currency,
                        'notes' => $notes,
                    ]);
                }

                $allocated = bcadd($allocated, $chunk, 2);
                $left = bcsub($left, $chunk, 2);
                $applied[] = ['type' => $personType === 'customer' ? 'order' : 'purchase', 'id' => $document->id, 'amount' => $chunk];
            }

            // Leftover (party has no open balance for this currency) stays on
            // account so it credits the party and shows in payment history —
            // inside the same transaction, so allocation and its remainder
            // commit or roll back together.
            if (bccomp($left, '0', 2) > 0) {
                PartyPayment::create([
                    'person_type' => $personType,
                    'person_id' => $personId,
                    'amount' => $left,
                    'currency' => $currency,
                    'type' => $personType === 'customer' ? 'payment_received' : 'payment_made',
                    'notes' => $notes,
                ]);
            }
        });

        return ['allocated' => $allocated, 'unallocated' => $left, 'documents' => $applied];
    }

    /**
     * Open documents for the party in the payment currency, oldest first.
     *
     * @return Collection<int, Order|Purchase>
     */
    protected function openDocuments(string $modelClass, string $typeColumn, string $idColumn, string $personType, int $personId, string $currency)
    {
        return $modelClass::where($typeColumn, $personType)
            ->where($idColumn, $personId)
            ->where('currency', $currency)
            ->where('status', '!=', 'cancelled')
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->filter(fn ($doc) => bccomp($doc->remaining_amount, '0', 2) > 0)
            ->values();
    }
}
