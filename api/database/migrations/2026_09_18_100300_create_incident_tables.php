<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incident_categories', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 40)->unique();
            $table->string('name');
            $table->unsignedInteger('ttl_minutes');
            $table->timestamps();
        });

        // Bewust GEEN velden over personen. Alleen categorie, plek, korte tekst.
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete(); // wordt geleegd bij anonimisering
            $table->string('description', 200)->nullable();
            $table->unsignedInteger('confirmations')->default(0);
            $table->unsignedInteger('disputes')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('hidden_at')->nullable();
            $table->timestamp('anonymised_at')->nullable();
            $table->timestamps();
        });

        DB::statement('ALTER TABLE incidents ADD COLUMN location geography(Point, 4326) NOT NULL');
        DB::statement('CREATE INDEX incidents_location_idx ON incidents USING GIST (location)');

        Schema::create('incident_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // confirm | dispute
            $table->timestamps();
            $table->unique(['incident_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incident_votes');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('incident_categories');
    }
};
