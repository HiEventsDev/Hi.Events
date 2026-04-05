<?php

declare(strict_types=1);

namespace HiEvents\Http\Actions\Pos;

use HiEvents\DomainObjects\EventDomainObject;
use HiEvents\Http\Actions\BaseAction;
use HiEvents\Http\ResponseCodes;
use HiEvents\Repository\Interfaces\PosSessionRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreatePosTransactionAction extends BaseAction
{
    public function __construct(
        private readonly PosSessionRepositoryInterface $posSessionRepository,
    ) {
    }

    public function __invoke(int $eventId, int $sessionId, Request $request): JsonResponse
    {
        $this->isActionAuthorized($eventId, EventDomainObject::class);

        $validated = $request->validate([
            'payment_method' => 'required|string|in:card,cash,free',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'order_id' => 'nullable|integer',
            'stripe_payment_intent_id' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:2000',
        ]);

        $transaction = DB::transaction(function () use ($eventId, $sessionId, $validated) {
            $receiptNumber = 'POS-' . strtoupper(Str::random(8));

            $transactionId = DB::table('pos_transactions')->insertGetId([
                'pos_session_id' => $sessionId,
                'order_id' => $validated['order_id'] ?? null,
                'event_id' => $eventId,
                'payment_method' => $validated['payment_method'],
                'amount' => $validated['amount'],
                'currency' => strtoupper($validated['currency']),
                'stripe_payment_intent_id' => $validated['stripe_payment_intent_id'] ?? null,
                'status' => 'completed',
                'receipt_number' => $receiptNumber,
                'notes' => $validated['notes'] ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Update session totals
            $updateField = $validated['payment_method'] === 'cash' ? 'total_cash' : 'total_card';
            DB::table('pos_sessions')
                ->where('id', $sessionId)
                ->increment('total_sales', $validated['amount']);
            DB::table('pos_sessions')
                ->where('id', $sessionId)
                ->increment('total_orders');

            if ($validated['payment_method'] !== 'free') {
                DB::table('pos_sessions')
                    ->where('id', $sessionId)
                    ->increment($updateField, $validated['amount']);
            }

            return [
                'id' => $transactionId,
                'receipt_number' => $receiptNumber,
                'payment_method' => $validated['payment_method'],
                'amount' => $validated['amount'],
                'status' => 'completed',
            ];
        });

        return $this->jsonResponse($transaction, ResponseCodes::HTTP_CREATED);
    }
}
