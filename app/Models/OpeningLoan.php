<?php

namespace App\Models;

use App\Enums\LoanDirection;
use Database\Factories\OpeningLoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A loan already running when the store started, entered on the opening balance's Qarzlar
 * tab. Executing the opening balance turns it into a loan with no cash operation.
 *
 * @property int $id
 * @property int $document_id
 * @property int $counterparty_id
 * @property LoanDirection $direction
 * @property numeric-string $principal
 * @property Carbon|null $due_date
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Document $document
 * @property-read Counterparty $counterparty
 */
#[Fillable(['document_id', 'counterparty_id', 'direction', 'principal', 'due_date', 'note'])]
class OpeningLoan extends Model
{
    /** @use HasFactory<OpeningLoanFactory> */
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
            'direction' => LoanDirection::class,
            'principal' => 'decimal:2',
            'due_date' => 'date',
        ];
    }
}
