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
        Schema::create('debt_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->foreignId('counterparty_id')->constrained()->restrictOnDelete();
            $table->string('side');
            $table->string('type');
            $table->decimal('amount', 18, 2);
            $table->foreignId('document_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('cash_operation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['team_id', 'side', 'counterparty_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('debt_entries');
    }
};
