<?php

namespace App\Models;

use App\Enums\DebtSide;
use Database\Factories\OpeningDebtFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A debt already running when the store started, entered on the opening balance's debt tabs.
 * Executing the opening balance turns it into an opening debt entry; no money moves.
 *
 * @property int $id
 * @property int $document_id
 * @property int $counterparty_id
 * @property DebtSide $side
 * @property numeric-string $amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Document $document
 * @property-read Counterparty $counterparty
 */
#[Fillable(['document_id', 'counterparty_id', 'side', 'amount'])]
class OpeningDebt extends Model
{
    /** @use HasFactory<OpeningDebtFactory> */
    use HasFactory;

    /**
     * Get the opening balance.
     *
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the counterparty.
     *
     * @return BelongsTo<Counterparty, $this>
     */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
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
            'amount' => 'decimal:2',
        ];
    }
}
