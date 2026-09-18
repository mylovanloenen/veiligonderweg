<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('neighbourhoods', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();        // CBS buurtcode, bijv. BU0363AA01
            $table->string('name');
            $table->string('district_code', 10)->nullable();   // wijkcode WK...
            $table->string('municipality_code', 10)->index();  // GM...
            $table->string('municipality_name')->nullable();
            $table->unsignedInteger('population')->nullable(); // null = geheim/n.v.t.
            $table->boolean('is_water')->default(false);
            $table->unsignedSmallInteger('source_year');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE neighbourhoods ADD COLUMN geom geometry(MultiPolygon, 4326)');
        DB::statement('CREATE INDEX neighbourhoods_geom_idx ON neighbourhoods USING GIST (geom)');
    }

    public function down(): void
    {
        Schema::dropIfExists('neighbourhoods');
    }
};
