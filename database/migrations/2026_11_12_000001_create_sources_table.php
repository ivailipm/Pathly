<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            // Null for a source without a link, such as a paper book
            $table->string('url', 2048)->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['subject_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
