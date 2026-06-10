<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migrasi tabel `modules` — Phase 1 APELS.
 *
 * Schema mengikuti Requirement 10.7 dan Requirement 30.4:
 *   - title, description, path_key, level, tag, order_index, content, is_active
 *
 * Catatan path_key:
 *   path_key WAJIB salah satu dari 8 key yang terdaftar di
 *   `config/learning_paths.php` (Req 9.3, totality AdaptiveEngineService).
 *   Validasi whitelist tidak dipasang di level DB karena enum 8-nilai tidak
 *   fleksibel terhadap perubahan konfigurasi; whitelist ditegakkan di lapisan
 *   aplikasi (Filament ModuleResource, Req 21.3) dan AdaptiveEngineService.
 *
 * Catatan tag:
 *   tag pada `modules` dibatasi `basic` | `advanced` (Req 10.7) — beda dengan
 *   `questions.tag` yang punya nilai `intermediate` juga (Req 19.2).
 *
 * Catatan description:
 *   Dibuat nullable karena Req 10.7 tidak menyebut wajib, dan modul placeholder
 *   (mis. dari seeder/fixture) bisa belum punya deskripsi final saat dibuat.
 *
 * Index:
 *   - composite `(path_key, is_active)` mempercepat query
 *     `Module::where('is_active', true)->where('path_key', $key)` yang
 *     dipanggil AdaptiveEngineService::resolveModules (Req 10.6).
 *   - `(order_index)` mempercepat sort di Learning Roadmap (Req 14.1).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('title', 255);
            $table->text('description')->nullable();
            $table->string('path_key', 50);
            $table->enum('level', ['basic', 'intermediate', 'advanced', 'professional']);
            $table->enum('tag', ['basic', 'advanced']);
            $table->unsignedInteger('order_index')->default(0);
            $table->json('content')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['path_key', 'is_active'], 'modules_path_active_idx');
            $table->index(['order_index'], 'modules_order_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};
