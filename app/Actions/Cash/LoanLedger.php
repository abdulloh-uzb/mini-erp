<?php

namespace App\Actions\Cash;

use App\Enums\LoanDirection;
use App\Enums\PaymentMethod;
use App\Models\CashOperation;
use App\Models\ExpenseCategory;
use App\Models\Loan;
use App\Models\OpeningLoan;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The loan ledger: the one way loans are opened, repaid and charged interest. Principal and
 * repayments move money through the cash register but never reach net profit; only interest,
 * recorded under _Qarz foizi_ / _Olingan foiz_, does (ADR-0001).
 */
class LoanLedger
{
    public function __construct(private CashRegister $register) {}

    /**
     * Open a loan and move its principal: out of the register for a loan given, into it for a
     * loan received.
     *
     * @param  numeric-string  $principal  greater than zero
     *
     * @throws ValidationException when a loan given exceeds the method's balance; nothing is written.
     */
    public function open(
        User $author,
        LoanDirection $direction,
        int $counterpartyId,
        string $principal,
        PaymentMethod $method,
        ?string $dueDate = null,
        ?string $note = null,
    ): Loan {
        return DB::transaction(function () use ($author, $direction, $counterpartyId, $principal, $method, $dueDate, $note) {
            $loan = Loan::create([
                'team_id' => $author->team_id,
                'counterparty_id' => $counterpartyId,
                'direction' => $direction,
                'principal' => $principal,
                'due_date' => $dueDate,
                'note' => $note,
                'author_id' => $author->id,
            ]);

            $this->register->record(
                $author,
                $direction->principalType(),
                $method,
                $principal,
                counterpartyId: $counterpartyId,
                loanId: $loan->id,
                note: $note,
                errorKey: 'principal',
            );

            return $loan;
        });
    }

    /**
     * Open a loan that was already running when the store started, from the opening balance's
     * Qarzlar tab. No money moves: the opening cash already reflects it.
     */
    public function openExisting(User $author, OpeningLoan $openingLoan): Loan
    {
        return Loan::create([
            'team_id' => $author->team_id,
            'author_id' => $author->id,
            ...$openingLoan->only(['counterparty_id', 'direction', 'principal', 'due_date', 'note', 'document_id']),
        ]);
    }

    /**
     * Record a repayment of the loan: money coming back on a loan given, going out on a loan
     * received. The loan row is locked, so two repayments cannot together exceed the balance.
     *
     * @param  numeric-string  $amount  greater than zero
     *
     * @throws ValidationException when the amount exceeds the loan balance or a cash-out exceeds the method's balance.
     */
    public function repay(Loan $loan, User $author, string $amount, PaymentMethod $method, ?string $note = null): CashOperation
    {
        return DB::transaction(function () use ($loan, $author, $amount, $method, $note) {
            $locked = $this->lock($loan);
            $balance = $locked->balance();

            if (bccomp($amount, $balance, 2) > 0) {
                throw ValidationException::withMessages([
                    'amount' => __('Summa qoldiqdan oshmasligi kerak (qoldiq: :balance)', ['balance' => Money::format($balance)]),
                ]);
            }

            return $this->register->record(
                $author,
                $locked->direction->repaymentType(),
                $method,
                $amount,
                counterpartyId: $locked->counterparty_id,
                loanId: $locked->id,
                note: $note,
            );
        });
    }

    /**
     * Record interest on the loan: received on a loan given (_Olingan foiz_ income), paid on a
     * loan received (_Qarz foizi_ expense). Interest does not change the loan balance.
     *
     * @param  numeric-string  $amount  greater than zero
     *
     * @throws ValidationException when paid interest exceeds the method's balance.
     */
    public function recordInterest(Loan $loan, User $author, string $amount, PaymentMethod $method, ?string $note = null): CashOperation
    {
        $category = ExpenseCategory::query()->where('code', $loan->direction->interestCategoryCode())->firstOrFail();

        return $this->register->record(
            $author,
            $category->direction->cashOperationType(),
            $method,
            $amount,
            counterpartyId: $loan->counterparty_id,
            expenseCategoryId: $category->id,
            loanId: $loan->id,
            note: $note,
        );
    }

    /**
     * Get the loan's row, locked for this transaction.
     */
    private function lock(Loan $loan): Loan
    {
        return Loan::withoutGlobalScopes()->whereKey($loan->id)->lockForUpdate()->firstOrFail();
    }
}
