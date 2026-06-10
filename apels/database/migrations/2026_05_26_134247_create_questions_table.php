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
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->text('question');
            $table->string('option_a', 1000);
            $table->string('option_b', 1000);
            $table->string('option_c', 1000);
            $table->string('option_d', 1000);
            $table->enum('correct_answer', ['a', 'b', 'c', 'd']);
            $table->enum('type', ['grammar', 'vocabulary']);
            $table->enum('tag', ['basic', 'intermediate', 'advanced']);
            $table->unsignedTinyInteger('difficulty')->default(1); // 1..5 (validated in Filament/FormRequest)
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'tag', 'is_active'], 'questions_type_tag_active_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
