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
        Schema::create('availabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('semester_id')->constrained()->onDelete('cascade'); // Dodane
            $table->foreignId('lecturer_id')->constrained()->onDelete('cascade');
            $table->foreignId('subject_id')->nullable()->constrained()->onDelete('cascade');
            $table->date('date')->nullable(); // Zmienione na nullable
            $table->enum('block', ['1', '2', 'both'])->nullable(); // Zmienione na nullable
            $table->enum('level', ['can', 'can_if_needed'])->nullable(); // Zmienione na nullable
            $table->string('comment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('availabilities');
    }
};
