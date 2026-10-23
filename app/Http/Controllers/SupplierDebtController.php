<?php

namespace App\Http\Controllers;

use App\Actions\Cash\DebtLedger;
use App\Enums\DebtSide;
use App\Enums\PaymentMethod;
use App\Http\Requests\StoreDebtPaymentRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Yetkazib beruvchi qarzlari (owner only): what the store still owes each supplier, and the
 * supplier payment used from this list and from Kassa → + Chiqim.
 */
class SupplierDebtController extends Controller
{
    /**
     * Show every supplier with a debt, largest first, and the total.
     */
    public function index(Request $request, DebtLedger $ledger): Response
    {
        $debts = collect($ledger->debtors($request->user()->team_id, DebtSide::Supplier))
            ->sortByDesc(fn (array $debt) => (float) $debt['balance'])
            ->values()
            ->all();

        return Inertia::render('supplier-debts/index', [
            'debts' => $debts,
            'total' => array_reduce($debts, fn (string $total, array $debt) => bcadd($total, $debt['balance'], 2), '0.00'),
            'methods' => PaymentMethod::options(),
        ]);
    }

    /**
     * Pay a supplier, up to the debt and the method's register balance.
     */
    public function pay(StoreDebtPaymentRequest $request, DebtLedger $ledger): RedirectResponse
    {
        $ledger->pay(
            $request->user(),
            $request->counterparty(),
            DebtSide::Supplier,
            (string) $request->validated('amount'),
            $request->enum('payment_method', PaymentMethod::class),
            $request->validated('note'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __("To'lov yozildi.")]);

        return back();
    }
}
