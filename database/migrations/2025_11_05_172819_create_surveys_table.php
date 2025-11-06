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
        Schema::create('surveys', function (Blueprint $table) {
            $table->id();
            $table->string('service');
            $table->string('name')->nullable();
            $table->string('age')->nullable();
            $table->string('barangay')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('comments')->nullable();
            $table->timestamps(); // Adds 'created_at' and 'updated_at'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('surveys');
    }
};
