<?php

namespace App\Models;

use App\Concerns\BelongsToStore;
use App\Enums\CashDirection;
use App\Enums\CashOperationType;
use App\Enums\PaymentMethod;
use Database\Factories\CashOperationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One movement of money in or out of the cash register. Recorded only by the cash register
 * module and permanent (D14): a mistake is corrected by a reversing operation.
 *
 * @property int $id
 * @property int $team_id
 * @property CashOperationType $type
 * @property CashDirection $direction
 * @property PaymentMethod $payment_method
 * @property numeric-string $amount
 * @property int|null $counterparty_id
 * @property int|null $expense_category_id
 * @property int|null $loan_id
 * @property int|null $document_id
 * @property int|null $reversal_of_id
 * @property string|null $note
 * @property int $author_id
 * @property Carbon|null $created_at
 * @property-read Counterparty|null $counterparty
 * @property-read ExpenseCategory|null $expenseCategory
 * @property-read Loan|null $loan
 * @property-read User $author
 * @property-read CashOperation|null $reversal
 * @property-read bool|null $reversal_exists
 */
#[Fillable([
    'team_id', 'type', 'direction', 'payment_method', 'amount', 'counterparty_id', 'expense_category_id',
    'loan_id', 'document_id', 'reversal_of_id', 'note', 'author_id',
])]
class CashOperation extends Model
{
    /** @use HasFactory<CashOperationFactory> */
    use BelongsToStore, HasFactory;

    /**
     * Operations are never updated, so they carry only a creation time.
     */
    public const null UPDATED_AT = null;

    /**
     * Refuse to change or delete a recorded operation (D14).
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Cash operations are permanent.'));
        static::deleting(fn () => throw new LogicException('Cash operations are permanent.'));
    }

    /**
     * Determine if this operation corrects an earlier one.
     */
    public function isReversal(): bool
    {
        return $this->reversal_of_id !== null;
    }

    /**
     * Determine if the operation is of a kind corrected from the Kassa page: an expense or
     * income that is not itself a correction. Other kinds are corrected through their own flow
     * (a return, a repayment).
     */
    public function isCorrectableKind(): bool
    {
        return in_array($this->type, [CashOperationType::Expense, CashOperationType::Income], true)
            && ! $this->isReversal();
    }

    /**
     * Determine if the operation can still be corrected from the Kassa page: a correctable
     * kind that has not been corrected yet. Uses `reversal_exists` when loaded.
     */
    public function isCorrectable(): bool
    {
        return $this->isCorrectableKind() && ! ($this->reversal_exists ?? $this->reversal()->exists());
    }

    /**
     * Get the counterparty money came from or went to.
     *
     * @return BelongsTo<Counterparty, $this>
     */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    /**
     * Get the expense or income category.
     *
     * @return BelongsTo<ExpenseCategory, $this>
     */
    public function expenseCategory(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class);
    }

    /**
     * Get the loan the operation lends, borrows, repays or pays interest on.
     *
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * Get the user who recorded the operation.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the operation that reversed this one, if any.
     *
     * @return HasOne<CashOperation, $this>
     */
    public function reversal(): HasOne
    {
        return $this->hasOne(CashOperation::class, 'reversal_of_id');
    }

    /**
     * Apply the Kassa list filters: date range, type, method, author and counterparty.
     *
     * @param  Builder<static>  $query
     * @param  array{from?: string|null, to?: string|null, type?: CashOperationType|null, payment_method?: PaymentMethod|null, author_id?: int|null, counterparty_id?: int|null}  $filters
     */
    #[Scope]
    protected function filtered(Builder $query, array $filters): void
    {
        $query
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate($this->qualifyColumn('created_at'), '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate($this->qualifyColumn('created_at'), '<=', $to))
            ->when($filters['type'] ?? null, fn (Builder $query, CashOperationType $type) => $query->where($this->qualifyColumn('type'), $type))
            ->when($filters['payment_method'] ?? null, fn (Builder $query, PaymentMethod $method) => $query->where($this->qualifyColumn('payment_method'), $method))
            ->when($filters['author_id'] ?? null, fn (Builder $query, int $id) => $query->where($this->qualifyColumn('author_id'), $id))
            ->when($filters['counterparty_id'] ?? null, fn (Builder $query, int $id) => $query->where($this->qualifyColumn('counterparty_id'), $id));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CashOperationType::class,
            'direction' => CashDirection::class,
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
        ];
    }
}
