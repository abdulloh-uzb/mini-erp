<?php

namespace App\Models;

use App\Concerns\BelongsToStore;
use App\Enums\DebtEntryType;
use App\Enums\DebtSide;
use Database\Factories\DebtEntryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * One change to what a counterparty owes the store (customer side) or the store owes them
 * (supplier side): positive raises the debt, negative lowers it, and the balance is the sum.
 * Written only by the debt ledger and permanent, like cash operations.
 *
 * @property int $id
 * @property int $team_id
 * @property int $counterparty_id
 * @property DebtSide $side
 * @property DebtEntryType $type
 * @property numeric-string $amount
 * @property int|null $document_id
 * @property int|null $cash_operation_id
 * @property int $author_id
 * @property Carbon|null $created_at
 * @property-read Counterparty $counterparty
 * @property-read Document|null $document
 * @property-read CashOperation|null $cashOperation
 */
#[Fillable(['team_id', 'counterparty_id', 'side', 'type', 'amount', 'document_id', 'cash_operation_id', 'author_id'])]
class DebtEntry extends Model
{
    /** @use HasFactory<DebtEntryFactory> */
    use BelongsToStore, HasFactory;

    /**
     * Entries are never updated, so they carry only a creation time.
     */
    public const null UPDATED_AT = null;

    /**
     * Refuse to change or delete a recorded entry.
     */
    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Debt entries are permanent.'));
        static::deleting(fn () => throw new LogicException('Debt entries are permanent.'));
    }

    /**
     * Get the counterparty the debt is with.
     *
     * @return BelongsTo<Counterparty, $this>
     */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    /**
     * Get the document the debt arose from, if any.
     *
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the cash operation that paid the debt, if any.
     *
     * @return BelongsTo<CashOperation, $this>
     */
    public function cashOperation(): BelongsTo
    {
        return $this->belongsTo(CashOperation::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'side' => DebtSide::class,
            'type' => DebtEntryType::class,
            'amount' => 'decimal:2',
        ];
    }
}
