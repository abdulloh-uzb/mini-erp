<?php

namespace App\Http\Controllers;

use App\Actions\Cash\DebtLedger;
use App\Actions\Documents\ExecuteDocument;
use App\Actions\Documents\ReturnedQuantities;
use App\Actions\Documents\SaveDraft;
use App\Concerns\ReadsDocumentListFilters;
use App\Enums\DebtEntryType;
use App\Enums\DebtSide;
use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Http\Requests\SaveReturnRequest;
use App\Http\Resources\DocumentResource;
use App\Models\CashOperation;
use App\Models\Counterparty;
use App\Models\DebtEntry;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Location;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The return pages shared by sales returns and supplier returns: a list, a picker of the
 * executed source documents, and a form created from one source with every source line at 0
 * beside what was moved, already returned and still returnable. Saqlash keeps a draft;
 * Bajarish (where permitted) executes it.
 */
abstract class ReturnDocumentController extends Controller
{
    use ReadsDocumentListFilters;

    /**
     * Get the kind of return the controller handles.
     */
    abstract protected function kind(): DocumentKind;

    /**
     * Get the route name prefix of the controller's pages.
     */
    abstract protected function routePrefix(): string;

    /**
     * Show the filtered list of returns, newest first.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $this->documentListFilters($request);

        return Inertia::render('returns/index', [
            'kind' => $this->kind(),
            'returns' => $this->listRows(Document::query()->ofKind($this->kind())->filtered($filters)),
            'filters' => [...$filters, 'status' => $filters['status']?->value],
            'locations' => Location::pickerOptions(Location::query()),
            'counterparties' => Counterparty::query()->orderBy('name')->get(['id', 'name']),
            'authors' => $user->team->users()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Show a new return of the executed source given as `source`, or without one, the
     * executed source documents to pick from (search by number or counterparty).
     */
    public function create(Request $request, ReturnedQuantities $returned, DebtLedger $debts): Response
    {
        $sourceKind = $this->kind()->returnSource();

        if (! $request->filled('source')) {
            $search = $request->string('search')->trim()->value();

            return Inertia::render('returns/pick-source', [
                'kind' => $this->kind(),
                'sources' => $this->listRows(Document::query()->ofKind($sourceKind)->filtered([
                    'status' => DocumentStatus::Executed,
                    'search' => $search,
                ])),
                'search' => $search,
            ]);
        }

        $source = Document::query()->ofKind($sourceKind)
            ->where('status', DocumentStatus::Executed)
            ->findOrFail($request->integer('source'));

        return $this->form($request, null, $source, $returned, $debts);
    }

    /**
     * Save a new return draft, executing it right away when asked.
     */
    public function store(SaveReturnRequest $request, SaveDraft $saveDraft, ExecuteDocument $executeDocument): RedirectResponse
    {
        return $this->save($request, null, $saveDraft, $executeDocument);
    }

    /**
     * Show a return: editable while a draft, read-only once executed.
     */
    public function show(Request $request, Document $document, ReturnedQuantities $returned, DebtLedger $debts): Response
    {
        abort_unless($document->kind === $this->kind(), 404);

        return $this->form($request, $document, $document->source, $returned, $debts);
    }

    /**
     * Save the return draft, executing it right away when asked.
     */
    public function update(SaveReturnRequest $request, Document $document, SaveDraft $saveDraft, ExecuteDocument $executeDocument): RedirectResponse
    {
        abort_unless($document->kind === $this->kind(), 404);
        Gate::authorize('update', $document);

        return $this->save($request, $document, $saveDraft, $executeDocument);
    }

    /**
     * Delete the return draft.
     */
    public function destroy(Document $document): RedirectResponse
    {
        abort_unless($document->kind === $this->kind(), 404);
        Gate::authorize('delete', $document);

        $document->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Qoralama o'chirildi.")]);

        return to_route("{$this->routePrefix()}.index");
    }

