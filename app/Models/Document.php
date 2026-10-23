<?php

namespace App\Models;

use App\Concerns\BelongsToStore;
use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A document that moves goods: saved as a draft, then executed by the document execution
 * module (`ExecuteDocument`). An executed document is permanent (ADR-0006) and its date is
 * the moment it was executed.
 *
 * @property int $id
 * @property int $team_id
 * @property DocumentKind $kind
 * @property DocumentStatus $status
 * @property int $number
 * @property int|null $location_id
 * @property int|null $destination_location_id
 * @property int|null $counterparty_id
 * @property int|null $source_document_id
 * @property PaymentMethod|null $refund_method
 * @property string|null $note
 * @property int $author_id
 * @property int|null $executor_id
 * @property Carbon|null $executed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property numeric-string|null $total
 * @property-read Collection<int, DocumentLine> $lines
 * @property-read Collection<int, DocumentPayment> $payments
 * @property-read Collection<int, OpeningLoan> $openingLoans
 * @property-read Collection<int, OpeningDebt> $openingDebts
 * @property-read Location|null $location
 * @property-read Location|null $destinationLocation
 * @property-read Counterparty|null $counterparty
 * @property-read Document|null $source
 * @property-read User $author
 * @property-read User|null $executor
 */
#[Fillable([
    'team_id', 'kind', 'status', 'number', 'location_id', 'destination_location_id', 'counterparty_id',
    'source_document_id', 'refund_method', 'note', 'author_id', 'executor_id', 'executed_at',
])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use BelongsToStore, HasFactory;

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * Number every new document after the last one of its kind in its store.
     */
    protected static function booted(): void
    {
        static::creating(function (Document $document) {
            $document->number ??= (int) static::withoutGlobalScopes()
                ->where('team_id', $document->team_id)
                ->where('kind', $document->kind)
                ->max('number') + 1;
        });
    }

    /**
     * Determine if the document is still a draft, which can be edited, deleted and executed.
     */
    public function isDraft(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    /**
     * Get the document's lines in the order they were entered.
     *
     * @return HasMany<DocumentLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(DocumentLine::class)->orderBy('id');
    }

    /**
     * Get the money the document moves per payment method (the opening balance's Kassa tab).
     *
     * @return HasMany<DocumentPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(DocumentPayment::class)->orderBy('id');
    }

    /**
     * Get the loans already running when the store started (the opening balance's Qarzlar tab).
     *
     * @return HasMany<OpeningLoan, $this>
     */
    public function openingLoans(): HasMany
    {
        return $this->hasMany(OpeningLoan::class)->orderBy('id');
    }

    /**
     * Get the customer and supplier debts already running when the store started (the opening
     * balance's debt tabs).
     *
     * @return HasMany<OpeningDebt, $this>
     */
    public function openingDebts(): HasMany
    {
        return $this->hasMany(OpeningDebt::class)->orderBy('id');
    }

    /**
     * Get the location the document's goods leave or enter.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the location a transfer moves goods to.
     *
     * @return BelongsTo<Location, $this>
     */
    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    /**
     * Get the customer or supplier.
     *
     * @return BelongsTo<Counterparty, $this>
     */
    public function counterparty(): BelongsTo
    {
        return $this->belongsTo(Counterparty::class);
    }

    /**
     * Get the executed sale or purchase a return comes from.
     *
     * @return BelongsTo<Document, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'source_document_id');
    }

    /**
     * Get the user who created the document.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Get the user who executed the document.
     *
     * @return BelongsTo<User, $this>
     */
    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executor_id');
    }

    /**
     * Limit the query to documents of the given kind.
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function ofKind(Builder $query, DocumentKind $kind): void
    {
        $query->where($this->qualifyColumn('kind'), $kind);
    }

    /**
     * Load the sum of the lines' quantity × price as `total` (unpriced lines count as nothing).
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function withTotal(Builder $query): void
    {
        $query->addSelect(['total' => DocumentLine::query()
            ->selectRaw('sum(quantity * price)')
            ->whereColumn('document_lines.document_id', 'documents.id'),
        ]);
    }

    /**
     * Apply the shared document-list filters: status tab, date range (execution date, or
     * creation date for drafts), location, counterparty, author, and a search by number or
     * counterparty name.
     *
     * @param  Builder<static>  $query
     * @param  array{status?: DocumentStatus|null, from?: string|null, to?: string|null, location_id?: int|null, counterparty_id?: int|null, author_id?: int|null, search?: string|null}  $filters
     */
    #[Scope]
    protected function filtered(Builder $query, array $filters): void
    {
        $date = DB::raw('coalesce(documents.executed_at, documents.created_at)');
        $search = trim($filters['search'] ?? '');
        $locationId = $filters['location_id'] ?? null;

        $query
            ->when($filters['status'] ?? null, fn (Builder $query, DocumentStatus $status) => $query->where($this->qualifyColumn('status'), $status))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate($date, '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate($date, '<=', $to))
            ->when($locationId, fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where($this->qualifyColumn('location_id'), $locationId)
                ->orWhere($this->qualifyColumn('destination_location_id'), $locationId)))
            ->when($filters['counterparty_id'] ?? null, fn (Builder $query, int $id) => $query->where($this->qualifyColumn('counterparty_id'), $id))
            ->when($filters['author_id'] ?? null, fn (Builder $query, int $id) => $query->where($this->qualifyColumn('author_id'), $id))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->when(ctype_digit(ltrim($search, '№#')), fn (Builder $query) => $query->where($this->qualifyColumn('number'), (int) ltrim($search, '№#')))
                ->orWhereHas('counterparty', fn (Builder $query) => $query->where('name', 'like', '%'.$search.'%'))));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => DocumentKind::class,
            'status' => DocumentStatus::class,
            'number' => 'integer',
            'executed_at' => 'datetime',
            'refund_method' => PaymentMethod::class,
        ];
    }
}
