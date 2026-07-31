<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookmarks', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->timestampsTz();

            $table->unique(['user_id', 'article_id']);
            $table->index('user_id');
            $table->index('article_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
    }
};
