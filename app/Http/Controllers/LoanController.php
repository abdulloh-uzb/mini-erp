<?php

namespace App\Http\Controllers;

use App\Actions\Cash\LoanLedger;
use App\Enums\LoanDirection;
use App\Enums\PaymentMethod;
use App\Http\Requests\StoreLoanPaymentRequest;
use App\Http\Requests\StoreLoanRequest;
use App\Models\CashOperation;
use App\Models\Counterparty;
use App\Models\Loan;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Loans given and received (owner only): the list, the loan card with its repayments and
 * interest, and the forms that record them through the loan ledger.
 */
class LoanController extends Controller
{
    /**
     * Show every loan, newest first, with what is repaid and still owed.
     */
    public function index(): Response
    {
        return Inertia::render('loans/index', [
            'loans' => Loan::query()
                ->withRepaid()
                ->with('counterparty')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Loan $loan) => $this->loanRow($loan))
                ->all(),
            'counterparties' => Counterparty::query()->orderBy('name')->get(['id', 'name']),
            'directions' => LoanDirection::options(),
            'methods' => PaymentMethod::options(),
        ]);
    }

    /**
     * Record a new loan and its principal's cash operation.
     */
    public function store(StoreLoanRequest $request, LoanLedger $ledger): RedirectResponse
    {
        $loan = $ledger->open(
            $request->user(),
            $request->enum('direction', LoanDirection::class),
            $request->integer('counterparty_id'),
            (string) $request->validated('principal'),
            $request->enum('payment_method', PaymentMethod::class),
            $request->validated('due_date'),
            $request->validated('note'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Qarz yozildi.')]);

        return to_route('loans.show', $loan);
    }

    /**
     * Show the loan card: its figures and every principal, repayment and interest operation.
     */
    public function show(Loan $loan): Response
    {
        $loan->load(['counterparty', 'cashOperations.author', 'cashOperations.expenseCategory']);

        return Inertia::render('loans/show', [
            'loan' => [...$this->loanRow($loan), 'note' => $loan->note, 'direction_code' => $loan->direction],
            'operations' => $loan->cashOperations->map(fn (CashOperation $operation) => [
                'id' => $operation->id,
                'date' => $operation->created_at?->toIso8601String(),
                'type' => $operation->expenseCategory?->name ?? $operation->type->label(),
                'direction' => $operation->direction,
                'payment_method' => $operation->payment_method->label(),
                'amount' => $operation->amount,
                'note' => $operation->note,
                'author' => $operation->author->name,
            ])->all(),
            'methods' => PaymentMethod::options(),
        ]);
    }

    /**
     * Record a repayment of the loan, up to its balance.
     */
    public function repay(StoreLoanPaymentRequest $request, Loan $loan, LoanLedger $ledger): RedirectResponse
    {
        $ledger->repay(
            $loan,
            $request->user(),
            (string) $request->validated('amount'),
            $request->enum('payment_method', PaymentMethod::class),
            $request->validated('note'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Qaytarish yozildi.')]);

        return to_route('loans.show', $loan);
    }

    /**
     * Record interest paid or received on the loan.
     */
    public function interest(StoreLoanPaymentRequest $request, Loan $loan, LoanLedger $ledger): RedirectResponse
    {
        $ledger->recordInterest(
            $loan,
            $request->user(),
            (string) $request->validated('amount'),
            $request->enum('payment_method', PaymentMethod::class),
            $request->validated('note'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Foiz yozildi.')]);

        return to_route('loans.show', $loan);
    }

    /**
     * Get the loan as the list and the card show it.
     *
     * @return array{id: int, counterparty: string, direction: string, principal: string, repaid: string, balance: string, due_date: string|null, overdue: bool, date: string|null}
     */
    private function loanRow(Loan $loan): array
    {
        return [
            'id' => $loan->id,
            'counterparty' => $loan->counterparty->name,
            'direction' => $loan->direction->label(),
            'principal' => $loan->principal,
            'repaid' => $loan->repaid(),
            'balance' => $loan->balance(),
            'due_date' => $loan->due_date?->toDateString(),
            'overdue' => $loan->isOverdue(),
            'date' => $loan->created_at?->toIso8601String(),
        ];
    }
}
