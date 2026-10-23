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
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->string('kind');
            $table->string('status');
            $table->unsignedInteger('number');
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('destination_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('counterparty_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('source_document_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('author_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('executor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->unique(['team_id', 'kind', 'number']);
            $table->index(['team_id', 'kind', 'status']);
        });

        Schema::create('document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('pieces_per_block');
            $table->decimal('price', 18, 2)->nullable();
            $table->decimal('unit_cost', 24, 6)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('document_lines');
        Schema::dropIfExists('documents');
    }
};
