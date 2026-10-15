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
        Schema::create('movie_posters', function (Blueprint $table) {
            $table->id();
            $table->string('film_id')->unique()->index();
            $table->unsignedBigInteger('tmdb_id')->nullable()->index();
            $table->string('imdb_id')->nullable()->index();
            $table->string('title')->nullable();
            $table->text('poster_url')->nullable();
            $table->string('local_path')->nullable();
            $table->string('studio')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('movie_posters');
    }
};
