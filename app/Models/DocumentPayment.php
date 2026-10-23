<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use Database\Factories\DocumentPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Money a document plans to move through one payment method. It becomes a cash operation
 * only when the document is executed; the opening balance uses it for its Kassa section.
 *
 * @property int $id
 * @property int $document_id
 * @property PaymentMethod $payment_method
 * @property numeric-string $amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Document $document
 */
#[Fillable(['document_id', 'payment_method', 'amount'])]
class DocumentPayment extends Model
{
    /** @use HasFactory<DocumentPaymentFactory> */
    use HasFactory;

    /**
     * Get the document.
     *
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
        ];
    }
}
