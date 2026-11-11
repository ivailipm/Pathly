<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subjects can have any colour now: `colour` holds "#rrggbb" instead of one of four names. The four named colours
 * become the colours of their text, which App\Support\Tint turns back into the same tints.
 */
return new class extends Migration
{
    private const NAMES = [
        'clay' => '#7a3b22',
        'slate' => '#2d4b5f',
        'moss' => '#4b5320',
        'plum' => '#5b3a5b',
    ];

    public function up(): void
    {
        foreach (self::NAMES as $name => $hex) {
            DB::table('subjects')->where('colour', $name)->update(['colour' => $hex]);
        }

        // Anything else was never valid; give it the default
        DB::table('subjects')->whereNotIn('colour', array_values(self::NAMES))->update(['colour' => self::NAMES['clay']]);

        Schema::table('subjects', function (Blueprint $table) {
            $table->string('colour', 7)->default(self::NAMES['clay'])->change();
        });
    }

    public function down(): void
    {
        Schema::table('subjects', function (Blueprint $table) {
            $table->string('colour')->default('clay')->change();
        });

        foreach (self::NAMES as $name => $hex) {
            DB::table('subjects')->where('colour', $hex)->update(['colour' => $name]);
        }

        // Custom colours have no name: they go back to clay
        DB::table('subjects')->whereNotIn('colour', array_keys(self::NAMES))->update(['colour' => 'clay']);
    }
};
