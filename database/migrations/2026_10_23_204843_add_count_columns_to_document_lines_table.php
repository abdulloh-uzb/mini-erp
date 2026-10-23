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
        Schema::table('document_lines', function (Blueprint $table) {
            $table->unsignedInteger('expected_quantity')->nullable()->after('quantity');
            $table->decimal('difference_value', 18, 2)->nullable()->after('unit_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_lines', function (Blueprint $table) {
            $table->dropColumn(['expected_quantity', 'difference_value']);
        });
    }
};