    /**
     * Render the return form: one row per source line with sold (or purchased), already
     * returned (executed returns) and returnable pieces, the counterparty's current debt for
     * the refund split, and once executed, how the refund was actually split.
     */
    private function form(Request $request, ?Document $document, Document $source, ReturnedQuantities $returnedQuantities, DebtLedger $debts): Response
    {
        $user = $request->user();
        $document?->load(['lines.product', 'lines.location.parent', 'location.parent', 'counterparty', 'author', 'executor']);
        $source->load(['lines.product', 'location.parent', 'counterparty']);
        $returned = $returnedQuantities->handle($source);
        $quantities = $document?->lines->pluck('quantity', 'source_line_id') ?? collect();

        return Inertia::render('returns/show', [
            'kind' => $this->kind(),
            'document' => $document ? DocumentResource::make($document)->resolve($request) : null,
            'editable' => $document === null || $user->can('update', $document),
            'source' => [
                'id' => $source->id,
                'number' => $source->number,
                'counterparty_name' => $source->counterparty?->name,
                'location_name' => $source->location?->fullName(),
                'executed_at' => $source->executed_at?->toIso8601String(),
            ],
            'lines' => $source->lines->map(fn (DocumentLine $line) => [
                'source_line_id' => $line->id,
                'product_name' => $line->product->name,
                'pieces_per_block' => $line->pieces_per_block,
                'price' => $line->price,
                'sold' => $line->quantity,
                'returned' => $returned[$line->id] ?? 0,
                'returnable' => $line->quantity - ($returned[$line->id] ?? 0),
                'quantity' => $quantities[$line->id] ?? 0,
            ])->all(),
            'default_location_id' => $document->location_id ?? $source->location_id,
            'default_refund_method' => $document->refund_method
                ?? $source->payments()->orderByDesc('amount')->first()->payment_method
                ?? PaymentMethod::Cash,
            'counterparty_debt' => $source->counterparty
                ? $debts->balance($source->counterparty, $this->kind() === DocumentKind::SalesReturn ? DebtSide::Customer : DebtSide::Supplier)
                : null,
            'settlement' => $document && ! $document->isDraft() ? $this->settlement($document) : null,
            'locations' => Location::pickerOptions(Location::query()->active()),
            'methods' => PaymentMethod::options(),
        ]);
    }

    /**
     * Get how an executed return's value went back: off the debt, and paid in which method.
     *
     * @return array{debt_reduced: numeric-string, refunded: numeric-string, method: string|null}
     */
    private function settlement(Document $document): array
    {
        $reduced = DebtEntry::query()->where('document_id', $document->id)->where('type', DebtEntryType::Return)->sum('amount');
        $refund = CashOperation::query()->where('document_id', $document->id)->whereNull('reversal_of_id')->first();

        return [
            'debt_reduced' => bcsub('0', (string) $reduced, 2),
            'refunded' => $refund->amount ?? '0.00',
            'method' => $refund?->payment_method->label(),
        ];
    }

    /**
     * Get one page of documents as list rows, newest first.
     *
     * @param  Builder<Document>  $query
     * @return LengthAwarePaginator<int, array{id: int, number: int, status: DocumentStatus, date: string|null, location: string|null, counterparty: string|null, total: string|null, author: string}>
     */
    private function listRows(Builder $query): LengthAwarePaginator
    {
        return $query
            ->select('documents.*')
            ->withTotal()
            ->with(['location.parent', 'counterparty', 'author'])
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Document $document) => [
                'id' => $document->id,
                'number' => $document->number,
                'status' => $document->status,
                'date' => ($document->executed_at ?? $document->created_at)?->toIso8601String(),
                'location' => $document->location?->fullName(),
                'counterparty' => $document->counterparty?->name,
                'total' => $document->total !== null ? bcadd((string) $document->total, '0', 2) : null,
                'author' => $document->author->name,
            ]);
    }

    /**
     * Save the form as a draft and, when asked, execute it. If execution is refused the draft
     * stays saved and the errors come back on its page.
     */
    private function save(SaveReturnRequest $request, ?Document $document, SaveDraft $saveDraft, ExecuteDocument $executeDocument): RedirectResponse
    {
        $user = $request->user();
        $execute = $request->boolean('execute');
        abort_if($execute && ! $user->can($this->kind()->executePermission()->value), 403);

        $document = $saveDraft->handle($document, $this->kind(), $user, $request->documentHeader(), $request->documentLines());

        if (! $execute) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Saqlandi.')]);

            return to_route("{$this->routePrefix()}.show", $document);
        }

        try {
            $executeDocument->handle($document, $user);
        } catch (ValidationException $exception) {
            return to_route("{$this->routePrefix()}.show", $document)->withErrors($exception->errors());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':kind №:number bajarildi.', [
            'kind' => $this->kind()->label(),
            'number' => $document->number,
        ])]);

        return to_route("{$this->routePrefix()}.show", $document);
    }
}
