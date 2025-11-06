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
        Schema::create('invoices', function (Blueprint $table) {
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
            
            // Informations de la facture
            $table->string('number'); // Numéro de facture (ex: F-2025-0143)
            $table->enum('status', ['draft', 'sent', 'paid', 'canceled'])->default('draft');
            $table->date('issue_date')->nullable(); // Date légale d'émission
            $table->date('due_date')->nullable(); // Date limite de paiement
            $table->boolean('is_locked')->default(false); // Verrouillé après envoi (immutabilité)
            
            // Totaux
            $table->decimal('total_ht', 10, 2)->default(0); // Total HT
            $table->decimal('total_tva', 10, 2)->default(0); // TVA
            $table->decimal('total_ttc', 10, 2)->default(0); // Total TTC
            
            // Métadonnées (JSONB pour PostgreSQL)
            $table->jsonb('metadata')->nullable(); // Pour échanges PDP / Chorus etc.
            
            $table->timestamps();
            $table->softDeletes();
            
            // Index pour améliorer les performances
            $table->index('company_id');
            $table->index('customer_id');
            $table->index('status');
            $table->index('issue_date');
            $table->index('due_date');
            $table->index('is_locked');
            
            // Contrainte unique : number doit être unique par company_id
            // Le numéro de facture doit être unique pour chaque entreprise émettrice
            $table->unique(['number', 'company_id'], 'invoices_number_company_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};

