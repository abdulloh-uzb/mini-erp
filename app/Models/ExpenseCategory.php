<?php

namespace App\Models;

use App\Enums\CategoryDirection;
use App\Enums\StorePermission;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A fixed, globally seeded expense or income category (ADR-0005), shared by every store.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property CategoryDirection $direction
 * @property bool $counts_in_net_profit
 * @property bool $employee_may_use
 * @property bool $requires_note
 * @property bool $is_system
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'name', 'direction', 'counts_in_net_profit', 'employee_may_use', 'requires_note', 'is_system', 'sort_order'])]
class ExpenseCategory extends Model
{
    /**
     * Limit the query to the categories the user may record from the cash register, in display
     * order: never the system ones (_Umidsiz qarz_, used by write-offs only), and for employees
     * only those marked for them (D8).
     *
     * @param  Builder<static>  $query
     */
    #[Scope]
    protected function usableBy(Builder $query, User $user): void
    {
        $query
            ->where('is_system', false)
            ->when(! $user->can(StorePermission::OwnerExpenseCategoriesUse->value), fn (Builder $query) => $query->where('employee_may_use', true))
            ->orderBy('sort_order');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => CategoryDirection::class,
            'counts_in_net_profit' => 'boolean',
            'employee_may_use' => 'boolean',
            'requires_note' => 'boolean',
            'is_system' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
