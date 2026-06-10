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
        Schema::create('user_learning_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('path_key', 50);
            $table->enum('status', ['active', 'completed'])->default('active');
            $table->timestamp('assigned_at')->nullable();
            $table->timestamps();

            $table->unique('user_id', 'user_learning_paths_user_id_unique'); // Req 26.2 single active path per user
            $table->index('path_key', 'user_learning_paths_path_key_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_learning_paths');
    }
};
