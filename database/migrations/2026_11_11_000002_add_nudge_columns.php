<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Profile settings: "Remind me when a subject goes quiet"
            $table->boolean('wants_nudges')->default(true);
        });

        Schema::table('subjects', function (Blueprint $table) {
            // When the last "finish what you start" reminder mentioned this subject: one reminder per quiet spell
            $table->timestamp('nudged_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('wants_nudges');
        });

        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn('nudged_at');
        });
    }
};
