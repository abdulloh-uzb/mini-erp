<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('debt_entries', function (Blueprint $table) {
            $table->foreignId('expense_category_id')->nullable()->after('cash_operation_id')->constrained()->restrictOnDelete();
            $table->string('note', 1000)->nullable()->after('expense_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('debt_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_category_id');
            $table->dropColumn('note');
        });
    }
};
