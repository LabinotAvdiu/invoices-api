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
        Schema::create('invoice_versions', function (Blueprint $table) {
            $table->id();
            
            // Relation avec la facture
            $table->foreignId('invoice_id')->constrained('invoices')->onDelete('cascade');
            
            // Snapshot complet de la facture + lignes au moment où elle passe draft → sent
            $table->jsonb('snapshot_data'); // Copie complète de la facture + lignes
            
            // Date de gel (création automatique)
            $table->timestamp('created_at');
            
            // Index pour améliorer les performances
            $table->index('invoice_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_versions');
    }
};

