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
        Schema::create('quote_lines', function (Blueprint $table) {
            $table->id();
            
            // Relation avec le devis
            $table->foreignId('quote_id')->constrained('quotes')->onDelete('cascade');
            
            // Informations de la ligne
            $table->string('title'); // Titre du service / produit
            $table->text('description')->nullable(); // Description du service / produit
            
            // Quantité et prix
            $table->decimal('quantity', 12, 3)->default(1); // Quantité (avec 3 décimales pour gérer les unités fractionnées)
            $table->decimal('unit_price', 12, 2); // Prix unitaire
            
            // TVA
            $table->decimal('tva_rate', 5, 2)->default(0); // % TVA (ex: 20.00 pour 20%)
            
            // Totaux calculés
            $table->decimal('total_ht', 12, 2); // Montant HT (quantity * unit_price)
            $table->decimal('total_tax', 12, 2)->default(0); // Montant TVA
            $table->decimal('total_ttc', 12, 2); // Montant TTC (total_ht + total_tax)
            
            $table->timestamps();
            
            // Index pour améliorer les performances
            $table->index('quote_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quote_lines');
    }
};
