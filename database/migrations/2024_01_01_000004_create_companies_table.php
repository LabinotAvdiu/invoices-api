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
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['issuer', 'customer'])->default('customer');
            $table->string('name'); // Unique constraint removed, will be handled in validation
            $table->string('legal_form')->nullable(); // SARL, SAS, SA, Auto-entrepreneur, etc.
            $table->string('siret', 14)->nullable(); // 14 chiffres, unique constraint removed for issuer, will be handled in validation
            $table->text('address')->nullable(); // Adresse du siège social
            $table->string('zip_code', 10)->nullable(); // Code postal
            $table->string('city')->nullable();
            $table->string('country')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->date('creation_date')->nullable(); // Date de création
            $table->string('sector')->nullable(); // Secteur d'activité
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};


