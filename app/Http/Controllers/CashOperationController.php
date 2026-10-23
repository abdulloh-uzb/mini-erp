<?php

namespace App\Http\Controllers;

use App\Actions\Cash\CashRegister;
use App\Enums\CashOperationType;
use App\Enums\CategoryDirection;
use App\Enums\PaymentMethod;
use App\Enums\StorePermission;
use App\Http\Requests\StoreCashOperationRequest;
use App\Models\CashOperation;
use App\Models\Counterparty;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The Kassa page: the balance per payment method (owner only, D7), the list of cash
 * operations for everyone, and the expense / income form behind + Chiqim / + Kirim.
 */
class CashOperationController extends Controller
{
    /**
     * Show the balance strip and the filtered operations, newest first.
     */
    public function index(Request $request, CashRegister $register): Response
    {
        $user = $request->user();
        $filters = [
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
            'type' => $request->enum('type', CashOperationType::class),
            'payment_method' => $request->enum('payment_method', PaymentMethod::class),
            'author_id' => $request->filled('author_id') ? $request->integer('author_id') : null,
            'counterparty_id' => $request->filled('counterparty_id') ? $request->integer('counterparty_id') : null,
        ];

        $operations = CashOperation::query()
            ->filtered($filters)
            ->with(['author', 'counterparty', 'expenseCategory'])
            ->withExists('reversal')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (CashOperation $operation) => [
                'id' => $operation->id,
                'date' => $operation->created_at?->toIso8601String(),
                'type' => $operation->type->label(),
                'direction' => $operation->direction,
                'payment_method' => $operation->payment_method->label(),
                'amount' => $operation->amount,
                'category' => $operation->expenseCategory?->name,
                'counterparty' => $operation->counterparty?->name,
                'note' => $operation->note,
                'author' => $operation->author->name,
                'is_reversal' => $operation->isReversal(),
                'correctable' => $operation->isCorrectable(),
            ]);

        $categories = ExpenseCategory::query()->usableBy($user)->get();

        return Inertia::render('cash/index', [
            ...($user->can(StorePermission::CashBalanceView->value) ? ['balances' => $this->balanceStrip($register->balances($user->team))] : []),
            'operations' => $operations,
            'filters' => [
                ...$filters,
                'type' => $filters['type']?->value,
                'payment_method' => $filters['payment_method']?->value,
            ],
            'types' => CashOperationType::options(),
            'methods' => PaymentMethod::options(),
            'authors' => $user->team->users()->orderBy('name')->get(['id', 'name']),
            'counterparties' => Counterparty::query()->orderBy('name')->get(['id', 'name']),
            'categories' => [
                'expense' => $this->categoryOptions($categories, CategoryDirection::Expense),
                'income' => $this->categoryOptions($categories, CategoryDirection::Income),
            ],
        ]);
    }

    /**
     * Record an expense or income from one of the categories the user may use.
     */
    public function store(StoreCashOperationRequest $request, CashRegister $register): RedirectResponse
    {
        $category = $request->category();

        $register->record(
            $request->user(),
            $category->direction->cashOperationType(),
            $request->enum('payment_method', PaymentMethod::class),
            (string) $request->validated('amount'),
            expenseCategoryId: $category->id,
            note: $request->validated('note'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Saqlandi.')]);

        return to_route('cash.index');
    }

    /**
     * Correct an expense or income by recording its opposite (D14).
     */
    public function reverse(Request $request, CashOperation $operation, CashRegister $register): RedirectResponse
    {
        abort_unless($operation->isCorrectableKind(), 403);

        $register->reverse($operation, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Teskari operatsiya yozildi.')]);

        return to_route('cash.index');
    }

    /**
     * Add the total to the balance per method.
     *
     * @param  array<string, numeric-string>  $balances
     * @return array<string, numeric-string>
     */
    private function balanceStrip(array $balances): array
    {
        return [...$balances, 'total' => array_reduce($balances, fn (string $total, string $amount) => bcadd($total, $amount, 2), '0.00')];
    }

    /**
     * Get the categories of one direction as form options.
     *
     * @param  iterable<ExpenseCategory>  $categories
     * @return array<int, array{id: int, name: string, requires_note: bool}>
     */
    private function categoryOptions(iterable $categories, CategoryDirection $direction): array
    {
        return collect($categories)
            ->filter(fn (ExpenseCategory $category) => $category->direction === $direction)
            ->map(fn (ExpenseCategory $category) => $category->only(['id', 'name', 'requires_note']))
            ->values()
            ->all();
    }
}
