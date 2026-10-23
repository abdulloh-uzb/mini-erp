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
use App\Http\Requests\SaveSaleRequest;
use App\Http\Resources\DocumentResource;
use App\Models\Counterparty;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\DocumentPayment;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\StockBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The sale screen (Sotuv, D1): a fast document. Saqlash (F2) parks the sale as a draft and
 * clears the screen; Bajarish (F9) executes it and hands back a receipt with a fresh screen.
 * Average costs reach only users who may view costs.
 */
class SaleController extends Controller
{
    /**
     * Show a fresh sale screen.
     */
    public function create(Request $request, PreviousPrices $previousPrices, DebtLedger $debts): Response
    {
        return $this->screen($request, null, $previousPrices, $debts);
    }

    /**
     * Park a new sale, or execute it right away when asked.
     */
    public function store(SaveSaleRequest $request, SaveDraft $saveDraft, ExecuteDocument $executeDocument): RedirectResponse
    {
        return $this->save($request, null, $saveDraft, $executeDocument);
    }

    /**
     * Reopen a parked sale on the screen, or show an executed sale's receipt.
     */
    public function show(Request $request, Document $sale, PreviousPrices $previousPrices, DebtLedger $debts): Response
    {
        abort_unless($sale->kind === DocumentKind::Sale, 404);

        if ($sale->isDraft()) {
            Gate::authorize('update', $sale);

            return $this->screen($request, $sale, $previousPrices, $debts);
        }

        return Inertia::render('sales/show', [
            'receipt' => $this->receipt($sale),
        ]);
    }

    /**
     * Save the parked sale, or execute it when asked.
     */
    public function update(SaveSaleRequest $request, Document $sale, SaveDraft $saveDraft, ExecuteDocument $executeDocument): RedirectResponse
    {
        abort_unless($sale->kind === DocumentKind::Sale, 404);
        Gate::authorize('update', $sale);

        return $this->save($request, $sale, $saveDraft, $executeDocument);
    }

    /**
     * Delete the parked sale.
     */
    public function destroy(Document $sale): RedirectResponse
    {
        abort_unless($sale->kind === DocumentKind::Sale, 404);
        Gate::authorize('delete', $sale);

        $sale->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Qoralama o'chirildi.")]);

        return to_route('sales.create');
    }

    /**
     * Render the sale screen with everything it needs to work without further requests: the
     * catalogue, stock per location, previous sale prices, customers with their debts, the
     * parked sales and the location the user sold from last.
     */
    private function screen(Request $request, ?Document $sale, PreviousPrices $previousPrices, DebtLedger $debts): Response
    {
        $user = $request->user();
        $sale?->load(['lines.product', 'lines.location.parent', 'location.parent', 'counterparty', 'payments', 'author', 'executor']);
        $locations = Location::pickerOptions(Location::query()->active());
        $lastLocationId = Document::query()->ofKind(DocumentKind::Sale)
            ->where('author_id', $user->id)
            ->whereIn('location_id', array_column($locations, 'id'))
            ->latest('id')
            ->value('location_id');

        return Inertia::render('sales/create', [
            'document' => $sale ? DocumentResource::make($sale)->resolve($request) : null,
            'default_location_id' => $lastLocationId ?? ($locations[0]['id'] ?? null),
            'locations' => $locations,
            'products' => Product::query()->active()->orderBy('name')->get(['id', 'name', 'barcode', 'pieces_per_block']),
            'stock' => StockBalance::query()->where('quantity', '>', 0)->get(['location_id', 'product_id', 'quantity']),
            'customers' => Counterparty::query()->orderBy('name')->get(['id', 'name', 'phone']),
            'customer_debts' => $debts->balances($user->team_id, DebtSide::Customer),
            'methods' => PaymentMethod::options(),
            'previous_prices' => $previousPrices->handle($user->team_id, DocumentKind::Sale),
            'drafts' => Document::query()->ofKind(DocumentKind::Sale)
                ->where('status', DocumentStatus::Draft)
                ->when($sale, fn ($query) => $query->whereKeyNot($sale->id))
                ->select('documents.*')
                ->withTotal()
                ->with('counterparty')
                ->orderBy('id')
                ->get()
                ->map(fn (Document $draft) => [
                    'id' => $draft->id,
                    'number' => $draft->number,
                    'counterparty' => $draft->counterparty?->name,
                    'total' => bcadd((string) ($draft->total ?? 0), '0', 2),
                ]),
            ...($user->can(StorePermission::CostsView->value) ? [
                'average_costs' => ProductCost::query()->pluck('average_cost', 'product_id')
                    ->map(fn ($cost) => bcadd((string) $cost, '0', 2)),
            ] : []),
        ]);
    }

    /**
     * Park the sale and clear the screen, or execute it and hand back its receipt with a fresh
     * screen. If execution is refused the sale stays parked and reopens with the errors.
     */
    private function save(SaveSaleRequest $request, ?Document $sale, SaveDraft $saveDraft, ExecuteDocument $executeDocument): RedirectResponse
    {
        $user = $request->user();
        $execute = $request->boolean('execute');
        abort_if($execute && ! $user->can(DocumentKind::Sale->executePermission()->value), 403);

        $sale = $saveDraft->handle($sale, DocumentKind::Sale, $user, $request->documentHeader(), $request->documentLines(), $request->payments());

        if (! $execute) {
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Qoralama saqlandi.')]);

            return to_route('sales.create');
        }

        try {
            $executeDocument->handle($sale, $user);
        } catch (ValidationException $exception) {
            return to_route('sales.show', $sale)->withErrors($exception->errors());
        }

        Inertia::flash([
            'toast' => ['type' => 'success', 'message' => __('Sotuv №:number bajarildi.', ['number' => $sale->number])],
            'receipt' => $this->receipt($sale),
        ]);

        return to_route('sales.create');
    }

    /**
     * Get what the printed receipt shows of an executed sale.
     *
     * @return array{id: int, number: int, executed_at: string|null, location: string|null, customer: string|null, seller: string, lines: array<int, array{product_name: string, quantity: int, pieces_per_block: int, price: string|null}>, payments: array<int, array{method: string, amount: string}>, total: string, debt: string}
     */
    private function receipt(Document $sale): array
    {
        $sale->load(['lines.product', 'location.parent', 'counterparty', 'payments', 'executor']);
        $total = array_reduce($sale->lines->all(), fn (string $sum, DocumentLine $line) => bcadd($sum, bcmul((string) $line->quantity, $line->price ?? '0', 2), 2), '0.00');
        $paid = array_reduce($sale->payments->all(), fn (string $sum, DocumentPayment $payment) => bcadd($sum, $payment->amount, 2), '0.00');

        return [
            'id' => $sale->id,
            'number' => $sale->number,
            'executed_at' => $sale->executed_at?->toIso8601String(),
            'location' => $sale->location?->fullName(),
            'customer' => $sale->counterparty?->name,
            'seller' => $sale->executor?->name ?? '',
            'lines' => $sale->lines->map(fn (DocumentLine $line) => [
                'product_name' => $line->product->name,
                'quantity' => $line->quantity,
                'pieces_per_block' => $line->pieces_per_block,
                'price' => $line->price,
            ])->all(),
            'payments' => $sale->payments->map(fn (DocumentPayment $payment) => [
                'method' => $payment->payment_method->label(),
                'amount' => $payment->amount,
            ])->all(),
            'total' => $total,
            'debt' => bcsub($total, $paid, 2),
        ];
    }
}
