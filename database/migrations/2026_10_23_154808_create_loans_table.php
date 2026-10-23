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
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->foreignId('counterparty_id')->constrained()->restrictOnDelete();
            $table->string('direction');
            $table->decimal('principal', 18, 2);
            $table->date('due_date')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('document_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['team_id', 'created_at']);
        });

        Schema::create('opening_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('counterparty_id')->constrained()->restrictOnDelete();
            $table->string('direction');
            $table->decimal('principal', 18, 2);
            $table->date('due_date')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opening_loans');
        Schema::dropIfExists('loans');
    }
};
