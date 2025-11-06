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
        Schema::create('quotes', function (Blueprint $table) {
            $table->id();
            
            // Relations
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade'); // Émetteur (ta société)
            $table->foreignId('customer_id')->nullable()->constrained('companies')->onDelete('set null'); // Client enregistré ou null
            
            // Informations client (si non enregistré)
            $table->string('customer_name')->nullable(); // Nom du client si non enregistré
            $table->text('customer_address')->nullable(); // Adresse client si non enregistré
            $table->string('customer_zip')->nullable();
            $table->string('customer_city')->nullable();
            $table->string('customer_country')->nullable();
            
            // Informations du devis
            $table->string('number'); // Numéro du devis (ex: D-2025-0012)
            $table->enum('status', ['draft', 'sent', 'accepted', 'rejected', 'expired'])->default('draft');
            $table->date('issue_date')->nullable(); // Date du devis
            $table->date('valid_until')->nullable(); // Date limite d'acceptation
            
            // Totaux
            $table->decimal('total_ht', 10, 2)->default(0); // Total HT
            $table->decimal('total_tva', 10, 2)->default(0); // TVA
            $table->decimal('total_ttc', 10, 2)->default(0); // Total TTC
            
            // Métadonnées (JSONB pour PostgreSQL)
            $table->jsonb('metadata')->nullable(); // Pour champs/extensions
            
            $table->timestamps();
            
            // Index pour améliorer les performances
            $table->index('company_id');
            $table->index('customer_id');
            $table->index('status');
            $table->index('issue_date');
            
            // Contrainte unique : number doit être unique par customer_id
            // Le numéro de devis doit être unique pour chaque client
            // Note: Si customer_id est null, on utilise customer_name pour l'unicité
            $table->unique(['number', 'customer_id'], 'quotes_number_customer_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quotes');
    }
};
