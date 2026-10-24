<?php

namespace App\Http\Controllers;

use App\Actions\Documents\ExecuteDocument;
use App\Actions\Documents\SaveDraft;
use App\Concerns\ReadsDocumentListFilters;
use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Http\Requests\SaveTransferRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Transfers (Transferlar): employees and the owner move pieces from one location to another,
 * with no money or cost effect. An executed transfer is undone by a reverse transfer, a new
 * form with the locations swapped.
 */
class TransferController extends Controller
{
    use ReadsDocumentListFilters;

    /**
     * Show the filtered transfer list, newest first.
     */
    public function index(Request $request): Response
    {
        $filters = $this->documentListFilters($request);

        $transfers = Document::query()
            ->ofKind(DocumentKind::Transfer)
            ->filtered($filters)
            ->with(['location.parent', 'destinationLocation.parent', 'author'])
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Document $document) => [
                'id' => $document->id,
                'number' => $document->number,
                'status' => $document->status,
                'date' => ($document->executed_at ?? $document->created_at)?->toIso8601String(),
                'location' => $document->location?->fullName().' → '.$document->destinationLocation?->fullName(),
                'counterparty' => null,
                'total' => null,
                'author' => $document->author->name,
            ]);

        return Inertia::render('transfers/index', [
            'transfers' => $transfers,
            'filters' => [...$filters, 'status' => $filters['status']?->value],
            'locations' => Location::pickerOptions(Location::query()),
            'authors' => $request->user()->team->users()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Show an empty transfer form, or with `reverse` set to an executed transfer, a form
     * pre-filled with its lines and its locations swapped.
     */
    public function create(Request $request): Response
    {
        $prefill = null;

        if ($request->filled('reverse')) {
            $reversed = Document::query()
                ->ofKind(DocumentKind::Transfer)
                ->where('status', DocumentStatus::Executed)
                ->findOrFail($request->integer('reverse'));

            $prefill = [
                'location_id' => $reversed->destination_location_id,
                'destination_location_id' => $reversed->location_id,
                'lines' => $reversed->lines->map(fn (DocumentLine $line) => $line->only(['product_id', 'quantity', 'pieces_per_block']))->all(),
            ];
        }

        return $this->form($request, null, $prefill);
    }

    /**
     * Save a new transfer draft, executing it right away when asked.
     */
    public function store(SaveTransferRequest $request, SaveDraft $saveDraft, ExecuteDocument $executeDocument): RedirectResponse
    {
        return $this->save($request, null, $saveDraft, $executeDocument);
    }

    /**
     * Show a transfer: editable while a draft, read-only once executed.
     */
    public function show(Request $request, Document $transfer): Response
    {
        abort_unless($transfer->kind === DocumentKind::Transfer, 404);

        return $this->form($request, $transfer);
    }

    /**
     * Save the transfer draft, executing it right away when asked.
     */
    public function update(SaveTransferRequest $request, Document $transfer, SaveDraft $saveDraft, ExecuteDocument $executeDocument): RedirectResponse
    {
        abort_unless($transfer->kind === DocumentKind::Transfer, 404);
        Gate::authorize('update', $transfer);

        return $this->save($request, $transfer, $saveDraft, $executeDocument);
    }

    /**
     * Delete the transfer draft.
     */
    public function destroy(Document $transfer): RedirectResponse
    {
        abort_unless($transfer->kind === DocumentKind::Transfer, 404);
        Gate::authorize('delete', $transfer);

        $transfer->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Qoralama o'chirildi.")]);

        return to_route('transfers.index');
    }

    /**
     * Render the transfer form with its pickers and the pieces on hand per location and
     * product, so each line can show its source stock.
     *
     * @param  array{location_id: int|null, destination_location_id: int|null, lines: array<int, array<string, int>>}|null  $prefill
     */
    private function form(Request $request, ?Document $transfer, ?array $prefill = null): Response
    {
        $transfer?->load(['lines.product', 'lines.location.parent', 'location.parent', 'destinationLocation.parent', 'author', 'executor']);

        return Inertia::render('transfers/show', [
            'document' => $transfer ? DocumentResource::make($transfer)->resolve($request) : null,
            'editable' => $transfer === null || $request->user()->can('update', $transfer),
            'prefill' => $prefill,
            'locations' => Location::pickerOptions(Location::query()->active()),
            'products' => Product::query()->active()->orderBy('name')->get(['id', 'name', 'barcode', 'pieces_per_block']),
            'stock' => StockBalance::query()
                ->where('quantity', '>', 0)
                ->get(['location_id', 'product_id', 'quantity'])
                ->groupBy('location_id')
                ->map(fn (Collection $balances) => $balances->pluck('quantity', 'product_id')),
        ]);
    }

    /**
     * Save the form as a draft and, when asked, execute it. If execution is refused the draft
     * stays saved and the errors come back on its page.
     */
    private function save(SaveTransferRequest $request, ?Document $transfer, SaveDraft $saveDraft, ExecuteDocument $executeDocument): RedirectResponse
    {
        $user = $request->user();
        $execute = $request->boolean('execute');
        abort_if($execute && ! $user->can(DocumentKind::Transfer->executePermission()->value), 403);

        $transfer = $saveDraft->handle($transfer, DocumentKind::Transfer, $user, $request->documentHeader(), $request->documentLines());

        if (! $execute) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Saqlandi.')]);

            return to_route('transfers.show', $transfer);
        }

        try {
            $executeDocument->handle($transfer, $user);
        } catch (ValidationException $exception) {
            return to_route('transfers.show', $transfer)->withErrors($exception->errors());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Transfer №:number bajarildi.', ['number' => $transfer->number])]);

        return to_route('transfers.show', $transfer);
    }
}
