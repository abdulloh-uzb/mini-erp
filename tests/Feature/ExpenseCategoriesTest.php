<?php

use App\Models\ExpenseCategory;
use Database\Seeders\ExpenseCategorySeeder;

test('the fixed expense and income categories are seeded with their rules', function () {
    $categories = ExpenseCategory::orderBy('sort_order')->get()->map(fn (ExpenseCategory $category) => [
        $category->code,
        $category->name,
        $category->direction->value,
        $category->counts_in_net_profit,
        $category->employee_may_use,
        $category->requires_note,
        $category->is_system,
    ])->all();

    // code, name, direction, counts in net profit, employee may use, note required, system only
    expect($categories)->toBe([
        ['transport', 'Transport xarajati', 'expense', true, true, false, false],
        ['rent', 'Ijara', 'expense', true, true, false, false],
        ['salary', 'Ish haqi', 'expense', true, true, false, false],
        ['utilities', 'Kommunal', 'expense', true, true, false, false],
        ['tax', 'Soliq', 'expense', true, true, false, false],
        ['loan-interest', 'Qarz foizi', 'expense', true, false, false, false],
        ['other', 'Boshqa', 'expense', true, true, true, false],
        ['owner-withdrawal', 'Egasi olgan pul', 'expense', false, false, false, false],
        ['bad-debt', 'Umidsiz qarz', 'expense', true, false, false, true],
        ['interest-received', 'Olingan foiz', 'income', true, false, false, false],
    ]);
});

test('seeding the categories again changes nothing', function () {
    $before = ExpenseCategory::orderBy('id')->get()->toArray();

    (new ExpenseCategorySeeder)->run();

    expect(ExpenseCategory::orderBy('id')->get()->toArray())->toBe($before);
});
