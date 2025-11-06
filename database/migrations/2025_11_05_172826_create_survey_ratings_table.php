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
        Schema::create('survey_ratings', function (Blueprint $table) {
            $table->id();
            
            // This links the rating to the survey
            $table->foreignId('survey_id')->constrained()->onDelete('cascade');
            
            $table->string('question_id'); // e.g., 'A1', 'A2', 'B1', 'C3'
            $table->string('value'); // e.g., 'Satisfied', 'Neutral'
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('survey_ratings');
    }
};
