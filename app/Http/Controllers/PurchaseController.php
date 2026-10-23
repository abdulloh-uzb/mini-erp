<?php

namespace App\Http\Controllers;

use App\Actions\Cash\DebtLedger;
use App\Actions\Documents\ExecuteDocument;
use App\Actions\Documents\PreviousPrices;
use App\Actions\Documents\SaveDraft;
use App\Enums\DebtSide;
use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Enums\StorePermission;
use App\Http\Requests\SavePurchaseRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Counterparty;
use App\Models\Document;
use App\Models\DocumentPayment;
use App\Models\Location;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Purchases (Xaridlar): employees draft what arrived without seeing prices; the owner fills
 * prices (pre-filled with the previous purchase price) and payment, then executes. Prices,
 * payments and supplier debts reach only users who may view costs.
 */
class PurchaseController extends Controller
{
    /**
     * Show the filtered purchase list, newest first.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canViewCosts = $user->can(StorePermission::CostsView->value);
        $filters = [
            'status' => $request->enum('status', DocumentStatus::class),
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
            'location_id' => $request->filled('location_id') ? $request->integer('location_id') : null,
            'counterparty_id' => $request->filled('counterparty_id') ? $request->integer('counterparty_id') : null,
            'author_id' => $request->filled('author_id') ? $request->integer('author_id') : null,
            'search' => $request->string('search')->trim()->value(),
        ];

        $purchases = Document::query()
            ->ofKind(DocumentKind::Purchase)
            ->filtered($filters)
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
                'total' => $canViewCosts && $document->total !== null ? bcadd((string) $document->total, '0', 2) : null,
                'author' => $document->author->name,
            ]);

        return Inertia::render('purchases/index', [
            'purchases' => $purchases,
            'filters' => [...$filters, 'status' => $filters['status']?->value],
            'locations' => Location::pickerOptions(Location::query()),
            'counterparties' => Counterparty::query()->orderBy('name')->get(['id', 'name']),
            'authors' => $user->team->users()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Show an empty purchase form.
     */
    public function create(Request $request, PreviousPrices $previousPrices, DebtLedger $debts): Response
    {
        return $this->form($request, null, $previousPrices, $debts);
    }

    /**
     * Save a new purchase draft, executing it right away when asked.
     */
    public function store(SavePurchaseRequest $request, SaveDraft $saveDraft, ExecuteDocument $executeDocument): RedirectResponse
    {
        return $this->save($request, null, $saveDraft, $executeDocument);
    }

    /**
     * Show a purchase: editable while a draft, read-only once executed.
     */
    public function show(Request $request, Document $purchase, PreviousPrices $previousPrices, DebtLedger $debts): Response
    {
        abort_unless($purchase->kind === DocumentKind::Purchase, 404);

        return $this->form($request, $purchase, $previousPrices, $debts);
    }

    /**
     * Save the purchase draft, executing it right away when asked.
     */
    public function update(SavePurchaseRequest $request, Document $purchase, SaveDraft $saveDraft, ExecuteDocument $executeDocument): RedirectResponse
    {
        abort_unless($purchase->kind === DocumentKind::Purchase, 404);
        Gate::authorize('update', $purchase);

        return $this->save($request, $purchase, $saveDraft, $executeDocument);
    }

    /**
     * Delete the purchase draft.
     */
    public function destroy(Document $purchase): RedirectResponse
    {
        abort_unless($purchase->kind === DocumentKind::Purchase, 404);
        Gate::authorize('delete', $purchase);

        $purchase->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Qoralama o'chirildi.")]);

        return to_route('purchases.index');
    }

    /**
     * Render the purchase form with its pickers; users who may view costs also get the
     * previous purchase prices and the suppliers' current debts.
     */
    private function form(Request $request, ?Document $purchase, PreviousPrices $previousPrices, DebtLedger $debts): Response
    {
        $user = $request->user();
        $purchase?->load(['lines.product', 'lines.location.parent', 'location.parent', 'counterparty', 'payments', 'author', 'executor']);

        return Inertia::render('purchases/show', [
            'document' => $purchase ? DocumentResource::make($purchase)->resolve($request) : null,
            'editable' => $purchase === null || $user->can('update', $purchase),
            'locations' => Location::pickerOptions(Location::query()->active()),
            'products' => Product::query()->active()->orderBy('name')->get(['id', 'name', 'barcode', 'pieces_per_block']),
            'counterparties' => Counterparty::query()->orderBy('name')->get(['id', 'name']),
            'methods' => PaymentMethod::options(),
            ...($user->can(StorePermission::CostsView->value) ? [
                'previous_prices' => $previousPrices->handle($user->team_id, DocumentKind::Purchase),
                'supplier_debts' => $debts->balances($user->team_id, DebtSide::Supplier),
            ] : []),
        ]);
    }

    /**
     * Save the form as a draft and, when asked, execute it. Users who may not view costs never
     * set prices or payments: the draft keeps the ones the owner already entered. If execution
     * is refused the draft stays saved and the errors come back on its page.
     */
    private function save(SavePurchaseRequest $request, ?Document $purchase, SaveDraft $saveDraft, ExecuteDocument $executeDocument): RedirectResponse
    {
        $user = $request->user();
        $execute = $request->boolean('execute');
        abort_if($execute && ! $user->can(DocumentKind::Purchase->executePermission()->value), 403);

        $lines = $request->documentLines();
        $payments = $request->payments();

        if (! $user->can(StorePermission::CostsView->value)) {
            $prices = $purchase?->lines()->pluck('price', 'product_id')->all() ?? [];
            $lines = array_map(fn (array $line) => [...$line, 'price' => $prices[$line['product_id']] ?? null], $lines);
            $payments = $purchase?->payments()->get()
                ->mapWithKeys(fn (DocumentPayment $payment) => [$payment->payment_method->value => $payment->amount])
                ->all() ?? [];
        }

        $purchase = $saveDraft->handle($purchase, DocumentKind::Purchase, $user, $request->documentHeader(), $lines, $payments);

        if (! $execute) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Saqlandi.')]);

            return to_route('purchases.show', $purchase);
        }

        try {
            $executeDocument->handle($purchase, $user);
        } catch (ValidationException $exception) {
            return to_route('purchases.show', $purchase)->withErrors($exception->errors());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Xarid №:number bajarildi.', ['number' => $purchase->number])]);

        return to_route('purchases.show', $purchase);
    }
}
