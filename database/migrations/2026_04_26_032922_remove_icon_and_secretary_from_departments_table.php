<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropForeign(['secretary_user_id']);
            $table->dropColumn(['icon', 'secretary_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->string('icon')->nullable();
            $table->foreignId('secretary_user_id')->nullable();
            $table->foreign('secretary_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }
};
