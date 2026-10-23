<?php

namespace App\Http\Controllers;

use App\Actions\Cash\DebtLedger;
use App\Enums\DebtEntryType;
use App\Enums\DebtSide;
use App\Enums\PaymentMethod;
use App\Http\Requests\StoreDebtPaymentRequest;
use App\Http\Requests\WriteOffDebtRequest;
use App\Models\Counterparty;
use App\Models\DebtEntry;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Mijoz qarzlari: what each customer still owes the store, the customer card with its debt
 * history, the one debt payment form (also behind Kassa → + Kirim) and the owner's write-off.
 */
class CustomerDebtController extends Controller
{
    /**
     * Show every customer with a debt, largest first, and the total.
     */
    public function index(Request $request, DebtLedger $ledger): Response
    {
        $debts = collect($ledger->debtors($request->user()->team_id, DebtSide::Customer))
            ->sortByDesc(fn (array $debt) => (float) $debt['balance'])
            ->values()
            ->all();

        return Inertia::render('customer-debts/index', [
            'debts' => $debts,
            'total' => array_reduce($debts, fn (string $total, array $debt) => bcadd($total, $debt['balance'], 2), '0.00'),
            'methods' => PaymentMethod::options(),
        ]);
    }

    /**
     * Show the customer card: the one balance, which sales it is made of with the oldest paid
     * off first (D9), and the history of everything that moved it, newest first.
     */
    public function show(Counterparty $counterparty, DebtLedger $ledger): Response
    {
        $entries = DebtEntry::query()
            ->where('counterparty_id', $counterparty->id)
            ->where('side', DebtSide::Customer)
            ->with(['author', 'cashOperation'])
            ->orderByDesc('id')
            ->get();

        $numbers = Document::query()->whereIn('id', $entries->pluck('document_id')->filter())->pluck('number', 'id');
        $allocation = collect($ledger->allocation($counterparty, DebtSide::Customer));

        return Inertia::render('customer-debts/show', [
            'customer' => $counterparty->only(['id', 'name', 'phone', 'note']),
            'balance' => $ledger->balance($counterparty, DebtSide::Customer),
            'sales' => $allocation
                ->reverse()
                ->map(fn (array $row) => [
                    ...$row,
                    'type' => $row['type']->label(),
                    'document_number' => $numbers[$row['document_id']] ?? null,
                ])
                ->values()
                ->all(),
            'history' => $entries->map(fn (DebtEntry $entry) => [
                'id' => $entry->id,
                'date' => $entry->created_at?->toIso8601String(),
                'type' => $entry->type->label(),
                'amount' => $entry->amount,
                'document_id' => $entry->type === DebtEntryType::Sale ? $entry->document_id : null,
                'document_number' => $numbers[$entry->document_id] ?? null,
                'payment_method' => $entry->cashOperation?->payment_method->label(),
                'note' => $entry->note ?? $entry->cashOperation?->note,
                'author' => $entry->author->name,
            ])->all(),
            'methods' => PaymentMethod::options(),
        ]);
    }

    /**
     * Take a payment from a customer, up to the debt.
     */
    public function pay(StoreDebtPaymentRequest $request, DebtLedger $ledger): RedirectResponse
    {
        $ledger->pay(
            $request->user(),
            $request->counterparty(),
            DebtSide::Customer,
            (string) $request->validated('amount'),
            $request->enum('payment_method', PaymentMethod::class),
            $request->validated('note'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __("To'lov yozildi.")]);

        return back();
    }

    /**
     * Write off part or all of a customer's debt as uncollectable (owner only).
     */
    public function writeOff(WriteOffDebtRequest $request, DebtLedger $ledger): RedirectResponse
    {
        $ledger->writeOff(
            $request->user(),
            $request->counterparty(),
            (string) $request->validated('amount'),
            $request->validated('note'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Qarz hisobdan chiqarildi.')]);

        return back();
    }
}
