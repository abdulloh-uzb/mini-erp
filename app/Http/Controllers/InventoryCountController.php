<?php

namespace App\Http\Controllers;

use App\Actions\Documents\ExecuteDocument;
use App\Actions\Documents\SaveDraft;
use App\Concerns\ReadsDocumentListFilters;
use App\Enums\DocumentKind;
use App\Enums\StorePermission;
use App\Http\Requests\SaveInventoryCountRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Document;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\StockBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Inventory counts (Inventarizatsiyalar): employees count products at one location blind and
 * save drafts; the owner reviews expected vs counted, valued at average cost, and executes.
 * Expected quantities reach only users who may execute counts (D6).
 */
class InventoryCountController extends Controller
{
    use ReadsDocumentListFilters;

    /**
     * Show the filtered count list, newest first.
     */
    public function index(Request $request): Response
    {
        $filters = $this->documentListFilters($request);

        $counts = Document::query()
            ->ofKind(DocumentKind::InventoryCount)
            ->filtered($filters)
            ->with(['location.parent', 'author'])
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Document $document) => [
                'id' => $document->id,
                'number' => $document->number,
                'status' => $document->status,
                'date' => ($document->executed_at ?? $document->created_at)?->toIso8601String(),
                'location' => $document->location?->fullName(),
                'counterparty' => null,
                'total' => null,
                'author' => $document->author->name,
            ]);

        return Inertia::render('inventory-counts/index', [
            'counts' => $counts,
            'filters' => [...$filters, 'status' => $filters['status']?->value],
            'locations' => Location::pickerOptions(Location::query()),
            'authors' => $request->user()->team->users()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Show an empty count form.
     */
    public function create(Request $request): Response
    {
        return $this->form($request, null);
    }

    /**
     * Save a new count draft, executing it right away when asked.
     */
    public function store(SaveInventoryCountRequest $request, SaveDraft $saveDraft, ExecuteDocument $executeDocument): RedirectResponse
    {
        return $this->save($request, null, $saveDraft, $executeDocument);
    }

    /**
     * Show a count: editable while a draft, read-only once executed.
     */
    public function show(Request $request, Document $count): Response
    {
        abort_unless($count->kind === DocumentKind::InventoryCount, 404);

        return $this->form($request, $count);
    }

    /**
     * Save the count draft, executing it right away when asked.
     */
    public function update(SaveInventoryCountRequest $request, Document $count, SaveDraft $saveDraft, ExecuteDocument $executeDocument): RedirectResponse
    {
        abort_unless($count->kind === DocumentKind::InventoryCount, 404);
        Gate::authorize('update', $count);

        return $this->save($request, $count, $saveDraft, $executeDocument);
    }

    /**
     * Delete the count draft.
     */
    public function destroy(Document $count): RedirectResponse
    {
        abort_unless($count->kind === DocumentKind::InventoryCount, 404);
        Gate::authorize('delete', $count);

        $count->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Qoralama o'chirildi.")]);

        return to_route('inventory-counts.index');
    }

    /**
     * Render the count form with its pickers and, per location, the products with stock there
     * (for "Joydagi barcha mahsulotlarni qo'shish"). Users who may execute counts also get the
     * pieces on hand (expected) and, when they may view costs, each product's average cost.
     */
    private function form(Request $request, ?Document $count): Response
    {
        $user = $request->user();
        $canReview = $user->can(StorePermission::InventoryCountExecute->value);
        $count?->load(['lines.product', 'lines.location.parent', 'location.parent', 'author', 'executor']);
        $stock = StockBalance::query()
            ->where('quantity', '>', 0)
            ->orderBy('product_id')
            ->get(['location_id', 'product_id', 'quantity'])
            ->groupBy('location_id');

        return Inertia::render('inventory-counts/show', [
            'document' => $count ? DocumentResource::make($count)->resolve($request) : null,
            'editable' => $count === null || $user->can('update', $count),
            'locations' => Location::pickerOptions(Location::query()->active()),
            'products' => Product::query()->active()->orderBy('name')->get(['id', 'name', 'barcode', 'pieces_per_block']),
            'stocked_products' => $stock->map(fn (Collection $balances) => $balances->pluck('product_id')),
            ...($canReview ? [
                'expected' => $stock->map(fn (Collection $balances) => $balances->pluck('quantity', 'product_id')),
            ] : []),
            ...($canReview && $user->can(StorePermission::CostsView->value) ? [
                'average_costs' => ProductCost::query()->pluck('average_cost', 'product_id'),
            ] : []),
        ]);
    }

    /**
     * Save the form as a draft and, when asked, execute it. If execution is refused the draft
     * stays saved and the errors come back on its page.
     */
    private function save(SaveInventoryCountRequest $request, ?Document $count, SaveDraft $saveDraft, ExecuteDocument $executeDocument): RedirectResponse
    {
        $user = $request->user();
        $execute = $request->boolean('execute');
        abort_if($execute && ! $user->can(DocumentKind::InventoryCount->executePermission()->value), 403);

        $count = $saveDraft->handle($count, DocumentKind::InventoryCount, $user, $request->documentHeader(), $request->documentLines());

        if (! $execute) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Saqlandi.')]);

            return to_route('inventory-counts.show', $count);
        }

        try {
            $executeDocument->handle($count, $user);
        } catch (ValidationException $exception) {
            return to_route('inventory-counts.show', $count)->withErrors($exception->errors());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Inventarizatsiya №:number bajarildi.', ['number' => $count->number])]);

        return to_route('inventory-counts.show', $count);
    }
}
