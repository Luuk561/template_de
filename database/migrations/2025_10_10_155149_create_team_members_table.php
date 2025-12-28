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
        Schema::create('team_members', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Voornaam van teamlid
            $table->string('slug')->unique(); // URL-vriendelijke slug
            $table->string('role'); // Rol (bijv. "Productliefhebber", "Tester")
            $table->text('bio'); // Uitgebreide bio (900-1200 woorden voor profielpagina)
            $table->string('quote'); // Korte motiverende quote (1 zin)
            $table->string('focus'); // Expertise focus (bijv. "gebruiksgemak", "techniek", "duurzaamheid")
            $table->string('tone'); // Schrijfstijl (bijv. "vriendelijk en praktisch")
            $table->string('photo_url')->nullable(); // URL naar profielfoto
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
