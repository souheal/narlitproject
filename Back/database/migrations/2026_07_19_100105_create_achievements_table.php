<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('achievements', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->string('key', 80)->unique();
            $table->string('title');
            $table->text('description');
            $table->string('icon', 80)->nullable();
            $table->string('category', 80)->nullable();
            $table->integer('target')->default(1);
            $table->integer('points')->default(0);
            $table->timestampsTz();
        });

        $now = now();
        DB::table('achievements')->insert([
            [
                'key' => 'first_read',
                'title' => 'First Read',
                'description' => 'Complete your first article.',
                'icon' => 'star',
                'category' => 'reading',
                'target' => 1,
                'points' => 10,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'ten_reads',
                'title' => 'Getting Hooked',
                'description' => 'Complete 10 articles.',
                'icon' => 'flame',
                'category' => 'reading',
                'target' => 10,
                'points' => 50,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'week_streak',
                'title' => 'Consistent Reader',
                'description' => 'Read on 7 days in a row.',
                'icon' => 'calendar',
                'category' => 'streak',
                'target' => 7,
                'points' => 40,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'five_organizations',
                'title' => 'Community Champion',
                'description' => 'Support 5 different nonprofits.',
                'icon' => 'heart',
                'category' => 'impact',
                'target' => 5,
                'points' => 60,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'key' => 'first_dollar',
                'title' => 'First Dollar',
                'description' => 'Direct your first dollar to a nonprofit.',
                'icon' => 'dollar',
                'category' => 'impact',
                'target' => 1,
                'points' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('achievements');
    }
};
