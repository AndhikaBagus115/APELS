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
        Schema::create('diagnostic_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('speaking')->default(0);
            $table->unsignedTinyInteger('grammar')->default(0);
            $table->unsignedTinyInteger('vocabulary')->default(0);
            $table->float('overall')->default(0);
            $table->unsignedInteger('attempt')->default(1);
            $table->string('audio_path', 255)->nullable();
            $table->boolean('is_speaking_processed')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'diagnostic_results_user_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('diagnostic_results');
    }
};
