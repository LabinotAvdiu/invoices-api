<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quote_lines', function (Blueprint $table) {
            $table->foreignId('quote_id')->after('id')->constrained('quotes')->onDelete('cascade');
            $table->string('title')->after('quote_id');
            $table->text('description')->nullable()->after('title');
            $table->decimal('quantity', 10, 3)->default(1)->after('description');
            $table->decimal('unit_price', 10, 2)->after('quantity');
            $table->decimal('tva_rate', 5, 2)->default(0)->after('unit_price');
            $table->decimal('total_ht', 10, 2)->after('tva_rate');
            $table->decimal('total_tax', 10, 2)->default(0)->after('total_ht');
            $table->decimal('total_ttc', 10, 2)->after('total_tax');
            $table->index('quote_id');
        });
    }

    public function down(): void
    {
        Schema::table('quote_lines', function (Blueprint $table) {
            if (Schema::hasColumn('quote_lines', 'quote_id')) {
                $table->dropForeign(['quote_id']);
            }
            $table->dropIndex(['quote_id']);
            $table->dropColumn(['quote_id', 'title', 'description', 'quantity', 'unit_price', 'tva_rate', 'total_ht', 'total_tax', 'total_ttc']);
        });
    }
};
