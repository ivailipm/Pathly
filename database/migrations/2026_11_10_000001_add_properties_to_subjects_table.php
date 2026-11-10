<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('status')->default('active');
            $table->date('target_date')->nullable();
            $table->json('tags')->default('[]');
            $table->string('colour')->default('clay');
        });

        // R8 with literal strings, not the SubjectColour enum, so this migration never depends on app code
        $colours = ['clay', 'slate', 'moss', 'plum'];

        DB::table('subjects')->orderBy('id')->select('id')->each(function (object $subject) use ($colours) {
            DB::table('subjects')
                ->where('id', $subject->id)
                ->update(['colour' => $colours[($subject->id - 1) % 4]]);
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->dropColumn(['status', 'target_date', 'tags', 'colour']);
        });
    }
};
