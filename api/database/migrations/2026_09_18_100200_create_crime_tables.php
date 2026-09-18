<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ruwe aantallen per politie-delictcode, zoals geimporteerd.
        Schema::create('crime_counts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('neighbourhood_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('crime_type_code', 8);   // bijv. 1.4.6
            $table->unsignedInteger('count');
            $table->timestamp('imported_at');
            $table->unique(['neighbourhood_id', 'year', 'crime_type_code']);
        });

        // Afgeleide scores per categorie.
        Schema::create('crime_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('neighbourhood_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->string('category', 32);
            $table->unsignedInteger('count');
            $table->decimal('rate_per_1000', 10, 3)->nullable();
            $table->unsignedTinyInteger('score')->nullable();   // 0..100 percentielrang
            $table->unsignedTinyInteger('class')->nullable();   // 1..5 legendaklasse
            $table->timestamp('computed_at');
            $table->unique(['neighbourhood_id', 'year', 'category']);
            $table->index(['year', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crime_scores');
        Schema::dropIfExists('crime_counts');
    }
};
