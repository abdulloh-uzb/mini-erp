<?php

namespace App\Http\Controllers;

use App\Actions\Documents\ExecuteDocument;
use App\Actions\Documents\SaveDraft;
use App\Enums\DocumentKind;
use App\Enums\StorePermission;
use App\Http\Requests\StoreStockReceiptRequest;
use App\Models\Document;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * The Qoldiqlar quick receipt: new pieces of one product arrive without opening Xaridlar. It
 * is an ordinary purchase, saved and executed at once, so it shows in Xaridlar, moves the
 * average cost, pays from the register and leaves the unpaid part as supplier debt.
 */
class StockReceiptController extends Controller
{
    /**
     * Receive the pieces as an executed one-line purchase and, when given, set the product's
     * new sale price. A refused execution saves nothing.
     */
    public function __invoke(StoreStockReceiptRequest $request, SaveDraft $saveDraft, ExecuteDocument $executeDocument): RedirectResponse
    {
        $user = $request->user();
        $product = Product::query()->findOrFail($request->integer('product_id'));
        $quantity = $request->quantity();
        $paid = $request->paidAmount();

        try {
            $purchase = DB::transaction(function () use ($request, $user, $product, $quantity, $paid, $saveDraft, $executeDocument): Document {
                $purchase = $saveDraft->handle(
                    null,
                    DocumentKind::Purchase,
                    $user,
                    [
                        'counterparty_id' => $request->integer('counterparty_id'),
                        'location_id' => $request->integer('location_id'),
                        'note' => null,
                    ],
                    [[
                        'product_id' => $product->id,
                        'location_id' => null,
                        'quantity' => $quantity,
                        'pieces_per_block' => $product->pieces_per_block,
                        'price' => (string) $request->validated('price'),
                    ]],
                    bccomp($paid, '0', 2) > 0 ? [$request->validated('payment_method') => $paid] : [],
                );

                $executeDocument->handle($purchase, $user);

                if ($request->validated('sale_price') !== null && $user->can(StorePermission::ProductsManage->value)) {
                    $product->update(['sale_price' => $request->validated('sale_price')]);
                }

                return $purchase;
            });
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())
                ->mapWithKeys(fn (array $messages, string $key) => [Str::startsWith($key, 'payments') ? 'paid_amount' : 'receipt' => $messages])
                ->all());
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Xarid №:number bajarildi: :product +:quantity dona.', [
            'number' => $purchase->number,
            'product' => $product->name,
            'quantity' => $quantity,
        ])]);

        return back();
    }
}
