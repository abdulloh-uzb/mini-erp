<?php

namespace App\Models;

use App\Concerns\BelongsToStore;
use App\Enums\LoanDirection;
use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Money lent to or borrowed from a counterparty, apart from any sale or purchase. Its balance
 * is the principal minus its repayments; principal and repayments never enter net profit
 * (ADR-0001). Written only by the loan ledger.
 *
 * @property int $id
 * @property int $team_id
 * @property int $counterparty_id
 * @property LoanDirection $direction
 * @property numeric-string $principal
 * @property Carbon|null $due_date
 * @property string|null $note
 * @property int|null $document_id
 * @property int $author_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property numeric-string|null $repaid_amount
 * @property-read Counterparty $counterparty
 * @property-read User $author
 * @property-read Collection<int, CashOperation> $cashOperations
 */
#[Fillable(['team_id', 'counterparty_id', 'direction', 'principal', 'due_date', 'note', 'document_id', 'author_id'])]
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use BelongsToStore, HasFactory;

    /**
     * Get the amount repaid so far: the loan's repayments net of any reversals. Uses the
     * `repaid_amount` loaded by `withRepaid()` when present.
     *
     * @return numeric-string
     */
    public function repaid(): string
    {
        if (! array_key_exists('repaid_amount', $this->attributes)) {
            $this->repaid_amount = static::query()->withoutGlobalScopes()->whereKey($this->id)->withRepaid()->value('repaid_amount');
        }

        return bcadd((string) ($this->repaid_amount ?? '0'), '0', 2);
    }

    /**
     * Get what is still owed: principal minus repayments.
     *
     * @return numeric-string
     */
    public function balance(): string
    {
        return bcsub($this->principal, $this->repaid(), 2);
    }

    /**
     * Determine if the due date has passed with money still owed.
     */
    public function isOverdue(): bool
    {
        return $this->due_date !== null
            && $this->due_date->lt(today())
            && bccomp($this->balance(), '0', 2) > 0;
    }

    /**
     * Load the repaid amount as `repaid_amount`: repayments in minus their reversals.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function withRepaid(Builder $query): void
    {
        $query->addSelect(['repaid_amount' => CashOperation::query()
            ->withoutGlobalScopes()
            ->selectRaw('coalesce(sum(case when reversal_of_id is null then amount else -amount end), 0)')
            ->whereColumn('cash_operations.loan_id', 'loans.id')
            ->whereIn('type', [LoanDirection::Given->repaymentType(), LoanDirection::Received->repaymentType()]),
        ]);
    }

    /**
     * Get the counterparty the money was lent to or borrowed from.
     *
     * @return BelongsTo<Counterparty, $this>
     */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    /**
     * Get the user who recorded the loan.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the principal, repayment and interest operations of the loan.
     *
     * @return HasMany<CashOperation, $this>
     */
    public function cashOperations(): HasMany
    {
        return $this->hasMany(CashOperation::class)->orderBy('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => LoanDirection::class,
            'principal' => 'decimal:2',
            'due_date' => 'date',
        ];
    }
}
