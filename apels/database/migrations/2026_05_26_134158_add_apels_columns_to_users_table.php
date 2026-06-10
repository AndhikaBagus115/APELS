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
        Schema::table('users', function (Blueprint $table) {
            $table->string('nim', 20)->nullable()->unique()->after('email');
            $table->unsignedTinyInteger('level')->default(1)->after('nim');
            $table->string('avatar', 255)->nullable()->after('level');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nim']);
            $table->dropColumn(['nim', 'level', 'avatar']);
        });
    }
};
