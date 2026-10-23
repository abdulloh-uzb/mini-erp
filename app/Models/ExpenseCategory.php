<?php

namespace App\Models;

use App\Enums\CategoryDirection;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
