<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            if (!Schema::hasColumn('surveys', 'deleted_at')) {
                $table->softDeletes();
            }
        });
        Schema::table('survey_ratings', function (Blueprint $table) {
            if (!Schema::hasColumn('survey_ratings', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('surveys', function (Blueprint $table) {
            if (Schema::hasColumn('surveys', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
        Schema::table('survey_ratings', function (Blueprint $table) {
            if (Schema::hasColumn('survey_ratings', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};
