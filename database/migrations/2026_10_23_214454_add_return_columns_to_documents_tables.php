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
        Schema::table('documents', function (Blueprint $table) {
            $table->string('refund_method')->nullable()->after('source_document_id');
        });

        Schema::table('document_lines', function (Blueprint $table) {
            $table->foreignId('source_line_id')->nullable()->after('location_id')->constrained('document_lines')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_lines', function (Blueprint $table) {
            $table->dropConstrainedForeignId('source_line_id');
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropColumn('refund_method');
        });
    }
};
