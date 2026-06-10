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
        Schema::create('feedbacks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('diagnostic_result_id')->constrained('diagnostic_results')->cascadeOnDelete();
            $table->text('message');
            $table->json('details')->nullable();
            $table->string('next_focus', 100)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at'], 'feedbacks_user_created_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('feedbacks');
    }
};
