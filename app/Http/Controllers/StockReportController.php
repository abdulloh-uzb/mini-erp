<?php

namespace App\Http\Controllers;

use App\Actions\Documents\PreviousPrices;
use App\Actions\Reports\StockReport;
use App\Enums\DocumentKind;
use App\Enums\PaymentMethod;
use App\Enums\StockLevel;
use App\Enums\StorePermission;
use App\Models\Counterparty;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockReportController extends Controller
{
    /**
     * Show current stock balances (Qoldiqlar), optionally only one stock level. Average cost
     * and value are sent only to users allowed to see costs; those who may also execute
     * purchases get what the quick receipt (+ Kirim) needs.
     */
    public function __invoke(Request $request, StockReport $report, PreviousPrices $previousPrices): Response
    {
        $user = $request->user();
        $search = $request->string('search')->trim()->value();
        $location = $request->filled('location_id')
            ? Location::query()->find($request->integer('location_id'))
            : null;
        $level = $request->enum('level', StockLevel::class);
        $withCosts = $user->can(StorePermission::CostsView->value);

        return Inertia::render('stock/index', [
            ...$report->handle($user->team, $location, $search, $withCosts, $level),
            'filters' => ['search' => $search, 'location_id' => $location?->id, 'level' => $level?->value],
            'locations' => Location::pickerOptions(Location::query()),
            ...($withCosts && $user->can(StorePermission::PurchaseExecute->value)
                ? ['receipt' => $this->receiptOptions($user, $previousPrices)]
                : []),
        ]);
    }

    /**
     * Get the quick receipt's pickers and defaults: the previous purchase price and supplier per
     * product, and each product's sale price.
     *
     * @return array<string, mixed>
     */
    private function receiptOptions(User $user, PreviousPrices $previousPrices): array
    {
        return [
            'products' => Product::query()->active()->orderBy('name')->get(['id', 'name', 'barcode', 'pieces_per_block', 'sale_price']),
            'locations' => Location::pickerOptions(Location::query()->active()),
            'counterparties' => Counterparty::query()->orderBy('name')->get(['id', 'name']),
            'methods' => PaymentMethod::options(),
            'previous_prices' => $previousPrices->handle($user->team_id, DocumentKind::Purchase),
            'previous_suppliers' => $previousPrices->suppliers($user->team_id),
        ];
    }
}
