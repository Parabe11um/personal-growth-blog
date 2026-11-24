<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicatesExist = DB::table('post_likes')
            ->select('user_id', 'post_id')
            ->groupBy('user_id', 'post_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicatesExist) {
            throw new \RuntimeException(
                'Duplicate post likes detected. Manual review required.'
            );
        }

        Schema::table('post_likes', function (Blueprint $table) {
            $table->unique(
                ['user_id', 'post_id'],
                'post_likes_user_post_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('post_likes', function (Blueprint $table) {
            $table->dropUnique(
                'post_likes_user_post_unique'
            );
        });
    }
};
