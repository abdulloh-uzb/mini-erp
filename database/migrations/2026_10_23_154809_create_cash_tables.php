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
        Schema::create('cash_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->string('payment_method');
            $table->decimal('amount', 18, 2)->default(0);
            $table->timestamps();

            $table->unique(['team_id', 'payment_method']);
        });

        Schema::create('cash_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->string('direction');
            $table->string('payment_method');
            $table->decimal('amount', 18, 2);
            $table->foreignId('counterparty_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('expense_category_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->unique()->constrained('cash_operations')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['team_id', 'created_at']);
            $table->index(['loan_id', 'type']);
        });

        Schema::create('document_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method');
            $table->decimal('amount', 18, 2);
            $table->timestamps();

            $table->unique(['document_id', 'payment_method']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_payments');
        Schema::dropIfExists('cash_operations');
        Schema::dropIfExists('cash_balances');
    }
};
