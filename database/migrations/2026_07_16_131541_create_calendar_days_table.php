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
        Schema::create('calendar_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->enum('status', ['normal', 'holiday', 'conditional_holiday'])->default('normal');
            $table->boolean('is_solemnity')->default(false);
            $table->string('name')->nullable(); 
            $table->string('comment')->nullable();
            $table->json('cohort_ids')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendar_days');
    }
};
