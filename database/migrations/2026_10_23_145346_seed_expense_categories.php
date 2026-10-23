<?php

use Database\Seeders\ExpenseCategorySeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Seed the fixed expense and income categories, which every store shares (ADR-0005).
     */
    public function up(): void
    {
        (new ExpenseCategorySeeder)->run();
    }
};
