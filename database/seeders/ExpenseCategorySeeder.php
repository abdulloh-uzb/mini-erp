<?php

namespace Database\Seeders;

use App\Enums\CategoryDirection;
use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * The fixed categories every store shares (ADR-0005), in display order. They change only
     * through this seeder and a migration that runs it, never in the UI.
     *
     * @var array<int, array{code: string, name: string, direction: CategoryDirection, counts_in_net_profit: bool, employee_may_use: bool, requires_note?: bool, is_system?: bool}>
     */
    private const array CATEGORIES = [
        ['code' => 'transport', 'name' => 'Transport xarajati', 'direction' => CategoryDirection::Expense, 'counts_in_net_profit' => true, 'employee_may_use' => true],
        ['code' => 'rent', 'name' => 'Ijara', 'direction' => CategoryDirection::Expense, 'counts_in_net_profit' => true, 'employee_may_use' => true],
        ['code' => 'salary', 'name' => 'Ish haqi', 'direction' => CategoryDirection::Expense, 'counts_in_net_profit' => true, 'employee_may_use' => true],
        ['code' => 'utilities', 'name' => 'Kommunal', 'direction' => CategoryDirection::Expense, 'counts_in_net_profit' => true, 'employee_may_use' => true],
        ['code' => 'tax', 'name' => 'Soliq', 'direction' => CategoryDirection::Expense, 'counts_in_net_profit' => true, 'employee_may_use' => true],
        ['code' => 'loan-interest', 'name' => 'Qarz foizi', 'direction' => CategoryDirection::Expense, 'counts_in_net_profit' => true, 'employee_may_use' => false],
        ['code' => 'other', 'name' => 'Boshqa', 'direction' => CategoryDirection::Expense, 'counts_in_net_profit' => true, 'employee_may_use' => true, 'requires_note' => true],
        ['code' => 'owner-withdrawal', 'name' => 'Egasi olgan pul', 'direction' => CategoryDirection::Expense, 'counts_in_net_profit' => false, 'employee_may_use' => false],
        ['code' => 'bad-debt', 'name' => 'Umidsiz qarz', 'direction' => CategoryDirection::Expense, 'counts_in_net_profit' => true, 'employee_may_use' => false, 'is_system' => true],
        ['code' => 'interest-received', 'name' => 'Olingan foiz', 'direction' => CategoryDirection::Income, 'counts_in_net_profit' => true, 'employee_may_use' => false],
    ];

    /**
     * Seed the fixed expense and income categories. Safe to run repeatedly.
     */
    public function run(): void
    {
        foreach (self::CATEGORIES as $sortOrder => $category) {
            ExpenseCategory::updateOrCreate(['code' => $category['code']], [
                'requires_note' => false,
                'is_system' => false,
                ...$category,
                'sort_order' => $sortOrder + 1,
            ]);
        }
    }
}
